<?php
return [
    [
        'menu_name' => '组件开发中心',
        'menu_key' => 'hsx_components',
        'menu_short_name' => '组件库',
        'parent_key' => 'tool',
        'menu_type' => '0',
        'icon' => 'element Grid',
        'api_url' => '',
        'router_path' => '',
        'view_path' => '',
        'methods' => '',
        'sort' => '120',
        'status' => '1',
        'is_show' => '1',
        'support_addon' => '',
        'mount_key' => '',
        'children' => [
            [
                'menu_name' => '组件总览', 'menu_key' => 'hsx_components_overview', 'menu_short_name' => '组件总览',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/overview',
                'view_path' => 'overview/index', 'methods' => 'get', 'sort' => '100', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => '基础组件', 'menu_key' => 'hsx_components_basic', 'menu_short_name' => '基础组件',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/basic',
                'view_path' => 'basic/index', 'methods' => 'get', 'sort' => '90', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => '图表与反馈', 'menu_key' => 'hsx_components_visual', 'menu_short_name' => '图表与反馈',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/visual',
                'view_path' => 'visual/index', 'methods' => 'get', 'sort' => '85', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => 'Schema 组件', 'menu_key' => 'hsx_components_pro', 'menu_short_name' => 'Schema 组件',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/pro',
                'view_path' => 'pro/index', 'methods' => 'get', 'sort' => '80', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => '业务组件', 'menu_key' => 'hsx_components_business', 'menu_short_name' => '业务组件',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/business',
                'view_path' => 'business/index', 'methods' => 'get', 'sort' => '75', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => '移动端组件', 'menu_key' => 'hsx_components_mobile', 'menu_short_name' => '移动端组件',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/mobile',
                'view_path' => 'mobile/index', 'methods' => 'get', 'sort' => '70', 'status' => '1', 'is_show' => '1',
            ],
            [
                'menu_name' => 'API 手册', 'menu_key' => 'hsx_components_api', 'menu_short_name' => 'API 手册',
                'menu_type' => '1', 'icon' => '', 'api_url' => '', 'router_path' => 'tools/hsx-components/api',
                'view_path' => 'api/index', 'methods' => 'get', 'sort' => '60', 'status' => '1', 'is_show' => '1',
            ],
        ],
    ],
];
