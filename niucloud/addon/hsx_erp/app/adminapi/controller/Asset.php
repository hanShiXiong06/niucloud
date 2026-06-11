<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpAssetService;
use addon\hsx_erp\app\service\admin\ErpStandaloneInboundService;
use core\base\BaseAdminController;
use think\App;

class Asset extends BaseAdminController
{
    protected ErpAssetService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpAssetService();
    }

    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['inventory_status', ''],
            ['page', 1],
            ['limit', 20],
        ]);
        return success($this->service->getPage($data));
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    public function confirmInbound(int $id)
    {
        $data = $this->request->params([
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmInbound($id, $data));
    }

    public function confirmAssetInbound(int $id)
    {
        $data = $this->request->params([
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmInboundByAsset($id, $data));
    }

    public function batchConfirmInbound()
    {
        $data = $this->request->params([
            ['asset_ids', []],
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmAssetsInbound((array)$data['asset_ids'], $data));
    }

    public function manualInbound()
    {
        $data = $this->request->params([
            ['imei', ''],
            ['imei2', ''],
            ['sn', ''],
            ['model', ''],
            ['category_id', 0],
            ['capacity', ''],
            ['color', ''],
            ['business_type', 'recycle'],
            ['counterparty_id', 0],
            ['purchase_cost', 0],
            ['paid_amount', 0],
            ['suggested_sale_price', 0],
            ['remark', ''],
        ]);
        return success((new ErpStandaloneInboundService())->create($data));
    }
}
