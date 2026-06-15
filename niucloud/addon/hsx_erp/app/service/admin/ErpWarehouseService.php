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
    public function getAll(): array
    {
        $warehouses = ErpWarehouse::where([['site_id', '=', $this->site_id]])
            ->order('is_default desc,sort asc,id asc')->select()->toArray();
        $locations = ErpWarehouseLocation::where([['site_id', '=', $this->site_id]])
            ->order('sort asc,id asc')->select()->toArray();
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

    public function getOptions(): array
    {
        return array_values(array_filter(array_map(function (array $warehouse) {
            if ((int)$warehouse['status'] !== 1) {
                return null;
            }
            $warehouse['locations'] = array_values(array_filter(
                $warehouse['locations'],
                fn(array $location) => (int)$location['status'] === 1
            ));
            return $warehouse;
        }, $this->getAll())));
    }

    public function saveWarehouse(array $data, int $id = 0): int
    {
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
        // 业务类型(=销售流向)：商城/同行/代卖/报废/暂存，缺省商城
        $allowedTypes = ['mall', 'peer', 'consignment', 'scrap', 'hold'];
        $businessType = in_array((string)($data['business_type'] ?? ''), $allowedTypes, true)
            ? (string)$data['business_type']
            : 'mall';
        // 允许调入：未显式传时，代卖仓默认不允许、其它仓默认允许
        $allowInbound = array_key_exists('allow_inbound', $data)
            ? ((int)$data['allow_inbound'] === 1 ? 1 : 0)
            : ($businessType === 'consignment' ? 0 : 1);
        $warehouseId = 0;
        Db::transaction(function () use ($id, $name, $data, $now, $isDefault, $businessType, $allowInbound, &$warehouseId) {
            if ($isDefault === 1) {
                ErpWarehouse::where([['site_id', '=', $this->site_id]])->update([
                    'is_default' => 0,
                    'update_at' => $now,
                ]);
            }
            $values = [
                'warehouse_name' => $name,
                'warehouse_code' => trim((string)($data['warehouse_code'] ?? '')),
                'business_type' => $businessType,
                'allow_inbound' => $allowInbound,
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
            } else {
                $warehouse = ErpWarehouse::create(array_merge($values, [
                    'site_id' => $this->site_id,
                    'create_at' => $now,
                ]));
                $warehouseId = (int)$warehouse->id;
            }
        });
        return $warehouseId;
    }

    public function saveLocation(int $warehouseId, array $data, int $id = 0): int
    {
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
        $this->findLocation($id);
        if (ErpAsset::where([['site_id', '=', $this->site_id], ['location_id', '=', $id]])->count() > 0) {
            throw new CommonException('库位已有库存，不能删除，可改为停用');
        }
        ErpWarehouseLocation::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->delete();
        return true;
    }

    public function validateInboundLocation(int $warehouseId, int $locationId): array
    {
        if ($warehouseId <= 0 || $locationId <= 0) {
            throw new CommonException('确认入库前必须选择仓库和库位');
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
