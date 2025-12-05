<?php

return  [
    [
        'menu_name' => 'hsx_cms',
        'menu_key' => 'hsx_cms',
        'menu_type' => 0,
        'icon' => '',
        'api_url' => '',
        'router_path' => '',
        'view_path' => '',
        'methods' => '',
        'sort' => 100,
        'status' => 1,
        'is_show' => 1,
        'children' => [
            [
                'menu_name' => 'hsx_cms',
                'menu_key' => 'hsx_cms_hello_world',
                'menu_type' => 1,
                'icon' => '',
                'api_url' => 'hsx_cms/hello_world',
                'router_path' => 'hsx_cms/hello_world',
                'view_path' => 'hello_world/index',
                'methods' => 'get',
                'sort' => 100,
                'status' => 1,
                'is_show' => 1,
                'children' => []
            ],
        ]
    ]
];
