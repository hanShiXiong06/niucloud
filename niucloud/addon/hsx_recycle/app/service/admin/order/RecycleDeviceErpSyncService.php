<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 回收设备统一入库分发服务。
 *
 * 回收插件只生成标准设备快照并发布事件，不依赖具体 ERP 表结构。
 */
class RecycleDeviceErpSyncService extends BaseAdminService
{
    public function dispatch(array $deviceIds, array $targets = ['self_erp']): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        $targets = array_values(array_unique(array_filter(array_map('strval', $targets))));
        if (empty($deviceIds)) {
            throw new CommonException('请选择需要同步的设备');
        }
        if (empty($targets)) {
            throw new CommonException('请选择同步目标');
        }

        $devices = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('id', $deviceIds)->with(['order', 'consignmentOrder'])->select();
        if ($devices->count() !== count($deviceIds)) {
            throw new CommonException('部分设备不存在或不属于当前站点');
        }

        $snapshots = [];
        foreach ($devices as $device) {
            $status = (int)$device->status;
            if (!in_array($status, [
                RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
            ], true)) {
                throw new CommonException(
                    ($device->imei ?: $device->model ?: ('设备#' . $device->id)) . '尚未完成回收或代卖，不能同步入库'
                );
            }
            $snapshots[] = $this->buildSnapshot($device->toArray());
        }

        $event = [
            'event_id' => $this->makeEventId(),
            'event_name' => 'recycle.device.inbound_requested',
            'event_version' => 1,
            'site_id' => $this->site_id,
            'occurred_at' => time(),
            'targets' => $targets,
            'operator' => [
                'type' => 'staff',
                'id' => $this->uid,
                'name' => $this->username ?: '',
            ],
            'source' => [
                'plugin' => 'hsx_recycle',
                'type' => 'recycle_device',
                'id' => count($deviceIds) === 1 ? $deviceIds[0] : 0,
            ],
            'devices' => $snapshots,
        ];

        $results = array_values(array_filter(event('ErpDeviceInboundRequested', $event)));
        if (empty($results)) {
            throw new CommonException('没有可用的 ERP 接收器，请先安装并启用 ERP 插件');
        }

