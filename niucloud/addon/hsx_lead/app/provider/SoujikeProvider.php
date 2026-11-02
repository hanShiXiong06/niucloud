<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

final class SoujikeProvider extends AbstractProvider
{
    public const LOGIN_ENDPOINT = 'https://www.dianddnet.cn/sjk-erp-service/api/member/login';
    public const STOCK_ENDPOINT = 'https://www.dianddnet.cn/sjk-erp-service/api/sku/homePage';
    private ?string $authenticatedStore = null;
    private const SNAPSHOT_KEYS = ['id', 'productName', 'categoryName', 'capacity', 'color', 'brandType',
        'watchNetworkMode', 'watchCaseSize', 'watchCaseMaterial', 'watchBand', 'fineness', 'retailPrice',
        'tradePrice', 'createTime', 'brandName', 'phoneVersion', 'battery', 'isNew', 'imei', 'renovateName',
        'renovateDesc', 'firstResIsVideo'];

    public function key(): string { return 'soujike'; }

    public function login(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->authenticatedStore = null;
        $mode = $credentials['auth_mode'] ?? null;
        if ($mode !== null && !in_array($mode, ['password', 'token'], true)) {
            throw new ProviderException('invalid_credentials', '请选择有效的搜机客授权方式');
        }
        $hasPasswordInput = array_key_exists('username',$credentials) || array_key_exists('password',$credentials);
        if ($mode === 'token' || ($mode === null && !$hasPasswordInput)) {
            // Explicit temporary administrator fallback; a token is not a profile proof.
            return ['access_token' => $this->accessToken($credentials), 'identity' => $this->identity($credentials) + ['verified' => false]];
        }
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;
        if (!is_string($username) || preg_match('/^1[0-9]{10}$/D', trim($username)) !== 1 ||
            !is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new ProviderException('invalid_credentials', '请填写搜机客登录手机号和密码');
        }
        // The existing member-card integration supplies this exact normal-login
        // contract. No member/card/customer API or raw login response is retained.
        $response = $this->transport->post(self::LOGIN_ENDPOINT, [], [
            'openId' => '', 'phoneNumber' => trim($username), 'passwords' => $password,
        ]);
        $this->assertBusinessSuccess($response);
        return $this->loginResult($response);
    }

    private function loginResult(#[\SensitiveParameter] array $response): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $data = $response['data'] ?? null;
        if (!is_array($data) || \addon\hsx_lead\app\support\PhpRuntime::isList($data) ||
            !is_array($data['tokenInfo'] ?? null) || \addon\hsx_lead\app\support\PhpRuntime::isList($data['tokenInfo'])) {
            throw new ProviderException('schema_changed', '搜机客登录响应结构变化，未保存连接');
        }
        $tokenName = $data['tokenInfo']['tokenName'] ?? 'recovery-token';
        if (!is_string($tokenName) || ($tokenName !== '' && strcasecmp($tokenName, 'recovery-token') !== 0)) {
            throw new ProviderException('schema_changed', '搜机客返回的授权协议与已接入方式不一致，已停止验证');
        }
        try {
            $token = $this->accessToken(['access_token' => $data['tokenInfo']['tokenValue'] ?? null]);
            $store = $this->id($data['storeId'] ?? null, '搜机客认证门店标识');
        } catch (ProviderException $error) {
            throw new ProviderException('schema_changed', '搜机客登录未返回有效授权或认证门店，未保存连接');
        }
        $storeInfo = $data['storeInfo'] ?? [];
        if (!is_array($storeInfo) || ($storeInfo !== [] && \addon\hsx_lead\app\support\PhpRuntime::isList($storeInfo))) {
            throw new ProviderException('schema_changed', '搜机客认证门店资料格式变化，未保存连接');
        }
        $storeName = $storeInfo['storeName'] ?? '';
        if (!is_string($storeName) || mb_strlen($storeName) > 120) {
            throw new ProviderException('schema_changed', '搜机客认证门店名称格式变化，未保存连接');
        }
        $this->authenticatedStore = $store;
        return [
            'access_token' => $token,
            'expires_at' => null,
            // The normal login proves its returned store, not an undocumented
            // tenant identifier, account list, sale status or financial scope.
            'identity' => ['tenant_id' => null, 'store_id' => $store, 'store_name' => trim($storeName), 'verified' => true],
            'warnings' => ['登录接口未提供可核验有效期；同步遇到授权失效时需重新验证'],
        ];
    }

