<?php
declare(strict_types=1);

namespace addon\hsx_ysepay\core\support;

use core\exception\PayException;

class YsepayCrypto
{
    private string $privateKey;
    private string $publicCertificate;

    public function __construct(string $merchantPfx, string $pfxPassword, string $ysepayCertificate)
    {
        if (!extension_loaded('openssl')) {
            throw new PayException('银盛支付需要启用 PHP OpenSSL 扩展');
        }

        $pfx = $this->decodeCertificateValue($merchantPfx);
        if ($pfx === '') {
            throw new PayException('银盛商户 PFX 证书或证书密码不正确');
        }
        $privateKey = $this->readPrivateKey($pfx, $pfxPassword);
        if ($privateKey === '') {
            throw new PayException('银盛商户 PFX 证书或证书密码不正确');
        }

        $publicCertificate = $this->normalizePublicCertificate($ysepayCertificate);
        if (openssl_pkey_get_public($publicCertificate) === false) {
            throw new PayException('银盛平台公钥证书格式不正确');
        }

        $this->privateKey = $privateKey;
        $this->publicCertificate = $publicCertificate;
    }

    public function sign(array $data): string
    {
        $signature = '';
        if (!openssl_sign($this->canonicalize($data), $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new PayException('银盛支付请求签名失败');
        }
        return base64_encode($signature);
    }

    public function verify(array $data, string $signature): bool
    {
        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            return false;
        }
        return openssl_verify(
            $this->canonicalize($data),
            $decoded,
            $this->publicCertificate,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    public function encryptKey(string $key): string
    {
        $encrypted = '';
        if (!openssl_public_encrypt($key, $encrypted, $this->publicCertificate, OPENSSL_PKCS1_PADDING)) {
            throw new PayException('银盛支付 AES 密钥加密失败');
        }
        return base64_encode($encrypted);
    }

    public function encryptBusiness(array $businessData, string $key): string
    {
        $json = json_encode($businessData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new PayException('银盛支付业务参数编码失败');
        }
        $encrypted = openssl_encrypt($json, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        if ($encrypted === false) {
            throw new PayException('银盛支付业务参数加密失败');
        }
        return base64_encode($encrypted);
    }

    public function decryptBusiness(string $businessData, string $key): array
    {
        $decoded = base64_decode($businessData, true);
        if ($decoded === false) {
            throw new PayException('银盛支付业务响应不是有效的 Base64 数据');
        }
        $plain = openssl_decrypt($decoded, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        if ($plain === false) {
            throw new PayException('银盛支付业务响应解密失败');
        }
        $result = json_decode($plain, true);
        if (!is_array($result)) {
            throw new PayException('银盛支付业务响应 JSON 格式不正确');
        }
        return $result;
    }

    public function canonicalize(array $data): string
    {
        unset($data['sign']);
        ksort($data, SORT_STRING);
        $pairs = [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            }
            $pairs[] = (string)$key . '=' . (string)$value;
        }
        return implode('&', $pairs);
    }

    private function decodeCertificateValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '-----BEGIN')) {
            return $value;
        }
        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : $decoded;
    }

    private function normalizePublicCertificate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '-----BEGIN CERTIFICATE-----')) {
            return $value;
        }
        $der = base64_decode($value, true);
        if ($der === false) {
            return '';
        }
        if (str_contains($der, '-----BEGIN CERTIFICATE-----')) {
            return $der;
        }
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function readPrivateKey(string $pfx, string $password): string
    {
        $certificates = [];
        if (openssl_pkcs12_read($pfx, $certificates, $password)) {
            return (string)($certificates['pkey'] ?? '');
        }

        // 银盛部分存量 PFX 使用 OpenSSL 3 默认禁用的旧式加密算法。
        // 仅在 PHP 原生读取失败时，通过 openssl -legacy 从临时 PFX 中提取私钥。
        if (!function_exists('proc_open')) {
            return '';
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'ysepay-pfx-');
        if ($tempFile === false) {
            return '';
        }

        try {
            if (file_put_contents($tempFile, $pfx, LOCK_EX) === false) {
                return '';
            }
            @chmod($tempFile, 0600);
            $pipes = [];
            $process = @proc_open([
                'openssl',
                'pkcs12',
                '-legacy',
                '-in',
                $tempFile,
                '-nocerts',
                '-nodes',
                '-passin',
                'stdin',
            ], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            if (!is_resource($process)) {
                return '';
            }

            fwrite($pipes[0], $password . PHP_EOL);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || !is_string($output)) {
                return '';
            }
            if (!preg_match(
                '/-----BEGIN (?:RSA )?PRIVATE KEY-----.*?-----END (?:RSA )?PRIVATE KEY-----/s',
                $output,
                $matches
            )) {
                return '';
            }
            return (string)$matches[0];
        } finally {
            @unlink($tempFile);
        }
    }
}
