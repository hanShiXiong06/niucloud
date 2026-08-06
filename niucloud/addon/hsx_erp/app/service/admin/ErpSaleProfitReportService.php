<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\support\ErpPartyMemberNames;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 销售利润明细报表。
 *
 * 销售事实仍以 erp_sale_item 为唯一数据源，不另建统计表，避免报表与财务账产生双口径。
 * sale_price 保留原成交价用于审计；经营收入按“有效成本 + 当前毛利”计算，以包含补差、退款后的影响。
 */
class ErpSaleProfitReportService extends BaseAdminService
{
    private const EXPORT_LIMIT = 20000;
    private const VIEW_CONFIG_KEY = 'HSX_ERP_DEVICE_LEDGER_VIEW';

    public function meta(): array
    {
        return [
            'columns' => array_values($this->columnRegistry()),
            'presets' => array_values($this->presetRegistry()),
            'view' => $this->getViewConfig(),
            'rules' => [
                'required_columns' => ['model', 'serial_no', 'business_state'],
                'max_export_rows' => self::EXPORT_LIMIT,
                'date_semantics' => 'start_inclusive_end_exclusive',
                'timezone' => date_default_timezone_get(),
            ],
        ];
    }

    public function saveView(array $data): array
    {
        $registry = $this->columnRegistry();
        $required = ['model', 'serial_no', 'business_state'];
        $columns = [];
        foreach ((array)($data['columns'] ?? []) as $row) {
            $key = is_array($row) ? trim((string)($row['key'] ?? '')) : trim((string)$row);
            if ($key === '' || !isset($registry[$key]) || isset($columns[$key])) continue;
            $columns[$key] = [
                'key' => $key,
                'visible' => (int)(is_array($row) ? ($row['visible'] ?? 1) : 1) === 1 ? 1 : 0,
                'export' => (int)(is_array($row) ? ($row['export'] ?? 1) : 1) === 1 ? 1 : 0,
                'width' => max(70, min(500, (int)(is_array($row) ? ($row['width'] ?? $registry[$key]['width']) : $registry[$key]['width']))),
                'fixed' => in_array((string)(is_array($row) ? ($row['fixed'] ?? '') : ''), ['left', 'right'], true)
                    ? (string)$row['fixed'] : '',
            ];
        }
        foreach ($required as $key) {
            if (!isset($columns[$key])) {
                $columns[$key] = ['key' => $key, 'visible' => 1, 'export' => 1, 'width' => $registry[$key]['width'], 'fixed' => ''];
            }
            $columns[$key]['visible'] = 1;
        }
        if ($columns === []) throw new CommonException('请至少保留一个台账字段');
        $preset = trim((string)($data['preset'] ?? 'sales_profit'));
        if (!isset($this->presetRegistry()[$preset])) $preset = 'sales_profit';
        $configService = new CoreConfigService();
        $stored = $configService->getConfigValue($this->site_id, self::VIEW_CONFIG_KEY);
        $views = is_array($stored) ? (array)($stored['views'] ?? []) : [];
        $views[$preset] = array_values($columns);
        $value = ['preset' => $preset, 'columns' => array_values($columns), 'views' => $views, 'update_at' => time()];
        $configService->setConfig($this->site_id, self::VIEW_CONFIG_KEY, $value);
        return $this->getViewConfig();
    }

    public function getPage(array $where): array
    {
        $where = $this->applyPreset($where);
        $this->normalizeRange($where);
        $assetScope = (string)($where['dataset_scope'] ?? 'sales') === 'assets';
        $query = $assetScope ? $this->buildAssetQuery($where) : $this->buildQuery($where);
        $summary = $assetScope ? $this->assetSummary($where) : $this->effectiveSummary($where);
        $page = $query
            ->field($assetScope ? $this->assetFields() : $this->fields())
            ->order($assetScope ? 'a.update_at desc,a.id desc' : 'o.sale_at desc,i.id desc')
            ->paginate([
                'list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))),
                'page' => max(1, (int)($where['page'] ?? 1)),
            ])
            ->toArray();
        $page['data'] = $assetScope
            ? $this->normalizeAssetRows((array)($page['data'] ?? []))
            : $this->normalizeRows((array)($page['data'] ?? []));
        $page['summary'] = $summary;
        $page['range'] = [
            'start_at' => (int)$where['start_at'],
            'end_at' => (int)$where['end_exclusive'] - 1,
            'end_exclusive' => (int)$where['end_exclusive'],
            'start_date' => (string)$where['start_date'],
            'end_date' => (string)$where['end_date'],
        ];
        $page['preset'] = (string)$where['preset'];
        $page['dataset_scope'] = (string)$where['dataset_scope'];
        return $page;
    }

