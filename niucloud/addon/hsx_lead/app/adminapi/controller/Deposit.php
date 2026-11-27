<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\MembershipDepositService;
use core\base\BaseAdminController;
final class Deposit extends BaseAdminController
{
    private function service():MembershipDepositService{return new MembershipDepositService(new Context((int)$this->request->siteId(),(int)$this->request->uid()));}
    public function lists(){return success($this->service()->adminList($this->request->param()))->header(['Cache-Control'=>'private, no-store']);}
    public function detail(int $id){return success($this->service()->detail($id))->header(['Cache-Control'=>'private, no-store']);}
    public function review(int $id){return success($this->service()->review($id,$this->request->param()));}
    public function refresh(int $id){return success($this->service()->refresh($id));}
}
