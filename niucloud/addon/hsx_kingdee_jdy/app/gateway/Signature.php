<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\gateway;

final class Signature
{
    public static function hmac(string $value, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $value, $secret));
    }

    public static function canonical(string $method, string $path, array $query, string $timestamp, string $nonce): string
    {
        ksort($query, SORT_STRING);
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = rawurlencode(rawurlencode((string)$key)) . '=' . rawurlencode(rawurlencode((string)$value));
        }
        return strtoupper($method) . "\n" . rawurlencode($path) . "\n" . implode('&', $pairs)
            . "\nx-api-nonce:" . $nonce . "\nx-api-timestamp:" . $timestamp . "\n";
    }

    public static function headers(string $clientId, string $secret, string $path, array $query,
        ?string $timestamp = null, ?string $nonce = null): array
    {
        $timestamp = $timestamp ?? (string)(int)floor(microtime(true) * 1000);
        $nonce = $nonce ?? (string)random_int(1, PHP_INT_MAX);
        return [
            'Content-Type' => 'application/json', 'X-Api-ClientID' => $clientId,
            'X-Api-Auth-Version' => '2.0', 'X-Api-TimeStamp' => $timestamp, 'X-Api-Nonce' => $nonce,
            'X-Api-SignHeaders' => 'X-Api-TimeStamp,X-Api-Nonce',
            'X-Api-Signature' => self::hmac(self::canonical('GET', $path, $query, $timestamp, $nonce), $secret),
        ];
    }
}
