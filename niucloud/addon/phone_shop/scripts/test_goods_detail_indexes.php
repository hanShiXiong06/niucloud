<?php
declare(strict_types=1);

use addon\phone_shop\app\service\core\upgrade\GoodsDetailIndexes;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/service/core/upgrade/GoodsDetailIndexes.php';
$rows = static fn(array $cols, string $name = 'other', array $attrs = []) => array_map(
    static fn($i) => array_merge(['Key_name'=>$name, 'Seq_in_index'=>$i+1, 'Column_name'=>$cols[$i], 'Sub_part'=>null, 'Index_type'=>'BTREE'], $attrs),
    array_keys($cols)
);
$sku = 'phone_shop_goods_sku';
$stat = 'phone_shop_goods_stat';
$tests = [
    [$sku, $rows(['goods_id','is_default'], 'idx_goods_default'), 'idx_goods_default'],
    [$sku, $rows(['is_default','goods_id']), null],
    [$sku, $rows(['goods_id'], 'hidden', ['Visible'=>'NO']), null],
    [$stat, [], null],
    [$stat, array_reverse($rows(['site_id','date_time','goods_id','id'])), 'other'],
    [$stat, $rows(['date','goods_id']), 'other'],
    [$stat, $rows(['id','goods_id','date_time']), null],
    [$stat, $rows(['goods_id','date_time'], 'ignored', ['Ignored'=>'YES']), null],
    [$stat, $rows(['goods_id','date'], 'partial', ['Sub_part'=>10]), null],
];
foreach ($tests as [$logical,$indexes,$expected]) {
    if (GoodsDetailIndexes::existing($logical,$indexes) !== $expected) throw new LogicException('错误的等效索引判断: ' . $logical);
}
try {
    GoodsDetailIndexes::existing($stat, $rows(['id'], GoodsDetailIndexes::INDEXES[$stat][0]));
    throw new LogicException('应拒绝冲突的同名索引');
} catch (RuntimeException $e) {
    if (strpos($e->getMessage(), '同名索引') === false) throw $e;
}
$install = file_get_contents(__DIR__ . '/../sql/install.sql');
foreach (GoodsDetailIndexes::INDEXES as $table => [$name, $cols]) {
    $tableDdl = explode('ENGINE=', explode('{{prefix}}'.$table.'`', $install, 2)[1], 2)[0];
    $fragment = str_replace(' ', '', 'KEY`'.$name.'`(`'.implode('`,`',$cols).'`)');
    if (!str_contains(preg_replace('/\s+/', '', $tableDdl), $fragment)) throw new LogicException('安装与升级索引不一致: ' . $table);
}
echo "OK: detail index coverage, duplicate/conflict guards and install SQL; no database connection.\n";
