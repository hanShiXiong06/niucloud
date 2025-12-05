<?php
return [
    [
        'menu_name' => 'AI设计',
        'menu_key' => 'ai_image',
        'menu_short_name' => '设计',
        'parent_key' => '',
        'menu_type' => '0',
        'icon' => 'nc-iconfont nc-icon-bianjiV6xx1',
        'api_url' => '',
        'router_path' => '',
        'view_path' => '',
        'methods' => '',
        'sort' => '100',
        'status' => '1',
        'is_show' => '1',
        'children' => [
            [
                'menu_name' => '随行付',
                'menu_key' => 'ai_image_sxf_config',
                'menu_short_name' => '随行付',
                'menu_type' => '1',
                'icon' => 'nc-iconfont nc-icon-bianjiV6xx',
                'api_url' => '',
                'router_path' => 'ai_image/sxf_config',
                'view_path' => 'config/sxf',
                'methods' => 'get',
                'sort' => '90',
                'status' => '1',
                'is_show' => '1',
            ],
        ],
    ],
];