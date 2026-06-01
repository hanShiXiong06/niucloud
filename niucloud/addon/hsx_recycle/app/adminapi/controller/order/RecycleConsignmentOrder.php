<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\service\admin\order\RecycleConsignmentOrderService;
use core\base\BaseAdminController;
use think\App;

/**
 * 代卖订单控制器
 */
class RecycleConsignmentOrder extends BaseAdminController
{
    protected RecycleConsignmentOrderService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleConsignmentOrderService();
    }

    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['source_order_id', ''],
            ['create_time', []],
            ['start_time', ''],
            ['end_time', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        return success($this->service->getPage($data));
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    public function logs(int $id)
    {
        return success($this->service->getLogs($id));
    }

    public function status()
    {
        return success(RecycleConsignmentDict::getStatusOptions());
    }

    public function transferDevice(int $id)
    {
        $data = $this->request->params([
            ['expected_price', 0],
            ['min_settlement_price', 0],
            ['listing_price', 0],
            ['remark', ''],
        ]);
        return success($this->service->transferFromDevice($id, $data));
    }

    public function listing(int $id)
    {
        $data = $this->request->params([
            ['listing_price', 0],
            ['remark', ''],
        ]);
        return success($this->service->updateListing($id, $data));
    }

    public function sold(int $id)
    {
        $data = $this->request->params([
            ['sold_price', 0],
            ['settlement_amount', 0],
            ['remark', ''],
        ]);
        return success($this->service->markSold($id, $data));
    }

    public function settle(int $id)
    {
        $data = $this->request->params([
            ['settlement_amount', 0],
            ['remark', ''],
        ]);
        return success($this->service->settle($id, $data));
    }

    public function close(int $id)
    {
        $data = $this->request->params([
            ['status', RecycleConsignmentDict::STATUS_CANCELLED],
            ['remark', ''],
        ]);
        return success($this->service->close($id, (int)$data['status'], trim((string)$data['remark'])));
    }

    public function pushNotify(int $id)
    {
        return success($this->service->pushNotify($id));
    }
}
