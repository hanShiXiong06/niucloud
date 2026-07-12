<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use app\service\core\site\CoreSiteService;

/** 统一判断当前站点是否由 ERP 接管回收财务。 */
class RecycleErpCapabilityService
{
    public const ERP_ADDON = 'hsx_erp';
    public const ERP_PAYABLE_PATH = '/site/hsx_erp/payable';

    public function isPaymentManaged(int $siteId): bool
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
