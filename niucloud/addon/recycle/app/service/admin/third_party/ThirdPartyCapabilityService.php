<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\third_party;

use addon\recycle\app\dict\config\RecycleConfigKeyDict;
use addon\recycle\app\dict\third_party\ThirdPartyDict;
use addon\recycle\app\model\third_party\ThirdPartyApiLog;
use addon\recycle\app\service\core\device_query\DeviceQueryConfigService;
use addon\recycle\app\service\core\quotation\QuotationCrawlerConfigService;
use addon\recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use app\model\sys\SysConfig;
use core\base\BaseAdminService;

/**
 * 第三方能力中心概览
 */
class ThirdPartyCapabilityService extends BaseAdminService
{
    private $thirdPartyConfigService;
    private $quotationCrawlerConfigService;
    private $deviceQueryConfigService;

    public function __construct()
    {
        parent::__construct();
        $this->thirdPartyConfigService = new RecycleThirdPartyConfigService();
        $this->quotationCrawlerConfigService = new QuotationCrawlerConfigService();
        $this->deviceQueryConfigService = new DeviceQueryConfigService();
    }

    public function overview(): array
    {
        $capabilities = [
            $this->buildExpressOrderCapability(),
            $this->buildExpressQueryCapability(),
            $this->buildAddressParseCapability(),
            $this->buildDeviceQueryCapability(),
            $this->buildPrinterCapability(),
            $this->buildQuotationCrawlerCapability(),
        ];

        return [
            'today' => $this->getTodayTotalStats(),
            'capabilities' => $capabilities,
        ];
    }

    private function buildExpressOrderCapability(): array
    {
        $config = $this->thirdPartyConfigService->getConfig($this->site_id, false);
        $section = $config['express_order'] ?? [];
        $providerConfig = $section[ThirdPartyDict::PROVIDER_YISU] ?? [];

        return $this->buildCapability(
            'express_order',
            '快递发件',
            '亿速快递',
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER,
            ThirdPartyDict::PROVIDER_YISU,
            !empty($section['enabled']),
            $this->checkRequired($providerConfig, ['base_url', 'appid', 'app_secret']),
            ['运费预估', '发件下单', '取消/拦截', '运单修改', '运单详情', '面单PDF', '资金查询', '回调接收']
        );
    }

    private function buildExpressQueryCapability(): array
    {
        $config = $this->thirdPartyConfigService->getConfig($this->site_id, false);
        $section = $config['express_query'] ?? [];
        $providerConfig = $section[ThirdPartyDict::PROVIDER_ALI_EXPRESS] ?? [];

        return $this->buildCapability(
            'express_query',
            '快递查询',
            '阿里云市场快递查询',
            ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
            ThirdPartyDict::PROVIDER_ALI_EXPRESS,
            !empty($section['enabled']),
            $this->checkRequired($providerConfig, ['base_url', 'api_path', 'api_key']),
            ['轨迹查询', '异常诊断', '调用日志']
        );
    }

    private function buildDeviceQueryCapability(): array
    {
        $config = $this->deviceQueryConfigService->getConfig($this->site_id, false);
        $defaultChannel = $config['default_channel_key'] ?? '3023_main';
        $channels = $config['channels'] ?? [];
        $channelMap = [];
        foreach ($channels as $channel) {
            if (!empty($channel['key'])) {
                $channelMap[(string)$channel['key']] = $channel;
            }
        }
        $providerConfig = $channelMap[(string)$defaultChannel] ?? [];
        return $this->buildCapability(
            'device_query',
            '设备查询中心',
            $providerConfig['name'] ?? '多渠道设备查询',
            ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
            (string)($providerConfig['provider'] ?? 'multi_channel'),
            !empty($config['enabled']),
            $this->checkRequired($config, ['services', 'channels', 'mappings']),
            ['保修查询', 'IMEI查询', '调用日志']
        );
    }

