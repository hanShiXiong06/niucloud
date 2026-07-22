<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\address\PhoneShopPaymentInfo;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 回收设备统一入库分发服务。
 *
 * 回收插件只生成标准设备快照并发布事件，不依赖具体 ERP 表结构。
 */
class RecycleDeviceErpSyncService extends BaseAdminService
{
    /** @var array<int,array> 同一批同步内复用会员快照，避免逐设备重复查询。 */
    private array $memberSnapshotCache = [];
    /** @var array<int,array> */
    private array $paymentMethodCache = [];
    /** @var array<int,array<string,array<string,string>>> 质检模板规格选项缓存。 */
    private array $specOptionLabelCache = [];

    public function dispatch(array $deviceIds, array $targets = ['self_erp'], array $placement = []): array
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

        if (in_array('self_erp', $targets, true)) {
            $this->ensureInboundPlacement($devices, $placement);
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
        $missingPlacement = [];
        if ((new RecycleErpCapabilityService())->isEnabled($this->site_id)) {
            $rows = RecycleDevice::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $deviceIds)
                ->field('id,target_warehouse_id,target_location_id')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                if ((int)$row['target_warehouse_id'] <= 0 || (int)$row['target_location_id'] <= 0) {
                    $missingPlacement[(int)$row['id']] = true;
                }
            }
        }
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
            if (isset($missingPlacement[$id]) && empty($merged[$id]['has_asset'])) {
                $out[$id] = [
                    'stuck' => true,
                    'has_asset' => false,
                    'repair_required' => true,
                    'reason' => '缺少 ERP 入库仓库或库位',
                ];
                continue;
            }
            $out[$id] = $merged[$id] ?? ['stuck' => false, 'has_asset' => true, 'reason' => ''];
        }
        return $out;
    }

    /**
     * 重新同步单台设备：先让 ERP 重发卡住的下游事件；若 ERP 还没这台资产，则重新 dispatch 入库后再重发。
     * @param int $deviceId
     * @return array
     */
    public function resync(int $deviceId, array $placement = []): array
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

        // 已经入库时只刷新型号/规格等来源快照，绝不重复创建采购、资产和应付。
        if (!empty($result['has_asset'])) {
            $device = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $deviceId],
            ])->with(['order', 'consignmentOrder'])->findOrEmpty();
            if (!$device->isEmpty()) {
                $responses = (array)event('RefreshErpDeviceSnapshot', [
                    'site_id' => $this->site_id,
                    'source_device_id' => $deviceId,
                    'device' => $this->buildSnapshot($device->toArray()),
                ]);
                foreach ($responses as $response) {
                    if (is_array($response) && !empty($response['snapshot_refreshed'])) {
                        $result['snapshot_refreshed'] = true;
                        break;
                    }
                }
            }
        }

        // ERP 还没这台资产 → 入库同步根本没落地：重新 dispatch 入库，再 flush 一次
        if (empty($result['has_asset'])) {
            try {
                $this->dispatch([$deviceId], ['self_erp'], $placement);
            } catch (\Throwable $e) {
                throw new CommonException('重新入库同步失败：' . $e->getMessage());
            }
            $result = $flush();
        }

        return $result;
    }

    /**
     * 新数据强校验；历史缺失数据可在重同步时传入 placement 一次性补齐。
     */
    private function ensureInboundPlacement($devices, array $placement): void
    {
        $capability = new RecycleErpCapabilityService();
        if (!$capability->isEnabled($this->site_id)) return;

        $repair = [];
        if (!empty($placement)) {
            $repairType = (string)($placement['warehouse_type'] ?? '');
            $repair = $capability->validateInboundPlacement(
                $this->site_id,
                (int)($placement['target_warehouse_id'] ?? 0),
                (int)($placement['target_location_id'] ?? 0),
                $repairType
            );
        }

        $validated = [];
        foreach ($devices as $device) {
            $warehouseId = (int)$device->target_warehouse_id;
            $locationId = (int)$device->target_location_id;
            if (($warehouseId <= 0 || $locationId <= 0) && !empty($repair)) {
                $warehouseId = (int)$repair['warehouse_id'];
                $locationId = (int)$repair['location_id'];
                $device->target_warehouse_id = $warehouseId;
                $device->target_warehouse_name = (string)$repair['warehouse_name'];
                $device->target_location_id = $locationId;
                $device->target_location_name = (string)$repair['location_name'];
                $device->save();
            }
            if ($warehouseId <= 0 || $locationId <= 0) {
                $label = trim((string)($device->imei ?: $device->model ?: ('设备#' . $device->id)));
                throw new CommonException($label . '缺少 ERP 入库仓库或库位，请先补全入库位置后再同步');
            }

            $key = $warehouseId . ':' . $locationId;
            $isConsign = (string)($device->dispose_type ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN
                || (int)($device->status ?? 0) === RecycleOrderDict::DEVICE_STATUS_CONSIGNED;
            $expectedType = $isConsign ? 'consignment' : '';
            $key .= ':' . $expectedType;
            if (!isset($validated[$key])) {
                $validated[$key] = $capability->validateInboundPlacement($this->site_id, $warehouseId, $locationId, $expectedType);
            }
        }
    }

    private function buildSnapshot(array $device): array
    {
        $isConsign = (string)($device['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN
            || (int)($device['status'] ?? 0) === RecycleOrderDict::DEVICE_STATUS_CONSIGNED;
        $memberId = (int)($device['member_id'] ?? ($device['order']['member_id'] ?? 0));
        $counterpartySourceId = $memberId > 0 ? $memberId : (int)($device['order_id'] ?? 0);
        $member = $this->memberSnapshot($memberId);
        // 回收用户本来就是牛云会员，ERP 主体优先沿用会员身份，不再用“来源客户#ID”。
        $memberName = trim((string)($member['nickname'] ?? ''));
        if ($memberName === '') $memberName = trim((string)($device['order']['customer_name'] ?? ''));
        if ($memberName === '') $memberName = trim((string)($member['username'] ?? ''));
        $memberMobile = trim((string)($member['mobile'] ?? ''));
        if ($memberMobile === '') $memberMobile = trim((string)($device['order']['customer_phone'] ?? ''));
        $spec = $this->resolveDeviceSpec($device);

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
            'capacity' => $spec['capacity_label'],
            'capacity_value' => $spec['capacity_value'],
            'color' => $spec['color_label'],
            'color_value' => $spec['color_value'],
            'payment_methods' => $this->paymentMethods($memberId),
            'ownership_type' => $isConsign ? 'consign' : 'owned',
            'purchase_cost' => $isConsign ? 0 : round((float)($device['final_price'] ?? 0), 2),
            'counterparty' => [
                'source_plugin' => $memberId > 0 ? 'niucloud' : 'hsx_recycle',
                'source_type' => $memberId > 0 ? 'member' : 'order_customer',
                'source_id' => $counterpartySourceId,
                'member_id' => $memberId,
                'counterparty_type' => 'individual',
                'role_type' => $isConsign ? 'consignor' : 'supplier',
                'name' => $memberName,
                'mobile' => $memberMobile,
                'contact_name' => $memberName,
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
                'expected_price' => round((float)($device['consignment_order']['expected_price'] ?? 0), 2),
                'min_settlement_price' => round((float)($device['consignment_order']['min_settlement_price'] ?? 0), 2),
                'listing_price' => round((float)($device['consignment_order']['listing_price'] ?? 0), 2),
                'settlement_amount' => round((float)($device['consignment_order']['settlement_amount'] ?? 0), 2),
            ] : null,
        ];
    }

    private function memberSnapshot(int $memberId): array
    {
        if ($memberId <= 0) return [];
        if (array_key_exists($memberId, $this->memberSnapshotCache)) {
            return $this->memberSnapshotCache[$memberId];
        }
        $member = Member::where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $memberId],
        ])->field('member_id,nickname,username,mobile')->findOrEmpty();
        return $this->memberSnapshotCache[$memberId] = ($member->isEmpty() ? [] : $member->toArray());
    }

    private function paymentMethods(int $memberId): array
    {
        if ($memberId <= 0) return [];
        if (array_key_exists($memberId, $this->paymentMethodCache)) {
            return $this->paymentMethodCache[$memberId];
        }
        $rows = PhoneShopPaymentInfo::where([['member_id', '=', $memberId]])
            ->field('pay_type,account,qrcode_image,is_default')
            ->order('is_default desc,id desc')->select()->toArray();
        return $this->paymentMethodCache[$memberId] = array_values(array_map(static fn(array $row): array => [
            'pay_type' => trim((string)($row['pay_type'] ?? '')),
            'account' => trim((string)($row['account'] ?? '')),
            'qrcode_image' => trim((string)($row['qrcode_image'] ?? '')),
            'is_default' => (int)($row['is_default'] ?? 0),
        ], $rows));
    }

    /**
     * ERP 快照必须携带可读文案，不能把回收模板内部的选项 ID 当作规格展示。
     * 原始值同时保留，方便审计以及模板选项调整后的重新同步。
     */
    private function resolveDeviceSpec(array $device): array
    {
        $templateId = (int)($device['check_template_id'] ?? 0);
        if ($templateId > 0 && !array_key_exists($templateId, $this->specOptionLabelCache)) {
            $maps = DeviceSummaryHelper::buildOptionLabelMap(
                [$templateId],
                ['capacity', 'color'],
                (int)$this->site_id
            );
            $this->specOptionLabelCache[$templateId] = (array)($maps[$templateId] ?? []);
        }

        $optionMap = $this->specOptionLabelCache[$templateId] ?? [];
        $capacityValue = $device['capacity'] ?? '';
        $colorValue = $device['color'] ?? '';

        return [
            'capacity_value' => is_array($capacityValue) ? $capacityValue : trim((string)$capacityValue),
            'capacity_label' => DeviceSummaryHelper::resolveDisplayValue($capacityValue, (array)($optionMap['capacity'] ?? [])),
            'color_value' => is_array($colorValue) ? $colorValue : trim((string)$colorValue),
            'color_label' => DeviceSummaryHelper::resolveDisplayValue($colorValue, (array)($optionMap['color'] ?? [])),
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
