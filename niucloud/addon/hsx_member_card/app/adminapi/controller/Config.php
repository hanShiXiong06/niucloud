<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\service\admin\MemberCardConfigAdminService;
use core\base\BaseAdminController;
final class Config extends BaseAdminController
{
    public function info() { return success((new MemberCardConfigAdminService())->info()); }
    public function save() { return success((new MemberCardConfigAdminService())->save($this->request->params([['allow_receivable', 1], ['allow_unpaid_redemption', 1], ['default_capital_account_id', 0], ['finance_retry_limit', 8], ['inventory_mode', 'none'], ['inventory_warehouse_id', 0], ['inventory_location_id', 0]]))); }
    public function saveCapitalAccount() { return success((new MemberCardConfigAdminService())->saveCapitalAccount($this->request->params([['id', 0], ['name', ''], ['type', 'other'], ['status', 1], ['is_default', 0], ['sort', 0]]))); }
    public function deleteCapitalAccount(int $id) { return success((new MemberCardConfigAdminService())->deleteCapitalAccount($id)); }
    public function dicts() { return success(MemberCardDict::lists()); }
}
