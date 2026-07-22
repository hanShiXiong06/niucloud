<?php
declare(strict_types=1);

$repo = dirname(__DIR__, 4);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$turnoverService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpTurnoverService.php');
$warehousePolicy = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpWarehousePolicyService.php');
$configService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpConfigService.php');
$stockService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpStockService.php');
$financeService = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/service/admin/ErpFinanceService.php');
$routes = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/adminapi/route/route.php');
$pcStock = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/stock/list.vue');
$mobileStock = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/stock/list.vue');
$mobileStockDetail = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue');
$pcSale = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/admin/views/erp/sale/list.vue');
$mobileSale = (string)file_get_contents($repo . '/site-uniapp/src/addon/hsx_erp/pages/sale/create.vue');
$eventConfig = (string)file_get_contents($repo . '/niucloud/addon/hsx_erp/app/event.php');
$shopEventConfig = (string)file_get_contents($repo . '/niucloud/addon/phone_shop/app/event.php');
$directListing = (string)file_get_contents($repo . '/niucloud/addon/phone_shop/app/listener/erp/ErpPublishListing.php');

$assert(str_contains($configService, "'turnover' => ["), '业务规则必须提供库存周转配置');
$assert(str_contains($turnoverService, "'warning_total_cost'"), '周转汇总必须统计预警库存占用成本');
$assert(str_contains($turnoverService, "'average_age_days'"), '周转汇总必须返回平均库龄');
$assert(str_contains($warehousePolicy, "'can_direct_sale'") && str_contains($warehousePolicy, "'can_transfer'") && str_contains($warehousePolicy, "'can_list_mall'"), '仓库规则必须统一输出销售、调拨和商城能力');
$assert(str_contains($turnoverService, "'turnover_action_key'") && str_contains($turnoverService, "'actions'"), '库存周转必须返回设备级和汇总级处理动作');
$assert(str_contains($stockService, 'turnover_level'), '库存列表必须支持周转等级筛选');
$assert(str_contains($stockService, '->decorate('), '库存设备必须由后端统一补齐周转字段');
$assert(str_contains($stockService, 'function adjustRetailPrice') && str_contains($stockService, "'retail_price_adjust'"), '库存周转必须支持独立零售价调整并保留流水');
$assert(str_contains($stockService, 'function transfer(') && str_contains($stockService, "'stock_transfer'"), '库存周转必须支持受仓库规则保护的批量调拨');
$assert(str_contains($stockService, 'listingStatusAfterRefurbish') && str_contains($stockService, "'sale_target' => (string)\$warehouse->default_sale_target"), '整备完成并调仓后必须自动应用目标仓库销售去向与上架规则');
$updateFlowBody = explode('public function syncListing', explode('public function updateFlow', $stockService, 2)[1] ?? '', 2)[0] ?? '';
$assert(!str_contains($updateFlowBody, '$this->syncListing('), '保存商品资料不能强依赖商城或拍照插件，外部同步必须显式触发');
$assert(!str_contains($stockService, 'erp.asset.ready_for_photo.v1'), '库存上架不能再强制派发拍照中台事件');
$assert(str_contains($stockService, "event('HsxErpPublishListing'") && !str_contains($eventConfig, 'PhoneShopDirectListing'), 'ERP必须只发布渠道上架Hook，不能装配商城实现');
$assert(str_contains($shopEventConfig, 'HsxErpPublishListing') && str_contains($shopEventConfig, 'ErpPublishListing'), '已安装商城必须自行装配ERP上架消费监听器');
$assert(str_contains($directListing, 'CoreDeviceIntakeService') && str_contains($directListing, 'DeviceIntakeService())->build'), '商城Hook必须复用现有货源和建品服务，不重复实现商品写入');
$assert(str_contains($stockService, 'function specValueText') && str_contains($stockService, "['label', 'name', 'text'"), '结构化规格必须安全转换为商城文本，不能直接把数组强转为字符串');
$assert(str_contains($stockService, "'action' => 'retail_price_adjust'") && str_contains($stockService, "'source_type' => 'stock_turnover'"), '零售价调整不能伪装成采购成本调整');
$assert(str_contains($financeService, "'turnover' => ["), '经营工作台必须返回库存周转提醒');
$assert(str_contains($routes, "stock/turnover_summary"), '必须开放统一库存周转汇总接口');
$assert(str_contains($pcStock, '周转预警') && str_contains($pcStock, '严重滞销'), 'PC库存中心必须展示周转分层');
$assert(str_contains($pcStock, '批量销售') && str_contains($pcStock, '批量调拨') && str_contains($pcStock, '调整零售价'), 'PC库存中心必须提供可执行的销售、调拨和零售价动作');
$assert(str_contains($mobileStock, 'stock-overview') && str_contains($mobileStock, '周转等级'), '移动库存中心必须展示并筛选周转分层');
$assert(str_contains($mobileStockDetail, '完善商品资料') && str_contains($mobileStockDetail, '库存调拨') && str_contains($mobileStockDetail, '调整零售价'), '移动库存档案必须闭环商城资料、调拨和零售价动作');
$assert(str_contains($pcSale, 'route.query.asset_ids') && str_contains($mobileSale, 'query?.asset_ids'), 'PC和移动销售出库必须支持从库存预选设备');

echo "[PASS] ERP inventory turnover smoke test\n";
