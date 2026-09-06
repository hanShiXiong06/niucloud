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

$sql = (string)file_get_contents($root . '/sql/install.sql');
$schema = (string)file_get_contents($root . '/app/support/ErpSchema.php');
foreach (['credit_policy', 'credit_limit', 'credit_remark', 'credit_update_uid', 'credit_update_at'] as $field) {
    $assert(str_contains($sql, '`' . $field . '`'), '全新安装缺少客户信用字段：' . $field);
    $assert(str_contains($schema, "'{$field}'"), '增量迁移缺少客户信用字段：' . $field);
}
$assert(str_contains($sql, 'idx_site_credit') && str_contains($schema, 'idx_site_credit'), '客户信用策略必须有站点索引');

$config = (string)file_get_contents($root . '/app/service/admin/ErpConfigService.php');
$assert(str_contains($config, "'credit_control'"), '销售规则缺少客户信用控制配置');
foreach (['default_policy', 'min_outstanding_amount', 'min_outstanding_days'] as $field) {
    $assert(str_contains($config, "'{$field}'"), '客户信用控制缺少配置：' . $field);
}

$credit = (string)file_get_contents($root . '/app/service/admin/ErpCustomerCreditService.php');
$assert(str_contains($credit, 'SUM(amount - settled_amount)'), '欠款余额必须从未结应收实时汇总');
$assert(str_contains($credit, 'assertSaleAllowed'), '销售提交必须经过统一信用校验');
$assert(str_contains($credit, "'cash_only'") && str_contains($credit, "'blocked'"), '必须支持仅现结和暂停交易');
$assert(str_contains($credit, '本次收款必须等于销售总额'), '仅现结客户必须强制全额收款');

$sale = (string)file_get_contents($root . '/app/service/admin/ErpSaleService.php');
$assert(str_contains($sale, 'ErpCustomerCreditService::forSite('), '销售事务必须显式绑定站点执行客户信用校验');
$assert(str_contains($sale, ')->assertSaleAllowed('), '销售事务未调用客户信用校验');
$assert(str_contains($sale, 'confirmReceivableItemsInTransaction'), '销售现结必须在销售事务中同时确认收款');
$assert(str_contains($sale, 'lock(true)->findOrEmpty()'), '同一客户销售信用额度校验必须串行化');

$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpSale.php');
foreach (['settle_mode', 'received_amount', 'capital_account_id', 'voucher_urls'] as $field) {
    $assert(str_contains($controller, "['{$field}'"), '销售接口缺少现结字段：' . $field);
}

$pcSale = (string)file_get_contents($workspace . '/admin/src/addon/hsx_erp/views/erp/sale/list.vue');
$mobileSale = (string)file_get_contents($workspace . '/site-uniapp/src/addon/hsx_erp/pages/sale/create.vue');
foreach ([$pcSale, $mobileSale] as $source) {
    $assert(str_contains($source, 'creditProfile'), '销售开单页必须展示客户信用信息');
    $assert(str_contains($source, 'creditAllowedForOrder'), '销售开单页必须结合本单金额判断信用额度');
    $assert(str_contains($source, 'settle_mode'), '销售开单必须提交结算方式');
}

echo "[PASS] ERP customer credit smoke test\n";
