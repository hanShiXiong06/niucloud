<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\api\controller\recycle_order;

use addon\hsx_recycle\app\service\api\recycle_order\RecycleConsignmentOrderService;
use core\base\BaseApiController;
use think\App;

/**
 * 用户端代卖订单
 */
class RecycleConsignmentOrder extends BaseApiController
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
            ['page', 1],
            ['limit', 10],
        ]);

        return success($this->service->getPage($data));
    }

    public function detail(int $id)
    {
        return success($this->service->getInfo($id));
    }

    public function status()
    {
        return success($this->service->getStatusOptions());
    }

    public function statusCount()
    {
        return success($this->service->getStatusCount());
    }
}
