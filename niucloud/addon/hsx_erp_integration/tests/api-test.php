<?php
/**
 * API快速测试脚本
 *
 * 用于测试集成API接口，不依赖前端
 */

namespace test;

use think\facade\Db;

require_once __DIR__ . '/../../../../../../vendor/autoload.php';

echo "\n=== ERP集成API测试 ===\n\n";

$siteId = 1; // 替换为实际站点ID

echo "【测试1】创建博远连接\n";
$connectionData = [
    'site_id' => $siteId,
    'name' => '博远ERP测试环境',
    'adapter_type' => 'boyuan',
    'config' => json_encode([
        'app_key' => 'test_app_key',
        'app_secret' => 'test_app_secret',
        'api_url' => 'https://open.boyuan.com/api',
        'account_code' => 'test_account',
    ]),
    'is_enabled' => 1,
    'create_time' => time(),
    'update_time' => time(),
];

try {
    $connectionId = Db::name('integration_connection')->insertGetId($connectionData);
    echo "✓ 连接创建成功 [ID: {$connectionId}]\n";
} catch (\Exception $e) {
    echo "✗ 连接创建失败: {$e->getMessage()}\n";
}
echo "\n";

echo "【测试2】创建商品映射\n";
$mappings = [
    ['resource_type' => 'product', 'local_id' => 1, 'local_name' => 'iPhone 13', 'remote_id' => '10001', 'remote_name' => '苹果13'],
    ['resource_type' => 'product', 'local_id' => 2, 'local_name' => 'iPhone 14', 'remote_id' => '10002', 'remote_name' => '苹果14'],
];

$mappingCount = 0;
foreach ($mappings as $mapping) {
    try {
        Db::name('integration_mapping')->insert(array_merge($mapping, [
            'site_id' => $siteId,
            'connection_id' => $connectionId,
            'create_time' => time(),
            'update_time' => time(),
        ]));
        $mappingCount++;
    } catch (\Exception $e) {
        echo "✗ 映射创建失败: {$e->getMessage()}\n";
    }
}
echo "✓ 创建 {$mappingCount} 个商品映射\n\n";

echo "【测试3】创建仓库映射\n";
try {
    Db::name('integration_mapping')->insert([
        'site_id' => $siteId,
        'connection_id' => $connectionId,
        'resource_type' => 'warehouse',
        'local_id' => 1,
        'local_name' => '主仓库',
        'remote_id' => '200',
        'remote_name' => '博远主仓库',
        'create_time' => time(),
        'update_time' => time(),
    ]);
    echo "✓ 仓库映射创建成功\n";
} catch (\Exception $e) {
    echo "✗ 仓库映射创建失败: {$e->getMessage()}\n";
}
echo "\n";

echo "【测试4】创建往来单位映射\n";
try {
    Db::name('integration_mapping')->insert([
        'site_id' => $siteId,
        'connection_id' => $connectionId,
        'resource_type' => 'party',
        'local_id' => 10,
        'local_name' => '供应商A',
        'remote_id' => '3000',
        'remote_name' => '博远供应商A',
        'create_time' => time(),
        'update_time' => time(),
    ]);
    echo "✓ 往来单位映射创建成功\n";
} catch (\Exception $e) {
    echo "✗ 往来单位映射创建失败: {$e->getMessage()}\n";
}
echo "\n";

echo "【测试5】模拟采购单创建事件\n";
// 这里应该由实际采购单创建触发，这里仅作演示
$mockPurchaseId = 1; // 替换为实际采购单ID
echo "假设采购单ID: {$mockPurchaseId}\n";
echo "事件将由 ErpPurchaseService::create() 自动触发\n";
echo "事件名称: ErpPurchaseCreated\n";
echo "监听器: addon\\hsx_erp_integration\\app\\listener\\ErpPurchaseCreated\n";
echo "\n";

echo "【测试6】查询集成任务\n";
try {
    $tasks = Db::name('integration_task')
        ->where('site_id', $siteId)
        ->order('id DESC')
        ->limit(5)
        ->select()
        ->toArray();

    if (empty($tasks)) {
        echo "暂无同步任务（需要实际创建采购单后才会有）\n";
    } else {
        echo "最近的同步任务：\n";
        foreach ($tasks as $task) {
            echo "  - 任务#{$task['id']} | {$task['business_type']} | {$task['status']} | {$task['source_no']}\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ 查询任务失败: {$e->getMessage()}\n";
}
echo "\n";

echo "【测试7】查询失败记录\n";
try {
    $failures = Db::name('integration_failure')
        ->where('site_id', $siteId)
        ->where('status', 'pending')
        ->limit(5)
        ->select()
        ->toArray();

    if (empty($failures)) {
        echo "✓ 暂无失败记录\n";
    } else {
        echo "待处理的失败记录：\n";
        foreach ($failures as $failure) {
            echo "  - 失败#{$failure['id']} | {$failure['business_type']} | {$failure['error_code']}\n";
            echo "    错误信息: {$failure['error_message']}\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ 查询失败记录失败: {$e->getMessage()}\n";
}
echo "\n";

echo "=== 测试完成 ===\n\n";

echo "【下一步】\n";
echo "1. 在 hsx_erp 中创建一个采购单\n";
echo "2. 观察日志：runtime/log/\n";
echo "3. 查看任务表：SELECT * FROM integration_task ORDER BY id DESC LIMIT 1;\n";
echo "4. 启动队列：php think queue:work\n";
echo "5. 查看推送结果\n";
echo "\n";

echo "【清理测试数据】\n";
echo "如需清理，执行以下SQL：\n";
echo "DELETE FROM integration_mapping WHERE connection_id = {$connectionId};\n";
echo "DELETE FROM integration_connection WHERE id = {$connectionId};\n";
echo "\n";
