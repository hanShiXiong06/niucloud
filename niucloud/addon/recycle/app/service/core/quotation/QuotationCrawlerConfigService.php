<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\quotation;

use addon\recycle\app\dict\config\RecycleConfigKeyDict;
use addon\recycle\app\model\quotation\RecycleQuotationConfig;
use app\model\sys\SysConfig;
use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Cache;

/**
 * 报价爬虫站点级配置
 */
class QuotationCrawlerConfigService extends BaseCoreService
{
    const MASK_VALUE = '******';

    private $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new CoreConfigService();
    }

    public function getConfig(int $siteId, bool $maskSecret = false): array
    {
        $saved = $this->readStoredValue($siteId);
        $config = $this->normalizeConfig($this->mergeConfig($this->getDefaultConfig(), $this->normalizeInputConfig(is_array($saved) ? $saved : [])));
        return $maskSecret ? $this->maskSecret($config) : $config;
    }

    public function setConfig(int $siteId, array $data): bool
    {
        $this->saveConfig($siteId, $data);
        return true;
    }

    public function saveConfig(int $siteId, array $data, bool $maskSecret = false): array
    {
        $old = $this->getConfig($siteId, false);
        $config = $this->normalizeConfig($this->mergeConfig($this->getDefaultConfig(), $this->normalizeInputConfig($data)));
        $config = $this->keepMaskedSecret($config, $old);
        $this->writeConfig($siteId, $config);
        $saved = $this->readRawConfig($siteId);
        $provider = (string)($config['provider'] ?? 'chaoniu');
        $expected = $config['providers'][$provider] ?? [];
        $actual = $saved['providers'][$provider] ?? [];

        foreach (['authorization_token', 'open_id'] as $field) {
            if ((string)($expected[$field] ?? '') !== (string)($actual[$field] ?? '')) {
                throw new CommonException('报价爬虫配置保存后校验失败：' . $field);
            }
        }

        return $maskSecret ? $this->maskSecret($saved) : $saved;
    }

    private function writeConfig(int $siteId, array $config): void
    {
        $where = [
            ['site_id', '=', $siteId],
            ['config_key', '=', RecycleConfigKeyDict::QUOTATION_CRAWLER],
        ];
        $model = new SysConfig();
        $row = $model->where($where)->findOrEmpty();
        if ($row->isEmpty()) {
            $result = $model->create([
                'site_id' => $siteId,
                'config_key' => RecycleConfigKeyDict::QUOTATION_CRAWLER,
                'value' => $config,
                'create_time' => time(),
            ]);
            if (!$result) {
                throw new CommonException('报价爬虫配置创建失败');
            }
        } else {
            $row->value = $config;
            $row->update_time = time();
            $result = $row->save();
            if ($result === false) {
                throw new CommonException('报价爬虫配置更新失败');
            }
        }

        Cache::tag(CoreConfigService::$cache_tag_name . $siteId)->clear();
    }

    private function readRawConfig(int $siteId): array
    {
        Cache::tag(CoreConfigService::$cache_tag_name . $siteId)->clear();
        $value = $this->readStoredValue($siteId);
        return $this->normalizeConfig($this->mergeConfig($this->getDefaultConfig(), $this->normalizeInputConfig(is_array($value) ? $value : [])));
    }

    private function readStoredValue(int $siteId)
    {
        $row = (new SysConfig())->where([
            ['site_id', '=', $siteId],
            ['config_key', '=', RecycleConfigKeyDict::QUOTATION_CRAWLER],
        ])->findOrEmpty()->toArray();

        return $row['value'] ?? [];
    }

    public function getProviderConfig(int $siteId, string $provider = '', bool $allowLegacyFallback = true): array
    {
        $config = $this->getConfig($siteId);
        if (empty($config['enabled'])) {
            return [];
        }

        $provider = $provider !== '' ? $provider : (string)($config['provider'] ?? 'chaoniu');
        $providerConfig = $config['providers'][$provider] ?? [];
        if (!is_array($providerConfig)) {
            return [];
        }

        if ($allowLegacyFallback) {
            $providerConfig = $this->mergeLegacyQuotationToken($siteId, $providerConfig);
        }

        return $providerConfig;
    }

    public function getDiagnostic(int $siteId): array
    {
        $rawRow = (new SysConfig())->where([
            ['site_id', '=', $siteId],
            ['config_key', '=', RecycleConfigKeyDict::QUOTATION_CRAWLER],
        ])->findOrEmpty()->toArray();
        $rawValue = $rawRow['value'] ?? [];
        $config = $this->getConfig($siteId, false);
        $provider = (string)($config['provider'] ?? 'chaoniu');

        return [
            'site_id' => $siteId,
            'config_key' => RecycleConfigKeyDict::QUOTATION_CRAWLER,
            'source' => 'direct_sys_config',
            'row_exists' => !empty($rawRow) ? 1 : 0,
            'raw_value' => is_array($rawValue) ? $rawValue : $rawValue,
            'normalized_config' => $config,
            'provider' => $provider,
            'provider_config' => $config['providers'][$provider] ?? null,
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'enabled' => 1,
            'provider' => 'chaoniu',
            'providers' => [
                'chaoniu' => [
                    'name' => '超牛报价',
                    'base_url' => 'https://daheng.chaoniu.top',
                    'detail_path' => '/api/v1/quotation/detail',
                    'version' => '2.4.1',
                    'app_id' => '',
                    'platform' => '2',
                    'authorization_token' => '',
                    'open_id' => '',
                    'referer' => 'https://servicewechat.com/wx7c82fedb54be53fc/41/page-frame.html',
                    'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.71(0x18004730) NetType/WIFI Language/zh_CN',
                    'accept_encoding' => 'gzip,compress,br,deflate',
                    'timeout' => 30,
                ],
            ],
        ];
    }

    private function mergeConfig(array $default, array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($default[$key]) && is_array($default[$key])) {
                $default[$key] = $this->mergeConfig($default[$key], $value);
            } else {
                $default[$key] = $value;
            }
        }

        return $default;
    }

    private function normalizeConfig(array $config): array
    {
        $config['enabled'] = (int)(bool)($config['enabled'] ?? 0);
        $config['provider'] = (string)($config['provider'] ?? 'chaoniu');

        foreach ($config['providers'] ?? [] as $provider => $providerConfig) {
            if (!is_array($providerConfig)) {
                continue;
            }
            $config['providers'][$provider]['base_url'] = rtrim((string)($providerConfig['base_url'] ?? ''), '/');
            $path = '/' . ltrim((string)($providerConfig['detail_path'] ?? ''), '/');
            $config['providers'][$provider]['detail_path'] = $path;
            $config['providers'][$provider]['timeout'] = max(1, (int)($providerConfig['timeout'] ?? 30));
        }

        return $config;
    }

    private function normalizeInputConfig(array $data): array
    {
        if (isset($data['providers']) && is_array($data['providers'])) {
            return $data;
        }

        $provider = (string)($data['provider'] ?? 'chaoniu');
        $providerData = is_array($data[$provider] ?? null) ? $data[$provider] : [];
        $flatProviderFields = [
            'name',
            'base_url',
            'detail_path',
            'version',
            'app_id',
            'platform',
            'authorization_token',
            'open_id',
            'referer',
            'user_agent',
            'accept_encoding',
            'timeout',
        ];

        foreach ($flatProviderFields as $field) {
            if (array_key_exists($field, $data) && !is_array($data[$field])) {
                $providerData[$field] = $data[$field];
            }
        }

        if (!empty($providerData)) {
            $data['providers'] = [
                $provider => $providerData,
            ];
        }

        unset($data['chaoniu']);
        return $data;
    }

    private function maskSecret(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->maskSecret($value);
                continue;
            }

            if ($this->isSecretField((string)$key) && (string)$value !== '') {
                $config[$key] = self::MASK_VALUE;
            }
        }

        return $config;
    }

    private function keepMaskedSecret(array $config, array $old): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->keepMaskedSecret($value, is_array($old[$key] ?? null) ? $old[$key] : []);
                continue;
            }

            if ($this->isSecretField((string)$key) && $value === self::MASK_VALUE && isset($old[$key])) {
                $config[$key] = $old[$key];
            }
        }

        return $config;
    }

    private function isSecretField(string $field): bool
    {
        return in_array($field, ['authorization_token', 'open_id'], true);
    }

    private function mergeLegacyQuotationToken(int $siteId, array $providerConfig): array
    {
        if (
            trim((string)($providerConfig['authorization_token'] ?? '')) !== ''
            && trim((string)($providerConfig['open_id'] ?? '')) !== ''
        ) {
            return $providerConfig;
        }

        $legacy = (new RecycleQuotationConfig())->where([
            ['site_id', '=', $siteId],
            ['is_enable', '=', 1],
        ])->where(function ($query) {
            $query->whereOr([
                ['authorization_token', '<>', ''],
                ['open_id', '<>', ''],
            ]);
        })->order('id desc')->findOrEmpty()->toArray();

        if (!empty($legacy)) {
            if (trim((string)($providerConfig['authorization_token'] ?? '')) === '') {
                $providerConfig['authorization_token'] = (string)($legacy['authorization_token'] ?? '');
            }
            if (trim((string)($providerConfig['open_id'] ?? '')) === '') {
                $providerConfig['open_id'] = (string)($legacy['open_id'] ?? '');
            }
            $providerConfig['_credential_source'] = 'legacy_quotation_config';
        }

        return $providerConfig;
    }
}
