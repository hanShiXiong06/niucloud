<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\adminapi\controller;

use addon\hsx_device_asset\app\service\admin\DeviceAssetAssignService;
use core\base\BaseAdminController;
use think\App;

/**
 * 设备资产中台 - 库位责任分配（管理员）
 */
class DeviceAssetAssign extends BaseAdminController
{
    protected DeviceAssetAssignService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new DeviceAssetAssignService();
    }

    /** ERP 仓库/库位树（可分配的库位来源） */
    public function warehouseTree()
    {
        return success($this->service->getWarehouseTree());
    }

    /** 可分配员工列表 */
    public function staffOptions()
    {
        return success($this->service->getStaffOptions());
    }

    /** 分配列表（可按 uid / location_id 过滤） */
    public function assignments()
    {
        $data = $this->request->params([
            ['uid', 0],
            ['location_id', 0],
        ]);
        return success($this->service->getAssignments($data));
    }

    /** 给某库位设置负责人（整组替换） */
    public function setLocationStaff()
    {
        $data = $this->request->params([
            ['warehouse_id', 0],
            ['location_id', 0],
            ['uids', []],
        ]);
        return success($this->service->setLocationStaff((int)$data['warehouse_id'], (int)$data['location_id'], (array)$data['uids']));
    }

    /** 给某员工设置负责库位（整组替换） */
    public function setStaffLocations()
    {
        $data = $this->request->params([
            ['uid', 0],
            ['locations', []],
        ]);
        return success($this->service->setStaffLocations((int)$data['uid'], (array)$data['locations']));
    }
}
