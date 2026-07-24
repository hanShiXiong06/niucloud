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

    public function payableInfo(int $id)
    {
        return success($this->service->payableInfo($id));
    }

    public function dashboard()
    {
        $params = $this->request->params([
            ['period', 'month'],
            ['start_at', 0],
            ['end_at', 0],
        ]);
        return success($this->service->dashboard($params));
    }

    public function receivableLists()
    {
        return success($this->service->receivablePage($this->listParams()));
    }

    public function receivableInfo(int $id)
    {
        return success($this->service->receivableInfo($id));
    }

    public function receivableItems(int $id)
    {
        return success($this->service->receivableItems($id));
    }

    public function confirmPayment(int $id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['request_id', ''],
        ]);
        return success(['id' => $this->service->confirmPayment($id, (float)$params['amount'], $params)]);
    }

    public function confirmPartyPayment(int $party_id)
    {
        $params = $this->request->params([
            ['amount', 0],
            ['payable_id', 0],
            ['payable_ids', []],
            ['source_type', ''],
            ['source_id', 0],
            ['purchase_order_id', 0],
            ['batch_id', 0],
            ['batch_no', ''],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['request_id', ''],
        ]);
        return success(['ids' => $this->service->confirmPartyPayment($party_id, (float)$params['amount'], $params)]);
    }

    public function confirmPayableItemsPayment(int $party_id)
    {
        $params = $this->request->params([
            ['items', []],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['request_id', ''],
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
            ['items', []],
            ['capital_account_id', 0],
            ['confirmed_at', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['request_id', ''],
        ]);
        return success(['id' => $this->service->confirmReceipt($id, (float)$params['amount'], $params)]);
    }

    public function offset()
    {
        $params = $this->request->params([
            ['payable_ids', []],
            ['receivable_ids', []],
            ['amount', 0],
            ['settle_diff', false],
            ['capital_account_id', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['request_id', ''],
        ]);
        return success(['id' => $this->service->confirmOffset(
            (array)$params['payable_ids'],
            (array)$params['receivable_ids'],
            (float)$params['amount'],
            (string)$params['remark'],
            $params
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

    public function settlementLists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['party_id', 0],
            ['payable_id', 0],
            ['settlement_type', ''],
            ['capital_account_id', 0],
            ['asset_id', 0],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->settlementPage($params));
    }

    private function listParams(): array
    {
        return $this->request->params([
            ['keyword', ''],
            ['payable_id', 0],
            ['status', ''],
            ['source_type', ''],
            ['finance_type_key', ''],
            ['business_source_key', ''],
            ['source_plugin', ''],
            ['channel_code', ''],
            ['biz_scene', ''],
            ['party_id', 0],
            ['party_name', ''],
            ['purchase_order_id', 0],
            ['source_no', ''],
            ['imei', ''],
            ['m_no', ''],
            ['contact_mobile', ''],
            ['salesman_uid', 0],
            ['operator_uid', 0],
            ['salesman_name', ''],
            ['min_amount', ''],
            ['max_amount', ''],
            ['min_remain', ''],
            ['max_remain', ''],
            ['can_offset', ''],
            ['only_effective', ''],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
    }
}
