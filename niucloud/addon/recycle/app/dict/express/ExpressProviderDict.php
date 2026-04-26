<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\express;

/**
 * 快递服务商字典
 * Class ExpressProviderDict
 * @package addon\recycle\app\dict\express
 */
class ExpressProviderDict
{
    // 服务商标识
    const PROVIDER_YISU = 'yisu';
    const PROVIDER_ANGUO = 'anguo';
    const PROVIDER_MANUAL = 'manual'; // 手动录入快递号

    // 状态
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;

    /**
     * 获取所有服务商信息
     * @return array
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_YISU => [
                'key' => self::PROVIDER_YISU,
                'name' => '亿速物流',
                'desc' => '支持多家快递品牌，系统可配置开启的快递服务',
                'support_quote' => true,   // 支持报价
                'support_cancel' => true,  // 支持取消
                'support_track' => true,   // 支持追踪
            ],
            self::PROVIDER_ANGUO => [
                'key' => self::PROVIDER_ANGUO,
                'name' => '安果ERP',
                'desc' => '安果ERP快递服务，支持上门取件',
                'support_quote' => false,  // 安果不支持在线报价
                'support_cancel' => true,
                'support_track' => true,
            ],
        ];
    }

    /**
     * 获取服务商名称
     * @param string $provider
     * @return string
     */
    public static function getProviderName(string $provider): string
    {
        $providers = self::getProviders();
        return $providers[$provider]['name'] ?? '未知服务商';
    }

    /**
     * 验证服务商标识是否合法
     * @param string $provider
     * @return bool
     */
    public static function isValid(string $provider): bool
    {
        return in_array($provider, [self::PROVIDER_YISU, self::PROVIDER_ANGUO]);
    }

    /**
     * 获取服务商状态文本映射
     * @return array
     */
    public static function getStatusText(): array
    {
        return [
            self::STATUS_ENABLED => '已启用',
            self::STATUS_DISABLED => '已禁用',
        ];
    }
}
