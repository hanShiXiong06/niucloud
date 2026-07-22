<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\service\core\recycle_device\CoreRecycleDownstreamMirrorService;
use addon\hsx_recycle\app\dict\order\RecycleDownstreamDict;

/**
 * 监听 ERP 域事件（ErpDomainEvent），把"已入库 / 转中台待拍照"回流到回收设备。
 *
 * ERP 所有域事件经 Outbox 统一以 event('ErpDomainEvent', $payload) 派发，
 * 这里按 event_name 过滤，只处理与回收设备相关的两类，其余跳过。
 * 监听器绝不抛异常，避免影响 Outbox 发布状态与其它插件的监听器。
 *
 * Class ErpAssetDownstreamListener
 * @package addon\hsx_recycle\app\listener\downstream
 */
class ErpAssetDownstreamListener
{
    public function handle(array $event): array
    {
        try {
            $name = (string)($event['event_name'] ?? '');
            // ERP 域事件 → 回收下游流转阶段。售出/下架(无商城时由ERP出库驱动)推进到 已售/下架。
            $stageMap = [
                'erp.asset.stocked.v1'         => RecycleDownstreamDict::STAGE_STOCKED,
                'erp.asset.ready_for_photo.v1' => RecycleDownstreamDict::STAGE_READY_FOR_PHOTO,
                'erp.asset.sold.v1'            => RecycleDownstreamDict::STAGE_SOLD,
                'erp.asset.returned.v1'        => RecycleDownstreamDict::STAGE_STOCKED,
                'erp.asset.delisted.v1'        => RecycleDownstreamDict::STAGE_SOLD,
                'erp.purchase_return.completed.v1' => RecycleDownstreamDict::STAGE_PURCHASE_RETURN_PENDING,
            ];
            if (!isset($stageMap[$name])) {
                return ['skipped' => true];
            }

            $payload = (array)($event['payload'] ?? []);
            if ($name === 'erp.purchase_return.completed.v1') {
                $results = [];
                foreach ((array)($payload['assets'] ?? []) as $asset) {
                    if (!is_array($asset)) continue;
                    $deviceId = (int)($asset['source_device_id'] ?? 0);
                    if ($deviceId <= 0) continue;
                    $results[] = $this->mirrorPurchaseReturn(
                        $deviceId,
                        [
                            'erp_asset_id' => (int)($asset['asset_id'] ?? 0),
                            'site_id' => (int)($event['site_id'] ?? 0),
                            'return_no' => (string)($payload['return_no'] ?? ''),
                        ],
                        (string)($event['event_id'] ?? '') . ':' . $deviceId
                    );
                }
                foreach ($results as $result) {
                    if (!empty($result['error'])) {
                        return ['consumer' => 'hsx_recycle', 'status' => 'failed', 'error' => true, 'message' => (string)($result['message'] ?? '采购退货镜像更新失败')];
                    }
                }
                return [
                    'consumer' => 'hsx_recycle',
                    'status' => $results === [] ? 'skipped' : 'processed',
                    'results' => $results,
                ];
            }
            $deviceId = (int)($payload['source_device_id'] ?? 0);
            if ($deviceId <= 0) {
                // 兜底：stocked 事件的 source.id 即回收设备ID（ready_for_photo 的 source 是入库单，不取）
                $src = (array)($event['source'] ?? []);
                if ((string)($src['type'] ?? '') !== 'stock_in') {
                    $deviceId = (int)($src['id'] ?? 0);
                }
            }

            $eventId = (string)($event['event_id'] ?? '');
            $extra = [
                'erp_asset_id' => (int)($payload['asset_id'] ?? $event['aggregate_id'] ?? 0),
                'site_id' => (int)($event['site_id'] ?? 0),
            ];

            if ($name === 'erp.asset.sold.v1' && (string)($payload['ownership_type'] ?? '') === 'consigned') {
                $result = (new CoreRecycleDownstreamMirrorService())->applyConsignmentSale($deviceId, array_merge($extra, [
                    'sale_price' => (float)($payload['sale_price'] ?? 0),
                    'consignment_settlement_amount' => (float)($payload['consignment_settlement_amount'] ?? 0),
                    'consignment_service_fee' => (float)($payload['consignment_service_fee'] ?? 0),
                    'consignment_payable_no' => (string)($payload['consignment_payable_no'] ?? ''),
                    'sale_no' => (string)($payload['outbound_no'] ?? ''),
                    'sold_at' => (int)($payload['snapshot_at'] ?? time()),
                ]), $eventId);
                return $this->consumerResult($result);
            }

            if ($name === 'erp.asset.returned.v1' && (string)($payload['ownership_type'] ?? '') === 'consigned') {
                return $this->consumerResult((new CoreRecycleDownstreamMirrorService())->applyConsignmentSaleCancellation(
                    $deviceId,
                    array_merge($extra, [
                        'sale_no' => (string)($payload['outbound_no'] ?? ''),
                        'return_reason' => (string)($payload['return_reason'] ?? ''),
                    ]),
                    $eventId
                ));
            }

            $stage = $stageMap[$name];
            return $this->consumerResult((new CoreRecycleDownstreamMirrorService())->applyStage($deviceId, $stage, $extra, $eventId));
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    protected function mirrorPurchaseReturn(int $deviceId, array $extra, string $eventId): array
    {
        return (new CoreRecycleDownstreamMirrorService())->applyPurchaseReturn($deviceId, $extra, $eventId);
    }

    private function consumerResult(array $result): array
    {
        if (!empty($result['error'])) {
            return array_merge(['consumer' => 'hsx_recycle', 'status' => 'failed'], $result);
        }
        return array_merge([
            'consumer' => 'hsx_recycle',
            'status' => !empty($result['skipped']) ? 'duplicate' : 'processed',
        ], $result);
    }
}
