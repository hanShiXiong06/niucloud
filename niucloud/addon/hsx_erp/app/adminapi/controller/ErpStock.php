<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpStockService;
use core\base\BaseAdminController;
use think\App;

class ErpStock extends BaseAdminController
{
    protected ErpStockService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpStockService();
    }

    public function lists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['refurbish_status', ''],
            ['sale_target', ''],
            ['listing_status', ''],
            ['warehouse_id', 0],
            ['warehouse_name', ''],
            ['location_id', 0],
            ['location_name', ''],
            ['catalog_product_id', 0],
            ['category_name', ''],
            ['asset_no', ''],
            ['imei', ''],
            ['sn', ''],
            ['model', ''],
            ['spec', ''],
            ['party_id', 0],
            ['party_name', ''],
            ['min_cost', ''],
            ['max_cost', ''],
            ['min_price', ''],
            ['max_price', ''],
            ['stock_age_min', ''],
            ['stock_age_max', ''],
            ['turnover_level', ''],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->getPage($params));
    }

    public function turnoverSummary()
    {
        return success($this->service->turnoverSummary());
    }

    public function adjustRetailPrice(int $id)
    {
        $params = $this->request->params([
            ['retail_price', 0], ['reason', ''], ['request_id', ''],
        ]);
        return success($this->service->adjustRetailPrice(
            $id,
            (float)$params['retail_price'],
            (string)$params['reason'],
            (string)$params['request_id']
        ));
    }

    public function transfer()
    {
        $params = $this->request->params([
            ['asset_ids', []], ['warehouse_id', 0], ['location_id', 0], ['reason', ''], ['request_id', ''],
        ]);
        return success($this->service->transfer(
            (array)$params['asset_ids'],
            (int)$params['warehouse_id'],
            (int)$params['location_id'],
            (string)$params['reason'],
            (string)$params['request_id']
        ));
    }

    public function serialTrace()
    {
        $params = $this->request->params([
            ['keyword', ''], ['page', 1], ['limit', 15],
        ]);
        return success($this->service->serialTracePage($params));
    }

    public function serialTraceDetail(int $id)
    {
        return success($this->service->serialTraceDetail($id));
    }

    public function ledger()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['asset_id', 0],
            ['action', ''],
            ['source_type', ''],
            ['start_time', 0],
            ['end_time', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->ledgerPage($params));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function adjustCost(int $id)
    {
        $params = $this->request->params([
            ['cost', 0],
            ['reason', ''],
            ['sync_payable', 1],
            ['cost_type', 'internal_adjust'],
            ['expense_type_key', ''],
            ['party_id', 0],
            ['party_name', ''],
            ['refurbish_items', []],
            ['request_id', ''],
        ]);
        return success($this->service->adjustCost(
            $id,
            (float)$params['cost'],
            (string)$params['reason'],
            (bool)$params['sync_payable'],
            (string)$params['request_id'],
            (string)$params['cost_type'],
            ['expense_type_key' => (string)$params['expense_type_key'], 'party_id' => (int)$params['party_id'], 'party_name' => (string)$params['party_name'], 'refurbish_items' => (array)$params['refurbish_items']]
        ));
    }

    public function sendRefurbish()
    {
        $params = $this->request->params([
            ['asset_ids', []], ['provider_party_id', 0], ['remark', ''], ['tracking_mode', ''], ['request_id', ''],
        ]);
        return success($this->service->sendRefurbish(
            (array)$params['asset_ids'], (int)$params['provider_party_id'], (string)$params['remark'], (string)$params['tracking_mode'], (string)$params['request_id']
        ));
    }

    public function completeRefurbish(int $id)
    {
        $params = $this->request->params([
            ['result', ''], ['refurbish_items', []], ['expense_type_key', 'refurbish_mixed'],
            ['warehouse_id', 0], ['location_id', 0], ['voucher_urls', []], ['remark', ''], ['request_id', ''],
        ]);
        return success($this->service->completeRefurbish($id, $params));
    }

    public function flow(int $id)
    {
        $params = $this->request->params([
            ['refurbish_status', ''],
            ['sale_target', ''],
            ['listing_status', ''],
            ['estimate_sale_price', null],
            ['retail_price', null],
            ['image_urls', null],
            ['catalog_product_id', null],
            ['category_name', null],
            ['category_path', null],
            ['spec', null],
            ['quality_remark', null],
            ['remark_public', null],
            ['remark_internal', null],
            ['remark', ''],
        ]);
        $this->service->updateFlow($id, $params);
        return success('SUCCESS');
    }

    public function syncListing(int $id)
    {
        return success($this->service->syncListing($id));
    }
}
