<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpLocationAssignService;
use core\base\BaseAdminController;
use think\App;

class LocationAssign extends BaseAdminController
{
    protected ErpLocationAssignService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpLocationAssignService();
    }

    /** 仓库/库位树 */
    public function tree()
    {
        return success($this->service->getWarehouseTree());
    }

    /** 可分配员工 */
    public function staffOptions()
    {
        return success($this->service->getStaffOptions());
    }

    /** 分配列表（可按 uid / location_id 过滤） */
    public function lists()
    {
        return success($this->service->getAssignments($this->request->params([
            ['uid', 0], ['location_id', 0],
        ])));
    }

    /** 设置某库位的负责人组 */
    public function setLocationStaff(int $location_id)
    {
        $params = $this->request->params([['warehouse_id', 0], ['uids', []]]);
        return success($this->service->setLocationStaff(
            (int)$params['warehouse_id'],
            $location_id,
            is_array($params['uids']) ? $params['uids'] : []
        ));
    }

    /** 设置某员工负责的库位组 */
    public function setStaffLocations(int $uid)
    {
        $params = $this->request->params([['locations', []]]);
        return success($this->service->setStaffLocations(
            $uid,
            is_array($params['locations']) ? $params['locations'] : []
        ));
    }
}
