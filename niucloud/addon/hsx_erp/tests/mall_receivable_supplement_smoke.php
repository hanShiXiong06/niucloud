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

$finance = $read('addon/hsx_erp/app/service/admin/ErpFinanceService.php');
$ledger = $read('addon/hsx_erp/app/service/admin/ErpLedgerService.php');
$stock = $read('addon/hsx_erp/app/service/admin/ErpStockService.php');
$sql = $read('addon/hsx_erp/sql/install.sql');
$schema = $read('addon/hsx_erp/app/support/ErpSchema.php');
$routes = $read('addon/hsx_erp/app/adminapi/route/route.php');
$menu = $read('addon/hsx_erp/app/dict/menu/site.php');
$controller = $read('addon/hsx_erp/app/adminapi/controller/ErpFinance.php');
$receivablePage = $read('../admin/src/addon/hsx_erp/views/erp/receivable/list.vue');
$supplementDialog = $read('../admin/src/addon/hsx_erp/components/ErpMallReceivableSupplementDialog.vue');

$assert(str_contains($finance, 'supplementMallReceivableDetails'), '商城来源应收必须支持人工补全成交设备事实');
$assert(str_contains($finance, '只有商城来源且缺少资料的应收款允许补录'), '补录入口必须禁止绕过ERP正常库存销售');
$assert(!str_contains(substr($finance, strpos($finance, 'function supplementMallReceivableDetails'), 9000), 'ErpPayable::create'), '商城成交补录不得伪造采购应付');
$assert(str_contains($finance, '商城补录资产') && str_contains($finance, 'mall_sale_detail_supplement'), '商城补录必须带明确标识并写操作留痕');
$assert(str_contains($finance, 'ErpAsset::create') && str_contains($finance, "'asset_ids' => \$assetIds"), '商城补录必须生成可追踪的ERP已售资产并关联审计结果');
$assert(str_contains($finance, '商城成交后财务补录历史入库事实') && str_contains($finance, '商城成交设备补录销售出库'), '商城补录必须留下历史入库与销售出库资产流水');
$assert(str_contains($finance, '$this->refreshSaleFinance($saleOrderId)') && str_contains($finance, "'finance_sync' =>"), '商城补录后必须按真实应收结算刷新销售单并记录前后状态');
$assert(str_contains($finance, '$this->saleOrderIdFromReceivable($receivable)') && str_contains($finance, '$this->isMallReceivable($receivable)'), '商城挂账后续收款必须复用统一销售关联规则');
$assert(str_contains($stock, 'isSaleReceivableRow') && str_contains($stock, "'received_amount'"), '库存生命周期必须识别商城应收，并兼容线上现结无应收明细');
$assert(str_contains($finance, "'skip_performance' => true") && str_contains($ledger, "empty(\$data['skip_performance'])"), '历史资产补录不得误记为员工当期采购绩效');
$assert(str_contains($finance, 'receivableItemSettledAmount') && str_contains($finance, "'sale_item_id' =>"), '无ERP资产的多台商城设备必须按销售明细独立核销');
$assert(str_contains($ledger, "'sale_item_id' => (int)(\$data['sale_item_id'] ?? 0)"), '账目服务必须真正写入销售明细ID');
$assert(str_contains($sql, '`sale_item_id` int NOT NULL DEFAULT 0 COMMENT \'销售明细ID，商城缺失ERP资产时用于分台核销\''), '全新安装账目流水必须包含销售明细ID');
$assert(str_contains($schema, "'sale_item_id' => \"`sale_item_id` int NOT NULL DEFAULT 0"), '升级迁移必须补齐账目流水销售明细ID');
$assert(str_contains($routes, 'supplement_sale_details') && str_contains($controller, 'supplementReceivableSaleDetails'), 'ERP应收接口必须暴露商城成交资料补录动作');
$assert(str_contains($menu, 'hsx_erp_receivable_supplement_sale_details') && str_contains($menu, 'erp/finance/receivable/<id>/supplement_sale_details'), '商城成交资料补录接口必须登记菜单权限，避免线上403');
$assert(str_contains($receivablePage, 'can_supplement_sale_detail') && str_contains($receivablePage, '商城来源成交'), 'ERP应收详情必须明确展示商城补录入口与资料状态');
$assert(str_contains($supplementDialog, '成交价合计必须等于应收金额') && str_contains($supplementDialog, '不会重复生成应收'), '补录表单必须解释边界并强制金额对平');

echo "[PASS] mall receivable manual detail supplement contract\n";
