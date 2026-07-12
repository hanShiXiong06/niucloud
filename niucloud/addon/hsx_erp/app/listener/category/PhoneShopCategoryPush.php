<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\category;

use core\exception\CommonException;
use think\facade\Db;

final class PhoneShopCategoryPush
{
    public function handle(array $params = []): array
    {
        $siteId = (int)($params['site_id'] ?? 0);
        if ($siteId <= 0 || (string)($params['provider'] ?? '') !== 'phone_shop') return [];
        $data = (array)($params['category'] ?? []);
        $name = trim((string)($data['category_name'] ?? ''));
        if ($name === '') throw new CommonException('ERP同步的分类名称不能为空');
        $targetId = (int)($data['target_category_id'] ?? 0);
        $pid = (int)($data['target_pid'] ?? 0);
        $row = $targetId > 0 ? Db::name('phone_shop_goods_category')->where([['site_id', '=', $siteId], ['category_id', '=', $targetId]])->find() : null;
        if (!$row) $row = Db::name('phone_shop_goods_category')->where([['site_id', '=', $siteId], ['pid', '=', $pid], ['category_name', '=', $name]])->find();
        $level = 1;
        $fullName = $name;
        if ($pid > 0) {
            $parent = Db::name('phone_shop_goods_category')->where([['site_id', '=', $siteId], ['category_id', '=', $pid]])->find();
            if (!$parent) throw new CommonException('ERP分类的商城上级映射不存在');
            $level = (int)$parent['level'] + 1;
            $fullName = trim((string)($parent['category_full_name'] ?: $parent['category_name'])) . '/' . $name;
        }
        $payload = ['site_id' => $siteId, 'category_name' => $name, 'pid' => $pid, 'level' => $level,
            'category_full_name' => $fullName, 'is_show' => (int)($data['is_show'] ?? 1),
            'sort' => (int)($data['sort'] ?? 0), 'update_time' => time()];
        if ($row) {
            Db::name('phone_shop_goods_category')->where([['site_id', '=', $siteId], ['category_id', '=', (int)$row['category_id']]])->update($payload);
            $targetId = (int)$row['category_id'];
        } else {
            $payload['create_time'] = time();
            $targetId = (int)Db::name('phone_shop_goods_category')->insertGetId($payload);
        }
        return ['provider' => 'phone_shop', 'target_category_id' => $targetId];
    }
}
