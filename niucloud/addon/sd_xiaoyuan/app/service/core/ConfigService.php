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
        
        // 设置默认值
        $info['value']['site_name'] = $info['value']['site_name'] ?? '校园帮';
        $info['value']['service_phone'] = $info['value']['service_phone'] ?? '';
        $info['value']['service_time'] = $info['value']['service_time'] ?? '08:00-22:00';
        $info['value']['order_expire_time'] = $info['value']['order_expire_time'] ?? 30;
        $info['value']['accept_expire_time'] = $info['value']['accept_expire_time'] ?? 30;
        $info['value']['base_fee'] = $info['value']['base_fee'] ?? 3.00;
        $info['value']['distance_threshold'] = $info['value']['distance_threshold'] ?? 2;
        $info['value']['distance_fee'] = $info['value']['distance_fee'] ?? 1.50;
        $info['value']['weight_threshold'] = $info['value']['weight_threshold'] ?? 5;
        $info['value']['weight_fee'] = $info['value']['weight_fee'] ?? 0.50;
        $info['value']['urgent_fee'] = $info['value']['urgent_fee'] ?? 5.00;
        $info['value']['commission_rate'] = $info['value']['commission_rate'] ?? 20;
        $info['value']['platform_rate'] = $info['value']['platform_rate'] ?? 10;
        $info['value']['open_fenxiao'] = $info['value']['open_fenxiao'] ?? 0;
        $info['value']['fenxiao_rate1'] = $info['value']['fenxiao_rate1'] ?? 0;
        $info['value']['fenxiao_rate2'] = $info['value']['fenxiao_rate2'] ?? 0;
        $info['value']['express_type'] = $info['value']['express_type'] ?? 1;
        $info['value']['express_app_key'] = $info['value']['express_app_key'] ?? '';
        $info['value']['express_customer'] = $info['value']['express_customer'] ?? '';
        $info['value']['aliyun_express_appcode'] = $info['value']['aliyun_express_appcode'] ?? '';
        $info['value']['runner_school_limit'] = $info['value']['runner_school_limit'] ?? 0;
        $info['value']['runner_complete_point'] = $info['value']['runner_complete_point'] ?? 0;
        $info['value']['auto_confirm_days'] = $info['value']['auto_confirm_days'] ?? 0;
        
        // 功能开关
        $info['value']['enable_buy'] = $info['value']['enable_buy'] ?? 1;
        $info['value']['enable_send'] = $info['value']['enable_send'] ?? 1;
        $info['value']['enable_express'] = $info['value']['enable_express'] ?? 1;
        $info['value']['enable_print'] = $info['value']['enable_print'] ?? 1;
        $info['value']['enable_trash'] = $info['value']['enable_trash'] ?? 1;
        $info['value']['enable_carry'] = $info['value']['enable_carry'] ?? 1;
        $info['value']['enable_clean'] = $info['value']['enable_clean'] ?? 1;
        $info['value']['enable_help'] = $info['value']['enable_help'] ?? 1;
        $info['value']['enable_confession'] = $info['value']['enable_confession'] ?? 1;
        $info['value']['enable_game'] = $info['value']['enable_game'] ?? 1;
        $info['value']['enable_parttime'] = $info['value']['enable_parttime'] ?? 1;
        $info['value']['enable_companion'] = $info['value']['enable_companion'] ?? 1;
        $info['value']['enable_house'] = $info['value']['enable_house'] ?? 1;
        $info['value']['enable_schedule'] = $info['value']['enable_schedule'] ?? 1;
        $info['value']['enable_group'] = $info['value']['enable_group'] ?? 1;
        $info['value']['enable_secondhand'] = $info['value']['enable_secondhand'] ?? 1;
        $info['value']['enable_lost_found'] = $info['value']['enable_lost_found'] ?? 1;
        $info['value']['enable_community'] = $info['value']['enable_community'] ?? 1;
        $info['value']['secondhand_name'] = $info['value']['secondhand_name'] ?? '闲置市场';
        $info['value']['community_name'] = $info['value']['community_name'] ?? '校园树洞';
        $info['value']['enable_sign'] = $info['value']['enable_sign'] ?? 1;
        $info['value']['enable_points_mall'] = $info['value']['enable_points_mall'] ?? 1;
        $info['value']['enable_diy_index'] = $info['value']['enable_diy_index'] ?? 0;
        $info['value']['use_diy_index'] = $info['value']['use_diy_index'] ?? 0;
        $info['value']['close_text'] = $info['value']['close_text'] ?? '功能已下架';
        $info['value']['require_auth_publish'] = $info['value']['require_auth_publish'] ?? 1;
        $info['value']['unauth_show_order_hall'] = $info['value']['unauth_show_order_hall'] ?? 0;
        $info['value']['enable_custom_tabbar'] = $info['value']['enable_custom_tabbar'] ?? 1;
        $info['value']['community_auto_approve'] = $info['value']['community_auto_approve'] ?? 0;
        $info['value']['confession_auto_approve'] = $info['value']['confession_auto_approve'] ?? 0;
        $info['value']['house_auto_approve'] = $info['value']['house_auto_approve'] ?? 0;
        $info['value']['secondhand_auto_approve'] = $info['value']['secondhand_auto_approve'] ?? 0;
        $info['value']['lost_found_auto_approve'] = $info['value']['lost_found_auto_approve'] ?? 0;
        
        return $info['value'];
    }
}