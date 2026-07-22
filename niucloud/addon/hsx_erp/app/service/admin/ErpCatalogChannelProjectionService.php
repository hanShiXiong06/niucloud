<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use core\exception\CommonException;
use think\facade\Db;

/**
 * ERP 目录到渠道的读模型投影。
 *
 * ERP 是唯一主数据；渠道表只是为了兼容商城现有检索与商品字段，
 * 不反向修改 ERP 目录，也不再使用旧 erp_category_mapping。
 */
final class ErpCatalogChannelProjectionService
{
    public function ensurePhoneShopPath(int $siteId, int $siteProductId): array
    {
        if ($siteId <= 0 || $siteProductId <= 0) throw new CommonException('商品目录信息不完整');
        $product = Db::name('erp_site_catalog_product')->where([
            ['site_id', '=', $siteId], ['site_product_id', '=', $siteProductId], ['is_enabled', '=', 1],
        ])->field('site_product_id,category_path,brand_name,series_name,product_name')->find();
        if (!$product) throw new CommonException('商品目录型号不存在或已停用');

        $categoryPath = preg_replace('/[\x{00A0}\x{3000}]+/u', ' ', trim((string)$product['category_path']))
            ?? trim((string)$product['category_path']);
        $segments = array_values(array_filter(array_map(
            static fn(string $value): string => trim(preg_replace('/\s+/u', ' ', $value) ?? $value),
            preg_split('~[/\\\\／＞>,，|｜]+~u', $categoryPath) ?: []
        ), static fn(string $value): bool => $value !== ''));
        if (count($segments) > 2) throw new CommonException('商品目录品类只允许“一级品类/可选一级子分类”');
        $brand = trim((string)$product['brand_name']);
        if ($brand !== '') $segments[] = $brand;
        if ($segments === []) throw new CommonException('商品目录缺少品类和品牌');

        $projection = null;
        foreach ((array)event('HsxErpChannelCategoryProject', [
            'site_id' => $siteId,
            'channel_key' => 'phone_shop',
            'segments' => array_slice($segments, 0, 3),
            'erp_product' => $product,
        ]) as $result) {
            if (!is_array($result) || (string)($result['provider'] ?? '') !== 'phone_shop') continue;
            if ((string)($result['status'] ?? '') === 'projected') {
                $projection = $result;
                break;
            }
        }
        if ($projection === null) throw new CommonException('商城渠道未提供目录投影能力');
        $ids = array_values(array_filter(array_map('intval', (array)($projection['category_ids'] ?? []))));
        if ($ids === []) throw new CommonException('商城分类投影失败');
        (new ErpChannelMappingService())->recordCategoryMapping([
            'site_id' => $siteId,
            'channel_key' => 'phone_shop',
            'erp_category_path' => $categoryPath,
            'channel_category_path' => $ids,
            'channel_category_name' => trim((string)($projection['category_name'] ?? '')) ?: implode('/', array_slice($segments, 0, 3)),
            'mapping_source' => 'projection',
        ]);
        return ['category_ids' => $ids, 'product' => $product];
    }
}
