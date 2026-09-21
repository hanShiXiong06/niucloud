<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\listener\marketplace\PhoneShopBridge;
use core\exception\CommonException;

/** 可选商城能力。分类选择保存在设备既有 spec_json 中，不污染 ERP 目录。 */
final class ErpMallListingService
{
    public static function capability(int $siteId): array
    {
        $channel = ErpConfigService::forSite($siteId)->getMarketplaceChannel('phone_shop');
        if ((int)($channel['enabled'] ?? 0) !== 1) {
            return ['connected' => 0, 'message' => '商城联动未开启，本次仅保存 ERP 资料，不会上架商城。'];
        }
        try {
            if (!PhoneShopBridge::available($siteId)) return ['connected' => 0, 'message' => '本站未启用手机商城，本次仅保存 ERP 资料。'];
            return self::request($siteId, 'describe');
        } catch (\Throwable $e) {
            return ['connected' => 0, 'message' => '商城分类服务未就绪，本次仅保存 ERP 资料。请检查商城插件及配套更新。'];
        }
    }

    public static function request(int $siteId, string $action, int $categoryId = 0): array
    {
        $channel = ErpConfigService::forSite($siteId)->getMarketplaceChannel('phone_shop');
        if ((int)($channel['enabled'] ?? 0) !== 1) throw new CommonException('商城联动已关闭，请确认配置后重试');
        if (!PhoneShopBridge::available($siteId)) throw new CommonException('本站未启用手机商城，不能选择商城分类或上架');
        foreach ((array)event('HsxErpListingCatalog', ['site_id' => $siteId, 'action' => $action, 'category_id' => $categoryId]) as $result) {
            if (is_array($result) && ($result['provider'] ?? '') === 'phone_shop' && (int)($result['connected'] ?? 0) === 1) return $result;
        }
        throw new CommonException('商城分类服务未响应，请检查 phone_shop 插件是否已启用并更新配套后端');
    }

    public static function selection(mixed $specJson): array
    {
        $spec = is_array($specJson) ? $specJson : json_decode((string)$specJson, true);
        return (array)($spec['_mall_listing']['category'] ?? []);
    }
}
