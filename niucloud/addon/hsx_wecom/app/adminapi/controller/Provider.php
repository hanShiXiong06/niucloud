<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\adminapi\controller;

use addon\hsx_wecom\app\service\admin\WecomProviderAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Provider extends BaseAdminController
{
    public function info(): Response
    {
        return success((new WecomProviderAdminService())->info());
    }

    public function save(): Response
    {
        $data = $this->request->params([
            ['id', 0], ['enabled', 0], ['channel_code', 'default'], ['provider_corp_id', ''],
            ['suite_id', ''], ['suite_secret', ''], ['callback_token', ''], ['encoding_aes_key', ''],
            ['admin_miniapp_appid', ''], ['admin_miniapp_name', '后台管理端'], ['web_base_url', ''],
            ['event_callback_url', ''], ['auth_callback_url', ''],
        ]);
        return success((new WecomProviderAdminService())->save($data));
    }

    public function test(): Response
    {
        return success((new WecomProviderAdminService())->test());
    }
}
