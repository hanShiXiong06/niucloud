<?php

return [
    'dict_home_service_card_valid' => [
        'monthly_card' => '月卡',
        'season_card' => '季卡',
        'year_card' => '年卡',
        'permanent_card' => '永久'
    ],
    'dict_home_service_goods' => [
        'up' => '已上架',
        'down' => '已下架',
        'reservation' => '预约',
        'buy' => '一口价',
    ],
    'dict_home_service_technician_status' => [
        'status_on' => '工作中',
        'status_off' => '休息中',
        'status_depart' => '已离职'
    ],
    'dict_home_service_technician_application_status' => [
        'pending_examine' => '待审核',
        'pass' => '通过',
        'refuse' => '拒绝'
    ],
    'dict_home_service_technician_distribute' => [
        'default' => '默认',
        'customize' => '自定义',
    ],
    //师傅来源
    'dict_home_service_technician_source_name' => [
        'internal' => '内部',
        'application' => '入驻',
    ],

    'dict_home_service_store_application_status' => [
        'pending_examine' => '待审核',
        'pass' => '通过',
        'refuse' => '拒绝'
    ],

    /*订单状态*/
    'dict_home_service_order_status' => [
        'wait_pay' => '待支付',
        'wait_dispatch' => '待派单',
        'wait_service' => '待服务',
        'in_service' => '服务中',
        'in_progress' => '进行中',
        'wait_check' => '待验收',
        'abnormal_order' => '异常订单',
        'order_finish' => '已完成',
        'order_close' => '已关闭'
    ],


    /*订单操作*/
    'dict_home_service_order_action' => [


        'action_offline_payment' => '线下支付',
        'action_dispatch' => '派单',
        'action_transfer' => '重新派单',
        'again_check' => '确认验收',  // 会员 +  系统
        'action_contact_technician' => '联系师傅',
        'action_pay' => '去支付',
        'action_cancel' => '取消订单',
        'action_delete' => '删除订单',
        'action_sub_depart' => '出发',
        'action_sub_photo_taken' => '打卡',
        'action_start' => '开始服务',
        'action_save_check' => '服务完成',
        'action_check' => '订单验收',
        'action_order_creation' => '订单创建',
        'action_follow' => '回访',
        'action_refund' => '退款/售后',
        'action_order_again' => '再来一单',
        'action_order_review_share' => '评价晒单',
        'action_reminder' => '催单',
        'action_item_pay' => '增项支付',
        'edit_reserve_service_time' => '修改预约时间',
        'action_order_item' => '附加服务',
        //        'transfer_order' => '转单',
        //        'service_pay' => '师傅报单',
        //        'item_pay' => '去支付',
        //        'action_dispatch' => '派单',
        //
        //        'finish' => '完成订单',
        //        'action_delete' => '删除订单'

    ],


    'dict_home_service_order_log' => [
        'order_create' => '订单提交成功',
        'order_pay' => '订单支付成功',
        'order_overtime' => '订单超时未完成支付，已自动关闭',
        'order_cancel' => '订单已取消',
        'order_dispatch' => '订单派单',
        'order_grab' => '订单抢单',
        'order_depart' => '师傅出发',
        'order_photo_taken' => '师傅拍照',
        'order_service' => '订单开始服务',
        'order_save_check' => '订单提交验收',
        'order_check' => '订单验收',


        'order_transfer' => '订单转单',
        'order_add_pay' => '师傅报单',
        'order_del_pay' => '师傅删除报单',
        'order_edit_pay' => '师傅修改报单',
        'order_item_pay' => '客户服务项支付',
        'order_status_finish' => '订单完成',
        'order_refund' => '订单退款',
    ],


    'dict_home_service_card_order_status' => [
        'wait_pay' => '待支付',
        'wait_use' => '待使用',
        'order_finish' => '已完成',
        'order_close' => '已关闭'
    ],

    'dict_home_service_card_order_action' => [
        'action_pay' => '去支付',
        'action_cancel' => '取消订单',
        'action_delete' => '删除订单'
    ],

    'dict_home_service_goods_evaluate' => [
        'audit_no' => '无需审核',
        'audit' => '待审核',
        'audit_adopt' => '审核通过',
        'audit_refuse' => '审核拒绝'
    ],

    'dict_home_service_membercard_status' => [
        'wait_use' => '待使用',
        'used' => '已使用',
        'expire' => '已过期',
    ],

    'dict_home_service_coupon' => [
        'user' => '手动领取',
        'grant' => '后台或活动发放',
        'all' => '通用券',
        'category' => '品类券',
        'goods' => '商品券',
        'wait_start' => '未开始',
        'normal' => '进行中',
        'expire' => '已过期',
        'invalid' => '已失效',
    ],
    'dict_home_service_member_coupon' => [
        'wait_use' => '待使用',
        'used' => '已使用',
        'expire' => '已过期',
        'invalid' => '已失效',
        'send' => '后台发放',
        'receive' => '用户手动领取'
    ],
    'dict_home_service_send_coupon_range' => [//后台发送优惠券范围类型
        'all' => '全部会员',
        'member' => '选择会员',
        'member_level' => '按会员等级',
        'member_label' => '按会员标签',
    ],
    'dict_home_service_send_coupon_range_type_desc' => [//后台发送优惠券范围类型描述
        'all' => '全部会员',
        'member' => '指定会员',
        'member_level' => '指定会员等级',
        'member_label' => '指定会员标签',
    ],
    'dict_home_service_send_coupon_status' => [//后台发送优惠券状态
        'wait' => '待发放',
        'progress' => '发放中',
        'finish' => '发放完成',
    ],


    "dict_home_service_account" => [
        'order_commission' => '订单佣金',
        'order_refund_commission' => '订单退款佣金',
        'cash_out' => '佣金提现',
    ],

    "dict_home_service_account_status" => [
        'pending_settlement' => '待结算',
        'settled' => '已结算',
    ],


    'dict_home_service_order_refund_log' => [
        'apply' => '退款申请提交成功',
        'agree' => '退款申请商家已同意',
        'refuse' => '商家拒绝了此次退款申请',
        'cancel' => '已取消了退款申请',
        'completed' => '退款已到账，售后结束',
        'refund' => '商家确认退款，退款处理中'
    ],

    'dict_home_service_order_refund' => [
        'wait_audit' => '待审核',
        'wait_refund' => '待退款',
        'refunding' => '转账中',
        'refund_completed' => '退款完成',
        'refund_refuse' => '退款被拒',
        'refund_fail' => '转账失败',
        'cancel' => '取消退款'
    ],

    'dict_home_service_order_refund_reason' => [
        'service_not_completed_agreed' => '服务未按约定完成',
        'service_quality_not_meet' => '服务质量不达标',
        'item_price_unreasonable' => '额外收费不合理',
        'service_personnel_attitude_problem' => '服务人员态度问题',
        'other_reasons' => '其他原因',
    ],

    'dict_home_service_technician_notice_title' => [
        'grab_success' => '抢单成功',
        'dispatch_success' => '派单成功',
        'about_to_timeout' => '即将超时',
        'timeout' => '已超时',
        'item_pay_success' => '附加项支付成功',
        'refund' => '发起售后',
        'refund_success' => '退款成功',
        'refund_fail' => '退款失败',
        'audit_pass' => '审核通过',
        'commission_credited' => '佣金到账',
        'cash_out_success' => '提现成功',
        'reminder' => '催单',
    ],

    'dict_home_service_invoice_type' => [
        'electron_regular_invoice' => '电子普通发票',
        'electron_vat_invoice' => '电子增值税专用发票',
    ],

    'dict_home_service_invoice_content' => [
        'service_details' => '服务明细',
    ],

    'dict_home_service_invoice_header_type' => [
        'individual' => '个人',
        'enterprise' => '企业',
    ],

    'dict_home_service_invoice_status' => [
        'wait' => '待开具',
        'issued' => '已开具',
    ],

    'dict_home_service_help_category_is_show' => [
        'yes' => '显示',
        'no' => '不显示',
    ],

    'dict_home_service_help_is_show' => [
        'yes' => '显示',
        'no' => '不显示',
    ],

    'dict_home_service_help_type' => [
        'member' => '会员端',
        'store' => '门店端',
        'technician' => '师傅端',
    ],

    'dict_home_service_feedback_type' => [
        'member' => '会员端',
        'store' => '门店端',
        'technician' => '师傅端',
    ],

    'dict_home_service_follow_result' => [
        "resolved" => '已解决',
        "unresolved" => '未解决',
    ],

    'dict_home_service_fee_situation' => [
        "consistent" => '收费一致',
        "inconsistent" => '有出入',
    ],

    'dict_home_service_cash_out_source' => [
        "store" => '门店',
        "technician" => '师傅',
    ],

    'dict_home_service_cash_out' => [
        "wait_transfer" => '待转账',
        "transfered" => '已转账',
        "cancel" => '已取消',
    ],

    'dict_home_service_account_type' => [
        "money" => '金额',
        "commission" => '佣金',
    ],

    'dict_diy' => [
        'home_service_component_type_basic' => '上门家政组件',
        'home_service_index' => '上门家政首页',
        'home_service_link' => '上门家政',
        'home_service_title' => '基础链接',
        'home_service_category' => '分类',
        'home_service_link_order_list' => '订单',
        'home_service_link_member_index' => '我的',
        'home_service_link_coupon' => '优惠券',
        'page_home_service_index' => '上门家政首页',
        'page_home_service_member_index' => '上门家政个人中心',
        'home_service_link_goods_search' => '商品列表',
        'home_service_link_card_list' => '次卡列表',
        'home_service_link_technician_entrance' => '服务人员登陆入口',
        'home_service_link_store_entrance' => '服务机构登陆入口',


        'o2o_link_index' => '上门服务首页',
        'o2o_link_goods_list' => '项目列表',
        'o2o_link_technician_list' => '师傅列表',
        'o2o_link_order_refund_list' => '退款列表',
        'o2o_link_address_list' => '地址列表',
        'o2o_link_master_stat_index' => '师傅中心',
        'o2o_goods_select' => '服务项目',
        'o2o_goods_category_select' => '项目分类',
        'o2o_technician_select' => '选择师傅',
    ],


    'dict_home_service_order_stat_status' => [
        'finish' => '已完成',
        'refund' => '退款/售后',
        'close' => '已关闭',
    ],

    'dict_diy_poster' => [
        'home_service_goods_component_type_basic' => '上门家政组件',
    ],
    //请假理由
    'dict_home_service_rest' => [
        'personal_affairs' => '个人事务',
        'sick_leave' => '生病就医',
        'family_emergency' => '家庭紧急事务',
        'marriage' => '本人结婚',
        'funeral' => '亲属奔丧',
        'maternity' => '产假',
        'paternity' => '陪产假',
        'annual_leave' => '年休假',
        'business' => '处理公事',
        'study' => '学习培训',
        'travel' => '外出旅行',
        'other' => '其他原因',
    ],

    //消息来源



    'dict_home_service_notice_source' => [
        'order' => '订单提醒',
        'bill' => '账单通知',
        'system' => '系统通知',
    ]


];
