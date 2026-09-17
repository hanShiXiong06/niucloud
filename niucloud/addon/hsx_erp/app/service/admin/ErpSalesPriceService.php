<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\service\admin;

use core\exception\CommonException;

/** 可选商城价格提供器。ERP 独立使用时保持原本零售/同行价语义。 */
final class ErpSalesPriceService
{
    public static function request(int $siteId, string $action, array $data = []): array
    {
        foreach ((array)event('HsxErpSalesPricing', array_merge($data, ['site_id' => $siteId, 'action' => $action])) as $result) {
            if (is_array($result) && ($result['provider'] ?? '') === 'phone_shop') return $result;
        }
        return ['enabled' => 0];
    }

    public static function fields(int $siteId, float $input): array
    {
        $quote = self::request($siteId, 'quote', ['base_price' => $input]);
        return (int)($quote['enabled'] ?? 0) === 1
            ? ['retail_price' => $quote['retail_price'], 'estimate_sale_price' => $quote['base_price']]
            : ['retail_price' => $input];
    }

    public static function sync(int $siteId, array $asset): void
    {
        $result = self::request($siteId, 'sync', ['asset' => $asset]);
        // 未安装商城允许独立使用；已交接设备不允许静默丢失同步。
        if (!isset($result['provider']) && in_array((string)($asset['listing_status'] ?? ''), ['listed', 'pending_shop'], true)
            && (int)(ErpConfigService::forSite($siteId)->getMarketplaceChannel('phone_shop', $siteId)['enabled'] ?? 0) === 1) {
            throw new CommonException('设备已交接商城，但商城价格同步服务未响应，本次价格未保存');
        }
    }
}
