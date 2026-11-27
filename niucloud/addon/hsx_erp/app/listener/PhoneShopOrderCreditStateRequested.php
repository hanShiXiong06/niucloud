<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\core\ErpMallCreditStateService;

/** 只读查询，不收款、不创建应收、不改变订单状态。 */
class PhoneShopOrderCreditStateRequested
{
    public function handle(array $data): array
    {
        $siteId = (int)($data['site_id'] ?? 0);
        if ($siteId <= 0) return [];
        return [
            'provider' => 'hsx_erp', 'site_id' => $siteId,
            'orders' => (new ErpMallCreditStateService())->getStates($siteId, (array)($data['orders'] ?? []), !empty($data['lock'])),
        ];
    }
}
