<?php
return [
    'phone_shop_offline_order_status' => [
        'addon' => 'phone_shop',
        'key' => 'phone_shop_offline_order_status',
        'receiver_type' => 1,
        'name' => '商城线下订单状态提醒',
        'title' => '客户提交凭证、审核结果或锁单超时关闭时发送',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号', 'order_money' => '订单金额', 'body' => '商品信息',
            'create_time' => '下单时间', 'status_name' => '处理结果',
            'status_remark' => '处理说明', 'review_result' => '审核结果',
            'review_time' => '审核时间', 'review_content' => '审核内容',
            'review_reason' => '审核说明', 'url' => '订单链接',
        ],
    ],
    'phone_shop_offline_order_submitted' => [
        'addon' => 'phone_shop',
        'key' => 'phone_shop_offline_order_submitted',
        'receiver_type' => 1,
        'name' => '商城线下自提订单提交提醒',
        'title' => '客户提交线下支付订单后，告知到店位置和默认负责人',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号', 'order_money' => '订单金额', 'body' => '商品信息',
            'create_time' => '下单时间', 'handler_name' => '默认负责人',
            'handler_mobile' => '负责人电话', 'pickup_store' => '自提门店',
            'pickup_address' => '自提地址', 'contact_tip' => '联系说明', 'url' => '订单链接',
        ],
    ],
    'phone_shop_forward_application_approved' => [
        'addon' => 'phone_shop', 'key' => 'phone_shop_forward_application_approved',
        'receiver_type' => 1, 'name' => '同行转发申请通过通知',
        'title' => '同行身份审核通过并开通商品转发后发送', 'async' => true,
        'variable' => [
            'level_name' => '会员等级', 'review_result' => '审核结果',
            'review_reason' => '审核说明', 'review_time' => '审核时间', 'url' => '商城链接',
        ],
    ],
    'phone_shop_forward_application_rejected' => [
        'addon' => 'phone_shop', 'key' => 'phone_shop_forward_application_rejected',
        'receiver_type' => 1, 'name' => '同行转发申请未通过通知',
        'title' => '同行身份申请未通过时发送', 'async' => true,
        'variable' => [
            'level_name' => '申请等级', 'review_result' => '审核结果',
            'review_reason' => '审核说明', 'review_time' => '审核时间', 'url' => '商城链接',
        ],
    ],
    'phone_shop_goods_match' => [
        'addon' => 'phone_shop',
        'key' => 'phone_shop_goods_match',
        'receiver_type' => 1,
        'name' => '商品上新与调价订阅提醒',
        'title' => '分类上新或符合条件的商品调价时发送',
        'async' => true,
        'variable' => [
            'goods_name' => '商品名称',
            'goods_price' => '商品价格',
            'goods_count' => '上架商品数',
            'listing_time' => '上架时间',
            'brand_name' => '商品品牌',
            'notice_remark' => '通知备注',
            'supplier_name' => '供应商（当前站点名称）',
            'subscription_name' => '订阅条件',
            'change_type_name' => '变化类型',
            'change_summary' => '变化说明',
            'old_price' => '原价格',
            'new_price' => '新价格',
            'match_time' => '命中时间',
            'url' => '商品链接',
        ],
    ],
    'shop_order_pay' => [
        'addon' => 'phone_shop',
        'key' => 'shop_order_pay',
        'receiver_type' => 1,
        'name' => '商城订单支付成功通知',
        'title' => '订单支付成功后发送',
        'async' => true,
        'variable' => [
            'order_money' => '订单总额',
            'pay_time' => '支付时间',
            'create_time' => '支付时间',
            'body' => '订单内容',
            'order_no' => '订单编号',
            'url' => '订单链接'
        ],
    ],
    'admin_shop_order_pay' => [
        'addon' => 'phone_shop',
        'key' => 'admin_shop_order_pay',
        'receiver_type' => 0,
        'is_need_bind_merchant' => 1,//是否需要绑定商户通知接收者
        'name' => '【商户通知】商品售出通知',
        'title' => '订单支付成功后发送',
        'async' => true,
        'variable' => [
            'order_money' => '订单总额',
            'pay_time' => '支付时间',
            'create_time' => '支付时间',
            'body' => '订单内容',
            'order_no' => '订单编号',
            'url' => '订单链接'
        ],
    ],
    'phone_shop_order_delivery' => [
        'addon' => 'phone_shop',
        'key' => 'phone_shop_order_delivery',
        'receiver_type' => 1,
        'name' => '商城订单发货通知',
        'title' => '订单发货之后通知买家',
        'async' => true,
        'variable' => [
            'delivery_time' => '发货时间',
            'order_money' => '订单总额',
            'body' => '订单内容',
            'order_no' => '订单编号',
            'url' => '订单链接'
        ],
    ],
    'shop_refund_agree' => [
        'addon' => 'phone_shop',
        'key' => 'shop_refund_agree',
        'receiver_type' => 1,
        'name' => '商城商家同意退款申请',
        'title' => '商家同意买家退款申请后发送',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'refund_money' => '退款金额',
        ],
    ],
    'admin_shop_refund_agree' => [
        'addon' => 'phone_shop',
        'key' => 'admin_shop_refund_agree',
        'receiver_type' => 0,
        'is_need_bind_merchant' => 1,//是否需要绑定商户通知接收者
        'name' => '【商户通知】订单退款通知',
        'title' => '订单支付成功后发送',
        'async' => true,
        'variable' => [
            'order_no' => '退款编号',
            'refund_money' => '退款金额',
        ],
    ],
    'shop_refund_refuse' => [
        'addon' => 'phone_shop',
        'key' => 'shop_refund_refuse',
        'receiver_type' => 1,
        'name' => '商城商家拒绝退款申请',
        'title' => '商家拒绝买家退款申请后发送',
        'async' => true,
        'variable' => [
            'order_no' => '订单编号',
            'refund_money' => '退款金额',
        ]
    ],

];
