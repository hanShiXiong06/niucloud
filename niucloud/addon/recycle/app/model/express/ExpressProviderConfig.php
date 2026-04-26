<?php
declare(strict_types=1);

namespace addon\recycle\app\model\express;

use addon\recycle\app\dict\express\ExpressProviderDict;
use core\base\BaseModel;

/**
 * 快递服务商配置模型
 * Class ExpressProviderConfig
 * @package addon\recycle\app\model\express
 */
class ExpressProviderConfig extends BaseModel
{
    protected $name = 'recycle_express_provider_config';

    protected $type = [
        'config' => 'json',
    ];

    // 自动时间戳
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    /**
     * 获取站点启用的默认服务商
     * @param int $siteId
     * @return string 服务商标识
     */
    public static function getDefaultProvider(int $siteId): string
    {
        $config = self::where([
            ['site_id', '=', $siteId],
            ['status', '=', ExpressProviderDict::STATUS_ENABLED],
            ['is_default', '=', 1],
        ])->find();

        if ($config) {
            return $config['provider'];
        }

        // 没有默认的，取第一个启用的
        $config = self::where([
            ['site_id', '=', $siteId],
            ['status', '=', ExpressProviderDict::STATUS_ENABLED],
        ])->order('sort desc, id asc')->find();

        return $config ? $config['provider'] : '';
    }

    /**
     * 获取站点所有启用的服务商
     * @param int $siteId
     * @return array
     */
    public static function getEnabledProviders(int $siteId): array
    {
        return self::where([
            ['site_id', '=', $siteId],
            ['status', '=', ExpressProviderDict::STATUS_ENABLED],
        ])->order('sort desc, id asc')->select()->toArray();
    }

    /**
     * 获取站点指定服务商的配置
     * @param int $siteId
     * @param string $provider
     * @return array|null
     */
    public static function getProviderConfig(int $siteId, string $provider): ?array
    {
        $config = self::where([
            ['site_id', '=', $siteId],
            ['provider', '=', $provider],
        ])->find();

        return $config ? $config->toArray() : null;
    }

    /**
     * 初始化站点服务商配置（安装插件时调用）
     * @param int $siteId
     * @return void
     */
    public static function initSiteConfig(int $siteId): void
    {
        $providers = ExpressProviderDict::getProviders();
        $isFirst = true;

        foreach ($providers as $key => $provider) {
            $exists = self::where([
                ['site_id', '=', $siteId],
                ['provider', '=', $key],
            ])->find();

            if (!$exists) {
                self::create([
                    'site_id' => $siteId,
                    'provider' => $key,
                    'provider_name' => $provider['name'],
                    'status' => ExpressProviderDict::STATUS_DISABLED,
                    'is_default' => $isFirst ? 1 : 0,
                    'config' => [],
                    'sort' => $isFirst ? 100 : 0,
                ]);
                $isFirst = false;
            }
        }
    }

    /**
     * 设为默认（同时取消其他默认）
     * @param int $siteId
     * @param string $provider
     * @return void
     */
    public static function setDefault(int $siteId, string $provider): void
    {
        // 先取消所有默认
        self::where([
            ['site_id', '=', $siteId],
        ])->update(['is_default' => 0]);

        // 设为默认
        self::where([
            ['site_id', '=', $siteId],
            ['provider', '=', $provider],
        ])->update(['is_default' => 1]);
    }

    /**
     * 搜索器: site_id
     */
    public function searchSiteIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('site_id', '=', $value);
        }
    }

    /**
     * 搜索器: status
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', '=', $value);
        }
    }
}
