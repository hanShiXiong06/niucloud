<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\admin;

use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\service\core\CoreStatService;
use Carbon\Carbon;
use core\base\BaseAdminService;

/**
 * 统计
 * Class StatService
 * @package app\service\admin
 */
class StatService extends BaseAdminService
{
    /**
     * 获取统计数据
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getStat(string $start_date = '', string $end_date = ''): array
    {
        return ( new CoreStatService() )->getStat($this->site_id, $start_date, $end_date);
    }

    /**
     * 获取区间统计数据
     * @param string $start_date
     * @param string $end_date
     * @return array|null
     */
    public function getStatData(string $start_date, string $end_date)
    {
        return ( new CoreStatService() )->getStatData($this->site_id, $start_date, $end_date);
    }

    /**
     * 获取某天统计数据（按小时）
     * @param string $date
     * @return array|null
     */
    public function getHourStatData(string $date)
    {
        return ( new CoreStatService() )->getHourStatData($this->site_id, $date);
    }

    /**
     * 获取指定日期的概览数据（含上下架数量）
     * @param string $date
     * @return array
     */
    public function getDayOverview(string $date): array
    {
        $coreStat = new CoreStatService();
        $stat = $coreStat->getStat($this->site_id, $date);

        $range = $this->getDayRange($date);
        $stat['listed_goods_num'] = $this->countListedWithin($range['start'], $range['end']);
        $stat['delisted_goods_num'] = $this->countDelistedWithin($range['start'], $range['end']);

        return $stat;
    }

    /**
     * 获取商品上下架分类对比数据
     * @param string $timeRange 时间范围：today, yesterday, week, month
     * @return array
     */
    public function getGoodsCategoryTrend(string $timeRange = 'today'): array
    {
        $ranges = $this->getTrendRanges($timeRange);
        $shelfTrend = $this->buildCategoryTrend($ranges, true);
        $unshelfTrend = $this->buildCategoryTrend($ranges, false);

        return [
            'shelf' => $shelfTrend,
            'unshelf' => $unshelfTrend
        ];
    }

