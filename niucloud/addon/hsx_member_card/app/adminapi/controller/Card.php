<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardRedemptionService;
use core\base\BaseAdminController;
final class Card extends BaseAdminController
{
    public function search() { return success((new MemberCardRedemptionService())->search($this->request->params([['mobile_keyword', ''], ['name', '']]))); }
    public function info(int $id) { return success((new MemberCardRedemptionService())->cardInfo($id)); }
    public function redeem(int $id) { return success((new MemberCardRedemptionService())->redeem($id, $this->request->params([['request_id', ''], ['card_item_id', 0], ['verification_confirmed', 0], ['remark', '']]))); }
    public function freeze(int $id) { return success((new MemberCardRedemptionService())->freeze($id, (string)$this->request->param('reason', ''))); }
    public function unfreeze(int $id) { return success((new MemberCardRedemptionService())->unfreeze($id)); }
}
