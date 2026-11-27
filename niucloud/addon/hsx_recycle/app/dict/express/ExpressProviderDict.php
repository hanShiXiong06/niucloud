<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\express;

/**
 * 快递服务商字典
 * Class ExpressProviderDict
 * @package addon\hsx_recycle\app\dict\express
 */
class ExpressProviderDict
{
    // 服务商标识
    const PROVIDER_YISU = 'yisu';
    const PROVIDER_KUAIDI100 = 'kuaidi100';
    const PROVIDER_SF_DIRECT = 'sf_direct';
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
            self::PROVIDER_SF_DIRECT => [
                'key' => self::PROVIDER_SF_DIRECT, 'name' => '顺丰直连',
                'desc' => '由物流中心独立管理上门取件账号和产品，不共用电子面单开关',
                'support_quote' => false, 'support_cancel' => true, 'support_track' => false,
                'configuration_managed_by' => 'hsx_express',
                'configuration_path' => '/hsx_express/config?provider=sf_direct&scene=pickup',
            ],
            self::PROVIDER_KUAIDI100 => [
                'key' => self::PROVIDER_KUAIDI100, 'name' => '快递100',
                'desc' => '管理员固定承运商与产品，预约上门取件；各站点独立结算',
                'support_quote' => true, 'support_cancel' => true, 'support_track' => true,
            ],
            self::PROVIDER_YISU => [
                'key' => self::PROVIDER_YISU,
                'name' => '亿速物流',
                'desc' => '支持多家快递品牌，系统可配置开启的快递服务',
                'support_quote' => true,   // 支持报价
                'support_cancel' => true,  // 支持取消
                'support_track' => true,   // 支持追踪
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
        return array_key_exists($provider, self::getProviders());
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
