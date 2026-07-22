<?php
declare(strict_types=1);

use addon\hsx_erp\app\support\ErpIdempotency;

$root = dirname(__DIR__);
require_once $root . '/app/support/ErpIdempotency.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$assert(ErpIdempotency::normalize(' payment:abc-123 ') === 'payment:abc-123', 'request_id应去除首尾空白');
$assert(ErpIdempotency::normalize('') === '', '空request_id应兼容旧客户端');
$assert(ErpIdempotency::nullable('') === null, '空request_id写入可空唯一索引时必须转换为NULL');
$assert(ErpIdempotency::nullable(' payment:abc-123 ') === 'payment:abc-123', '有效request_id写入前必须规范化');
$child = ErpIdempotency::child(str_repeat('a', 80), 'difference-payment');
$assert(strlen($child) <= ErpIdempotency::MAX_REQUEST_ID_LENGTH, '派生request_id不得超过数据库字段长度');
$assert(str_ends_with($child, ':difference-payment'), '派生request_id应保留业务后缀');

$sql = (string)file_get_contents($root . '/sql/install.sql');
foreach (['erp_settlement', 'erp_purchase_return', 'erp_sale_return', 'erp_purchase_order', 'erp_sale_order', 'erp_asset_ledger', 'erp_stocktake'] as $table) {
    $assert(str_contains($sql, "CREATE TABLE IF NOT EXISTS `{{prefix}}{$table}`"), "安装SQL缺少{$table}");
}
$assert(substr_count($sql, '`request_id` varchar(80) DEFAULT NULL') >= 7, '核心幂等业务表都必须包含request_id，后续模块可继续扩展');
$assert(substr_count($sql, 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)') >= 7, '核心幂等业务表都必须包含唯一索引，后续模块可继续扩展');

$finance = (string)file_get_contents($root . '/app/service/admin/ErpFinanceService.php');
$schema = (string)file_get_contents($root . '/app/support/ErpSchema.php');
$assert(str_contains($finance, 'runIdempotentSettlement'), '财务写入必须经过统一幂等入口');
$assert(str_contains($finance, "ErpIdempotency::nullable(\$data['request_id'] ?? null)"), '结算单必须把空request_id转换为NULL');
$settlementModel = (string)file_get_contents($root . '/app/model/ErpSettlement.php');
$assert(str_contains($settlementModel, 'setRequestIdAttr') && str_contains($settlementModel, 'ErpIdempotency::nullable'), '结算模型必须兜底规范化幂等键');
$assert(str_contains($schema, "SET `request_id` = NULL WHERE `request_id` = ''"), '升级迁移必须清理历史空结算request_id');
$assert(str_contains($finance, 'existingOffsetId'), '折账必须支持重复请求回放');

$purchase = (string)file_get_contents($root . '/app/service/admin/ErpPurchaseService.php');
$assert(str_contains($purchase, 'existingPurchaseRequest'), '采购开单必须支持重复请求回放');
$assert(str_contains($purchase, 'existingCostAdjustmentRequest'), '成本调整必须支持重复请求回放');

$sale = (string)file_get_contents($root . '/app/service/admin/ErpSaleService.php');
$assert(str_contains($sale, 'existingSaleRequest') && str_contains($sale, 'assertSameSaleRequest'), '销售开单必须支持重复请求回放并拒绝载荷碰撞');

foreach (['ErpPurchaseReturnService.php', 'ErpSaleReturnService.php'] as $service) {
    $source = (string)file_get_contents($root . '/app/service/admin/' . $service);
    $assert(str_contains($source, 'existingReturnId'), "{$service}必须支持重复请求回放");
    $assert(str_contains($source, "'request_id'"), "{$service}必须保存request_id");
}

$apiFiles = [
    dirname($root, 3) . '/niucloud/addon/hsx_erp/admin/api/erp.ts',
    $root . '/admin/api/erp.ts',
    dirname($root, 3) . '/site-uniapp/src/addon/hsx_erp/api/erp.ts',
];
foreach ($apiFiles as $apiFile) {
    $source = (string)file_get_contents($apiFile);
    $assert(str_contains($source, 'withErpRequestId'), basename(dirname($apiFile)) . '/erp.ts必须自动生成request_id');
}

$mobileErpApi = (string)file_get_contents(dirname($root, 3) . '/site-uniapp/src/addon/hsx_erp/api/erp.ts');
$assert(str_contains($mobileErpApi, "withErpRequestId(data, 'purchase')"), '移动采购开单必须自动生成request_id');
$mobileAssetApi = (string)file_get_contents(dirname($root, 3) . '/site-uniapp/src/addon/hsx_erp/api/asset.ts');
$assert(str_contains($mobileAssetApi, "'cost-adjust'"), '移动成本调整必须自动生成request_id');

echo "[PASS] ERP idempotency smoke test\n";
