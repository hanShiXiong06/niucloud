<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $root . '/app',
    FilesystemIterator::SKIP_DOTS
));
$ddlPattern = '/\b(?:CREATE\s+TABLE|ALTER\s+TABLE|SHOW\s+COLUMNS|SHOW\s+INDEX)\b/i';
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_ends_with($path, '/app/support/ErpSchema.php')) {
        continue;
    }
    $source = (string)file_get_contents($path);
    $assert(!preg_match($ddlPattern, $source), '业务请求代码不得执行DDL：' . $path);
}

$addon = (string)file_get_contents($root . '/Addon.php');
$assert(str_contains($addon, 'ErpSchema::migrate();'), '插件安装/升级入口必须调用集中迁移');

$schema = (string)file_get_contents($root . '/app/support/ErpSchema.php');
foreach (['erp_settlement', 'erp_settlement_link', 'erp_payable', 'erp_receivable', 'erp_purchase_return', 'erp_purchase_return_item', 'erp_sale_return', 'erp_purchase_order', 'erp_sale_order', 'erp_asset_ledger', 'erp_account_ledger', 'erp_money_ledger'] as $table) {
    $assert(str_contains($schema, "'{$table}'"), "集中迁移缺少{$table}");
}
$assert(str_contains($schema, 'erp_catalog_product_master') && str_contains($schema, 'erp_site_catalog_product'), '集中迁移必须包含标准目录和站点目录');
$assert(!str_contains($schema, 'erp_category_mapping') && !str_contains($schema, 'erp_goods_category'), '旧分类及映射表已退役，不得继续创建');
$assert(str_contains($schema, "'balance_after'"), '集中迁移必须补齐账目流水余额字段');
$assert(str_contains($schema, "'refund_receivable_amount'"), '集中迁移必须补齐退货退款应收审计字段');
$assert(str_contains($schema, "'policy_json'"), '集中迁移必须保存采购退货策略快照');
$assert(str_contains($schema, "'sale_channel_key'"), '集中迁移必须保存动态销售渠道稳定编码');
$assert(str_contains($schema, "'category_source_plugin'"), '集中迁移必须保存动态收支分类来源快照');
foreach (['origin_plugin', 'origin_type', 'biz_scene', 'category_statement_group', 'channel_code', 'business_reason'] as $field) {
    $assert(str_contains($schema, "'{$field}'"), '集中迁移必须补齐财务事实来源字段：' . $field);
}
$assert(str_contains($schema, 'backfillFinanceSourceSnapshots'), '升级必须回填历史应收应付来源快照');
$assert(str_contains($schema, "erp_settlement_link` l") && str_contains($schema, "erp_money_ledger` m"), '历史核销明细和资金流水必须补齐收支分类');
foreach (['retail_price', 'remark_public', 'remark_internal'] as $field) {
    $assert(str_contains($schema, "'{$field}'"), '集中迁移必须补齐设备流转字段：' . $field);
}
$assert(substr_count($schema, "'manager_uid'") >= 2, '集中迁移必须补齐仓库和库位负责人字段');
$assert(substr_count($schema, "'idx_site_manager'") >= 2, '集中迁移必须补齐仓库和库位负责人索引');
$assetBlockStart = strpos($schema, "'erp_asset' => [");
$assetBlockEnd = strpos($schema, "'erp_sale_order' => [", $assetBlockStart ?: 0);
$assetBlock = $assetBlockStart !== false && $assetBlockEnd !== false
    ? substr($schema, $assetBlockStart, $assetBlockEnd - $assetBlockStart)
    : '';
