<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/support/QuoteSyncIdentity.php';

use addon\recycle_quote_spider\app\support\QuoteSyncIdentity;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$yesterday = [
    'id' => '7735573',
    'url' => '10',
    'brand' => 'OPPO',
    'name' => 'OPPO',
    'parent_name' => '废旧手机回收报价',
    'type' => 3,
];
$today = array_merge($yesterday, ['id' => '8835573']);

$assert(QuoteSyncIdentity::itemKey($yesterday) === QuoteSyncIdentity::itemKey($today), '上游每日更换报价 ID 时必须命中同一报价项');
$assert(QuoteSyncIdentity::itemKey($today) !== QuoteSyncIdentity::itemKey(array_merge($today, ['name' => 'vivo', 'brand' => 'vivo'])), '同一详情分组下的不同报价名称不能误合并');
$assert(strlen(QuoteSyncIdentity::itemKey($today)) <= 64, '报价项稳定键必须满足数据库字段长度');

$oldRow = ['id' => '7735574', 'tab' => 'Find系列', 'name' => 'Find X9 Pro'];
$newRow = ['id' => '8835574', 'tab' => ' Find系列 ', 'mobile_name' => 'Find   X9 Pro'];
$assert(QuoteSyncIdentity::rowKey($oldRow, 0) === QuoteSyncIdentity::rowKey($newRow, 99), '型号稳定键不能依赖上游行 ID 或列表顺序');
$assert(QuoteSyncIdentity::rowKey($newRow) !== QuoteSyncIdentity::rowKey(array_merge($newRow, ['tab' => 'Reno系列'])), '不同系列的同名型号不能误合并');

$noStableField = ['id' => 'legacy-100', 'name' => '临时报价'];
$assert(QuoteSyncIdentity::itemKey($noStableField) === 'legacy-100', '缺少稳定详情分组时应兼容旧的第三方 ID');

echo "[PASS] recycle quote spider stable identity smoke test\n";
