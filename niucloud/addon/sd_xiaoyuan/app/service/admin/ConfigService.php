<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use core\base\BaseAdminService;
use app\service\core\sys\CoreConfigService;
use addon\sd_xiaoyuan\app\dict\config\ConfigDict;

class ConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取配置
     */
    public function getConfig($site_id = 0)
    {
        $site_id = $site_id ?: $this->site_id;
        $info = (new CoreConfigService())->getConfig($site_id, ConfigDict::getConfigType());
        
        $default = $this->getDefaultConfig();
        $value = $info['value'] ?? [];
        
        return array_merge($default, $value);
    }

    /**
     * 设置配置
     */
    public function setConfig($value)
    {
        (new CoreConfigService())->setConfig($this->site_id, ConfigDict::getConfigType(), $value);
        return true;
    }

    /**
     * 按key获取某项配置
     */
    public function get($site_id, $key)
    {
        $config = $this->getConfig($site_id);
        if ($key) {
            // 返回以key为前缀的配置项
            $result = [];
            foreach ($config as $k => $v) {
                if (strpos($k, $key . '_') === 0 || $k === $key) {
                    $result[$k] = $v;
                }
            }
            return !empty($result) ? $result : $config;
        }
        return $config;
    }

    /**
     * 按key设置某项配置
     */
    public function set($site_id, $key, $value)
    {
        $this->site_id = $site_id;
        $config = $this->getConfig($site_id);
        if (is_array($value)) {
            $config = array_merge($config, $value);
        }
        return $this->setConfig($config);
    }

    /**
     * 获取默认配置
     */
    private function getDefaultConfig()
    {
        return [
            'site_name' => '校园帮',
            'service_phone' => '',
            'service_time' => '08:00-22:00',
            'order_expire_time' => 30,
            'accept_expire_time' => 30,
            'base_fee' => 3.00,
            'distance_threshold' => 2,
            'distance_fee' => 1.50,
            'weight_threshold' => 5,
            'weight_fee' => 0.50,
            'urgent_fee' => 5.00,
            'commission_rate' => 20,
            'commission_rate_express' => '',
            'commission_rate_buy' => '',
            'commission_rate_errand' => '',
            'commission_rate_queue' => '',
            'commission_rate_print' => '',
            'commission_rate_seat' => '',
            'commission_rate_carry' => '',
            'commission_rate_trash' => '',
            'commission_rate_clean' => '',
            'commission_rate_help' => '',
            'platform_rate' => 10,
            'open_fenxiao' => 0,
            'fenxiao_rate1' => 0,
            'fenxiao_rate2' => 0,
            'auto_open_fenxiao' => 1,
            'invite_reward_amount' => 10,
            'invite_require_orders' => 1,
            'house_range' => 999,
            'express_type' => 1,
            'express_app_key' => '',
            'express_customer' => '',
            'aliyun_express_appcode' => '',
            'runner_school_limit' => 0
        ];
    }
}
