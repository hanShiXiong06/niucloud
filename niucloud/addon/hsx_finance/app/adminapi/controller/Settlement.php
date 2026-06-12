<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\adminapi\controller;

use addon\hsx_finance\app\service\admin\FinanceSettlementService;
use core\base\BaseAdminController;
use think\App;

class Settlement extends BaseAdminController
{
    protected FinanceSettlementService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new FinanceSettlementService();
    }

    /** 预演: 只算折账/现金, 不落库(确认弹窗用) */
    public function preview()
    {
        $p = $this->request->params([
            ['counterparty_id', 0], ['payable_ids', []], ['receivable_ids', []],
        ]);
        return success($this->service->preview(
            (int)$p['counterparty_id'],
            (array)$p['payable_ids'],
            (array)$p['receivable_ids']
        ));
    }

    /** 执行结算(现金/折账/混合自动判定) */
    public function settle()
    {
        $p = $this->request->params([
            ['counterparty_id', 0], ['payable_ids', []], ['receivable_ids', []], ['remark', ''],
        ]);
        return success($this->service->settle(
            (int)$p['counterparty_id'],
            (array)$p['payable_ids'],
            (array)$p['receivable_ids'],
            ['remark' => (string)$p['remark']]
        ));
    }
}
