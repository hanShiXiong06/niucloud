<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\ConfigService;
use core\base\BaseAdminController;

class Config extends BaseAdminController
{
    /**
     * 获取配置
     */
    public function get()
    {
        $service = new ConfigService();
        $config = $service->getConfig();
        
        // 将配置按类型分组返回，以匹配前端期望的格式
        $result = [
            'base' => [
                'service_phone' => $config['service_phone'] ?? '',
                'service_time' => $config['service_time'] ?? '08:00-22:00',
                'order_timeout' => $config['order_expire_time'] ?? 30,
                'accept_timeout' => $config['accept_expire_time'] ?? 30,
                'runner_school_limit' => $config['runner_school_limit'] ?? 0,
                'runner_complete_point' => $config['runner_complete_point'] ?? 0,
                'auto_confirm_days' => $config['auto_confirm_days'] ?? 0,
                'unauth_show_order_hall' => $config['unauth_show_order_hall'] ?? 0,
            ],
            'fee' => [
                'base_fee' => $config['base_fee'] ?? 3.00,
                'distance_price' => $config['distance_fee'] ?? 1.50,
                'free_distance' => $config['distance_threshold'] ?? 2,
                'weight_price' => $config['weight_fee'] ?? 0.50,
                'free_weight' => $config['weight_threshold'] ?? 5,
                'urgent_fee' => $config['urgent_fee'] ?? 5.00,
                'commission_rate' => $config['commission_rate'] ?? 20,
                'commission_rate_express' => $config['commission_rate_express'] ?? null,
                'commission_rate_buy' => $config['commission_rate_buy'] ?? null,
                'commission_rate_errand' => $config['commission_rate_errand'] ?? null,
                'commission_rate_send' => $config['commission_rate_send'] ?? null,
                'commission_rate_queue' => $config['commission_rate_queue'] ?? null,
                'commission_rate_print' => $config['commission_rate_print'] ?? null,
                'commission_rate_seat' => $config['commission_rate_seat'] ?? null,
                'commission_rate_carry' => $config['commission_rate_carry'] ?? null,
                'commission_rate_trash' => $config['commission_rate_trash'] ?? null,
                'commission_rate_clean' => $config['commission_rate_clean'] ?? null,
                'commission_rate_help' => $config['commission_rate_help'] ?? null,
                'commission_rate_game' => $config['commission_rate_game'] ?? null,
                'commission_rate_group' => $config['commission_rate_group'] ?? null,
                'commission_rate_parttime' => $config['commission_rate_parttime'] ?? null,
                'commission_rate_companion' => $config['commission_rate_companion'] ?? null,
            ],
            'express' => [
                'express_type' => $config['express_type'] ?? 1,
                'express_app_key' => $config['express_app_key'] ?? '',
                'express_customer' => $config['express_customer'] ?? '',
                'aliyun_express_appcode' => $config['aliyun_express_appcode'] ?? '',
            ],
            'features' => [
                'enable_buy' => $config['enable_buy'] ?? 1,
                'enable_send' => $config['enable_send'] ?? 1,
                'enable_express' => $config['enable_express'] ?? 1,
                'enable_print' => $config['enable_print'] ?? 1,
                'enable_trash' => $config['enable_trash'] ?? 1,
                'enable_carry' => $config['enable_carry'] ?? 1,
                'enable_clean' => $config['enable_clean'] ?? 1,
                'enable_help' => $config['enable_help'] ?? 1,
                'enable_game' => $config['enable_game'] ?? 1,
                'enable_parttime' => $config['enable_parttime'] ?? 1,
                'enable_companion' => $config['enable_companion'] ?? 1,
                'enable_queue' => $config['enable_queue'] ?? 1,
                'enable_seat' => $config['enable_seat'] ?? 1,
                'enable_house' => $config['enable_house'] ?? 1,
                'enable_schedule' => $config['enable_schedule'] ?? 1,
                'enable_group' => $config['enable_group'] ?? 1,
                'enable_secondhand' => $config['enable_secondhand'] ?? 1,
                'enable_lost_found' => $config['enable_lost_found'] ?? 1,
                'enable_community' => $config['enable_community'] ?? 1,
                'secondhand_name' => $config['secondhand_name'] ?? '闲置市场',
                'community_name' => $config['community_name'] ?? '校园树洞',
                'enable_confession' => $config['enable_confession'] ?? 1,
                'enable_sign' => $config['enable_sign'] ?? 1,
                'enable_points_mall' => $config['enable_points_mall'] ?? 1,
                'close_text' => $config['close_text'] ?? '功能已下架',
                'require_auth_publish' => $config['require_auth_publish'] ?? 1,
                'enable_custom_tabbar' => $config['enable_custom_tabbar'] ?? 1,
                'community_auto_approve' => $config['community_auto_approve'] ?? 0,
                'confession_auto_approve' => $config['confession_auto_approve'] ?? 0,
                'house_auto_approve' => $config['house_auto_approve'] ?? 0,
                'secondhand_auto_approve' => $config['secondhand_auto_approve'] ?? 0,
                'lost_found_auto_approve' => $config['lost_found_auto_approve'] ?? 0,
            ],
        ];
        
        return success($result);
    }