    public function fetchPage(#[\SensitiveParameter] array $credentials, array $cursor, array $scope): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        [$pageNum, $pageSize] = $this->pagination($cursor, $scope);
        $store = $this->id($scope['store_id'] ?? null, '搜机客来源门店标识');
        if ($this->authenticatedStore !== null && $store !== $this->authenticatedStore) {
            throw new ProviderException('scope_mismatch', '搜机客库存范围与当前登录认证门店不一致，已停止同步');
        }
        $body = ['current' => $pageNum, 'size' => $pageSize, 'showHome' => false, 'fromShare' => false, 'storeId' => $store,
            'brandType' => '', 'brandIds' => [], 'categoryIds' => [], 'color' => '', 'capacity' => '', 'searchKey' => '',
            'sortFieldName' => '', 'sortOrderType' => '', 'minRetailPrice' => '', 'maxRetailPrice' => '', 'isNew' => null];
        $page = $this->transport->post(self::STOCK_ENDPOINT, ['recovery-token' => $this->accessToken($credentials)], $body);
        $this->assertBusinessSuccess($page);
        $data = $page['data'] ?? null;
        if (!is_array($data) || !is_array($data['records'] ?? null) || !\addon\hsx_lead\app\support\PhpRuntime::isList($data['records']) ||
            (string) ($data['current'] ?? '') !== (string) $pageNum || (string) ($data['size'] ?? '') !== (string) $pageSize ||
            (!is_string($data['total'] ?? null) && !is_int($data['total'] ?? null)) ||
            !preg_match('/^(?:0|[1-9][0-9]{0,7})$/D', (string) $data['total'])) {
            throw new ProviderException('schema_changed', '搜机客库存分页结构变化，已停止同步');
        }
        $total = (int) $data['total'];
        $expected = min($pageSize, max(0, $total - ($pageNum - 1) * $pageSize));
        if (count($data['records']) !== $expected) {
            throw new ProviderException('schema_changed', '搜机客分页结果不完整，已停止同步');
        }
        $rows = [];
        $seen = [];
        foreach ($data['records'] as $row) {
            if (!is_array($row)) throw new ProviderException('schema_changed', '搜机客库存记录格式变化');
            $id = $this->id($row['id'] ?? null, '搜机客商品标识');
            if (isset($seen[$id])) throw new ProviderException('schema_changed', '搜机客单页出现重复商品，已停止同步');
            $seen[$id] = true;
            $rows[] = $this->safeSnapshot($row);
        }
        return ['rows' => $rows, 'has_more' => $pageNum * $pageSize < $total, 'cursor' => ['page' => $pageNum + 1]];
    }

    public function normalize(array $row, array $context): array
    {
        $safe = $this->safeSnapshot($row);
        return $this->common($safe, $context, [
            'id' => $row['id'] ?? null, 'store_id' => $context['store_id'] ?? null,
            'tenant_id' => $context['tenant_id'] ?? null,
            'title' => $this->text($row['categoryName'] ?? null) ?? $row['productName'] ?? null, 'brand' => $row['brandName'] ?? null,
            'capacity' => $row['capacity'] ?? null, 'color' => $row['color'] ?? null,
            'retail_price' => $row['retailPrice'] ?? null, 'trade_price' => $row['tradePrice'] ?? null,
            'imei' => $row['imei'] ?? null,
            'images' => ($row['firstResIsVideo'] ?? null) === false ? [$safe['coverImage']] : [],
            'source_status' => [],
        ]);
    }

    private function safeSnapshot(array $row): array
    {
        $safe = [];
        foreach (self::SNAPSHOT_KEYS as $key) {
            if (array_key_exists($key, $row)) $safe[$key] = $this->scalar($row[$key]);
        }
        // A video URL is not a usable photo. No detail endpoint or media download is performed.
        $safe['coverImage'] = ($row['firstResIsVideo'] ?? null) === false ? $this->url($row['coverImage'] ?? null) : null;
        return $safe;
    }
}
