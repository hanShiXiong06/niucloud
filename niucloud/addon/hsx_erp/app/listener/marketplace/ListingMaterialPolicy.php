<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\hsx_erp\app\service\admin\ErpConfigService;
/** 向商城暴露只读渠道策略，避免商城反向读取 ERP 私有表。 */
final class ListingMaterialPolicy
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        if ($siteId <= 0) return [];
        $channel = (new ErpConfigService())->getMarketplaceChannel('phone_shop', $siteId);
        $publishMode = (string)($channel['publish_mode'] ?? 'direct');
        $owner = in_array($publishMode, ['manual', 'basic_first'], true) ? 'phone_shop' : 'erp';
        $requiresMapping = (string)($channel['category_mode'] ?? 'erp') === 'independent'
            || (string)($channel['spec_mode'] ?? 'erp') === 'independent';

        return [
            'owner' => $owner,
            'owner_label' => $owner === 'phone_shop' ? '商城运营专员' : 'ERP 库存人员',
            'can_phone_shop_operate' => (int)($channel['enabled'] ?? 1) === 1 && ($owner === 'phone_shop' || $requiresMapping) ? 1 : 0,
            'requires_mapping' => $requiresMapping ? 1 : 0,
            'channel_key' => 'phone_shop',
            'enabled' => (int)($channel['enabled'] ?? 1),
            'category_mode' => (string)($channel['category_mode'] ?? 'erp'),
            'spec_mode' => (string)($channel['spec_mode'] ?? 'erp'),
            'publish_mode' => $publishMode,
            'basic_first' => $publishMode === 'basic_first' ? 1 : 0,
            'erp_is_master' => 1,
            'mall_can_write_erp_master' => 0,
        ];
    }
}
