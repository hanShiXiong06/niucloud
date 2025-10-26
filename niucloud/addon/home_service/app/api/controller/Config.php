<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\api\controller;

use addon\home_service\app\service\api\ConfigService;
use core\base\BaseApiController;

/**
 * 配置控制器
 * Class GoodsController
 * @package app\adminapi\controller
 */
class Config extends BaseApiController
{

    public function getOrderConfig()
    {
        return success((new ConfigService())->getOrderConfig());
    }

    /**
     * 评价设置
     * @return \think\Response
     */
    public function evaluate()
    {
        return success((new ConfigService())->getEvaluateConfig());
    }

    /**
     * 提现配置
     * @return \think\Response
     */
    public function cashOutConfig()
    {
        return success((new ConfigService())->getCashOutConfig());
    }

}
