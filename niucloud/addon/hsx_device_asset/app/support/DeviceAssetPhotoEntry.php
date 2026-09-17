<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\support;

/** 管理移动端拍摄入口，不使用销售端 /wap，也不依赖 PC 开发服务器地址。 */
final class DeviceAssetPhotoEntry
{
    public static function mobileUrl(string $serviceDomain, int $siteId, int $assetId): string
    {
        if ($siteId <= 0 || $assetId <= 0) return '';
        return rtrim($serviceDomain, '/') . '/adminapp/' . $siteId
            . '/addon/hsx_device_asset/pages/photo/capture?id=' . $assetId;
    }
}
