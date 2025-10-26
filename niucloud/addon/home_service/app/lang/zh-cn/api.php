<?php

return [


    /*********************************************商品业务 start ****************************************************/
    'HOME_SERVICE_SKU_DATA_FORMAT_ERROR' => 'SKU数据格式错误：',
    'HOME_SERVICE_SKU_DATA_MUST_BE_ARRAY' => 'SKU数据必须为数组',
    'HOME_SERVICE_SKU_DATA_SAVE_FAILED' => 'SKU数据保存失败',
    // 次卡（主卡）相关
    'HOME_SERVICE_CARD_CREATE_FAILED' => '次卡创建失败',
    'HOME_SERVICE_CARD_NOT_EXIST_OR_NO_PERMISSION' => '次卡不存在或无权操作',
    'HOME_SERVICE_CARD_UPDATE_FAILED' => '次卡更新失败',
    'HOME_SERVICE_CARD_NOT_EXIST' => '未获取到次卡信息',
    'HOME_SERVICE_GOODS_NOT_EXIST' => '服务项目不存在',
    'HOME_SERVICE_MEMBER_ALREADY_COLLECT' => '服务项目已收藏',
    'HOME_SERVICE_CARD_IS_EXPIRE' => '次卡套餐已到期',
    'HOME_SERVICE_EXCHANGE_GOODS_ERROR' => '兑换商品信息错误',
    'HOME_SERVICE_CARD_ITEM_NOT_EXIST' => '未获取到卡项信息',
    'HOME_SERVICE_CARD_ITEM_USABLE_NUM_INSUFFICIENT' => '卡项可用次数不足',
    'HOME_SERVICE_CARD_STATUS_ABNORMAL' => '卡项状态异常',
    'HOME_SERVICE_CARD_USE_RECORD_NOT_EXIST' => '次卡使用记录不存在',
    'EXIST_NOT_USE_CARD' => '存在次卡套餐，不能编辑服务规格',
    'COLLECT_SUCCESS' => '收藏成功',
    'CANCEL_COLLECT_SUCCESS' => '取消收藏成功',
    'HOME_SERVICE_CATEGORY_BE_USED'=>'分类已被使用，请查验后重新操作',
    'HOME_SERVICE_ACCEPT_ORDER_CARD_NOT_REFUND'=>'已接单次卡不可申请退款',



    /*********************************************  商品业务 end ****************************************************/


    /*********************************************师傅  ****************************************************/
    'HOME_SERVICE_YOU_ARE_ALREADY_TECHNICIAN_NO_NEED_APPLY' => '您已经是师傅了无需申请！！',
    'HOME_SERVICE_TECHNICIAN_NO_NEED_APPLY' => '师傅申请数据不存在！！',
    'HOME_SERVICE_YOU_ARE_ALREADY_TECHNICIAN_NO_NEED' => '您已经是师傅了无需操作！！',
    'HOME_SERVICE_MEMBER_IS_TECHNICIAN' => '当前会员已经是师傅！！',
    'HOME_SERVICE_TECHNICIAN_NOT_EXIST' => '师傅数据不存在',
    'HOME_SERVICE_TECHNICIAN_ALREADY_COLLECT' => '师傅已收藏',

    'HOME_SERVICE_TECHNICIAN_CATEGORY_id_NOT_EXIST' => '师傅技能不能为空',





    /*********************************************  策略 ****************************************************/
    'HOME_SERVICE_CITY_STRATEGY_IS_EXIST' => '当前城市策略已存在！！',

    /*********************************************  师傅休息 ****************************************************/
    'HOME_SERVICE_REST_DATE_NOT_EMPTY' => '休息时间不能为空',

    /*********************************************师傅等级 ****************************************************/
    'ONLY_HAVE_ONE_DEFAULT_LEVEL' => '只能有一个默认等级',
    'ONLY_HAVE_ONE_LEVEL_NUM' => '只能有一个等级权重',
    'TECHNICIAN_HAS_LEVEL_TECHNICIAN_NOT_ALLOW_DELETE' => '存在使用当前等级的师傅,无法删除！',


    /*********************************************  师傅 end ****************************************************/

    /********************************************* 门店  ****************************************************/
    'HOME_SERVICE_STORE_NO_NEED_APPLY' => '门店申请数据不存在！！',
    'HOME_SERVICE_STORE_APPLY_IS_NOT_PENDING_EXAMINE' => '门店申请数据不是待审核状态！！',
    'HOME_SERVICE_STORE_NOT_EXIST' => '门店数据不存在',
    'HOME_SERVICE_NOT_REPEAT_APPLY' => '不能重复申请',

    /*********************************************  门店 end ****************************************************/

    /********************************************* 订单  ****************************************************/
    'HOME_SERVICE_ORDER_NOT_FOUND' => '订单不存在',
    'HOME_SERVICE_NOT_SELECT_ADDRESS' => '需要先选择上门地址',
    'HOME_SERVICE_ORDER_BUYER_NOT_FOUND' => '找不到买家',
    'HOME_SERVICE_GOODS_NOT_EXIST' => '服务不存在',
    'HOME_SERVICE_ORDER_EXPIRE' => '订单数据不存在',
    'HOME_SERVICE_ORDER_IS_PAY_FINISH' => '订单已支付',
    'HOME_SERVICE_ORDER_IS_NOT_GRAB' => '当前订单不可抢',
    'HOME_SERVICE_ORDER_IS_NOT_SERVICE' => '当前订单不可服务',
    'HOME_SERVICE_ORDER_TECHNCIAN_IS_COIMCIDE'=> '当前指定师傅与当前师傅相同无需重复操作',
    'HOME_SERVICE_ORDER_COUPON_SUPPORT_GOODS' => '没有适用的商品',
    'HOME_SERVICE_ORDER_COUPON_NOT_CONDITION' => '未达到最低可使用金额',

    'HOME_SERVICE_ONLY_WAIT_PAY_CAN_BE_PAY' => '只有待支付的订单可以支付',

    'HOME_SERVICE_ORDER_DELETE_STATUS_ERROR' => '删除订单中有未关闭订单：%s',
    'HOME_SERVICE_ORDER_IS_NOT_CLOSE' => '当前订单不可关闭',
    'HOME_SERVICE_ORDER_IS_NOT_EDIT_SERVICE_TIME' => '当前订单不可修改服务时间',


    /********************************************* 订单 end ****************************************************/

    /********************************************* 发票  ****************************************************/
    'ORDER_ISSUED_INVOICE' => '所选订单已开具发票',
    'INVOICE_NOT_EXIST' => '发票信息不存在',
    'INVOICE_ISSUED' => '发票已开具',
    'INVOICE_MONEY_NOT_GREATER_THAN_PAY_MONEY' => '开票金额不能大于实际支付金额',
    /********************************************* 订单 end ****************************************************/

    /********************************************* 增项服务  ****************************************************/
    'HOME_SERVICE_ORDER_ADDED_ITEM_NOT_EXIST' => '增值项不存在',
    'HOME_SERVICE_ORDER_ADDED_ITEM_ORDER_NOT_EXIST' => '增项订单不存在',
    'HOME_SERVICE_ORDER_ITEM_NOT_DEL' => '该服务项不能删除',
    'HOME_SERVICE_ORDER_ITEM_NOT_DEL_AUTHORITY' => '权限不足',
    'HOME_SERVICE_ORDER_NOT_ADD_ORDER_ITEM' => '当前订单不可添加项目',
    'HOME_SERVICE_ORDER_IS_HAVE_WAIT_PAY_ITEM' => '当前订单存在待付款项目',

    /********************************************* 增项服务 end ****************************************************/

    /********************************************* 订单维权  ****************************************************/
    'HOME_SERVICE_ORDER_IS_NOT_ENABLE_REFUND' => '订单不允许退款',
    'HOME_SERVICE_ORDER_REFUND_MONEY_GT_ORDER_MONEY' => '退款金额不能大于可退款总额',
    'HOME_SERVICE_ORDER_REFUND_IS_NOT_SETTLEMENT' => '当前订单不可结算',
    'HOME_SERVICE_REFUND_NOT_EXIST' => '未获取到售后信息',
    'HOME_SERVICE_REFUND_STATUS_ERROR' => '售后单据状态异常',
    'HOME_SERVICE_REFUND_CANNOT_CANCEL' => '售后申请商家已处理无法取消',
    'HOME_SERVICE_REFUND_MONEY_CANNOT_GT_PAYMONEY' => '退款金额不能大于订单实付金额',
    'HOME_SERVICE_REFUND_MONEY_NOT_GT_APPLY_MONEY' => '退款金额不能大于申请金额',
    'HOME_SERVICE_REFUND_MONEY_GT_ZERO' => '退款金额必须大于0',
    'HOME_SERVICE_REFUND_APPLY_SUCCESS' => '退款申请已提交',

    /********************************************* 订单维权 end ****************************************************/

    /********************************************* 订单评价  ****************************************************/
    'HOME_SERVICE_GOODS_EVALUATE_SUCCESS' => '评价成功',
    'ORDER_IS_EVALUATE' => '订单已评价',
    /********************************************* 订单评价 end ****************************************************/

    /********************************************* 优惠券  ****************************************************/
    'COUPON_STOCK_INSUFFICIENT' => '优惠券已领完',
    'COUPON_NOT_EXIST' => '优惠券不存在',
    'COUPON_INVALID' => '优惠券已失效',
    'COUPON_CAN_NOT_MANUAL_RECEIVE' => '该优惠券不可手动领取',
    'COUPON_RECEIVE_NOT_TIME' => '优惠券不在领取时间范围内',
    'COUPON_RECEIVE_EXCESS' => '已领取数量超过限制领取数量，不可领取',
    'COUPON_RECEIVE_TYPE_NOT_EXIST' => '优惠券领取方式有误',
    'COUPON_RECEIVE_SUCCESS' => '领取成功',
    'HOME_SERVICE_COUPON_IS_USED_OR_EXIST' => '优惠券不存在或已使用',
    'HOME_SERVICE_COUPON_VALID_END_TIME_NOT_ALLOW_LT_START_TIME' => '优惠券的有效期结束时间不能小于当前时间',
    'HOME_SERVICE_COUPON_IN_USE_NOT_ALLOW_EDIT' => '优惠券正在参与营销活动，禁止修改',
    'HOME_SERVICE_COUPON_IN_USE_NOT_ALLOW_DEL' => '优惠券已有用户领取，禁止删除',
    'HOME_SERVICE_ORDER_ITEM_NOT_PAY' => '当前订单有项目未支付，请先支付',
    'HOME_SERVICE_ORDER_COUPON_EXPIRE_OR_NOT_FOUND' => '优惠券已使用或已过期',
    'HOME_SERVICE_ORDER_COUPON_EXPIRE' => '当前优惠券已过期',
    'HOME_SERVICE_ORDER_COUPON_NOT_SUPPORT_GOODS' => '当前优惠券在本单不可用',
    'HOME_SERVICE_ORDER_COUPON_NOT_SUPPORT_MIN_MONEY' => '未达到当前优惠券的最低使用条件',


    /********************************************* 优惠券 end  ****************************************************/

    /********************************************* 次卡套餐订单  ****************************************************/
    'HOME_SERVICE_CARD_ORDER_EXPIRE' => '订单数据不存在',
    'HOME_SERVICE_CARD_ORDER_PAID' => '订单已支付',

    /********************************************* 帮助反馈  ****************************************************/
    'HELP_CATEGORY_BE_USED'=>'帮助分类已被使用，请查验后重新操作',
    'HELP_NOT_EXIST'=>'帮助信息不存在',
    'MUST_HAVE_DEFAULT_CATEGORY'=>'必须有默认分类',
    'BUILTIN_DATA_NOT_DEL'=>'内置数据不能删除',

    /********************************************* 提现  ****************************************************/
    'CASH_OUT_ACCOUNT_NOT_EXIST' => '提现账户不存在',
    'TECHNICIAN_APPLY_CASHOUT' => '师傅申请提现,扣除佣金',
    'STORE_APPLY_CASHOUT' => '门店申请提现,扣除佣金',
    'CASH_OUT_SOURCE_FAIL' => '提现来源错误',
    'TECHNICIAN_CASHOUT_TRANSFER' => '师傅提现转账',
    'STORE_CASHOUT_TRANSFER' => '门店提现转账',
    'TECHNICIAN_CANCEL_APPLY_CASHOUT' => '师傅取消提现,返还佣金',
    'STORE_CANCEL_APPLY_CASHOUT' => '门店取消提现,返还佣金',

    /********************************************* 结算  ****************************************************/
    'GET_ACCOUNT_LIST_PARAM_ERROR' => '获取结算账单参数错误',

    'UNSUPPORTED_TIME_TYPE' => '不支持的时间类型',
    'UNSUPPORTED_RANK_TYPE' => '不支持的排行类型',

    /********************************************* 统计  ****************************************************/
    'STATISTICS_TYPE_ERROR' => '统计类型错误',


];
