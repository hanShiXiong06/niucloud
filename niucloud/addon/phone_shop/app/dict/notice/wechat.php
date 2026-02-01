<?php
return [
    'phone_shop_order_pay' => [
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
    'phone_shop_refund_agree' => [
        'temp_key' => '48058',
        'content' => [
            ['订单编号', '{order_no}', 'character_string5'],
            ['退款金额', '{refund_money}', 'amount2'],
        ],
        'keyword_name_list' => ["订单编号", "退款金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
    'phone_shop_refund_refuse' => [
        'temp_key' => '49580',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['退款金额', '{refund_money}', 'amount2']
        ],
        'keyword_name_list' => ["订单编号", "退款金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
    /*
    调拨件数 {{character_string1.DATA}}
    调拨商品 {{thing3.DATA}}
    调拨门店 {{thing4.DATA}}
    调拨时间 {{time2.DATA}}
    */
    'phone_shop_new_goods' => [
        'temp_key' => '64312', // 需要去微信公众平台申请模板ID，推荐使用"商品上新提醒"模板
        'content' => [
            // ['商品名称', '{goods_names}', 'thing2'],
            // ['上架时间', '{update_time}', 'time3'],
            // ['商品数量', '{goods_count}', 'number4'],
            ['调拨商品', '最新上架的商品', 'thing3'],
            ['调拨件数', '{goods_count}', 'character_string1'],
            ['调拨门店', '{site_name}', 'thing4'],
            ['调拨时间', '{update_time}', 'time2'],
        ],
        // 'keyword_name_list' => ["商品名称", "上架时间", "商品数量"],
        'keyword_name_list' => ["调拨商品", "调拨件数", "调拨门店", "调拨时间"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：生活服务——>百货/超市/便利店，推荐使用"商品上新提醒"模板'
    ],
];
