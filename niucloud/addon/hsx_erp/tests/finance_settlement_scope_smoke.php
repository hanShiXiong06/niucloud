<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$finance = (string)file_get_contents($root . '/app/service/admin/ErpFinanceService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpFinance.php');

foreach ([
    'partyPaymentTargets',
    'applyPayableBatchScope',
    'assertSinglePayableBatch',
    '整体付款必须提交 payable_ids',
    '当前一个来源批次',
] as $needle) {
    $assert(str_contains($finance, $needle), '整体付款缺少显式应付或来源批次约束：' . $needle);
}
foreach (["['payable_id', 0]", "['payable_ids', []]", "['source_type', '']", "['batch_no', '']"] as $needle) {
    $assert(str_contains($controller, $needle), '整体付款接口缺少兼容的显式核销参数：' . $needle);
}

$assert(str_contains($finance, 'private function allPayableItems'), '折账候选必须提供同主体全部应付事实');
foreach (['purchase_asset', 'refurbish', 'sale_return', 'p.category_key', 'p.origin_plugin'] as $needle) {
    $assert(str_contains($finance, $needle), '全部应付候选缺少业务类型或来源字段：' . $needle);
}
$assert(str_contains($finance, "return \$this->allPayableItems(\$partyId, \$where)"), '未指定来源时不得再默认只查采购应付');
$assert(str_contains($finance, "'p.id as payable_id'"), '折账与付款候选必须返回真实 payable_id');
$assert(str_contains($finance, "['payable_ids']"), '应付批次列表必须返回当前卡片可核销的显式 payable_ids');

foreach ([
    'appendFinanceDevices',
    'settlementTargetDeviceMap',
    'receivableDirectAssetIds',
    'receivablePurchaseReturnIds',
    "COALESCE(NULLIF(i.imei,''), a.imei) as imei",
    "'devices' => \$targetDeviceMap",
] as $needle) {
    $assert(str_contains($finance, $needle), '折账候选或结算快照缺少关联设备与IMEI：' . $needle);
}

foreach ([
    '应付款核销金额无效或已超过剩余应付',
    '应收款核销金额无效或已超过剩余应收',
    '部分应付款不存在、已结清或已变化',
    '部分应收款不存在、已结清或已变化',
    'request_id已用于不同金额的结算请求',
    'request_id已用于其他应收应付明细的结算请求',
] as $needle) {
    $assert(str_contains($finance, $needle), '结算缺少超额、重复或幂等碰撞保护：' . $needle);
}

$assert(str_contains($finance, "'asset_id' => \$this->assetIdFromPayable(\$payable)"), '付款账目轨迹必须记录应付关联设备');
$assert(str_contains($finance, 'Db::transaction(function () use ($payableId'), '单笔付款必须在事务中执行');
$assert(str_contains($finance, 'Db::transaction(function () use ($receivableId'), '单笔收款必须在事务中执行');
$assert(str_contains($finance, '实际收付款必须选择资金账户'), '实际收付款必须强制选择资金账户');

foreach (['erp.settlement.completed.v1', 'queueSettlementCompletedEvent', 'flushSettlementDomainEvents', "'targets' => \$targets", "'source_meta' => \$sourceMeta", "'asset' =>", "'assets' => \$assets"] as $needle) {
    $assert(str_contains($finance, $needle), '结算完成 outbox 事件缺少事务快照或提交后派发：' . $needle);
}

$assert(str_contains($finance, "sum(Db::raw('i.cost + i.profit'))"), '经营看板有效销售额必须按净销售收入计算');
foreach (['sale_original_amount', 'sale_compensation_amount', 'original_total_amount', 'compensation_amount'] as $needle) {
    $assert(str_contains($finance, $needle), '净销售额必须保留原价与补差审计字段：' . $needle);
}

echo "[PASS] ERP finance settlement scope smoke test\n";
