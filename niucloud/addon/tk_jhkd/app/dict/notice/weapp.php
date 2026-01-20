<?php
return [
    'tk_jhkd_order_pay' => [
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
    'tk_jhkd_order_pick' => [
        'tid' => '25608',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['订单状态', '{status_name}', 'phrase2'],
            ['温馨提示', '{remark}', 'thing3'],
        ],
        'kid_list' => [1, 2, 3],
        'scene_desc' => '取件成功',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：生活服务 二级类目：百货/超市/便利店'
    ],
    'tk_jhkd_order_add' => [
        'tid' => '1979',
        'content' => [
            ['订单编号', '{order_no}', 'character_string11'],
            ['订单状态', '{amount}', 'amount8'],
            ['温馨提示', '{remark}', 'thing7'],
        ],
        'kid_list' => [11, 8, 7],
        'scene_desc' => '补差价提醒',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：工具 二级类目：信息查询'
    ],
    'tk_jhkd_order_sign' => [
        'tid' => '31224',
        'content' => [
            ['订单编号', '{order_no}', 'character_string9'],
            ['温馨提示', '{remark}', 'thing5'],
        ],
        'kid_list' => [9,5],
        'scene_desc' => '快件签收',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],

];
