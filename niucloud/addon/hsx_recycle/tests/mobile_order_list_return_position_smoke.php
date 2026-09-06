<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$workspace = dirname($root, 3);
$listFile = $workspace . '/site-uniapp/src/addon/hsx_recycle/pages/order/list.vue';
$pagingFile = $workspace . '/site-uniapp/src/addon/hsx_recycle/hooks/useRecyclePaging.ts';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$list = (string)file_get_contents($listFile);
$paging = (string)file_get_contents($pagingFile);

$assert(str_contains($list, 'pagingRef, list, reload, refresh, complete'), '回收订单列表未引入原位刷新能力');
$assert(str_contains($list, 'else if (needRefresh.value)'), '列表必须只在详情返回时更新数据');
$assert(str_contains($list, 'refresh()'), '详情返回必须刷新已加载页并保留滚动位置');
$assert(!str_contains($list, 'initialized.value || needRefresh.value'), '不得在每次 onShow 时重载并回到顶部');
$assert(str_contains($paging, 'pagingRef.value?.refresh?.()'), '分页封装必须调用 z-paging refresh 而不是伪装成 reload');

echo "[PASS] recycle mobile order list preserves return position\n";
