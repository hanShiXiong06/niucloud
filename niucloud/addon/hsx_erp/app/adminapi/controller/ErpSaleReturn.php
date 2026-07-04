<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpSaleReturnService;
use core\base\BaseAdminController;
use think\App;

class ErpSaleReturn extends BaseAdminController
{
    protected ErpSaleReturnService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpSaleReturnService();
    }

    public function lists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['party_id', 0],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->lists($params));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function create()
    {
        $params = $this->request->params([
            ['sale_order_id', 0],
            ['refund_mode', 'cash'],
            ['capital_account_id', 0],
            ['return_to_warehouse_id', 0],
            ['return_to_location_id', 0],
            ['remark', ''],
            ['items', []],
        ]);
        return success(['id' => $this->service->create($params)]);
    }

    public function confirm(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->confirm($id, $params));
    }

    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancel($id, (string)$params['remark']));
    }
}
