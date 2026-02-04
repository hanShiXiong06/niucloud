<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\third_party;

/**
 * 第三方服务常量类
 * Class ThirdPartyDict
 * @package addon\recycle\app\dict\third_party
 */
class ThirdPartyDict
{
    // ==================== 服务类型 ====================
    const SERVICE_TYPE_DEVICE_QUERY = 'device_query';    // 设备查询
    const SERVICE_TYPE_EXPRESS_ORDER = 'express_order';  // 快递下单
    const SERVICE_TYPE_EXPRESS_QUERY = 'express_query';  // 快递查询
    const SERVICE_TYPE_SMS = 'sms';                      // 短信服务
    const SERVICE_TYPE_PAYMENT = 'payment';              // 支付网关

    // ==================== 服务状态 ====================
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    // ==================== 调用状态 ====================
    const CALL_STATUS_SUCCESS = 1;  // 成功
    const CALL_STATUS_FAILED = 0;   // 失败

    // ==================== 服务提供商 ====================
    const PROVIDER_3023 = '3023';                    // 3023设备查询
    const PROVIDER_ANGUO = 'anguo';                  // 安果ERP快递
    const PROVIDER_ALI_EXPRESS = 'ali_express';      // 阿里快递查询
    const PROVIDER_ALIYUN_SMS = 'aliyun_sms';        // 阿里云短信

    // ==================== 服务类型文本映射 ====================
    const SERVICE_TYPE_TEXT = [
        self::SERVICE_TYPE_DEVICE_QUERY => '设备查询',
        self::SERVICE_TYPE_EXPRESS_ORDER => '快递下单',
        self::SERVICE_TYPE_EXPRESS_QUERY => '快递查询',
        self::SERVICE_TYPE_SMS => '短信服务',
        self::SERVICE_TYPE_PAYMENT => '支付网关',
    ];

    // ==================== 状态文本映射 ====================
    const STATUS_TEXT = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_ENABLED => '启用',
    ];

    // ==================== 调用状态文本映射 ====================
    const CALL_STATUS_TEXT = [
        self::CALL_STATUS_SUCCESS => '成功',
        self::CALL_STATUS_FAILED => '失败',
    ];

    // ==================== 服务提供商文本映射 ====================
    const PROVIDER_TEXT = [
        self::PROVIDER_3023 => '3023设备查询',
        self::PROVIDER_ANGUO => '安果ERP快递',
        self::PROVIDER_ALI_EXPRESS => '阿里快递查询',
        self::PROVIDER_ALIYUN_SMS => '阿里云短信',
    ];

    /**
     * 获取服务类型列表
     * @return array
     */
    public static function getServiceTypes(): array
    {
        return self::SERVICE_TYPE_TEXT;
    }

    /**
     * 获取服务类型名称
     * @param string $serviceType
     * @return string
     */
    public static function getServiceTypeName(string $serviceType): string
    {
        return self::SERVICE_TYPE_TEXT[$serviceType] ?? '未知';
    }

    /**
     * 获取状态列表
     * @return array
     */
    public static function getStatusList(): array
    {
        return self::STATUS_TEXT;
    }

    /**
     * 获取状态名称
     * @param int $status
     * @return string
     */
    public static function getStatusName(int $status): string
    {
        return self::STATUS_TEXT[$status] ?? '未知';
    }

    /**
     * 获取调用状态列表
     * @return array
     */
    public static function getCallStatusList(): array
    {
        return self::CALL_STATUS_TEXT;
    }

    /**
     * 获取调用状态名称
     * @param int $status
     * @return string
     */
    public static function getCallStatusName(int $status): string
    {
        return self::CALL_STATUS_TEXT[$status] ?? '未知';
    }

    /**
     * 获取服务提供商列表
     * @param string $serviceType 服务类型（可选，筛选特定类型的提供商）
     * @return array
     */
    public static function getProviderList(string $serviceType = ''): array
    {
        if (empty($serviceType)) {
            return self::PROVIDER_TEXT;
        }

        // 根据服务类型筛选提供商
        $providers = [];
        switch ($serviceType) {
            case self::SERVICE_TYPE_DEVICE_QUERY:
                $providers = [
                    self::PROVIDER_3023 => self::PROVIDER_TEXT[self::PROVIDER_3023],
                ];
                break;
            case self::SERVICE_TYPE_EXPRESS_ORDER:
                $providers = [
                    self::PROVIDER_ANGUO => self::PROVIDER_TEXT[self::PROVIDER_ANGUO],
                ];
                break;
            case self::SERVICE_TYPE_EXPRESS_QUERY:
                $providers = [
                    self::PROVIDER_ALI_EXPRESS => self::PROVIDER_TEXT[self::PROVIDER_ALI_EXPRESS],
                ];
                break;
            case self::SERVICE_TYPE_SMS:
                $providers = [
                    self::PROVIDER_ALIYUN_SMS => self::PROVIDER_TEXT[self::PROVIDER_ALIYUN_SMS],
                ];
                break;
        }

        return $providers;
    }

    /**
     * 获取服务提供商名称
     * @param string $providerName
     * @return string
     */
    public static function getProviderName(string $providerName): string
    {
        return self::PROVIDER_TEXT[$providerName] ?? '未知';
    }

    /**
     * 验证服务类型是否有效
     * @param string $serviceType
     * @return bool
     */
    public static function isValidServiceType(string $serviceType): bool
    {
        return isset(self::SERVICE_TYPE_TEXT[$serviceType]);
    }

    /**
     * 验证服务提供商是否有效
     * @param string $providerName
     * @return bool
     */
    public static function isValidProvider(string $providerName): bool
    {
        return isset(self::PROVIDER_TEXT[$providerName]);
    }
}
