<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

use InvalidArgumentException;

/** 只对适配器声明的字段读写；密码只向前端返回「已设置」而不返回占位符。 */
final class ConnectionFields
{
    public static function values($raw): array
    {
        if (is_string($raw)) $raw = json_decode($raw, true);
        return is_array($raw) ? $raw : [];
    }

    public static function secret(array $field): bool
    {
        return ($field['type'] ?? '') === 'password' || preg_match('/secret|password|token|private.key/i', $field['name']) === 1;
    }

    public static function present(array $fields, array $values): array
    {
        $config = $saved = [];
        foreach ($fields as $f) {
            $name = $f['name'];
            if (self::secret($f)) $saved[$name] = ($values[$name] ?? '') !== '';
            else $config[$name] = $values[$name] ?? ($f['default'] ?? '');
        }
        return ['config' => $config, 'saved_secrets' => $saved];
    }

    public static function merge(array $fields, array $input, array $previous): array
    {
        $result = [];
        foreach ($fields as $f) {
            $key = $f['name'];
            $value = $input[$key] ?? $previous[$key] ?? $f['default'] ?? '';
            if (!is_scalar($value)) throw new InvalidArgumentException($f['label'] . '格式不正确');
            $value = (string)$value;
            if (self::secret($f) && $value === '') $value = (string)($previous[$key] ?? '');
            if (!self::secret($f)) $value = trim($value);
            if (($f['required'] ?? false) && $value === '') throw new InvalidArgumentException('请填写' . $f['label']);
            if ($value !== '' && ($f['type'] ?? '') === 'select' && !in_array($value, array_map('strval', array_column($f['options'], 'value')), true))
                throw new InvalidArgumentException('请重新选择' . $f['label']);
            $result[$key] = $value;
        }
        // 不允许把旧凭据随新目的地址发送。
        foreach (['api_url', 'endpoint', 'gateway_path', 'mode', 'app_id', 'account_code'] as $target) {
            if (isset($previous[$target]) && ($result[$target] ?? '') !== $previous[$target]) {
                foreach ($fields as $f) if (self::secret($f) && !empty($previous[$f['name']]) && ($input[$f['name']] ?? '') === '')
                    throw new InvalidArgumentException('连接地址或账号已变更，请重新填写' . $f['label']);
            }
        }
        return $result;
    }

    public static function seal(array $values, array $fields, int $site, string $adapter): array
    {
        foreach ($fields as $f) {
            $key = $f['name'];
            if (!self::secret($f) || ($values[$key] ?? '') === '') continue;
            $iv = random_bytes(12); $tag = '';
            $data = openssl_encrypt($values[$key], 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, "$site:$adapter:$key");
            if ($data === false) throw new InvalidArgumentException('凭据加密失败，未保存');
            $values[$key] = ['encrypted_v1' => base64_encode($iv . $tag . $data)];
        }
        return $values;
    }

    public static function open(array $values, int $site, string $adapter): array
    {
        foreach ($values as $key => $value) {
            if (!is_array($value) || !isset($value['encrypted_v1'])) continue;
            $raw = base64_decode((string)$value['encrypted_v1'], true);
            $plain = $raw !== false && strlen($raw) >= 29
                ? openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), "$site:$adapter:$key") : false;
            if ($plain === false) throw new InvalidArgumentException('无法读取连接凭据，请核对部署密钥');
            $values[$key] = $plain;
        }
        return $values;
    }

    private static function key(): string
    {
        $key = trim((string)env('app.auth_key', ''));
        if ($key === '') throw new InvalidArgumentException('请平台管理员先配置部署密钥 app.auth_key，再保存连接');
        return hash('sha256', $key, true);
    }
}
