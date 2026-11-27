<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardRedemptionService;
use core\base\BaseAdminController;
final class Redemption extends BaseAdminController
{
    public function lists() { return success((new MemberCardRedemptionService())->redemptionLists($this->request->params([['keyword', ''], ['status', ''], ['page', 1], ['limit', 15]]))); }
    public function info(int $id) { return success((new MemberCardRedemptionService())->redemptionInfo($id)); }
    public function reverse(int $id) { return success((new MemberCardRedemptionService())->reverse($id, $this->request->params([['request_id', ''], ['reason', '']]))); }
}
