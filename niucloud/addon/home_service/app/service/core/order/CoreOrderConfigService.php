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

namespace addon\home_service\app\service\core\order;

use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;


/**
 * 订单设置服务层
 * Class CoreOrderConfigService
 * @package addon\home_service\app\service\core
 */
class CoreOrderConfigService extends BaseCoreService
{
    //系统配置文件
    public $core_config_service;

    public function __construct()
    {
        parent::__construct();
        $this->core_config_service = new CoreConfigService();
    }

    /**
     * 设置交易配置
     * @param array $params
     * @return array
     */
    public function setOrderConfig($params)
    {
        $site_id = $params['site_id'];

        $value['order_close'] = [
            'is_close' => $params['is_close'],
            'close_length' => $params['close_length']
        ];
        $value['order_finish'] = [
            'is_finish' => 0,
            'finish_length' => $params['finish_length']
        ];
        $value['dispatch_timeout'] = [
            'is_use' => $params['is_use'],
            'timeout_time' => $params['timeout_time'],  // 5 分钟不派单直接  恢复
        ];
        $value['order_time'] = [
            'about_to_timeout_time' => $params['about_to_timeout_time'], //即将超时时间  默认30分钟
        ];
        $value['check'] = [    //门店抢单 待分配时间
            'is_check' => $params['is_check'],
            'check_length' => $params['check_length'],  //自定验收时间   (最大10--1)
        ];
        $value['reserve'] = [  // 预约配置
            'week' => $params['week'],
            'start' => $params['start'],
            'end' => $params['end'],
            'interval' => $params['interval'],
            'advance' => $params['advance'],
        ];
        $this->core_config_service->setConfig($site_id, 'HOME_SERVICE_ORDER_CONFIG', $value);

        return true;
    }

    /**
     * 获取订单配置
     * @param int $id
     * @return array
     */
    public function getOrderConfig(int $site_id)
    {
        $config = (new CoreConfigService())->getConfigValue($site_id, 'HOME_SERVICE_ORDER_CONFIG');
        if (empty($config)) {
            $data['order_close'] = [
                'is_close' => true,
                'close_length' => 120
            ];
            $data['order_finish'] = [
                'is_finish' => true,
                'finish_length' => 14
            ];
            $data['dispatch_timeout'] = [
                'is_use' => true,
                'timeout_time' => 5,  // 5 分钟不派单直接  恢复
            ];
            $data['order_time'] = [
                'about_to_timeout_time' => 30, //即将超时时间  默认30分钟
            ];
            $data['check'] = [    //门店抢单 待分配时间
                'is_check' => true,
                'check_length' => 10,  //自定验收时间   (最大10--1)
            ];
            $data['reserve'] = [  // 预约配置
                'week' => ['1', '2', '3', '4', '5'],
                'start' => 32400,
                'end' => 79200,
                'interval' => 30,
                'advance' => 1,
            ];
        } else {
            $data['order_close'] = [
                'is_close' => $config['order_close']['is_close'],
                'close_length' => $config['order_close']['close_length']
            ];
            $data['order_finish'] = [
                'is_finish' => $config['order_finish']['is_finish'],
                'finish_length' => $config['order_finish']['finish_length'],
            ];
            $data['dispatch_timeout'] = [
                'is_use' => $config['dispatch_timeout']['is_use'],
                'timeout_time' => $config['dispatch_timeout']['timeout_time'],
            ];
            $data['order_time'] = [
                'about_to_timeout_time' => $config['order_time']['about_to_timeout_time'],
            ];
            $data['check'] = [    //门店抢单 待分配时间
                'is_check' => $config['check']['is_check'],
                'check_length' => $config['check']['check_length'] ,  //自定验收时间   (最大10--1)
            ];
            $data['reserve'] = [  // 预约配置
                'week' => $config['reserve']['week'],
                'start' => $config['reserve']['start'],
                'end' => $config['reserve']['end'],
                'interval' => $config['reserve']['interval'],
                'advance' => $config['reserve']['advance'],
            ];
        }
        return $data;
    }

    /**
     * 订单维权配置
     * @param array $params
     * @return array
     */
    public function setOrderRefundConfig($params)
    {
        $site_id = $params['site_id'];
        $value['order_refund'] = [
            'no_allow_refund' => $params['no_allow_refund'],
            'refund_length' => $params['refund_length'],
            'refund_expect_revenue_rate' => $params['refund_expect_revenue_rate']  //平台保价比例
        ];
        $value['order_auto_refund'] = [
            'is_auto_refund' => $params['is_auto_refund'],
        ];
        $value['refund_auto'] = [    //售后自动通过时间
            'is_check' =>  $params['is_check'],
            'refund_auto_length' => $params['refund_auto_length'],  //售后自动通过时间    (最大10*24*60 )
        ];
        $this->core_config_service->setConfig($site_id, 'HOME_SERVICE_ORDER_REFUND_CONFIG', $value);

        return true;
    }


    /**
     * 获取订单维权配置
     * @param int $site_id
     * @return array
     */
    public function getOrderRefundConfig(int $site_id)
    {
        $config = (new CoreConfigService())->getConfigValue($site_id, 'HOME_SERVICE_ORDER_REFUND_CONFIG');
        if (empty($config)) {

            $data['order_refund'] = [
                'no_allow_refund' => true,
                'refund_length' => 7,
                'refund_expect_revenue_rate' => 30  //平台保价比例
            ];

            $data['order_auto_refund'] = [
                'is_auto_refund' => false,
            ];

            $data['refund_auto'] = [    //售后自动通过时间
                'is_check' =>  true,
                'refund_auto_length' => 10,  //售后自动通过时间
            ];

        } else {
            $data['order_refund'] = [
                'no_allow_refund' => $config['order_refund']['no_allow_refund'],
                'refund_length' => $config['order_refund']['refund_length'],
                'refund_expect_revenue_rate' => $config['order_refund']['refund_expect_revenue_rate'],  //平台保价比例
            ];

            $data['order_auto_refund'] = [
                'is_auto_refund' => $config['order_auto_refund']['is_auto_refund'],
            ];

            $data['refund_auto'] = [    //售后自动通过时间
                'is_check' => $config['refund_auto']['is_check'],
                'refund_auto_length' => $config['refund_auto']['refund_auto_length'],  //售后自动通过时间    (最大10*24*60 )
            ];
        }


        return $data;
    }

    /**
     * 评价设置
     * @param array $params
     * @return array
     */
    public function setEvaluateConfig($params)
    {
        $site_id = $params['site_id'];

        $config = [
            'is_evaluate' => $params['is_evaluate'],
            'evaluate_is_to_examine' => $params['evaluate_is_to_examine'],
            'evaluate_is_show' => $params['evaluate_is_show'],
            'auto_adopt_examine' => $params['auto_adopt_examine'],
            'auto_adopt_examine_time' => $params['auto_adopt_examine_time']
        ];
        $this->core_config_service->setConfig($site_id, 'HOME_SERVICE_GOODS_EVALUATE', $config);

        return true;
    }

    /**
     * 获取评价设置
     * @return array|int[]|mixed
     */
    public function getEvaluateConfig(int $site_id)
    {
        $config = (new CoreConfigService())->getConfigValue($site_id, 'HOME_SERVICE_GOODS_EVALUATE');
        if (empty($config)) {
            $config = [
                'is_evaluate' => true,
                'evaluate_is_to_examine' => true,
                'evaluate_is_show' => true,
                'auto_adopt_examine' => 0,
                'auto_adopt_examine_time' => 0
            ];
        }
        return $config;
    }

}

