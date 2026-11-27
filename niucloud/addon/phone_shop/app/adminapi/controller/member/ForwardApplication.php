<?php

namespace addon\phone_shop\app\adminapi\controller\member;

use addon\phone_shop\app\service\admin\member\ForwardApplicationService;
use core\base\BaseAdminController;

class ForwardApplication extends BaseAdminController
{
    public function pages()
    {
        $data = $this->request->params([
            ['status', ''],
            ['keyword', ''],
            ['reviewer_uid', 0],
        ]);
        return success((new ForwardApplicationService())->getPage($data));
    }

    public function info(int $id)
    {
        return success((new ForwardApplicationService())->getInfo($id));
    }

    public function review(int $id)
    {
        $data = $this->request->params([
            ['action', ''],
            ['reason', ''],
        ]);
        (new ForwardApplicationService())->review($id, (string)$data['action'], (string)$data['reason']);
        return success($data['action'] === 'approve' ? '已通过并设置会员身份' : '已拒绝申请');
    }
}
