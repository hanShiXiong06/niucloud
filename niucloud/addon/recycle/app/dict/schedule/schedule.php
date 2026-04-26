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
    ],
    [
        'key' => 'quotation_auto_request',
        'name' => '报价接口自动请求',
        'desc' => '根据配置自动请求报价接口获取最新价格数据',
        'time' => [
            'type' => 'day',
            'hour' => 0,  // 每天0点执行
            'min' => 0
        ],
        'class' => 'addon\recycle\app\job\quotation\QuotationAutoRequest',
        'function' => 'doJob'
    ]
]; 