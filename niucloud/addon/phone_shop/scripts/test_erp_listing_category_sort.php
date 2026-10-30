<?php
declare(strict_types=1);

// 使用真实分类选择契约的隔离回归，不加载应用、不连接数据库、不修改分类。
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/listener/erp/ErpListingCatalog.php';

use addon\phone_shop\app\listener\erp\ErpListingCatalog;

$passed = 0;
function expect($actual, $expected, string $label): void
{
    global $passed;
    if ($actual !== $expected) {
        throw new RuntimeException($label . '\nactual=' . json_encode($actual, JSON_UNESCAPED_UNICODE)
            . '\nexpected=' . json_encode($expected, JSON_UNESCAPED_UNICODE));
    }
    $passed++;
    echo '[通过] ' . $label . PHP_EOL;
}
function category(int $id, int $pid, string $name, int $sort = 0, int $time = 0, int $visible = 1): array
{
    return ['category_id' => $id, 'pid' => $pid, 'category_name' => $name, 'sort' => $sort, 'create_time' => $time, 'is_show' => $visible];
}
function ids(array $options): array { return array_column($options, 'value'); }

$rows = [
    category(100, 0, '荣耀', 1, 999), category(101, 100, 'Magic9', 999, 999),
    category(1, 0, '苹果', 100, 1), category(10, 1, 'iPhone 17', 10, 1),
    category(11, 10, 'iPhone 17 Pro', 1, 1), category(12, 10, 'iPhone 17', 20, 1),
    category(20, 1, 'iPhone 16', 1, 1), category(21, 20, 'iPhone 16 Pro', 9999, 9999),
    category(200, 0, '小米', 50, 1), category(201, 200, '小米 17', 999, 999),
];
$options = ErpListingCatalog::options($rows);
expect(ids($options), [12, 11, 21, 201, 101], '按每级 sort 降序，叶级高权重不越过品牌和系列');
expect($options[0]['path'], [1, 10, 12], '分类路径不改变');
expect($options[0]['names'], ['苹果', 'iPhone 17', 'iPhone 17'], '分类名称不改变');
expect($options[0]['label'], '苹果 / iPhone 17 / iPhone 17', '现有 label 契约保留');
expect($options[0]['sort'], 20, '返回当前末级分类 sort');
expect($options[0]['path_sort'], [100, 10, 20], '返回整条路径各级 sort');
expect($options[0]['path_create_time'], [1, 1, 1], '返回整条路径稳定次序时间');

$shuffled = array_reverse($rows);
expect(ErpListingCatalog::options($shuffled), $options, '数据库返回顺序变化不改变目录顺序');

$ties = [category(1, 0, '品牌'), category(10, 1, '旧系列', 0, 1), category(11, 10, '旧机', 0, 999),
    category(20, 1, '新系列', 0, 2), category(21, 20, '新机A', 0, 1), category(22, 20, '新机B', 0, 1)];
expect(ids(ErpListingCatalog::options($ties)), [22, 21, 11], '同 sort 按该级创建时间降序，再按 ID 降序');

$invalid = [category(1, 0, '正常'), category(2, 1, '正常机'),
    category(3, 999, '孤立机'), category(4, 0, '隐藏品牌', 999, 1, 0), category(5, 4, '隐藏品牌机'),
    category(6, 0, '隐藏机', 0, 0, 0), category(7, 8, '循环A'), category(8, 7, '循环B')];
expect(ids(ErpListingCatalog::options($invalid)), [2], '停用、孤立和循环分类仍不可消费');
expect(ids(ErpListingCatalog::options([category(4, 0, '根级可售', 10)])), [4], '无子类的根级分类仍可选');
expect(ErpListingCatalog::options([]), [], '空分类返回空数组');

$source = file_get_contents(dirname(__DIR__) . '/app/listener/erp/ErpListingCatalog.php');
expect(strpos($source, "->field('category_id,pid,category_name,is_show,sort,create_time')") !== false, true, '真实查询读取 sort 和创建时间');
expect(strpos($source, "->where('site_id', \$siteId)") !== false, true, '真实查询仍限定当前站点');
echo '[结果] ' . $passed . ' 项分类排序隔离回归通过；未读取或修改数据库。' . PHP_EOL;
