<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\service\admin\ErpAssetService;
use addon\hsx_erp\app\service\core\ErpInboundService;

class DeviceInboundRequestedListener
{
    public function handle(array $event): array
    {
        $targets = array_values(array_unique((array)($event['targets'] ?? [])));
        if (!in_array(ErpDict::TARGET_SELF_ERP, $targets, true)) {
            return [
                'target' => ErpDict::TARGET_SELF_ERP,
                'skipped' => true,
            ];
        }

        $result = (new ErpInboundService())->receive($event);

        // A1 打款自动入库：回收来源(质检在店=已到货)且定价已选定仓位的设备，
        // 入库后直接确认入库到该仓位，跳过"待入库"人工步骤。
        // 故障隔离：任何一台失败/未选仓位即跳过 → 该台留在"待入库"由人工确认，绝不影响主流程。
        try {
            $this->autoConfirmArrived($event, $result);
        } catch (\Throwable $e) {
        }

        return $result;
    }

    /**
     * 已到货自动确认入库（仅处理定价已选定"仓库+库位"的设备）
     */
    private function autoConfirmArrived(array $event, array $result): void
    {
        $created = (array)($result['created_assets'] ?? []);
        if (empty($created)) {
            return;
        }

        // source_device_id → 定价选定的目标仓位
        $targetMap = [];
        foreach ((array)($event['devices'] ?? []) as $device) {
            $sid = (int)($device['source_device_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $targetMap[$sid] = [
                'warehouse_id' => (int)($device['target_warehouse_id'] ?? 0),
                'location_id' => (int)($device['target_location_id'] ?? 0),
            ];
        }

        $assetService = new ErpAssetService();
        foreach ($created as $asset) {
            $sid = (int)($asset['source_device_id'] ?? 0);
            $assetId = (int)($asset['id'] ?? 0);
            $target = $targetMap[$sid] ?? null;
            // 未选定仓位(仓库或库位缺失)→ 留待入库人工选位确认
            if ($assetId <= 0 || !$target || $target['warehouse_id'] <= 0 || $target['location_id'] <= 0) {
                continue;
            }
            try {
                $assetService->confirmInboundByAsset($assetId, [
                    'warehouse_id' => $target['warehouse_id'],
                    'location_id' => $target['location_id'],
                    'remark' => '打款已到货，自动确认入库',
                ]);
            } catch (\Throwable $e) {
                // 单台失败不影响其它；该台留在待入库由人工处理
            }
        }
    }
}
