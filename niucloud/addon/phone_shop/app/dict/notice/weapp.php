<?php
return [
    'phone_shop_offline_order_submitted' => [
        'tid' => '3480',
        'content' => [
            ['审核结果', '{review_result}', 'thing1'],
            ['审核时间', '{review_time}', 'date3'],
            ['审核内容', '{review_content}', 'thing4'],
            ['备注', '{review_reason}', 'thing5'],
        ],
        'kid_list' => [1, 3, 4, 5],
        'scene_desc' => '线下订单提交及锁单提醒',
        'tips' => '客户提交线下订单时授权，用于提醒及时联系门店付款。',
    ],
    'phone_shop_offline_order_status' => [
        'tid' => '3480',
        'content' => [
            ['审核结果', '{review_result}', 'thing1'],
            ['审核时间', '{review_time}', 'date3'],
            ['审核内容', '{review_content}', 'thing4'],
            ['备注', '{review_reason}', 'thing5'],
        ],
        'kid_list' => [1, 3, 4, 5],
        'scene_desc' => '线下订单凭证审核及关闭提醒',
        'tips' => '请在小程序订阅消息中启用“审核结果通知”，用于线下订单状态提醒。',
    ],
    'phone_shop_goods_match' => [
        'tid' => '51261',
        'content' => [
            ['上架商品数', '{goods_count}', 'number1'],
            ['上架时间', '{listing_time}', 'time2'],
            ['品牌', '{brand_name}', 'thing3'],
            ['备注', '{notice_remark}', 'thing4'],
            ['供应商', '{supplier_name}', 'thing5'],
        ],
        'kid_list' => [1, 2, 3, 4, 5],
        'scene_desc' => '新商品上架提醒',
        'tips' => '使用编号 51261 的“新商品上架提醒”：上架商品数、上架时间、品牌、备注、供应商；保留本站原模板 ID。',
    ],
    /*
     * 标题
      模版ID
        vwaU4N124xPTuAmUzBK1kRFJ0LlW9WwyqF9A0PaH4dE
        模版编号
        3480
        标题
        审核结果通知
        类目
        报价/比价
        操作人
        hs****sx 2026-08-11 添加
        详细内容
        审核结果
        {{thing1.DATA}}
        审核时间
        {{date3.DATA}}
        审核内容
        {{thing4.DATA}}
        备注
        {{thing5.DATA}}
        场景说明
        部分场景审核通过提醒
     */
    'phone_shop_forward_application_approved' => [
        'tid' => '3480',
        'content' => [
            ['审核结果', '{review_result}', 'thing1'],
            ['审核时间', '{review_time}', 'date3'],
            ['审核内容', '{level_name}', 'thing4'],
            ['备注', '{review_reason}', 'thing5'],
        ],
        'kid_list' => [1, 3, 4, 5],
        'scene_desc' => '同行申请通过',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：报价/比价'
    ],
    /*
    模版ID
        qGfxkVprSoTVwcuyv-3Lic12Pxf0LHl1GClHAP7md2I
        模版编号
        24318
        标题
        申请驳回提醒
        类目
        信息查询
        操作人
        hs****sx 2026-08-11 添加
        详细内容
        驳回时间
        {{time1.DATA}}
        温馨提示
        {{thing3.DATA}}
        场景说明
        申请部分权益拒绝通知
        */ 
    'phone_shop_forward_application_rejected' => [
        'tid' => '24318',
        'content' => [
            ['驳回时间', '{review_time}', 'time1'],
            ['温馨提示', '{review_reason}', 'thing3'],
        ],
        'kid_list' => [1, 3],
        'scene_desc' => '同行申请驳回',
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
    'shop_refund_agree' => [
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
    'shop_refund_refuse' => [
        'tid' => '30825',
        'content' => [
            ['订单编号', '{order_no}', 'character_string3'],
            ['退款金额', '{refund_money}', 'amount1'],
            ['申请结果', '{result}', 'phrase7'],
        ],
        'kid_list' => [3, 1, 7],
        'scene_desc' => '商家拒绝退款',
        'tips' => '使用该消息请在小程序的服务类目中添加类目：一级类目：商业服务 二级类目：软件/建站/技术开发'
    ]
];
