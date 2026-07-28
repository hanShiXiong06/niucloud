<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpQuantityProduct;
use addon\hsx_erp\app\model\ErpQuantityStock;
use addon\hsx_erp\app\model\ErpQuantityStockFlow;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use core\exception\CommonException;

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
            $now = time();
            if ($stock->isEmpty()) {
                $stock = ErpQuantityStock::create([
                    'site_id' => (int)$this->site_id,
                    'product_id' => (int)$product->id,
                    'warehouse_id' => (int)$warehouse->id,
                    'location_id' => (int)($location->id ?? 0),
                    'quantity' => $after,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            } else {
                $stock->save(['quantity' => $after, 'update_at' => $now]);
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
            $now = time();
            if ($stock->isEmpty()) {
                $stock = ErpQuantityStock::create([
                    'site_id' => (int)$this->site_id,
                    'product_id' => (int)$product->id,
                    'warehouse_id' => (int)$warehouse->id,
                    'location_id' => (int)($location->id ?? 0),
                    'quantity' => $after,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            } else {
                $stock->save(['quantity' => $after, 'update_at' => $now]);
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
