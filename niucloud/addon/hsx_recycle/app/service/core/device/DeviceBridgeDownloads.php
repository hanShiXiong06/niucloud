<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device;

use core\exception\CommonException;
use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
use app\service\core\sys\CoreConfigService;

/** 只发布安装包元信息，不代理下载、不访问外部地址。 */
class DeviceBridgeDownloads
{
    public static function getConfig(): array
    {
        $saved = (new CoreConfigService())->getConfigValue(0, RecycleConfigKeyDict::DEVICE_BRIDGE_DOWNLOADS);
        return self::sanitize(is_array($saved) ? $saved : []);
    }

    public static function setConfig(array $data): bool
    {
        $config = self::sanitize($data, true);
        return (bool)(new CoreConfigService())->setConfig(0, RecycleConfigKeyDict::DEVICE_BRIDGE_DOWNLOADS, $config);
    }

    public static function defaults(): array
    {
        return [
            'windows_url' => '',
            'windows_version' => '',
            'macos_arm64_url' => '',
            'macos_arm64_version' => '',
            'tutorial_url' => '',
        ];
    }

    public static function sanitize(array $data, bool $strict = false): array
    {
        $result = self::defaults();
        foreach ($result as $key => $unused) {
            $value = is_string($data[$key] ?? null) ? trim($data[$key]) : '';
            if ($value === '') continue;
            $isVersion = substr($key, -8) === '_version';
            $valid = $isVersion
                ? strlen($value) <= 40 && preg_match('/^\d{1,6}(?:\.\d{1,6}){1,3}$/D', $value) === 1
                : self::validUrl($value);
            if (!$valid && $strict) {
                throw new CommonException($isVersion
                    ? '设备桥版本号格式不正确，请填写数字版本，如 0.2.0'
                    : '设备桥下载或教程地址不正确，请填写完整 http(s) 地址');
            }
            $result[$key] = $valid ? $value : '';
        }
        return $result;
    }

    private static function validUrl(string $value): bool
    {
        if (strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) return false;
        $parts = parse_url($value);
        return is_array($parts)
            && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass'])
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
