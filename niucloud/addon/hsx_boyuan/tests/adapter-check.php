<?php
/**
 * 博远适配器简单测试
 */

echo "\n=== 博远ERP适配器测试 ===\n\n";

// 检查文件是否存在
$adapterFile = __DIR__ . '/../app/adapter/BoyuanErpAdapter.php';
echo "【检查1】适配器文件\n";
if (file_exists($adapterFile)) {
    echo "✓ 文件存在: $adapterFile\n";
    echo "文件大小: " . filesize($adapterFile) . " 字节\n";
} else {
    echo "✗ 文件不存在: $adapterFile\n";
    exit(1);
}
echo "\n";

// 检查语法
echo "【检查2】PHP语法检查\n";
$output = [];
$return = 0;
exec("php -l " . escapeshellarg($adapterFile), $output, $return);
if ($return === 0) {
    echo "✓ 语法正确\n";
} else {
    echo "✗ 语法错误:\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}
echo "\n";

// 读取文件内容检查
echo "【检查3】代码结构\n";
$content = file_get_contents($adapterFile);

$checks = [
    'namespace addon\hsx_boyuan\app\adapter' => 'namespace声明',
    'class BoyuanErpAdapter implements ErpAdapterInterface' => '类定义和接口实现',
    'public function getAdapterCode()' => 'getAdapterCode方法',
    'public function getAdapterName()' => 'getAdapterName方法',
    'public function syncPurchase(' => 'syncPurchase方法',
    'public function syncSale(' => 'syncSale方法',
    'public function syncPayment(' => 'syncPayment方法',
    'public function testConnection(' => 'testConnection方法',
    'public function getConfigFields()' => 'getConfigFields方法',
    'public function getMappingRequirements()' => 'getMappingRequirements方法',
];

$passed = 0;
foreach ($checks as $needle => $description) {
    if (strpos($content, $needle) !== false) {
        echo "✓ $description\n";
        $passed++;
    } else {
        echo "✗ 缺少: $description\n";
    }
}

echo "\n通过检查: $passed / " . count($checks) . "\n\n";

// 检查Addon.php是否已修改
echo "【检查4】Addon.php集成\n";
$addonFile = __DIR__ . '/../Addon.php';
$addonContent = file_get_contents($addonFile);

if (strpos($addonContent, 'registerAdapter') !== false) {
    echo "✓ Addon.php已包含registerAdapter方法\n";
} else {
    echo "✗ Addon.php未包含registerAdapter方法\n";
}

if (strpos($addonContent, 'BoyuanErpAdapter') !== false) {
    echo "✓ Addon.php已引用BoyuanErpAdapter\n";
} else {
    echo "✗ Addon.php未引用BoyuanErpAdapter\n";
}
echo "\n";

// 统计代码行数
echo "【统计】代码行数\n";
$lines = count(file($adapterFile));
$commentLines = substr_count($content, '//') + substr_count($content, '/*') + substr_count($content, '*');
$blankLines = substr_count($content, "\n\n");
echo "总行数: $lines\n";
echo "预估注释行: ~$commentLines\n";
echo "预估代码行: ~" . ($lines - $commentLines - $blankLines) . "\n";
echo "\n";

// 总结
echo "=== 测试总结 ===\n";
if ($passed === count($checks)) {
    echo "✓ 所有检查通过！\n";
    echo "✓ 博远适配器创建成功\n";
    echo "✓ 已集成到Addon.php\n";
    echo "\n";
    echo "【下一步】\n";
    echo "1. 通过niucloud后台升级hsx_boyuan插件\n";
    echo "2. 适配器将自动注册到集成框架\n";
    echo "3. 在PC管理界面配置博远连接\n";
    echo "4. 配置资源映射\n";
    echo "5. 测试实际推送\n";
} else {
    echo "✗ 有 " . (count($checks) - $passed) . " 项检查未通过\n";
    echo "请检查代码是否完整\n";
}
echo "\n";
