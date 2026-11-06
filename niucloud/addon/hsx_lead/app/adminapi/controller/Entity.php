<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\adminapi\controller;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\TeamService;
use core\base\BaseAdminController;
use core\exception\CommonException;

final class Entity extends BaseAdminController
{
    private function service(): TeamService { return new TeamService(new Context((int)$this->request->siteId(), (int)$this->request->uid())); }
    public function lists() { return success($this->service()->adminList($this->request->param())); }
    public function detail(int $id) { return success($this->service()->adminDetail($id))->header(['Cache-Control'=>'private, no-store']); }
    public function review(int $id, int $member_id)
    {
        $approve=$this->request->param('approve');
        if (!is_bool($approve)) throw new CommonException('请明确选择批准或拒绝加入，不能使用含糊的审核值');
        return success($this->service()->adminReview($id,$member_id,$approve));
    }
    public function remove(int $id, int $member_id) { return success($this->service()->adminRemove($id,$member_id)); }
}