        return [
            'event_id' => $event['event_id'],
            'device_count' => count($snapshots),
            'targets' => $targets,
            'results' => $results,
        ];
    }

    /**
     * 批量查询设备的下游同步健康度（仅"卡住"的需要显示「重新同步」）。
     * 解耦：发 GetErpDeviceSyncHealth 事件向 ERP 问；ERP 未装则无人应答 → 全部视为健康(stuck=false)，前端不显示按钮。
     * @param array $deviceIds
     * @return array device_id => ['stuck'=>bool, ...]
     */
    public function syncHealth(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            return [];
        }

        $merged = [];
        try {
            $results = (array)event('GetErpDeviceSyncHealth', [
                'site_id' => $this->site_id,
                'source_device_ids' => $deviceIds,
            ]);
            foreach ($results as $resp) {
                if (is_array($resp)) {
                    foreach ($resp as $sid => $info) {
                        if (is_array($info)) {
                            $merged[(int)$sid] = $info;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $merged = [];
        }

        // 没有应答(ERP 未装)的设备 → 视为健康，不显示按钮
        $out = [];
        foreach ($deviceIds as $id) {
            $out[$id] = $merged[$id] ?? ['stuck' => false, 'has_asset' => true, 'reason' => ''];
        }
        return $out;
    }

    /**
     * 重新同步单台设备：先让 ERP 重发卡住的下游事件；若 ERP 还没这台资产，则重新 dispatch 入库后再重发。
     * @param int $deviceId
     * @return array
     */
    public function resync(int $deviceId): array
    {
        $deviceId = (int)$deviceId;
        if ($deviceId <= 0) {
            throw new CommonException('设备不存在');
        }

        $flush = function () use ($deviceId): array {
            $merged = ['has_asset' => false, 'asset_id' => 0, 'flushed' => 0, 'still_failed' => 0];
            $results = (array)event('ResyncErpDevice', [
                'site_id' => $this->site_id,
                'source_device_id' => $deviceId,
            ]);
            foreach ($results as $resp) {
                if (is_array($resp) && array_key_exists('has_asset', $resp)) {
                    $merged = array_merge($merged, $resp);
                    break;
                }
            }
            return $merged;
        };

        $result = $flush();

        // ERP 还没这台资产 → 入库同步根本没落地：重新 dispatch 入库，再 flush 一次
        if (empty($result['has_asset'])) {
            try {
                $this->dispatch([$deviceId], ['self_erp']);
            } catch (\Throwable $e) {
                throw new CommonException('重新入库同步失败：' . $e->getMessage());
            }
            $result = $flush();
        }

        return $result;
    }

    private function buildSnapshot(array $device): array
    {
        $isConsign = (string)($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN
            || (int)($device['status'] ?? 0) === RecycleOrderDict::DEVICE_STATUS_CONSIGNED;
        $memberId = (int)($device['member_id'] ?? ($device['order']['member_id'] ?? 0));
        $counterpartySourceId = $memberId > 0 ? $memberId : (int)($device['order_id'] ?? 0);

        return [
            'source_id' => (int)($device['order_id'] ?? 0),
            'source_device_id' => (int)$device['id'],
            'source_order_no' => (string)($device['order']['order_no'] ?? ''),
            'member_id' => (int)($device['member_id'] ?? ($device['order']['member_id'] ?? 0)),
            'imei' => (string)($device['imei'] ?? ''),
            'imei2' => (string)($device['imei2'] ?? ''),
            'sn' => (string)($device['sn'] ?? ''),
            'model' => (string)($device['model'] ?? ''),
            'category_id' => (int)($device['category_id'] ?? 0),
            'capacity' => (string)($device['capacity'] ?? ''),
            'color' => (string)($device['color'] ?? ''),
            'ownership_type' => $isConsign ? 'consign' : 'owned',
            'purchase_cost' => $isConsign ? 0 : round((float)($device['final_price'] ?? 0), 2),
            'counterparty' => [
                'source_plugin' => $memberId > 0 ? 'niucloud' : 'hsx_recycle',
                'source_type' => $memberId > 0 ? 'member' : 'order_customer',
                'source_id' => $counterpartySourceId,
                'counterparty_type' => 'individual',
                'role_type' => $isConsign ? 'consignor' : 'supplier',
                'name' => (string)($device['order']['customer_name'] ?? ''),
                'mobile' => (string)($device['order']['customer_phone'] ?? ''),
                'contact_name' => (string)($device['order']['customer_name'] ?? ''),
            ],
            'paid_amount' => $isConsign ? 0 : round((float)($device['pay_amount'] ?? 0), 2),
            'settlement_status' => $isConsign
                ? 'consignment'
                : ((int)($device['pay_status'] ?? 0) === 1 ? 'paid' : 'unpaid'),
            'sale_destination' => (string)($device['sale_destination'] ?? RecycleOrderDict::SALE_DESTINATION_MALL),
            'target_warehouse_id' => (int)($device['target_warehouse_id'] ?? 0),
            'target_warehouse_name' => (string)($device['target_warehouse_name'] ?? ''),
            'target_location_id' => (int)($device['target_location_id'] ?? 0),
            'target_location_name' => (string)($device['target_location_name'] ?? ''),
            'suggested_sale_price' => round((float)($device['sell_price'] ?? 0), 2),
            'acquired_at' => (int)($device['pay_time'] ?? $device['update_at'] ?? time()),
            'check_snapshot' => [
                'check_template_id' => (int)($device['check_template_id'] ?? 0),
                'check_result' => (string)($device['check_result'] ?? ''),
                'check_result_seller' => (string)($device['check_result_seller'] ?? ''),
                'check_result_buyer' => (string)($device['check_result_buyer'] ?? ''),
                'check_images' => (string)($device['check_images'] ?? ''),
                'check_images_seller' => (string)($device['check_images_seller'] ?? ''),
                'check_images_buyer' => (string)($device['check_images_buyer'] ?? ''),
                'check_uid' => (int)($device['check_uid'] ?? 0),
                'check_at' => (int)($device['check_at'] ?? 0),
                // 质检员手填的补充备注，透传给中台展示（模板覆盖不到的关键信息）
                'check_remark' => (string)($device['remark'] ?? ''),
            ],
            'recycle_pricing_snapshot' => [
                'recycle_price' => round((float)($device['final_price'] ?? 0), 2),
                'suggested_sale_price' => round((float)($device['sell_price'] ?? 0), 2),
                'sale_destination' => (string)($device['sale_destination'] ?? RecycleOrderDict::SALE_DESTINATION_MALL),
                'price_uid' => (int)($device['price_uid'] ?? 0),
                'price_at' => (int)($device['price_at'] ?? 0),
            ],
            'pricing_snapshot' => [
                'pricing_type' => 'recycle',
                'recycle_price' => round((float)($device['final_price'] ?? 0), 2),
                'suggested_sale_price' => round((float)($device['sell_price'] ?? 0), 2),
                'sale_destination' => (string)($device['sale_destination'] ?? RecycleOrderDict::SALE_DESTINATION_MALL),
                'price_uid' => (int)($device['price_uid'] ?? 0),
                'price_at' => (int)($device['price_at'] ?? 0),
            ],
            'refurbishment' => [
                'required' => (bool)($device['refurbishment_required'] ?? false),
                'decision_source' => array_key_exists('refurbishment_required', $device) ? 'hsx_recycle' : 'default',
                'reason' => trim((string)($device['refurbishment_reason'] ?? '')),
                'suggested_items' => $this->normalizeRefurbishmentItems($device['refurbishment_items'] ?? []),
                'estimated_cost' => round((float)($device['refurbishment_estimated_cost'] ?? 0), 2),
                'decided_by' => [
                    'type' => 'staff',
                    'id' => (int)($device['price_uid'] ?? $device['check_uid'] ?? 0),
                    'name' => '',
                ],
                'assignee' => [
                    'type' => 'staff',
                    'id' => (int)($device['refurbishment_assignee_uid'] ?? 0),
                    'name' => (string)($device['refurbishment_assignee_name'] ?? ''),
                ],
                'decided_at' => (int)($device['price_at'] ?? $device['check_at'] ?? 0),
            ],
            'consignment' => $isConsign ? [
                'order_id' => (int)($device['consignment_order_id'] ?? 0),
                'order_no' => (string)($device['consignment_order']['consignment_no'] ?? ''),
                'listing_price' => round((float)($device['consignment_order']['listing_price'] ?? 0), 2),
            ] : null,
        ];
    }

    private function normalizeRefurbishmentItems($items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }
        return is_array($items) ? array_values($items) : [];
    }

    private function makeEventId(): string
    {
        return 'recycle-inbound-' . $this->site_id . '-' . date('YmdHis') . '-' . random_int(100000, 999999);
    }
}
