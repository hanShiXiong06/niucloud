<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core\ai;

use addon\hsx_erp\app\dict\ErpDict;
use think\facade\Db;

/** ERP 面向 AI 的只读业务口径，避免模型绕过财务和库存服务边界。 */
final class AiErpReadService
{
    public function stockSummary(int $siteId, array $arguments, array $actor = []): array
    {
        $warehouseId = max(0, (int)($arguments['warehouse_id'] ?? 0));
        $sellableStatuses = [ErpDict::ASSET_IN_STOCK, 'pending_sale', 'available_for_sale'];
        $base = Db::name('erp_asset')->where('site_id', '=', $siteId)->whereIn('status', $sellableStatuses);
        if ($warehouseId > 0) $base->where('warehouse_id', '=', $warehouseId);

        $listingRows = (clone $base)->field('listing_status,COUNT(*) as count')->group('listing_status')->select()->toArray();
        $listing = [];
        foreach ($listingRows as $row) $listing[(string)$row['listing_status']] = (int)$row['count'];
        $warehouseRows = (clone $base)->field('warehouse_id,MAX(warehouse_name) as warehouse_name,COUNT(*) as stock_count,SUM(total_cost) as stock_cost')
            ->group('warehouse_id')->order('stock_count desc')->limit(20)->select()->toArray();
        foreach ($warehouseRows as &$row) {
            $row['warehouse_id'] = (int)$row['warehouse_id'];
            $row['stock_count'] = (int)$row['stock_count'];
            $row['stock_cost'] = round((float)$row['stock_cost'], 2);
        }
        unset($row);

        $canViewCost = $this->can($actor, [
            'hsx_erp_stock_adjust_cost', 'hsx_erp_purchase_adjust_cost',
            'hsx_erp_payable', 'hsx_erp_operating_finance',
        ]);
        $result = [
            'warehouse_id' => $warehouseId,
            'stock_count' => (int)(clone $base)->count(),
            'listing_workload' => [
                'need_photo' => (int)($listing['need_photo'] ?? 0),
                'need_price' => (int)($listing['need_price'] ?? 0),
                'need_material' => (int)($listing['need_material'] ?? 0),
                'ready' => (int)($listing['ready'] ?? 0),
                'listed' => (int)($listing['listed'] ?? 0),
            ],
            'warehouses' => $warehouseRows,
            'entry' => [
                'web_path' => 'site/hsx_erp/stock',
                'miniapp_path' => 'addon/hsx_erp/pages/stock/list',
            ],
            'generated_at' => time(),
        ];
        $statItems = [
                ['title' => '在库数量', 'value' => $result['stock_count'], 'unit' => '台', 'tone' => 'primary', 'icon' => 'element Box'],
                ['title' => '待拍照', 'value' => $result['listing_workload']['need_photo'], 'unit' => '台', 'tone' => 'info'],
                ['title' => '待定价', 'value' => $result['listing_workload']['need_price'], 'unit' => '台', 'tone' => 'danger'],
        ];
        $warehouseColumns = [
            ['prop' => 'warehouse_name', 'label' => '仓库'],
            ['prop' => 'stock_count', 'label' => '库存数量', 'align' => 'right'],
        ];
        if ($canViewCost) {
            $result['stock_cost'] = round((float)(clone $base)->sum('total_cost'), 2);
            array_splice($statItems, 1, 0, [[
                'title' => '库存成本', 'value' => $result['stock_cost'], 'unit' => '元', 'tone' => 'warning', 'icon' => 'element Money',
            ]]);
            $warehouseColumns[] = ['prop' => 'stock_cost', 'label' => '库存成本', 'align' => 'right'];
        } else {
            foreach ($warehouseRows as &$warehouseRow) unset($warehouseRow['stock_cost']);
            unset($warehouseRow);
            $result['warehouses'] = $warehouseRows;
            $result['restricted_fields'] = ['stock_cost'];
        }
        $result['assistant_summary'] = sprintf(
            '当前在库 %d 台%s；待拍照 %d 台、待定价 %d 台、待完善资料 %d 台、已上架 %d 台。',
            $result['stock_count'],
            isset($result['stock_cost']) ? ('，库存成本 ¥' . number_format((float)$result['stock_cost'], 2, '.', '')) : '',
            $result['listing_workload']['need_photo'],
            $result['listing_workload']['need_price'],
            $result['listing_workload']['need_material'],
            $result['listing_workload']['listed']
        );
        $result['_presentation'] = ['blocks' => [
            ['type' => 'stat_grid', 'source_plugin' => 'hsx_erp', 'data' => ['items' => $statItems]],
            ['type' => 'table', 'source_plugin' => 'hsx_erp', 'data' => [
                'title' => '仓库库存分布',
                'columns' => $warehouseColumns,
                'rows' => $warehouseRows,
            ]],
            $this->routeActionBlock('查看库存明细', '/site/hsx_erp/stock'),
        ]];
        return $result;
    }

