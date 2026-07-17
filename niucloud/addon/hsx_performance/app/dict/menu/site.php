<?php
declare(strict_types=1);

return [
    [
        'menu_name' => '经营分析', 'menu_key' => 'hsx_performance', 'menu_short_name' => '经营分析',
        'parent_select_key' => '', 'parent_key' => '', 'menu_type' => '0', 'icon' => 'iconfont icontongji',
        'api_url' => '', 'router_path' => '', 'view_path' => '', 'methods' => '', 'sort' => '175', 'status' => '1', 'is_show' => '1',
        'children' => [
            [
                'menu_name' => '经营报告', 'menu_key' => 'hsx_performance_report', 'menu_short_name' => '经营报告',
                'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'performance/reports',
                'router_path' => 'hsx_performance/report', 'view_path' => 'report/index', 'methods' => 'get',
                'sort' => '100', 'status' => '1', 'is_show' => '1',
                'children' => [
                    ['menu_name' => '报告配置', 'menu_key' => 'hsx_performance_config', 'menu_short_name' => '报告配置', 'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => 'performance/config', 'router_path' => '', 'view_path' => '', 'methods' => 'get', 'sort' => '100', 'status' => '1', 'is_show' => '0'],
                    ['menu_name' => '保存报告配置', 'menu_key' => 'hsx_performance_config_save', 'menu_short_name' => '保存配置', 'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => 'performance/config', 'router_path' => '', 'view_path' => '', 'methods' => 'post', 'sort' => '90', 'status' => '1', 'is_show' => '0'],
                    ['menu_name' => '报告详情', 'menu_key' => 'hsx_performance_report_info', 'menu_short_name' => '报告详情', 'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => 'performance/reports/<id>', 'router_path' => '', 'view_path' => '', 'methods' => 'get', 'sort' => '80', 'status' => '1', 'is_show' => '0'],
                    ['menu_name' => '生成报告', 'menu_key' => 'hsx_performance_report_generate', 'menu_short_name' => '生成报告', 'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => 'performance/reports/generate', 'router_path' => '', 'view_path' => '', 'methods' => 'post', 'sort' => '70', 'status' => '1', 'is_show' => '0'],
                    ['menu_name' => '重试报告通知', 'menu_key' => 'hsx_performance_report_retry', 'menu_short_name' => '重试通知', 'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => 'performance/reports/<id>/retry', 'router_path' => '', 'view_path' => '', 'methods' => 'post', 'sort' => '60', 'status' => '1', 'is_show' => '0'],
                ],
            ],
        ],
    ],
];
