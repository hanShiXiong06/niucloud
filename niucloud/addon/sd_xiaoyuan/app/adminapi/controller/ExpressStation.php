<?php
namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\ExpressStationService;
use core\base\BaseAdminController;
use think\Response;

class ExpressStation extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['school_id', ''],
            ['name', ''],
            ['status', ''],
        ]);
        return success((new ExpressStationService())->getPage($data));
    }

    public function info(int $id)
    {
        return success((new ExpressStationService())->getInfo($id));
    }

    public function add()
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['name', ''],
            ['logo', ''],
            ['address', ''],
            ['express_company', ''],
            ['lng', ''],
            ['lat', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['business_hours', '08:00-20:00'],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, 'addon\sd_xiaoyuan\app\validate\ExpressStation.add');
        $id = (new ExpressStationService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    public function edit(int $id)
    {
        $data = $this->request->params([
            ['school_id', 0],
            ['name', ''],
            ['logo', ''],
            ['address', ''],
            ['express_company', ''],
            ['lng', ''],
            ['lat', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['business_hours', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, 'addon\sd_xiaoyuan\app\validate\ExpressStation.edit');
        (new ExpressStationService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    public function del(int $id)
    {
        (new ExpressStationService())->del($id);
        return success('DELETE_SUCCESS');
    }

    public function setStatus(int $id)
    {
        $data = $this->request->params([
            ['status', 0],
        ]);
        (new ExpressStationService())->setStatus($id, $data['status']);
        return success('MODIFY_SUCCESS');
    }

    public function all()
    {
        $data = $this->request->params([
            ['school_id', ''],
        ]);
        $where = [];
        if ($data['school_id']) {
            $where[] = ['school_id', '=', $data['school_id']];
        }
        return success((new ExpressStationService())->getAll($where));
    }
}
