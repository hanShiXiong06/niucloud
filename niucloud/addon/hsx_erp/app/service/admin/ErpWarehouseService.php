<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpWarehouseService extends BaseAdminService
{
    private static bool $schemaEnsured = false;

    public function getAll(): array
    {
        $this->ensureSchema();
        $warehouses = ErpWarehouse::where([['site_id', '=', $this->site_id]])
            ->order('is_default desc,sort asc,id asc')
            ->select()
            ->toArray();
        $locations = ErpWarehouseLocation::where([['site_id', '=', $this->site_id]])
            ->order('sort asc,id asc')
            ->select()
            ->toArray();
        $locationMap = [];
        foreach ($locations as $location) {
            $locationMap[(int)$location['warehouse_id']][] = $location;
        }
        foreach ($warehouses as &$warehouse) {
            $warehouse['locations'] = $locationMap[(int)$warehouse['id']] ?? [];
        }
        unset($warehouse);
        return $warehouses;
    }

    public function ensureReady(): void
    {
        $this->ensureSchema();
    }

    public function getOptions(): array
    {
        return array_values(array_filter(array_map(function (array $warehouse) {
            if ((int)$warehouse['status'] !== 1) {
                return null;
            }
            $warehouse['locations'] = array_values(array_filter(
                $warehouse['locations'] ?? [],
                fn(array $location) => (int)$location['status'] === 1
            ));
            return $warehouse;
        }, $this->getAll())));
    }

    public function saveWarehouse(array $data, int $id = 0): int
    {
        $this->ensureSchema();
        $name = trim((string)($data['warehouse_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请填写仓库名称');
        }
        $duplicate = ErpWarehouse::where([
            ['site_id', '=', $this->site_id],
            ['warehouse_name', '=', $name],
            ['id', '<>', $id],
        ])->count();
        if ($duplicate > 0) {
            throw new CommonException('仓库名称已存在');
        }

        $now = time();
        $isDefault = (int)($data['is_default'] ?? 0) === 1 ? 1 : 0;
        $warehouseId = 0;
        Db::transaction(function () use ($id, $name, $data, $now, $isDefault, &$warehouseId) {
            if ($isDefault === 1) {
                ErpWarehouse::where([['site_id', '=', $this->site_id]])->update([
                    'is_default' => 0,
                    'update_at' => $now,
                ]);
            }
            $values = [
                'warehouse_name' => $name,
                'warehouse_code' => trim((string)($data['warehouse_code'] ?? '')),
                'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
                'is_default' => $isDefault,
                'sort' => (int)($data['sort'] ?? 0),
                'remark' => trim((string)($data['remark'] ?? '')),
                'update_at' => $now,
            ];
            if ($id > 0) {
                $warehouse = $this->findWarehouse($id);
                $warehouse->save($values);
                $warehouseId = $id;
                return;
            }
            $warehouse = ErpWarehouse::create(array_merge($values, [
                'site_id' => $this->site_id,
                'create_at' => $now,
            ]));
            $warehouseId = (int)$warehouse->id;
        });
        return $warehouseId;
    }

    public function saveLocation(int $warehouseId, array $data, int $id = 0): int
    {
        $this->ensureSchema();
        $this->findWarehouse($warehouseId);
        $name = trim((string)($data['location_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请填写库位名称');
        }
        $duplicate = ErpWarehouseLocation::where([
            ['site_id', '=', $this->site_id],
            ['warehouse_id', '=', $warehouseId],
            ['location_name', '=', $name],
            ['id', '<>', $id],
        ])->count();
        if ($duplicate > 0) {
            throw new CommonException('该仓库下库位名称已存在');
        }
        $now = time();
        $values = [
            'warehouse_id' => $warehouseId,
            'location_name' => $name,
            'location_code' => trim((string)($data['location_code'] ?? '')),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'remark' => trim((string)($data['remark'] ?? '')),
            'update_at' => $now,
        ];
        if ($id > 0) {
            $location = $this->findLocation($id);
            if ((int)$location->warehouse_id !== $warehouseId) {
                throw new CommonException('库位不属于当前仓库');
            }
            $location->save($values);
            return $id;
        }
        $location = ErpWarehouseLocation::create(array_merge($values, [
            'site_id' => $this->site_id,
            'create_at' => $now,
        ]));
        return (int)$location->id;
    }

    public function deleteWarehouse(int $id): bool
    {
        $this->ensureSchema();
        $this->findWarehouse($id);
        if (ErpAsset::where([['site_id', '=', $this->site_id], ['warehouse_id', '=', $id]])->count() > 0) {
            throw new CommonException('仓库已有库存，不能删除，可改为停用');
        }
        if (ErpWarehouseLocation::where([['site_id', '=', $this->site_id], ['warehouse_id', '=', $id]])->count() > 0) {
            throw new CommonException('请先删除仓库下的库位');
        }
        ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->delete();
        return true;
    }

    public function deleteLocation(int $id): bool
    {
        $this->ensureSchema();
        $this->findLocation($id);
        if (ErpAsset::where([['site_id', '=', $this->site_id], ['location_id', '=', $id]])->count() > 0) {
            throw new CommonException('库位已有库存，不能删除，可改为停用');
        }
        ErpWarehouseLocation::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->delete();
        return true;
    }

    public function validateInboundLocation(int $warehouseId, int $locationId): array
    {
        $this->ensureSchema();
        if ($warehouseId <= 0 || $locationId <= 0) {
            throw new CommonException('入库必须选择仓库和库位');
        }
        $warehouse = $this->findWarehouse($warehouseId);
        $location = $this->findLocation($locationId);
        if ((int)$warehouse->status !== 1 || (int)$location->status !== 1) {
            throw new CommonException('所选仓库或库位已停用');
        }
        if ((int)$location->warehouse_id !== $warehouseId) {
            throw new CommonException('所选库位不属于当前仓库');
        }
        return [$warehouse, $location];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;
        $warehouseTable = (new ErpWarehouse())->getTable();
        $locationTable = (new ErpWarehouseLocation())->getTable();
        $assetTable = (new ErpAsset())->getTable();
        Db::execute("CREATE TABLE IF NOT EXISTS `{$warehouseTable}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '仓库名称',
            `warehouse_code` varchar(60) NOT NULL DEFAULT '' COMMENT '仓库编码',
            `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用/0停用',
            `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '默认入库仓',
            `sort` int NOT NULL DEFAULT 0,
            `remark` varchar(255) NOT NULL DEFAULT '',
            `create_at` int NOT NULL DEFAULT 0,
            `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_site_name` (`site_id`,`warehouse_name`),
            KEY `idx_site_status` (`site_id`,`status`,`sort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-仓库'");
        Db::execute("CREATE TABLE IF NOT EXISTS `{$locationTable}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
            `warehouse_id` int NOT NULL DEFAULT 0 COMMENT '仓库ID',
            `location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '库位名称',
            `location_code` varchar(60) NOT NULL DEFAULT '' COMMENT '库位编码',
            `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用/0停用',
            `sort` int NOT NULL DEFAULT 0,
            `remark` varchar(255) NOT NULL DEFAULT '',
            `create_at` int NOT NULL DEFAULT 0,
            `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_warehouse_name` (`site_id`,`warehouse_id`,`location_name`),
            KEY `idx_site_warehouse` (`site_id`,`warehouse_id`,`status`,`sort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-仓库库位'");
        $this->ensureColumn($assetTable, 'location_id', "`location_id` int NOT NULL DEFAULT 0 COMMENT '库位ID' AFTER `warehouse_name`");
        $this->ensureColumn($assetTable, 'location_name', "`location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '库位名称快照' AFTER `location_id`");
        $purchaseTable = (new \addon\hsx_erp\app\model\ErpPurchaseOrder())->getTable();
        $this->ensureColumn($purchaseTable, 'location_id', "`location_id` int NOT NULL DEFAULT 0 AFTER `warehouse_name`");
        $this->ensureColumn($purchaseTable, 'location_name', "`location_name` varchar(100) NOT NULL DEFAULT '' AFTER `location_id`");
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!empty($rows)) {
            return;
        }
        Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }

    private function findWarehouse(int $id): ErpWarehouse
    {
        $model = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($model->isEmpty()) {
            throw new CommonException('仓库不存在');
        }
        return $model;
    }

    private function findLocation(int $id): ErpWarehouseLocation
    {
        $model = ErpWarehouseLocation::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($model->isEmpty()) {
            throw new CommonException('库位不存在');
        }
        return $model;
    }
}
