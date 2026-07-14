<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\recycle_quote_spider\app\service\core\QuoteDuplicateMergeService;
use addon\recycle_quote_spider\app\support\QuoteSyncIdentity;
use think\facade\Db;

$app = new think\App();
$app->initialize();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$siteId = 990000 + random_int(1, 9000);
$sourceId = 990000 + random_int(1, 9000);
$now = time();
$itemData = [
    'id' => 'today-100',
    'url' => '10',
    'brand' => 'OPPO',
    'name' => 'OPPO',
    'parent_name' => '废旧手机回收报价',
    'type' => 3,
];

Db::startTrans();
try {
    $canonicalItemId = (int)Db::name('recycle_quote_spider_item')->insertGetId([
        'site_id' => $siteId,
        'source_id' => $sourceId,
        'source_item_id' => QuoteSyncIdentity::itemKey($itemData),
        'source_url_id' => '10',
        'brand' => 'OPPO',
        'name' => 'OPPO',
        'parent_name' => '废旧手机回收报价',
        'quote_type' => '3',
        'view_count' => 5,
        'create_at' => $now,
        'update_at' => $now,
    ]);
    $duplicateItemId = (int)Db::name('recycle_quote_spider_item')->insertGetId([
        'site_id' => $siteId,
        'source_id' => $sourceId,
        'source_item_id' => 'yesterday-100',
        'source_url_id' => '10',
        'brand' => 'OPPO',
        'name' => 'OPPO',
        'parent_name' => '废旧手机回收报价',
        'quote_type' => '3',
        'view_count' => 7,
        'create_at' => $now - 86400,
        'update_at' => $now - 86400,
    ]);

    $canonicalRowId = (int)Db::name('recycle_quote_spider_row')->insertGetId([
        'site_id' => $siteId,
        'source_id' => $sourceId,
        'item_id' => $canonicalItemId,
        'source_row_id' => QuoteSyncIdentity::rowKey(['tab' => 'Find系列', 'name' => 'Find X9 Pro']),
        'tab' => 'Find系列',
        'model_name' => 'Find X9 Pro',
        'create_at' => $now,
        'update_at' => $now,
    ]);
    $duplicateRowId = (int)Db::name('recycle_quote_spider_row')->insertGetId([
        'site_id' => $siteId,
        'source_id' => $sourceId,
        'item_id' => $duplicateItemId,
        'source_row_id' => 'volatile-row-1',
        'tab' => 'Find系列',
        'model_name' => 'Find X9 Pro',
        'create_at' => $now - 86400,
        'update_at' => $now - 86400,
    ]);
    $legacyOnlyRowId = (int)Db::name('recycle_quote_spider_row')->insertGetId([
        'site_id' => $siteId,
        'source_id' => $sourceId,
        'item_id' => $duplicateItemId,
        'source_row_id' => 'volatile-row-2',
        'tab' => 'Reno系列',
        'model_name' => 'Reno Legacy',
        'is_show' => 1,
        'create_at' => $now - 86400,
        'update_at' => $now - 86400,
    ]);

    foreach ([
        [$canonicalItemId, $canonicalRowId, 'Find X9 Pro', '2026-07-12'],
        [$duplicateItemId, $duplicateRowId, 'Find X9 Pro', '2026-07-11'],
        [$duplicateItemId, $legacyOnlyRowId, 'Reno Legacy', '2026-07-11'],
    ] as [$itemId, $rowId, $modelName, $recordDate]) {
        Db::name('recycle_quote_spider_price_history')->insert([
            'site_id' => $siteId,
            'source_id' => $sourceId,
            'item_id' => $itemId,
            'row_id' => $rowId,
            'model_name' => $modelName,
            'columns' => json_encode(['A价'], JSON_UNESCAPED_UNICODE),
            'final_prices' => json_encode([100], JSON_UNESCAPED_UNICODE),
            'record_date' => $recordDate,
            'create_at' => $now,
        ]);
    }

    (new QuoteDuplicateMergeService())->merge($siteId, $sourceId, $canonicalItemId, $itemData);

    $assert((int)Db::name('recycle_quote_spider_item')->where('id', $duplicateItemId)->count() === 0, '重复报价项应被删除');
    $assert((int)Db::name('recycle_quote_spider_item')->where('id', $canonicalItemId)->value('view_count') === 12, '重复报价项浏览量应合并');
    $assert((int)Db::name('recycle_quote_spider_row')->where('id', $duplicateRowId)->count() === 0, '同型号重复行应被删除');
    $assert((int)Db::name('recycle_quote_spider_price_history')->where('row_id', $canonicalRowId)->count() === 2, '旧型号历史应合并到当前型号');

    $legacyRow = Db::name('recycle_quote_spider_row')->where('id', $legacyOnlyRowId)->find();
    $assert((int)($legacyRow['item_id'] ?? 0) === $canonicalItemId, '旧报价独有型号应迁移到当前报价项');
    $assert((int)($legacyRow['is_show'] ?? 1) === 0, '当前已不存在的旧型号应保留历史但停止展示');
    $assert((int)Db::name('recycle_quote_spider_price_history')->where('row_id', $legacyOnlyRowId)->value('item_id') === $canonicalItemId, '旧型号快照应改绑当前报价项');

    echo "[PASS] recycle quote spider duplicate merge integration test\n";
} finally {
    Db::rollback();
}
