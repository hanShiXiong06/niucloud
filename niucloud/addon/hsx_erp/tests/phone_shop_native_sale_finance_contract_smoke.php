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

$assert(in_array('addon\\phone_shop\\app\\listener\\erp\\PhoneShopNativeOrderPaidToErp', $payListeners, true), '商城付款必须注册原生商品财务桥');
$assert(in_array('addon\\phone_shop\\app\\listener\\erp\\PhoneShopNativeOrderRefundedToErp', $refundListeners, true), '商城退款必须注册原生商品冲销桥');
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
$assert(str_contains($skuModel, 'goods_category,supplier_id,attr_ids'), 'SKU关联商品时必须读取供应商ID，订单快照不能静默丢失');

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

$saleService = $read('addon/hsx_erp/app/service/admin/ErpSaleService.php');
foreach (['i.external_goods_id', 'i.external_sku_id', 'i.external_line_id', 'i.quantity', 'i.refunded_amount', 'i.refunded_cost'] as $field) {
    $assert(str_contains($saleService, "'{$field}'"), "ERP销售列表必须返回{$field}");
}
$assert(str_contains($saleService, "- (float)\$row['external_refunded_amount']"), '销售明细实际收入必须扣除商城退款');
$assert(str_contains($saleService, "- (float)\$order['external_refunded_amount']"), '销售单实际收入必须扣除商城退款');

$pcSale = $read('../admin/src/addon/hsx_erp/views/erp/sale/list.vue');
$mobileSale = $read('../site-uniapp/src/addon/hsx_erp/pages/sale/list.vue');
$mobileDetail = $read('../site-uniapp/src/addon/hsx_erp/pages/sale/detail.vue');
foreach ([$pcSale, $mobileSale, $mobileDetail] as $index => $source) {
    $assert(str_contains($source, 'isExternalGoods'), '第' . ($index + 1) . '个销售界面必须识别商城普通商品');
    $assert(str_contains($source, '商城订单') || str_contains($source, '商城退款'), '第' . ($index + 1) . '个销售界面必须解释商城退款入口');
}
$assert(str_contains($pcSale, 'Number(row.asset_id || 0) > 0'), 'PC销售退货必须只允许真实ERP设备');
$assert(str_contains($mobileSale, 'Number(row.asset_id || 0) <= 0'), '移动销售退货必须拦截非设备商品');

echo "[PASS] phone_shop native sale -> ERP finance contract\n";
