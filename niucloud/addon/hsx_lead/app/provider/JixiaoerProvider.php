<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

use GuzzleHttp\Cookie\SetCookie;

/** 机小二只读库存适配。固定登录、认证门店页及库存读取，不包含业务写入。 */
final class JixiaoerProvider extends AbstractProvider
{
    public const LOGIN_ENDPOINT = 'https://jx2.guanql.cn/admin/login/in';
    public const IDENTITY_ENDPOINT = 'https://jx2.guanql.cn/admin';
    public const STOCK_ENDPOINT = 'https://jx2.guanql.cn/admin/serial-follow/get-list';
    public const CATALOG_ENDPOINT = 'https://jx2.guanql.cn/mall/goods/get-list';
    public const LISTED_ENDPOINT = 'https://jx2.guanql.cn/mall/imei-goods/get-list';
    public const DEFAULT_TIME_START = '1970-01-01';
    public const DEFAULT_TIME_END = '2099-12-31';
    private const COOKIE_NAME = 'gql_repair_token';
    private const CURSOR_VERSION = 2;
    private const MAX_PAGES = 100;
    private const MAX_INDEX = 10000;
    private const MAX_CURSOR_BYTES = 4194304;
    private const PROPERTY_LABELS = [
        'memory' => '内存', 'color' => '颜色', 'fineness' => '成色', 'buyChannel' => '购买渠道',
        'guarantee' => '保修', 'shop_guarantee' => '店铺保修', 'batteryHealth' => '电池健康度',
        'batteryCycleCount' => '电池循环次数', 'system_version' => '系统版本',
    ];

    public function key(): string
    {
        return 'jixiaoer';
    }

    public function login(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;
        if (!is_string($username) || trim($username) === '' || strlen($username) > 512
            || !is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new ProviderException('invalid_credentials', '请填写机小二账号和密码');
        }
        $response = $this->request('POST', self::LOGIN_ENDPOINT, [], [
            'phone' => trim($username), 'password' => $password, 'login_type' => 'admin',
        ], 'form');
        $page = GuzzleJsonTransport::decode($response['body']);
        $this->assertBusinessSuccess($page);
        $token = $this->responseCookie($response['headers']);
        $identityResponse = $this->request('GET', self::IDENTITY_ENDPOINT,
            ['Accept' => 'text/html', 'Cookie' => self::COOKIE_NAME.'='.$token], [], 'query');
        $identity = $this->htmlIdentity($identityResponse['body']);
        return ['access_token' => $token, 'expires_at' => null, 'identity' => $identity,
            'warnings' => ['ERP 未提供已核验的授权有效期；会话到期后需重新登录']];
    }

