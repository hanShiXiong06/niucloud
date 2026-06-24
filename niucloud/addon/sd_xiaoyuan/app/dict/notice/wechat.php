<?php
return [
    'sd_xiaoyuan_order_pay_runner' => [
        'temp_key' => '42995',
        'content' => [
            ['订单金额', '{order_money}', 'amount3'],
            ['服务名称', '{service_name}', 'thing9'],
            ['配送地址', '{delivery_address}', 'thing15'],
            ['下单时间', '{create_time}', 'time5'],
        ],
        'keyword_name_list' => ['订单金额', '服务名称', '配送地址', '下单时间'],
        'tips' => '使用该消息请将微信公众号服务类目选择为：跑腿',
    ],
    'sd_xiaoyuan_runner_apply' => [
        'temp_key' => '62977',
        'content' => [
            ['申请姓名', '{real_name}', 'thing1'],
            ['联系电话', '{mobile}', 'phone_number2'],
            ['申请时间', '{apply_time}', 'time3'],
        ],
        'keyword_name_list' => ['申请姓名', '联系电话', '申请时间'],
        'tips' => '使用该消息请将微信公众号服务类目选择为：跑腿',
    ],
    'sd_xiaoyuan_order_accept_user' => [
        'temp_key' => '47140',
        'content' => [
            ['服务类型', '{service_type}', 'phrase2'],
            ['跑手姓名', '{runner_name}', 'thing3'],
            ['跑手电话', '{runner_mobile}', 'phone_number6'],
            ['接单时间', '{accept_time}', 'time8'],
        ],
        'keyword_name_list' => ['服务类型', '跑手姓名', '跑手电话', '接单时间'],
        'tips' => '使用该消息请将微信公众号服务类目选择为：跑腿',
    ],
];
