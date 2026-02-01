<?php
return [
    'phone_shop_order_pay' => [
        'tid' => '30808',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['下单时间', '{create_time}', 'time2'],
            ['商品名称', '{body}', 'thing4'],
            ['订单金额', '{order_money}', 'amount3'],
        ],
        'kid_list' => [1, 2, 4, 3],
        'scene_desc' => '订单支付之后通知',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],
    'phone_shop_order_delivery' => [
        'tid' => '30766',
        'content' => [
            ['订单编号', '{order_no}', 'character_string2'],
            ['商品名称', '{body}', 'thing1'],
            ['订单金额', '{order_money}', 'amount7'],
            ['发货时间', '{delivery_time}', 'date3'],
        ],
        'kid_list' => [2, 1, 7, 3],
        'scene_desc' => '订单发货通知',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],
    'phone_shop_refund_agree' => [
        'tid' => '30825',
        'content' => [
            ['订单编号', '{order_no}', 'character_string3'],
            ['退款金额', '{refund_money}', 'amount1'],
            ['申请结果', '{result}', 'phrase7'],
        ],
        'kid_list' => [3, 1, 7],
        'scene_desc' => '商家同意退款',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],
    'phone_shop_refund_refuse' => [
        'tid' => '30825',
        'content' => [
            ['订单编号', '{order_no}', 'character_string3'],
            ['退款金额', '{refund_money}', 'amount1'],
            ['申请结果', '{result}', 'phrase7'],
        ],
        'kid_list' => [3, 1, 7],
        'scene_desc' => '商家拒绝退款',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],
    'phone_shop_new_goods' => [
        'tid' => '', // 需要去小程序后台申请订阅消息模板ID，推荐使用"商品上新提醒"模板
        'content' => [
            ['商品名称', '{goods_names}', 'thing1'],
            ['上架时间', '{update_time}', 'time2'],
            ['温馨提示', '快来抢购吧', 'thing3'],
        ],
        'kid_list' => [1, 2, 3],
        'scene_desc' => '新商品上架时通知用户',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发，推荐使用"商品上新提醒"模板'
    ]
];