    public function fetchPage(#[\SensitiveParameter] array $credentials, array $cursor, array $scope): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        [$page, $size] = $this->pagination($cursor, $scope);
        if ($page > self::MAX_PAGES) throw new ProviderException('invalid_scope', '机小二同步超过100页安全上限，本轮未发布');
        $dates = $scope['query'] ?? [];
        if (!is_array($dates) || array_diff(array_keys($dates), ['time_start', 'time_end'])) {
            throw new ProviderException('invalid_scope', '机小二库存查询范围包含未核验参数');
        }
        $start = $this->dateBound($dates['time_start'] ?? self::DEFAULT_TIME_START) ?: self::DEFAULT_TIME_START;
        $end = $this->dateBound($dates['time_end'] ?? self::DEFAULT_TIME_END) ?: self::DEFAULT_TIME_END;
        if ($start !== '' && $end !== '' && $start > $end) {
            throw new ProviderException('invalid_scope', '机小二库存开始日期不能晚于结束日期');
        }
        $store = $this->id($scope['store_id'] ?? null, '机小二来源门店标识', true);
        $tenant = $this->id($scope['tenant_id'] ?? null, '机小二来源租户标识', true);
        if ($store !== null && $tenant !== null && $store !== $tenant) {
            throw new ProviderException('scope_mismatch', '机小二租户与门店应为同一认证门店');
        }
        $fingerprint = hash('sha256', json_encode([$store, $tenant, $size, $start, $end], JSON_THROW_ON_ERROR));
        $state = $this->state($cursor, $page, $size, $fingerprint, $store ?? $tenant);
        $phase = $state['phase'];
        $phasePage = $state['phase_page'];
        $query = $this->query($phase, $phasePage, $size, $start, $end);
        $endpoint = ['catalog' => self::CATALOG_ENDPOINT, 'listed' => self::LISTED_ENDPOINT, 'stock' => self::STOCK_ENDPOINT][$phase];
        $referer = ['catalog' => 'https://jx2.guanql.cn/mall/goods',
            'listed' => 'https://jx2.guanql.cn/mall/imei-goods',
            'stock' => 'https://jx2.guanql.cn/admin/serial-follow'][$phase];
        $response = $this->request('GET', $endpoint,
            $this->headers($this->accessToken($credentials), $referer), $query, 'query');
        [$input, $total, $more] = $this->readPage(GuzzleJsonTransport::decode($response['body']), $phasePage, $size, $state['expected_total']);
        $rows = [];
        foreach ($input as $row) {
            if (!is_array($row)) throw new ProviderException('schema_changed', '机小二分页记录格式变化，本轮未发布');
            if ($phase === 'catalog') $this->indexParent($row, $state);
            elseif ($phase === 'listed') $this->indexListing($row, $state);
            else {
                $safe = $this->joinStock($row, $state);
                if ($safe !== null) $rows[] = $safe;
            }
        }
        $state['page'] = $page + 1;
        $state['phase_page'] = $phasePage + 1;
        $state['expected_total'] = $total;
        $hasMore = $more;
        if (!$more) {
            if ($phase === 'catalog' && $state['parents'] !== []) {
                $state['phase'] = 'listed';
                $hasMore = true;
            } elseif ($phase === 'listed' && $state['listed'] !== []) {
                $state['phase'] = 'stock';
                $state['parents'] = [];
                $hasMore = true;
            } else {
                if ($phase === 'stock' && $state['listed'] !== []) {
                    throw new ProviderException('schema_changed', '机小二上架设备与当前库存未能完整对应，本轮未发布，请核对原系统后重新同步');
                }
                $state['phase'] = 'done';
                $state['parents'] = [];
                $state['listed'] = [];
            }
            $state['phase_page'] = 1;
            $state['expected_total'] = null;
        }
        if ($hasMore && $page >= self::MAX_PAGES) {
            throw new ProviderException('invalid_scope', '机小二同步超过100页安全上限，本轮未发布');
        }
        $this->checkStateSize($state);
        $note = ['catalog' => '核对上架型号', 'listed' => '核对上架设备', 'stock' => '匹配当前库存'][$phase]
            .'：第'.$phasePage.'页，来源共'.$total.'条';
        if ($phase !== 'stock') $note .= '；正在预读上架规则，此时未输出设备不代表零库存';
        if ($state['skipped_parent'] > 0) $note .= '；已排除未上架父商品的设备'.$state['skipped_parent'].'条';
        return ['rows' => $rows, 'has_more' => $hasMore, 'cursor' => $state, 'note' => $note];
    }

