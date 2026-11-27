<?php

return [
    'hsx_phone_query_success' => [
        'addon' => 'hsx_phone_query',
        'key' => 'hsx_phone_query_success',
        'receiver_type' => 1,
        'name' => '设备查询完成通知',
        'title' => '设备查询完成后通知用户查看报告',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'result_text' => '查询结果',
            'service_name' => '查询项目',
            'query_count' => '查询数量',
            'success_count' => '成功数量',
            'fail_count' => '失败数量',
            'refund_text' => '退款说明',
            'pay_text' => '支付说明',
            'finish_time' => '完成时间',
            'url' => '查询记录链接',
        ],
    ],
    'hsx_phone_query_fail' => [
        'addon' => 'hsx_phone_query',
        'key' => 'hsx_phone_query_fail',
        'receiver_type' => 1,
        'name' => '设备查询失败通知',
        'title' => '设备查询失败并退款后通知用户',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'result_text' => '查询结果',
            'service_name' => '查询项目',
            'query_count' => '查询数量',
            'fail_reason' => '失败原因',
            'refund_text' => '退款说明',
            'finish_time' => '完成时间',
            'url' => '查询记录链接',
        ],
    ],
];