    public function financeSummary(int $siteId, string $side, array $arguments, array $actor): array
    {
        [$table, $numberField, $webPath, $miniappPath] = $this->financeMeta($side);
        $mine = !empty($arguments['mine']);
        $query = Db::name($table)->where('site_id', '=', $siteId)
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->whereRaw('amount > settled_amount');
        if ($mine) $query->where('task_assignee_uid', '=', (int)($actor['id'] ?? 0));
        $result = [
            'side' => $side,
            'scope' => $mine ? 'mine' : 'site',
            'open_count' => (int)(clone $query)->count(),
            'original_amount' => round((float)(clone $query)->sum('amount'), 2),
            'settled_amount' => round((float)(clone $query)->sum('settled_amount'), 2),
            'remaining_amount' => round((float)(clone $query)->sum(Db::raw('amount - settled_amount')), 2),
            'entry' => ['web_path' => $webPath, 'miniapp_path' => $miniappPath],
            'number_field' => $numberField,
            'generated_at' => time(),
        ];
        $sideName = $side === 'receivable' ? '应收' : '应付';
        $result['assistant_summary'] = sprintf(
            '%s当前有 %d 笔待处理，原额 ¥%s，已结算 ¥%s，剩余%s ¥%s。',
            $mine ? '我的' . $sideName : '全店' . $sideName,
            $result['open_count'],
            number_format((float)$result['original_amount'], 2, '.', ''),
            number_format((float)$result['settled_amount'], 2, '.', ''),
            $sideName,
            number_format((float)$result['remaining_amount'], 2, '.', '')
        );
        $result['_presentation'] = ['blocks' => [
            ['type' => 'stat_grid', 'source_plugin' => 'hsx_erp', 'data' => ['items' => [
                ['title' => '待处理' . $sideName, 'value' => $result['open_count'], 'unit' => '笔', 'tone' => 'danger'],
                ['title' => $sideName . '原额', 'value' => $result['original_amount'], 'unit' => '元', 'tone' => 'primary'],
                ['title' => '已结算', 'value' => $result['settled_amount'], 'unit' => '元', 'tone' => 'success'],
                ['title' => '剩余' . $sideName, 'value' => $result['remaining_amount'], 'unit' => '元', 'tone' => 'warning'],
            ]]],
            $this->routeActionBlock('查看' . $sideName . '明细', '/' . $webPath, ['status' => 'open']),
        ]];
        return $result;
    }

