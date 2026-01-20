<?php
return [
    'tk_jhkd_order_pay' => [
        'temp_key' => '43216',
        'content' => [
            ['下单时间', '{create_time}', 'time4'],
            ['订单编号', '{order_no}', 'character_string2'],
            ['商品信息', '{body}', 'thing3'],
            ['订单金额', '{order_money}', 'amount5']
        ],
        'keyword_name_list' => ["下单时间", "订单号", "商品名称", "支付金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：生活服务——>百货/超市/便利店'
    ],
    'tk_jhkd_order_pick' => [
        'temp_key' => '47049',
        'content' => [
            ['运单号', '{delivery_id}', 'character_string26'],
            ['当前状态', '{status_name}', 'thing5'],
        ],
        'keyword_name_list' => ["运单号", "当前状态"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：物流服务——>查件'
    ],
    'tk_jhkd_order_add' => [
        'temp_key' => '53509',
        'content' => [
            ['快速公司', '{delivery_type}', 'thing2'],
            ['快递单号', '{delivery_id}', 'character_string1'],
            ['补价价格', '{amount}', 'amount5'],
        ],
        'keyword_name_list' => ["快递公司", "快递单号", "补价价格"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：物流服务——>查件'
    ],
    'tk_jhkd_order_sign' => [
        'temp_key' => '57446',
        'content' => [
            'content' => [
                ['快速公司', '{delivery_type}', 'thing7'],
                ['快递单号', '{delivery_id}', 'character_string1'],
            ],
        ],
        'keyword_name_list' => ["快递公司", "快递单号"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：物流服务——>查件'
    ],
];