    public function exportRows(array $where): array
    {
        $where = $this->applyPreset($where);
        $this->normalizeRange($where);
        $assetScope = (string)($where['dataset_scope'] ?? 'sales') === 'assets';
        $query = $assetScope ? $this->buildAssetQuery($where) : $this->buildQuery($where);
        $summary = $assetScope ? $this->assetSummary($where) : $this->effectiveSummary($where);
        $rows = $query
            ->field($assetScope ? $this->assetFields() : $this->fields())
            ->order($assetScope ? 'a.stock_in_at asc,a.id asc' : 'o.sale_at asc,i.id asc')
            ->limit(self::EXPORT_LIMIT + 1)
            ->select()
            ->toArray();
        if (count($rows) > self::EXPORT_LIMIT) {
            throw new CommonException('单次最多导出20000条销售明细，请缩小查询日期后重试');
        }
        return [
            'rows' => $assetScope ? $this->normalizeAssetRows($rows) : $this->normalizeRows($rows),
            'summary' => $summary,
            'columns' => $this->resolveExportColumns($where),
            'range' => [
                'start_at' => (int)$where['start_at'],
                'end_at' => (int)$where['end_exclusive'] - 1,
                'start_date' => (string)$where['start_date'],
                'end_date' => (string)$where['end_date'],
            ],
            'generated_at' => time(),
        ];
    }

