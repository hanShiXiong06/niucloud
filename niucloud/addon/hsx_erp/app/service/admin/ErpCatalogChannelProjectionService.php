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

        $ids = [];
        $pid = 0;
        $full = [];
        foreach (array_slice($segments, 0, 3) as $index => $name) {
            $full[] = $name;
            $where = [['site_id', '=', $siteId], ['pid', '=', $pid], ['category_name', '=', $name]];
            $row = Db::name('phone_shop_goods_category')->where($where)->field('category_id')->find();
            if ($row) {
                $id = (int)$row['category_id'];
            } else {
                $id = (int)Db::name('phone_shop_goods_category')->insertGetId([
                    'site_id' => $siteId, 'category_name' => $name, 'pid' => $pid,
                    'level' => $index + 1, 'category_full_name' => implode('/', $full),
                    'is_show' => 1, 'sort' => 0, 'create_time' => time(), 'update_time' => time(),
                ]);
            }
            $ids[] = $id;
            $pid = $id;
        }
        return ['category_ids' => $ids, 'product' => $product];
    }
}
