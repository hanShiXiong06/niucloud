<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . ltrim($path, '/'));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$phoneEvent = require $root . '/addon/phone_shop/app/event.php';
$erpEvent = require $root . '/addon/hsx_erp/app/event.php';
$payListeners = (array)($phoneEvent['listen']['PhoneShopOrderPay'] ?? []);
$refundListeners = (array)($phoneEvent['listen']['AfterPhoneShopOrderRefundFinish'] ?? []);
$erpDomainListeners = (array)($phoneEvent['listen']['ErpDomainEvent'] ?? []);

$assert(in_array('addon\\phone_shop\\app\\listener\\erp\\PhoneShopNativeOrderPaidToErp', $payListeners, true), '商城付款必须注册原生商品财务桥');
$assert(in_array('addon\\phone_shop\\app\\listener\\erp\\PhoneShopNativeOrderRefundedToErp', $refundListeners, true), '商城退款必须注册原生商品冲销桥');
$assert(in_array('addon\\phone_shop\\app\\listener\\erp\\ErpSettlementCompletedListener', $erpDomainListeners, true), '商城必须消费ERP结算完成事件');
$assert(isset($erpEvent['listen']['ErpExternalSaleRecordedRequested']), 'ERP必须注册外部商品销售契约');
$assert(isset($erpEvent['listen']['ErpExternalSaleRefundedRequested']), 'ERP必须注册外部商品退款契约');
$externalContract = $read('addon/hsx_erp/app/service/admin/ErpExternalContractService.php');
$saleListener = $read('addon/hsx_erp/app/listener/ErpExternalSaleRecordedRequested.php');
$refundListener = $read('addon/hsx_erp/app/listener/ErpExternalSaleRefundedRequested.php');
$assert(str_contains($externalContract, 'public static function forSite'), '支付回调和队列必须支持显式绑定事件站点');
$assert(str_contains($saleListener, '::forSite'), '外部销售监听器不能依赖后台登录请求站点');
$assert(str_contains($refundListener, '::forSite'), '外部退款监听器不能依赖后台登录请求站点');

$shopSql = $read('addon/phone_shop/sql/install.sql');
foreach (['cost_price_snapshot', 'total_cost_snapshot', 'supplier_id_snapshot', 'inventory_source'] as $column) {
    $assert(str_contains($shopSql, "`{$column}`"), "商城订单明细缺少{$column}快照");
}
$erpSql = $read('addon/hsx_erp/sql/install.sql');
foreach (['external_goods_id', 'external_sku_id', 'external_line_id', 'supplier_id', 'payment_gross_amount', 'payment_fee_amount', 'merchant_net_amount', 'refunded_amount', 'refunded_cost'] as $column) {
    $assert(str_contains($erpSql, "`{$column}`"), "ERP销售事实缺少{$column}");
}

$create = $read('addon/phone_shop/app/service/core/order/CoreOrderCreateService.php');
$assert(str_contains($create, "'cost_price_snapshot'"), '下单时必须冻结成本价');
$assert(str_contains($create, "'supplier_id_snapshot'"), '下单时必须冻结供应商ID');
$assert(str_contains($create, "'erp_asset_id'"), '下单时必须识别ERP设备和商城原生商品');
$assert(str_contains($create, "'offline_pending'"), '商城下单必须支持线下支付待业务员处理');
$skuModel = $read('addon/phone_shop/app/model/goods/GoodsSku.php');
$assert(str_contains($skuModel, 'goods_category,supplier_id,attr_id'), 'SKU关联商品时必须读取供应商ID，订单快照不能静默丢失');

$record = $read('addon/hsx_erp/app/service/admin/ErpExternalSaleRecordedService.php');
$assert(str_contains($record, "'asset_id' => 0"), '商城原生商品不能伪造ERP设备资产');
$assert(!str_contains($record, 'ErpPurchase'), '商城原生商品成交不能伪造采购单');
$assert(!str_contains($record, 'ErpPayable'), '商城原生商品成交不能伪造采购应付');
$assert(str_contains($record, '微信支付待结算') === false, '清算账户名称应由公共财务层统一维护');

$accounting = $read('addon/hsx_erp/app/service/admin/ErpExternalSaleAccountingService.php');
$assert(str_contains($accounting, '微信支付待结算'), '线上支付必须进入明确的清算账户');
$assert(str_contains($record, "'category_key' => 'channel_payment_fee'"), '渠道手续费必须单独留痕');
$assert(!str_contains($record, "'direction' => 'none'"), '已现结销售不能产生无法解释的账款方向');
$assert(str_contains($record, "\$isOfflineCash"), '线下现结必须记录真实收款账户');
$assert(str_contains($record, "\$isCredit"), '线下挂账必须形成ERP应收');

