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

namespace addon\home_service\app\service\api;

use addon\home_service\app\service\core\cash_out\CoreCashOutConfigService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use core\base\BaseApiService;

/**
 * 配置服务层
 * Class ConfigService
 * @package app\service\api
 */
class ConfigService extends BaseApiService
{


    /**
     * 获取订单配置信息
     * @return Response
     */
    public function getOrderConfig()
    {

        return ((new CoreOrderConfigService())->getOrderConfig($this->site_id));
    }


    /**
     * 获取评价设置
     * @return array|int[]|mixed
     */
    public function getEvaluateConfig()
    {
        return (new CoreOrderConfigService())->getEvaluateConfig($this->site_id);
    }

    /**
     * 获取提现设置
     * @return array|int[]|mixed
     */
    public function getCashOutConfig()
    {
        return (new CoreCashOutConfigService())->getCashOutConfig($this->site_id);
    }


}
