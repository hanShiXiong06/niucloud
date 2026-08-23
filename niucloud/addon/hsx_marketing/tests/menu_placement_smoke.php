<?php
declare(strict_types=1);

$menu = require dirname(__DIR__) . '/app/dict/menu/site.php';
$root = (array)($menu[0] ?? []);

if (($root['menu_key'] ?? '') !== 'hsx_marketing') {
    fwrite(STDERR, "[FAIL] 营销中心根菜单不存在\n");
    exit(1);
}
if (($root['parent_select_key'] ?? '') !== '') {
    fwrite(STDERR, "[FAIL] 营销中心不应再依赖临时菜单重排\n");
    exit(1);
}
if (($root['parent_key'] ?? null) !== 'addon') {
    fwrite(STDERR, "[FAIL] 营销中心未使用系统应用目录作为真实父级\n");
    exit(1);
}

$event = require dirname(__DIR__) . '/app/event.php';
$listeners = (array)($event['listen']['ShowCustomer'] ?? []);
if (!in_array('addon\\hsx_marketing\\app\\listener\\system\\ShowCustomerListener', $listeners, true)) {
    fwrite(STDERR, "[FAIL] 营销中心未注册应用分组事件\n");
    exit(1);
}

$listenerFile = dirname(__DIR__) . '/app/listener/system/ShowCustomerListener.php';
$listenerSource = (string)file_get_contents($listenerFile);
if (!str_contains($listenerSource, 'ADDON_CHILD_MENU_DICT_MARKING_ACTIVE') || !str_contains($listenerSource, "'key' => 'hsx_marketing'")) {
    fwrite(STDERR, "[FAIL] 营销中心未归入应用中的营销活动分组\n");
    exit(1);
}

echo "[PASS] hsx marketing menu placement smoke test\n";