    public function searchFinance(int $siteId, string $side, array $arguments, array $actor): array
    {
        [$table, $numberField, $webPath, $miniappPath] = $this->financeMeta($side);
        $keyword = mb_substr(trim((string)($arguments['keyword'] ?? '')), 0, 100);
        $status = trim((string)($arguments['status'] ?? 'open')) ?: 'open';
        $mine = !empty($arguments['mine']);
        $limit = max(1, min(20, (int)($arguments['limit'] ?? 10)));
        $query = Db::name($table)->where('site_id', '=', $siteId);
        if ($status === 'open') {
            $query->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])->whereRaw('amount > settled_amount');
        } elseif (in_array($status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL, ErpDict::STATUS_SETTLED], true)) {
            $query->where('status', '=', $status);
        } else {
            $query->where('status', '<>', ErpDict::STATUS_VOID);
        }
        if ($mine) $query->where('task_assignee_uid', '=', (int)($actor['id'] ?? 0));
        if ($keyword !== '') {
            $query->whereLike($numberField . '|party_name|source_no|origin_no|business_reason|remark', '%' . $keyword . '%');
        }
        $this->applyRange($query, $arguments);
        $rows = $query->field('id,' . $numberField . ',party_id,party_name,source_type,source_id,source_no,origin_name,category_name,business_reason,task_assignee_uid,task_assignee_name,amount,settled_amount,status,occurred_at')
            ->order('occurred_at desc,id desc')->limit($limit)->select()->toArray();
        foreach ($rows as &$row) {
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
            $row['amount'] = round((float)$row['amount'], 2);
            $row['settled_amount'] = round((float)$row['settled_amount'], 2);
            $row['finance_status_name'] = $this->statusName((string)$row['status'], $side);
            $row['occurred_at_text'] = (int)$row['occurred_at'] > 0 ? date('Y-m-d H:i:s', (int)$row['occurred_at']) : '';
            $row['web_path'] = $webPath . '?status=' . urlencode((string)$row['status']) . '&source_no=' . urlencode((string)$row['source_no']);
            $row['miniapp_path'] = $miniappPath . '?status=' . urlencode((string)$row['status']) . '&source_no=' . urlencode((string)$row['source_no']);
        }
        unset($row);
        $sideName = $side === 'receivable' ? '应收' : '应付';
        $result = [
            'side' => $side,
            'scope' => $mine ? 'mine' : 'site',
            'total_returned' => count($rows),
            'items' => $rows,
            'generated_at' => time(),
            '_presentation' => ['blocks' => [
                ['type' => 'table', 'source_plugin' => 'hsx_erp', 'data' => [
                    'title' => $sideName . '明细',
                    'description' => $mine ? '仅展示分配给当前账号的数据' : '按当前账号的数据权限查询',
                    'columns' => [
                        ['prop' => 'party_name', 'label' => '往来单位', 'min_width' => 140],
                        ['prop' => $numberField, 'label' => $sideName . '单号', 'min_width' => 150],
                        ['prop' => 'source_no', 'label' => '来源单号', 'min_width' => 140],
                        ['prop' => 'amount', 'label' => '原额', 'align' => 'right'],
                        ['prop' => 'remain_amount', 'label' => '剩余', 'align' => 'right'],
                        ['prop' => 'finance_status_name', 'label' => '状态'],
                        ['prop' => 'task_assignee_name', 'label' => '负责人'],
                        ['prop' => 'occurred_at_text', 'label' => '发生时间', 'min_width' => 160],
                    ],
                    'rows' => $rows,
                ]],
                $this->routeActionBlock('进入' . $sideName . '列表', '/' . $webPath, ['status' => $status]),
            ]],
        ];
        $result['assistant_summary'] = count($rows) > 0
            ? sprintf('共查到 %d 笔符合条件的%s明细，已经按发生时间从近到远列出。', count($rows), $sideName)
            : '当前没有查到符合这些条件的' . $sideName . '明细。';
        return $result;
    }

    public function salesSummary(int $siteId, array $arguments, array $actor): array
    {
        $period = in_array((string)($arguments['period'] ?? 'today'), ['today', 'week', 'month'], true)
            ? (string)$arguments['period'] : 'today';
        $mine = !empty($arguments['mine']);
        $range = $this->periodRange($period);
        $query = Db::name('erp_sale_order')->where('site_id', '=', $siteId)
            ->where('status', '<>', ErpDict::STATUS_VOID)
            ->where('sale_at', '>=', $range['start_at'])->where('sale_at', '<=', $range['end_at']);
        if ($mine) $query->where('salesman_uid', '=', (int)($actor['id'] ?? 0));
        $orderIds = array_map('intval', (clone $query)->column('id'));
        $soldCount = $orderIds === [] ? 0 : (int)Db::name('erp_sale_item')->where('site_id', '=', $siteId)
            ->whereIn('sale_order_id', $orderIds)->where('status', '=', ErpDict::ASSET_SOLD)->sum('quantity');
        $canViewProfit = $this->can($actor, ['hsx_erp_sale_profit_report', 'hsx_erp_operating_finance']);
        $result = [
            'period' => $period,
            'scope' => $mine ? 'mine' : 'site',
            'range' => $range,
            'sale_order_count' => count($orderIds),
            'sold_count' => $soldCount,
            'sale_amount' => round((float)(clone $query)->sum('total_amount'), 2),
            'received_amount' => round((float)(clone $query)->sum('received_amount'), 2),
            'receivable_amount' => round((float)(clone $query)->sum('receivable_amount'), 2),
            'generated_at' => time(),
        ];
        $statItems = [
                ['title' => '销售单', 'value' => $result['sale_order_count'], 'unit' => '单', 'tone' => 'primary'],
                ['title' => '售出数量', 'value' => $result['sold_count'], 'unit' => '台/件', 'tone' => 'info'],
                ['title' => '销售额', 'value' => $result['sale_amount'], 'unit' => '元', 'tone' => 'success'],
                ['title' => '待收金额', 'value' => $result['receivable_amount'], 'unit' => '元', 'tone' => 'danger'],
        ];
        if ($canViewProfit) {
            $result['profit_amount'] = round((float)(clone $query)->sum('profit'), 2);
            array_splice($statItems, 3, 0, [[
                'title' => '销售毛利', 'value' => $result['profit_amount'], 'unit' => '元', 'tone' => 'warning',
            ]]);
        } else {
            $result['restricted_fields'] = ['profit_amount'];
        }
        $periodLabel = ['today' => '今日', 'week' => '本周', 'month' => '本月'][$period] ?? '今日';
        $result['assistant_summary'] = sprintf(
            '%s%s共有 %d 笔销售单，售出 %d 台/件，销售额 ¥%s%s，待收 ¥%s。',
            $mine ? '我的' : '全店',
            $periodLabel,
            $result['sale_order_count'],
            $result['sold_count'],
            number_format((float)$result['sale_amount'], 2, '.', ''),
            isset($result['profit_amount']) ? ('，毛利 ¥' . number_format((float)$result['profit_amount'], 2, '.', '')) : '',
            number_format((float)$result['receivable_amount'], 2, '.', '')
        );
        $result['_presentation'] = ['blocks' => [
            ['type' => 'stat_grid', 'source_plugin' => 'hsx_erp', 'data' => ['items' => $statItems]],
            $this->routeActionBlock('查看销售明细', '/site/hsx_erp/sale'),
        ]];
        return $result;
    }

    public function searchSales(int $siteId, array $arguments, array $actor): array
    {
        $keyword = mb_substr(trim((string)($arguments['keyword'] ?? '')), 0, 100);
        $mine = !empty($arguments['mine']);
        $limit = max(1, min(20, (int)($arguments['limit'] ?? 10)));
        $query = Db::name('erp_sale_order')->where('site_id', '=', $siteId)->where('status', '<>', ErpDict::STATUS_VOID);
        if ($mine) $query->where('salesman_uid', '=', (int)($actor['id'] ?? 0));
        if ($keyword !== '') $query->whereLike('sale_no|party_name|salesman_name|sale_channel|origin_no|remark', '%' . $keyword . '%');
        $this->applyRange($query, $arguments, 'sale_at');
        $rows = $query->field('id,sale_no,party_name,sale_channel,salesman_name,total_amount,total_cost,profit,received_amount,receivable_amount,finance_status,status,sale_at')
            ->order('sale_at desc,id desc')->limit($limit)->select()->toArray();
        $canViewProfit = $this->can($actor, ['hsx_erp_sale_profit_report', 'hsx_erp_operating_finance']);
        foreach ($rows as &$row) {
            foreach (['total_amount', 'total_cost', 'profit', 'received_amount', 'receivable_amount'] as $field) $row[$field] = round((float)$row[$field], 2);
            if (!$canViewProfit) unset($row['total_cost'], $row['profit']);
            $row['sale_at_text'] = (int)$row['sale_at'] > 0 ? date('Y-m-d H:i:s', (int)$row['sale_at']) : '';
        }
        unset($row);
        $columns = [
            ['prop' => 'sale_no', 'label' => '销售单号', 'min_width' => 150],
            ['prop' => 'party_name', 'label' => '客户', 'min_width' => 140],
            ['prop' => 'salesman_name', 'label' => '销售员'],
            ['prop' => 'total_amount', 'label' => '销售额', 'align' => 'right'],
            ['prop' => 'receivable_amount', 'label' => '待收', 'align' => 'right'],
            ['prop' => 'sale_at_text', 'label' => '销售时间', 'min_width' => 160],
        ];
        if ($canViewProfit) array_splice($columns, 4, 0, [[
            'prop' => 'profit', 'label' => '毛利', 'align' => 'right',
        ]]);
        $result = [
            'scope' => $mine ? 'mine' : 'site',
            'total_returned' => count($rows),
            'items' => $rows,
            'generated_at' => time(),
            '_presentation' => ['blocks' => [
                ['type' => 'table', 'source_plugin' => 'hsx_erp', 'data' => [
                    'title' => '销售明细',
                    'columns' => $columns,
                    'rows' => $rows,
                ]],
                $this->routeActionBlock('进入销售列表', '/site/hsx_erp/sale'),
            ]],
        ];
        if (!$canViewProfit) $result['restricted_fields'] = ['total_cost', 'profit'];
        $result['assistant_summary'] = count($rows) > 0
            ? sprintf('共查到 %d 笔符合条件的销售明细，已经按销售时间从近到远列出。', count($rows))
            : '当前没有查到符合这些条件的销售明细。';
        return $result;
    }

    private function financeMeta(string $side): array
    {
        return $side === 'receivable'
            ? ['erp_receivable', 'receivable_no', 'site/hsx_erp/receivable', 'addon/hsx_erp/pages/receivable/list']
            : ['erp_payable', 'payable_no', 'site/hsx_erp/payable', 'addon/hsx_erp/pages/payable/list'];
    }

    private function statusName(string $status, string $side): string
    {
        return match ($status) {
            ErpDict::STATUS_PENDING => $side === 'receivable' ? '待收款' : '待付款',
            ErpDict::STATUS_PARTIAL => $side === 'receivable' ? '部分收款' : '部分付款',
            ErpDict::STATUS_SETTLED => '已结清',
            ErpDict::STATUS_VOID => '已作废',
            default => $status,
        };
    }

    private function applyRange($query, array $arguments, string $field = 'occurred_at'): void
    {
        $start = trim((string)($arguments['start_date'] ?? ''));
        $end = trim((string)($arguments['end_date'] ?? ''));
        if ($start !== '' && strtotime($start . ' 00:00:00') !== false) $query->where($field, '>=', strtotime($start . ' 00:00:00'));
        if ($end !== '' && strtotime($end . ' 23:59:59') !== false) $query->where($field, '<=', strtotime($end . ' 23:59:59'));
    }

    private function periodRange(string $period): array
    {
        $start = match ($period) {
            'week' => strtotime('monday this week 00:00:00'),
            'month' => strtotime(date('Y-m-01 00:00:00')),
            default => strtotime(date('Y-m-d 00:00:00')),
        };
        return ['start_at' => $start, 'end_at' => time(), 'start' => date('Y-m-d H:i:s', $start), 'end' => date('Y-m-d H:i:s')];
    }

    private function routeActionBlock(string $label, string $route, array $params = []): array
    {
        return ['type' => 'action_group', 'source_plugin' => 'hsx_erp', 'data' => ['actions' => [[
            'id' => 'navigate', 'label' => $label, 'type' => 'route', 'route' => $route,
            'params' => $params, 'tone' => 'primary', 'approved' => true,
        ]]]];
    }

    private function can(array $actor, array $permissions): bool
    {
        if (!empty($actor['attributes']['is_site_admin'])) return true;
        return array_intersect($permissions, array_values(array_filter((array)($actor['permissions'] ?? []), 'is_string'))) !== [];
    }
}