foreach (['retail_price', 'stock_in_at', 'remark_public', 'remark_internal'] as $field) {
    $assert(str_contains($assetBlock, "'{$field}'"), 'erp_asset增量迁移必须补齐新装表已有字段：' . $field);
}
$assert(str_contains($schema, 'backfillHistoricalFinanceState'), '升级必须修复历史设备级账目与零余额状态');
$assert(
    str_contains($schema, "l.action = 'refurbish'")
    && str_contains($schema, 'HAVING COUNT(DISTINCT l.asset_id) = 1')
    && str_contains($schema, "p.source_type = 'refurbish' AND p.asset_id = 0"),
    '历史整备应付只能依据唯一整备资产流水回填设备'
);
$assert(
    substr_count($schema, "SET status = 'settled'") >= 2
    && substr_count($schema, "status NOT IN ('settled', 'void') AND amount <= settled_amount") >= 2,
    '历史零余额应收应付必须结清且保留作废审计状态'
);
$assert(str_contains($schema, "'idx_action_source_no'"), '集中迁移必须为整备来源号回填建立索引');
$assert(str_contains($schema, 'p.party_id = l.party_id') && str_contains($schema, 'ABS(p.amount - ABS(l.cost_delta)) < 0.01'), '流水ID回填必须校验往来主体与整备金额，避免ID碰撞误绑');
$assert(substr_count($schema, "'uk_site_request'") === 6, '集中迁移必须创建六个幂等唯一索引');

$sql = (string)file_get_contents($root . '/sql/install.sql');
$assert(str_contains($sql, '`balance_after` decimal(14,2)'), '全新安装结构必须包含balance_after');
$assert(str_contains($sql, '`refund_receivable_amount` decimal(12,2)'), '全新安装结构必须包含退款应收拆分字段');
$assert(str_contains($sql, '`sale_channel_key` varchar(80)'), '全新安装结构必须包含动态销售渠道编码');
$assert(str_contains($sql, '`category_key` varchar(80)'), '全新安装结构必须包含动态收支分类编码');
$assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}erp_catalog_product_master`') && str_contains($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}erp_site_catalog_product`'), '全新安装必须以ERP商品目录为唯一主数据');
$assert(!str_contains($sql, 'erp_category_mapping') && !str_contains($sql, 'erp_goods_category'), '全新安装不得包含旧分类模块');
foreach (['origin_plugin', 'origin_type', 'biz_scene', 'category_statement_group', 'channel_code', 'business_reason'] as $field) {
    $assert(str_contains($sql, '`' . $field . '`'), '全新安装结构必须包含财务事实来源字段：' . $field);
}
foreach (['retail_price', 'remark_public', 'remark_internal'] as $field) {
    $assert(str_contains($sql, '`' . $field . '`'), '全新安装结构必须包含设备流转字段：' . $field);
}
$assert(substr_count($sql, '`manager_uid` int NOT NULL DEFAULT 0') >= 2, '全新安装结构必须包含仓库和库位负责人');
$installAssetStart = strpos($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}erp_asset`');
$installAssetEnd = strpos($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}erp_asset_ledger`', $installAssetStart ?: 0);
$installAssetBlock = $installAssetStart !== false && $installAssetEnd !== false
    ? substr($sql, $installAssetStart, $installAssetEnd - $installAssetStart)
    : '';
foreach (['retail_price', 'stock_in_at', 'remark_public', 'remark_internal'] as $field) {
    $assert(str_contains($installAssetBlock, '`' . $field . '`'), 'erp_asset新装表必须包含设备流转字段：' . $field);
}
$assert(str_contains($sql, 'KEY `idx_action_source_no` (`site_id`,`action`,`source_no`)'), '全新安装结构必须包含整备来源号索引');
$uninstall = (string)file_get_contents($root . '/sql/uninstall.sql');
foreach (['erp_catalog_import_task', 'erp_site_catalog_product', 'erp_catalog_product_master', 'erp_goods_category', 'erp_category_mapping'] as $table) {
    $assert(str_contains($uninstall, 'DROP TABLE IF EXISTS `{{prefix}}' . $table . '`'), '卸载必须清理目录及旧分类残留：' . $table);
}
$assert(str_contains($addon, "installAddonSchedule('hsx_erp')") && str_contains($addon, "uninstallAddonSchedule('hsx_erp')"), '插件生命周期必须安装和卸载领域事件重试任务');

$info = json_decode((string)file_get_contents($root . '/info.json'), true);
$assert(($info['version'] ?? '') === '0.0.1', '开发阶段版本必须保持0.0.1');

echo "[PASS] ERP migration governance smoke test\n";
