<?php

namespace addon\ai_image\app\api\controller\config;

use addon\ai_image\app\service\api\aiimagecreate\AiimageCreateService;
use core\base\BaseApiController;
use addon\ai_image\app\service\api\config\ConfigService;


class Config extends BaseApiController
{

    /**
     * Author: TK
     * Notes:  获取配置信息
     * @return \think\Response
     * 2025/10/15 22:39
     */
    public function getConfig()
    {
        return success("操作成功", (new ConfigService())->getConfig());
    }
    public function getStat()
    {
        return success((new AiimageCreateService())->getStat());
    }
}
