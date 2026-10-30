<?php
declare(strict_types=1);

/**
 * Every sensitive operator route is explicitly registered. Merchant workbenches
 * use member-token API authentication, never admin roles or operator menus.
 */
$permission = static function (string $key, string $name, string $url, string $method): array {
    return [
        'menu_name' => $name,
        'menu_key' => $key,
        'menu_short_name' => $name,
        'parent_select_key' => '',
        'menu_type' => '2',
        'icon' => '',
        'api_url' => 'hsx_lead/' . $url,
        'router_path' => '',
        'view_path' => '',
        'methods' => $method,
        'sort' => '100',
        'status' => '1',
        'is_show' => '0',
    ];
};

$managePermissions = [
    ['catalog', '型号需求清单', 'catalog', 'get'],
    ['catalog_save', '维护型号需求清单', 'catalog', 'put'],
    ['members', '可绑定本站会员', 'members', 'get'],
    ['providers', 'ERP接入方式', 'providers', 'get'],
    ['connection', '读取ERP连接', 'merchants/<id>/connection', 'get'],
    ['connection_probe', '探测ERP授权及库存', 'merchants/<id>/connection/probe', 'post'],
    ['connection_activate', '确认激活ERP连接', 'merchants/<id>/connection/activate', 'post'],
    ['connection_settings', '设置库存同步周期', 'merchants/<id>/connection/settings', 'put'],
    ['notice_config', '读取工单通知配置', 'notice/config', 'get'],
    ['notice_config_save', '维护工单通知配置', 'notice/config', 'put'],
    ['merchants', '商户列表', 'merchants', 'get'],
    ['merchant_info', '商户详情', 'merchants/<id>', 'get'],
    ['merchant_add', '新增商户资料', 'merchants', 'post'],
    ['merchant_edit', '修改商户资料', 'merchants/<id>', 'put'],
    ['merchant_sync', '手动同步库存', 'merchants/<id>/sync', 'post'],
    ['sync_info', '同步进度详情', 'sync/<id>', 'get'],
    ['sync_advance', '继续分批同步库存', 'sync/<id>/advance', 'post'],
    ['inventory', '已发布库存', 'inventory', 'get'],
    ['leads', '客资列表', 'leads', 'get'],
    ['lead_info', '客资详情', 'leads/<id>', 'get'],
    ['lead_matches', '客资地区库存匹配', 'leads/<id>/matches', 'get'],
    ['lead_add', '录入客资与联系同意', 'leads', 'post'],
    ['lead_edit', '修改客资', 'leads/<id>', 'put'],
    ['lead_assign', '派单与重派', 'leads/<id>/assign', 'post'],
    ['workorders', '全部派单列表', 'workorders', 'get'],
    ['workorder_info', '派单详情', 'workorders/<id>', 'get'],
    ['workorder_notice', '工单通知状态', 'workorders/<id>/notice', 'get'],
    ['workorder_notice_retry', '重试工单通知', 'workorders/<id>/notice/retry', 'post'],
    ['workorder_accept', '接单', 'workorders/<id>/accept', 'post'],
    ['workorder_reject', '退回派单', 'workorders/<id>/reject', 'post'],
    ['workorder_follow', '登记跟进', 'workorders/<id>/follow', 'post'],
    ['workorder_deal', '申报成交', 'workorders/<id>/deal', 'post'],
    ['workorder_verify', '核验成交与登记佣金', 'workorders/<id>/verify', 'post'],
    ['workorder_close', '关闭工单', 'workorders/<id>/close', 'post'],
    ['workorder_cancel', '撤销派单', 'workorders/<id>/cancel', 'post'],
    ['commissions', '佣金台账', 'commissions', 'get'],
    ['commission_receive', '登记收到佣金', 'commissions/<id>/receive', 'post'],
    ['commission_refund', '登记佣金退款', 'commissions/<id>/refund', 'post'],
];
return [[
    'menu_name' => '客资协作',
    'menu_key' => 'hsx_lead',
    'menu_short_name' => '客资协作',
    'parent_select_key' => '',
    'parent_key' => 'addon',
    'menu_type' => '0',
    'icon' => 'nc-iconfont nc-icon-yingxiao',
    'api_url' => '',
    'router_path' => '',
    'view_path' => '',
    'methods' => '',
    'sort' => '165',
    'status' => '1',
    'is_show' => '1',
    'children' => [
        [
            'menu_name' => '商户客资管理',
            'menu_key' => 'hsx_lead_manage',
            'menu_short_name' => '客资管理',
            'parent_select_key' => '',
            'menu_type' => '1',
            'icon' => '',
            'api_url' => 'hsx_lead/dashboard',
            'router_path' => 'hsx_lead/workbench',
            'view_path' => 'workbench/index',
            'methods' => 'get',
            'sort' => '100',
            'status' => '1',
            'is_show' => '1',
            'children' => array_map(
                static fn (array $row): array => $permission('hsx_lead_manage_' . $row[0], $row[1], $row[2], $row[3]),
                $managePermissions
            ),
        ],
    ],
]];

