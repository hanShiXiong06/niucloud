<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpFinanceService;
use core\base\BaseAdminController;
use think\App;

class ErpFinance extends BaseAdminController
{
    protected ErpFinanceService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpFinanceService();
    }

    public function payableLists()
    {
        return success($this->service->payablePage($this->listParams()));
    }

    public function receivableLists()
    {
        return success($this->service->receivablePage($this->listParams()));
    }

    public function confirmPayment(int $id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['remark', ''],
        ]);
        return success(['id' => $this->service->confirmPayment($id, (float)$params['amount'], $params)]);
    }

    public function confirmPartyPayment(int $party_id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['remark', ''],
        ]);
        return success(['ids' => $this->service->confirmPartyPayment($party_id, (float)$params['amount'], $params)]);
    }

    public function confirmPayableItemsPayment(int $party_id)
    {
        $params = $this->request->params([
            ['items', []],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['remark', ''],
        ]);
        return success(['id' => $this->service->confirmPayableItemsPayment(
            $party_id,
            (array)$params['items'],
            $params
        )]);
    }

    public function payablePartyItems(int $party_id)
    {
        return success($this->service->payablePartyItems($party_id, $this->listParams()));
    }

    public function confirmReceipt(int $id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['remark', ''],
        ]);
        return success(['id' => $this->service->confirmReceipt($id, (float)$params['amount'], $params)]);
    }

    public function offset()
    {
        $params = $this->request->params([
            ['payable_ids', []],
            ['receivable_ids', []],
            ['amount', 0],
            ['remark', ''],
        ]);
        return success(['id' => $this->service->confirmOffset(
            (array)$params['payable_ids'],
            (array)$params['receivable_ids'],
            (float)$params['amount'],
            (string)$params['remark']
        )]);
    }

    public function accountLedger()
    {
        return success($this->service->accountLedgerPage($this->listParams()));
    }

    public function moneyLedger()
    {
        return success($this->service->moneyLedgerPage($this->listParams()));
    }

    private function listParams(): array
    {
        return $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['party_id', 0],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
    }
}
