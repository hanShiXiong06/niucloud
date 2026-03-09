<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use core\base\BaseApiService;
use app\service\core\sys\CoreConfigService;
use addon\sd_xiaoyuan\app\dict\config\ConfigDict;

/**
 * 配置服务(前端API用)
 */
class ConfigService extends BaseApiService
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
            'platform_rate' => 10,
            'open_fenxiao' => 0,
            'fenxiao_rate1' => 0,
            'fenxiao_rate2' => 0,
            'express_type' => 1,
            'express_app_key' => '',
            'express_customer' => '',
            'aliyun_express_appcode' => ''
        ];
    }
}
