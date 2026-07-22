<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\model\third_party\ThirdPartyService;
use addon\hsx_recycle\app\model\third_party\ThirdPartyApiLog;
use addon\hsx_recycle\app\model\third_party\ThirdPartyCostStats;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 第三方服务核心服务类
 * Class CoreThirdPartyService
 * @package addon\hsx_recycle\app\service\core\third_party
 */
class CoreThirdPartyService extends BaseCoreService
{
    /**
     * 调用第三方服务
     * @param string $serviceType 服务类型
     * @param string $method 方法名
     * @param array $params 参数
     * @param int $siteId 站点ID
     * @return array
     * @throws CommonException
     */
    /**
     * 测试某能力「当前指定服务商」的就绪状态。
     * 校验：能力已启用 + 指定服务商已配置完整 + Provider 类可加载并通过自身健康检查。
     * 严格手动：只测指定的那一家，不尝试别家。
     * @param int $siteId
     * @param string $serviceType
     * @return array { success, provider, provider_name, message }
     */
    public function testConnection(int $siteId, string $serviceType): array
    {
        if (!ThirdPartyDict::isValidServiceType($serviceType)) {
            return ['success' => false, 'message' => "无效的服务类型: {$serviceType}"];
        }
        try {
            $provider = $this->getProvider($serviceType, $siteId);
            if (!$provider) {
                return ['success' => false, 'message' => '未配置或当前指定的服务商不可用'];
            }
            if (!$provider->healthCheck()) {
                return [
                    'success' => false,
                    'provider' => $provider->getProviderName(),
                    'provider_name' => $provider->getName(),
                    'message' => '配置不完整，请补全必填项后重试',
                ];
            }
            return [
                'success' => true,
                'provider' => $provider->getProviderName(),
                'provider_name' => $provider->getName(),
                'message' => $provider->getName() . ' 配置就绪，可使用',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 调用第三方服务
     * @param string $serviceType 服务类型
     * @param string $method 方法名
     * @param array $params 参数
     * @param int $siteId 站点ID
     * @return array
     * @throws CommonException
     */
    public function call(string $serviceType, string $method, array $params, int $siteId): array
    {
        // 1. 验证服务类型
        if (!ThirdPartyDict::isValidServiceType($serviceType)) {
            throw new CommonException("无效的服务类型: {$serviceType}");
        }

        // 2. 获取服务提供者（支持主备切换）
        $provider = $this->getProvider($serviceType, $siteId);
        if (!$provider) {
            throw new CommonException("没有可用的{$serviceType}服务提供者");
        }

        // 3. 记录开始时间
        $startTime = microtime(true);
        $success = false;
        $result = [];
        $errorMsg = '';

        try {
            // 4. 执行调用
            $result = $provider->execute($method, $params);
            if (array_key_exists('success', $result) && empty($result['success'])) {
                throw new CommonException((string)($result['message'] ?? '第三方服务调用失败'));
            }
            $success = true;

            // 5. 计算耗时
            $duration = (int)round((microtime(true) - $startTime) * 1000);

            // 6. 自动更新余额（如果返回结果中包含余额信息）
            $this->updateBalanceIfExists($siteId, $serviceType, $provider->getProviderName(), $result);

            // 7. 记录日志
            $this->logApiCall(
                $siteId,
                $serviceType,
                $provider->getProviderName(),
                $method,
                $params,
                $result,
                $duration,
                true
            );

            // 8. 更新统计
            $this->updateStats($siteId, $serviceType, $provider->getProviderName(), true, $result['cost'] ?? 0, $duration);

            return [
                'success' => true,
                'data' => $result,
                'provider' => $provider->getProviderName(),
                'duration' => $duration,
            ];

        } catch (\Exception $e) {
            $duration = (int)round((microtime(true) - $startTime) * 1000);
            $errorMsg = $e->getMessage();

            // 记录错误日志
            $this->logApiCall(
                $siteId,
                $serviceType,
                $provider->getProviderName(),
                $method,
                $params,
                [],
                $duration,
                false,
                $errorMsg
            );

            // 更新统计
            $this->updateStats($siteId, $serviceType, $provider->getProviderName(), false, 0, $duration);

            // 新配置中心是严格手动选择服务商，不允许在用户不知情时切换线路。
            if ((new RecycleThirdPartyConfigService())->hasSavedConfig($siteId)) {
                throw new CommonException($errorMsg);
            }

            // 仅兼容旧配置的主备策略；完成配置迁移后删除此分支。
            try {
                return $this->tryBackupProvider($serviceType, $method, $params, $siteId, $provider->getProviderName());
            } catch (\Exception $backupException) {
                throw new CommonException($errorMsg ?: $backupException->getMessage());
            }
        }
    }

    /**
     * 获取服务提供者（支持主备）
     * @param string $serviceType
     * @param int $siteId
     * @param string $excludeProvider 排除的提供商（用于主备切换）
     * @return mixed
     * @throws CommonException
     */
    private function getProvider(string $serviceType, int $siteId, string $excludeProvider = '')
    {
        $configService = new RecycleThirdPartyConfigService();
        if ($configService->hasSavedConfig($siteId)) {
            if (!$configService->isServiceEnabled($siteId, $serviceType)) {
                throw new CommonException("站点{$siteId}已禁用{$serviceType}服务");
            }

            $provider = $this->getProviderFromConfigCenter($serviceType, $siteId, $excludeProvider, $configService);
            if ($provider) {
                return $provider;
            }

            return null;
        }

        // 获取所有可用的服务提供者
        $services = ThirdPartyService::getAvailableProviders($siteId, $serviceType);

        if (empty($services)) {
            throw new CommonException("站点{$siteId}未配置{$serviceType}服务");
        }

        // 按优先级尝试
        foreach ($services as $service) {
            // 2.0 阶段快递下单仅保留亿速，忽略历史安果等快递服务商配置
            if (
                $serviceType === ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER
                && $service['provider_name'] !== ThirdPartyDict::PROVIDER_YISU
            ) {
                continue;
            }

            // 跳过已排除的提供商
            if (!empty($excludeProvider) && $service['provider_name'] === $excludeProvider) {
                continue;
            }

            try {
                // 动态加载Provider类
                $providerClass = $this->getProviderClass($serviceType, $service['provider_name']);
                if (!class_exists($providerClass)) {
                    Log::warning("Provider类不存在: {$providerClass}");
                    continue;
                }

                // 实例化Provider
                $providerConfig = $this->mergeDefaultProviderConfig($serviceType, $service['provider_name'], $service['config'] ?? []);
                $provider = new $providerClass($providerConfig, $siteId);

                // 健康检查
                if ($provider->healthCheck()) {
                    return $provider;
                }

                Log::warning("Provider健康检查失败: {$service['provider_name']}");

            } catch (\Exception $e) {
                Log::error("加载Provider失败: {$service['provider_name']}, 错误: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function mergeDefaultProviderConfig(string $serviceType, string $providerName, array $config): array
    {
        $configService = new RecycleThirdPartyConfigService();
        $map = $configService->getServiceMap();
        if (!isset($map[$serviceType])) {
            return $config;
        }

        $sectionKey = $map[$serviceType]['section'];
        $default = $configService->getDefaultConfig();
        $providerDefault = $default[$sectionKey][$providerName] ?? [];
        if (!is_array($providerDefault)) {
            return $config;
        }

        return array_merge($providerDefault, $config);
    }

    private function getProviderFromConfigCenter(
        string $serviceType,
        int $siteId,
        string $excludeProvider,
        RecycleThirdPartyConfigService $configService
    ) {
        $map = $configService->getServiceMap();
        if (!isset($map[$serviceType])) {
            return null;
        }

        // 用配置中"当前生效服务商"，支持每个能力挂多个服务商并自由切换（严格手动，不做故障转移）
        $providerName = $configService->getActiveProvider($siteId, $serviceType);
        if ($providerName === '') {
            $providerName = $map[$serviceType]['provider'];
        }
        if (!empty($excludeProvider) && $providerName === $excludeProvider) {
            return null;
        }

        $config = $configService->getProviderConfig($siteId, $serviceType, $providerName);
        if (empty($config)) {
            return null;
        }

        try {
            $providerClass = $this->getProviderClass($serviceType, $providerName);
            if (!class_exists($providerClass)) {
                Log::warning("Provider类不存在: {$providerClass}");
                return null;
            }

            $provider = new $providerClass($config, $siteId);
            if ($provider->healthCheck()) {
                return $provider;
            }

            Log::warning("配置中心Provider健康检查失败: {$providerName}");
        } catch (\Exception $e) {
            Log::error("配置中心加载Provider失败: {$providerName}, 错误: " . $e->getMessage());
        }

        return null;
    }

    /**
     * 尝试备用服务商
     * @param string $serviceType
     * @param string $method
     * @param array $params
     * @param int $siteId
     * @param string $failedProvider
     * @return array
     * @throws CommonException
     */
    private function tryBackupProvider(string $serviceType, string $method, array $params, int $siteId, string $failedProvider): array
    {
        Log::info("主服务商{$failedProvider}调用失败，尝试备用服务商");

        // 获取备用服务提供者
        $provider = $this->getProvider($serviceType, $siteId, $failedProvider);

        if (!$provider) {
            throw new CommonException("所有{$serviceType}服务提供者均不可用");
        }

        // 记录开始时间
        $startTime = microtime(true);

        try {
            // 执行调用
            $result = $provider->execute($method, $params);
            $duration = (int)round((microtime(true) - $startTime) * 1000);

            // 自动更新余额（如果返回结果中包含余额信息）
            $this->updateBalanceIfExists($siteId, $serviceType, $provider->getProviderName(), $result);

            // 记录日志
            $this->logApiCall(
                $siteId,
                $serviceType,
                $provider->getProviderName(),
                $method,
                $params,
                $result,
                $duration,
                true
            );

            // 更新统计
            $this->updateStats($siteId, $serviceType, $provider->getProviderName(), true, $result['cost'] ?? 0, $duration);

            Log::info("备用服务商{$provider->getProviderName()}调用成功");

            return [
                'success' => true,
                'data' => $result,
                'provider' => $provider->getProviderName(),
                'duration' => $duration,
                'is_backup' => true,
            ];

        } catch (\Exception $e) {
            $duration = (int)round((microtime(true) - $startTime) * 1000);

            // 记录错误日志
            $this->logApiCall(
                $siteId,
                $serviceType,
                $provider->getProviderName(),
                $method,
                $params,
                [],
                $duration,
                false,
                $e->getMessage()
            );

            // 更新统计
            $this->updateStats($siteId, $serviceType, $provider->getProviderName(), false, 0, $duration);

            throw new CommonException("备用服务商调用失败: " . $e->getMessage());
        }
    }

    /**
     * 获取Provider类名
     * @param string $serviceType
     * @param string $providerName
     * @return string
     */
    private function getProviderClass(string $serviceType, string $providerName): string
    {
        // 将provider_name转换为类名格式
        // 例如: 3023 -> Provider3023, ali_express -> ProviderAliExpress
        $className = 'Provider' . str_replace('_', '', ucwords($providerName, '_'));

        return "addon\\hsx_recycle\\app\\service\\core\\third_party\\provider\\{$serviceType}\\{$className}";
    }

    /**
     * 记录API调用日志
     * @param int $siteId
     * @param string $serviceType
     * @param string $providerName
     * @param string $method
     * @param array $requestParams
     * @param array $responseData
     * @param int $duration
     * @param bool $success
     * @param string $errorMsg
     * @return void
     */
    private function logApiCall(
        int $siteId,
        string $serviceType,
        string $providerName,
        string $method,
        array $requestParams,
        array $responseData,
        int $duration,
        bool $success,
        string $errorMsg = ''
    ) {
        try {
            $sanitizer = new ThirdPartyLogSanitizer();
            ThirdPartyApiLog::log([
                'site_id' => $siteId,
                'service_type' => $serviceType,
                'provider_name' => $providerName,
                'method' => $method,
                'request_params' => $sanitizer->sanitize($requestParams),
                'response_data' => $sanitizer->sanitize($responseData),
                'cost' => $responseData['cost'] ?? 0,
                'duration' => $duration,
                'status' => $success ? ThirdPartyDict::CALL_STATUS_SUCCESS : ThirdPartyDict::CALL_STATUS_FAILED,
                'error_msg' => $errorMsg,
            ]);
        } catch (\Exception $e) {
            Log::error("记录API日志失败: " . $e->getMessage());
        }
    }

    /**
     * 更新统计数据
     * @param int $siteId
     * @param string $serviceType
     * @param string $providerName
     * @param bool $success
     * @param float $cost
     * @param int $duration
     * @return void
     */
    private function updateStats(int $siteId, string $serviceType, string $providerName, bool $success, float $cost, int $duration)
    {
        try {
            $date = date('Y-m-d');

            // 获取今日统计
            $stats = ThirdPartyCostStats::where([
                ['site_id', '=', $siteId],
                ['service_type', '=', $serviceType],
                ['provider_name', '=', $providerName],
                ['date', '=', $date],
            ])->find();

            if ($stats) {
                // 更新统计
                $totalCalls = $stats['total_calls'] + 1;
                $successCalls = $stats['success_calls'] + ($success ? 1 : 0);
                $failedCalls = $stats['failed_calls'] + ($success ? 0 : 1);
                $totalCost = $stats['total_cost'] + $cost;
                $avgDuration = round(($stats['avg_duration'] * $stats['total_calls'] + $duration) / $totalCalls);

                $stats->save([
                    'total_calls' => $totalCalls,
                    'success_calls' => $successCalls,
                    'failed_calls' => $failedCalls,
                    'total_cost' => $totalCost,
                    'avg_duration' => $avgDuration,
                ]);
            } else {
                // 创建统计
                ThirdPartyCostStats::create([
                    'site_id' => $siteId,
                    'service_type' => $serviceType,
                    'provider_name' => $providerName,
                    'date' => $date,
                    'total_calls' => 1,
                    'success_calls' => $success ? 1 : 0,
                    'failed_calls' => $success ? 0 : 1,
                    'total_cost' => $cost,
                    'avg_duration' => $duration,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("更新统计数据失败: " . $e->getMessage());
        }
    }

    /**
     * 自动更新余额（如果返回结果中包含余额信息）
     * @param int $siteId
     * @param string $serviceType
     * @param string $providerName
     * @param array $result
     * @return void
     */
    private function updateBalanceIfExists(int $siteId, string $serviceType, string $providerName, array $result)
    {
        try {
            // 检查返回结果中是否包含余额信息
            $balance = null;

            // 尝试从不同的位置获取余额
            if (isset($result['balance'])) {
                $balance = (float)$result['balance'];
            } elseif (isset($result['data']['balance'])) {
                $balance = (float)$result['data']['balance'];
            }

            // 如果找到余额信息，更新到数据库
            if ($balance !== null) {
                $service = ThirdPartyService::where([
                    ['site_id', '=', $siteId],
                    ['service_type', '=', $serviceType],
                    ['provider_name', '=', $providerName]
                ])->find();

                if ($service) {
                    $service->save(['balance' => $balance]);
                    Log::info("自动更新余额: {$providerName} = {$balance}");
                }
            }
        } catch (\Exception $e) {
            // 余额更新失败不影响主流程
            Log::warning("自动更新余额失败: " . $e->getMessage());
        }
    }

    /**
     * 获取服务余额
     * @param int $siteId
     * @param string $serviceType
     * @param string $providerName
     * @return float
     * @throws CommonException
     */
    public function getBalance(int $siteId, string $serviceType, string $providerName = ''): float
    {
        $provider = $this->getProvider($serviceType, $siteId);
        if (!$provider) {
            throw new CommonException("服务提供者不可用");
        }

        return $provider->getBalance();
    }

    /**
     * 检查服务健康状态
     * @param int $siteId
     * @param string $serviceType
     * @return array
     */
    public function checkHealth(int $siteId, string $serviceType): array
    {
        $services = ThirdPartyService::getAvailableProviders($siteId, $serviceType);
        $result = [];

        foreach ($services as $service) {
            try {
                $providerClass = $this->getProviderClass($serviceType, $service['provider_name']);
                if (!class_exists($providerClass)) {
                    $result[] = [
                        'provider' => $service['provider_name'],
                        'healthy' => false,
                        'message' => 'Provider类不存在',
                    ];
                    continue;
                }

                $provider = new $providerClass($service['config'], $siteId);
                $healthy = $provider->healthCheck();

                $result[] = [
                    'provider' => $service['provider_name'],
                    'healthy' => $healthy,
                    'message' => $healthy ? '正常' : '异常',
                    'balance' => $healthy ? $provider->getBalance() : 0,
                ];
            } catch (\Exception $e) {
                $result[] = [
                    'provider' => $service['provider_name'],
                    'healthy' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }
}