    public function normalize(array $row, array $context): array
    {
        $safe = $this->safeSnapshot($row);
        $safe['listing'] = $this->listingProof($row['listing'] ?? null, $safe);
        $specifications = array_column($safe['ext_json'], 'value', 'key');
        // The observed ERP contract joins an active parent + active listed IMEI
        // to this one current stock-device row. stock_num is its stock-history
        // balance, where the verified in-stock rule includes zero; it is NOT an
        // aggregate available quantity and must not be copied into quantity.
        $stockNumber = $safe['stock_list_history_imei']['stock_num'] ?? null;
        $validStock = (is_int($stockNumber) || is_string($stockNumber))
            && preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', (string) $stockNumber) === 1;
        $inStock = $validStock && !(str_starts_with((string) $stockNumber, '-') && preg_match('/[1-9]/', (string) $stockNumber));
        $stockStatus = $inStock ? 'in_stock' : ($validStock ? 'out_of_stock' : 'unknown');
        return $this->common($safe, $context, [
            'id' => $safe['id'], 'store_id' => $safe['shop_id'], 'tenant_id' => $safe['shop_id'],
            'title' => $safe['goods_name'] ?? null, 'brand' => $safe['brand_name'] ?? null,
            'capacity' => $specifications['memory'] ?? null, 'color' => $specifications['color'] ?? null,
            'retail_price' => $safe['listing']['sell_price'], 'trade_price' => $safe['listing']['wholesale_price'],
            'imei' => $safe['imei'] ?? null, 'images' => [],
            'listing_status' => 'listed', 'stock_status' => $stockStatus,
            'availability' => $inStock ? 'available' : ($validStock ? 'unavailable' : 'unknown'),
            'quantity' => null, 'saleable_quantity' => $inStock ? '1.000' : null,
            'quantity_basis' => $inStock ? 'listed_stock_device' : 'unknown',
            'source_status' => ['stock_type' => $safe['stock_type'] ?? null,
                'is_sale_before' => $safe['is_sale_before'] ?? null,
                'stock_num' => $safe['stock_list_history_imei']['stock_num'] ?? null,
                'parent_status' => 1, 'listing_status' => 1],
        ]);
    }

    private function readPage(array $response, int $page, int $size, ?int $expectedTotal): array
    {
        $this->assertBusinessSuccess($response);
        $data = $response['data'] ?? null;
        if (!is_array($data) || !is_array($data['list'] ?? null) || !\addon\hsx_lead\app\support\PhpRuntime::isList($data['list'])
            || (!is_int($data['total'] ?? null) && !is_string($data['total'] ?? null))
            || !preg_match('/^(?:0|[1-9][0-9]{0,7})$/D', (string) $data['total'])) {
            throw new ProviderException('schema_changed', '机小二库存分页结构变化，未发布本次库存');
        }
        $total = (int) $data['total'];
        if (array_key_exists('offset', $data) && ((!is_int($data['offset']) && !is_string($data['offset']))
            || !preg_match('/^(?:0|[1-9][0-9]*)$/D', (string) $data['offset'])
            || (string) $data['offset'] !== (string) (($page - 1) * $size))) {
            throw new ProviderException('schema_changed', '机小二来源分页偏移不一致，本轮未发布');
        }
        if ($expectedTotal !== null && $expectedTotal !== $total) {
            throw new ProviderException('schema_changed', '机小二库存总数在分页期间变化，本轮未发布，请稍后重新同步');
        }
        $expected = min($size, max(0, $total - ($page - 1) * $size));
        if (count($data['list']) !== $expected) {
            throw new ProviderException('schema_changed', '机小二库存分页不完整，未发布本次库存');
        }
        if ($total > self::MAX_INDEX) throw new ProviderException('invalid_scope', '机小二来源记录超过安全数量，本轮未发布');
        return [$data['list'], $total, $page * $size < $total];
    }

