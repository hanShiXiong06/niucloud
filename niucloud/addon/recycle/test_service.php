<?php
/**
 * 第三方服务测试脚本
 * 运行方式: cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud && php addon/recycle/test_service.php
 */

namespace think;

// 加载基础文件
require __DIR__ . '/../../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->name('admin')->run();

// 不输出响应，我们只是初始化框架
// $response->send();

use addon\recycle\app\service\core\third_party\CoreThirdPartyService;
use addon\recycle\app\dict\third_party\ThirdPartyDict;

echo "========================================\n";
echo "第三方服务测试\n";
echo "========================================\n\n";

$service = new CoreThirdPartyService();
$siteId = 100000;

$testResults = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
];

function runTest($testName, $callback, &$results) {
    echo "【测试】{$testName}\n";
    echo str_repeat('-', 50) . "\n";

    $results['total']++;

    try {
        $result = $callback();

        if ($result['success']) {
            echo "✓ 测试通过\n";
            echo "提供商: " . ($result['provider'] ?? 'N/A') . "\n";
            echo "耗时: " . ($result['duration'] ?? 0) . "ms\n";

            if (isset($result['data'])) {
                echo "返回数据: " . json_encode($result['data'], JSON_UNESCAPED_UNICODE) . "\n";
            }

            $results['success']++;
        } else {
            echo "✗ 测试失败\n";
            echo "错误信息: " . ($result['message'] ?? '未知错误') . "\n";
            $results['failed']++;
        }
    } catch (\Exception $e) {
        echo "✗ 测试异常\n";
        echo "异常信息: " . $e->getMessage() . "\n";
        echo "异常位置: " . $e->getFile() . ':' . $e->getLine() . "\n";
        $results['failed']++;
    }

    echo "\n";
}

// 测试1: 健康检查
echo "【测试】健康检查\n";
echo str_repeat('-', 50) . "\n";

try {
    // 检查设备查询服务
    echo "检查设备查询服务...\n";
    $healthResult = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY);
    foreach ($healthResult as $item) {
        $status = $item['healthy'] ? '✓ 正常' : '✗ 异常';
        echo "  - {$item['provider']}: {$status} ({$item['message']})\n";
        if ($item['healthy'] && isset($item['balance'])) {
            echo "    余额: {$item['balance']}\n";
        }
    }

    // 检查快递查询服务
    echo "\n检查快递查询服务...\n";
    $healthResult = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY);
    foreach ($healthResult as $item) {
        $status = $item['healthy'] ? '✓ 正常' : '✗ 异常';
        echo "  - {$item['provider']}: {$status} ({$item['message']})\n";
    }

    // 检查快递下单服务
    echo "\n检查快递下单服务...\n";
    $healthResult = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER);
    foreach ($healthResult as $item) {
        $status = $item['healthy'] ? '✓ 正常' : '✗ 异常';
        echo "  - {$item['provider']}: {$status} ({$item['message']})\n";
        if ($item['healthy'] && isset($item['balance'])) {
            echo "    余额: {$item['balance']}\n";
        }
    }

} catch (\Exception $e) {
    echo "✗ 健康检查异常: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试2: 设备查询 - 型号查询
runTest('设备查询 - 3023 - 查询型号', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
        'queryByImei',
        [
            'imei' => '352000000000000',
            'api' => '/apple/model'
        ],
        $siteId
    );
}, $testResults);

// 测试3: 设备查询 - 保修查询
runTest('设备查询 - 3023 - 查询保修', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
        'getCoverage',
        [
            'imei' => '352000000000000'
        ],
        $siteId
    );
}, $testResults);

// 测试4: 快递查询
runTest('快递查询 - 阿里云', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
        'query',
        [
            'express_no' => '75******1234',
            'express_code' => 'YTO'
        ],
        $siteId
    );
}, $testResults);

// 测试总结
echo "========================================\n";
echo "测试总结\n";
echo "========================================\n";
echo "总测试数: {$testResults['total']}\n";
echo "成功: {$testResults['success']}\n";
echo "失败: {$testResults['failed']}\n";

if ($testResults['failed'] > 0) {
    echo "\n⚠️  有测试失败，请检查配置和日志\n";
} else {
    echo "\n✓ 所有测试通过！\n";
}

echo "\n提示：\n";
echo "1. 查看API调用日志: SELECT * FROM saas_third_party_api_log ORDER BY create_at DESC LIMIT 10;\n";
echo "2. 查看费用统计: SELECT * FROM saas_third_party_cost_stats WHERE site_id = {$siteId};\n";
