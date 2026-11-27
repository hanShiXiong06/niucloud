<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

/** Read-only profile and stock adapter. Company is the source scope; warehouses remain attributes. */
final class AiguanjiProvider extends AbstractProvider implements InteractiveAuthProviderContract
{
    public const STOCK_ENDPOINT = 'https://api.aiguanji.com/api/v1/storage/product/index';
    public const PROFILE_ENDPOINT = 'https://api.aiguanji.com/api/v1/index/index';
    public const CAPTCHA_ENDPOINT = 'https://api.aiguanji.com/api/v1/common/captcha/index';
    public const LOGIN_ENDPOINT = 'https://api.aiguanji.com/api/v1/index/login';
    public const CLIENT_VERSION = '2026102201';
    public const ON_SHELF_STATUS = '55';
    private const MAX_VERIFIED_PAGE_SIZE = 20;
    private const MAX_CAPTCHA_BYTES = 131072;
    private const MAX_LOGIN_BYTES = 131072;
    private const AUTH_STATE_VERSION = 1;
    private const ATTRIBUTES = ['内存', '颜色', '国别', '保修期', '充电次数', '电池健康度'];

    public function key(): string
    {
        return 'aiguanji';
    }

    public function beginAuthorization(): array
    {
        if (!$this->transport instanceof RequestTransportContract) {
            throw new ProviderException('unsupported_transport', '当前读取服务不支持正常验证码授权，请更新插件后端');
        }
        $response = $this->transport->request('GET', self::CAPTCHA_ENDPOINT, [],
            ['v' => '0.'.str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT)], 'query');
        if (($response['status'] ?? null) !== 200 || !is_array($response['headers'] ?? null) ||
            !is_string($response['body'] ?? null)) {
            throw new ProviderException('auth_challenge', '验证码暂未加载成功，请稍后重新获取');
        }
        $types = [];
        foreach ($response['headers'] as $name => $values) {
            if (!is_string($name) || !is_array($values)) throw new ProviderException('schema_changed', '验证码响应格式发生变化，请联系管理员核对');
            if (strcasecmp($name, 'Set-Cookie') === 0 && $values !== []) {
                // Actual endpoint is cookie-free. Do not silently invent a new session protocol.
                throw new ProviderException('auth_challenge', '验证码服务增加了会话校验，请联系管理员核对接入');
            }
            if (strcasecmp($name, 'Content-Type') === 0) {
                foreach ($values as $value) {
                    if (!is_string($value)) throw new ProviderException('schema_changed', '验证码响应格式发生变化，请联系管理员核对');
                    $types[] = strtolower(trim(explode(';', $value, 2)[0]));
                }
            }
        }
        $types = array_values(array_unique($types));
        $body = $response['body'];
        $magic = ['image/jpeg' => str_starts_with($body, "\xff\xd8\xff"),
            'image/png' => str_starts_with($body, "\x89PNG\r\n\x1a\n"),
            'image/gif' => str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')];
        if (count($types) !== 1 || !($magic[$types[0]] ?? false) || strlen($body) > self::MAX_CAPTCHA_BYTES) {
            throw new ProviderException('schema_changed', '验证码不是受支持的安全图片，请重新获取或联系管理员');
        }
        return ['public' => ['captcha_image' => 'data:'.$types[0].';base64,'.base64_encode($body), 'expires_in' => 180],
            'state' => ['version' => self::AUTH_STATE_VERSION, 'stage' => 'captcha', 'device_id' => bin2hex(random_bytes(16))]];
    }