    private function state(array $cursor, int $page, int $size, string $fingerprint, ?string $shop): array
    {
        if ($page === 1 && array_diff(array_keys($cursor), ['page']) === []) {
            return ['version' => self::CURSOR_VERSION, 'page' => 1, 'phase' => 'catalog', 'phase_page' => 1,
                'size' => $size, 'scope' => $fingerprint, 'shop_id' => $shop, 'expected_total' => null,
                'parents' => [], 'listed' => [], 'seen_listed' => [], 'seen_stock' => [], 'seen_join' => [], 'skipped_parent' => 0];
        }
        $this->checkStateSize($cursor);
        if (($cursor['version'] ?? null) !== self::CURSOR_VERSION || ($cursor['size'] ?? null) !== $size
            || ($cursor['scope'] ?? null) !== $fingerprint || !in_array($cursor['phase'] ?? null, ['catalog', 'listed', 'stock'], true)
            || !is_int($cursor['phase_page'] ?? null) || $cursor['phase_page'] < 1 || $cursor['phase_page'] > $page
            || !array_key_exists('expected_total', $cursor) || ($cursor['expected_total'] !== null && (!is_int($cursor['expected_total']) || $cursor['expected_total'] < 0 || $cursor['expected_total'] > self::MAX_INDEX))
            || !is_int($cursor['skipped_parent'] ?? null) || $cursor['skipped_parent'] < 0) {
            throw new ProviderException('invalid_scope', '机小二同步进度或查询范围已变化，请重新开始同步');
        }
        foreach (['parents', 'listed', 'seen_listed', 'seen_stock', 'seen_join'] as $key) {
            if (!is_array($cursor[$key] ?? null) || count($cursor[$key]) > self::MAX_INDEX) {
                throw new ProviderException('invalid_scope', '机小二同步进度索引异常，请重新开始同步');
            }
        }
        $currentShop = $this->id($cursor['shop_id'] ?? null, '机小二同步门店', true);
        if ($shop !== null && $currentShop !== $shop) throw new ProviderException('scope_mismatch', '机小二同步门店已变化，请重新开始同步');
        return $cursor;
    }

    private function checkStateSize(array $state): void
    {
        try {
            $bytes = strlen(json_encode($state, JSON_THROW_ON_ERROR));
        } catch (\Throwable $error) {
            throw new ProviderException('invalid_scope', '机小二同步进度格式异常');
        }
        if ($bytes > self::MAX_CURSOR_BYTES) throw new ProviderException('invalid_scope', '机小二同步进度超过安全大小，本轮未发布');
    }

    private function shop(string $shop, array &$state): void
    {
        if ($state['shop_id'] !== null && $state['shop_id'] !== $shop) {
            throw new ProviderException('scope_mismatch', '机小二返回数据与已绑定门店不一致，已停止同步');
        }
        $state['shop_id'] = $shop;
    }

    private function indexParent(array $row, array &$state): void
    {
        $shop = $this->id($row['shop_id'] ?? null, '机小二商品门店');
        $this->shop($shop, $state);
        $id = $this->id($row['id'] ?? null, '机小二父商品标识');
        $goods = $this->id($row['goods_id'] ?? null, '机小二型号标识');
        if ((string) ($row['status'] ?? '') !== '1') throw new ProviderException('schema_changed', '机小二上架型号返回了非上架记录，本轮未发布');
        $key = 'p:'.$id;
        if (isset($state['parents'][$key])) throw new ProviderException('schema_changed', '机小二上架型号分页重复，本轮未发布');
        $state['parents'][$key] = ['id' => $id, 'goods_id' => $goods, 'shop_id' => $shop, 'status' => 1];
    }