$offlineService = $read('addon/phone_shop/app/service/admin/order/OfflineOrderService.php');
$assert(str_contains($offlineService, 'ErpCapitalAccountOptionsRequested'), '线下收款账户必须由ERP Hook提供');
$assert(str_contains($offlineService, "'confirm_paid'"), '订单详情必须支持确认实际收款');
$assert(str_contains($offlineService, "'confirm_credit'"), '订单详情必须支持确认挂账');
$assert(str_contains($offlineService, '$canRetryCash'), '收款回调失败后必须允许安全重试');
$assert(str_contains($offlineService, 'applyDealTotal'), '线下订单必须支持单台议价和多台打包总价');
$assert(str_contains($offlineService, "'deal_amount'"), '多台打包价必须分摊到订单明细，作为单台退款上限');
$assert(!str_contains($offlineService, "\$order->save([\n                'update_time'"), '商城订单表没有update_time，确认挂账不能写入不存在的字段');
$assert(substr_count($offlineService, '$order->save(') === 1, '确认挂账订单状态必须一次写入，避免ORM二次保存注入update_time');
$assert(substr_count($offlineService, "'is_enable_refund' => 0") >= 2, '线下挂账订单和明细必须关闭客户退款权限');
$orderModel = $read('addon/phone_shop/app/model/order/Order.php');
$assert(str_contains($orderModel, 'protected $updateTime = false'), '商城订单模型必须按真实表结构关闭update_time');
$apiRefund = $read('addon/phone_shop/app/service/api/refund/RefundActionService.php');
$assert(str_contains($apiRefund, "!== 'online'"), '客户退款接口必须二次拦截线下订单');
$settlementConsumer = $read('addon/phone_shop/app/listener/erp/ErpSettlementCompletedListener.php');
foreach (['origin_finance', "'credit_status' => \$creditStatus", "'settle_status' => \$isSettled ? 1 : 0", "'is_enable_refund' => 0"] as $needle) {
    $assert(str_contains($settlementConsumer, $needle), 'ERP结算回写商城缺少聚合进度或退款权限保护：' . $needle);
}
$shopRoutes = $read('addon/phone_shop/app/adminapi/route/route.php');
$assert(str_contains($shopRoutes, 'order/offline/capital_accounts'), '商城后台缺少ERP资金账户接口');
$assert(str_contains($shopRoutes, 'order/offline/process'), '商城后台缺少线下订单处理接口');

$assetBridge = $read('addon/phone_shop/app/listener/erp/PhoneShopOrderPaidToErp.php');
$nativeBridge = $read('addon/phone_shop/app/listener/erp/PhoneShopNativeOrderPaidToErp.php');
foreach ([$assetBridge, $nativeBridge] as $index => $bridge) {
    $assert(str_contains($bridge, "':paid:v1'"), '第' . ($index + 1) . '个商城财务桥必须保留线上付款历史幂等键');
    $assert(str_contains($bridge, "':offline_paid:v1'"), '第' . ($index + 1) . '个商城财务桥必须区分线下现结幂等键');
    $assert(str_contains($bridge, "':credit_confirmed:v1'"), '第' . ($index + 1) . '个商城财务桥必须区分挂账幂等键');
}

$refund = $read('addon/hsx_erp/app/service/admin/ErpExternalSaleRefundedService.php');
$assert(str_contains($refund, "'category_key' => 'sale_refund'"), '退款必须形成真实资金支出');
$assert(str_contains($refund, "'status' => \$fullyReturned ? 'returned' : 'completed'"), '全额退款必须更新销售状态');
$assert(str_contains($refund, "\$cashRefundAmount"), '退款资金支出必须采用实际退款总额');
$assert(str_contains($refund, "\$payload['refund_goods_amount']"), '商品收入与成本冲销必须剔除退还运费');
$assert(str_contains($refund, 'closeReceivableRemainder'), '线上退款必须关闭ERP剩余应收和财务待办');
$assert(str_contains($refund, 'restoreAsset'), 'ERP一物一码商品退款必须恢复库存');
$assert(str_contains($refund, 'erp.asset.returned.v1'), '退款恢复库存后必须通知商城商品状态');
$integration = $read('addon/hsx_erp/app/service/admin/ErpIntegrationService.php');
$assert(str_contains($integration, 'retryFailedExternalRequests'), '商城退款同步ERP失败后必须进入定时补偿');
$assert(str_contains($integration, 'ErpExternalSaleRefundedService::EVENT_NAME'), '入站补偿只能处理明确支持幂等重放的退款契约');
$assert(str_contains($externalContract, "'_retry'"), 'ERP外部请求失败必须记录补偿次数，避免无限重试');

