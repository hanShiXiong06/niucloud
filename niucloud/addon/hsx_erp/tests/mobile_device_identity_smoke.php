<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$deviceText = $read('site-uniapp/src/addon/hsx_erp/hooks/useErpDeviceText.ts');
$assert(str_contains($deviceText, '`IMEI ${String(row.imei).trim()}`'), '公共设备摘要必须优先展示IMEI');
$assert(str_contains($deviceText, 'return [identity, spec]'), '公共设备摘要必须同时保留串号与规格');

$offsetModal = $read('site-uniapp/src/addon/hsx_erp/components/ErpOffsetConfirmModal.vue');
foreach (['客户给我的设备', '我给客户的设备', 'financeDevices(item)', 'deviceSerial(device)', '非设备业务，无关联 IMEI'] as $needle) {
    $assert(str_contains($offsetModal, $needle), '折账弹窗缺少双向设备识别信息：' . $needle);
}

foreach (['receivable', 'payable'] as $type) {
    $list = $read('site-uniapp/src/addon/hsx_erp/pages/' . $type . '/list.vue');
    $assert(str_contains($list, 'class="card-actions" @click.stop @tap.stop'), $type . '操作区必须阻止卡片点击冒泡');
    $assert(str_contains($list, '@click.stop="openOffset(row)"'), $type . '折账按钮必须阻止跳转详情');
}

$stockDetail = $read('site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue');
$assert(str_contains($stockDetail, '<text class="label">IMEI</text>'), '设备档案必须独立展示IMEI');
$assert(str_contains($stockDetail, 'formatErpDate, formatErpTime'), '设备档案必须使用统一数字时间格式');
$assert(!str_contains($stockDetail, 'toLocale'), '设备档案不得依赖安卓WebView本地化时间');

$purchaseReturn = $read('site-uniapp/src/addon/hsx_erp/pages/purchase_return/create.vue');
$assert(str_contains($purchaseReturn, 'return formatErpTime(ts)'), '采购退货时间必须使用统一数字格式');
$assert(!str_contains($purchaseReturn, 'toLocaleString'), '采购退货不得显示系统英文时间');

echo "[PASS] ERP mobile device identity smoke test\n";
