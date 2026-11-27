<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\adminapi\controller;
use addon\hsx_member_card\app\service\admin\MemberCardMemberService;
use core\base\BaseAdminController;
final class Member extends BaseAdminController
{
    public function lists() { return success((new MemberCardMemberService())->lists($this->request->params([['keyword', ''], ['page', 1], ['limit', 15]]))); }
    public function options() { return success((new MemberCardMemberService())->options($this->request->params([['keyword', ''], ['limit', 20]]))); }
    public function quickCreate() { return success((new MemberCardMemberService())->quickCreate($this->request->params([['request_id', ''], ['name', ''], ['mobile', ''], ['password', '']]))); }
    public function info(int $member_id) { return success((new MemberCardMemberService())->info($member_id)); }
    public function cards(int $member_id) { return success((new MemberCardMemberService())->cards($member_id)); }
}
