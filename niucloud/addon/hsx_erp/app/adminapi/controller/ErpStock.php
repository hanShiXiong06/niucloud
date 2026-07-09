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
            ['category_id', 0],
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
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->getPage($params));
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
        ]);
        return success($this->service->adjustCost($id, (float)$params['cost'], (string)$params['reason'], (bool)$params['sync_payable']));
    }

    public function flow(int $id)
    {
        $params = $this->request->params([
            ['refurbish_status', ''],
            ['sale_target', ''],
            ['listing_status', ''],
            ['estimate_sale_price', null],
            ['image_urls', null],
            ['quality_remark', null],
            ['remark', ''],
        ]);
        $this->service->updateFlow($id, $params);
        return success('SUCCESS');
    }
}
