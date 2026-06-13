<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\FinanceCounterpartyBalanceService;
use addon\hsx_erp\app\service\admin\FinancePayableService;
use addon\hsx_erp\app\service\admin\FinanceReceivableService;
use addon\hsx_erp\app\service\admin\FinanceSettlementService;
use core\base\BaseAdminController;
use think\App;

/**
 * 财务中心(ERP 内置): 往来单位应付/应收 + 结算/折账。
 * 小团队场景, 不单独拆财务插件; 折账依赖回收(应付)与销售(应收)产生的事实。
 */
class Finance extends BaseAdminController
{
    /** 往来单位余额看板(应付/应收/可折账/净额) */
    public function board()
    {
        return success((new FinanceCounterpartyBalanceService())->getBoard());
    }

    /** 查单个往来单位往来账(谁欠谁多少, 是否可折账) — 与 GetFinanceCounterpartyBalance 事件同口径 */
    public function counterpartyBalance()
    {
        $cpId = (int)$this->request->param('counterparty_id', 0);
        return success((new FinanceCounterpartyBalanceService())->getCounterpartyBalance($cpId));
    }

    /** 应付列表 */
    public function payableLists()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['status', ''], ['keyword', ''], ['page', 1], ['limit', 15],
        ]);
        return success((new FinancePayableService())->getPage($where));
    }

    /** 应收列表 */
    public function receivableLists()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['status', ''], ['keyword', ''], ['page', 1], ['limit', 15],
        ]);
        return success((new FinanceReceivableService())->getPage($where));
    }

    /** 某往来单位待结算应付(结算选择用) */
    public function payableOutstanding()
    {
        $cpId = (int)$this->request->param('counterparty_id', 0);
        return success((new FinancePayableService())->getOutstandingByCounterparty($cpId));
    }

    /** 某往来单位待结算应收 */
    public function receivableOutstanding()
    {
        $cpId = (int)$this->request->param('counterparty_id', 0);
        return success((new FinanceReceivableService())->getOutstandingByCounterparty($cpId));
    }

    /** 结算预演(只算折账/现金, 不落库) */
    public function settlementPreview()
    {
        $p = $this->request->params([
            ['counterparty_id', 0], ['payable_ids', []], ['receivable_ids', []],
        ]);
        return success((new FinanceSettlementService())->preview(
            (int)$p['counterparty_id'], (array)$p['payable_ids'], (array)$p['receivable_ids']
        ));
    }

    /** 确认结算(现金/折账/混合自动判定) */
    public function settlementSettle()
    {
        $p = $this->request->params([
            ['counterparty_id', 0], ['payable_ids', []], ['receivable_ids', []], ['remark', ''],
        ]);
        return success((new FinanceSettlementService())->settle(
            (int)$p['counterparty_id'], (array)$p['payable_ids'], (array)$p['receivable_ids'],
            ['remark' => (string)$p['remark']]
        ));
    }
}
