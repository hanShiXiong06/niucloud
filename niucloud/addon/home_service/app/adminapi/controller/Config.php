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

namespace addon\home_service\app\adminapi\controller;

use addon\home_service\app\service\admin\ConfigService;
use core\base\BaseAdminController;

/**
 * 订单交易设置
 * @description 订单交易设置
 * Class Config
 * @package addon\home_service\app\adminapi\controller\config
 */
class Config extends BaseAdminController
{
    /**
     * 订单设置配置
     * @description 设置交易设置
     * @return \think\Response
     */
    public function setOrderConfig()
    {
        $data = $this->request->params([
            ["is_close", true],
            ["close_length", 120],
            ["is_finish", true],
            ["finish_length",1],
            ["is_use", true],
            ["timeout_time", 5],
            ["about_to_timeout_time", 600],
            ["is_check", true],
            ["check_length",1 ],
            ["week", []],
            ["start", 0],
            ["end", 0],
            ["interval", 0],
            ["advance", 0],
        ]);

        (new ConfigService())->setOrderConfig($data);
        return success('SUCCESS');
    }

    /**
     * 订单配置
     * @description 获取交易配置
     * @return \think\Response
     */
    public function getOrderConfig()
    {
        return success((new ConfigService())->getOrderConfig());
    }

    /**
     * 订单维权配置
     * @description 设置交易设置
     * @return \think\Response
     */
    public function setOrderRefundConfig()
    {
        $data = $this->request->params([
            ["no_allow_refund", true],
            ["refund_length", 7],
            ["refund_expect_revenue_rate", 100],
            ["is_auto_refund", false],
            ["is_check", true],
            ["refund_auto_length", 1445],
        ]);

        (new ConfigService())->setOrderRefundConfig($data);
        return success('SUCCESS');
    }

    /**
     * 获取订单维权配置
     * @description 获取订单维权配置
     * @return \think\Response
     */
    public function getOrderRefundConfig()
    {
        return success((new ConfigService())->getOrderRefundConfig());
    }

    /**
     * 评价配置
     * @description 评价配置
     * @return \think\Response
     */
    public function setEvaluateConfig()
    {
        $data = $this->request->params([
            ["is_evaluate",true],
            ["evaluate_is_to_examine",true],
            ["evaluate_is_show",true],
            ["auto_adopt_examine",true],
            ["auto_adopt_examine_time",""],
        ]);

        (new ConfigService())->setEvaluateConfig($data);
        return success('SUCCESS');
    }

    /**
     * 获取评价配置
     * @description 获取评价配置
     * @return \think\Response
     */
    public function getEvaluateConfig()
    {
        return success((new ConfigService())->getEvaluateConfig());
    }

    /**
     * 提现配置
     * @description 提现配置
     * @return \think\Response
     */
    public function setCashOutConfig()
    {
        $data = $this->request->params([
            ["is_open",1],//是否启用提现
            ["transfer_type",""],
            ["min",""],
            ["is_auto_verify",1],
            ["is_auto_transfer",""],
            ["rate",""],
        ]);

        (new ConfigService())->setCashOutConfig($data);
        return success('SUCCESS');
    }

    /**
     * 获取提现配置
     * @description 获取提现配置
     * @return \think\Response
     */
    public function getCashOutConfig()
    {
        return success((new ConfigService())->getCashOutConfig());
    }

}
