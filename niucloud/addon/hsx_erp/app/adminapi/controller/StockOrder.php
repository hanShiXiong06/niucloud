<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpInboundOrderService;
use core\base\BaseAdminController;
use think\App;

class StockOrder extends BaseAdminController
{
    protected ErpInboundOrderService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpInboundOrderService();
    }

    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 20],
        ]);
        return success($this->service->getPage($data));
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    public function confirmItems(int $id)
    {
        $data = $this->request->params([
            ['item_ids', []],
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmItems($id, (array)$data['item_ids'], $data));
    }

    public function rejectItems(int $id)
    {
        $data = $this->request->params([
            ['item_ids', []],
            ['reason', ''],
        ]);
        return success($this->service->rejectItems(
            $id,
            (array)$data['item_ids'],
            (string)$data['reason']
        ));
    }

    public function resubmitItem(int $id, int $item_id)
    {
        $data = $this->request->params([
            ['imei', ''],
            ['imei2', ''],
            ['sn', ''],
            ['model', ''],
            ['capacity', ''],
            ['color', ''],
            ['purchase_cost', 0],
        ]);
        return success($this->service->resubmitItem($id, $item_id, $data));
    }
}
