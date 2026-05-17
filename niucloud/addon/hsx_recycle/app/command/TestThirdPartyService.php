<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\command;

use addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService;
use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 第三方服务测试命令
 * 运行方式: php think recycle:test-service
 */
class TestThirdPartyService extends Command
{
    protected function configure()
    {
        $this->setName('recycle:test-service')
            ->setDescription('测试第三方服务');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("========================================");
        $output->writeln("第三方服务测试");
        $output->writeln("========================================");
        $output->writeln("");

        $service = new CoreThirdPartyService();
        $siteId = 100000;

        $testResults = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ];

        // 测试1: 健康检查
        $output->writeln("【测试】健康检查");
        $output->writeln(str_repeat('-', 50));

        try {
            // 检查设备查询服务
            $output->writeln("检查设备查询服务...");
            $healthResult = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY);
            foreach ($healthResult as $item) {
                $status = $item['healthy'] ? '✓ 正常' : '✗ 异常';
                $output->writeln("  - {$item['provider']}: {$status} ({$item['message']})");
                if ($item['healthy'] && isset($item['balance'])) {
                    $output->writeln("    余额: {$item['balance']}");
                }
            }

            // 检查快递查询服务
            $output->writeln("\n检查快递查询服务...");
            $healthResult = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY);
            foreach ($healthResult as $item) {
                $status = $item['healthy'] ? '✓ 正常' : '✗ 异常';
                $output->writeln("  - {$item['provider']}: {$status} ({$item['message']})");
            }

            // 检查快递下单服务
            $output->writeln("\n检查快递下单服务...");
            $healthResult = $service->checkHealth($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER);
            foreach ($healthResult as $item) {
                $status = $item['healthy'] ? '✓ 正常' : '✗ 异常';
                $output->writeln("  - {$item['provider']}: {$status} ({$item['message']})");
                if ($item['healthy'] && isset($item['balance'])) {
                    $output->writeln("    余额: {$item['balance']}");
                }
            }

        } catch (\Exception $e) {
            $output->error("健康检查异常: " . $e->getMessage());
        }

        $output->writeln("");

        // 测试2: 设备查询 - 型号查询
        $this->runTest(
            $output,
            '设备查询 - 3023 - 查询型号',
            function() use ($service, $siteId) {
                return $service->call(
                    ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
                    'queryByImei',
                    [
                        'imei' => '352000000000000',
                        'api' => '/apple/model'
                    ],
                    $siteId
                );
            },
            $testResults
        );

        // 测试3: 设备查询 - 保修查询
        $this->runTest(
            $output,
            '设备查询 - 3023 - 查询保修',
            function() use ($service, $siteId) {
                return $service->call(
                    ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
                    'getCoverage',
                    [
                        'imei' => '352000000000000'
                    ],
                    $siteId
                );
            },
            $testResults
        );

        // 测试4: 快递查询
        $this->runTest(
            $output,
            '快递查询 - 阿里云',
            function() use ($service, $siteId) {
                return $service->call(
                    ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
                    'query',
                    [
                        'express_no' => '75******1234',
                        'express_code' => 'YTO'
                    ],
                    $siteId
                );
            },
            $testResults
        );

        // 测试总结
        $output->writeln("");
        $output->writeln("========================================");
        $output->writeln("测试总结");
        $output->writeln("========================================");
        $output->writeln("总测试数: {$testResults['total']}");
        $output->writeln("成功: {$testResults['success']}");
        $output->writeln("失败: {$testResults['failed']}");

        if ($testResults['failed'] > 0) {
            $output->warning("\n⚠️  有测试失败，请检查配置和日志");
        } else {
            $output->info("\n✓ 所有测试通过！");
        }

        $output->writeln("\n提示：");
        $output->writeln("1. 查看API调用日志: SELECT * FROM saas_third_party_api_log ORDER BY create_at DESC LIMIT 10;");
        $output->writeln("2. 查看费用统计: SELECT * FROM saas_third_party_cost_stats WHERE site_id = {$siteId};");
    }

    private function runTest(Output $output, string $testName, callable $callback, array &$results)
    {
        $output->writeln("【测试】{$testName}");
        $output->writeln(str_repeat('-', 50));

        $results['total']++;

        try {
            $result = $callback();

            if ($result['success']) {
                $output->info("✓ 测试通过");
                $output->writeln("提供商: " . ($result['provider'] ?? 'N/A'));
                $output->writeln("耗时: " . ($result['duration'] ?? 0) . "ms");

                if (isset($result['data'])) {
                    $output->writeln("返回数据: " . json_encode($result['data'], JSON_UNESCAPED_UNICODE));
                }

                $results['success']++;
            } else {
                $output->error("✗ 测试失败");
                $output->writeln("错误信息: " . ($result['message'] ?? '未知错误'));
                $results['failed']++;
            }
        } catch (\Exception $e) {
            $output->error("✗ 测试异常");
            $output->writeln("异常信息: " . $e->getMessage());
            $output->writeln("异常位置: " . $e->getFile() . ':' . $e->getLine());
            $results['failed']++;
        }

        $output->writeln("");
    }
}
