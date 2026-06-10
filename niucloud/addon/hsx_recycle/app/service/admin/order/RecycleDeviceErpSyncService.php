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

    private function buildSnapshot(array $device): array
    {
        $isConsign = (string)($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN
            || (int)($device['status'] ?? 0) === RecycleOrderDict::DEVICE_STATUS_CONSIGNED;

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
            ],
            'pricing_snapshot' => [
                'recycle_price' => round((float)($device['final_price'] ?? 0), 2),
                'suggested_sale_price' => round((float)($device['sell_price'] ?? 0), 2),
                'price_uid' => (int)($device['price_uid'] ?? 0),
                'price_at' => (int)($device['price_at'] ?? 0),
            ],
            'consignment' => $isConsign ? [
                'order_id' => (int)($device['consignment_order_id'] ?? 0),
                'order_no' => (string)($device['consignment_order']['consignment_no'] ?? ''),
                'listing_price' => round((float)($device['consignment_order']['listing_price'] ?? 0), 2),
            ] : null,
        ];
    }

    private function makeEventId(): string
    {
        return 'recycle-inbound-' . $this->site_id . '-' . date('YmdHis') . '-' . random_int(100000, 999999);
    }
}
