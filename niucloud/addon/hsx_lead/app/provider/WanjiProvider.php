<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

final class WanjiProvider extends AbstractProvider
{
    public const LOGIN_ENDPOINT = 'https://lite.wanjisaas.com/lite-api/auth/login';
    public const STOCK_ENDPOINT = 'https://lite.wanjisaas.com/lite-api/erp/stock/selectMachineDetailV2';
    private const QUERY_KEYS = ['machineType', 'productNames', 'productFilters', 'channelIdList', 'paymentStatus', 'wjtShare',
        'storeMallFlag', 'isAsc', 'orderByColumn', 'colorList', 'memoryList', 'ownerId', 'finenessList', 'buyChannelList', 'memoryLikeList'];
    private const SNAPSHOT_KEYS = ['tenantId', 'machineId', 'shopId', 'shopName', 'machineStatus', 'storeMallFlag', 'machineType',
        'categoryId', 'categoryName', 'brandId', 'brandName', 'productId', 'productName', 'imeiNoFirst', 'memory', 'color',
        'buyChannel', 'guarantee', 'batteryHealth', 'batterycycles', 'fineness', 'storeWarranty', 'systemVersion', 'skuName',
        'retailAmount', 'wholesaleAmount'];

    public function key(): string { return 'wanji'; }

