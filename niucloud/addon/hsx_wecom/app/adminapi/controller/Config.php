<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\adminapi\controller;

use addon\hsx_wecom\app\service\admin\WecomConfigAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Config extends BaseAdminController
{
    public function info(): Response
    {
        return success((new WecomConfigAdminService())->getConfig());
    }

    public function save(): Response
    {
        $data = $this->request->params([
            ['enabled', 0], ['corp_id', ''], ['agent_id', 0], ['secret', ''],
            ['web_base_url', ''], ['miniapp_appid', ''], ['jump_mode', 'web'],
            ['admin_base_url', ''], ['task_notice_enabled', 1], ['report_notice_enabled', 1], ['recycle_task_target', 'detail'],
        ]);
        return success((new WecomConfigAdminService())->save($data));
    }

    public function test(): Response
    {
        return success((new WecomConfigAdminService())->test());
    }
}
