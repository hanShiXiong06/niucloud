<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\AddressService;
use core\base\BaseApiController;

class Address extends BaseApiController
{
    public function lists()
    {
        $service = new AddressService();
        $list = $service->getList();
        return success($list);
    }

    public function detail()
    {
        $id = $this->request->param('id', 0);
        $service = new AddressService();
        $data = $service->getDetail((int)$id);
        return success($data);
    }

    public function add()
    {
        $data = $this->request->params([
            ['name', ''],
            ['mobile', ''],
            ['address', ''],
            ['address_type', 'OTHER'],
            ['building', ''],
            ['room', ''],
            ['lng', ''],
            ['lat', ''],
            ['is_default', 0]
        ]);
        
        $service = new AddressService();
        $result = $service->add($data);
        return success($result);
    }

    public function edit()
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['mobile', ''],
            ['address', ''],
            ['address_type', 'OTHER'],
            ['building', ''],
            ['room', ''],
            ['lng', ''],
            ['lat', ''],
            ['is_default', 0]
        ]);
        
        $service = new AddressService();
        $result = $service->edit($id, $data);
        return success($result);
    }

    public function delete()
    {
        $id = $this->request->param('id', 0);
        
        $service = new AddressService();
        $result = $service->delete($id);
        return success($result);
    }
}
