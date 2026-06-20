<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpQuickTradeService;
use core\base\BaseAdminController;
use think\App;

/**
 * 快速出入库（一单成账）
 */
class QuickTrade extends BaseAdminController
{
    protected ErpQuickTradeService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpQuickTradeService();
    }

    public function create()
    {
        $p = $this->request->params([
            ['supplier_id', 0],          // 供货人 对接人 counterparty_id
            ['supplier_member_id', 0],   // 供货人 member_id
            ['buyer_id', 0],             // 买家 member_id
            ['remark', ''],
            ['items', []],               // [{model, imei, sn, remark, purchase_cost, sale_price}]
        ]);
        return success('过账完成', $this->service->create($p));
    }
}
