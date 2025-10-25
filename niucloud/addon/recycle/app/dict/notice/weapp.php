<?php
return [
  
    'recycle_order_sign' => [
        'tid' => '31224',
        'content' => [
            ['订单编号', '{order_no}', 'character_string9'],
            ['温馨提示', '{remark}', 'thing5'],
        ],
        'kid_list' => [9,5], // 9 订单编号 5 温馨提示
        'scene_desc' => '快件签收',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],
    //回收订单下单通知
    /*
    订单编号:{{character_string1.DATA}}
    订单状态 :{{phrase2.DATA}}
    创建时间: {{time3.DATA}}
    邮寄地址: {{thing6.DATA}}
    温馨提示 : {{thing4.DATA}}

    订单编号:{{character_string1.DATA}}
    订单状态:{{phrase2.DATA}}
    创建时间:{{time3.DATA}}
    邮寄地址:{{thing6.DATA}}
    温馨提示:{{thing4.DATA}}
    */
    'recycle_order_add' => [
        'tid' => '30171',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['订单状态', '{status_name}', 'phrase2'],
            ['创建时间', '{create_time}', 'time3'],
            ['邮寄地址', '{address}', 'thing6'],
            ['温馨提示', '{remark}', 'thing4'],

        ],
        'kid_list' => [ 1,2,3,6,4 ], // 9 订单编号 2 订单状态 3 创建时间 6 邮寄地址 5 温馨提示
        'scene_desc' => '回收订单下单通知',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：环保回收/废品回收'
    ],
    /*
    37859
    回收价格确认提醒 {{thing1.DATA}}
    回收价格{{amount2.DATA}}
    时间{{time3.DATA}}
    状态 {{phrase4.DATA}}

    3C数码
    */
    'recycle_order_agree' => [
        'tid' => '37859',
        'content' => [
            ['时间', '{time}', 'time3'],
            ['状态', '{status}', 'phrase4'],
        ],
        'kid_list' => [ 2, 3, 4 ], // 2 回收价格 3 时间 4 状态
        'scene_desc' => '回收价格确认提醒',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商家自营 二级类目:3C数码'
    ],
    /* 20778 打款推送 
    商品名{{thing1.DATA}}
    订单号{{character_string2.DATA}}
    收款方式{{thing3.DATA}}
    收款账号{{thing4.DATA}}
    打款结果{{thing5.DATA}} */
    'recycle_order_pay' => [
        'tid' => '20778',
        'content' => [
            ['商品名', '{goods_name}', 'thing1'],
            ['订单编号', '{order_no}', 'character_string2'],
            ['收款方式', '{pay_type}', 'thing3'],
            ['收款账号', '{pay_account}', 'thing4'],
            ['打款结果', '{pay_result}', 'thing5'],
            
        ],
        'kid_list' => [ 1, 2, 3, 4, 5 ], //1 商品名  2 订单编号 3 收款方式 4 收款账号 5 打款结果 
        'scene_desc' => '打款推送',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商家自营 二级类目:3C数码'
    ],


];
