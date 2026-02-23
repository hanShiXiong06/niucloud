<?php
return [
    // 用户下单成功通知
    'recycle_order_add' => [
        'temp_key' => '46624',
        'content' => [
            ['订单号', '{order_no}', 'character_string1'],
            ['下单门店', '{shop_name}', 'thing6'],
            ['商品名称', '{address}', 'thing2'],
            ['下单时间', '{create_time}', 'time9'],
        ],
        'keyword_name_list' => ["订单号", "下单门店", "商品名称", "下单时间"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：百货/超市/便利店'
    ],
    /*
    订单号
{{character_string1.DATA}}
下单时间
{{time9.DATA}}
下单门店
{{thing6.DATA}}
商品名称
{{thing2.DATA}}*/
    // 收款成功通知
    'recycle_order_pay' => [
        'temp_key' => '47568',
        'content' => [
            ['订单编号', '{order_no}', 'character_string4'],
            ['收款共计', '{pay_amount}', 'amount10'],
            ['门店', '{shop_name}', 'thing13'],
            ['支付时间', '{pay_time}', 'time3'],
        ],
        'keyword_name_list' => ["订单编号", "收款共计", "门店", "支付时间"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：百货/超市/便利店'
    ],

    // 订单验收通知（待确认）
    'recycle_order_agree' => [
        'temp_key' => '49839',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['商品名称', '{goods_name}', 'thing13'],
            ['订单金额', '{order_amount}', 'amount11'],
            ['创建时间', '{create_time}', 'time6'],
            ['审核人员', '{auditor}', 'thing15'],
        ],
        'keyword_name_list' => ["订单编号", "商品名称", "订单金额", "创建时间", "审核人员"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：软件/建站/技术开发'
    ],
];
