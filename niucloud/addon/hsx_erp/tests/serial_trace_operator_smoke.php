<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$stockService = file_get_contents($root . '/app/service/admin/ErpStockService.php');
$mobileTimeline = file_get_contents(dirname($root, 3) . '/site-uniapp/src/addon/hsx_erp/components/ErpAssetLifecycleTimeline.vue');
$pcStock = file_get_contents(dirname($root, 3) . '/admin/src/addon/hsx_erp/views/erp/stock/list.vue');
$sourceService = file_get_contents($root . '/app/service/admin/ErpFinanceSourceService.php');

$assertions = [
    '后端串号流水提供统一操作人展示字段' => str_contains($stockService, "\$row['operator_display']"),
    '历史流水缺少操作人时标识系统自动' => str_contains($stockService, "'系统自动'"),
    '移动端生命周期逐步显示操作人' => str_contains($mobileTimeline, '操作人：{{ operatorLabel(node) }}'),
    'PC 设备流水展示操作人' => str_contains($pcStock, 'label="操作人"'),
    'PC 串号追踪稳定更新响应式行数据' => str_contains($pcStock, 'serialTrace.data.splice'),
    '历史业务场景不会作为渠道技术值展示' => str_contains($sourceService, '$internalScenes'),
];

foreach ($assertions as $message => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
}

echo "[PASS] ERP serial trace operator and source display smoke test\n";
