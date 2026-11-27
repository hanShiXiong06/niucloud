<?php
declare(strict_types=1);

use addon\phone_shop\app\service\core\upgrade\GoodsDetailIndexes;
use think\facade\Db;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$args = array_slice($argv, 1);
$usage = "从 niucloud 目录运行：php addon/phone_shop/scripts/upgrade_goods_detail_indexes.php [--check | --apply] [--site=整数 --goods=整数]\n"
    . "默认只检查索引；指定商品和站点时另做 EXPLAIN（不会浏览商品或累加访问量）。仅 --apply 会建缺失的索引。\n";
if ($args === ['--help']) { echo $usage; exit(0); }
$mode = null;
$ids = [];
foreach ($args as $arg) {
    if (in_array($arg, ['--apply', '--check'], true) && $mode === null) { $mode = $arg; continue; }
    if (preg_match('/^--(site|goods)=([1-9][0-9]{0,9})$/D', $arg, $m) && !isset($ids[$m[1]])) {
        $ids[$m[1]] = (int)$m[2]; continue;
    }
    fwrite(STDERR, $usage); exit(1);
}
if (count($ids) === 1) { fwrite(STDERR, $usage); exit(1); }
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
try {
    (new \think\App($root . DIRECTORY_SEPARATOR))->initialize();
    echo '当前数据库：' . Db::connect()->getConfig('database') . "\n";
    $upgrade = new GoodsDetailIndexes();
    $report = static function (array $plan): void {
        foreach ($plan as $table => [$existing, $sql]) {
            echo $existing === null ? "[缺少索引] {$sql}\n" : "[已覆盖] {$table}: {$existing}\n";
        }
    };
    $explain = static function () use ($ids): void {
        if (!$ids) return;
        $stat = GoodsDetailIndexes::table('phone_shop_goods_stat');
        $sku = GoodsDetailIndexes::table('phone_shop_goods_sku');
        $date = date('Y-m-d');
        $plans = [
            '日统计' => ["SELECT * FROM `{$stat}` WHERE site_id=? AND goods_id=? AND `date`=? AND date_time=? LIMIT 1", [$ids['site'], $ids['goods'], $date, strtotime($date)]],
            '默认规格' => ["SELECT sku_id FROM `{$sku}` WHERE site_id=? AND goods_id=? AND is_default=1 LIMIT 1", [$ids['site'], $ids['goods']]],
            '规格列表' => ["SELECT * FROM `{$sku}` WHERE goods_id=?", [$ids['goods']]],
        ];
        foreach ($plans as $label => [$sql, $bind]) {
            $row = Db::query('EXPLAIN ' . $sql, $bind, true)[0];
            echo "[EXPLAIN] {$label} type={$row['type']} key=" . ($row['key'] ?? '无') . " rows={$row['rows']}（估算）\n";
        }
    };
    $report($upgrade->plan());
    $explain();
    if ($mode !== '--apply') { echo "只读检查结束，数据库未修改。\n"; exit(0); }
    $upgrade->handle();
    echo "执行后复查（不会重建已有等效索引）：\n";
    $report($upgrade->plan());
    $explain();
} catch (Throwable $e) {
    fwrite(STDERR, '[失败] ' . $e->getMessage() . "\n未删除数据；已成功创建的索引可保留，重新 --check 查看。\n"); exit(1);
}
