<?php

namespace addon\tk_vip\app\api\controller\config;
use core\base\BaseAdminController;
use addon\tk_vip\app\service\core\ConfigService;


class Config extends BaseAdminController
{

    /**
     * @Notes:获取配置
     * @Interface getConfig
     * @return \think\Response
     * @author: TK
     * @Time: 2024/10/25   上午8:56
     */
    public function getConfig()
    {
        return success("操作成功",(new ConfigService())->getConfig());
    }

}
