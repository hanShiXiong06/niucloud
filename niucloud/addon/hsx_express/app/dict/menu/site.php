<?php

$action = static function (string $name, string $key, string $url, string $method): array {
    return [
        'menu_name' => $name, 'menu_key' => $key, 'menu_short_name' => $name,
        'parent_select_key' => '', 'menu_type' => '2', 'icon' => '', 'api_url' => $url,
        'router_path' => '', 'view_path' => '', 'methods' => $method,
        'sort' => '0', 'status' => '1', 'is_show' => '0',
    ];
};

return [[
    'menu_name' => '物流服务', 'menu_key' => 'hsx_express', 'menu_short_name' => '物流服务',
    'parent_select_key' => '', 'parent_key' => '', 'menu_type' => '0', 'icon' => 'iconfont iconwuliu',
    'api_url' => '', 'router_path' => '', 'view_path' => '', 'methods' => '',
    'sort' => '179', 'status' => '1', 'is_show' => '1',
    'children' => [
        [
            'menu_name' => '接入与上手', 'menu_key' => 'hsx_express_config', 'menu_short_name' => '接入与上手',
            'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'hsx_express/config',
            'router_path' => 'hsx_express/config', 'view_path' => 'config/index', 'methods' => 'get',
            'sort' => '100', 'status' => '1', 'is_show' => '1',
            'children' => [
                $action('保存配置', 'hsx_express_config_save', 'hsx_express/config', 'put'),
                $action('配置检查', 'hsx_express_config_check', 'hsx_express/config/check', 'post'),
            ],
        ],
        [
            'menu_name' => '运单与打印记录', 'menu_key' => 'hsx_express_tasks', 'menu_short_name' => '运单与打印记录',
            'parent_select_key' => '', 'menu_type' => '1', 'icon' => '', 'api_url' => 'hsx_express/tasks',
            'router_path' => 'hsx_express/tasks', 'view_path' => 'tasks/index', 'methods' => 'get',
            'sort' => '90', 'status' => '1', 'is_show' => '1',
            'children' => [
                $action('任务详情', 'hsx_express_tasks_detail', 'hsx_express/tasks/<id>', 'get'),
                $action('补打原单', 'hsx_express_tasks_reprint', 'hsx_express/tasks/<id>/reprint', 'post'),
                $action('恢复原申请', 'hsx_express_tasks_recover', 'hsx_express/tasks/<id>/recover', 'post'),
                $action('取消运单', 'hsx_express_tasks_cancel', 'hsx_express/tasks/<id>/cancel', 'post'),
            ],
        ],
    ],
]];
