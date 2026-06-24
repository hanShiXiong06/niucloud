<?php
return [
    'sd_xiaoyuan_order_pay_runner' => [
        'tid' => '32270',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['订单时间', '{create_time}', 'time4'],
            ['服务类型', '{service_type}', 'thing12'],
            ['跑腿费用', '{order_amount}', 'amount28'],
        ],
        'kid_list' => [1, 4, 12, 28],
        'scene_desc' => '跑腿通知接单员',
        'tips' => '小程序后台添加「新订单提醒」，类目选跑腿',
    ],
    'sd_xiaoyuan_order_accept_user' => [
        'tid' => '33722',
        'content' => [
            ['订单号', '{order_no}', 'character_string1'],
            ['任务名称', '{task_name}', 'thing12'],
            ['接单人员', '{runner_name}', 'thing13'],
            ['接单时间', '{accept_time}', 'time14'],
        ],
        'kid_list' => [1, 12, 13, 14],
        'scene_desc' => '接单通知用户',
        'tips' => '小程序后台添加「订单接单通知」，类目选跑腿',
    ],
    'sd_xiaoyuan_order_cancel_user' => [
        'tid' => '33674',
        'content' => [
            ['订单号', '{order_no}', 'character_string1'],
            ['取消时间', '{cancel_time}', 'time8'],
            ['操作人', '{operator}', 'thing5'],
            ['取消原因', '{cancel_reason}', 'thing9'],
        ],
        'kid_list' => [1, 8, 5, 9],
        'scene_desc' => '取消订单通知',
        'tips' => '小程序后台添加「订单取消通知」，类目选跑腿',
    ],
];