    public function authorize(#[\SensitiveParameter] array $credentials, #[\SensitiveParameter] array $state,
        #[\SensitiveParameter] array $answer): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $username = $this->username($credentials);
        if (($state['version'] ?? null) !== self::AUTH_STATE_VERSION ||
            !is_string($state['device_id'] ?? null) || preg_match('/^[a-f0-9]{32}$/D', $state['device_id']) !== 1 ||
            !in_array($state['stage'] ?? null, ['captcha', 'company'], true)) {
            throw new ProviderException('invalid_challenge', '本次授权上下文已失效，请重新获取验证码');
        }
        $companyId = null;
        if ($state['stage'] === 'captcha') {
            if (isset($answer['company_id']) && $answer['company_id'] !== '' && $answer['company_id'] !== null) {
                throw new ProviderException('invalid_challenge', '请先完成正常登录，再从返回的公司中选择');
            }
            $password = $this->password($answer['password'] ?? null);
            $code = $this->captchaCode($answer['code'] ?? null);
            $body = ['mobile' => $username, 'password' => $password, 'code' => $code, 'device_id' => $state['device_id']];
        } else {
            if (($state['username'] ?? null) !== $username) throw new ProviderException('scope_mismatch', '授权账号已变化，请重新登录');
            $companies = $this->companies($state['companies'] ?? null);
            $companyId = $this->companyId($answer['company_id'] ?? null);
            if (!in_array($companyId, array_column($companies, 'id'), true)) {
                throw new ProviderException('invalid_challenge', '所选公司不属于本次授权候选，请重新选择或重新登录');
            }
            $password = $this->password($state['password'] ?? null);
            $code = $this->captchaCode($state['code'] ?? null);
            $body = ['mobile' => $username, 'password' => $password, 'code' => $code, 'device_id' => $state['device_id'],
                'company_id' => $companyId, 'token' => $this->accessToken(['access_token' => $state['temporary_token'] ?? null])];
        }
        [$response, $httpStatus] = $this->loginResponse($body);
        $data = $response['data'] ?? null;
        $businessCode = $response['code'] ?? null;
        $success = $businessCode === 0 || $businessCode === '0';
        if ($httpStatus !== 200 && $success) {
            // Non-200 responses may explain a failure, but can never authorize a connection.
            throw new ProviderException('http_error', '爱管机登录请求未成功，请稍后重新获取验证码');
        }
        if (!$success) {
            $explicitFailure = (is_int($businessCode) && $businessCode > 0) ||
                (is_string($businessCode) && preg_match('/^[1-9][0-9]*$/D', $businessCode) === 1);
            if ($explicitFailure && $state['stage'] === 'captcha' && is_array($data) && array_key_exists('teams', $data)) {
                $companies = $this->companies($data['teams']);
                $temporary = $this->accessToken(['access_token' => $data['token'] ?? null]);
                return ['status' => 'choose_company', 'public' => ['companies' => $companies],
                    'state' => ['version' => self::AUTH_STATE_VERSION, 'stage' => 'company',
                        'device_id' => $state['device_id'], 'username' => $username, 'password' => $password,
                        'code' => $code, 'temporary_token' => $temporary, 'companies' => $companies]];
            }
            $message = is_string($response['msg'] ?? null) ? $response['msg'] : '';
            if (($httpStatus === 200 || $explicitFailure) &&
                (str_contains($message, '验证码') || str_contains(strtolower($message), 'captcha'))) {
                throw new ProviderException('invalid_captcha', '验证码不正确或已失效，请刷新验证码后重新验证');
            }
            if (($httpStatus === 200 || $explicitFailure) &&
                (str_contains($message, '密码') || str_contains($message, '账号') || str_contains($message, '帐号'))) {
                throw new ProviderException('invalid_credentials', '账号或密码未通过验证，请核对后重新获取验证码');
            }
            if ($httpStatus !== 200) {
                throw new ProviderException('http_error', '爱管机登录服务暂未完成验证，请稍后重新获取验证码');
            }
            $this->assertSuccess($response);
        }
        if (!is_array($data) || \addon\hsx_lead\app\support\PhpRuntime::isList($data) || !is_string($data['token'] ?? null)) {
            throw new ProviderException('schema_changed', '正常登录未返回有效授权，请联系管理员核对');
        }
        $saved = ['username' => $username, 'access_token' => $this->accessToken(['access_token' => $data['token']])];
        $verified = $this->login($saved);
        if ($companyId !== null && ($verified['identity']['tenant_id'] ?? null) !== $companyId) {
            throw new ProviderException('scope_mismatch', '登录后的当前公司与所选公司不一致，未保存连接');
        }
        return ['status' => 'authorized', 'credentials' => $saved, 'identity' => $verified['identity']];
    }