    private function buildAddressParseCapability(): array
    {
        $config = $this->thirdPartyConfigService->getConfig($this->site_id, false);
        $section = $config['address_parse'] ?? [];
        $providerConfig = $section[ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS] ?? [];

        return $this->buildCapability(
            'address_parse',
            '地址解析',
            '腾讯云市场地址解析',
            ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE,
            ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS,
            !empty($section['enabled']),
            $this->checkRequired($providerConfig, ['base_url', 'api_path', 'secret_id', 'secret_key']),
            ['姓名识别', '手机号识别', '省市区识别', '系统地区匹配']
        );
    }

    private function buildPrinterCapability(): array
    {
        $config = $this->thirdPartyConfigService->getConfig($this->site_id, false);
        $section = $config['printer'] ?? [];
        $providerConfig = $section['xpyun'] ?? [];

        return $this->buildCapability(
            'printer',
            '云打印',
            '芯烨云',
            'printer',
            'xpyun',
            !empty($section['enabled']),
            $this->checkRequired($providerConfig, ['base_url', 'print_label_path']),
            ['标签打印', '打印机状态', '模板打印']
        );
    }

    private function buildQuotationCrawlerCapability(): array
    {
        $rawConfig = $this->getRawQuotationCrawlerConfig();
        $config = !empty($rawConfig) ? $rawConfig : $this->quotationCrawlerConfigService->getConfig($this->site_id, false);
        $provider = (string)($config['provider'] ?? ThirdPartyDict::PROVIDER_CHAONIU_QUOTATION);
        $providerConfig = $config['providers'][$provider] ?? [];
        $configStatus = $this->checkRequired($providerConfig, ['base_url', 'detail_path', 'authorization_token', 'open_id'], ['authorization_token', 'open_id']);

        $capability = $this->buildCapability(
            'quotation_crawler',
            '报价爬虫',
            $providerConfig['name'] ?? '超牛报价',
            ThirdPartyDict::SERVICE_TYPE_QUOTATION_CRAWLER,
            $provider,
            !empty($config['enabled']),
            $configStatus,
            ['报价同步', '自动请求', '备注同步']
        );
        $capability['config_diagnostic'] = [
            'site_id' => $this->site_id,
            'source' => !empty($rawConfig) ? 'sys_config' : 'service_config',
            'provider' => $provider,
            'has_provider_config' => is_array($providerConfig) && !empty($providerConfig) ? 1 : 0,
            'has_authorization_token' => $this->hasConfigValue($providerConfig['authorization_token'] ?? null, true) ? 1 : 0,
            'has_open_id' => $this->hasConfigValue($providerConfig['open_id'] ?? null, true) ? 1 : 0,
        ];
        $capability['config_summary'] = [
            'enabled' => !empty($config['enabled']) ? 1 : 0,
            'provider' => $provider,
            'provider_label' => $providerConfig['name'] ?? '超牛报价',
            'base_url' => (string)($providerConfig['base_url'] ?? ''),
            'detail_path' => (string)($providerConfig['detail_path'] ?? ''),
            'authorization_token' => (string)($providerConfig['authorization_token'] ?? ''),
            'open_id' => (string)($providerConfig['open_id'] ?? ''),
            'authorization_token_saved' => $this->hasConfigValue($providerConfig['authorization_token'] ?? null, true) ? 1 : 0,
            'open_id_saved' => $this->hasConfigValue($providerConfig['open_id'] ?? null, true) ? 1 : 0,
            'timeout' => (int)($providerConfig['timeout'] ?? 0),
        ];

        return $capability;
    }

    private function getRawQuotationCrawlerConfig(): array
    {
        $row = (new SysConfig())->where([
            ['site_id', '=', $this->site_id],
            ['config_key', '=', RecycleConfigKeyDict::QUOTATION_CRAWLER],
        ])->findOrEmpty()->toArray();

        $value = $row['value'] ?? [];
        return is_array($value) ? $value : [];
    }

