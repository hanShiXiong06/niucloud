<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\hsx_erp\app\service\admin\ErpConfigService;
use app\service\core\sys\CoreConfigService;

/** 向商城暴露“回收设备资料由谁完善”，避免商城反向读取 ERP 私有表。 */
final class ListingMaterialPolicy
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        $stored = $siteId > 0
            ? (new CoreConfigService())->getConfigValue($siteId, ErpConfigService::CONFIG_KEY)
            : [];
        $owner = (string)((is_array($stored) ? $stored : [])['marketplace']['recycle_material_owner'] ?? 'erp');
        if (!in_array($owner, ['erp', 'phone_shop'], true)) $owner = 'erp';

        return [
            'owner' => $owner,
            'owner_label' => $owner === 'phone_shop' ? '商城运营专员' : 'ERP 库存人员',
            'can_phone_shop_operate' => $owner === 'phone_shop' ? 1 : 0,
        ];
    }
}
