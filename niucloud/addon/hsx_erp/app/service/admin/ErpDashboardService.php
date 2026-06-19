<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use core\base\BaseAdminService;

/**
 * 运营看板（老板视角）数据聚合
 *
 * 全局口径：不套库位权限（看板是给老板/运营看的，要全局数）。
 * 一次返回：KPI + 销售毛利趋势 + 库存状态分布 + 库龄分布 + 热销机型 Top。
 */
class ErpDashboardService extends BaseAdminService
{
    public function data(array $filter): array
    {
        $start = (int)($filter['start'] ?? strtotime(date('Y-m') . '-01 00:00:00'));
        $end   = (int)($filter['end'] ?? time());
        if ($end < $start) {
            $end = $start;
        }
        $warehouseId = (int)($filter['warehouse_id'] ?? 0);

        $base = function () use ($warehouseId) {
            $q = ErpAsset::where([['site_id', '=', $this->site_id]]);
            if ($warehouseId > 0) {
                $q->where('warehouse_id', '=', $warehouseId);
            }
            return $q;
        };

        $onHand = [
            ErpDict::INVENTORY_IN_STOCK,
            ErpDict::INVENTORY_REFURBISHING,
            ErpDict::INVENTORY_PENDING_PRICING,
            ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
        ];

        // —— 售出明细（区间）—— 一次取出，PHP 聚合趋势/机型/毛利
        $soldRows = $base()->where('inventory_status', '=', ErpDict::INVENTORY_OUTBOUND)
            ->where('stock_out_at', '>=', $start)->where('stock_out_at', '<=', $end)
            ->field('model,current_sale_price,current_cost,stock_out_at')
            ->select()->toArray();

        $salesAmount = 0.0;
        $salesCost = 0.0;
        $dayMap = [];
        $modelMap = [];
        foreach ($soldRows as $r) {
            $sale = (float)$r['current_sale_price'];
            $cost = (float)$r['current_cost'];
            $profit = $sale - $cost;
            $salesAmount += $sale;
            $salesCost += $cost;
            $day = date('Y-m-d', (int)$r['stock_out_at']);
            if (!isset($dayMap[$day])) {
                $dayMap[$day] = ['sale' => 0, 'profit' => 0];
            }
            $dayMap[$day]['sale'] += $sale;
            $dayMap[$day]['profit'] += $profit;
            $m = (string)($r['model'] ?: '未知机型');
            if (!isset($modelMap[$m])) {
                $modelMap[$m] = ['count' => 0, 'sale' => 0, 'profit' => 0];
            }
            $modelMap[$m]['count']++;
            $modelMap[$m]['sale'] += $sale;
            $modelMap[$m]['profit'] += $profit;
        }
        $grossProfit = round($salesAmount - $salesCost, 2);
        $margin = $salesAmount > 0 ? round($grossProfit / $salesAmount * 100, 1) : 0;

        // —— 采购（区间）——
        $purchaseCount = $base()->where('stock_in_at', '>=', $start)->where('stock_in_at', '<=', $end)->count();
        $purchaseCost  = round((float)$base()->where('stock_in_at', '>=', $start)->where('stock_in_at', '<=', $end)->sum('purchase_cost'), 2);

        // —— 在手 / 可售 / 库龄（当前快照）——
        $onHandCount = $base()->whereIn('inventory_status', $onHand)->count();
        $onHandCost  = round((float)$base()->whereIn('inventory_status', $onHand)->sum('current_cost'), 2);
        $sellableCount  = $base()->where('inventory_status', '=', ErpDict::INVENTORY_AVAILABLE_FOR_SALE)->count();
        $sellableAmount = round((float)$base()->where('inventory_status', '=', ErpDict::INVENTORY_AVAILABLE_FOR_SALE)->sum('current_sale_price'), 2);
        $avgStockIn = (float)$base()->whereIn('inventory_status', $onHand)->where('stock_in_at', '>', 0)->avg('stock_in_at');
        $now = time();
        $avgAge = $avgStockIn > 0 ? round(($now - $avgStockIn) / 86400, 1) : 0;

        // 周转率（区间售出成本 / 当前在手成本，近似）
        $turnover = $onHandCost > 0 ? round($salesCost / $onHandCost, 2) : 0;

        // —— 销售/毛利趋势（补全每一天，最多 180 天防爆）——
        $trend = [];
        $cursor = strtotime(date('Y-m-d', $start));
        $guard = 0;
        while ($cursor <= $end && $guard < 180) {
            $day = date('Y-m-d', $cursor);
            $trend[] = [
                'day'    => $day,
                'sale'   => round($dayMap[$day]['sale'] ?? 0, 2),
                'profit' => round($dayMap[$day]['profit'] ?? 0, 2),
            ];
            $cursor = strtotime('+1 day', $cursor);
            $guard++;
        }

        // —— 热销机型 Top10（按毛利）——
        $models = [];
        foreach ($modelMap as $m => $v) {
            $models[] = ['model' => $m, 'count' => $v['count'], 'sale' => round($v['sale'], 2), 'profit' => round($v['profit'], 2)];
        }
        usort($models, fn($a, $b) => $b['profit'] <=> $a['profit']);
        $topModels = array_slice($models, 0, 10);

        // —— 库存状态分布（当前快照）——
        $statusMap = ErpDict::getInventoryStatusMap();
        $statusRows = $base()->field('inventory_status, count(*) c')->group('inventory_status')->select()->toArray();
        $statusDist = [];
        foreach ($statusRows as $sr) {
            $st = (string)$sr['inventory_status'];
            $statusDist[] = ['status' => $st, 'name' => $statusMap[$st] ?? $st, 'count' => (int)$sr['c']];
        }

        // —— 库龄分布（在手）——
        $ageList = $base()->whereIn('inventory_status', $onHand)->where('stock_in_at', '>', 0)->column('stock_in_at');
        $buckets = ['0-7天' => 0, '8-15天' => 0, '16-30天' => 0, '30天以上' => 0];
        foreach ($ageList as $ts) {
            $d = ($now - (int)$ts) / 86400;
            if ($d <= 7) {
                $buckets['0-7天']++;
            } elseif ($d <= 15) {
                $buckets['8-15天']++;
            } elseif ($d <= 30) {
                $buckets['16-30天']++;
            } else {
                $buckets['30天以上']++;
            }
        }
        $ageDist = [];
        foreach ($buckets as $k => $v) {
            $ageDist[] = ['bucket' => $k, 'count' => $v];
        }

        return [
            'range' => ['start' => date('Y-m-d', $start), 'end' => date('Y-m-d', $end)],
            'kpi'   => [
                'sales_amount'    => round($salesAmount, 2),
                'gross_profit'    => $grossProfit,
                'margin'          => $margin,
                'purchase_cost'   => $purchaseCost,
                'purchase_count'  => $purchaseCount,
                'sold_count'      => count($soldRows),
                'on_hand_count'   => $onHandCount,
                'on_hand_cost'    => $onHandCost,
                'sellable_count'  => $sellableCount,
                'sellable_amount' => $sellableAmount,
                'avg_age_days'    => $avgAge,
                'turnover'        => $turnover,
            ],
            'sales_trend'  => $trend,
            'top_models'   => $topModels,
            'stock_status' => $statusDist,
            'stock_age'    => $ageDist,
        ];
    }
}
