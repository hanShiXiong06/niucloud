<?php
namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\PackagePriceService;
use core\base\BaseAdminController;

class PackagePrice extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['name', ''],
            ['status', ''],
        ]);
        return success((new PackagePriceService())->getPage($data));
    }

    public function info(int $id)
    {
        return success((new PackagePriceService())->getInfo($id));
    }

    public function add()
    {
        $data = $this->request->params([
            ['size', ''],
            ['name', ''],
            ['description', ''],
            ['price', 0],
            ['sort', 0],
            ['status', 1],
        ]);
        return success((new PackagePriceService())->add($data));
    }

    public function edit(int $id)
    {
        $data = $this->request->params([
            ['size', ''],
            ['name', ''],
            ['description', ''],
            ['price', 0],
            ['sort', 0],
            ['status', 1],
        ]);
        return success((new PackagePriceService())->edit($id, $data));
    }

    public function del(int $id)
    {
        return success((new PackagePriceService())->del($id));
    }
}