$saleService = $read('addon/hsx_erp/app/service/admin/ErpSaleService.php');
$saleController = $read('addon/hsx_erp/app/adminapi/controller/ErpSale.php');
$saleReturnService = $read('addon/hsx_erp/app/service/admin/ErpSaleReturnService.php');
$saleReturnController = $read('addon/hsx_erp/app/adminapi/controller/ErpSaleReturn.php');
$saleReturnPage = $read('../admin/src/addon/hsx_erp/views/erp/sale_return/list.vue');
$assetSaleListener = $read('addon/hsx_erp/app/listener/ErpSaleCreatedRequested.php');
$assert(str_contains($saleService, "'external_line_id' => (string)\$resolvedItem['external_line_id']"), 'ERP设备销售明细必须保存商城订单行ID');
$assert(str_contains($saleService, 'recordOnlinePaymentAdjustments'), 'ERP设备线上销售必须单独记录客户手续费补款和渠道手续费');
$assert(str_contains($saleService, "'payment_mode' => trim"), 'ERP设备销售单必须保存线上支付方式和金额快照');
$assert(str_contains($assetSaleListener, 'ensureWechatClearingAccount'), 'ERP设备线上支付必须进入微信支付清算账户并直接结清');
foreach (['i.external_goods_id', 'i.external_sku_id', 'i.external_line_id', 'i.quantity', 'i.refunded_amount', 'i.refunded_cost'] as $field) {
    $assert(str_contains($saleService, "'{$field}'"), "ERP销售列表必须返回{$field}");
}
$assert(str_contains($saleService, "- (float)\$row['external_refunded_amount']"), '销售明细实际收入必须扣除商城退款');
$assert(str_contains($saleService, "- (float)\$order['external_refunded_amount']"), '销售单实际收入必须扣除商城退款');
$assert(str_contains($saleController, "['origin_plugin', '']"), '销售查询默认必须包含ERP与商城订单，不能静默限定为ERP来源');
$assert(str_contains($saleService, 'applyOrderPartyFilter') && str_contains($saleService, "where('o.party_id', '=', 0)"), '商城历史销售只有客户名称快照时必须支持安全的主体查询兜底');
$assert(str_contains($saleService, "'sale_channel_key' => 'o.sale_channel_key'") && str_contains($saleService, "'origin_plugin'"), '销售来源与销售渠道筛选必须真正落到销售订单字段');
$assert(str_contains($saleReturnService, 'assertRefundEntry') && str_contains($saleReturnService, "['online', 'wechat_online']"), '商城线上支付必须强制从商城原渠道退款，避免ERP重复退款');
$assert(str_contains($saleReturnService, "whereOr('source_type', 'like', 'phone_shop.%')"), 'ERP销退必须识别商城销售形成的应收事实');
$assert(str_contains($saleReturnController, "['return_to_warehouse_id', 0]") && str_contains($saleReturnPage, 'requiresReturnDestination'), '商城补录设备没有原仓位时，销退必须要求明确实际回库位置');

$pcSale = $read('../admin/src/addon/hsx_erp/views/erp/sale/list.vue');
$mobileSale = $read('../site-uniapp/src/addon/hsx_erp/pages/sale/list.vue');
$mobileDetail = $read('../site-uniapp/src/addon/hsx_erp/pages/sale/detail.vue');
foreach ([$pcSale, $mobileSale, $mobileDetail] as $index => $source) {
    $assert(str_contains($source, 'isExternalGoods'), '第' . ($index + 1) . '个销售界面必须识别商城普通商品');
    $assert(str_contains($source, '商城订单') || str_contains($source, '商城退款'), '第' . ($index + 1) . '个销售界面必须解释商城退款入口');
}
$assert(str_contains($pcSale, 'Number(row.asset_id || 0) > 0'), 'PC销售退货必须只允许真实ERP设备');
$assert(str_contains($mobileSale, 'Number(row.asset_id || 0) <= 0'), '移动销售退货必须拦截非设备商品');

$finance = $read('addon/hsx_erp/app/service/admin/ErpFinanceService.php');
$assert(str_contains($finance, 'settlementOriginReceivableSummary'), 'ERP结算事件必须按商城原订单聚合多张应收');
$assert(str_contains($finance, 'phone_shop.erp_credit_state'), '商城结算回写必须纳入Outbox必需消费者');

echo "[PASS] phone_shop native sale -> ERP finance contract\n";
