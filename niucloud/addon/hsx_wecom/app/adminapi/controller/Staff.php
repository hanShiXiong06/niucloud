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

    public function bindUrl(int $uid): Response
    {
        return success((new WecomStaffService())->bindUrl(
            $uid,
            trim((string)$this->request->param('return_url', ''))
        ));
    }

    public function bindStatus(int $uid): Response
    {
        return success((new WecomStaffService())->bindStatus($uid, (int)$this->request->param('intent_id', 0)));
    }

    public function bindConfirm(int $uid): Response
    {
        return success((new WecomStaffService())->bindConfirm($uid, (int)$this->request->param('intent_id', 0)));
    }

    public function bindCancel(int $uid): Response
    {
        return success((new WecomStaffService())->bindCancel($uid, (int)$this->request->param('intent_id', 0)));
    }
}
