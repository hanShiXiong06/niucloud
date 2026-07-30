<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpSaleService;
use core\base\BaseAdminController;
use think\App;

class ErpSale extends BaseAdminController
{
    protected ErpSaleService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpSaleService();
    }

    public function lists()
    {
        $params = $this->request->params([
            ['item_type', ''],
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
            ['sale_no', ''],
            ['sale_channel', ''],
            ['sale_channel_key', ''],
            ['channel_source_plugin', ''],
            ['channel_source_key', ''],
            ['origin_plugin', 'hsx_erp'],
            ['origin_plugin_name', ''],
            ['origin_type', ''],
            ['origin_name', ''],
            ['origin_id', ''],
            ['origin_no', ''],
            ['origin_event_id', ''],
            ['event_id', ''],
            ['warehouse_id', 0],
            ['warehouse_name', ''],
            ['location_id', 0],
            ['catalog_product_id', 0],
            ['salesman_uid', 0],
            ['salesman_name', ''],
            ['operator_uid', 0],
            ['operator_name', ''],
            ['min_amount', ''],
            ['max_amount', ''],
            ['min_profit', ''],
            ['max_profit', ''],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->getPage($params));
    }

    public function stock()
    {
        $params = $this->request->params([
            ['item_type', 'device'],
            ['keyword', ''],
            ['warehouse_id', 0],
            ['location_id', 0],
            ['catalog_product_id', 0],
            ['asset_no', ''],
            ['imei', ''],
            ['sn', ''],
            ['model', ''],
            ['spec', ''],
            ['party_name', ''],
            ['warehouse_name', ''],
            ['location_name', ''],
            ['asset_ids', []],
            ['stock_ids', []],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->stockPage($params));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function create()
    {
        $params = $this->request->params([
            ['request_id', ''],
            ['event_id', ''],
            ['party_name', ''],
            ['party_id', 0],
            ['sale_channel', ''],
            ['sale_channel_key', ''],
            ['channel_source_plugin', ''],
            ['channel_source_key', ''],
            ['origin_plugin', 'hsx_erp'],
            ['origin_plugin_name', ''],
            ['origin_type', ''],
            ['origin_name', ''],
            ['origin_id', ''],
            ['origin_no', ''],
            ['origin_event_id', ''],
            ['salesman_uid', 0],
            ['settle_method', ''],
            ['settle_mode', 'credit'],
            ['received_amount', 0],
            ['capital_account_id', 0],
            ['voucher_urls', ''],
            ['sale_at', 0],
            ['remark', ''],
            ['items', []],
        ]);
        return success(['id' => $this->service->create($params)]);
    }

    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancel($id, (string)$params['remark']));
    }

    public function cancelItem(int $item_id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancelItem($item_id, (string)$params['remark']));
    }
}
