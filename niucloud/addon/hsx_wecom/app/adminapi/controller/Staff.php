<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\adminapi\controller;

use addon\hsx_wecom\app\service\admin\WecomStaffService;
use core\base\BaseAdminController;
use think\Response;

final class Staff extends BaseAdminController
{
    public function lists(): Response
    {
        return success((new WecomStaffService())->lists());
    }

    public function save(int $uid): Response
    {
        $data = $this->request->params([['wecom_userid', ''], ['status', 1]]);
        (new WecomStaffService())->save($uid, $data);
        return success('保存成功');
    }
}