    private function countListedWithin(int $start, int $end): int
    {
        return ( new Goods() )
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'status', '=', 1 ],
                [ 'create_time', '>=', $start ],
                [ 'create_time', '<=', $end ]
            ])->count();
    }

    private function countDelistedWithin(int $start, int $end): int
    {
        // 下架：使用 update_time，status = 0
        // 确保 update_time > create_time（确保是下架操作，不是创建时就是下架状态）
        return ( new Goods() )
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'status', '=', 0 ],
                [ 'update_time', '>', 0 ],
                [ 'update_time', '>=', $start ],
                [ 'update_time', '<=', $end ]
            ])
            ->whereRaw('update_time > create_time')
            ->count();
    }

    private function buildCategoryTrend(array $ranges, bool $isShelf): array
    {
        // 获取图例标签
        $legendMap = $this->getLegendLabels($ranges);
        
        $categoryBuckets = [];
        $categoryIds = [];

        foreach ($ranges as $key => $range) {
            $bucket = $this->collectCategoryCounts($range['start'], $range['end'], $isShelf);
            $categoryBuckets[$key] = $bucket;
            if (!empty($bucket)) {
                $categoryIds = array_unique(array_merge($categoryIds, array_keys($bucket)));
            }
        }

        // 只保留在所有时间段中至少有一个不为0的分类
        $categoryOrder = $this->resolveCategoryOrder($categoryIds, $categoryBuckets);
        if (empty($categoryOrder)) {
            return [
                'legend' => array_values($legendMap),
                'categories' => [],
                'series' => []
            ];
        }

        // 获取分类名称
        $categoryNames = $this->getCategoryNames($categoryOrder);
        if (empty($categoryNames)) {
            return [
                'legend' => array_values($legendMap),
                'categories' => [],
                'series' => []
            ];
        }

        // 构建分类名称数组，只包含有名称的分类
        $validCategories = [];
        $validCategoryIds = [];
        foreach ($categoryOrder as $categoryId) {
            if (isset($categoryNames[$categoryId]) && !empty($categoryNames[$categoryId])) {
                $validCategories[] = $categoryNames[$categoryId];
                $validCategoryIds[] = $categoryId;
            }
        }

        if (empty($validCategories)) {
            return [
                'legend' => array_values($legendMap),
                'categories' => [],
                'series' => []
            ];
        }

        // 构建系列数据，只包含有数据的分类
        $series = [];
        foreach ($ranges as $key => $range) {
            $data = [];
            foreach ($validCategoryIds as $categoryId) {
                $count = (int)($categoryBuckets[$key][$categoryId] ?? 0);
                $data[] = $count;
            }
            $series[] = [
                'name' => $legendMap[$key] ?? $key,
                'data' => $data
            ];
        }

        return [
            'legend' => array_values($legendMap),
            'categories' => $validCategories,
            'series' => $series
        ];
    }

    private function collectCategoryCounts(int $start, int $end, bool $isShelf): array
    {
        if ($isShelf) {
            // 上架：使用 create_time，status = 1
            // 统计在指定时间范围内创建且状态为上架的商品
            $query = ( new Goods() )->where([
                [ 'site_id', '=', $this->site_id ],
                // [ 'status', '=', 1 ],
                [ 'create_time', '>=', $start ],
                [ 'create_time', '<=', $end ]
            ]);
        } else {
            // 下架：使用 update_time，status = 0
            // 统计在指定时间范围内更新且状态为下架的商品
            // 确保 update_time > create_time（确保是下架操作，不是创建时就是下架状态）
            $query = ( new Goods() )->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'status', '=', 0 ],
                [ 'update_time', '>', 0 ],
                [ 'update_time', '>=', $start ],
                [ 'update_time', '<=', $end ]
            ])->whereRaw('update_time > create_time');  // 确保是更新操作，不是创建时就是下架状态
        }

        $goodsList = $query->field('goods_category')->select()->toArray();
        if (empty($goodsList)) {
            return [];
        }

        $bucket = [];
        foreach ($goodsList as $goods) {
            $categoryId = $this->extractCategoryId($goods['goods_category'] ?? '');
            if ($categoryId === null) {
                continue;
            }
            if (!isset($bucket[$categoryId])) {
                $bucket[$categoryId] = 0;
            }
            $bucket[$categoryId]++;
        }

        return array_filter($bucket, function($count) {
            return $count > 0;
        });
    }

    private function resolveCategoryOrder(array $categoryIds, array $categoryBuckets): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $total = [];
        foreach ($categoryIds as $categoryId) {
            $sum = 0;
            foreach ($categoryBuckets as $bucket) {
                $sum += $bucket[$categoryId] ?? 0;
            }
            if ($sum > 0) {
                $total[$categoryId] = $sum;
            }
        }

        if (empty($total)) {
            return [];
        }

        arsort($total);
        return array_keys($total);
    }

    private function getCategoryNames(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        return ( new Category() )
            ->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'category_id', 'in', $categoryIds ]
            ])->column('category_name', 'category_id');
    }

    /**
     * 获取图例标签
     * @param array $ranges 时间范围数组，key为日期标识
     * @return array
     */
    private function getLegendLabels(array $ranges): array
    {
        $labels = [];
        
        foreach ($ranges as $key => $range) {
            // 如果是预定义的key（today, yesterday），直接使用
            if ($key === 'today') {
                $labels[$key] = '今日';
            } elseif ($key === 'yesterday') {
                $labels[$key] = '昨日';
            } else {
                // 如果是日期格式（Y-m-d），转换为月-日格式
                try {
                    $carbon = Carbon::parse($key);
                    $labels[$key] = $carbon->format('m-d');
                } catch (\Exception $e) {
                    $labels[$key] = $key;
                }
            }
        }
        
        return $labels;
    }

    /**
     * 获取时间范围（按天）
     * @param string $timeRange 时间范围：today, yesterday, week, month
     * @return array 返回按天划分的时间范围数组，key为日期标识，value为{start, end}
     */
    private function getTrendRanges(string $timeRange = 'today'): array
    {
        $now = Carbon::now();
        $ranges = [];

        switch ($timeRange) {
            case 'today':
                $today = $now->copy()->startOfDay();
                $ranges['today'] = [
                    'start' => $today->getTimestamp(),
                    'end' => $today->copy()->endOfDay()->getTimestamp()
                ];
                break;

            case 'yesterday':
                $yesterday = $now->copy()->subDay()->startOfDay();
                $ranges['yesterday'] = [
                    'start' => $yesterday->getTimestamp(),
                    'end' => $yesterday->copy()->endOfDay()->getTimestamp()
                ];
                break;

            case 'week':
                // 本周按天返回（从周一到周日，或从周日到周六，取决于Carbon的配置）
                $startOfWeek = $now->copy()->startOfWeek();
                $endOfWeek = $now->copy()->endOfWeek();
                $current = $startOfWeek->copy();
                
                while ($current->lte($endOfWeek)) {
                    $dayKey = $current->format('Y-m-d');
                    $ranges[$dayKey] = [
                        'start' => $current->copy()->startOfDay()->getTimestamp(),
                        'end' => $current->copy()->endOfDay()->getTimestamp()
                    ];
                    $current->addDay();
                }
                break;

            case 'month':
                // 本月按天返回
                $startOfMonth = $now->copy()->startOfMonth();
                $endOfMonth = $now->copy()->endOfMonth();
                $current = $startOfMonth->copy();
                
                while ($current->lte($endOfMonth)) {
                    $dayKey = $current->format('Y-m-d');
                    $ranges[$dayKey] = [
                        'start' => $current->copy()->startOfDay()->getTimestamp(),
                        'end' => $current->copy()->endOfDay()->getTimestamp()
                    ];
                    $current->addDay();
                }
                break;

            default:
                // 默认返回今天
                $today = $now->copy()->startOfDay();
                $ranges['today'] = [
                    'start' => $today->getTimestamp(),
                    'end' => $today->copy()->endOfDay()->getTimestamp()
                ];
                break;
        }

        return $ranges;
    }

    private function getDayRange(string $date): array
    {
        $carbon = Carbon::parse($date);
        return [
            'start' => $carbon->copy()->startOfDay()->getTimestamp(),
            'end' => $carbon->copy()->endOfDay()->getTimestamp()
        ];
    }

    private function extractCategoryId($raw): ?int
    {
        if (empty($raw)) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
        } else {
            $decoded = $raw;
        }

        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        $decoded = array_values(array_filter(array_map('intval', $decoded)));
        if (empty($decoded)) {
            return null;
        }

        return (int) end($decoded);
    }
}
