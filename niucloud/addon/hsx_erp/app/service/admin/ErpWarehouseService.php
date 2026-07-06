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
        $warehouseType = $this->normalizeWarehouseType((string)($data['warehouse_type'] ?? 'owned'));
        $defaultSaleTarget = $this->normalizeDefaultSaleTarget((string)($data['default_sale_target'] ?? 'unset'));
        $ownershipType = $warehouseType === 'consignment' ? 'consigned' : 'owned';
        if ($warehouseType === 'exception') {
            $ownershipType = 'pending';
        }
        $warehouseId = 0;
        Db::transaction(function () use ($id, $name, $data, $now, $isDefault, $warehouseType, $ownershipType, $defaultSaleTarget, &$warehouseId) {
            if ($isDefault === 1) {
                ErpWarehouse::where([['site_id', '=', $this->site_id]])->update([
                    'is_default' => 0,
                    'update_at' => $now,
                ]);
            }
            $values = [
                'warehouse_name' => $name,
                'warehouse_code' => trim((string)($data['warehouse_code'] ?? '')),
                'warehouse_type' => $warehouseType,
                'ownership_type' => $ownershipType,
                'need_photo' => (int)($data['need_photo'] ?? 0) === 1 ? 1 : 0,
                'need_pricing' => (int)($data['need_pricing'] ?? 0) === 1 ? 1 : 0,
                'allow_direct_sale' => (int)($data['allow_direct_sale'] ?? 1) === 1 ? 1 : 0,
                'allow_transfer' => $warehouseType === 'consignment' ? 0 : ((int)($data['allow_transfer'] ?? 1) === 1 ? 1 : 0),
                'default_sale_target' => $defaultSaleTarget,
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
            `warehouse_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned二手机/peer同行/consignment代卖/exception异常',
            `ownership_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned自有/consigned代卖/pending待定',
            `need_photo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要拍照',
            `need_pricing` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要定价',
            `allow_direct_sale` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许直接销售',
            `allow_transfer` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许调拨',
            `default_sale_target` varchar(20) NOT NULL DEFAULT 'unset' COMMENT 'unset未定/peer同行/mall商城',
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
        $this->ensureColumn($warehouseTable, 'warehouse_type', "`warehouse_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned二手机/peer同行/consignment代卖/exception异常' AFTER `warehouse_code`");
        $this->ensureColumn($warehouseTable, 'ownership_type', "`ownership_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned自有/consigned代卖/pending待定' AFTER `warehouse_type`");
        $this->ensureColumn($warehouseTable, 'need_photo', "`need_photo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要拍照' AFTER `ownership_type`");
        $this->ensureColumn($warehouseTable, 'need_pricing', "`need_pricing` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要定价' AFTER `need_photo`");
        $this->ensureColumn($warehouseTable, 'allow_direct_sale', "`allow_direct_sale` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许直接销售' AFTER `need_pricing`");
        $this->ensureColumn($warehouseTable, 'allow_transfer', "`allow_transfer` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许调拨' AFTER `allow_direct_sale`");
        $this->ensureColumn($warehouseTable, 'default_sale_target', "`default_sale_target` varchar(20) NOT NULL DEFAULT 'unset' COMMENT 'unset未定/peer同行/mall商城' AFTER `allow_transfer`");
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

    private function normalizeWarehouseType(string $type): string
    {
        return in_array($type, ['owned', 'peer', 'consignment', 'exception'], true) ? $type : 'owned';
    }

    private function normalizeDefaultSaleTarget(string $target): string
    {
        return in_array($target, ['unset', 'peer', 'mall'], true) ? $target : 'unset';
    }
}
