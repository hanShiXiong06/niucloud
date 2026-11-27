<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDownstreamMirrorService;
use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;

/** 消费 ERP 可重试结算事件，分别记录本次流水与累计已付；只有全额结清才推进完成。 */
class ErpSettlementCompletedListener
{
    public function handle(array $event): array
    {
        if ((string)($event['event_name'] ?? '') !== 'erp.settlement.completed.v1') {
            return ['consumer' => 'hsx_recycle', 'status' => 'skipped'];
        }
        try {
            $payload = (array)($event['payload'] ?? []);
            $settlementType = (string)($payload['settlement_type'] ?? '');
            $method = $settlementType === 'payment' ? 'payment' : ($settlementType === 'offset' ? 'offset' : 'mixed');
            $deviceIds = [];
            $purchaseReturnDeviceIds = [];
            $partialDeviceIds = [];
            $purchaseSnapshots = [];
            foreach ((array)($payload['targets'] ?? []) as $target) {
                if (!is_array($target)) continue;
                $targetType = (string)($target['target_type'] ?? '');
                if ($targetType === 'receivable' && (string)($target['source_type'] ?? '') === 'purchase_return') {
                    foreach ((array)($target['assets'] ?? []) as $asset) {
                        if (!is_array($asset)) continue;
                        if ((string)($asset['source_plugin'] ?? '') !== 'hsx_recycle') continue;
                        $sourceDeviceId = (int)($asset['source_device_id'] ?? 0);
                        if ($sourceDeviceId <= 0) continue;
                        if ((float)($target['remaining_amount'] ?? 0) > 0.0001) {
                            $partialDeviceIds[] = $sourceDeviceId;
                        } else {
                            $purchaseReturnDeviceIds[] = $sourceDeviceId;
                        }
                    }
                    continue;
                }
                if ($targetType !== 'payable') continue;
                $origin = (array)($target['origin'] ?? []);
                if ((string)($origin['plugin'] ?? '') !== 'hsx_recycle') continue;
                foreach ((array)($target['assets'] ?? []) as $asset) {
                    if (!is_array($asset)) continue;
                    if ((string)($asset['source_plugin'] ?? '') !== '' && (string)$asset['source_plugin'] !== 'hsx_recycle') continue;
                    $sourceDeviceId = (int)($asset['source_device_id'] ?? 0);
                    if ($sourceDeviceId <= 0) continue;
                    if ((string)($target['source_type'] ?? '') === 'purchase_asset'
                        && count((array)($target['assets'] ?? [])) === 1
                        && isset($target['target_amount'], $target['settled_amount'], $target['applied_amount'], $target['remaining_amount'])) {
                        if (isset($purchaseSnapshots[$sourceDeviceId])) {
                            throw new \RuntimeException('同一结算中设备对应多份采购快照，请核对来源应付后重试');
                        }
                        $purchaseSnapshots[$sourceDeviceId] = $target;
                        continue;
                    }
                    // 已接收本插件的部分结算事实，但不能提前推进为全额结清。
                    if ((float)($target['remaining_amount'] ?? 0) > 0.0001) {
                        $partialDeviceIds[] = $sourceDeviceId;
                    } else {
                        $deviceIds[] = $sourceDeviceId;
                    }
                }
            }
            $deviceIds = array_values(array_unique($deviceIds));
            $purchaseReturnDeviceIds = array_values(array_unique($purchaseReturnDeviceIds));
            $partialDeviceIds = array_values(array_unique($partialDeviceIds));
            $marked = 0;
            foreach ($purchaseSnapshots as $deviceId => $snapshot) {
                $marked += $this->settlePurchaseSnapshot((int)($event['site_id'] ?? 0), (int)$deviceId,
                    (string)($payload['settlement_no'] ?? ''), $snapshot, [
                        'method' => $method, 'operator' => (string)($event['operator']['name'] ?? 'ERP财务'),
                        'account' => (string)($payload['capital_account_name'] ?? ''),
                        'confirmed_at' => (int)($payload['confirmed_at'] ?? 0),
                    ]);
            }
            if ($deviceIds !== []) {
                $marked += $this->settleDevices(
                    $deviceIds,
                    (string)($payload['settlement_no'] ?? ''),
                    [
                        'site_id' => (int)($event['site_id'] ?? 0),
                        'method' => $method,
                        'operator' => (string)($event['operator']['name'] ?? 'ERP财务'),
                        'account' => (string)($payload['capital_account_name'] ?? ''),
                        'offset' => $settlementType === 'offset' ? (float)($payload['amount'] ?? 0) : 0,
                        'cash' => $settlementType === 'payment' ? (float)($payload['amount'] ?? 0) : 0,
                    ]
                );
            }
            $mirrored = 0;
            foreach ($purchaseReturnDeviceIds as $deviceId) {
                $result = $this->mirrorPurchaseReturnSettlement(
                    $deviceId,
                    RecycleDownstreamDict::STAGE_PURCHASE_RETURN_SETTLED,
                    ['site_id' => (int)($event['site_id'] ?? 0)],
                    (string)($event['event_id'] ?? '') . ':' . $deviceId
                );
                if (!empty($result['error']) || (string)($result['status'] ?? '') === 'failed') {
                    throw new \RuntimeException((string)($result['message'] ?? '采购退货结算镜像更新失败'));
                }
                if (!empty($result['updated'])) $mirrored++;
            }
            if ($deviceIds === [] && $purchaseReturnDeviceIds === [] && $partialDeviceIds === [] && $purchaseSnapshots === []) {
                return ['consumer' => 'hsx_recycle', 'status' => 'skipped'];
            }
            return [
                'consumer' => 'hsx_recycle',
                'status' => ($marked > 0 || $mirrored > 0 || $partialDeviceIds !== []) ? 'processed' : 'duplicate',
                'settlement_no' => (string)($payload['settlement_no'] ?? ''),
                'device_ids' => $deviceIds,
                'marked_count' => $marked,
                'purchase_return_device_ids' => $purchaseReturnDeviceIds,
                'purchase_return_mirrored_count' => $mirrored,
                'partial_device_ids' => $partialDeviceIds,
                'purchase_snapshot_device_ids' => array_keys($purchaseSnapshots),
            ];
        } catch (\Throwable $e) {
            // 返回 error 让 ERP Outbox 保持 failed 并按原 event_id 重试。
            return ['consumer' => 'hsx_recycle', 'status' => 'failed', 'error' => true, 'message' => $e->getMessage()];
        }
    }

    protected function settleDevices(array $deviceIds, string $settlementNo, array $info): int
    {
        return (new RecycleDevicePaymentService())->settleByOffset($deviceIds, $settlementNo, $info);
    }

    protected function settlePurchaseSnapshot(int $siteId, int $deviceId, string $settlementNo, array $snapshot, array $info): int
    {
        return (new RecycleDevicePaymentService())->applyErpPurchaseSettlement($siteId, $deviceId, $settlementNo, $snapshot, $info);
    }

    protected function mirrorPurchaseReturnSettlement(int $deviceId, int $stage, array $extra, string $eventId): array
    {
        return (new CoreRecycleDownstreamMirrorService())->applyStage($deviceId, $stage, $extra, $eventId);
    }
}
