<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

/** Official SDK encoding/signature primitives, with validated credentials and per-request nonces. */
final class SignedRequest
{
    public static function headers(string $url, array $config, string $secret, int $timestamp, string $nonce): array
    {
        foreach (['account_id', 'username', 'app_id', 'organization'] as $field) {
            $value = (string)($config[$field] ?? '');
            if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F,]/', $value)) {
                throw new ApiFailure('credentials_incomplete', '请检查账套 ID、集成用户、应用 ID 和组织编码');
            }
        }
        if ($secret === '' || strlen($secret) > 2000 || $timestamp <= 0 || !preg_match('/^[a-zA-Z0-9]{1,64}$/D', $nonce)) {
            throw new ApiFailure('credentials_incomplete', '应用授权信息不完整');
        }
        $app = $config['app_id'];
        $parts = explode('_', $app);
        if (count($parts) !== 2 || !preg_match('/^[a-zA-Z0-9-]+$/D', $parts[0])
            || !preg_match('#^[a-zA-Z0-9+/]{32}$#D', $parts[1])) {
            throw new ApiFailure('app_id_format', '需要新版第三方系统登录授权的完整应用 ID；请勿填写应用名称');
        }
        OfficialSdk::load();
        $clientSecret = \kingdee_cdp_webapi_sdk\util\EncodeUtil::base64_xor_encode($parts[1], '0054f397c6234378b09ca7d3e5debce7');
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') throw new ApiFailure('endpoint_invalid', '接口路径不合法');
        $signature = "POST\n" . rawurlencode($path) . "\n\nx-api-nonce:" . $nonce . "\nx-api-timestamp:" . $timestamp . "\n";
        $appData = $config['account_id'] . ',' . $config['username'] . ',2052,' . $config['organization'];
        // This protocol uses base64 of lowercase hexadecimal HMAC, not base64 of raw bytes.
        return [
            'Content-Type' => 'application/json', 'Accept-Charset' => 'utf-8',
            'X-Api-ClientID' => $parts[0], 'X-Api-Auth-Version' => '2.0',
            'x-api-timestamp' => (string)$timestamp, 'x-api-nonce' => $nonce,
            'x-api-signheaders' => 'x-api-timestamp,x-api-nonce',
            'X-Api-Signature' => \kingdee_cdp_webapi_sdk\util\EncodeUtil::HmacSHA256($signature, $clientSecret),
            'X-Kd-Appkey' => $app, 'X-Kd-Appdata' => base64_encode($appData),
            'X-Kd-Signature' => \kingdee_cdp_webapi_sdk\util\EncodeUtil::HmacSHA256($app . $appData, $secret),
        ];
    }
}
