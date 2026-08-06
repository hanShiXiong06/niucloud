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
            ['business_type', ''],
            ['party_id', 0],
            ['imei', ''],
            ['operator_id', 0],
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
            ['request_id', ''],
        ]);
        // 即使来自单张销售单，也按设备原仓位拆单，禁止前端指定统一回库位置。
        $ids = $this->service->createBatch($params);
        return success(['id' => (int)($ids[0] ?? 0), 'ids' => $ids]);
    }

    public function createAndConfirm()
    {
        $params = $this->request->params([
            ['sale_order_id', 0],
            ['refund_mode', 'payable'],
            ['capital_account_id', 0],
            ['return_to_warehouse_id', 0],
            ['return_to_location_id', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['items', []],
            ['request_id', ''],
        ]);
        $ids = $this->service->createAndConfirm($params);
        return success(['id' => (int)($ids[0] ?? 0), 'ids' => $ids]);
    }

    public function compensate()
    {
        $params=$this->request->params([['party_id',0],['refund_mode','payable'],['capital_account_id',0],['voucher_urls',''],['remark',''],['items',[]],['request_id','']]);
        return success(['id'=>$this->service->createCompensation($params)]);
    }

    public function confirm(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
            ['capital_account_id', 0],
            ['voucher_urls', ''],
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
