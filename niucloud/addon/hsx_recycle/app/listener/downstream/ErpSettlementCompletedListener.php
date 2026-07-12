<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDownstreamMirrorService;
use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;

/** 消费 ERP 可重试结算领域事件，把已全额结清的回收采购设备回写为已结清。 */
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
            foreach ((array)($payload['targets'] ?? []) as $target) {
                if (!is_array($target)) continue;
                $targetType = (string)($target['target_type'] ?? '');
                if ($targetType === 'receivable' && (string)($target['source_type'] ?? '') === 'purchase_return') {
                    if ((float)($target['remaining_amount'] ?? 0) > 0.0001) continue;
                    foreach ((array)($target['assets'] ?? []) as $asset) {
                        if (!is_array($asset)) continue;
                        if ((string)($asset['source_plugin'] ?? '') !== 'hsx_recycle') continue;
                        $sourceDeviceId = (int)($asset['source_device_id'] ?? 0);
                        if ($sourceDeviceId > 0) $purchaseReturnDeviceIds[] = $sourceDeviceId;
                    }
                    continue;
                }
                if ($targetType !== 'payable') continue;
                $origin = (array)($target['origin'] ?? []);
                if ((string)($origin['plugin'] ?? '') !== 'hsx_recycle') continue;
                // 部分付款/部分折账不能把来源设备提前标成已结清。
                if ((float)($target['remaining_amount'] ?? 0) > 0.0001) continue;
                foreach ((array)($target['assets'] ?? []) as $asset) {
                    if (!is_array($asset)) continue;
                    if ((string)($asset['source_plugin'] ?? '') !== '' && (string)$asset['source_plugin'] !== 'hsx_recycle') continue;
                    $sourceDeviceId = (int)($asset['source_device_id'] ?? 0);
                    if ($sourceDeviceId > 0) $deviceIds[] = $sourceDeviceId;
                }
            }
            $deviceIds = array_values(array_unique($deviceIds));
            $purchaseReturnDeviceIds = array_values(array_unique($purchaseReturnDeviceIds));
            $marked = 0;
            if ($deviceIds !== []) {
                $marked = $this->settleDevices(
                    $deviceIds,
                    (string)($payload['settlement_no'] ?? ''),
                    [
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
                $result = (new CoreRecycleDownstreamMirrorService())->applyStage(
                    $deviceId,
                    RecycleDownstreamDict::STAGE_PURCHASE_RETURN_SETTLED,
                    ['site_id' => (int)($event['site_id'] ?? 0)],
                    (string)($event['event_id'] ?? '') . ':' . $deviceId
                );
                if (!empty($result['updated'])) $mirrored++;
            }
            if ($deviceIds === [] && $purchaseReturnDeviceIds === []) {
                return ['consumer' => 'hsx_recycle', 'status' => 'skipped'];
            }
            return [
                'consumer' => 'hsx_recycle',
                'status' => ($marked > 0 || $mirrored > 0) ? 'processed' : 'duplicate',
                'settlement_no' => (string)($payload['settlement_no'] ?? ''),
                'device_ids' => $deviceIds,
                'marked_count' => $marked,
                'purchase_return_device_ids' => $purchaseReturnDeviceIds,
                'purchase_return_mirrored_count' => $mirrored,
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
}
