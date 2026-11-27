<?php

return [
    'bind' => [

    ],
    'listen' => [
        'HsxErpSalesPricing' => ['addon\phone_shop\app\listener\erp\ErpSalesPricing'],
        'PhoneShopSaleReturnCancelled' => [
            'addon\phone_shop\app\listener\erp\ErpSaleReturnCancelled',
        ],
        'HsxErpMallInventory' => ['addon\phone_shop\app\listener\erp\ErpMallInventoryProvider'],
        // 营销任务奖励提供器：商城只负责优惠券能力，活动规则与台账由 hsx_marketing 管理。
        'HsxMarketingRewardProvidersRequested' => ['addon\phone_shop\app\listener\marketing\MarketingRewardProviders'],
        'HsxMarketingRewardOptionsRequested' => ['addon\phone_shop\app\listener\marketing\MarketingRewardOptions'],
        'HsxMarketingRewardGrantRequested' => ['addon\phone_shop\app\listener\marketing\MarketingRewardGrant'],
        'HsxMarketingRewardReverseRequested' => ['addon\phone_shop\app\listener\marketing\MarketingRewardReverse'],
        // 营销任务复用商城现有“同行转发权益”申请、万能表单、企微审核与结果通知闭环。
        'HsxMarketingQualificationOptionsRequested' => ['addon\phone_shop\app\listener\marketing\MarketingQualificationOptions'],
        'HsxMarketingQualificationRequested' => ['addon\phone_shop\app\listener\marketing\MarketingQualification'],
        'HsxAiIntegrationRegistryRequested' => [
            'addon\phone_shop\app\listener\ai\AiIntegrationRegistryRequested',
        ],
        'HsxAiAdminAgentRegistryRequested' => [
            'addon\phone_shop\app\listener\ai\AiAdminAgentRegistryRequested',
        ],
        'HsxAiDefaultToolArgumentsRequested' => [
            'addon\phone_shop\app\listener\ai\AiDefaultToolArgumentsRequested',
        ],
        'HsxAiToolIntentRequested' => [
            'addon\phone_shop\app\listener\ai\AiToolIntentRequested',
        ],
        'HsxAiSkillRegistryRequested' => [
            'addon\phone_shop\app\listener\ai\AiSkillRegistryRequested',
        ],
        // AI 插件只通过事件取得当前站点公开在售商品，不直接依赖商城内部类。
        'HsxAiBusinessContextRequested' => [
            'addon\phone_shop\app\listener\ai\AiMallBusinessContextRequested',
        ],
        'HsxAiToolRegistryRequested' => [
            'addon\phone_shop\app\listener\ai\AiToolRegistryRequested',
        ],
        'HsxAiToolExecuteRequested' => [
            'addon\phone_shop\app\listener\ai\AiToolExecuteRequested',
        ],
        // 企业微信发出同行身份审核待办前再次校验，避免已审核任务仍继续提醒。
        'HsxBusinessTaskValidate' => [
            'addon\phone_shop\app\listener\member\ForwardApplicationTaskValidate',
            'addon\phone_shop\app\listener\order\OfflineOrderTaskValidate',
        ],
        'PhoneShopOfflineOrderSubmitted' => [
            'addon\phone_shop\app\listener\order\OfflineOrderSubmitted',
        ],
        // ERP 扩展：只有当前站点套餐包含 phone_shop 时，牛云事件加载器才会装配该销售渠道
        'HsxErpSaleChannelOptions' => [
            'addon\phone_shop\app\listener\erp\ErpSaleChannelOptionsListener',
        ],
        'HsxErpBusinessSourceOptions' => [
            'addon\phone_shop\app\listener\erp\ErpBusinessSourceOptionsListener',
        ],
        'HsxErpMarketplaceProviders' => [
            'addon\phone_shop\app\listener\erp\ErpMarketplaceProvider',
        ],
        'HsxErpChannelCategoryProject' => [
            'addon\phone_shop\app\listener\erp\ErpCategoryProjection',
        ],
        'HsxErpListingCatalog' => [
            'addon\phone_shop\app\listener\erp\ErpListingCatalog',
        ],
        'HsxErpPublishListing' => [
            'addon\phone_shop\app\listener\erp\ErpPublishListing',
        ],
        // 小程序真实订单付款后：一物一码设备走库存销售；商城自有标品只同步销售与资金事实。
        'PhoneShopOrderPay' => [
            'addon\phone_shop\app\listener\erp\PhoneShopOrderPaidToErp',
            'addon\phone_shop\app\listener\erp\PhoneShopNativeOrderPaidToErp',
        ],
        // 手机端初始化加载事件
        'initWap' => [
            'addon\phone_shop\app\listener\config\initWapListener'
        ],

        // 货源入库：中台拍照定价完成 -> 落"待上架货源"(与 hsx_recycle 共享该事件)
        'DeviceAssetPriceCompleted' => [
            'addon\phone_shop\app\listener\intake\DeviceAssetPricedListener'
        ],

        // ERP 出库回流：成交->建商城订单+商品已售；退回->商品回在售(与 hsx_recycle 共享该事件)
        'ErpDomainEvent' => [
            'addon\phone_shop\app\listener\order\ErpAssetSoldListener',
            'addon\phone_shop\app\listener\erp\ErpSettlementCompletedListener',
        ],

        // 添加/编辑商品之后的事件
        'AfterGoodsEdit' => [
            'addon\phone_shop\app\listener\point_exchange\AfterGoodsEdit',
        ],
        // 插件专属事件，避免 shop 的同号商品编辑误触发 phone_shop 订阅。
        'PhoneShopGoodsSaleableChanged' => [
            'addon\phone_shop\app\listener\goods\GoodsSubscriptionChanged',
        ],
        // 订单创建
        'PhoneShopOrderCreate' => [
            'addon\phone_shop\app\listener\order\ShopOrderCreate',
        ],
        // 站点创建之后
        'AddSiteAfter' => ['addon\phone_shop\app\listener\AddSiteAfterListener'],
        //站点初始化
        'SiteInit' => ['addon\phone_shop\app\listener\SiteInitListener'],
        //订单创建时 优惠抵扣减免业务
        'PhoneShopOrderDiscountCreate' => [
            'addon\phone_shop\app\listener\point_exchange\ShopOrderDiscountCreate'   //积分商城兑换
        ],
        //订单创建后
        'AfterPhoneShopOrderCreate' => [
            'addon\phone_shop\app\listener\order\AfterShopOrderCreate',
            'addon\phone_shop\app\listener\point_exchange\AfterShopOrderCreate',
        ],

        //订单支付后
        'AfterPhoneShopOrderPay' => [
            'addon\phone_shop\app\listener\order\AfterShopOrderPay',
        ],
        //订单发货后
        'AfterPhoneShopOrderDelivery' => ['addon\phone_shop\app\listener\order\AfterShopOrderDelivery'],
        //订单收货后
        'AfterPhoneShopOrderFinish' => ['addon\phone_shop\app\listener\order\AfterShopOrderFinish'],
        //订单编辑价格后
        'AfterPhoneShopOrderEditPrice' => ['addon\phone_shop\app\listener\order\AfterShopOrderEditPrice'],
        //订单关闭后
        'AfterPhoneShopOrderClose' => [
            'addon\phone_shop\app\listener\order\AfterShopOrderClose',
            'addon\phone_shop\app\listener\point_exchange\AfterShopOrderClose',   //积分商城业务
        ],
        //计算活动信息
        'PhoneShopGoodsMarketCalculate' => [
            'addon\phone_shop\app\listener\marketing\ShopNewcomerCalculate',   //新人专享
        ],
        /***************************************************** 退款 start *****************************************************/
        'AfterPhoneShopOrderRefundApply' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundApply'],
        'AfterPhoneShopOrderRefundAuditApply' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundAuditApply'],
        'AfterPhoneShopOrderRefundAuditRefundGoods' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundAuditRefundGoods'],
        'AfterPhoneShopOrderRefundClose' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundClose'],
        'AfterPhoneShopOrderRefundDelivery' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundDelivery'],
        'AfterPhoneShopOrderRefundEdit' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundEdit'],
        'AfterPhoneShopOrderRefundEditDelivery' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundEditDelivery'],
        'AfterPhoneShopOrderRefundFinish' => [
            'addon\phone_shop\app\listener\refund\AfterShopOrderRefundFinish',
            'addon\phone_shop\app\listener\erp\PhoneShopNativeOrderRefundedToErp',
        ],
        'AfterPhoneShopOrderRefundActiveCreate' => ['addon\phone_shop\app\listener\refund\AfterShopOrderRefundActiveCreate'],
        /***************************************************** 退款 end *****************************************************/

        'PhoneShopPromotion' => ['addon\phone_shop\app\listener\app\ShopPromotionListener'],
        'WapIndex' => ['addon\phone_shop\app\listener\WapIndexListener'],
        'BottomNavigation' => ['addon\phone_shop\app\listener\BottomNavigationListener'],

        //支付
        'PayCreate' => ['addon\phone_shop\app\listener\pay\PayCreateListener'],
        'HttpRun' => ['addon\phone_shop\app\listener\pay\RegisterPaymentGuard'],
        'PaySuccess' => ['addon\phone_shop\app\listener\pay\PaySuccessListener'],
        'PayTradeInfo' => ['addon\phone_shop\app\listener\order\ShopOrderTradeInfoListener'],   //订单交易信息
        'RefundSuccess' => ['addon\phone_shop\app\listener\pay\RefundSuccessListener'],

        'NoticeData' => [
            'addon\phone_shop\app\listener\notice_template\OrderPay',
            'addon\phone_shop\app\listener\notice_template\OrderPayRemind',
            'addon\phone_shop\app\listener\notice_template\OrderDelivery',
            'addon\phone_shop\app\listener\notice_template\RefundAgree',
            'addon\phone_shop\app\listener\notice_template\RefundRefuse',
            'addon\phone_shop\app\listener\notice_template\GoodsMatch',
            'addon\phone_shop\app\listener\notice_template\ForwardApplicationResult',
            'addon\phone_shop\app\listener\notice_template\OfflineOrderSubmitted',
            'addon\phone_shop\app\listener\notice_template\OfflineOrderStatus',
        ],
        //优惠券
        'CouponReceiveType' => ['addon\phone_shop\app\listener\coupon\CouponReceiveListener'],
        'CouponCheck' => ['addon\phone_shop\app\listener\coupon\CouponCheckListener'],

        //获取海报数据
        'GetPosterType' => ['addon\phone_shop\app\listener\poster\ShopPosterType'],
        'GetPosterData' => ['addon\phone_shop\app\listener\poster\ShopPoster'],

        //导出数据类型
        'ExportDataType' => [
            //订单列表导出
            'addon\phone_shop\app\listener\order_export\ShopOrderExportTypeListener',
            //订单项导出
            'addon\phone_shop\app\listener\order_export\ShopOrderGoodsExportTypeListener',
            //退款售后导出
            'addon\phone_shop\app\listener\refund_export\ShopOrderRefundExportTypeListener',
            //发票列表导出
            'addon\phone_shop\app\listener\order_export\ShopInvoiceExportTypeListener',
        ],
        //导出数据源
        'ExportData' => [
            //订单列表导出
            'addon\phone_shop\app\listener\order_export\ShopOrderExportDataListener',
            //订单项导出
            'addon\phone_shop\app\listener\order_export\ShopOrderGoodsExportDataListener',
            //退款售后导出
            'addon\phone_shop\app\listener\refund_export\ShopOrderRefundExportDataListener',
            //发票列表导出
            'addon\phone_shop\app\listener\order_export\ShopInvoiceExportDataListener',
        ],
        //商城统计执行
        'StatExecute' => ['addon\phone_shop\app\listener\stat\StatExecuteListener'],
        //商城统计字段
        'StatField' => ['addon\phone_shop\app\listener\stat\StatFieldListener'],
        //核销
        'VerifyType' => ['addon\phone_shop\app\listener\verify\VerifyTypeListener'],
        'VerifyCreate' => ['addon\phone_shop\app\listener\verify\VerifyCreateListener'],
        'Verify' => ['addon\phone_shop\app\listener\verify\VerifyListener'],
        'VerifyInfo' => ['addon\phone_shop\app\listener\verify\VerifyInfoListener'],
        'VerifyCheck' => ['addon\phone_shop\app\listener\verify\VerifyCheckListener'],

        'GetGoodsJoinInfo' => [
            'addon\phone_shop\app\listener\marketing\GetGoodsJoinInfo',
//            'addon\pintuan\app\listener\GetGoodsJoinInfo'
        ],
        'ActiveSaveAfter' => [
            'addon\phone_shop\app\listener\marketing\ShopActiveSaveAfter'
        ],
        //通过支付信息获取手机端订单详情路径
        'WapOrderDetailPath' => [
            'addon\phone_shop\app\listener\order\WapOrderDetailPathListener',
        ],

        'PrinterContent' => [
            'addon\phone_shop\app\listener\printer\PrinterContentListener'
        ],

        //新人专享
        'NewcomerActiveJoin' => ['addon\phone_shop\app\listener\marketing\NewcomerActiveJoinListener'],
        //会员登录后事件
        'MemberLoginAfter' => ['addon\phone_shop\app\listener\MemberLoginAfterListener'],

        // 会员充值 赠送内容
        'RechargeGiftContent' => ['addon\phone_shop\app\listener\recharge\GiftContentListener'],
        // 会员充值成功后的事件
        'RechargeAfterListener' => ['addon\phone_shop\app\listener\recharge\RechargeAfterListener'],

        //获取宝贝数据
        'TreasureType' => ['addon\phone_shop\app\listener\treasure\TreasureTypeListener'],
        'TreasureData' => ['addon\phone_shop\app\listener\treasure\TreasureDataListener'],

        //主题色
        'ThemeColor' => ['addon\phone_shop\app\listener\diy\ThemeColorListener'],
        //万能表单删除前
        'BeforeFormDelete' => ['addon\phone_shop\app\listener\diy\BeforeFormDeleteListener'],
        //查询营销列表
        'ShowMarketing' => [
            'addon\phone_shop\app\listener\system\ShowMarketingListener'
        ],
        'ShowCustomer' => [
            'addon\phone_shop\app\listener\system\ShowCustomerListener'
        ],
        //获取商品展示价格
        'GoodsShowPrice' => ['addon\phone_shop\app\listener\goods\GoodsShowPriceListener'],

        // 种草奖励发放优惠券
        'SettleRewardListener' => ['addon\phone_shop\app\listener\sow_community\SettleRewardListener'],

        // 种草奖励优惠券
        'RuleContentListener' => ['addon\phone_shop\app\listener\sow_community\RuleContentListener'],


        //三方插件对接
        //第三方通用插件订单备份
        //同步订单到商城
        'ThirdAddonOrderBackUp' => ['addon\phone_shop\app\listener\third_addon\order\BackUpThirdOrderListener'],
        //获取订单后续操作路径   退款  服务
        'ThirdAddonOrderPath' => ['addon\phone_shop\app\listener\third_addon\order\OrderServicePathListener'],
        //输出当前插件/应用名称及标识
        'ThirdAddonOutputSource' => ['addon\phone_shop\app\listener\third_addon\SupportAddonListener'],
        'ThirdAddonOutputData' =>  ['addon\phone_shop\app\listener\third_addon\goods\GoodsOutPutDataListener'],
        'ThirdAddonOutputWhere' =>  ['addon\phone_shop\app\listener\third_addon\goods\GoodsOutPutWhereInitListener'],
        //商品详情
        'ThirdAddonOutputDataInfo' =>  ['addon\phone_shop\app\listener\third_addon\goods\GoodsOutPutDataInfoListener' ],
        //三方订单附加费用
        'ThirdAddonOrderAmount' => ['addon\phone_shop\app\listener\third_addon\order\OrderAmountServiceListener'],


        //获取商品配送费用
        'ThirdAddonOrderDelivery' => ['addon\phone_shop\app\listener\third_addon\order\OrderDeliveryServiceListener'],

        /***************************************************** 同城配送 start *****************************************************/
        //配送方式
        'LocalDeliveryType' => ['addon\phone_shop\app\listener\local_delivery\dada\local_delivery_type\LocalDeliveryTypeListener'],
        //订单取消原因
        'OrderCancelReason' => ['addon\phone_shop\app\listener\local_delivery\dada\order\OrderCancelReasonListener'],
        //配送订单操作前置状态
        'DeliveryOrderPreStatus' => [
            'addon\phone_shop\app\listener\local_delivery\dada\order\OrderPreStatusListener',
            'addon\phone_shop\app\listener\shop_delivery\OrderPreStatusListener'
        ],
        //获取同城配送轨迹
        'GetLocalDeliveryTrack' => ['addon\phone_shop\app\listener\local_delivery\dada\order\GetLocalDeliveryTrack'],

        /***************************************************** 同城配送 end *****************************************************/
    ],
    'subscribe' => [
    ],
];