    /** Only this login endpoint may interpret bounded non-200 bodies as safe failures. */
    private function loginResponse(#[\SensitiveParameter] array $body): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $headers = ['version' => self::CLIENT_VERSION, 'X-Client-Route' => '/login'];
        if (!$this->transport instanceof RequestTransportContract) {
            // Existing JSON-only transport contract accepts HTTP 200 only.
            return [$this->transport->post(self::LOGIN_ENDPOINT, $headers, $body), 200];
        }
        $raw = $this->transport->request('POST', self::LOGIN_ENDPOINT, $headers, $body, 'json');
        $status = $raw['status'] ?? null;
        if ($status === 429) {
            throw new ProviderException('rate_limited', '爱管机登录请求过于频繁，请稍后再试；系统不会自动重试');
        }
        if (!in_array($status, [200, 400, 401, 403, 422, 500], true)) {
            throw new ProviderException('http_error', '爱管机登录请求未成功，请稍后重新获取验证码');
        }
        $bytes = $raw['body'] ?? null;
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_LOGIN_BYTES) {
            throw new ProviderException('http_error', '爱管机登录响应异常，请稍后重新获取验证码');
        }
        try {
            $response = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new ProviderException('http_error', '爱管机登录响应异常，请稍后重新获取验证码');
        }
        if (!is_array($response) || \addon\hsx_lead\app\support\PhpRuntime::isList($response)) {
            throw new ProviderException('http_error', '爱管机登录响应异常，请稍后重新获取验证码');
        }
        return [$response, $status];
    }

    public function login(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $username = $this->username($credentials);
        $token = $this->accessToken($credentials);
        // The verified token authorizes this profile read. Password CAPTCHA is not bypassed.
        // An empty transport body is encoded as a top-level JSON object by the HTTP transport.
        $response = $this->transport->post(self::PROFILE_ENDPOINT, $this->headers($credentials), []);
        $this->assertSuccess($response);
        $data = $response['data'] ?? null;
        $user = is_array($data) ? ($data['user'] ?? null) : null;
        $company = is_array($user) ? ($user['company'] ?? null) : null;
        if (!is_array($user) || \addon\hsx_lead\app\support\PhpRuntime::isList($user) || !is_string($user['mobile'] ?? null) ||
            !is_array($company) || \addon\hsx_lead\app\support\PhpRuntime::isList($company)) {
            throw new ProviderException('schema_changed', '爱管机未返回可核验的授权账号和当前公司，请重新授权');
        }
        if ($user['mobile'] !== $username) {
            throw new ProviderException('scope_mismatch', '爱管机授权与填写的手机号不一致，请核对账号后重新授权');
        }
        $companyRawId = $company['id'] ?? null;
        if (!is_string($companyRawId) && !is_int($companyRawId)) {
            throw new ProviderException('schema_changed', '爱管机当前公司身份不完整，未保存连接');
        }
        $companyId = (string) $companyRawId;
        $companyName = $this->text($company['name'] ?? null);
        if (preg_match('/^[1-9][0-9]{0,18}$/D', $companyId) !== 1 || $companyName === null ||
            preg_match('/^[^\x00-\x1f\x7f]{1,120}$/uD', $companyName) !== 1) {
            throw new ProviderException('schema_changed', '爱管机当前公司身份不完整，未保存连接');
        }
        // Never return/cache the full profile, menus, contacts, account balances or user object.
        return ['access_token' => $token, 'expires_at' => null,
            'identity' => ['tenant_id' => $companyId, 'store_id' => $companyId, 'store_name' => $companyName, 'verified' => true],
            'warnings' => ['当前授权有效期未由接口返回；失效后需重新授权，不会自动绕过图片验证码登录']];
    }

    public function fetchPage(#[\SensitiveParameter] array $credentials, array $cursor, array $scope): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        [$pageNum, $requestedSize] = $this->pagination($cursor, $scope);
        $pageSize = min($requestedSize, self::MAX_VERIFIED_PAGE_SIZE);
        if (isset($scope['query']) && $scope['query'] !== []) {
            throw new ProviderException('invalid_scope', '爱管机在售库存不接受未核验的额外筛选条件');
        }
        $expectedCompany = $this->expectedCompany($scope, $cursor);
        $body = [
            'page' => $pageNum, 'limit' => $pageSize, 'value1' => '', 'cate_id' => '', 'brand_id' => '', 'product_id' => '',
            'purchase_time' => [], 'selected_attr' => new \stdClass(), 'from' => 'stock',
            'with_refund_remark' => true, 'storage_hide' => true, 'level_id' => [], 'purity_id' => [],
            'status' => [self::ON_SHELF_STATUS], 'user_id_arr' => [], 'owner_user_id_arr' => [], 'newchan_id' => '',
            'channel_id' => '', 'sale_channel_id' => '', 'warehouse_id' => [],
        ];
        $response = $this->transport->post(self::STOCK_ENDPOINT, $this->headers($credentials), $body);
        $this->assertSuccess($response);
        $data = $response['data'] ?? null;
        if (!is_array($data) || !is_array($data['data'] ?? null) || !\addon\hsx_lead\app\support\PhpRuntime::isList($data['data']) ||
            (string) ($data['current_page'] ?? '') !== (string) $pageNum ||
            (string) ($data['per_page'] ?? '') !== (string) $pageSize ||
            !$this->unsigned($data['total'] ?? null) || !$this->unsigned($data['last_page'] ?? null) ||
            !is_bool($data['has_more'] ?? null)) {
            throw new ProviderException('schema_changed', '爱管机库存分页结构变化，未发布本批库存');
        }
        $total = (int) $data['total'];
        $lastPage = $total === 0 ? 0 : (int) ceil($total / $pageSize);
        $hasMore = $pageNum * $pageSize < $total;
        $expectedCount = min($pageSize, max(0, $total - ($pageNum - 1) * $pageSize));
        if ((int) $data['last_page'] !== $lastPage || $data['has_more'] !== $hasMore ||
            count($data['data']) !== $expectedCount ||
            (isset($cursor['total']) && (!$this->unsigned($cursor['total']) || (int) $cursor['total'] !== $total))) {
            throw new ProviderException('schema_changed', '爱管机分页总数或在售清单发生变化，请重新同步；旧库存保持不变');
        }
        $rows = [];
        $seen = [];
        $company = $expectedCompany;
        foreach ($data['data'] as $row) {
            if (!is_array($row)) throw new ProviderException('schema_changed', '爱管机在售记录格式变化');
            $actualCompany = $this->id($row['company_id'] ?? null, '爱管机来源公司标识');
            $id = $this->id($row['id'] ?? null, '爱管机库存标识');
            if ($company !== null && $company !== $actualCompany) {
                throw new ProviderException('scope_mismatch', '爱管机库存公司与已验证来源不一致，已停止同步');
            }
            $company = $actualCompany;
            if ((string) ($row['status'] ?? '') !== self::ON_SHELF_STATUS) {
                throw new ProviderException('scope_mismatch', '爱管机在售接口混入未上架记录，已停止同步');
            }
            if (isset($seen[$id])) throw new ProviderException('schema_changed', '爱管机单页存在重复库存记录，已停止同步');
            $seen[$id] = true;
            $rows[] = $this->safeSnapshot($row);
        }
        return ['rows' => $rows, 'has_more' => $hasMore, 'cursor' => ['page' => $pageNum + 1, 'total' => $total, 'company_id' => $company]];
    }

    public function normalize(array $row, array $context): array
    {
        $inspection = $this->inspectSnapshot($row, $context);
        if (!$inspection['eligibility']['listed']) {
            throw new ProviderException('scope_mismatch', '爱管机未上架记录不能作为在售库存');
        }
        return $inspection['normalized'];
    }

    /** Parse safe device fields independently of business eligibility. Never writes inventory. */
    public function inspectSnapshot(array $row, array $context): array
    {
        $safe = $this->safeSnapshot($row);
        if (!$this->unsigned($safe['status'] ?? null)) throw new ProviderException('schema_changed', '爱管机设备状态缺失或格式变化');
        $listed = (string) $safe['status'] === self::ON_SHELF_STATUS;
        $company = $this->id($safe['company_id'] ?? null, '爱管机来源公司标识');
        $normalized = $this->common($safe, $context, [
            'id' => $safe['id'] ?? null, 'store_id' => $company, 'tenant_id' => $company,
            'title' => $safe['name'] ?? null, 'brand' => $safe['product_brand']['name'] ?? null,
            'capacity' => $this->text($safe['product_attr_memory'] ?? null) ?? $safe['selected_attr']['内存'] ?? null,
            'color' => $this->text($safe['product_attr_color'] ?? null) ?? $safe['selected_attr']['颜色'] ?? null,
            // Verified current inventory column labels; never substitute transaction revenue or costs.
            'retail_price' => $safe['pre_sale_amount'], 'trade_price' => $safe['industry_amount'],
            'imei' => $safe['imei'] ?? null, 'images' => [],
            // This verified storage endpoint returns one current stock-device
            // record per product id, and status 55 is its listed inventory state.
            // Preserve the absence of an aggregate quantity; the per-device
            // availability proof is explicit and separate from raw quantity.
            'listing_status' => $listed ? 'listed' : 'unknown',
            'stock_status' => $listed ? 'in_stock' : 'unknown',
            'availability' => $listed ? 'available' : 'unknown',
            'quantity' => null, 'saleable_quantity' => $listed ? '1.000' : null,
            'quantity_basis' => $listed ? 'listed_stock_device' : 'unknown',
            'source_status' => ['status' => $safe['status'], 'status_text' => $safe['status_text'] ?? null,
                'source_scope_kind' => 'company', 'warehouse_id' => $safe['warehouse_id'] ?? null, 'listed' => $listed],
        ]);
        return ['normalized' => $normalized, 'eligibility' => ['listed' => $listed,
            'reason' => $listed ? '来源为已上架；实际成交仍需门店确认' : '未上架，仅用于资料解析，不进入可派单库存']];
    }

    private function username(#[\SensitiveParameter] array $credentials): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $username = $credentials['username'] ?? null;
        if (!is_string($username) || preg_match('/^[0-9]{11}$/D', trim($username)) !== 1) {
            throw new ProviderException('invalid_credentials', '请填写爱管机授权账号的完整手机号');
        }
        return trim($username);
    }

    private function password(#[\SensitiveParameter] mixed $password): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new ProviderException('invalid_credentials', '请填写本次登录密码');
        }
        return $password;
    }

    private function captchaCode(#[\SensitiveParameter] mixed $code): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!is_string($code) || preg_match('/^[A-Za-z0-9]{4}$/D', trim($code)) !== 1) {
            throw new ProviderException('invalid_captcha', '请输入图片中的4位验证码');
        }
        return trim($code);
    }

    private function companyId(mixed $id): string
    {
        if ((!is_int($id) && !is_string($id)) || preg_match('/^[1-9][0-9]{0,18}$/D', (string) $id) !== 1) {
            throw new ProviderException('invalid_challenge', '请选择本次授权返回的公司');
        }
        return (string) $id;
    }

    private function companies(mixed $teams): array
    {
        if (!is_array($teams) || !\addon\hsx_lead\app\support\PhpRuntime::isList($teams) || $teams === [] || count($teams) > 50) {
            throw new ProviderException('schema_changed', '授权公司候选格式变化，请联系管理员核对');
        }
        $result = [];
        $seen = [];
        foreach ($teams as $team) {
            if (!is_array($team)) throw new ProviderException('schema_changed', '授权公司候选格式变化，请联系管理员核对');
            try {
                $id = $this->companyId($team['id'] ?? null);
            } catch (ProviderException $error) {
                throw new ProviderException('schema_changed', '授权公司候选格式变化，请联系管理员核对');
            }
            $name = $this->text($team['name'] ?? null);
            if ($name === null || preg_match('/^[^\x00-\x1f\x7f]{1,120}$/uD', $name) !== 1 || isset($seen[$id])) {
                throw new ProviderException('schema_changed', '授权公司候选格式变化，请联系管理员核对');
            }
            $seen[$id] = true;
            $result[] = ['id' => $id, 'name' => $name];
        }
        return $result;
    }

    private function assertSuccess(array $response): void
    {
        $code = $response['code'] ?? null;
        if ($code === 5 || $code === '5' || in_array($code, [401, '401', 403, '403'], true)) {
            throw new ProviderException('auth_required', '爱管机登录已失效，请重新授权');
        }
        if ($code === 2 || $code === '2') throw new ProviderException('client_version_changed', '爱管机要求更新客户端版本，请核对接口后再同步');
        if (in_array($code, [3, '3', 4, '4', 7, '7', 10, '10'], true)) {
            throw new ProviderException('auth_challenge', '爱管机要求人工确认或二次验证，本次未同步');
        }
        if ($code !== 0 && $code !== '0') throw new ProviderException('upstream_error', '爱管机接口未成功，未更新库存');
    }

    private function headers(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return ['Authorization' => $this->accessToken($credentials), 'version' => self::CLIENT_VERSION,
            'X-Client-Route' => '/storage/product'];
    }

    private function unsigned(mixed $value): bool
    {
        return (is_string($value) || is_int($value)) && preg_match('/^(?:0|[1-9][0-9]{0,7})$/D', (string) $value) === 1;
    }

    private function expectedCompany(array $scope, array $cursor): ?string
    {
        $company = null;
        foreach ([$scope['tenant_id'] ?? null, $scope['store_id'] ?? null, $cursor['company_id'] ?? null] as $candidate) {
            $id = $this->id($candidate, '爱管机来源公司标识', true);
            if ($id === null) continue;
            if ($company !== null && $company !== $id) throw new ProviderException('scope_mismatch', '爱管机来源公司范围不一致');
            $company = $id;
        }
        return $company;
    }

    private function safeSnapshot(array $row): array
    {
        $safe = [];
        foreach ([
                'id',
                'company_id',
                'warehouse_id',
                'product_id',
                'product_no',
                'imei',
                'imei2',
                'name',
                'status',
                'status_text',
                'product_attr_memory',
                'product_attr_color'
            ] as $key) {
            if (array_key_exists($key, $row)) $safe[$key] = $this->scalar($row[$key]);
        }
        $safe['pre_sale_amount'] = $this->money($row['pre_sale_amount'] ?? null);
        $safe['industry_amount'] = $this->money($row['industry_amount'] ?? null);
        foreach (['product_brand', 'product_cate', 'warehouse', 'purity'] as $key) {
            $object = $row[$key] ?? null;
            if (!is_array($object)) continue;
            $safe[$key] = [];
            foreach (['id', 'name'] as $field) if (array_key_exists($field, $object)) $safe[$key][$field] = $this->scalar($object[$field]);
        }
        $safe['purity_name'] = $this->text($safe['purity']['name'] ?? null);
        $safe['selected_attr'] = [];
        foreach (self::ATTRIBUTES as $key) {
            if (is_array($row['selected_attr'] ?? null) && array_key_exists($key, $row['selected_attr'])) {
                $safe['selected_attr'][$key] = $this->scalar($row['selected_attr'][$key]);
            }
        }
        return $safe;
    }
}
