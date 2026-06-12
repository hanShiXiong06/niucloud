<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\adminapi\controller;

use addon\hsx_finance\app\service\admin\FinancePayableService;
use core\base\BaseAdminController;
use think\App;

class Payable extends BaseAdminController
{
    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new FinancePayableService();
    }

    public function lists()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['status', ''], ['keyword', ''],
            ['page', 1], ['limit', 15],
        ]);
        return success($this->service->getPage($where));
    }

    /** 某往来单位的待结算明细(供结算选择) */
    public function outstanding()
    {
        $cpId = (int)$this->request->param('counterparty_id', 0);
        return success($this->service->getOutstandingByCounterparty($cpId));
    }
}
