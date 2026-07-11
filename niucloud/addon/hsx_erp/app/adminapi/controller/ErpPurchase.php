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
            ['asset_no', ''],
            ['imei', ''],
            ['sn', ''],
            ['model', ''],
            ['spec', ''],
            ['party_id', 0],
            ['party_name', ''],
            ['purchase_no', ''],
            ['m_no', ''],
            ['warehouse_id', 0],
            ['warehouse_name', ''],
            ['location_id', 0],
            ['category_id', 0],
            ['purchaser_uid', 0],
            ['purchaser_name', ''],
            ['min_amount', ''],
            ['max_amount', ''],
            ['start_at', 0],
            ['end_at', 0],
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
            ['purchase_channel_key', ''],
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
            ['origin_plugin', ''],
            ['origin_plugin_name', ''],
            ['origin_type', ''],
            ['origin_name', ''],
            ['origin_id', ''],
            ['origin_no', ''],
            ['origin_event_id', ''],
            ['event_id', ''],
            ['items', []],
            ['request_id', ''],
        ]);
        return success(['id' => $this->service->create($params)]);
    }

    public function adjustCost(int $item_id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['remark', ''],
            ['request_id', ''],
        ]);
        return success($this->service->adjustCost($item_id, (float)$params['amount'], (string)$params['remark'], true, (string)$params['request_id']));
    }

    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancel($id, (string)$params['remark']));
    }
}
