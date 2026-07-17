<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardOrderService;
use core\base\BaseAdminController;
final class Order extends BaseAdminController
{
    public function lists() { return success((new MemberCardOrderService())->lists($this->request->params([['keyword', ''], ['business_status', ''], ['finance_status', ''], ['settlement_mode', ''], ['member_id', 0], ['page', 1], ['limit', 15]]))); }
    public function info(int $id) { return success((new MemberCardOrderService())->info($id)); }
    public function create() { return success((new MemberCardOrderService())->create($this->request->params([['request_id', ''], ['member_id', 0], ['product_id', 0], ['settlement_mode', 'receivable'], ['capital_account_id', 0], ['voucher_urls', []], ['remark', '']]))); }
    public function retryFinance(int $id) { return success((new MemberCardOrderService())->retryFinance($id, $this->request->params([['capital_account_id', 0], ['voucher_urls', []]]))); }
    public function cancel(int $id) { return success((new MemberCardOrderService())->cancel($id, (string)$this->request->param('reason', ''))); }
}