    public function login(#[\SensitiveParameter] array $credentials): array
    {
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;
        if (!is_string($username) || trim($username) === '' || strlen($username) > 512 ||
            !is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new ProviderException('invalid_credentials', '请填写万机管家账号和密码');
        }
        $page = $this->transport->post(self::LOGIN_ENDPOINT, $this->headers($credentials), ['username' => trim($username), 'password' => $password]);
        $this->assertBusinessSuccess($page);
        return $this->loginResult($page);
    }

    private function loginResult(#[\SensitiveParameter] array $page): array
    {
        // Authorized live schema: {code:200,data:{access_token,link,expires_in}}.
        // Never hunt recursively for tokens or return the original authentication body.
        $data = $page['data'] ?? null;
        if (!is_array($data)) throw new ProviderException('schema_changed', '万机登录响应结构变化');
        try {
            $token = $this->accessToken(['access_token' => $data['access_token'] ?? null]);
        } catch (ProviderException $exception) {
            throw new ProviderException('schema_changed', '万机登录响应未包含有效授权凭据');
        }
        $rawExpiry = $data['expires_in'] ?? null;
        $expiry = (is_int($rawExpiry) || is_string($rawExpiry)) && preg_match('/^[1-9][0-9]{0,7}$/D', (string) $rawExpiry)
            && (int) $rawExpiry <= 31536000 ? (int) $rawExpiry : null;
        return [
            'access_token' => $token,
            'expires_in' => is_int($rawExpiry) || (is_string($rawExpiry) && strlen($rawExpiry) <= 32 && ctype_digit($rawExpiry)) ? $rawExpiry : null,
            'expires_at' => $expiry === null ? null : time() + $expiry,
            // JWT claims are hints from this HTTPS login response, not an authorization
            // or proof of store ownership. Every inventory row must still be checked.
            'identity' => ['tenant_id' => $this->tenantHint($token), 'store_id' => null, 'store_name' => null, 'verified' => false],
            'warnings' => $expiry === null ? ['ERP 未返回可核验的有效期，到期后需重新授权'] : [],
        ];
    }

    private function tenantHint(#[\SensitiveParameter] string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || strlen($parts[1]) > 32768 || !preg_match('/^[A-Za-z0-9_\-]+$/D', $parts[1])) return null;
        $payload = base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4), true);
        if ($payload === false) return null;
        try {
            $claims = json_decode($payload, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            return is_array($claims) ? $this->id($claims['tenant_id'] ?? null, '租户提示', true) : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function fetchPage(#[\SensitiveParameter] array $credentials, array $cursor, array $scope): array
    {
        [$pageNum, $pageSize] = $this->pagination($cursor, $scope);
        $query = $scope['query'] ?? [];
        if (!is_array($query) || array_diff(array_keys($query), self::QUERY_KEYS)) {
            throw new ProviderException('invalid_scope', '存在未经核验的万机库存查询参数');
        }
        $store = $this->id($scope['store_id'] ?? null, '来源门店标识', true);
        $tenant = $this->id($scope['tenant_id'] ?? null, '来源租户标识', true);
        $body = array_replace([
            // Original authorized stock query. Its enum is preserved, not translated.
            'machineType' => '1', 'productNames' => [], 'productFilters' => [], 'channelIdList' => [],
            'paymentStatus' => '', 'wjtShare' => '', 'storeMallFlag' => '', 'isAsc' => '', 'orderByColumn' => '',
            'colorList' => [], 'memoryList' => [], 'ownerId' => '', 'finenessList' => [], 'buyChannelList' => [], 'memoryLikeList' => [],
        ], $query, ['shopId' => $store, 'pageNum' => $pageNum, 'pageSize' => $pageSize]);
        if (($scope['omit_machine_type'] ?? false) === true) unset($body['machineType']);
        $page = $this->transport->post(self::STOCK_ENDPOINT, $this->headers($credentials) + ['Authorization' => 'Bearer ' . $this->accessToken($credentials)], $body);
        $this->assertBusinessSuccess($page);
        if (!isset($page['rows']) || !is_array($page['rows']) || !array_is_list($page['rows']) ||
            !is_bool($page['hasMore'] ?? null) || (string) ($page['pageNum'] ?? '') !== (string) $pageNum ||
            (string) ($page['pageSize'] ?? '') !== (string) $pageSize || count($page['rows']) > $pageSize ||
            ($page['hasMore'] && $page['rows'] === [])) {
            throw new ProviderException('schema_changed', '万机库存分页结构变化或结果不完整，已停止同步');
        }
        $rows = [];
        $seen = [];
        $observedTenant = null;
        foreach ($page['rows'] as $row) {
            if (!is_array($row)) throw new ProviderException('schema_changed', '万机库存记录格式变化');
            $actualTenant = $this->id($row['tenantId'] ?? null, '万机来源租户标识');
            $actualStore = $this->id($row['shopId'] ?? null, '万机来源门店标识');
            $id = $this->id($row['machineId'] ?? null, '万机设备标识');
            if (($tenant !== null && $actualTenant !== $tenant) || ($store !== null && $actualStore !== $store) ||
                ($observedTenant !== null && $observedTenant !== $actualTenant)) {
                throw new ProviderException('scope_mismatch', '万机返回数据与绑定租户或门店不一致，已停止同步');
            }
            $observedTenant = $actualTenant;
            $key = $actualTenant . ':' . $actualStore . ':' . $id;
            if (isset($seen[$key])) throw new ProviderException('schema_changed', '万机单页出现重复设备，已停止同步');
            $seen[$key] = true;
            $rows[] = $this->safeSnapshot($row);
        }
        return ['rows' => $rows, 'has_more' => $page['hasMore'], 'cursor' => ['page' => $pageNum + 1]];
    }

    public function normalize(array $row, array $context): array
    {
        $safe = $this->safeSnapshot($row);
        return $this->common($safe, $context, [
            'id' => $row['machineId'] ?? null,
            'store_id' => $row['shopId'] ?? null,
            'tenant_id' => $this->id($row['tenantId'] ?? null, '万机来源租户标识'),
            'title' => $row['productName'] ?? null, 'brand' => $row['brandName'] ?? null,
            'capacity' => $row['memory'] ?? null, 'color' => $row['color'] ?? null,
            'retail_price' => $row['retailAmount'] ?? null, 'trade_price' => $row['wholesaleAmount'] ?? null,
            'imei' => $row['imeiNoFirst'] ?? null,
            'images' => array_merge([$safe['homeImageUrl']], array_column($safe['imageList'], 'url')),
            'source_status' => ['machine_status' => $this->scalar($row['machineStatus'] ?? null),
                'mall_flag' => $this->scalar($row['storeMallFlag'] ?? null), 'machine_type' => $this->scalar($row['machineType'] ?? null)],
        ]);
    }

    private function headers(#[\SensitiveParameter] array $credentials): array
    {
        $result = [];
        foreach (['Platsource' => ['platsource', 'MiniPrograms'], 'DeviceInfo' => ['device_info', null]] as $name => [$key, $default]) {
            $value = $credentials[$key] ?? $default;
            if ($value === null || $value === '') continue;
            if (!is_string($value) || strlen($value) > 4096 || preg_match('/[\r\n]/', $value)) {
                throw new ProviderException('invalid_credentials', '万机客户端请求头格式不正确');
            }
            $result[$name] = $value;
        }
        return $result;
    }

    private function safeSnapshot(array $row): array
    {
        $safe = [];
        foreach (self::SNAPSHOT_KEYS as $key) {
            if (array_key_exists($key, $row)) $safe[$key] = $this->scalar($row[$key]);
        }
        $safe['homeImageUrl'] = $this->url($row['homeImageUrl'] ?? null);
        $safe['imageList'] = [];
        foreach (is_array($row['imageList'] ?? null) ? $row['imageList'] : [] as $image) {
            if (is_array($image) && ($image['type'] ?? null) === 'image' && ($url = $this->url($image['url'] ?? null)) !== null) {
                $safe['imageList'][] = ['url' => $url, 'type' => 'image'];
            }
        }
        $safe['publishPlatforms'] = array_values(array_filter(is_array($row['publishPlatforms'] ?? null) ? $row['publishPlatforms'] : [], 'is_string'));
        return $safe;
    }
}
