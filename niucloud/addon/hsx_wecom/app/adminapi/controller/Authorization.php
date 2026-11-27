<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\adminapi\controller;

use addon\hsx_wecom\app\service\admin\WecomAuthorizationAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Authorization extends BaseAdminController
{
    public function status(): Response
    {
        return success((new WecomAuthorizationAdminService())->status());
    }

    public function start(): Response
    {
        return success((new WecomAuthorizationAdminService())->start(
            trim((string)$this->request->param('return_url', ''))
        ));
    }

    public function check(): Response
    {
        return success((new WecomAuthorizationAdminService())->check());
    }
}
