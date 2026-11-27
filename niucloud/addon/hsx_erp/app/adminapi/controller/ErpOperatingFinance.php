<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpOperatingFinanceService;
use core\base\BaseAdminController;
use think\App;

class ErpOperatingFinance extends BaseAdminController
{
    protected ErpOperatingFinanceService $service;
    public function __construct(App $app) { parent::__construct($app); $this->service = new ErpOperatingFinanceService(); }

    public function lists()
    {
        $params = $this->request->params([
            ['direction', ''], ['status', ''], ['category_key', ''], ['party_id', 0], ['keyword', ''],
            ['start_at', 0], ['end_at', 0], ['page', 1], ['limit', 15],
        ]);
        return success($this->service->lists($params));
    }

    public function create()
    {
        $params = $this->request->params([
            ['category_key', ''], ['amount', 0], ['party_id', 0], ['party_name', ''],
            ['settlement_mode', 'pending'], ['capital_account_id', 0], ['voucher_urls', ''],
            ['occurred_at', 0], ['remark', ''], ['request_id', ''],
        ]);
        return success($this->service->create($params));
    }
}
