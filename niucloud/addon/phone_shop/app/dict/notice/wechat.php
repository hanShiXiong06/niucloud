<?php
return [
    'phone_shop_offline_order_status' => [
        'temp_key' => '43216',
        'content' => [
            ['下单时间', '{create_time}', 'time4'],
            ['订单编号', '{order_no}', 'character_string2'],
            ['商品信息', '{body}', 'thing3'],
            ['订单金额', '{order_money}', 'amount5'],
        ],
        'keyword_name_list' => ['下单时间', '订单号', '商品名称', '支付金额'],
        'tips' => '订单处理结果会写入商品信息/跳转详情；请在牛云通知设置中启用该公众号模板。',
    ],
    'phone_shop_goods_match' => [
        'temp_key' => '',
        'content' => [
            ['商品名称', '{goods_name}', 'thing1'],
            ['变动类型', '{change_type_name}', 'phrase2'],
            ['变动说明', '{change_summary}', 'thing3'],
            ['当前价格', '{goods_price}', 'amount4'],
            ['提醒时间', '{match_time}', 'time5'],
        ],
        'keyword_name_list' => ['商品名称', '变动类型', '变动说明', '当前价格', '提醒时间'],
        'tips' => '公众号模板消息需在微信公众平台申请商品上新/价格变动类模板，并在牛云通知设置中填写模板 ID、配置对应关键词。',
    ],
    'phone_shop_offline_order_submitted' => [
        'temp_key' => '43216',
        'content' => [
            ['下单时间', '{create_time}', 'time4'],
            ['订单编号', '{order_no}', 'character_string2'],
            ['商品信息', '{body}', 'thing3'],
            ['订单金额', '{order_money}', 'amount5']
        ],
        'keyword_name_list' => ['下单时间', '订单号', '商品名称', '支付金额'],
        'tips' => '使用该消息请将微信公众号服务类目选择为：生活服务——>百货/超市/便利店'
    ],
    'shop_order_pay' => [
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
    'admin_shop_order_pay' => [
        'temp_key' => '57759',
        'content' => [
            ['商品名称', '{body}', 'thing2'],
            ['成交金额', '{order_money}', 'amount1'],
            ['成交时间', '{create_time}', 'time3']
        ],
        'keyword_name_list' => ["商品名称","成交金额", "成交时间"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：生活服务——>百货/超市/便利店'
    ],
    'phone_shop_order_delivery' => [
        'temp_key' => '42984',
        'content' => [
            ['订单编号', '{order_no}', 'character_string2'],
            ['商品名称', '{body}', 'thing4'],
            ['发货时间', '{delivery_time}', 'time12']
        ],
        'keyword_name_list' => ["订单编号", "商品名称", "发货时间"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：生活服务——>百货/超市/便利店'
    ],
    'shop_refund_agree' => [
        'temp_key' => '48058',
        'content' => [
            ['订单编号', '{order_no}', 'character_string5'],
            ['退款金额', '{refund_money}', 'amount2'],
        ],
        'keyword_name_list' => ["订单编号", "退款金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
    'admin_shop_refund_agree' => [
        'temp_key' => '45762',
        'content' => [
            ['退款单号', '{order_no}', 'character_string13'],
            ['退款金额', '{refund_money}', 'amount2'],
        ],
        'keyword_name_list' => ["退款单号", "退款金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
    'shop_refund_refuse' => [
        'temp_key' => '49580',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['退款金额', '{refund_money}', 'amount2']
        ],
        'keyword_name_list' => ["订单编号", "退款金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
];
