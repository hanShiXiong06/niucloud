<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$workspace = dirname($root, 3);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$install = (string)file_get_contents($root . '/sql/install.sql');
$schema = (string)file_get_contents($root . '/app/support/ErpSchema.php');
$policy = (string)file_get_contents($root . '/app/service/admin/ErpWarehousePolicyService.php');
$stock = (string)file_get_contents($root . '/app/service/admin/ErpStockService.php');
$inbound = (string)file_get_contents($root . '/app/listener/ErpDeviceInboundRequested.php');
$consignmentInbound = (string)file_get_contents($root . '/app/service/admin/ErpConsignmentInboundService.php');
$routes = (string)file_get_contents($root . '/app/adminapi/route/route.php');
$pc = (string)file_get_contents($workspace . '/admin/src/addon/hsx_erp/views/erp/stock/list.vue');
$mobileList = (string)file_get_contents($workspace . '/site-uniapp/src/addon/hsx_erp/pages/stock/list.vue');
$mobileDetail = (string)file_get_contents($workspace . '/site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue');
$recycleListener = (string)file_get_contents($workspace . '/niucloud/addon/hsx_recycle/app/listener/downstream/ConsignDeviceBoughtOutListener.php');
$recycleEvent = (string)file_get_contents($workspace . '/niucloud/addon/hsx_recycle/app/event.php');

$assert(str_contains($install, '`ownership_type`') && str_contains($schema, 'idx_site_ownership'), '设备必须持久化物权类型并具备站点物权索引');
$assert(str_contains($policy, '禁止调入代卖仓') && str_contains($policy, '自有设备不能通过库存调拨变成客户物权'), '自有库存必须禁止普通调拨进入代卖仓');
$assert(str_contains($policy, "'buyout'") && str_contains($policy, '生成设备级采购应付'), '代卖转自有必须被识别为买断业务');
$assert(str_contains($policy, "\$sourceType === 'peer' && \$targetType === 'owned'")
    && str_contains($policy, "\$missingFields[] = 'image'")
    && str_contains($policy, "\$missingFields[] = 'retail_price'"), '同行仓转二手机仓必须硬校验图片与零售价');
$assert(str_contains($stock, 'public function transferPreview') && str_contains($stock, 'public function buyoutConsignment'), '库存服务必须提供统一预判和代卖买断事务');
$assert(str_contains($stock, 'ErpPurchaseOrder::create') && str_contains($stock, 'ErpPayable::create')
    && str_contains($stock, "'source_type' => 'purchase_asset'")
    && str_contains($stock, "'asset_id' => (int)\$asset->id"), '代卖买断必须形成财务模块可识别的设备级采购应付');
$assert(str_contains($stock, 'ownership_purchase') && str_contains($stock, 'erp.asset.consignment_bought_out.v1'), '物权变更必须保留设备流水并通过Outbox同步插件');
$assert(str_contains($stock, 'HsxErpConsignmentBuyoutValidate'), '代卖买断必须在ERP事务写入前校验来源插件业务状态');
$assert(str_contains($inbound, 'ErpConsignmentInboundService')
    && !str_contains($inbound, '代卖设备不能生成采购应付，请走代卖登记流程'), '回收插件代卖设备必须真实登记进ERP，不能被静默跳过');
$assert(str_contains($consignmentInbound, "'ownership_type' => 'consigned'")
    && str_contains($consignmentInbound, "'purchase_order_id' => 0")
    && !str_contains($consignmentInbound, 'ErpPayable::create'), '代卖登记只能形成客户物权库存，不能提前生成采购单或应付');
$assert(str_contains($consignmentInbound, 'consignment_inbound')
    && str_contains($consignmentInbound, '未取得物权、未形成应付'), '代卖登记必须留下可查询的库存与操作日志');
$assert(str_contains($routes, 'stock/transfer/preview') && str_contains($routes, 'stock/consignment/buyout'), '调拨预判与买断接口必须注册');
$assert(str_contains($pc, 'previewErpStockTransfer') && str_contains($pc, 'buyoutErpConsignment') && str_contains($pc, '确认回收价'), 'PC库存中心必须显式展示买断价格与二次确认');
$assert(str_contains($mobileList, 'previewMobileStockTransfer') && str_contains($mobileList, 'buyoutMobileConsignment'), '移动库存列表必须使用同一调拨决策和买断接口');
$assert(str_contains($mobileDetail, 'previewMobileStockTransfer') && str_contains($mobileDetail, 'buyoutMobileConsignment'), '移动设备详情必须使用同一调拨决策和买断接口');
$assert(str_contains($recycleListener, 'RecycleConsignmentDict::STATUS_CANCELLED')
    && str_contains($recycleListener, 'RecycleConsignmentLog::create')
    && str_contains($recycleListener, 'consign_to_recycle'), '回收插件必须关闭原代卖单并保留设备与代卖日志');
$assert(str_contains($recycleEvent, 'ConsignmentBuyoutValidateListener'), '回收插件必须注册代卖买断前置校验监听器');

echo "[PASS] ERP warehouse ownership transfer smoke test\n";
