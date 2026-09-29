<?php
return [
    'hsx_recycle_pickup_update' => [
        // 不猜测公共模板编号。管理员填写本小程序真实模板 ID 与字段映射后才可启用。
        'tid' => '',
        'kid_list' => [],
        'is_need_closure_content' => 1,
        'content' => static fn(array $context) => \addon\hsx_recycle\app\service\core\recycle_order\PickupNoticeConfigService::templateContent('weapp', $context),
        'scene_desc' => '回收预约取件状态更新',
        'tips' => '请在回收快递配置的预约通知设置中填写真实模板 ID 和字段映射；用户须主动订阅。发送受理不代表已读。',
    ],
    'recycle_order_sign' => [
        'tid' => '31224',
        'content' => [
            ['订单编号', '{order_no}', 'character_string9'],
            ['温馨提示', '{remark}', 'thing5'],
        ],
        'kid_list' => [9, 5],
        'scene_desc' => '快件签收',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ],

    // 回收订单下单通知
    'recycle_order_add' => [
        'tid' => '30171',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['订单状态', '{status_name}', 'phrase2'],
            ['创建时间', '{create_time}', 'time3'],
            ['邮寄地址', '{address}', 'thing6'],
            ['温馨提示', '{remark}', 'thing4'],
        ],
        'kid_list' => [1, 2, 3, 6, 4],
        'scene_desc' => '回收订单下单通知',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：环保回收/废品回收'
    ],

    // 回收价格确认提醒 - 变量与 OrderAgree listener 保持一致
    'recycle_order_agree' => [
        'tid' => '37859',
        'content' => [
            ['时间', '{time}', 'time3'],
            ['状态', '{status}', 'phrase4'],
        ],
        'kid_list' => [2, 3, 4],
        'scene_desc' => '回收价格确认提醒',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商家自营 二级类目:3C数码'
    ],

    // 打款推送 - 变量与 OrderPay listener 保持一致
    'recycle_order_pay' => [
        'tid' => '20778',
        'content' => [
            ['订单编号', '{order_no}', 'character_string2'],
            ['收款方式', '{pay_type}', 'thing3'],
            ['收款账号', '{pay_account}', 'thing4'],
            ['打款结果', '{pay_result}', 'thing5'],
        ],
        'kid_list' => [2, 3, 4, 5],
        'scene_desc' => '打款推送',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：报价 二级类目:比价'
    ],
    // 订单完成积分奖励通知
    'recycle_order_reward' => [
        'tid' => '30939',
        'content' => [
            ['活动名称', '订单完成奖励', 'thing6'],
            ['奖励内容', '{reward_point}积分', 'thing4'],
            ['备注', '{remark}', 'thing5'],
        ],
        'kid_list' => [6, 4, 5],
        'scene_desc' => '订单生效奖励',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：软件/建站/技术开发'
    ],
];