    private function buildQuery(array $where)
    {
        $orderTable = (new ErpSaleOrder())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        $prefix = (string)config('database.connections.mysql.prefix');
        $query = ErpSaleItem::alias('i')
            ->leftJoin($orderTable . ' o', 'o.id = i.sale_order_id AND o.site_id = i.site_id')
            ->leftJoin($assetTable . ' a', 'a.id = i.asset_id AND a.site_id = i.site_id')
            ->leftJoin($prefix . 'erp_purchase_order p', 'p.id = a.purchase_order_id AND p.site_id = a.site_id')
            ->leftJoin($prefix . 'erp_site_catalog_product cp', 'cp.site_product_id = a.catalog_product_id AND cp.site_id = a.site_id')
            ->where('i.site_id', '=', $this->site_id);

        $itemType = (string)($where['item_type'] ?? 'device');
        if (in_array($itemType, ['device', 'standard'], true)) {
            $query->where('i.item_type', '=', $itemType);
        }

        $tradeScope = (string)($where['trade_scope'] ?? 'effective');
        if ($tradeScope === 'invalid') {
            $query->where(function ($sub) {
                $sub->where('i.status', '<>', ErpDict::ASSET_SOLD)
                    ->whereOr('o.status', '=', 'void');
            });
        } elseif ($tradeScope !== 'all') {
            $query->where('i.status', '=', ErpDict::ASSET_SOLD)
                ->where('o.status', '<>', 'void');
        }

        $profitState = (string)($where['profit_state'] ?? '');
        if ($profitState === 'profit') {
            $query->where('i.profit', '>', 0);
        } elseif ($profitState === 'loss') {
            $query->where('i.profit', '<', 0);
        } elseif ($profitState === 'zero') {
            $query->where('i.profit', '=', 0);
        }

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike(
                'o.sale_no|o.party_name|o.sale_channel|o.salesman_name|i.model|i.product_code|i.imei|a.asset_no|a.sn|a.spec',
                '%' . $keyword . '%'
            );
        }
        if (!empty($where['party_id'])) {
            $this->applySnapshotPartyFilter($query, 'o.party_id', 'o.party_name', (int)$where['party_id']);
        }
        if (!empty($where['source_plugin'])) {
            $this->applyBusinessSourceFilter($query, 'o.origin_plugin', (string)$where['source_plugin']);
        }
        if (!empty($where['staff_uid'])) {
            $query->where('o.salesman_uid', '=', (int)$where['staff_uid']);
        }
        if (!empty($where['salesman_uid'])) {
            $query->where('o.salesman_uid', '=', (int)$where['salesman_uid']);
        }
        if (!empty($where['category_path'])) {
            $query->whereLike('a.category_path', trim((string)$where['category_path']) . '%');
        }
        if (!empty($where['catalog_product_id'])) {
            $query->where('a.catalog_product_id', '=', (int)$where['catalog_product_id']);
        }
        if (!empty($where['sale_channel_key'])) {
            $query->where('o.sale_channel_key', '=', trim((string)$where['sale_channel_key']));
        }
        if (!empty($where['warehouse_id'])) {
            $query->whereRaw(
                "IF(i.item_type = 'standard', i.warehouse_id, a.warehouse_id) = ?",
                [(int)$where['warehouse_id']]
            );
        }
        if (!empty($where['location_id'])) {
            $query->whereRaw(
                "IF(i.item_type = 'standard', i.location_id, a.location_id) = ?",
                [(int)$where['location_id']]
            );
        }
        if (($where['min_profit'] ?? '') !== '') {
            $query->where('i.profit', '>=', $this->decimal($where['min_profit'], 2));
        }
        if (($where['max_profit'] ?? '') !== '') {
            $query->where('i.profit', '<=', $this->decimal($where['max_profit'], 2));
        }
        $query->where('o.sale_at', '>=', (int)$where['start_at'])
            ->where('o.sale_at', '<', (int)$where['end_exclusive']);
        return $query;
    }

    private function buildAssetQuery(array $where)
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $query = ErpAsset::alias('a')
            ->leftJoin($prefix . 'erp_purchase_order p', 'p.id = a.purchase_order_id AND p.site_id = a.site_id')
            ->leftJoin($prefix . 'erp_sale_order o', 'o.id = a.sale_order_id AND o.site_id = a.site_id')
            ->leftJoin($prefix . 'erp_sale_item i', 'i.id = a.sale_item_id AND i.site_id = a.site_id')
            ->leftJoin($prefix . 'erp_site_catalog_product cp', 'cp.site_product_id = a.catalog_product_id AND cp.site_id = a.site_id')
            ->where('a.site_id', '=', $this->site_id);
        $assetState = trim((string)($where['asset_state'] ?? ''));
        if ($assetState !== '' && $assetState !== 'all') $query->where('a.status', '=', $assetState);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('a.model|a.imei|a.sn|a.asset_no|a.category_path|a.party_name|p.purchase_no|o.sale_no|o.party_name', '%' . $keyword . '%');
        }
        if (!empty($where['warehouse_id'])) $query->where('a.warehouse_id', '=', (int)$where['warehouse_id']);
        if (!empty($where['location_id'])) $query->where('a.location_id', '=', (int)$where['location_id']);
        if (!empty($where['category_path'])) $query->whereLike('a.category_path', trim((string)$where['category_path']) . '%');
        if (!empty($where['catalog_product_id'])) $query->where('a.catalog_product_id', '=', (int)$where['catalog_product_id']);
        $partyScope = (string)($where['party_scope'] ?? 'supplier') === 'customer' ? 'customer' : 'supplier';
        if (!empty($where['party_id'])) {
            $this->applySnapshotPartyFilter(
                $query,
                $partyScope === 'customer' ? 'o.party_id' : 'a.party_id',
                $partyScope === 'customer' ? 'o.party_name' : 'a.party_name',
                (int)$where['party_id']
            );
        }
        if (!empty($where['source_plugin'])) {
            $this->applyBusinessSourceFilter(
                $query,
                $partyScope === 'customer' ? 'o.origin_plugin' : 'a.source_plugin',
                (string)$where['source_plugin']
            );
        }
        if (!empty($where['staff_uid'])) {
            $query->where($partyScope === 'customer' ? 'o.salesman_uid' : 'p.purchaser_uid', '=', (int)$where['staff_uid']);
        }
        if (!empty($where['salesman_uid'])) $query->where('o.salesman_uid', '=', (int)$where['salesman_uid']);
        $dimension = (string)($where['time_dimension'] ?? 'stock_in');
        $field = match ($dimension) {
            'sale' => 'o.sale_at',
            'purchase' => 'p.purchase_at',
            'update' => 'a.update_at',
            default => 'IF(a.stock_in_at > 0, a.stock_in_at, a.create_at)',
        };
        if ((int)($where['ignore_time'] ?? 0) !== 1) {
            $query->whereRaw("{$field} >= ? AND {$field} < ?", [(int)$where['start_at'], (int)$where['end_exclusive']]);
        }
        return $query;
    }

    /** 兼容历史手工来源 erp/hsx_erp，其他插件按稳定命名空间精确筛选。 */
    private function applyBusinessSourceFilter($query, string $column, string $source): void
    {
        $source = trim($source);
        if ($source === 'erp') {
            $query->whereIn($column, ['erp', 'hsx_erp']);
            return;
        }
        if ($source !== '') $query->where($column, '=', $source);
    }

    /** 已选主体优先按 ID；只对 party_id=0 的历史快照按精确名称兜底。 */
    private function applySnapshotPartyFilter($query, string $idColumn, string $nameColumn, int $partyId): void
    {
        $partyName = trim((string)ErpParty::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $partyId],
        ])->value('party_name'));
        $query->where(function ($sub) use ($idColumn, $nameColumn, $partyId, $partyName) {
            $sub->where($idColumn, '=', $partyId);
            if ($partyName !== '') {
                $sub->whereOr(function ($legacy) use ($idColumn, $nameColumn, $partyName) {
                    $legacy->where($idColumn, '=', 0)->where($nameColumn, '=', $partyName);
                });
            }
        });
    }

    private function fields(): array
    {
        return [
            'i.id',
            'i.sale_order_id',
            'i.item_type',
            'i.quantity',
            'i.product_code',
            'i.imei',
            'i.model',
            'i.cost',
            'i.sale_price',
            'i.profit',
            'i.refunded_amount',
            'i.refunded_cost',
            'i.status',
            'a.asset_no',
            'a.sn',
            'a.spec',
            'a.category_name',
            'a.category_path',
            'cp.brand_name',
            'cp.series_name',
            'cp.product_name as catalog_product_name',
            'a.color',
            'a.battery',
            'a.warranty',
            'a.party_name as supplier_name',
            'a.purchase_cost',
            'a.adjust_cost',
            'a.refurbish_cost',
            'a.total_cost',
            'a.stock_in_at',
            'a.ownership_type',
            'a.listing_status',
            'a.source_plugin',
            'a.source_type',
            'a.remark',
            'p.purchase_no',
            'p.purchase_at',
            'p.purchaser_uid',
            'p.purchaser_name',
            "IF(i.item_type = 'standard', i.warehouse_name, a.warehouse_name) as warehouse_name",
            "IF(i.item_type = 'standard', i.location_name, a.location_name) as location_name",
            'o.sale_no',
            'o.status as order_status',
            'o.party_id',
            'o.party_name',
            'o.sale_channel',
            'o.sale_channel_key',
            'o.origin_plugin',
            'o.origin_plugin_name',
            'o.origin_type',
            'o.origin_name',
            'o.origin_no',
            'o.salesman_uid',
            'o.salesman_name',
            'o.operator_uid',
            'o.operator_name',
            'o.finance_status',
            'o.sale_at',
        ];
    }

    private function assetFields(): array
    {
        return [
            'a.id', 'a.id as asset_id', 'a.asset_no', 'a.imei', 'a.sn', 'a.model', 'a.spec',
            'a.category_name', 'a.category_path', 'a.color', 'a.battery', 'a.warranty',
            'a.catalog_product_id', 'cp.brand_name', 'cp.series_name', 'cp.product_name as catalog_product_name',
            'a.party_id as supplier_id', 'a.party_name as supplier_name', 'p.purchase_no', 'p.purchase_at',
            'p.purchaser_uid', 'p.purchaser_name', 'a.purchase_cost', 'a.adjust_cost', 'a.refurbish_cost', 'a.total_cost',
            'a.warehouse_id', 'a.warehouse_name', 'a.location_id', 'a.location_name', 'a.ownership_type',
            'a.owner_party_name', 'a.refurbish_status', 'a.listing_status', 'a.sale_target',
            'a.estimate_sale_price', 'a.retail_price', 'a.sale_price', 'a.profit', 'a.status',
            'a.source_plugin', 'a.source_type', 'a.remark', 'a.stock_in_at', 'a.create_at', 'a.update_at',
            'o.sale_no', 'o.sale_at', 'o.party_name', 'o.sale_channel', 'o.salesman_uid', 'o.salesman_name',
            'o.operator_uid', 'o.operator_name', 'o.status as order_status',
            'i.id as sale_item_id', 'i.quantity', 'i.cost', 'i.refunded_amount', 'i.refunded_cost',
        ];
    }

    private function summary($query): array
    {
        $effectiveCost = 'GREATEST(i.cost - i.refunded_cost, 0)';
        $quantity = $this->decimal((clone $query)->sum('i.quantity'), 3);
        $cost = $this->decimal((clone $query)->sum(Db::raw($effectiveCost)), 2);
        $profit = $this->decimal((clone $query)->sum('i.profit'), 2);
        return [
            'quantity' => $quantity,
            'amount' => $this->add($cost, $profit, 2),
            'cost' => $cost,
            'profit' => $profit,
            'profit_quantity' => $this->decimal((clone $query)->sum(Db::raw('CASE WHEN i.profit > 0 THEN i.quantity ELSE 0 END')), 3),
            'loss_quantity' => $this->decimal((clone $query)->sum(Db::raw('CASE WHEN i.profit < 0 THEN i.quantity ELSE 0 END')), 3),
            'zero_quantity' => $this->decimal((clone $query)->sum(Db::raw('CASE WHEN i.profit = 0 THEN i.quantity ELSE 0 END')), 3),
        ];
    }

    private function assetSummary(array $where): array
    {
        $query = $this->buildAssetQuery($where);
        $quantity = (string)(clone $query)->count('a.id');
        $cost = $this->decimal((clone $query)->sum('a.total_cost'), 2);
        $listValue = $this->decimal((clone $query)->sum(Db::raw('CASE WHEN a.retail_price > 0 THEN a.retail_price ELSE a.estimate_sale_price END')), 2);
        $profit = $this->decimal((clone $query)->where('a.status', '=', ErpDict::ASSET_SOLD)->sum('a.profit'), 2);
        return [
            'quantity' => $quantity, 'amount' => $listValue, 'cost' => $cost, 'profit' => $profit,
            'profit_quantity' => (string)(clone $query)->where('a.profit', '>', 0)->count('a.id'),
            'loss_quantity' => (string)(clone $query)->where('a.profit', '<', 0)->count('a.id'),
            'zero_quantity' => (string)(clone $query)->where('a.profit', '=', 0)->count('a.id'),
        ];
    }

    /**
     * 顶部经营汇总始终只统计真实成交。
     *
     * “已退/作废、全部留痕”只是审计列表的查看范围，不能因此把失效交易
     * 重新算入本月销售额和利润，否则切换标签时经营数据会改变口径。
     */
    private function effectiveSummary(array $where): array
    {
        $summaryWhere = $where;
        $summaryWhere['trade_scope'] = 'effective';
        return $this->summary($this->buildQuery($summaryWhere));
    }

    private function normalizeRows(array $rows): array
    {
        $rows = (new ErpSaleService())->appendReturnContext($rows);
        ErpPartyMemberNames::append($this->site_id, $rows);
        foreach ($rows as &$row) {
            $effective = (string)($row['status'] ?? '') === ErpDict::ASSET_SOLD
                && (string)($row['order_status'] ?? '') !== 'void';
            $effectiveCost = $this->subtractFloorZero((string)($row['cost'] ?? '0'), (string)($row['refunded_cost'] ?? '0'));
            $profit = $this->decimal($row['profit'] ?? 0, 2);
            $row['effective_trade'] = $effective ? 1 : 0;
            $row['serial_no'] = (string)($row['imei'] ?: ($row['sn'] ?: ($row['asset_no'] ?? '')));
            $row['business_state'] = $effective ? '已售' : $this->invalidStateName($row);
            $row['quantity'] = $this->decimal($row['quantity'] ?? 1, 3);
            $row['report_cost'] = $effective ? $effectiveCost : '0.00';
            $row['report_profit'] = $effective ? $profit : '0.00';
            $row['report_amount'] = $effective ? $this->add($effectiveCost, $profit, 2) : '0.00';
            $compare = $this->compare($profit, '0');
            $row['profit_state'] = $compare > 0 ? 'profit' : ($compare < 0 ? 'loss' : 'zero');
            $row['profit_state_name'] = $compare > 0 ? '盈利' : ($compare < 0 ? '亏损' : '持平');
            $row['trade_state_name'] = $effective ? '真实成交' : $this->invalidStateName($row);
            $row['sale_at_text'] = !empty($row['sale_at']) ? date('Y-m-d H:i:s', (int)$row['sale_at']) : '';
            $row['is_dropship'] = $this->isDropship($row) ? 1 : 0;
            $row['is_dropship_name'] = $row['is_dropship'] ? '是' : '否';
        }
        unset($row);
        return $rows;
    }

    private function normalizeAssetRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['quantity'] = '1.000';
            $row['serial_no'] = (string)($row['imei'] ?: ($row['sn'] ?: $row['asset_no']));
            $row['business_state'] = $this->assetStateName((string)($row['status'] ?? ''));
            $row['trade_state_name'] = $row['business_state'];
            $row['effective_trade'] = (string)($row['status'] ?? '') === ErpDict::ASSET_SOLD ? 1 : 0;
            $row['report_cost'] = $this->decimal($row['total_cost'] ?? 0, 2);
            $row['report_profit'] = $this->decimal($row['profit'] ?? 0, 2);
            $amount = $this->compare((string)($row['sale_price'] ?? '0'), '0') > 0
                ? (string)$row['sale_price']
                : ($this->compare((string)($row['retail_price'] ?? '0'), '0') > 0
                    ? (string)$row['retail_price'] : (string)($row['estimate_sale_price'] ?? '0'));
            $row['report_amount'] = $this->decimal($amount, 2);
            $compare = $this->compare($row['report_profit'], '0');
            $row['profit_state'] = $compare > 0 ? 'profit' : ($compare < 0 ? 'loss' : 'zero');
            $row['profit_state_name'] = $compare > 0 ? '盈利' : ($compare < 0 ? '亏损' : '持平');
            $row['stock_days'] = !empty($row['stock_in_at']) ? max(0, (int)floor((time() - (int)$row['stock_in_at']) / 86400)) : 0;
            foreach (['sale_at', 'purchase_at', 'stock_in_at', 'create_at', 'update_at'] as $field) {
                $row[$field . '_text'] = !empty($row[$field]) ? date('Y-m-d H:i:s', (int)$row[$field]) : '';
            }
        }
        unset($row);
        return $rows;
    }

    private function normalizeRange(array &$where): void
    {
        $startDate = trim((string)($where['start_date'] ?? ''));
        $endDate = trim((string)($where['end_date'] ?? ''));
        if (!$this->validDate($startDate)) {
            $legacy = (int)($where['start_at'] ?? 0);
            $startDate = $legacy > 0 ? date('Y-m-d', $legacy) : date('Y-m-01');
        }
        if (!$this->validDate($endDate)) {
            $legacy = (int)($where['end_at'] ?? 0);
            $endDate = $legacy > 0 ? date('Y-m-d', $legacy) : date('Y-m-d');
        }
        $startAt = strtotime($startDate . ' 00:00:00');
        $endExclusive = strtotime('+1 day', strtotime($endDate . ' 00:00:00'));
        if ($startAt === false || $endExclusive === false || $startAt >= $endExclusive) throw new CommonException('台账查询开始日期不能晚于结束日期');
        $where['start_date'] = $startDate;
        $where['end_date'] = $endDate;
        $where['start_at'] = $startAt;
        $where['end_at'] = $endExclusive - 1;
        $where['end_exclusive'] = $endExclusive;
    }

    private function validDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }

    private function applyPreset(array $where): array
    {
        $preset = trim((string)($where['preset'] ?? 'sales_profit'));
        $registry = $this->presetRegistry();
        if (!isset($registry[$preset])) $preset = 'sales_profit';
        $defaults = (array)$registry[$preset]['filters'];
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $where) || $where[$key] === '') $where[$key] = $value;
        }
        $where['preset'] = $preset;
        return $where;
    }

    private function presetRegistry(): array
    {
        return [
            'sales_profit' => [
                'key' => 'sales_profit', 'name' => '销售利润', 'description' => '按设备查看真实成交、收入、成本与利润',
                'filters' => ['dataset_scope' => 'sales', 'trade_scope' => 'effective', 'time_dimension' => 'sale'],
                'columns' => ['sale_date', 'model', 'category_path', 'quantity', 'sale_amount', 'total_cost', 'profit', 'customer_name', 'serial_no', 'salesman_name', 'sale_channel', 'business_state'],
            ],
            'loss' => [
                'key' => 'loss', 'name' => '亏损设备', 'description' => '只看当前统计期内发生亏损的真实成交设备',
                'filters' => ['dataset_scope' => 'sales', 'trade_scope' => 'effective', 'profit_state' => 'loss', 'time_dimension' => 'sale'],
                'columns' => ['sale_date', 'model', 'category_path', 'sale_amount', 'total_cost', 'profit', 'customer_name', 'serial_no', 'salesman_name', 'business_state'],
            ],
            'inventory' => [
                'key' => 'inventory', 'name' => '当前库存', 'description' => '设备当前所在仓位、来源、成本与库龄',
                'filters' => ['dataset_scope' => 'assets', 'asset_state' => ErpDict::ASSET_IN_STOCK, 'time_dimension' => 'stock_in', 'ignore_time' => 1],
                'columns' => ['stock_in_date', 'model', 'category_path', 'spec', 'serial_no', 'supplier_name', 'total_cost', 'warehouse_name', 'location_name', 'stock_days', 'listing_status', 'business_state', 'remark'],
            ],
            'lifecycle' => [
                'key' => 'lifecycle', 'name' => '设备全生命周期', 'description' => '从采购入库到当前库存、销售或退货状态的完整设备台账',
                'filters' => ['dataset_scope' => 'assets', 'asset_state' => 'all', 'time_dimension' => 'stock_in'],
                'columns' => ['purchase_date', 'stock_in_date', 'sale_date', 'model', 'category_path', 'spec', 'serial_no', 'supplier_name', 'purchase_no', 'total_cost', 'customer_name', 'sale_no', 'sale_amount', 'profit', 'warehouse_name', 'business_state', 'remark'],
            ],
            'invalid' => [
                'key' => 'invalid', 'name' => '退货/作废审计', 'description' => '保留发生过但已撤回的销售事实，不计入经营汇总',
                'filters' => ['dataset_scope' => 'sales', 'trade_scope' => 'invalid', 'time_dimension' => 'sale'],
                'columns' => ['sale_date', 'model', 'category_path', 'serial_no', 'customer_name', 'sale_no', 'sale_amount', 'total_cost', 'profit', 'salesman_name', 'business_state', 'remark'],
            ],
        ];
    }

    private function columnRegistry(): array
    {
        $column = static fn(string $key, string $label, string $group, int $width, string $format = 'text', bool $required = false): array => [
            'key' => $key, 'label' => $label, 'group' => $group, 'width' => $width, 'format' => $format,
            'required' => $required ? 1 : 0, 'exportable' => 1,
        ];
        return [
            'purchase_date' => $column('purchase_date', '采购日期', '时间', 116, 'date'),
            'stock_in_date' => $column('stock_in_date', '入库日期', '时间', 116, 'date'),
            'sale_date' => $column('sale_date', '销售日期', '时间', 116, 'date'),
            'model' => $column('model', '商品型号', '设备', 220, 'text', true),
            'category_path' => $column('category_path', '分类', '设备', 180),
            'brand_name' => $column('brand_name', '品牌', '设备', 110),
            'series_name' => $column('series_name', '系列', '设备', 130),
            'spec' => $column('spec', '规格', '设备', 160),
            'color' => $column('color', '颜色', '设备', 90),
            'serial_no' => $column('serial_no', '串号', '设备', 180, 'identifier', true),
            'quantity' => $column('quantity', '数量', '经营', 82, 'quantity'),
            'supplier_name' => $column('supplier_name', '来源/供应商', '采购', 150),
            'purchase_no' => $column('purchase_no', '采购单号', '采购', 180, 'identifier'),
            'purchaser_name' => $column('purchaser_name', '采购人', '采购', 110),
            'purchase_cost' => $column('purchase_cost', '采购成本', '金额', 120, 'money'),
            'adjust_cost' => $column('adjust_cost', '调整成本', '金额', 120, 'money'),
            'refurbish_cost' => $column('refurbish_cost', '整备成本', '金额', 120, 'money'),
            'total_cost' => $column('total_cost', '成本金额', '金额', 125, 'money'),
            'sale_amount' => $column('sale_amount', '销售净额', '金额', 125, 'money'),
            'profit' => $column('profit', '利润', '金额', 120, 'money'),
            'customer_name' => $column('customer_name', '客户名称', '销售', 150),
            'sale_no' => $column('sale_no', '销售单号', '销售', 180, 'identifier'),
            'salesman_name' => $column('salesman_name', '制单员', '销售', 110),
            'sale_channel' => $column('sale_channel', '销售渠道', '销售', 120),
            'warehouse_name' => $column('warehouse_name', '仓库', '库存', 130),
            'location_name' => $column('location_name', '库位', '库存', 130),
            'stock_days' => $column('stock_days', '库龄(天)', '库存', 95, 'integer'),
            'listing_status' => $column('listing_status', '上架状态', '库存', 110, 'listing_status'),
            'business_state' => $column('business_state', '状态', '状态', 110, 'state', true),
            'remark' => $column('remark', '备注', '其他', 220),
        ];
    }

    private function getViewConfig(): array
    {
        $stored = (new CoreConfigService())->getConfigValue($this->site_id, self::VIEW_CONFIG_KEY);
        $preset = is_array($stored) && isset($this->presetRegistry()[(string)($stored['preset'] ?? '')])
            ? (string)$stored['preset'] : 'sales_profit';
        $views = is_array($stored) ? (array)($stored['views'] ?? []) : [];
        // 兼容首版只有 columns 的配置结构。
        if ($views === [] && is_array($stored) && (array)($stored['columns'] ?? []) !== []) {
            $views[$preset] = (array)$stored['columns'];
        }
        $saved = (array)($views[$preset] ?? []);
        if ($saved === []) {
            $saved = array_map(fn(string $key): array => [
                'key' => $key, 'visible' => 1, 'export' => 1,
                'width' => (int)$this->columnRegistry()[$key]['width'], 'fixed' => '',
            ], $this->presetRegistry()[$preset]['columns']);
        }
        return ['preset' => $preset, 'columns' => array_values($saved), 'views' => $views, 'update_at' => (int)(is_array($stored) ? ($stored['update_at'] ?? 0) : 0)];
    }

    private function resolveExportColumns(array $where): array
    {
        $registry = $this->columnRegistry();
        $requested = array_values(array_filter(array_map('trim', explode(',', (string)($where['columns'] ?? '')))));
        if ($requested === []) {
            $config = $this->getViewConfig();
            $requested = array_column(array_filter($config['columns'], static fn(array $row): bool => (int)($row['export'] ?? 1) === 1), 'key');
        }
        if ($requested === []) $requested = $this->presetRegistry()[(string)$where['preset']]['columns'];
        $result = [];
        foreach ($requested as $key) if (isset($registry[$key])) $result[] = $registry[$key];
        return $result;
    }

    private function decimal(mixed $value, int $scale = 2): string
    {
        $number = is_numeric($value) ? (string)$value : '0';
        if (function_exists('bcadd')) return bcadd($number, '0', $scale);
        return number_format((float)$number, $scale, '.', '');
    }

    private function add(string $left, string $right, int $scale = 2): string
    {
        if (function_exists('bcadd')) return bcadd($left, $right, $scale);
        return number_format((float)$left + (float)$right, $scale, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) return bccomp($left, $right, 2);
        return (float)$left <=> (float)$right;
    }

    private function subtractFloorZero(string $left, string $right): string
    {
        $value = function_exists('bcsub') ? bcsub($left, $right, 2) : number_format((float)$left - (float)$right, 2, '.', '');
        return $this->compare($value, '0') < 0 ? '0.00' : $value;
    }

    private function assetStateName(string $status): string
    {
        return match ($status) {
            ErpDict::ASSET_IN_STOCK => '库存中', ErpDict::ASSET_SOLD => '已售',
            ErpDict::ASSET_RETURNED => '已退货', ErpDict::ASSET_VOID => '已作废', default => $status !== '' ? $status : '未知',
        };
    }

    private function invalidStateName(array $row): string
    {
        if ((string)($row['return_status'] ?? '') !== '') {
            return '已退货';
        }
        if ((string)($row['order_status'] ?? '') === 'void') {
            return '销售单已作废';
        }
        return '销售已撤销';
    }

    private function isDropship(array $row): bool
    {
        $text = implode(' ', [
            (string)($row['sale_channel'] ?? ''),
            (string)($row['origin_name'] ?? ''),
            (string)($row['origin_type'] ?? ''),
        ]);
        return str_contains($text, '代发') || str_contains(strtolower($text), 'dropship');
    }
}
