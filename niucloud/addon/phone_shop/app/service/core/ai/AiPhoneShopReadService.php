<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\ai;

use addon\phone_shop\app\model\goods\Goods;

/** 二手机商城经营只读口径：一机一品，关注货盘每天的上新和离架。 */
final class AiPhoneShopReadService
{
    public function listingSummary(int $siteId, array $arguments): array
    {
        $period = in_array((string)($arguments['period'] ?? 'today'), ['today', 'week', 'month'], true)
            ? (string)$arguments['period'] : 'today';
        [$startAt, $endAt, $periodLabel] = $this->periodRange($period);
        $base = Goods::where('site_id', '=', $siteId);
        $created = (int)(clone $base)->where('create_time', '>=', $startAt)->where('create_time', '<=', $endAt)->count();
        $delisted = (int)(clone $base)->where('update_time', '>=', $startAt)->where('update_time', '<=', $endAt)
            ->where('status', '=', 0)->count();
        $sold = (int)(clone $base)->where('update_time', '>=', $startAt)->where('update_time', '<=', $endAt)
            ->where('sale_status', '=', 'sold')->count();
        $locked = (int)(clone $base)->where('update_time', '>=', $startAt)->where('update_time', '<=', $endAt)
            ->where('sale_status', '=', 'locked')->count();
        $sellable = (int)(clone $base)->where([
            ['status', '=', 1], ['sale_status', '=', 'available'], ['is_online_sellable', '=', 1], ['stock', '>', 0],
        ])->count();

        $recent = (clone $base)->where(function ($query) use ($startAt, $endAt) {
            $query->where(function ($created) use ($startAt, $endAt) {
                $created->where('create_time', '>=', $startAt)->where('create_time', '<=', $endAt);
            })->whereOr(function ($changed) use ($startAt, $endAt) {
                $changed->where('update_time', '>=', $startAt)->where('update_time', '<=', $endAt)->where('status', '=', 0);
            });
        })->field('goods_id,goods_name,memory_group,condition_grade,status,sale_status,create_time,update_time')
            ->order('update_time desc,goods_id desc')->limit(12)->select()->toArray();
        foreach ($recent as &$row) {
            $isDelisted = (int)$row['status'] === 0;
            $row['change_type'] = $isDelisted
                ? ((string)$row['sale_status'] === 'sold' ? '已售离架' : '离架')
                : '上新';
            $row['change_at'] = $isDelisted ? (int)$row['update_time'] : (int)$row['create_time'];
            $row['change_time'] = $row['change_at'] > 0 ? date('Y-m-d H:i', $row['change_at']) : '';
        }
        unset($row);

        return [
            'period' => $period,
            'period_label' => $periodLabel,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'definition' => [
                'listed' => '统计时间内 create_time 新增的二手机商品',
                'delisted' => '统计时间内 update_time 发生变化且 status=0 的离架商品；其中可能包含已售',
                'sold' => '统计时间内 sale_status=sold 的已售商品',
            ],
            'metrics' => [
                'listed_count' => $created,
                'delisted_count' => $delisted,
                'sold_count' => $sold,
                'locked_count' => $locked,
                'current_sellable_count' => $sellable,
            ],
            'assistant_summary' => sprintf(
                '%s商城上新 %d 台，离架 %d 台，其中已售 %d 台；当前线上可售 %d 台。上新按创建时间统计，离架按更新时间且 status=0 统计。',
                $periodLabel,
                $created,
                $delisted,
                $sold,
                $sellable
            ),
            'recent_changes' => $recent,
            '_presentation' => [
                'blocks' => [
                    [
                        'type' => 'stat_grid',
                        'source_plugin' => 'phone_shop',
                        'data' => ['items' => [
                            ['title' => $periodLabel . '上新', 'value' => $created, 'unit' => '台', 'description' => '按商品创建时间', 'tone' => 'primary', 'icon' => 'element Upload'],
                            ['title' => $periodLabel . '离架', 'value' => $delisted, 'unit' => '台', 'description' => '含已售离架', 'tone' => 'warning', 'icon' => 'element Download'],
                            ['title' => $periodLabel . '已售', 'value' => $sold, 'unit' => '台', 'description' => '商城货盘成交状态', 'tone' => 'success', 'icon' => 'element SoldOut'],
                            ['title' => '当前可售', 'value' => $sellable, 'unit' => '台', 'description' => '实时线上可售货盘', 'tone' => 'info', 'icon' => 'element Goods'],
                        ]],
                    ],
                    [
                        'type' => 'table',
                        'source_plugin' => 'phone_shop',
                        'data' => [
                            'title' => $periodLabel . '货盘变动',
                            'description' => '二手机一机一品，上新和离架均按设备商品计算。',
                            'columns' => [
                                ['prop' => 'change_type', 'label' => '变化', 'width' => 100],
                                ['prop' => 'goods_name', 'label' => '设备', 'min_width' => 180],
                                ['prop' => 'memory_group', 'label' => '内存', 'width' => 100],
                                ['prop' => 'condition_grade', 'label' => '成色', 'width' => 100],
                                ['prop' => 'change_time', 'label' => '时间', 'width' => 150],
                            ],
                            'rows' => $recent,
                        ],
                    ],
                    [
                        'type' => 'action_group',
                        'source_plugin' => 'phone_shop',
                        'data' => ['actions' => [[
                            'id' => 'phone_shop_goods_list',
                            'label' => '查看商城货盘',
                            'icon' => 'element Goods',
                            'tone' => 'primary',
                            'type' => 'route',
                            'route' => '/site/phone_shop/goods/list',
                            'params' => [],
                            'approved' => true,
                        ]]],
                    ],
                ],
            ],
        ];
    }

    private function periodRange(string $period): array
    {
        if ($period === 'week') {
            return [strtotime('monday this week 00:00:00'), strtotime('sunday this week 23:59:59'), '本周'];
        }
        if ($period === 'month') {
            return [strtotime(date('Y-m-01 00:00:00')), strtotime(date('Y-m-t 23:59:59')), '本月'];
        }
        return [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59')), '今日'];
    }
}