    private function indexListing(array $row, array &$state): void
    {
        $parentId = $this->id($row['id'] ?? null, '机小二父商品标识');
        $goods = $this->id($row['goods_id'] ?? null, '机小二型号标识');
        $imei = $this->id($row['imei'] ?? null, '机小二完整串号');
        if ((string) ($row['status'] ?? '') !== '1') throw new ProviderException('schema_changed', '机小二上架设备返回了非上架记录，本轮未发布');
        $seenKey = hash('sha256', json_encode([$parentId, $goods, $imei], JSON_THROW_ON_ERROR));
        if (isset($state['seen_listed'][$seenKey])) throw new ProviderException('schema_changed', '机小二上架设备分页重复，本轮未发布');
        $state['seen_listed'][$seenKey] = true;
        $parent = $state['parents']['p:'.$parentId] ?? null;
        if ($parent === null) {
            $state['skipped_parent']++;
            return;
        }
        if (($parent['goods_id'] ?? null) !== $goods || ($parent['shop_id'] ?? null) !== $state['shop_id']) {
            throw new ProviderException('scope_mismatch', '机小二上架设备与父商品归属不一致，本轮未发布');
        }
        $key = $this->joinKey($goods, $imei);
        if (isset($state['listed'][$key])) throw new ProviderException('schema_changed', '机小二上架设备串号对应多条记录，本轮未发布');
        $state['listed'][$key] = ['parent_id' => $parentId, 'goods_id' => $goods, 'shop_id' => $state['shop_id'],
            'parent_status' => 1, 'status' => 1, 'sell_price' => $this->money($row['sell_price'] ?? null),
            'wholesale_price' => $this->money($row['wholesale_price'] ?? null),
            'mall_goods_imei_id' => $this->id($row['mall_goods_imei_id'] ?? null, '商城设备追踪标识', true)];
    }

    private function joinStock(array $row, array &$state): ?array
    {
        $safe = $this->safeSnapshot($row);
        $this->shop($safe['shop_id'], $state);
        $goods = $this->id($safe['goods_id'] ?? null, '机小二库存型号标识');
        $imei = $this->id($safe['imei'] ?? null, '机小二库存完整串号');
        $idKey = 's:'.$safe['id'];
        $key = $this->joinKey($goods, $imei);
        if (isset($state['seen_stock'][$idKey]) || isset($state['seen_join'][$key])) {
            throw new ProviderException('schema_changed', '机小二库存分页出现重复或歧义设备，本轮未发布');
        }
        $state['seen_stock'][$idKey] = true;
        $state['seen_join'][$key] = true;
        if (!isset($state['listed'][$key])) return null;
        $num = $safe['stock_list_history_imei']['stock_num'] ?? null;
        if ((!is_string($num) && !is_int($num)) || !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', (string) $num)
            || (str_starts_with((string) $num, '-') && preg_match('/[1-9]/', (string) $num))) {
            throw new ProviderException('schema_changed', '机小二已上架设备的当前在库状态未能确认，本轮未发布');
        }
        $safe['listing'] = $state['listed'][$key];
        unset($state['listed'][$key]);
        return $safe;
    }

    private function joinKey(string $goods, string $imei): string
    {
        return hash('sha256', json_encode([$goods, $imei], JSON_THROW_ON_ERROR));
    }

    private function listingProof(mixed $listing, array $safe): array
    {
        if (!is_array($listing) || ($listing['parent_status'] ?? null) !== 1 || ($listing['status'] ?? null) !== 1
            || ($listing['goods_id'] ?? null) !== (string) ($safe['goods_id'] ?? '') || ($listing['shop_id'] ?? null) !== $safe['shop_id']) {
            throw new ProviderException('schema_changed', '机小二设备缺少已核对的商城上架关联，本轮未发布');
        }
        return ['parent_id' => $this->id($listing['parent_id'] ?? null, '机小二父商品标识'),
            'goods_id' => $listing['goods_id'], 'shop_id' => $safe['shop_id'], 'parent_status' => 1, 'status' => 1,
            'sell_price' => $this->money($listing['sell_price'] ?? null),
            'wholesale_price' => $this->money($listing['wholesale_price'] ?? null),
            'mall_goods_imei_id' => $this->id($listing['mall_goods_imei_id'] ?? null, '商城设备追踪标识', true)];
    }

