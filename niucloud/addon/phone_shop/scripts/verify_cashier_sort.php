<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit('CLI only');
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\phone_shop\app\service\admin\cashier\CashierGoodsOrder;
use addon\phone_shop\app\service\core\goods\CoreMemberPriceService;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

check(CashierGoodsOrder::order('price', 'asc') === 'cashier_price asc, sku.sku_id desc', 'price order');
check(CashierGoodsOrder::order('memory', 'desc') === 'cashier_memory IS NULL asc, cashier_memory desc, sku.sku_id desc', 'memory order');
check(CashierGoodsOrder::order('price; DROP TABLE goods', 'asc') === 'sku.sku_id desc', 'field whitelist');
check(CashierGoodsOrder::order('price', 'desc; SELECT 1') === 'cashier_price desc, sku.sku_id desc', 'direction whitelist');
check(CashierGoodsOrder::priceExpression([], 0) === 'ROUND(sku.price, 2)', 'guest price');

if (!in_array('--database', $argv, true)) {
    echo "Cashier sort whitelist checks passed. Add --database for read-only MySQL expression checks.\n";
    exit(0);
}

(new \think\App())->initialize();
$db = \think\facade\Db::connect();
$member = ['member_level' => 93, 'memberLevelData' => ['level_no' => 2, 'level_benefits' => ['discount' => ['is_use' => 1, 'discount' => 8, 'max_discount_money' => 100]]]];
$count = 0;
foreach ([[], $member] as $identity) {
    foreach ([['', '', 1000], ['discount', '', 1000], ['discount', '', 100], ['fixed_price', '{"level_2":500,"level_93":1}', 1000], ['fixed_price', '{"level_2":0}', 1000], ['fixed_price', '{"level_2":1100}', 1000], ['fixed_price', '{"level_2":-50}', 1000], ['fixed_price', 'not-json', 1000], ['fixed_price', '{"level_2":""}', 1000], ['fixed_price', '{"level_2":0.005}', 1000]] as [$mode, $fixed, $base]) {
        $expression = CashierGoodsOrder::priceExpression($identity, 2);
        $rows = $db->query("SELECT {$expression} AS value FROM (SELECT ? AS price, ? AS member_price) sku CROSS JOIN (SELECT ? AS member_discount) goods", [$base, $fixed, $mode]);
        $calculated = round((float) CoreMemberPriceService::calculate($identity, $mode, $fixed, $base), 2);
        $expected = $calculated > 0 && $calculated < $base ? $calculated : (float) $base;
        check(abs((float) $rows[0]['value'] - $expected) < 0.001, "Displayed/member price mismatch: {$mode} {$fixed}");
        $count++;
    }
}

$memory = CashierGoodsOrder::memoryExpression();
foreach (['128G' => 128, '256GB' => 256, '12+512G' => 512, '8GB + 128GB' => 128, '1TB' => 1024, '2T' => 2048, '512MB' => .5, '256' => 256, '' => null, '未知' => null, '12+foo' => null] as $input => $expected) {
    $parsed = CashierGoodsOrder::memoryGb((string) $input);
    check($expected === null ? $parsed === null : abs($parsed - $expected) < .00001, "Memory parser mismatch: {$input}");
    $rows = $db->query("SELECT {$memory} AS value FROM (SELECT ? AS memory_group, '' AS goods_name) goods", [(string) $input]);
    check($expected === null ? $rows[0]['value'] === null : abs((float) $rows[0]['value'] - $expected) < .00001, "Memory value mismatch: {$input}");
    $count++;
}
foreach ([['13', '苹果17Pro Max 256G 橙色', 256], ['0', 'vivo X300Pro 16+512G', 512], ['1', '苹果17Pro 1TB 白色', 1024], ['', '手机 8GB+128GB 黑色', 128], ['13', '苹果17Pro Max', null], ['256GB', '手机 512G', 256], ['0', '三星Galaxy S21 5G', null]] as [$input, $name, $expected]) {
    $rows = $db->query("SELECT {$memory} AS value FROM (SELECT ? AS memory_group, ? AS goods_name) goods", [$input, $name]);
    check($expected === null ? $rows[0]['value'] === null : (float) $rows[0]['value'] === (float) $expected, "Title capacity mismatch: {$name}");
    $count++;
}

$rows = $db->query('SELECT sku.sku_id, sku.cashier_price FROM (SELECT 1 AS sku_id, 900 AS cashier_price UNION ALL SELECT 2, 500 UNION ALL SELECT 3, 500) sku ORDER BY ' . CashierGoodsOrder::order('price', 'asc') . ' LIMIT 2');
check(array_map('intval', array_column($rows, 'sku_id')) === [3, 2], 'stable sorting before pagination');
[$where, $bind] = CashierGoodsOrder::memoryFilter(['128GB', '1TB']);
$rows = $db->table('(SELECT 1 AS site_id, 1 AS id, \'13\' AS memory_group, \'phone 128G\' AS goods_name UNION ALL SELECT 1, 2, \'1TB\', \'phone\' UNION ALL SELECT 1, 3, \'256G\', \'phone\' UNION ALL SELECT 2, 4, \'128GB\', \'phone\')')
    ->alias('goods')->where('goods.site_id', 1)->whereRaw($where, $bind)->order('goods.id')->select()->toArray();
check(array_map('intval', array_column($rows, 'id')) === [1, 2], 'capacity filter with ORM-bound site filter');
echo "Cashier SQL checks passed ({$count} price/memory cases plus stable pagination); SELECT only, no business records changed.\n";
