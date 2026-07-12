<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use app\service\core\site\CoreSiteService;

/** 统一判断当前站点是否由 ERP 接管回收财务。 */
class RecycleErpCapabilityService
{
    public const ERP_ADDON = 'hsx_erp';
    public const ERP_PAYABLE_PATH = '/site/hsx_erp/payable';

    public function isEnabled(int $siteId): bool
    {
        if ($siteId <= 0) return false;

        try {
            $addons = (new CoreSiteService())->getAddonKeysBySiteId($siteId);
            if (!in_array(self::ERP_ADDON, $addons, true)) return false;

            return class_exists('\\addon\\hsx_erp\\app\\service\\admin\\ErpFinanceService');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isPaymentManaged(int $siteId): bool
    {
        return $this->isEnabled($siteId);
    }

    /**
     * 校验 ERP 入库位置，并以 ERP 主数据中的名称为准。
     * 通过字符串类名保持回收插件可独立安装。
     */
    public function validateInboundPlacement(int $siteId, int $warehouseId, int $locationId): array
    {
        if (!$this->isEnabled($siteId)) return [];
        if ($warehouseId <= 0 || $locationId <= 0) {
            throw new \core\exception\CommonException('ERP 联动已开启，请选择入库仓库和具体库位');
        }

        $serviceClass = '\\addon\\hsx_erp\\app\\service\\admin\\ErpWarehouseService';
        if (!class_exists($serviceClass)) {
            throw new \core\exception\CommonException('ERP 仓库服务不可用，请检查插件安装状态');
        }

        [$warehouse, $location] = (new $serviceClass())->validateInboundLocation($warehouseId, $locationId);
        return [
            'warehouse_id' => (int)$warehouse->id,
            'warehouse_name' => (string)$warehouse->warehouse_name,
            'location_id' => (int)$location->id,
            'location_name' => (string)$location->location_name,
        ];
    }

    /** 历史异常数据手动重同步时，回退到默认仓库的首个可用库位。 */
    public function defaultInboundPlacement(int $siteId): array
    {
        if (!$this->isEnabled($siteId)) return [];
        $serviceClass = '\\addon\\hsx_erp\\app\\service\\admin\\ErpWarehouseService';
        if (!class_exists($serviceClass)) return [];
        $warehouses = (new $serviceClass())->getOptions();
        if (empty($warehouses)) return [];
        $warehouse = null;
        foreach ($warehouses as $item) {
            if ((int)($item['is_default'] ?? 0) === 1 && !empty($item['locations'])) {
                $warehouse = $item;
                break;
            }
        }
        if ($warehouse === null) {
            foreach ($warehouses as $item) {
                if (!empty($item['locations'])) {
                    $warehouse = $item;
                    break;
                }
            }
        }
        if ($warehouse === null) return [];
        $location = array_values($warehouse['locations'])[0];
        return [
            'target_warehouse_id' => (int)$warehouse['id'],
            'target_location_id' => (int)$location['id'],
        ];
    }

    public function paymentCapability(int $siteId): array
    {
        $managed = $this->isPaymentManaged($siteId);

        return [
            'erp_connected' => $managed,
            'payment_managed_by_erp' => $managed,
            'payment_path' => $managed ? self::ERP_PAYABLE_PATH : '',
            'message' => $managed
                ? '当前站点财务已由 ERP 接管，请到“二手机 ERP - 应付款”完成付款。'
                : '',
        ];
    }
}
