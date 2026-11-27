<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\api\controller;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{TeamService,EntityMembershipService,MembershipCostService,MembershipDepositService};
use addon\hsx_lead\app\support\Data;
use core\base\BaseApiController;
use core\exception\CommonException;

final class Entity extends BaseApiController
{
    private function context(): Context
    {
        return new Context((int) $this->request->siteId(), 0, (int) $this->request->memberId());
    }
    public function index()
    {
        return success((new TeamService($this->context()))->summary())->header(['Cache-Control' => 'private, no-store']);
    }
    public function lookup()
    {
        return success((new TeamService($this->context()))->lookup(Data::text($this->request->param(), 'code', 20, true)))->header(['Cache-Control' => 'private, no-store']);
    }
    public function join()
    {
        return success((new TeamService($this->context()))->join($this->request->param()));
    }
    public function review(int $memberId)
    {
        $approve = $this->request->param('approve');
        if (!in_array($approve, [true, false, 0, 1, '0', '1'], true)) throw new CommonException('请明确选择同意或拒绝');
        return success((new TeamService($this->context()))->review($memberId, in_array($approve, [true, 1, '1'], true)));
    }
    public function remove(int $memberId)
    {
        return success((new TeamService($this->context()))->remove($memberId));
    }
    public function leave()
    {
        return success((new TeamService($this->context()))->leave());
    }
    public function membership()
    {
        return success((new EntityMembershipService($this->context()))->summary())->header(['Cache-Control' => 'private, no-store']);
    }
    public function quote()
    {
        $input = $this->request->param();
        $buy = MembershipCostService::flag($input['buy_experience'] ?? false, '是否购买经验');
        return success((new EntityMembershipService($this->context()))->quote(Data::text($input, 'level_code', 16, true), Data::text($input, 'cycle', 24, true), $buy))->header(['Cache-Control' => 'private, no-store']);
    }
    public function closeOrder(int $id)
    {
        return success((new EntityMembershipService($this->context()))->closeUnpaid($id));
    }
    public function deposit()
    {
        return success((new MembershipDepositService($this->context()))->summary())->header(['Cache-Control' => 'private, no-store']);
    }
    public function applyDeposit()
    {
        return success((new MembershipDepositService($this->context()))->apply($this->request->param()))->header(['Cache-Control' => 'private, no-store']);
    }
}
