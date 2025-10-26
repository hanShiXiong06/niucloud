<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\admin;

use addon\home_service\app\service\core\cash_out\CoreCashOutConfigService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use core\base\BaseAdminService;


/**
 * 订单设置服务层
 * Class ConfigService
 * @package adaddon\home_service\app\service\admin
 */
class ConfigService extends BaseAdminService
{

    public $order_config_service;

    public function __construct()
    {
        parent::__construct();
        $this->order_config_service = new CoreOrderConfigService();
    }

    /**
     * 订单设置
     * @param array $params
     * @return array
     */
    public function setOrderConfig($params)
    {
        $params['site_id'] = $this->site_id;
        return $this->order_config_service->setOrderConfig($params);
    }

    /**
     * 获取订单设置
     * @return array
     */
    public function getOrderConfig()
    {
        return $this->order_config_service->getOrderConfig($this->site_id);
    }

    /**
     * 订单售后设置
     * @param array $params
     * @return array
     */
    public function setOrderRefundConfig($params)
    {
        $params['site_id'] = $this->site_id;
        return $this->order_config_service->setOrderRefundConfig($params);
    }

    /**
     * 获取订单售后设置
     * @return array
     */
    public function getOrderRefundConfig()
    {
        return $this->order_config_service->getOrderRefundConfig($this->site_id);
    }


    /**
     * 设置评价设置
     * @return array|int[]|mixed
     */
    public function setEvaluateConfig($params)
    {
        $params['site_id'] = $this->site_id;
        return (new CoreOrderConfigService())->setEvaluateConfig($params);
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
