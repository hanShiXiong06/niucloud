<?php
return [
    [
        'menu_name' => '设备桥下载', 'menu_key' => 'hsx_recycle_device_bridge',
        'menu_short_name' => '设备桥', 'parent_key' => 'setting_manage', 'menu_type' => '1',
        'icon' => 'element Connection', 'api_url' => 'recycle/platform/device_bridge',
        'router_path' => 'recycle/device-bridge', 'view_path' => 'device_bridge/settings',
        'methods' => 'get', 'sort' => 119, 'status' => 1, 'is_show' => 1,
        'support_addon' => '', 'mount_key' => '',
        'children' => [
            [
                'menu_name' => '保存设备桥配置', 'menu_key' => 'hsx_recycle_device_bridge_save',
                'menu_short_name' => '保存', 'menu_type' => '2', 'icon' => '',
                'api_url' => 'recycle/platform/device_bridge', 'methods' => 'post',
                'router_path' => '', 'view_path' => '', 'sort' => 1, 'status' => 1, 'is_show' => 0,
            ],
        ],
    ],
];