    /**
     * 保存配置
     */
    public function set()
    {
        $type = $this->request->post('type', '');
        $config = $this->request->post('config', []);
        
        $service = new ConfigService();
        
        // 获取当前完整配置
        $allConfig = $service->getConfig();
        
        // 根据type合并对应的配置
        if ($type && $config) {
            // 字段名映射：前端字段名 => 后端字段名
            $fieldMapping = [
                'order_timeout' => 'order_expire_time',
                'accept_timeout' => 'accept_expire_time',
                'distance_price' => 'distance_fee',
                'free_distance' => 'distance_threshold',
                'weight_price' => 'weight_fee',
                'free_weight' => 'weight_threshold',
            ];
            
            // 转换字段名
            $mappedConfig = [];
            foreach ($config as $key => $value) {
                $backendKey = $fieldMapping[$key] ?? $key;
                $mappedConfig[$backendKey] = $value;
            }
            
            // 只更新提交的字段，保留其他字段
            foreach ($mappedConfig as $key => $value) {
                $allConfig[$key] = $value;
            }
        } else {
            // 如果没有type，说明是完整配置提交
            $allData = $this->request->post();
            
            // 字段名映射
            $fieldMapping = [
                'order_timeout' => 'order_expire_time',
                'accept_timeout' => 'accept_expire_time',
                'distance_price' => 'distance_fee',
                'free_distance' => 'distance_threshold',
                'weight_price' => 'weight_fee',
                'free_weight' => 'weight_threshold',
            ];
            
            // 转换字段名并更新
            foreach ($allData as $key => $value) {
                if ($key === 'type' || $key === 'config') continue;
                $backendKey = $fieldMapping[$key] ?? $key;
                $allConfig[$backendKey] = $value;
            }
        }
        
        // 保存配置
        $service->setConfig($allConfig);
        
        return success('保存成功');
    }

    /**
     * 保存配置（别名）
     */
    public function save()
    {
        return $this->set();
    }

    /**
     * 获取费用配置
     */
    public function getFee()
    {
        $service = new ConfigService();
        $config = $service->getConfig();
        return success($config);
    }

    /**
     * 保存费用配置
     */
    public function setFee()
    {
        $service = new ConfigService();
        $config = $service->getConfig();

        $fieldMapping = [
            'distance_price' => 'distance_fee',
            'free_distance' => 'distance_threshold',
            'weight_price' => 'weight_fee',
            'free_weight' => 'weight_threshold',
        ];

        $post = $this->request->post();
        if (is_array($post)) {
            foreach ($post as $key => $value) {
                $backendKey = $fieldMapping[$key] ?? $key;
                $config[$backendKey] = $value;
            }
        }

        $service->setConfig($config);

        return success('保存成功');
    }

    /**
     * 获取范围配置
     */
    public function getRange()
    {
        $service = new ConfigService();
        $config = $service->getConfig();
        return success($config);
    }

    /**
     * 保存范围配置
     */
    public function setRange()
    {
        $data = $this->request->params([
            ['enabled', true],
            ['center_lng', ''],
            ['center_lat', ''],
            ['radius', 5],
            ['campus_list', []]
        ]);
        
        $service = new ConfigService();
        // 获取当前配置
        $config = $service->getConfig();
        // 合并范围配置
        $config = array_merge($config, $data);
        // 保存
        $service->setConfig($config);
        
        return success('保存成功');
    }
}
