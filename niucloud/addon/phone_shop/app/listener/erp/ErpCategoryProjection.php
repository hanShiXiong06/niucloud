<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use think\facade\Db;

/**
 * 消费 ERP 目录投影请求。
 *
 * 商城只维护自己的读模型；ERP 不需要知道商城分类表结构。
 */
final class ErpCategoryProjection
{
    public function handle(array $event = []): array
    {
        if ((string)($event['channel_key'] ?? '') !== 'phone_shop') return ['provider' => 'phone_shop', 'status' => 'ignored'];
        $siteId = (int)($event['site_id'] ?? 0);
        $segments = array_values(array_filter(array_map(static fn(mixed $value): string => trim((string)$value), (array)($event['segments'] ?? []))));
        if ($siteId <= 0 || $segments === []) {
            return ['provider' => 'phone_shop', 'status' => 'rejected', 'message' => '目录投影参数不完整'];
        }

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
                    'site_id' => $siteId,
                    'category_name' => $name,
                    'pid' => $pid,
                    'level' => $index + 1,
                    'category_full_name' => implode('/', $full),
                    'is_show' => 1,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
            }
            $ids[] = $id;
            $pid = $id;
        }
        return [
            'provider' => 'phone_shop',
            'status' => 'projected',
            'category_ids' => $ids,
            'category_name' => implode('/', array_slice($segments, 0, 3)),
        ];
    }
}
