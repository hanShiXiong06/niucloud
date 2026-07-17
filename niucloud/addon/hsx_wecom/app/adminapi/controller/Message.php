<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\adminapi\controller;

use addon\hsx_wecom\app\service\admin\WecomMessageAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Message extends BaseAdminController
{
    public function lists(): Response
    {
        $data = $this->request->params([['status', ''], ['keyword', ''], ['page', 1], ['limit', 15]]);
        return success((new WecomMessageAdminService())->page($data));
    }

    public function retry(int $id): Response
    {
        $success = (new WecomMessageAdminService())->retry($id);
        return success($success ? '发送成功' : '已进入重试队列');
    }
}
