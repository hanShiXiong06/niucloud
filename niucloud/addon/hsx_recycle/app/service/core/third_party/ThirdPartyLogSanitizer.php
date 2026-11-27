<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party;

/** 第三方调用日志脱敏，防止密钥、手机号和完整地址进入可查询日志。 */
class ThirdPartyLogSanitizer
{
    private const SECRET_KEYS = [
        'api_key', 'apikey', 'app_secret', 'appsecret', 'secret', 'secret_key',
        'secretid', 'secret_id', 'token', 'access_token', 'authorization',
        'password', 'passwd', 'user_key', 'sign', 'signature',
    ];

    public function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string)$key);
            if (in_array($normalizedKey, self::SECRET_KEYS, true)) {
                $data[$key] = $value === '' ? '' : '******';
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
                continue;
            }
            if ($this->isMobileKey($normalizedKey)) {
                $data[$key] = $this->maskMobile((string)$value);
                continue;
            }
            if ($this->isAddressKey($normalizedKey)) {
                $data[$key] = $this->maskAddress((string)$value);
            }
        }

        return $data;
    }

    private function isMobileKey(string $key): bool
    {
        return strpos($key, 'mobile') !== false || strpos($key, 'phone') !== false || $key === 'tel';
    }

    private function isAddressKey(string $key): bool
    {
        return $key === 'address' || substr($key, -7) === 'address';
    }

    private function maskMobile(string $mobile): string
    {
        if (strlen($mobile) < 7) {
            return $mobile === '' ? '' : '******';
        }
        return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
    }

    private function maskAddress(string $address): string
    {
        if ($address === '') {
            return '';
        }
        $prefix = function_exists('mb_substr') ? mb_substr($address, 0, 6) : substr($address, 0, 12);
        return $prefix . '***';
    }
}