    private function query(string $phase, int $page, int $size, string $start, string $end): array
    {
        $pagination = ['limit' => $size, 'offset' => ($page - 1) * $size];
        if ($phase === 'stock') return $pagination + [
            'order' => 'asc', 'time_start' => $start, 'time_end' => $end, 'category' => 0, 'brand_id' => 0,
            'imei_remark' => '', 'goods_id' => 0, 'goods_attributes_info_id' => '', 'memory' => '',
            'color' => '', 'fineness' => 0, 'fineness2' => '', 'imei' => '', 'platform' => '',
            'store' => 0, 'statusIn' => 0, 'tag' => 0, 'supplier_id' => 0, 'customer_id' => 0, 'admin_user_id' => 0];
        $query = $pagination + ['goods_number' => '', 'goods_bar_code' => '', 'goods_name' => '', 'goods_id' => 0,
            'category_id' => 0, 'status' => 1, 'time_start' => '', 'time_end' => '', 'imei' => '', 'fineness' => 0, 'fineness2' => ''];
        if ($phase === 'catalog') $query += ['brand_id' => 0, 'zero_stock' => 0, 'sort_name' => 'sort', 'sort_val' => 'asc'];
        return $query;
    }

    private function safeSnapshot(array $row): array
    {
        $safe = ['id' => $this->id($row['id'] ?? null, '机小二设备标识'),
            'shop_id' => $this->id($row['shop_id'] ?? null, '机小二来源门店标识')];
        foreach (['goods_id', 'goods_name', 'brand_name', 'imei', 'imei_price', 'imei_wholesale_price',
            'stock_type', 'is_sale_before'] as $key) {
            if (array_key_exists($key, $row)) $safe[$key] = $this->scalar($row[$key]);
        }
        $safe['ext_json'] = $this->properties($row['ext_json'] ?? []);
        $properties = array_column($safe['ext_json'], 'value', 'key');
        $safe['fineness'] = $this->scalar($properties['fineness'] ?? null);
        $history = $row['stock_list_history_imei'] ?? null;
        $safe['stock_list_history_imei'] = [];
        if ($history !== null && $history !== []) {
            if (!is_array($history)) throw new ProviderException('schema_changed', '机小二库存状态格式变化');
            if (array_key_exists('shop_id', $history)
                && $this->id($history['shop_id'], '库存状态来源门店') !== $safe['shop_id']) {
                throw new ProviderException('scope_mismatch', '机小二设备与库存状态的来源门店不一致');
            }
            if (array_key_exists('goods_id', $history)
                && $this->id($history['goods_id'], '库存状态型号标识') !== (string) ($safe['goods_id'] ?? '')) {
                throw new ProviderException('scope_mismatch', '机小二设备与库存状态的型号不一致');
            }
            foreach (['id', 'shop_id', 'goods_id', 'stock_type', 'stock_num', 'created_at', 'order_time'] as $key) {
                if (array_key_exists($key, $history)) $safe['stock_list_history_imei'][$key] = $this->scalar($history[$key]);
            }
        }
        $safe['goods_platforms'] = [];
        foreach (is_array($row['goods_platforms'] ?? null) ? $row['goods_platforms'] : [] as $platform) {
            if (!is_array($platform)) continue;
            $safe['goods_platforms'][] = ['key' => $this->scalar($platform['key'] ?? null),
                'platform' => $this->scalar($platform['platform'] ?? null),
                'name' => $this->text($platform['name'] ?? null), 'logo' => $this->url($platform['logo'] ?? null)];
        }
        return $safe;
    }

