<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\adminapi\controller;

use addon\hsx_finance\app\service\admin\FinanceCapabilityService;
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

    /** 能力检测(回收/ERP 是否在场, 是否可折账) — 前端据此决定是否显示折账按钮 */
    public function capability()
    {
        return success((new FinanceCapabilityService())->getCapability());
    }

    /** 往来单位余额看板(应付/应收/可折账/净额) */
    public function board()
    {
        return success($this->service->getBoard());
    }
}
