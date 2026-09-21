<?php

return [
    [
        'key' => 'return_order_auto_complete',
        'name' => '退货订单自动完成',
        'desc' => '确认退回发货满72小时自动完成，处理记录与人工签收区分',
        'time' => [
            'type' => 'min',
            'min' => 30
        ],
        'class' => 'addon\hsx_recycle\app\job\order_event\ReturnOrderAutoComplete',
        'function' => 'doJob'
    ],
    [
        'key' => 'quote_daily_snapshot',
        'name' => '报价单每日自动快照',
        'desc' => '每天自动将当前分类报价单同步到历史表，确保每天都有一条快照记录',
        'time' => [
            'type' => 'day',
            'day' => 1,
            'hour' => 23,
            'min' => 0
        ],
        'class' => 'addon\hsx_recycle\app\job\schedule\QuoteDailySnapshot',
        'function' => 'doJob'
    ]
];
