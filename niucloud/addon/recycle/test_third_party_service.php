<?php
/**
 * 第三方服务测试脚本
 *
 * 运行方式：
 * cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud
 * php addon/recycle/test_third_party_service.php
 */

// 引入框架
require_once __DIR__ . '/../../think';

use addon\recycle\app\service\core\third_party\CoreThirdPartyService;
use addon\recycle\app\dict\third_party\ThirdPartyDict;

echo "========================================\n";
echo "第三方服务测试脚本\n";
echo "========================================\n\n";

$service = new CoreThirdPartyService();
$siteId = 100000;

// 测试结果统计
$testResults = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
];

/**
 * 测试函数
 */
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
                echo "返回数据: " . json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
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

// ==================== 测试1: 设备查询服务 (3023) ====================
runTest('设备查询 - 3023 - 查询型号', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
        'queryByImei',
        [
            'imei' => '352000000000000',  // 测试IMEI
            'api' => '/apple/model'       // 查询型号
        ],
        $siteId
    );
}, $testResults);

// ==================== 测试2: 设备查询服务 - 保修查询 ====================
runTest('设备查询 - 3023 - 查询保修', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
        'getCoverage',
        [
            'imei' => '352000000000000'  // 测试IMEI
        ],
        $siteId
    );
}, $testResults);

// ==================== 测试3: 快递查询服务 (阿里云) ====================
runTest('快递查询 - 阿里云 - 查询物流', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
        'query',
        [
            'express_no' => '75******1234',  // 测试快递单号
            'express_code' => 'YTO'          // 圆通快递
        ],
        $siteId
    );
}, $testResults);

// ==================== 测试4: 快递下单服务 (安果ERP) ====================
// 注意：这个测试会实际创建订单并产生费用，建议使用测试环境
echo "【提示】快递下单测试已跳过（避免产生实际费用）\n";
echo "如需测试，请取消下面代码的注释\n\n";

/*
runTest('快递下单 - 安果ERP - 创建订单', function() use ($service, $siteId) {
    return $service->call(
        ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER,
        'createOrder',
        [
            'sender' => [
                'name' => '测试发件人',
                'phone' => '13800138000',
                'province' => '广东省',
                'city' => '深圳市',
                'district' => '南山区',
                'address' => '测试地址123号'
            ],
            'receiver' => [
                'name' => '测试收件人',
                'phone' => '13900139000',
                'province' => '北京市',
                'city' => '北京市',
                'district' => '朝阳区',
                'address' => '测试地址456号'
            ],
            'goods' => [
                'name' => '测试商品',
                'weight' => 1.0
            ]
        ],
        $siteId
    );
}, $testResults);
*/

// ==================== 测试5: 健康检查 ====================
echo "【测试】健康检查 - 所有服务\n";
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
    echo "✗ 健康检查异常\n";
    echo "异常信息: " . $e->getMessage() . "\n";
}

echo "\n";

// ==================== 测试总结 ====================
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
echo "3. 查看服务配置: SELECT * FROM saas_third_party_service WHERE site_id = {$siteId};\n";
