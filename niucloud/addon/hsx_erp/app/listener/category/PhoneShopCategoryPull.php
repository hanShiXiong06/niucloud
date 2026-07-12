<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\category;

use think\facade\Db;

final class PhoneShopCategoryPull
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        if ($siteId <= 0 || (string)($params['provider'] ?? '') !== 'phone_shop') return [];
        $rows = Db::name('phone_shop_goods_category')->where('site_id', $siteId)
            ->field('category_id,category_name,pid,level,category_full_name,is_show,sort')
            ->order('level asc,sort desc,category_id asc')->select()->toArray();
        return ['provider' => 'phone_shop', 'categories' => $rows];
    }
}
