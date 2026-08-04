<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use think\facade\Db;

final class ErpBusinessReportMetricsService
{
    private const STOCK_STATUSES = ['in_stock', 'pending_sale', 'available_for_sale'];

    public function collect(array $request): array
    {
        $siteId = (int)($request['site_id'] ?? 0);
        $start = (int)($request['start_at'] ?? 0);
        $end = (int)($request['end_at'] ?? 0);
        if ($siteId <= 0 || $start <= 0 || $end < $start) throw new \InvalidArgumentException('ERP经营指标缺少有效统计周期');

        $saleOrders = Db::name('erp_sale_order')->where([['site_id', '=', $siteId], ['status', '<>', 'void']])
            ->whereBetween('sale_at', [$start, $end]);
        $saleIds = array_map('intval', (clone $saleOrders)->column('id'));
        $soldItems = Db::name('erp_sale_item')->where('site_id', '=', $siteId)->where('status', '=', 'sold');
        if ($saleIds !== []) $soldItems->whereIn('sale_order_id', $saleIds);
        $saleCount = $saleIds === [] ? 0.0 : (float)(clone $soldItems)->sum('quantity');
        $saleAmount = $saleIds === [] ? 0.0 : (float)(clone $soldItems)->sum('sale_price');
        $saleProfit = $saleIds === [] ? 0.0 : (float)(clone $soldItems)->sum('profit');
        $effectiveSaleOrderCount = $saleIds === [] ? 0 : (int)(clone $soldItems)->distinct(true)->count('sale_order_id');
        $soldDeviceCount = $saleIds === [] ? 0 : (int)(clone $soldItems)->where('item_type', '=', 'device')->count();
        $purchaseOrders = Db::name('erp_purchase_order')->where([['site_id', '=', $siteId], ['status', '<>', 'void']])
            ->whereBetween('purchase_at', [$start, $end]);
        $purchaseIds = array_map('intval', (clone $purchaseOrders)->column('id'));
        $purchaseDeviceCount = $purchaseIds === [] ? 0 : (int)Db::name('erp_purchase_item')->where('site_id', '=', $siteId)->whereIn('purchase_order_id', $purchaseIds)->count();

        $stock = Db::name('erp_asset')->where('site_id', '=', $siteId)->whereIn('status', self::STOCK_STATUSES);
        $stockCount = (int)(clone $stock)->count();
        $stockCost = (float)(clone $stock)->sum('total_cost');
        $periodInbound = (int)Db::name('erp_asset')->where('site_id', '=', $siteId)->whereBetween('stock_in_at', [$start, $end])->count();
        $openingStock = max(0, $stockCount - $periodInbound + $soldDeviceCount);
        $availableStock = $openingStock + $periodInbound;
        $turnoverRate = $availableStock > 0 ? round($soldDeviceCount / $availableStock * 100, 2) : 0.0;

        $categories = [];
        $stockCategoryRows = (clone $stock)->field("IF(category_name='', '未分类', category_name) as name,COUNT(*) as stock_count")
            ->group('category_name')->select()->toArray();
        foreach ($stockCategoryRows as $row) $categories[(string)$row['name']] = ['key' => (string)$row['name'], 'name' => (string)$row['name'], 'stock_count' => (int)$row['stock_count'], 'sale_count' => 0, 'in_count' => 0];
        if ($saleIds !== []) {
            $rows = Db::name('erp_sale_item')->alias('i')->leftJoin('erp_asset a', 'a.id=i.asset_id AND a.site_id=i.site_id')
                ->where('i.site_id', '=', $siteId)->whereIn('i.sale_order_id', $saleIds)->where('i.status', '=', 'sold')
                ->field("IF(a.category_name='', '未分类', a.category_name) as name,SUM(i.quantity) as sale_count")->group('a.category_name')->select()->toArray();
            foreach ($rows as $row) {
                $name = (string)$row['name'];
                if (!isset($categories[$name])) $categories[$name] = ['key' => $name, 'name' => $name, 'stock_count' => 0, 'sale_count' => 0, 'in_count' => 0];
                $categories[$name]['sale_count'] = (float)$row['sale_count'];
            }
        }
        if ($purchaseIds !== []) {
            $rows = Db::name('erp_purchase_item')->where('site_id', '=', $siteId)->whereIn('purchase_order_id', $purchaseIds)
                ->field("IF(category_name='', '未分类', category_name) as name,COUNT(*) as in_count")->group('category_name')->select()->toArray();
            foreach ($rows as $row) {
                $name = (string)$row['name'];
                if (!isset($categories[$name])) $categories[$name] = ['key' => $name, 'name' => $name, 'stock_count' => 0, 'sale_count' => 0, 'in_count' => 0];
                $categories[$name]['in_count'] = (int)$row['in_count'];
            }
        }

        $staffRows = $saleIds === [] ? [] : Db::name('erp_sale_item')->alias('i')
            ->join('erp_sale_order s', 's.id=i.sale_order_id AND s.site_id=i.site_id')
            ->where('i.site_id', '=', $siteId)->whereIn('i.sale_order_id', $saleIds)->where('i.status', '=', 'sold')
            ->where('s.salesman_uid', '>', 0)
            ->field('s.salesman_uid as uid,MAX(s.salesman_name) as name,SUM(i.quantity) as sale_count,SUM(i.sale_price) as amount,SUM(i.profit) as profit')
            ->group('s.salesman_uid')->select()->toArray();
        $staff = array_map(static fn(array $row): array => [
            'uid' => (int)$row['uid'], 'name' => (string)$row['name'], 'role_key' => 'salesman', 'role_name' => '销售开单',
            'count' => (int)$row['sale_count'], 'amount' => round((float)$row['amount'], 2), 'profit' => round((float)$row['profit'], 2),
        ], $staffRows);

        $payableCount = (int)Db::name('erp_payable')->where('site_id', '=', $siteId)->whereIn('status', ['pending', 'partial'])->whereRaw('amount > settled_amount')->count();
        $receivableCount = (int)Db::name('erp_receivable')->where('site_id', '=', $siteId)->whereIn('status', ['pending', 'partial'])->whereRaw('amount > settled_amount')->count();
        $listingRows = Db::name('erp_asset')->where('site_id', '=', $siteId)->whereIn('status', self::STOCK_STATUSES)
            ->whereIn('listing_status', ['need_photo', 'need_price', 'need_material', 'ready', 'pending_shop'])
            ->field('listing_status,COUNT(*) as count')->group('listing_status')->select()->toArray();
        $todos = ['payable' => $payableCount, 'receivable' => $receivableCount];
        foreach ($listingRows as $row) $todos[(string)$row['listing_status']] = (int)$row['count'];

        $recentSales = $saleIds === [] ? [] : Db::name('erp_sale_item')->alias('i')->leftJoin('erp_asset a', 'a.id=i.asset_id AND a.site_id=i.site_id')
            ->where('i.site_id', '=', $siteId)->whereIn('i.sale_order_id', $saleIds)->where('i.status', '=', 'sold')
            ->field('i.asset_id,a.asset_no,COALESCE(NULLIF(i.imei,\'\'),a.imei) as imei,COALESCE(NULLIF(i.model,\'\'),a.model) as model,a.category_name,i.sale_price,i.profit')
            ->order('i.id desc')->limit(100)->select()->toArray();

        return [
            'provider' => 'hsx_erp', 'provider_name' => '二手机 ERP', 'available' => true,
            'summary' => [
                'purchase_order_count' => (int)(clone $purchaseOrders)->count(), 'purchase_device_count' => $purchaseDeviceCount,
                'sale_order_count' => $effectiveSaleOrderCount, 'sale_count' => round($saleCount, 3), 'sale_device_count' => $soldDeviceCount,
                'sale_amount' => round($saleAmount, 2), 'sale_profit' => round($saleProfit, 2),
                'stock_count' => $stockCount, 'stock_cost' => round($stockCost, 2), 'opening_stock_count' => $openingStock,
                'period_inbound_count' => $periodInbound, 'available_stock_count' => $availableStock,
                'turnover_rate' => $turnoverRate,
            ],
            'categories' => array_values($categories), 'staff' => $staff, 'todos' => $todos,
            'details' => ['sales' => $recentSales],
        ];
    }
}
