<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpPricingService;
use core\base\BaseAdminController;
use think\App;

class Pricing extends BaseAdminController
{
    protected ErpPricingService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpPricingService();
    }

    public function lists()
    {
        return success($this->service->getPage($this->request->params([
            ['keyword', ''], ['inventory_status', ''], ['asset_id', 0], ['page', 1], ['limit', 20],
        ])));
    }

    public function info(int $asset_id)
    {
        return success($this->service->getInfo($asset_id));
    }

    public function price(int $asset_id)
    {
        return success($this->service->price($asset_id, $this->request->params([
            ['sale_price', 0], ['min_profit', 0], ['remark', ''],
        ])));
    }
}
