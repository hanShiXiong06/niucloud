<?php

namespace addon\fast_pay\app\api\controller\config;
use addon\fast_pay\app\service\core\config\ConfigService;
use core\base\BaseApiController;

class Config extends BaseApiController
{
    /**
     * @Notes:获取配置数据
     * @Interface getConfig
     * @return \think\Response
     * @author: TK
     * @Time: 2024/8/5   下午1:26
     */
    public function getConfig()
    {
        return success("操作成功",(new ConfigService())->getConfig());
    }
    public function getBusinessConfig($id)
    {
        return success("操作成功",(new ConfigService())->getBusinessConfig($id));
    }
}
