<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpStocktakeService;
use core\base\BaseAdminController;
use think\App;

class ErpStocktake extends BaseAdminController
{
    protected ErpStocktakeService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpStocktakeService();
    }

    public function lists()
    {
        return success($this->service->getPage($this->request->params([
            ['keyword', ''], ['status', ''], ['workflow_mode', ''], ['warehouse_id', 0], ['page', 1], ['limit', 15],
        ])));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function items(int $id)
    {
        return success($this->service->itemPage($id, $this->request->params([
            ['keyword', ''], ['result', ''], ['resolution_status', ''], ['page', 1], ['limit', 30],
        ])));
    }

    public function create()
    {
        $id = $this->service->create($this->request->params([
            ['warehouse_id', 0], ['location_id', 0], ['scope_type', 'all'], ['asset_ids', []],
            ['workflow_mode', 'simple'], ['counter_uid', 0], ['reviewer_uid', 0], ['remark', ''], ['request_id', ''],
        ]));
        return success(['id' => $id]);
    }

    public function scan(int $id)
    {
        return success($this->service->scan($id, $this->request->params([
            ['code', ''], ['actual_location_id', 0],
        ])));
    }

    public function submit(int $id)
    {
        return success($this->service->submit($id, (bool)$this->request->param('auto_complete', false)));
    }

    public function resolve(int $id, int $item_id)
    {
        return success($this->service->resolveItem($id, $item_id, $this->request->params([
            ['action', ''], ['actual_warehouse_id', 0], ['actual_location_id', 0], ['remark', ''],
        ])));
    }

    public function complete(int $id)
    {
        return success($this->service->complete($id, (string)$this->request->param('remark', '')));
    }

    public function cancel(int $id)
    {
        return success($this->service->cancel($id, (string)$this->request->param('remark', '')));
    }
}
