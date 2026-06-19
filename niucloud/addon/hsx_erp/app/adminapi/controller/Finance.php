<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpCapitalAccountService;
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

    /** 按设备对账（导出用）：一台机器一行，回收→销售全链路 + 结算方式/折账原因 */
    public function reconciliation()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['settle_method', ''], ['pay_state', ''], ['recv_state', ''], ['keyword', ''],
            ['start_time', 0], ['end_time', 0],
        ]);
        return success((new \addon\hsx_erp\app\service\admin\FinanceReconciliationService())->deviceRows($where));
    }

    /** 应付列表 */
    public function payableLists()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['status', ''], ['settle_state', ''], ['source_type', ''], ['keyword', ''], ['operator', ''],
            ['start_time', 0], ['end_time', 0], ['amount_min', ''], ['amount_max', ''],
            ['sort_field', 'occurred_at'], ['sort_order', 'desc'],
            ['page', 1], ['limit', 15],
        ]);
        return success((new FinancePayableService())->getPage($where));
    }

    /** 应收列表 */
    public function receivableLists()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['status', ''], ['settle_state', ''], ['source_type', ''], ['keyword', ''], ['operator', ''],
            ['start_time', 0], ['end_time', 0], ['amount_min', ''], ['amount_max', ''],
            ['sort_field', 'occurred_at'], ['sort_order', 'desc'],
            ['page', 1], ['limit', 15],
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
            ['counterparty_id', 0], ['member_ids', []], ['payable_ids', []], ['receivable_ids', []],
        ]);
        $memberIds = array_values(array_filter(array_map('intval', (array)$p['member_ids'])));
        $scope = !empty($memberIds) ? $memberIds : (int)$p['counterparty_id'];
        return success((new FinanceSettlementService())->preview(
            $scope, (array)$p['payable_ids'], (array)$p['receivable_ids']
        ));
    }

    /** 结算单核销明细(哪笔应付折哪笔应收、各折账/现金多少、关联到人和设备) */
    public function settlementDetail(int $id)
    {
        return success((new FinanceSettlementService())->getDetail($id));
    }

    /** 一组对接人(主体)的未结应付/应收, 供主体级折账勾选 */
    public function groupOutstanding()
    {
        $memberIds = array_values(array_filter(array_map('intval', (array)$this->request->param('member_ids', []))));
        return success((new FinanceSettlementService())->outstandingByMembers($memberIds));
    }

    /** 确认结算(现金/折账/混合自动判定; 传 member_ids 则主体级跨人折账) */
    public function settlementSettle()
    {
        $p = $this->request->params([
            ['counterparty_id', 0], ['member_ids', []], ['entity_id', 0], ['entity_name', ''],
            ['payable_ids', []], ['receivable_ids', []], ['remark', ''], ['capital_account_id', 0],
        ]);
        $memberIds = array_values(array_filter(array_map('intval', (array)$p['member_ids'])));
        $options = ['remark' => (string)$p['remark'], 'capital_account_id' => (int)$p['capital_account_id']];
        if (!empty($memberIds)) {
            $options['anchor_id'] = (int)$p['entity_id'];
            $options['anchor_name'] = (string)$p['entity_name'];
            $scope = $memberIds;
        } else {
            $scope = (int)$p['counterparty_id'];
        }
        return success((new FinanceSettlementService())->settle(
            $scope, (array)$p['payable_ids'], (array)$p['receivable_ids'], $options
        ));
    }

    /** 结算记录(已结清历史) */
    public function settlementLists()
    {
        $where = $this->request->params([
            ['counterparty_id', 0], ['keyword', ''], ['operator', ''], ['start_time', 0], ['end_time', 0], ['page', 1], ['limit', 15],
        ]);
        return success((new FinanceSettlementService())->getPage($where));
    }

    /** 财务汇总(应收/应付合计 + 净额 + 各资金账户余额) */
    public function summary()
    {
        return success((new FinanceCounterpartyBalanceService())->getSummary());
    }

    /** 经营支出快捷记账(水电/房租/快递等)：从指定资金账户出账，必关联账户 */
    public function recordExpense()
    {
        $p = $this->request->params([
            ['account_id', 0], ['amount', 0], ['category', ''],
            ['counterparty_id', 0], ['counterparty_name', ''], ['remark', ''],
        ]);
        $category = trim((string)$p['category']);
        $remark = trim((string)$p['remark']);
        $fullRemark = $category !== '' ? ('[' . $category . ']' . ($remark !== '' ? ' ' . $remark : '')) : $remark;
        return success((new ErpCapitalAccountService())->recordEntry([
            'account_id'        => (int)$p['account_id'],
            'direction'         => 'out',
            'amount'            => $p['amount'],
            'biz_type'          => 'expense',
            'counterparty_id'   => (int)$p['counterparty_id'],
            'counterparty_name' => (string)$p['counterparty_name'],
            'source_type'       => 'expense',
            'remark'            => $fullRemark,
        ]));
    }

    /** 某供应商可用采购预付余额(供入库建档抵扣展示) */
    public function prepayBalance()
    {
        $cpId = (int)$this->request->param('counterparty_id', 0);
        return success((new FinanceReceivableService())->prepayBalance($cpId));
    }

    /** 采购预付挂账:钱付了货没到——现金出账 + 生成采购预付应收(货到折账相抵) */
    public function prepay()
    {
        $p = $this->request->params([
            ['counterparty_id', 0], ['counterparty_name', ''], ['amount', 0], ['account_id', 0], ['remark', ''],
        ]);
        return success((new FinanceReceivableService())->prepay([
            'counterparty_id'   => (int)$p['counterparty_id'],
            'counterparty_name' => (string)$p['counterparty_name'],
            'amount'            => $p['amount'],
            'account_id'        => (int)$p['account_id'],
            'remark'            => (string)$p['remark'],
        ]));
    }
}
