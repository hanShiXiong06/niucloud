<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpQuantityProduct;
use addon\hsx_erp\app\model\ErpQuantityStock;
use addon\hsx_erp\app\model\ErpQuantityStockFlow;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use core\exception\CommonException;
use think\facade\Db;

/**
 * ERP 数量库存的跨插件边界。
 *
 * 二手机资产库存仍以 erp_asset 为主；本服务只处理膜、壳等可计数耗材。
 */
final class ErpQuantityInventoryService extends ErpExternalContractService
{
    public const CAPABILITY_EVENT = 'ErpQuantityInventoryCapabilityRequested';
    public const CONSUME_EVENT = 'ErpQuantityInventoryConsumeRequested';
    public const RESTORE_EVENT = 'ErpQuantityInventoryRestoreRequested';
    public const ADJUST_EVENT = 'ErpQuantityInventoryAdjustRequested';

    /**
     * 数量商品档案查询。
     *
     * 补货、盘点和耗材绑定都应引用这里的 SKU 主数据，不能每次重新拼接名称。
     */
    public function productPage(array $where = []): array
    {
        $query = ErpQuantityProduct::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', (int)($where['status'] ?? 1)],
        ]);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->whereLike('product_name|spec|product_code|category_name|category_path', '%' . $keyword . '%');
        }
        if (!empty($where['category_path'])) {
            $query->whereLike('category_path', trim((string)$where['category_path']) . '%');
        }
        $page = $query->order('update_at desc,id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $productIds = array_values(array_filter(array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            (array)($page['data'] ?? [])
        )));
        $stockMap = [];
        if ($productIds !== []) {
            $stockRows = ErpQuantityStock::where([
                ['site_id', '=', $this->site_id],
                ['product_id', 'in', $productIds],
            ])->field('product_id,SUM(quantity) stock_quantity,SUM(inventory_amount) inventory_amount')
                ->group('product_id')->select()->toArray();
            foreach ($stockRows as $stock) $stockMap[(int)$stock['product_id']] = $stock;
        }
        foreach ($page['data'] as &$row) {
            $stock = $stockMap[(int)$row['id']] ?? [];
            $quantity = round((float)($stock['stock_quantity'] ?? 0), 3);
            $amount = round((float)($stock['inventory_amount'] ?? 0), 2);
            $row['stock_quantity'] = $quantity;
            $row['inventory_amount'] = $amount;
            $row['average_cost'] = $quantity > 0 ? round($amount / $quantity, 6) : 0;
            $row['display_name'] = trim((string)$row['product_name'] . ' ' . (string)($row['spec'] ?? ''));
        }
        unset($row);
        return $page;
    }

    /** 创建 ERP 标品 SKU；同站点同编码或同名称规格会直接复用，避免重复档案。 */
    public function createProduct(array $data): array
    {
        $name = mb_substr(trim((string)($data['product_name'] ?? '')), 0, 120);
        $spec = mb_substr(trim((string)($data['spec'] ?? '')), 0, 120);
        $unit = mb_substr(trim((string)($data['unit'] ?? '件')) ?: '件', 0, 20);
        if ($name === '') throw new CommonException('请填写商品名称');
        $category = $this->resolveCategory((string)($data['category_path'] ?? ''));
        $code = mb_substr(strtoupper(trim((string)($data['product_code'] ?? ''))), 0, 60);
        $existingQuery = ErpQuantityProduct::where([['site_id', '=', $this->site_id]]);
        if ($code !== '') {
            $existingQuery->where('product_code', '=', $code);
        } else {
            $existingQuery->where([
                ['product_name', '=', $name],
                ['spec', '=', $spec],
                ['unit', '=', $unit],
            ]);
        }
        $existing = $existingQuery->findOrEmpty();
        if (!$existing->isEmpty()) {
            // 兼容早期没有分类字段的标品：再次选用时可顺手补齐，但不静默改写已有分类。
            if (trim((string)$existing->category_path) === '') {
                $existing->save([
                    'category_name' => $category['category_name'],
                    'category_path' => $category['category_path'],
                    'update_at' => time(),
                ]);
            }
            return $this->productResult($existing, false);
        }
        if ($code === '') {
            $code = 'SP' . strtoupper(substr(hash('sha256', $this->site_id . '|' . $name . '|' . $spec . '|' . $unit), 0, 14));
        }
        $now = time();
        $product = ErpQuantityProduct::create([
            'site_id' => (int)$this->site_id,
            'product_code' => $code,
            'product_name' => $name,
            'spec' => $spec,
            'catalog_product_id' => max(0, (int)($data['catalog_product_id'] ?? 0)),
            'category_name' => $category['category_name'],
            'category_path' => $category['category_path'],
            'unit' => $unit,
            'source_plugin' => 'hsx_erp',
            'source_id' => $code,
            'status' => 1,
            'remark' => mb_substr(trim((string)($data['remark'] ?? 'ERP标品档案')), 0, 255),
            'create_at' => $now,
            'update_at' => $now,
        ]);
        return $this->productResult($product, true);
    }

    /** 历史标品分类修复入口；库存事实不变，只更新商品主数据。 */
    public function updateProductCategory(int $id, array $data): array
    {
        $product = $this->productInfo($id);
        $category = $this->resolveCategory((string)($data['category_path'] ?? ''));
        $product->save([
            'category_name' => $category['category_name'],
            'category_path' => $category['category_path'],
            'update_at' => time(),
        ]);
        return $this->productResult($product, false);
    }

    public function productInfo(int $id): ErpQuantityProduct
    {
        $product = ErpQuantityProduct::where([
            ['site_id', '=', $this->site_id], ['id', '=', $id], ['status', '=', 1],
        ])->findOrEmpty();
        if ($product->isEmpty()) throw new CommonException('标品档案不存在或已停用');
        return $product;
    }

    /**
     * 销售开单使用的数量库存列表。
     * 返回库存余额而不是商品档案，确保同一标品在不同仓位可分别选择和扣减。
     */
    public function saleStockPage(array $where): array
    {
        $productTable = (new ErpQuantityProduct())->getTable();
        $warehouseTable = (new ErpWarehouse())->getTable();
        $locationTable = (new ErpWarehouseLocation())->getTable();
        $query = ErpQuantityStock::alias('s')
            ->join($productTable . ' p', 'p.id = s.product_id AND p.site_id = s.site_id')
            ->join($warehouseTable . ' w', 'w.id = s.warehouse_id AND w.site_id = s.site_id')
            ->leftJoin($locationTable . ' l', 'l.id = s.location_id AND l.site_id = s.site_id')
            ->where([
                ['s.site_id', '=', $this->site_id],
                ['p.status', '=', 1],
                ['w.status', '=', 1],
                ['w.allow_direct_sale', '=', 1],
            ])->where('s.quantity', '>', 0);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('p.product_name|p.product_code|w.warehouse_name|l.location_name', '%' . $keyword . '%');
        }
        if (!empty($where['warehouse_id'])) $query->where('s.warehouse_id', '=', (int)$where['warehouse_id']);
        if (!empty($where['location_id'])) $query->where('s.location_id', '=', (int)$where['location_id']);
        $stockIds = array_values(array_unique(array_filter(array_map('intval', (array)($where['stock_ids'] ?? [])))));
        if ($stockIds !== []) $query->whereIn('s.id', $stockIds);
        $page = $query->field([
            's.id', 's.product_id', 's.warehouse_id', 's.location_id', 's.quantity', 's.inventory_amount',
            'p.product_code', 'p.product_name', 'p.unit',
            'w.warehouse_name', 'w.warehouse_type', 'l.location_name',
        ])->order('s.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $quantity = (float)($row['quantity'] ?? 0);
            $amount = round((float)($row['inventory_amount'] ?? 0), 2);
            $row['stock_id'] = (int)$row['id'];
            $row['item_type'] = 'standard';
            $row['quantity_product_id'] = (int)$row['product_id'];
            $row['model'] = (string)$row['product_name'];
            $row['available_quantity'] = $quantity;
            $row['average_cost'] = $quantity > 0 ? round($amount / $quantity, 6) : 0;
            $row['sale_cost_basis'] = $row['average_cost'];
        }
        unset($row);
        return $page;
    }

    /**
     * 标品销售出库并返回本次移动加权成本快照。
     * 方法由 ERP 内部销售事务调用，不经过跨插件信封。
     */
    public function saleOutbound(array $request): array
    {
        $eventId = mb_substr(trim((string)($request['event_id'] ?? '')), 0, 100);
        if ($eventId === '') throw new CommonException('标品销售出库缺少幂等键');
        $existing = ErpQuantityStockFlow::where([
            ['site_id', '=', $this->site_id], ['request_id', '=', $eventId],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) {
            return [
                'flow_id' => (int)$existing->id,
                'product_id' => (int)$existing->product_id,
                'cost_amount' => round((float)$existing->amount, 2),
                'unit_cost' => round((float)$existing->unit_cost, 6),
                'stock_before' => (float)$existing->before_quantity,
                'stock_after' => (float)$existing->after_quantity,
            ];
        }
        $stock = ErpQuantityStock::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)($request['stock_id'] ?? 0)],
        ])->lock(true)->findOrEmpty();
        if ($stock->isEmpty()) throw new CommonException('标品库存不存在');
        $quantity = $this->quantity($request['quantity'] ?? 0);
        $before = (float)$stock->quantity;
        if ($quantity <= 0 || $quantity > $before + 0.0001) {
            throw new CommonException('标品销售数量必须大于0且不能超过当前库存');
        }
        $product = ErpQuantityProduct::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$stock->product_id], ['status', '=', 1],
        ])->findOrEmpty();
        $warehouse = ErpWarehouse::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$stock->warehouse_id], ['status', '=', 1],
        ])->findOrEmpty();
        if ($product->isEmpty() || $warehouse->isEmpty() || (int)$warehouse->allow_direct_sale !== 1) {
            throw new CommonException('标品档案或所在仓库不可销售');
        }
        $location = ErpWarehouseLocation::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$stock->location_id],
        ])->findOrEmpty();
        $beforeAmount = round((float)($stock->inventory_amount ?? 0), 2);
        $costAmount = abs($quantity - $before) < 0.0001
            ? $beforeAmount
            : round(($before > 0 ? $beforeAmount / $before : 0) * $quantity, 2);
        $after = $this->quantity($before - $quantity);
        $afterAmount = max(0, round($beforeAmount - $costAmount, 2));
        $now = time();
        $stock->save(['quantity' => $after, 'inventory_amount' => $afterAmount, 'update_at' => $now]);
        $flow = ErpQuantityStockFlow::create([
            'site_id' => $this->site_id, 'request_id' => $eventId,
            'product_id' => (int)$product->id, 'product_name' => (string)$product->product_name,
            'warehouse_id' => (int)$warehouse->id, 'warehouse_name' => (string)$warehouse->warehouse_name,
            'location_id' => (int)($location->id ?? 0), 'location_name' => (string)($location->location_name ?? ''),
            'direction' => 'out', 'quantity' => $quantity,
            'unit_cost' => $quantity > 0 ? round($costAmount / $quantity, 6) : 0,
            'amount' => $costAmount, 'before_quantity' => $before, 'after_quantity' => $after,
            'biz_type' => 'sale_standard', 'biz_id' => max(0, (int)($request['biz_id'] ?? 0)),
            'biz_no' => mb_substr(trim((string)($request['biz_no'] ?? '')), 0, 80),
            'source_plugin' => 'hsx_erp', 'operator_uid' => max(0, (int)($request['operator_uid'] ?? 0)),
            'operator_name' => mb_substr(trim((string)($request['operator_name'] ?? '')), 0, 60),
            'remark' => mb_substr(trim((string)($request['remark'] ?? '标品销售出库')), 0, 255),
            'occurred_at' => max(1, (int)($request['occurred_at'] ?? $now)), 'create_at' => $now,
        ]);
        return [
            'flow_id' => (int)$flow->id, 'stock_id' => (int)$stock->id,
            'product_id' => (int)$product->id, 'product_code' => (string)$product->product_code,
            'product_name' => (string)$product->product_name, 'unit' => (string)$product->unit,
            'warehouse_id' => (int)$warehouse->id, 'warehouse_name' => (string)$warehouse->warehouse_name,
            'location_id' => (int)($location->id ?? 0), 'location_name' => (string)($location->location_name ?? ''),
            'quantity' => $quantity, 'cost_amount' => $costAmount,
            'unit_cost' => $quantity > 0 ? round($costAmount / $quantity, 6) : 0,
            'stock_before' => $before, 'stock_after' => $after,
        ];
    }

    /** 撤销销售或退货时，按原销售成本快照返还数量与成本。 */
    public function saleRestore(array $request): array
    {
        $eventId = mb_substr(trim((string)($request['event_id'] ?? '')), 0, 100);
        if ($eventId === '') throw new CommonException('标品销售返库缺少幂等键');
        $existing = ErpQuantityStockFlow::where([
            ['site_id', '=', $this->site_id], ['request_id', '=', $eventId],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) return ['flow_id' => (int)$existing->id, 'status' => 'duplicate'];
        $stock = ErpQuantityStock::where([
            ['site_id', '=', $this->site_id],
            ['product_id', '=', (int)($request['product_id'] ?? 0)],
            ['warehouse_id', '=', (int)($request['warehouse_id'] ?? 0)],
            ['location_id', '=', (int)($request['location_id'] ?? 0)],
        ])->lock(true)->findOrEmpty();
        if ($stock->isEmpty()) throw new CommonException('标品原库存位置不存在，无法返库');
        $quantity = $this->quantity($request['quantity'] ?? 0);
        $amount = round((float)($request['cost_amount'] ?? 0), 2);
        if ($quantity <= 0 || $amount < 0) throw new CommonException('标品返库数量或成本不正确');
        $product = ErpQuantityProduct::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$stock->product_id],
        ])->findOrEmpty();
        $warehouse = ErpWarehouse::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$stock->warehouse_id],
        ])->findOrEmpty();
        $location = ErpWarehouseLocation::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$stock->location_id],
        ])->findOrEmpty();
        $before = (float)$stock->quantity;
        $after = $this->quantity($before + $quantity);
        $stock->save([
            'quantity' => $after,
            'inventory_amount' => round((float)($stock->inventory_amount ?? 0) + $amount, 2),
            'update_at' => time(),
        ]);
        $flow = ErpQuantityStockFlow::create([
            'site_id' => $this->site_id, 'request_id' => $eventId,
            'product_id' => (int)$stock->product_id, 'product_name' => (string)($product->product_name ?? ''),
            'warehouse_id' => (int)$stock->warehouse_id, 'warehouse_name' => (string)($warehouse->warehouse_name ?? ''),
            'location_id' => (int)$stock->location_id, 'location_name' => (string)($location->location_name ?? ''),
            'direction' => 'in', 'quantity' => $quantity,
            'unit_cost' => $quantity > 0 ? round($amount / $quantity, 6) : 0, 'amount' => $amount,
            'before_quantity' => $before, 'after_quantity' => $after,
            'biz_type' => 'sale_standard_restore', 'biz_id' => max(0, (int)($request['biz_id'] ?? 0)),
            'biz_no' => mb_substr(trim((string)($request['biz_no'] ?? '')), 0, 80),
            'source_plugin' => 'hsx_erp', 'operator_uid' => max(0, (int)($request['operator_uid'] ?? 0)),
            'operator_name' => mb_substr(trim((string)($request['operator_name'] ?? '')), 0, 60),
            'remark' => mb_substr(trim((string)($request['remark'] ?? '标品销售返库')), 0, 255),
            'occurred_at' => time(), 'create_at' => time(),
        ]);
        return ['flow_id' => (int)$flow->id, 'stock_before' => $before, 'stock_after' => $after];
    }

    public function capability(array $event): array
    {
        $envelope = $this->normalizeEnvelope($event, 'erp.quantity_inventory.capability_requested.v1', 1);
        $warehouses = array_values(array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['warehouse_name'],
                'is_default' => (int)($row['is_default'] ?? 0),
                'locations' => array_map(static fn(array $location): array => [
                    'id' => (int)$location['id'],
                    'name' => (string)$location['location_name'],
                ], (array)($row['locations'] ?? [])),
            ];
        }, (new ErpWarehouseService())->getOptions()));
        return [
            'consumer' => 'hsx_erp',
            'event_id' => (string)$envelope['event_id'],
            'status' => 'processed',
            'available' => 1,
            'provider_name' => 'ERP 数量库存',
            'warehouses' => $warehouses,
        ];
    }

    public function consume(array $event): array
    {
        $payload = array_merge($event, $this->normalizeEnvelope($event, 'erp.quantity_inventory.consume_requested.v1', 1));
        return $this->consumeOnce($payload, self::CONSUME_EVENT, function (array $request): array {
            $quantity = $this->quantity($request['quantity'] ?? 0);
            if ($quantity <= 0) throw new CommonException('耗材扣减数量必须大于0');
            $mode = (string)($request['mode'] ?? 'auto');
            if (!in_array($mode, ['auto', 'strict'], true)) throw new CommonException('库存扣减模式不正确');
            [$warehouse, $location] = $this->resolvePosition(
                (int)($request['warehouse_id'] ?? 0),
                (int)($request['location_id'] ?? 0)
            );
            $product = $this->resolveProduct($request);
            $stock = ErpQuantityStock::where([
                ['site_id', '=', $this->site_id],
                ['product_id', '=', (int)$product->id],
                ['warehouse_id', '=', (int)$warehouse->id],
                ['location_id', '=', (int)($location->id ?? 0)],
            ])->lock(true)->findOrEmpty();
            $before = $stock->isEmpty() ? 0.0 : (float)$stock->quantity;
            $after = $this->quantity($before - $quantity);
            if ($mode === 'strict' && $after < 0) {
                throw new CommonException(sprintf(
                    '“%s”库存不足：当前%s%s，本次需%s%s',
                    (string)$product->product_name,
                    $this->formatQuantity($before),
                    (string)$product->unit,
                    $this->formatQuantity($quantity),
                    (string)$product->unit
                ));
            }
            $beforeAmount = $stock->isEmpty() ? 0.0 : round((float)($stock->inventory_amount ?? 0), 2);
            $costAmount = array_key_exists('cost_amount', $request)
                ? max(0, round((float)$request['cost_amount'], 2))
                : ($before > 0
                    ? round(($beforeAmount / $before) * min($quantity, $before), 2)
                    : 0.0);
            $afterAmount = max(0, round($beforeAmount - $costAmount, 2));
            $now = time();
            if ($stock->isEmpty()) {
                $stock = ErpQuantityStock::create([
                    'site_id' => (int)$this->site_id,
                    'product_id' => (int)$product->id,
                    'warehouse_id' => (int)$warehouse->id,
                    'location_id' => (int)($location->id ?? 0),
                    'quantity' => $after,
                    'inventory_amount' => $afterAmount,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            } else {
                $stock->save(['quantity' => $after, 'inventory_amount' => $afterAmount, 'update_at' => $now]);
            }
            $flow = ErpQuantityStockFlow::create([
                'site_id' => (int)$this->site_id,
                'request_id' => (string)$request['event_id'],
                'product_id' => (int)$product->id,
                'product_name' => (string)$product->product_name,
                'warehouse_id' => (int)$warehouse->id,
                'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id' => (int)($location->id ?? 0),
                'location_name' => (string)($location->location_name ?? ''),
                'direction' => 'out',
                'quantity' => $quantity,
                'unit_cost' => $quantity > 0 ? round($costAmount / $quantity, 6) : 0,
                'amount' => $costAmount,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'biz_type' => mb_substr(trim((string)($request['biz_type'] ?? 'member_card_redemption')), 0, 60),
                'biz_id' => max(0, (int)($request['biz_id'] ?? 0)),
                'biz_no' => mb_substr(trim((string)($request['biz_no'] ?? '')), 0, 80),
                'source_plugin' => (string)$request['source_plugin'],
                'operator_uid' => max(0, (int)($request['operator_uid'] ?? 0)),
                'operator_name' => mb_substr(trim((string)($request['operator_name'] ?? '')), 0, 60),
                'remark' => mb_substr(trim((string)($request['remark'] ?? '')), 0, 255),
                'occurred_at' => (int)$request['occurred_at'],
                'create_at' => $now,
            ]);
            return [
                'inventory_status' => $after < 0 ? 'negative' : 'deducted',
                'product_id' => (int)$product->id,
                'flow_id' => (int)$flow->id,
                'warehouse_id' => (int)$warehouse->id,
                'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id' => (int)($location->id ?? 0),
                'location_name' => (string)($location->location_name ?? ''),
                'stock_before' => $before,
                'stock_after' => $after,
                'message' => $after < 0 ? '库存已扣减并形成缺货提醒' : '耗材库存已扣减',
            ];
        });
    }

    public function adjust(array $event): array
    {
        $payload = array_merge($event, $this->normalizeEnvelope($event, 'erp.quantity_inventory.adjust_requested.v1', 1));
        return $this->consumeOnce($payload, self::ADJUST_EVENT, function (array $request): array {
            $target = $this->quantity($request['target_quantity'] ?? 0);
            if ($target < 0) throw new CommonException('盘点库存不能小于0');
            [$warehouse, $location] = $this->resolvePosition(
                (int)($request['warehouse_id'] ?? 0),
                (int)($request['location_id'] ?? 0)
            );
            $product = $this->resolveProduct($request);
            $stock = ErpQuantityStock::where([
                ['site_id', '=', $this->site_id], ['product_id', '=', (int)$product->id],
                ['warehouse_id', '=', (int)$warehouse->id], ['location_id', '=', (int)($location->id ?? 0)],
            ])->lock(true)->findOrEmpty();
            $before = $stock->isEmpty() ? 0.0 : (float)$stock->quantity;
            $now = time();
            if ($stock->isEmpty()) {
                $stock = ErpQuantityStock::create([
                    'site_id' => (int)$this->site_id, 'product_id' => (int)$product->id,
                    'warehouse_id' => (int)$warehouse->id, 'location_id' => (int)($location->id ?? 0),
                    'quantity' => $target, 'create_at' => $now, 'update_at' => $now,
                ]);
            } else {
                $stock->save(['quantity' => $target, 'update_at' => $now]);
            }
            ErpQuantityStockFlow::create([
                'site_id' => (int)$this->site_id, 'request_id' => (string)$request['event_id'],
                'product_id' => (int)$product->id, 'product_name' => (string)$product->product_name,
                'warehouse_id' => (int)$warehouse->id, 'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id' => (int)($location->id ?? 0), 'location_name' => (string)($location->location_name ?? ''),
                'direction' => 'adjust', 'quantity' => abs($target - $before),
                'before_quantity' => $before, 'after_quantity' => $target,
                'biz_type' => 'inventory_adjustment', 'biz_id' => max(0, (int)($request['biz_id'] ?? 0)),
                'biz_no' => mb_substr(trim((string)($request['biz_no'] ?? '')), 0, 80),
                'source_plugin' => (string)$request['source_plugin'],
                'operator_uid' => max(0, (int)($request['operator_uid'] ?? 0)),
                'operator_name' => mb_substr(trim((string)($request['operator_name'] ?? '')), 0, 60),
                'remark' => mb_substr(trim((string)($request['remark'] ?? '盘点调整')), 0, 255),
                'occurred_at' => (int)$request['occurred_at'], 'create_at' => $now,
            ]);
            return [
                'inventory_status' => 'adjusted', 'product_id' => (int)$product->id,
                'warehouse_id' => (int)$warehouse->id, 'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id' => (int)($location->id ?? 0), 'location_name' => (string)($location->location_name ?? ''),
                'stock_before' => $before, 'stock_after' => $target, 'message' => '库存数量已更新',
            ];
        });
    }

    public function restore(array $event): array
    {
        $payload = array_merge($event, $this->normalizeEnvelope($event, 'erp.quantity_inventory.restore_requested.v1', 1));
        return $this->consumeOnce($payload, self::RESTORE_EVENT, function (array $request): array {
            $quantity = $this->quantity($request['quantity'] ?? 0);
            if ($quantity <= 0) throw new CommonException('耗材返库数量必须大于0');
            [$warehouse, $location] = $this->resolvePosition(
                (int)($request['warehouse_id'] ?? 0),
                (int)($request['location_id'] ?? 0)
            );
            $product = $this->resolveProduct($request);
            $stock = ErpQuantityStock::where([
                ['site_id', '=', $this->site_id],
                ['product_id', '=', (int)$product->id],
                ['warehouse_id', '=', (int)$warehouse->id],
                ['location_id', '=', (int)($location->id ?? 0)],
            ])->lock(true)->findOrEmpty();
            $before = $stock->isEmpty() ? 0.0 : (float)$stock->quantity;
            $after = $this->quantity($before + $quantity);
            $costAmount = max(0, round((float)($request['cost_amount'] ?? 0), 2));
            $beforeAmount = $stock->isEmpty() ? 0.0 : round((float)($stock->inventory_amount ?? 0), 2);
            $afterAmount = round($beforeAmount + $costAmount, 2);
            $now = time();
            if ($stock->isEmpty()) {
                $stock = ErpQuantityStock::create([
                    'site_id' => (int)$this->site_id,
                    'product_id' => (int)$product->id,
                    'warehouse_id' => (int)$warehouse->id,
                    'location_id' => (int)($location->id ?? 0),
                    'quantity' => $after,
                    'inventory_amount' => $afterAmount,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            } else {
                $stock->save(['quantity' => $after, 'inventory_amount' => $afterAmount, 'update_at' => $now]);
            }
            $flow = ErpQuantityStockFlow::create([
                'site_id' => (int)$this->site_id,
                'request_id' => (string)$request['event_id'],
                'product_id' => (int)$product->id,
                'product_name' => (string)$product->product_name,
                'warehouse_id' => (int)$warehouse->id,
                'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id' => (int)($location->id ?? 0),
                'location_name' => (string)($location->location_name ?? ''),
                'direction' => 'in',
                'quantity' => $quantity,
                'unit_cost' => $quantity > 0 ? round($costAmount / $quantity, 6) : 0,
                'amount' => $costAmount,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'biz_type' => mb_substr(trim((string)($request['biz_type'] ?? 'member_card_redemption_reverse')), 0, 60),
                'biz_id' => max(0, (int)($request['biz_id'] ?? 0)),
                'biz_no' => mb_substr(trim((string)($request['biz_no'] ?? '')), 0, 80),
                'source_plugin' => (string)$request['source_plugin'],
                'operator_uid' => max(0, (int)($request['operator_uid'] ?? 0)),
                'operator_name' => mb_substr(trim((string)($request['operator_name'] ?? '')), 0, 60),
                'remark' => mb_substr(trim((string)($request['remark'] ?? '核销冲正返库')), 0, 255),
                'occurred_at' => (int)$request['occurred_at'],
                'create_at' => $now,
            ]);
            return [
                'inventory_status' => 'restored',
                'product_id' => (int)$product->id,
                'flow_id' => (int)$flow->id,
                'warehouse_id' => (int)$warehouse->id,
                'warehouse_name' => (string)$warehouse->warehouse_name,
                'location_id' => (int)($location->id ?? 0),
                'location_name' => (string)($location->location_name ?? ''),
                'stock_before' => $before,
                'stock_after' => $after,
                'message' => '核销耗材已返库',
            ];
        });
    }

    private function resolveProduct(array $request): ErpQuantityProduct
    {
        $quantityProductId = max(0, (int)($request['quantity_product_id'] ?? 0));
        if ($quantityProductId > 0) return $this->productInfo($quantityProductId);
        $sourceId = mb_substr(trim((string)($request['source_id'] ?? '')), 0, 80);
        $name = mb_substr(trim((string)($request['product_name'] ?? '')), 0, 120);
        if ($sourceId === '' || $name === '') throw new CommonException('耗材档案缺少来源ID或名称');
        $requestedCode = mb_substr(trim((string)($request['product_code'] ?? '')), 0, 60);
        $product = ErpQuantityProduct::where([
            ['site_id', '=', $this->site_id], ['source_plugin', '=', (string)$request['source_plugin']], ['source_id', '=', $sourceId],
        ])->lock(true)->findOrEmpty();
        if ($product->isEmpty() && $requestedCode !== '') {
            $product = ErpQuantityProduct::where([
                ['site_id', '=', $this->site_id], ['product_code', '=', $requestedCode],
            ])->lock(true)->findOrEmpty();
        }
        $now = time();
        $values = [
            'product_name' => $name,
            'spec' => mb_substr(trim((string)($request['spec'] ?? '')), 0, 120),
            'unit' => mb_substr(trim((string)($request['unit'] ?? '件')) ?: '件', 0, 20),
            'status' => 1,
            'update_at' => $now,
        ];
        if (!$product->isEmpty()) {
            $product->save($values);
            return $product;
        }
        return ErpQuantityProduct::create(array_merge($values, [
            'site_id' => (int)$this->site_id,
            'product_code' => $requestedCode !== ''
                ? $requestedCode
                : 'Q' . strtoupper(substr(hash('sha256', (string)$request['source_plugin'] . ':' . $sourceId), 0, 14)),
            'source_plugin' => (string)$request['source_plugin'],
            'source_id' => $sourceId,
            'remark' => '由外部业务自动关联',
            'create_at' => $now,
        ]));
    }

    private function productResult(ErpQuantityProduct $product, bool $created): array
    {
        $row = $product->toArray();
        $row['created'] = $created ? 1 : 0;
        $row['display_name'] = trim((string)$row['product_name'] . ' ' . (string)($row['spec'] ?? ''));
        return $row;
    }

    /** @return array{category_name:string,category_path:string} */
    private function resolveCategory(string $path): array
    {
        $normalized = preg_replace('~[\\\\／＞>,，|｜]+~u', '/', trim($path)) ?? trim($path);
        $segments = array_values(array_filter(array_map(
            static fn(string $value): string => trim(preg_replace('/\s+/u', ' ', $value) ?? $value),
            explode('/', $normalized)
        ), static fn(string $value): bool => $value !== ''));
        $categoryPath = mb_substr(implode('/', $segments), 0, 255);
        if ($categoryPath === '') throw new CommonException('请选择标品所属的末级分类');
        $exists = Db::name('erp_site_catalog_product')->where([
            ['site_id', '=', $this->site_id],
            ['category_path', '=', $categoryPath],
            ['is_enabled', '=', 1],
        ])->count();
        if ($exists <= 0) throw new CommonException('所选 ERP 分类不存在或已停用，请重新选择');
        return [
            'category_name' => mb_substr((string)end($segments), 0, 100),
            'category_path' => $categoryPath,
        ];
    }

    /** @return array{0:ErpWarehouse,1:ErpWarehouseLocation} */
    private function resolvePosition(int $warehouseId, int $locationId): array
    {
        $warehouseQuery = ErpWarehouse::where([['site_id', '=', $this->site_id], ['status', '=', 1]]);
        if ($warehouseId > 0) $warehouseQuery->where('id', '=', $warehouseId);
        else $warehouseQuery->order('is_default desc,sort asc,id asc');
        $warehouse = $warehouseQuery->findOrEmpty();
        if ($warehouse->isEmpty()) throw new CommonException('请先在 ERP 创建并启用耗材仓库');
        $locationQuery = ErpWarehouseLocation::where([
            ['site_id', '=', $this->site_id], ['warehouse_id', '=', (int)$warehouse->id], ['status', '=', 1],
        ]);
        if ($locationId > 0) $locationQuery->where('id', '=', $locationId);
        else $locationQuery->order('sort asc,id asc');
        $location = $locationQuery->findOrEmpty();
        if ($location->isEmpty()) throw new CommonException('所选 ERP 仓库没有可用库位');
        return [$warehouse, $location];
    }

    private function quantity(mixed $value): float
    {
        return round((float)$value, 3);
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
