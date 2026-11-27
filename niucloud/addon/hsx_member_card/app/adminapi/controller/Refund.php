<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardRefundService;
use core\base\BaseAdminController;
final class Refund extends BaseAdminController
{
    public function apply(int $id) { return success((new MemberCardRefundService())->apply($id, $this->request->params([['request_id', ''], ['refund_mode', 'finance'], ['capital_account_id', 0], ['voucher_urls', []], ['reason', '']]))); }
    public function lists() { return success((new MemberCardRefundService())->lists($this->request->params([['keyword', ''], ['status', ''], ['page', 1], ['limit', 15]]))); }
    public function info(int $id) { return success((new MemberCardRefundService())->info($id)); }
    public function retryFinance(int $id) { return success((new MemberCardRefundService())->retryFinance($id, $this->request->params([['capital_account_id', 0], ['voucher_urls', []]]))); }
}