    private function buildCapability(
        string $key,
        string $name,
        string $providerLabel,
        string $serviceType,
        string $providerName,
        bool $enabled,
        array $configStatus,
        array $actions
    ): array {
        $todayStats = $this->getTodayStats($serviceType, $providerName);
        $lastCall = $this->getLastCall($serviceType, $providerName);

        return [
            'key' => $key,
            'name' => $name,
            'provider_label' => $providerLabel,
            'service_type' => $serviceType,
            'provider_name' => $providerName,
            'enabled' => $enabled ? 1 : 0,
            'configured' => $configStatus['complete'] ? 1 : 0,
            'missing_fields' => $configStatus['missing_fields'],
            'status' => $this->resolveStatus($enabled, $configStatus['complete'], $todayStats, $lastCall),
            'actions' => $actions,
            'today' => $todayStats,
            'last_call' => $lastCall,
        ];
    }

    private function checkRequired(array $config, array $fields, array $secretFields = []): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $config)) {
                $missing[] = $field;
                continue;
            }

            $value = $config[$field];
            if (is_array($value)) {
                if (empty($value)) {
                    $missing[] = $field;
                }
                continue;
            }

            if (!$this->hasConfigValue($value, in_array($field, $secretFields, true))) {
                $missing[] = $field;
            }
        }

        return [
            'complete' => empty($missing),
            'missing_fields' => $missing,
        ];
    }

    private function hasConfigValue($value, bool $secret = false): bool
    {
        if (is_array($value)) {
            return !empty($value);
        }

        $text = trim((string)$value);
        if ($text === '') {
            return false;
        }

        if ($secret && $text === QuotationCrawlerConfigService::MASK_VALUE) {
            return true;
        }

        return true;
    }

    private function maskSecretValue($value): string
    {
        return $this->hasConfigValue($value, true) ? QuotationCrawlerConfigService::MASK_VALUE : '';
    }

    private function getTodayStats(string $serviceType, string $providerName): array
    {
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = strtotime(date('Y-m-d 23:59:59'));
        $query = ThirdPartyApiLog::where([
            ['site_id', '=', $this->site_id],
            ['service_type', '=', $serviceType],
            ['provider_name', '=', $providerName],
        ])->whereBetween('create_at', [$start, $end]);

        $total = $query->count();
        $success = (clone $query)->where('status', ThirdPartyDict::CALL_STATUS_SUCCESS)->count();
        $failed = $total - $success;
        $avgDuration = (clone $query)->avg('duration') ?: 0;

        return [
            'total_calls' => $total,
            'success_calls' => $success,
            'failed_calls' => $failed,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'avg_duration' => round($avgDuration),
        ];
    }

    private function getTodayTotalStats(): array
    {
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = strtotime(date('Y-m-d 23:59:59'));
        $query = ThirdPartyApiLog::where('site_id', $this->site_id)->whereBetween('create_at', [$start, $end]);
        $total = $query->count();
        $success = (clone $query)->where('status', ThirdPartyDict::CALL_STATUS_SUCCESS)->count();
        $failed = $total - $success;

        return [
            'total_calls' => $total,
            'success_calls' => $success,
            'failed_calls' => $failed,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
        ];
    }

    private function getLastCall(string $serviceType, string $providerName): array
    {
        $last = ThirdPartyApiLog::where([
            ['site_id', '=', $this->site_id],
            ['service_type', '=', $serviceType],
            ['provider_name', '=', $providerName],
        ])->field('id,status,error_msg,duration,create_at')->order('create_at desc')->findOrEmpty()->toArray();

        return $last ?: [];
    }

    private function resolveStatus(bool $enabled, bool $configured, array $todayStats, array $lastCall): string
    {
        if (!$enabled) {
            return 'disabled';
        }
        if (!$configured) {
            return 'incomplete';
        }
        if (!empty($lastCall) && (int)($lastCall['status'] ?? 1) === ThirdPartyDict::CALL_STATUS_FAILED) {
            return 'error';
        }
        if (($todayStats['total_calls'] ?? 0) > 0) {
            return 'running';
        }

        return 'ready';
    }
}
