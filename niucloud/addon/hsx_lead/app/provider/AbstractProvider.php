<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

abstract class AbstractProvider implements ProviderContract
{
    protected TransportContract $transport;

    public function __construct(?TransportContract $transport = null)
    {
        $this->transport = $transport ?? new GuzzleJsonTransport();
    }

    protected function accessToken(#[\SensitiveParameter] array $credentials): string
    {
        $token = $credentials['access_token'] ?? $credentials['token'] ?? '';
        if (!is_string($token) || $token === '' || strlen($token) > 16384 || !preg_match('/^[\x21-\x7e]+$/D', $token)) {
            throw new ProviderException('invalid_credentials', '请提供有效的 ERP 授权凭据');
        }
        return $token;
    }

    protected function id(mixed $value, string $label, bool $optional = false): ?string
    {
        if ($optional && ($value === null || $value === '')) return null;
        if ((!is_string($value) && !is_int($value)) || trim((string) $value) === '' || strlen((string) $value) > 191) {
            throw new ProviderException('invalid_scope', $label . '缺失或格式不正确');
        }
        return trim((string) $value);
    }

    protected function text(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) return null;
        return trim((string) $value) === '' ? null : trim((string) $value);
    }

    protected function scalar(mixed $value): string|int|bool|null
    {
        return is_string($value) || is_int($value) || is_bool($value) ? $value : null;
    }

    /** Decimal strings only; no binary-float arithmetic, guesses or cost-field fallback. */
    protected function money(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) return null;
        $raw = trim((string) $value);
        if (!preg_match('/^[0-9]{1,14}(?:\.[0-9]{1,2})?$/D', $raw)) return null;
        $parts = explode('.', $raw, 2);
        $whole = ltrim($parts[0], '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($parts[1] ?? '', 2, '0');
    }

    protected function url(mixed $value): ?string
    {
        $url = $this->text($value);
        if ($url === null || strlen($url) > 8192 || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? '') === 'https' && !isset($parsed['user']) && !isset($parsed['pass']) ? $url : null;
    }

    protected function pagination(array $cursor, array $scope): array
    {
        $page = $cursor['page'] ?? 1;
        $size = $scope['page_size'] ?? 100;
        foreach ([$page, $size] as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
                throw new ProviderException('invalid_scope', 'ERP 分页参数不正确');
            }
        }
        if ((int) $page < 1 || (int) $page > 100000 || (int) $size < 1 || (int) $size > 100) {
            throw new ProviderException('invalid_scope', 'ERP 每页数量应为 1—100，页码不可超过安全上限');
        }
        return [(int) $page, (int) $size];
    }

    protected function assertBusinessSuccess(array $page): void
    {
        $code = $page['code'] ?? null;
        if ($code === 401 || $code === '401' || $code === 403 || $code === '403') {
            throw new ProviderException('auth_required', 'ERP 授权已失效，请重新授权');
        }
        if ($code !== 200 && $code !== '200') {
            throw new ProviderException('upstream_error', 'ERP 接口未成功，未更新库存');
        }
    }

    protected function identity(array $scope): array
    {
        return [
            'tenant_id' => $this->id($scope['tenant_id'] ?? null, '来源租户标识', true),
            'store_id' => $this->id($scope['store_id'] ?? null, '来源门店标识', true),
            'store_name' => $this->text($scope['store_name'] ?? null),
        ];
    }

    protected function common(array $row, array $context, array $mapped): array
    {
        $connection = $this->id($context['connection_id'] ?? null, '连接标识');
        $sourceId = $this->id($mapped['id'], '来源商品标识');
        $store = $this->id($mapped['store_id'], '来源门店标识');
        $tenant = $this->id($mapped['tenant_id'] ?? null, '来源租户标识', true);
        foreach (['store_id' => $store, 'tenant_id' => $tenant] as $name => $actual) {
            if (isset($context[$name]) && $context[$name] !== '' && $this->id($context[$name], '预期来源标识') !== $actual) {
                throw new ProviderException('scope_mismatch', 'ERP 返回数据与绑定的租户或门店不一致，已停止同步');
            }
        }
        $fetchedAt = $context['fetched_at'] ?? gmdate('Y-m-d\TH:i:s\Z');
        if (!is_string($fetchedAt) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2})$/D', $fetchedAt) || strtotime($fetchedAt) === false) {
            throw new ProviderException('invalid_scope', '库存采集时间格式不正确');
        }
        $images = array_values(array_unique(array_filter(array_map(fn($url) => $this->url($url), $mapped['images'] ?? []))));
        return [
            'source_key' => implode(':', array_map('rawurlencode', [$this->key(), $connection, $tenant ?? '', $store, $sourceId])),
            'source_id' => $sourceId,
            'source_store_id' => $store,
            'tenant_id' => $tenant,
            'title' => $this->text($mapped['title'] ?? null),
            'brand' => $this->text($mapped['brand'] ?? null),
            'capacity' => $this->text($mapped['capacity'] ?? null),
            'color' => $this->text($mapped['color'] ?? null),
            'retail_price' => $this->money($mapped['retail_price'] ?? null),
            'trade_price' => $this->money($mapped['trade_price'] ?? null),
            'imei' => $this->text($mapped['imei'] ?? null),
            'cover' => $images[0] ?? null,
            'images' => $images,
            'source_status' => $mapped['source_status'] ?? [],
            // No stock/sale enum has been verified; never infer sellability from list presence.
            'availability' => 'unknown',
            'safe_snapshot' => $row,
            'fetched_at' => $fetchedAt,
        ];
    }
}
