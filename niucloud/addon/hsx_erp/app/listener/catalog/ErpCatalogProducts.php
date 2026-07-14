<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\catalog;

use think\facade\Db;

/**
 * 跨插件只读商品目录 Hook。
 *
 * 调用：event('HsxErpCatalogProducts', ['site_id' => 100005, ...])。
 * 始终从站点绑定表读取，不允许消费方直接查询全局标准模板。
 */
final class ErpCatalogProducts
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        if ($siteId <= 0) return [];
        $limit = min(500, max(1, (int)($params['limit'] ?? 100)));
        $query = Db::name('erp_site_catalog_product')->alias('sp')
            ->leftJoin('erp_catalog_product_master mp', 'mp.master_product_id = sp.master_product_id')
            ->where([['sp.site_id', '=', $siteId], ['sp.is_enabled', '=', 1]]);
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('sp.product_name|sp.brand_name|sp.series_name|mp.source_product_id', '%' . $keyword . '%');
        if (trim((string)($params['category_path'] ?? '')) !== '') $query->where('sp.category_path', '=', trim((string)$params['category_path'], " /\t\n\r\0\x0B"));
        if (trim((string)($params['brand_name'] ?? '')) !== '') $query->where('sp.brand_name', '=', trim((string)$params['brand_name']));
        if (trim((string)($params['series_name'] ?? '')) !== '') $query->where('sp.series_name', '=', trim((string)$params['series_name']));
        $products = $query->field('sp.site_product_id,sp.category_path,sp.product_name,sp.brand_name,sp.series_name,mp.source_key,mp.source_product_id,mp.category_source_id,mp.brand_source_id')
            ->order('sp.sort desc,sp.product_name asc')->limit($limit)->select()->toArray();
        return ['provider' => 'hsx_erp', 'site_id' => $siteId, 'products' => $products];
    }
}
