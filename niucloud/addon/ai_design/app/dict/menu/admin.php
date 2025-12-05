<?php
return [
    [
        'menu_name' => 'AI设计',
        'menu_key' => 'ai_design',
        'menu_short_name' => 'AI设计',
        'parent_select_key' => '',
        'parent_key' => '',
        'menu_type' => '0',
        'icon' => '',
        'api_url' => '',
        'router_path' => '',
        'view_path' => '',
        'methods' => '',
        'sort' => '100',
        'status' => '1',
        'is_show' => '1',
        'children' => [
            [
                'menu_name' => '设计管理',
                'menu_key' => 'ai_design_design',
                'menu_short_name' => '设计管理',
                'parent_select_key' => '',
                'menu_type' => '1',
                'icon' => '',
                'api_url' => 'ai_design/design',
                'router_path' => 'ai_design/design',
                'view_path' => 'design/index',
                'methods' => 'get',
                'sort' => '100',
                'status' => '1',
                'is_show' => '1',
            ],
        ],
    ],
];

