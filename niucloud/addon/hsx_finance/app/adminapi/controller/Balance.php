<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\adminapi\controller;

use addon\hsx_finance\app\service\admin\FinanceCounterpartyBalanceService;
use core\base\BaseAdminController;
use think\App;

class Balance extends BaseAdminController
{
    protected FinanceCounterpartyBalanceService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new FinanceCounterpartyBalanceService();
    }

    /** 往来单位余额看板(应付/应收/可折账/净额) */
    public function board()
    {
        return success($this->service->getBoard());
    }
}
