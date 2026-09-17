<?php
declare(strict_types=1);

// Local-only fixtures. No source sync or notifications; every inserted row is rolled back.
if (getenv('QUOTE_SEARCH_ROLLBACK_TEST') !== '1') {
    fwrite(STDERR, "Set QUOTE_SEARCH_ROLLBACK_TEST=1\n");
    exit(2);
}
require dirname(__DIR__, 4) . '/niucloud/vendor/autoload.php';

use addon\recycle_quote_spider\app\service\api\QuoteQueryService;
use addon\recycle_quote_spider\app\support\QuoteSearch;
use think\facade\Db;

(new think\App())->initialize();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    exit(1);
});
if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Local database required');
}
$site = 900000916;
$tables = ['recycle_quote_spider_source', 'recycle_quote_spider_category', 'recycle_quote_spider_item', 'recycle_quote_spider_row'];
foreach ($tables as $table) {
    if (Db::name($table)->whereIn('site_id', [$site, $site + 1])->count()) throw new RuntimeException('Fixture site already in use: ' . $table);
    $meta = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix') . $table]);
    if (strtoupper((string)($meta[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException('Transactional table required: ' . $table);
}
request()->siteId($site);
request()->memberId(0);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
    echo "PASS $message\n";
};
$add = static fn(string $table, array $values): int => (int)Db::name('recycle_quote_spider_' . $table)->insertGetId($values + ['site_id' => $site]);
Db::startTrans();
try {
    $source = $add('source', ['source_key' => 'quote-test-a', 'source_name' => '测试报价源', 'status' => 1]);
    $disabledSource = $add('source', ['source_key' => 'quote-test-b', 'source_name' => '停用报价源', 'status' => 0]);
    $category = $add('category', ['source_id' => $source, 'source_category_id' => 'quote-test-root', 'name' => '手机报价', 'is_show' => 1]);
    $hiddenCategory = $add('category', ['source_id' => $source, 'source_category_id' => 'quote-test-hidden', 'name' => '隐藏分类', 'is_show' => 0]);
    $hiddenChild = $add('category', ['source_id' => $source, 'source_category_id' => 'quote-test-child', 'name' => '不可见子分类', 'is_show' => 1, 'parent_id' => $hiddenCategory]);
    $makeItem = static function (string $name, array $extra = []) use ($add, $source, $category): int {
        return $add('item', $extra + ['source_id' => $source, 'category_id' => $category, 'source_item_id' => uniqid('sheet-'), 'name' => $name, 'is_show' => 1, 'is_image_quote' => 0, 'notice_text' => '以质检为准']);
    };
    $makeRow = static function (int $item, array $extra = []) use ($add, $source): int {
        return $add('row', $extra + [
            'source_id' => $source, 'item_id' => $item, 'source_row_id' => uniqid('row-'),
            'model_name' => '17ProMax', 'brand' => '苹果', 'is_show' => 1,
            'columns' => json_encode(['靓机', '小花', '充新'], JSON_UNESCAPED_UNICODE),
            'final_prices' => json_encode(['靓机' => 5500, '小花' => 0, '充新' => '暂停收购'], JSON_UNESCAPED_UNICODE),
            'source_prices' => '{"secret":999}', 'manual_prices' => '{"secret":998}',
            'raw_data' => json_encode(['内存' => '256GB', 'secret' => 'private'], JSON_UNESCAPED_UNICODE),
            'remark' => '换屏另议', 'create_at' => strtotime('2026-09-15 09:00:00'), 'update_at' => strtotime('2026-09-16 10:30:00'),
        ]);
    };
    $firstItem = $makeItem('苹果靓机报价');
    $secondItem = $makeItem('苹果问题机报价');
    $firstRow = $makeRow($firstItem);
    $makeRow($secondItem, ['model_name' => 'iPhone 17 Pro Max']);
    $manualItem = $makeItem('手工导入报价', ['source_id' => 0, 'category_id' => 0]);
    $makeRow($manualItem, ['source_id' => 0]);
    $makeRow($makeItem('隐藏报价单', ['is_show' => 0]));
    $makeRow($firstItem, ['is_show' => 0]);
    $makeRow($makeItem('停用源', ['source_id' => $disabledSource]), ['source_id' => $disabledSource]);
    $makeRow($makeItem('隐藏分类', ['category_id' => $hiddenCategory]));
    $makeRow($makeItem('隐藏父分类', ['category_id' => $hiddenChild]));
    $makeRow($makeItem('图片报价', ['is_image_quote' => 1]));
    $makeRow($makeItem('另一个站点', ['site_id' => $site + 1]), ['site_id' => $site + 1]);
    // Even a forged cross-site item reference must never expose the foreign row.
    $makeRow($firstItem, ['site_id' => $site + 1]);

    $service = new QuoteQueryService();
    if (getenv('QUOTE_SEARCH_DEBUG') === '1') {
        Db::listen(static function ($sql): void { echo $sql . "\n"; });
        echo 'site=' . request()->siteId() . '; term=' . QuoteSearch::modelTerm(' 苹果 iPhone 17 PRO Max ') . "\n";
    }
    $result = $service->search(['keyword' => ' 苹果 iPhone 17 PRO Max ']);
    $assert($result['total'] === 3, 'Matches all visible sheets, ignores case/spaces/duplicate Apple prefix; total=' . $result['total']);
    $assert(count(array_unique(array_column($result['data'], 'item_id'))) === 3, 'Same model in different quote sheets is retained');
    $assert($result['data'][0]['capacity'] === '256GB', 'Capacity extracted from structured raw data');
    $assert($result['data'][0]['price_date'] === '2026-09-16', 'Price date comes from the matched row');
    $assert($result['data'][0]['final_prices']['小花'] === 0, 'Zero price preserved');
    $assert($result['data'][0]['final_prices']['充新'] === '暂停收购', 'Non-numeric pricing remarks preserved');
    foreach ($result['data'] as $row) {
        $assert(!array_intersect(['source_prices', 'manual_prices', 'raw_data', 'site_id'], array_keys($row)), 'Only public display fields returned');
    }
    $assert($service->search(['keyword' => '17p', 'source_id' => $source])['total'] === 2, 'Configured source limits search');
    $assert($service->search(['keyword' => '17p', 'source_id' => $disabledSource])['total'] === 0, 'Disabled source cannot be queried directly');
    $assert($service->search(['keyword' => " \t\n "])['total'] === 0, 'Empty input does not enumerate quotations');
    $assert($service->search(['keyword' => ['unexpected']])['total'] === 0, 'Malformed keyword arrays do not trigger warnings or enumerate data');
    $assert($service->search(['keyword' => "%' OR 1=1 --"])['total'] === 0, 'Input is parameter-bound');
    $assert($service->search(['keyword' => '%'])['total'] === 0, 'Percent does not become a wildcard');
    $assert($service->search(['keyword' => '_'])['total'] === 0, 'Underscore does not become a wildcard');
    $first = $service->search(['keyword' => '17p', 'limit' => 1, 'page' => 1]);
    $second = $service->search(['keyword' => '17p', 'limit' => 1, 'page' => 2]);
    $assert($first['data'][0]['id'] !== $second['data'][0]['id'] && $first['total'] === 3, 'Stable pagination preserves all matches');
    $assert($service->search(['keyword' => '17p', 'limit' => 500])['per_page'] === 30, 'Page size bounded to 30');
    Db::name('recycle_quote_spider_row')->where('id', $firstRow)->update(['final_prices' => '{"靓机":5800}']);
    $fresh = array_column($service->search(['keyword' => '17p'])['data'], null, 'id');
    $assert($fresh[$firstRow]['final_prices']['靓机'] === 5800, 'Repeated search returns latest price, not a stale history snapshot');
    $assert(QuoteSearch::visibleCategoryIds([
        ['id' => 1, 'parent_id' => 2, 'source_id' => 1, 'is_show' => 1],
        ['id' => 2, 'parent_id' => 1, 'source_id' => 1, 'is_show' => 1],
    ]) === [0], 'Cyclic category hierarchy fails closed');
    echo "$checks checks passed\n";
} finally {
    Db::rollback();
    foreach ($tables as $table) {
        if (Db::name($table)->whereIn('site_id', [$site, $site + 1])->count()) throw new RuntimeException('Rollback verification failed: ' . $table);
    }
    echo "Fixtures rolled back; no business records changed.\n";
}
