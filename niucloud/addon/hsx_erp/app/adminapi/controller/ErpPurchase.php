<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use core\base\BaseAdminController;
use think\App;

class ErpPurchase extends BaseAdminController
{
    protected ErpPurchaseService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpPurchaseService();
    }

    public function lists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['finance_status', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->getPage($params));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function create()
    {
        $params = $this->request->params([
            ['party_name', ''],
            ['party_id', 0],
            ['m_no', ''],
            ['purchase_channel', ''],
            ['purchaser_uid', 0],
            ['settle_method', ''],
            ['settle_mode', 'credit'],
            ['paid_amount', 0],
            ['capital_account_id', 0],
            ['warehouse_id', 0],
            ['warehouse_name', ''],
            ['location_id', 0],
            ['location_name', ''],
            ['purchase_at', 0],
            ['remark', ''],
            ['source_plugin', 'erp'],
            ['source_type', 'manual'],
            ['source_id', ''],
            ['items', []],
        ]);
        return success(['id' => $this->service->create($params)]);
    }

    public function adjustCost(int $item_id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['remark', ''],
        ]);
        return success($this->service->adjustCost($item_id, (float)$params['amount'], (string)$params['remark']));
    }

    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancel($id, (string)$params['remark']));
    }
}
