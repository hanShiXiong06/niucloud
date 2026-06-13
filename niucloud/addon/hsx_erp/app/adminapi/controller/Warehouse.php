<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpWarehouseService;
use core\base\BaseAdminController;
use think\App;

class Warehouse extends BaseAdminController
{
    protected ErpWarehouseService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpWarehouseService();
    }

    public function lists()
    {
        return success($this->service->getAll());
    }

    public function options()
    {
        return success($this->service->getOptions());
    }

    public function save(int $id = 0)
    {
        return success($this->service->saveWarehouse($this->request->params([
            ['warehouse_name', ''], ['warehouse_code', ''], ['business_type', 'mall'], ['status', 1],
            ['is_default', 0], ['sort', 0], ['remark', ''],
        ]), $id));
    }

    public function saveLocation(int $warehouse_id, int $id = 0)
    {
        return success($this->service->saveLocation($warehouse_id, $this->request->params([
            ['location_name', ''], ['location_code', ''], ['status', 1],
            ['sort', 0], ['remark', ''],
        ]), $id));
    }

    public function delete(int $id)
    {
        return success($this->service->deleteWarehouse($id));
    }

    public function deleteLocation(int $id)
    {
        return success($this->service->deleteLocation($id));
    }
}
