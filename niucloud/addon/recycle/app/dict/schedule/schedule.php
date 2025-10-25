<?php

return [
    [
        'key' => 'return_order_auto_complete',
        'name' => '退货订单自动完成',
        'desc' => '自动将退回的订单状态设置为完成',
        'time' => [
            'type' => 'min',
            'min' => 30  // 每30分钟执行一次
        ],
        'class' => 'addon\recycle\app\job\order_event\ReturnOrderAutoComplete',
        'function' => 'doJob'
    ]
]; 