    private function properties(mixed $input): array
    {
        if ($input === null || $input === '') return [];
        if (is_string($input)) {
            if (strlen($input) > 65536) throw new ProviderException('schema_changed', '机小二设备属性超过安全大小');
            try {
                $input = json_decode($input, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (\Throwable $error) {
                throw new ProviderException('schema_changed', '机小二设备属性格式变化');
            }
        }
        if (!is_array($input) || !\addon\hsx_lead\app\support\PhpRuntime::isList($input)) throw new ProviderException('schema_changed', '机小二设备属性结构变化');
        $result = [];
        foreach ($input as $property) {
            if (!is_array($property) || !is_string($property['key'] ?? null)
                || !array_key_exists($property['key'], self::PROPERTY_LABELS)) continue;
            $key = $property['key'];
            $value = $this->scalar($property['value'] ?? null);
            if (isset($result[$key]) && $result[$key]['value'] !== $value) {
                throw new ProviderException('schema_changed', '机小二设备属性存在矛盾的重复值');
            }
            $result[$key] = ['key' => $key, 'text' => self::PROPERTY_LABELS[$key], 'value' => $value];
        }
        return array_values($result);
    }

    private function dateBound(mixed $value): string
    {
        if (!is_string($value)) throw new ProviderException('invalid_scope', '机小二库存日期格式不正确');
        if ($value === '') return '';
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new ProviderException('invalid_scope', '机小二库存日期格式不正确');
        }
        return $value;
    }

    private function headers(#[\SensitiveParameter] string $token, string $referer): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return ['Accept' => 'application/json', 'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest', 'Referer' => $referer,
            'Cookie' => self::COOKIE_NAME.'='.$this->cookieToken($token)];
    }

    private function cookieToken(#[\SensitiveParameter] string $token): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if ($token === '' || strlen($token) > 16384
            || !preg_match('/^[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]+$/D', $token)) {
            throw new ProviderException('invalid_credentials', '机小二会话凭据无效，请重新登录');
        }
        return $token;
    }

    private function responseCookie(#[\SensitiveParameter] array $headers): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $tokens = [];
        foreach ($headers as $name => $values) {
            if (!is_string($name) || strcasecmp($name, 'Set-Cookie') !== 0) continue;
            foreach (is_array($values) ? $values : [$values] as $value) {
                if (!is_string($value)) continue;
                try {
                    $cookie = SetCookie::fromString($value);
                } catch (\Throwable $error) {
                    continue;
                }
                if ($cookie->getName() === self::COOKIE_NAME) {
                    $tokens[] = $this->cookieToken((string) $cookie->getValue());
                }
            }
        }
        $tokens = array_values(array_unique($tokens));
        if (count($tokens) !== 1) throw new ProviderException('auth_required', '机小二登录未取得唯一有效会话，请重新登录');
        return $tokens[0];
    }

    private function htmlIdentity(#[\SensitiveParameter] string $html): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!class_exists(\DOMDocument::class) || !function_exists('libxml_use_internal_errors')) {
            throw new ProviderException('unsupported_environment', '机小二身份核验需要 PHP DOM/XML 扩展，请联系管理员启用');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (!$document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
                throw new ProviderException('schema_changed', '机小二认证门店页面结构变化');
            }
            $xpath = new \DOMXPath($document);
            $options = $xpath->query('//select[@id="base_shop_name"]/option[@selected]');
            if ($options === false || $options->length !== 1) {
                throw new ProviderException('auth_required', '机小二未返回唯一的当前认证门店，请核对登录状态');
            }
            $option = $options->item(0);
            $shop = $this->id($option->getAttribute('value'), '机小二认证门店');
            return ['tenant_id' => $shop, 'store_id' => $shop,
                'store_name' => $this->text($option->textContent), 'verified' => true];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function request(string $method, string $endpoint, #[\SensitiveParameter] array $headers,
        #[\SensitiveParameter] array $data, string $encoding): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if (!$this->transport instanceof RequestTransportContract) {
            throw new ProviderException('unsupported_transport', '当前 ERP 读取服务不支持机小二会话授权，请更新插件后端');
        }
        $response = $this->transport->request($method, $endpoint, $headers, $data, $encoding);
        if (!is_array($response) || !is_int($response['status'] ?? null)
            || !is_array($response['headers'] ?? null) || !is_string($response['body'] ?? null)) {
            throw new ProviderException('schema_changed', 'ERP 只读请求返回格式变化');
        }
        if (in_array($response['status'], [401, 403], true)) throw new ProviderException('auth_required', '机小二授权已失效，请重新登录');
        if ($response['status'] !== 200) throw new ProviderException('http_error', '机小二只读接口暂未成功，未更新库存');
        return $response;
    }
}
