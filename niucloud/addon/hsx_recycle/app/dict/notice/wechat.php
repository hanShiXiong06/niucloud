<?php
return [
    // 订单签收通知
    'recycle_order_sign' => [
        'temp_key' => '62949',
        'content' => [
            ['签收时间', '{sign_time}', 'time5'],
            ['订单号', '{order_no}', 'character_string2'],
            ['服务项目', '{remark}', 'thing7'],
        ],
        'keyword_name_list' => ["签收时间", "订单号", "服务项目"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：软件/建站/技术开发'
    ],

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

    // 打款成功通知 - 变量与 OrderPay listener 保持一致
    'recycle_order_pay' => [
        'temp_key' => '47568',
        'content' => [
            ['订单编号', '{order_no}', 'character_string4'],
            ['收款方式', '{pay_type}', 'thing13'],
            ['打款时间', '{pay_time}', 'time3'],
        ],
        'keyword_name_list' => ["订单编号", "收款方式", "打款时间"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：百货/超市/便利店'
    ],

    // 订单验收通知（待确认）- 变量与 OrderAgree listener 保持一致
    'recycle_order_agree' => [
        'temp_key' => '49839',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['创建时间', '{time}', 'time6'],
            ['审核状态', '{status}', 'thing15'],
        ],
        'keyword_name_list' => ["订单编号", "创建时间", "审核状态"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：软件/建站/技术开发'
    ],
    // 订单完成积分奖励通知
    'recycle_order_reward' => [
        'temp_key' => '',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['完成时间', '{complete_time}', 'time2'],
            ['奖励积分', '{reward_point}', 'number3'],
            ['温馨提示', '{remark}', 'thing4'],
        ],
        'keyword_name_list' => ['订单编号', '完成时间', '奖励积分', '温馨提示'],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商家自营/3C数码'
    ],
];
