<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\service\core\ErpInboundService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * ERP 独立建档入口。
 *
 * 这里只负责将后台录入转换为标准入库事件，真正的资产、周期和入库单
 * 仍统一由 ErpInboundService 创建。
 */
class ErpStandaloneInboundService extends BaseAdminService
{
    public function create(array $data): array
    {
        $imei = trim((string)($data['imei'] ?? ''));
        $sn = trim((string)($data['sn'] ?? ''));
        $model = trim((string)($data['model'] ?? ''));
        if ($imei === '' && $sn === '') {
            throw new CommonException('IMEI 和 SN 至少填写一个');
        }
        if ($model === '') {
            throw new CommonException('请填写设备型号');
        }

        $sourceDeviceId = $this->makeSourceDeviceId();
        $now = time();
        $event = [
            'event_id' => 'erp-manual-inbound-' . $this->site_id . '-' . date('YmdHis') . '-' . random_int(100000, 999999),
            'event_name' => 'erp.manual.inbound_requested',
            'event_version' => 1,
            'site_id' => $this->site_id,
            'occurred_at' => $now,
            'targets' => ['self_erp'],
            'operator' => [
                'type' => 'staff',
                'id' => $this->uid,
                'name' => $this->username ?: '',
            ],
            'source' => [
                'plugin' => 'hsx_erp',
                'type' => 'manual_inbound',
                'id' => $sourceDeviceId,
            ],
            'remark' => trim((string)($data['remark'] ?? '')),
            'devices' => [[
                'source_id' => $sourceDeviceId,
                'source_device_id' => $sourceDeviceId,
                'imei' => $imei,
                'imei2' => trim((string)($data['imei2'] ?? '')),
                'sn' => $sn,
                'model' => $model,
                'category_id' => (int)($data['category_id'] ?? 0),
                'capacity' => trim((string)($data['capacity'] ?? '')),
                'color' => trim((string)($data['color'] ?? '')),
                'ownership_type' => (string)($data['ownership_type'] ?? 'owned'),
                'purchase_cost' => round((float)($data['purchase_cost'] ?? 0), 2),
                'suggested_sale_price' => round((float)($data['suggested_sale_price'] ?? 0), 2),
                'acquired_at' => $now,
                'check_snapshot' => [],
                'pricing_snapshot' => [
                    'purchase_cost' => round((float)($data['purchase_cost'] ?? 0), 2),
                    'suggested_sale_price' => round((float)($data['suggested_sale_price'] ?? 0), 2),
                ],
                'manual_input' => [
                    'operator_id' => $this->uid,
                    'operator_name' => $this->username ?: '',
                    'remark' => trim((string)($data['remark'] ?? '')),
                ],
            ]],
        ];

        return (new ErpInboundService())->receive($event);
    }

    private function makeSourceDeviceId(): int
    {
        do {
            $id = random_int(100000000, 2147483647);
            $exists = ErpAssetCycle::where([
                ['site_id', '=', $this->site_id],
                ['source_plugin', '=', 'hsx_erp'],
                ['source_type', '=', 'manual_inbound'],
                ['source_device_id', '=', $id],
            ])->count();
        } while ($exists > 0);

        return $id;
    }
}
