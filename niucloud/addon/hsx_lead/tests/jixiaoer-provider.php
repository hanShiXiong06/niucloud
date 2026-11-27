<?php
declare(strict_types=1);

// Synthetic fixtures only: no account credentials, network, database or ERP writes.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_lead\app\provider\JixiaoerProvider;
use addon\hsx_lead\app\provider\ProviderException;
use addon\hsx_lead\app\provider\RequestTransportContract;
use addon\hsx_lead\app\provider\TransportContract;

final class JixiaoerFixtureTransport implements RequestTransportContract
{
    public array $calls = [];
    public function __construct(private array $responses) {}
    public function post(string $endpoint, array $headers, array $body): array
    {
        throw new RuntimeException('Jixiaoer must not pretend form/query calls are JSON POST');
    }
    public function request(string $method, string $endpoint, array $headers, array $data, string $encoding): array
    {
        $this->calls[] = compact('method', 'endpoint', 'headers', 'data', 'encoding');
        $response = array_shift($this->responses);
        if (!is_array($response)) throw new RuntimeException('No synthetic Jixiaoer response remaining');
        return $response;
    }
}

$checks = 0;
function jxCheck(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $GLOBALS['checks']++;
}
function jxFailure(callable $action, string $reason): ProviderException
{
    try { $action(); }
    catch (ProviderException $error) {
        jxCheck($error->reason() === $reason, 'safe failure reason ' . $reason);
        jxCheck($error->getPrevious() === null, 'provider failure never chains secret request/response objects');
        return $error;
    }
    throw new RuntimeException('FAIL: expected safe failure ' . $reason);
}
function jxResponse(array|string $body, array $headers = [], int $status = 200): array
{
    return ['status' => $status, 'headers' => $headers,
        'body' => is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $body];
}
function jxPage(array $rows, int|string $total): array
{
    return jxResponse(['code' => 200, 'data' => ['list' => $rows, 'total' => $total,
        'counts' => ['private' => 'discard'], 'redis_key' => 'private-fixture-discard']]);
}
function jxLoginResponses(string $html = '<select disabled id="base_shop_name"><option selected value="fixture-shop">测试门店</option></select>'): array
{
    return [jxResponse(['code' => 200, 'data' => []],
        ['Set-Cookie' => ['unrelated_cookie=not-used; Path=/', 'gql_repair_token=fixture-session-only; Path=/; Secure; HttpOnly']]),
        jxResponse($html)];
}

$row = ['id' => '9223372036854775808', 'shop_id' => 'fixture-shop', 'goods_id' => 123,
    'goods_name' => 'iPhone 测试机型', 'brand_name' => '苹果', 'imei' => '123456789012345',
    'imei_price' => '2000.01', 'imei_wholesale_price' => '0.00', 'stock_type' => 21, 'is_sale_before' => 0,
    'ext_json' => json_encode([
        ['key' => 'memory', 'text' => 'private-label-must-not-survive', 'value' => '512GB'],
        ['key' => 'color', 'text' => '颜色', 'value' => '白色'],
        ['key' => 'fineness', 'value' => '九成新'], ['key' => 'batteryHealth', 'value' => '97'],
        ['key' => 'batteryCycleCount', 'value' => 101], ['key' => 'guarantee', 'value' => '2027-01-01'],
        ['key' => 'shop_guarantee', 'value' => '90天'], ['key' => 'system_version', 'value' => '26.0'],
        ['key' => 'buyChannel', 'value' => '国行'], ['key' => 'customer_mobile', 'value' => 'private-contact-fixture'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    'stock_list_history_imei' => ['id' => 55, 'shop_id' => 'fixture-shop', 'goods_id' => 123,
        'stock_type' => 21, 'stock_num' => '1.00', 'created_at' => '2026-10-30 12:00:00',
        'goods' => ['private' => 'discard'], 'customer' => ['mobile' => 'private-contact-fixture'],
        'order_price' => 'private-cost-fixture'],
    'goods_platforms' => [['key' => 'known-platform', 'platform' => 'known-value', 'name' => '平台标识',
        'logo' => 'https://example.test/logo.png', 'customer' => 'private-contact-fixture']],
    'cost' => 'private-cost-fixture', 'order_price' => 'private-cost-fixture',
    'imei_remark' => 'private-note-fixture', 'imei_remark_us' => 'private-note-fixture',
    'first_user_json' => ['mobile' => 'private-contact-fixture'], 'checking_json' => ['private' => 'discard'],
    'customer' => ['mobile' => 'private-contact-fixture'], 'supplier' => ['mobile' => 'private-contact-fixture']];
$context = ['connection_id' => 'fixture-connection', 'tenant_id' => 'fixture-shop', 'store_id' => 'fixture-shop',
    'fetched_at' => '2026-10-30T12:00:00Z'];
$parent = ['id' => 14, 'goods_id' => 123, 'shop_id' => 'fixture-shop', 'status' => 1];
$listing = ['id' => 14, 'goods_id' => 123, 'imei' => $row['imei'], 'status' => 1,
    'sell_price' => '2100.01', 'wholesale_price' => '2000.00', 'mall_goods_imei_id' => null];
$proof = ['parent_id' => '14', 'goods_id' => '123', 'shop_id' => 'fixture-shop',
    'parent_status' => 1, 'status' => 1, 'sell_price' => '2100.01', 'wholesale_price' => '2000.00',
    'mall_goods_imei_id' => null];
$row['listing'] = $proof;
$provider = new JixiaoerProvider(new JixiaoerFixtureTransport([]));
$item = $provider->normalize($row, $context);
jxCheck($item['source_key'] === 'jixiaoer:fixture-connection:fixture-shop:fixture-shop:9223372036854775808', 'source identity includes independent provider, connection, shop and exact large id');
jxCheck($item['source_id'] === $row['id'] && $item['tenant_id'] === 'fixture-shop' && $item['source_store_id'] === 'fixture-shop', 'shop identity is taken from source rows, not a warehouse or IMEI');
jxCheck($item['title'] === $row['goods_name'] && $item['brand'] === '苹果', 'known model and brand fields map directly');
jxCheck($item['capacity'] === '512GB' && $item['color'] === '白色', 'JSON properties normalize into model specifications');
jxCheck($item['retail_price'] === '2100.01' && $item['trade_price'] === '2000.00', 'current mall prices, not stale ERP prices, are published');
jxCheck($item['safe_snapshot']['imei_price'] === '2000.01' && $item['safe_snapshot']['imei_wholesale_price'] === '0.00', 'raw ERP retail/wholesale values remain separate backup values');
jxCheck($item['safe_snapshot']['fineness'] === '九成新', 'grade is available to generic provider definition without another field dictionary');
jxCheck($item['availability'] === 'available' && $item['listing_status'] === 'listed'
    && $item['stock_status'] === 'in_stock', 'verified listed parent and device joined to current stock are a sellable snapshot reference');
jxCheck($item['quantity'] === null && $item['saleable_quantity'] === '1.000'
    && $item['quantity_basis'] === 'listed_stock_device', 'one verified current listed device is explicit, never copied from a stock-history balance');
jxCheck($item['source_status'] === ['stock_type' => 21, 'is_sale_before' => 0, 'stock_num' => '1.00', 'parent_status' => 1, 'listing_status' => 1], 'parent/child status and raw stock values remain independent');
jxCheck($item['images'] === [] && $item['cover'] === null, 'no product-image field has been verified; platform logo is not a device photo');
jxCheck(!preg_match('/private-|cost|order_price|imei_remark|first_user|checking_json|customer|supplier/', json_encode($item, JSON_THROW_ON_ERROR)), 'cost, internal notes, nested user/customer/supplier and dynamic text labels do not enter snapshots');
jxCheck(count($item['safe_snapshot']['ext_json']) === 9, 'only the nine verified device properties are retained');
jxCheck($item['safe_snapshot']['ext_json'][0]['text'] === '内存', 'property labels are local and stable, not untrusted upstream labels');
jxCheck(!isset($item['safe_snapshot']['stock_list_history_imei']['goods']), 'nested raw goods are not copied into inventory');
jxCheck($item['safe_snapshot']['goods_platforms'][0]['logo'] === 'https://example.test/logo.png', 'platform metadata stays a bounded explicit whitelist');
jxCheck($provider->normalize(array_replace($row, ['id' => 'different-source']), $context)['source_key'] !== $item['source_key'], 'same IMEI cannot merge independent ERP source records');
jxCheck($provider->normalize($row, array_replace($context, ['connection_id' => 'another-connection']))['source_key'] !== $item['source_key'], 'identical source under a different connection stays isolated');
foreach (['' => null, '***' => null, '-1' => null, '1.234' => null, '1e3' => null, '0002.1' => '2.10', '0' => '0.00'] as $price => $expected) {
    jxCheck($provider->normalize(array_replace($row, ['listing' => array_replace($proof, ['sell_price' => (string) $price])]), $context)['retail_price'] === $expected, 'missing/masked/invalid/exact price behavior');
}
jxCheck($provider->normalize(array_replace($row, ['listing' => array_replace($proof, ['sell_price' => 2.1])]), $context)['retail_price'] === null, 'binary float input cannot be silently rounded');
jxFailure(fn() => $provider->normalize(array_replace($row, ['listing' => null]), $context), 'schema_changed');
jxFailure(fn() => $provider->normalize(array_replace($row, ['listing' => array_replace($proof, ['shop_id' => 'other-shop'])]), $context), 'schema_changed');
jxCheck($provider->normalize(array_replace($row, ['ext_json' => null]), $context)['capacity'] === null, 'missing specifications remain empty rather than guessed');
$specs = [['key' => 'memory', 'value' => '128GB'], ['key' => 'color', 'value' => '蓝色']];
jxCheck($provider->normalize(array_replace($row, ['ext_json' => $specs]), $context)['capacity'] === '128GB', 'already-decoded property lists use the same contract');
jxFailure(fn() => $provider->normalize(array_replace($row, ['shop_id' => null]), $context), 'invalid_scope');
jxFailure(fn() => $provider->normalize($row, array_replace($context, ['store_id' => 'other-shop'])), 'scope_mismatch');
jxFailure(fn() => $provider->normalize($row, array_replace($context, ['tenant_id' => 'other-tenant'])), 'scope_mismatch');
jxFailure(fn() => $provider->normalize(array_replace($row, ['stock_list_history_imei' => ['shop_id' => 'other-shop']]), $context), 'scope_mismatch');
jxFailure(fn() => $provider->normalize(array_replace($row, ['stock_list_history_imei' => ['shop_id' => 'fixture-shop', 'goods_id' => 999]]), $context), 'scope_mismatch');
jxFailure(fn() => $provider->normalize(array_replace($row, ['ext_json' => '{broken']), $context), 'schema_changed');
jxFailure(fn() => $provider->normalize(array_replace($row, ['ext_json' => ['memory' => '128GB']]), $context), 'schema_changed');
jxFailure(fn() => $provider->normalize(array_replace($row, ['ext_json' => [['key' => 'memory', 'value' => '128GB'], ['key' => 'memory', 'value' => '512GB']]]), $context), 'schema_changed');

$loginTransport = new JixiaoerFixtureTransport(jxLoginResponses());
$login = (new JixiaoerProvider($loginTransport))->login(['username' => ' fixture-user ', 'password' => 'fixture-password']);
jxCheck($login['access_token'] === 'fixture-session-only' && $login['expires_at'] === null, 'only the verified session cookie value is persisted as authorization');
jxCheck($login['identity'] === ['tenant_id' => 'fixture-shop', 'store_id' => 'fixture-shop', 'store_name' => '测试门店', 'verified' => true], 'authenticated HTML selected shop supplies the verified identity');
jxCheck($loginTransport->calls[0] === ['method' => 'POST', 'endpoint' => JixiaoerProvider::LOGIN_ENDPOINT, 'headers' => [],
    'data' => ['phone' => 'fixture-user', 'password' => 'fixture-password', 'login_type' => 'admin'], 'encoding' => 'form'], 'observed login form maps username to phone with exact login_type');
jxCheck($loginTransport->calls[1]['method'] === 'GET' && $loginTransport->calls[1]['endpoint'] === JixiaoerProvider::IDENTITY_ENDPOINT, 'identity comes from the authenticated fixed page');
jxCheck($loginTransport->calls[1]['headers']['Cookie'] === 'gql_repair_token=fixture-session-only', 'unrelated cookies are never forwarded');
jxCheck(!str_contains(json_encode($login['identity']), 'password') && !isset($login['raw']), 'login public identity does not expose raw page or credentials');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse(['code' => 500, 'msg' => 'fixture-password'])])))->login(['username' => 'u', 'password' => 'p']), 'upstream_error');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse(['code' => 200, 'data' => []])])))->login(['username' => 'u', 'password' => 'p']), 'auth_required');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse(['code' => 200], ['Set-Cookie' => ['gql_repair_token=first', 'gql_repair_token=second']])])))->login(['username' => 'u', 'password' => 'p']), 'auth_required');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport(jxLoginResponses('<html><form>请登录</form></html>'))))->login(['username' => 'u', 'password' => 'p']), 'auth_required');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport(jxLoginResponses('<select id="base_shop_name"><option value="a" selected>A</option><option value="b" selected>B</option></select>'))))->login(['username' => 'u', 'password' => 'p']), 'auth_required');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse('', [], 302)])))->login(['username' => 'u', 'password' => 'p']), 'http_error');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([])))->login(['username' => '', 'password' => 'p']), 'invalid_credentials');

$scope = ['tenant_id' => 'fixture-shop', 'store_id' => 'fixture-shop', 'page_size' => 2];
$auth = ['access_token' => 'fixture-session-only'];
function jxAdvance(JixiaoerProvider $provider, array $scope, int $calls, array $cursor = []): array
{
    $result = [];
    for ($i = 0; $i < $calls; $i++) {
        $result = $provider->fetchPage(['access_token' => 'fixture-session-only'], $cursor, $scope);
        $cursor = $result['cursor'];
    }
    return $result;
}
$second = array_replace($row, ['id' => 'second-id', 'imei' => '223456789012345',
    'stock_list_history_imei' => ['shop_id' => 'fixture-shop', 'stock_num' => '0.00']]);
$third = array_replace($row, ['id' => 'third-id', 'imei' => '323456789012345']);
$secondListing = array_replace($listing, ['imei' => $second['imei'], 'mall_goods_imei_id' => 9]);
$thirdListing = array_replace($listing, ['imei' => $third['imei'], 'mall_goods_imei_id' => 9]);
$stockTransport = new JixiaoerFixtureTransport([jxPage([$parent], 1),
    jxPage([$listing, $secondListing], 3), jxPage([$thirdListing], 3),
    jxPage([$row, $second], 3), jxPage([$third], 3)]);
$stock = new JixiaoerProvider($stockTransport);
$first = $stock->fetchPage($auth, ['page' => 1], $scope);
jxCheck(count($stockTransport->calls) === 1 && $first['rows'] === [] && $first['has_more'], 'one catalog request per call and pre-read is not empty inventory');
jxCheck($first['cursor']['phase'] === 'listed' && $first['cursor']['page'] === 2 && $first['cursor']['phase_page'] === 1, 'global page increments while phase page restarts');
jxCheck(str_contains($first['note'], '核对上架型号') && str_contains($first['note'], '不代表零库存'), 'catalog note explains safe preparatory state');
$middle = $stock->fetchPage($auth, $first['cursor'], $scope);
jxCheck(count($stockTransport->calls) === 2 && $middle['rows'] === [] && $middle['cursor']['phase_page'] === 2, 'listed stage persists a small safe index across calls');
$ready = $stock->fetchPage($auth, $middle['cursor'], $scope);
jxCheck($ready['rows'] === [] && $ready['has_more'] && $ready['cursor']['phase'] === 'stock' && $ready['cursor']['parents'] === [], 'parent map is released after listed phase');
$stockFirst = $stock->fetchPage($auth, $ready['cursor'], $scope);
$last = $stock->fetchPage($auth, $stockFirst['cursor'], $scope);
jxCheck($stockFirst['has_more'] && !$last['has_more'] && $last['cursor']['phase'] === 'done' && $last['cursor']['page'] === 6, 'final stock page completes the whole monotonic state machine');
jxCheck(count($stockFirst['rows']) === 2 && count($last['rows']) === 1 && $last['cursor']['listed'] === [], 'all exact up-stock joins are emitted once');
jxCheck($stockFirst['rows'][1]['stock_list_history_imei']['stock_num'] === '0.00', 'official >=0 in-stock rule preserves zero stock_num');
$zeroStockItem = $stock->normalize($stockFirst['rows'][1], $context);
jxCheck($zeroStockItem['availability'] === 'available' && $zeroStockItem['quantity'] === null
    && $zeroStockItem['saleable_quantity'] === '1.000' && $zeroStockItem['source_status']['stock_num'] === '0.00',
    'zero history balance remains the verified current device, not zero quantity or an invented sale');
$negativeStockItem = $provider->normalize(array_replace($row, ['stock_list_history_imei' => ['shop_id' => 'fixture-shop', 'stock_num' => '-1.00']]), $context);
jxCheck($negativeStockItem['availability'] === 'unavailable' && $negativeStockItem['stock_status'] === 'out_of_stock'
    && $negativeStockItem['saleable_quantity'] === null, 'negative stock history cannot produce a sellable unit proof');
$missingStockItem = $provider->normalize(array_replace($row, ['stock_list_history_imei' => []]), $context);
jxCheck($missingStockItem['availability'] === 'unknown' && $missingStockItem['stock_status'] === 'unknown'
    && $missingStockItem['saleable_quantity'] === null, 'listing and IMEI alone do not prove current stock');
jxCheck($last['rows'][0]['id'] === 'third-id' && $stockFirst['rows'][0]['id'] === $row['id'], 'serial IDs stay unique despite missing and repeated mall_goods_imei_id');
jxCheck($stock->normalize($stockFirst['rows'][0], $context)['retail_price'] === '2100.01', 'fetched output supplies sanitized current mall prices to normalization');
jxCheck(!str_contains(json_encode([$first, $middle, $ready, $stockFirst, $last]), 'private-'), 'cursor and fetch rows exclude raw private fields and credentials');
jxCheck($stockTransport->calls[0]['endpoint'] === JixiaoerProvider::CATALOG_ENDPOINT
    && $stockTransport->calls[1]['endpoint'] === JixiaoerProvider::LISTED_ENDPOINT
    && $stockTransport->calls[3]['endpoint'] === JixiaoerProvider::STOCK_ENDPOINT, 'only the three observed readonly endpoints are used');
jxCheck($stockTransport->calls[1]['data']['offset'] === 0 && $stockTransport->calls[2]['data']['offset'] === 2
    && $stockTransport->calls[3]['data']['offset'] === 0 && $stockTransport->calls[4]['data']['offset'] === 2, 'offsets use phase page not global progress page');
jxCheck($stockTransport->calls[0]['data']['time_start'] === '' && $stockTransport->calls[0]['data']['time_end'] === ''
    && $stockTransport->calls[0]['data']['sort_name'] === 'sort' && $stockTransport->calls[0]['data']['sort_val'] === 'asc'
    && !isset($stockTransport->calls[0]['data']['attrsParams']), 'catalog mirrors actual blank-date sort/asc query without uninitialized attrsParams');
jxCheck($stockTransport->calls[1]['data']['goods_id'] === 0 && $stockTransport->calls[1]['data']['time_start'] === ''
    && !isset($stockTransport->calls[1]['data']['id']) && !isset($stockTransport->calls[1]['data']['zero_stock'])
    && !isset($stockTransport->calls[1]['data']['sort_name']), 'listed query reads global up IMEIs without per-model requests or unobserved filters');
foreach (['order', 'time_start', 'time_end', 'category', 'brand_id', 'imei_remark', 'goods_id', 'goods_attributes_info_id', 'memory', 'color', 'fineness', 'fineness2', 'imei', 'platform', 'store', 'statusIn', 'tag', 'supplier_id', 'customer_id', 'admin_user_id'] as $key) {
    jxCheck(array_key_exists($key, $stockTransport->calls[3]['data']), 'full stock query preserves required key ' . $key);
}
jxCheck($stockTransport->calls[3]['data']['time_start'] === JixiaoerProvider::DEFAULT_TIME_START
    && $stockTransport->calls[3]['data']['time_end'] === JixiaoerProvider::DEFAULT_TIME_END, 'stock uses verified full-period range rather than UI one-year default');
foreach ($stockTransport->calls as $call) {
    jxCheck($call['method'] === 'GET' && $call['encoding'] === 'query' && $call['headers']['Cookie'] === 'gql_repair_token=fixture-session-only', 'phase makes one fixed GET with only expected session cookie');
}
$blankDates = new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1), jxPage([$row], 1)]);
jxAdvance(new JixiaoerProvider($blankDates), $scope + ['query' => ['time_start' => '', 'time_end' => '']], 3);
jxCheck($blankDates->calls[2]['data']['time_start'] === JixiaoerProvider::DEFAULT_TIME_START, 'explicit empty stock bounds fall back to verified wide range');
$empty = (new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([], 0)])))->fetchPage($auth, [], $scope);
jxCheck($empty['rows'] === [] && !$empty['has_more'], 'a genuinely empty up catalog can finish empty');
$none = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([], 0)]));
jxCheck(!jxAdvance($none, $scope, 2)['has_more'], 'zero up IMEIs finishes without publishing raw stock');
$parentNotUp = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([array_replace($listing, ['id' => 999])], 1)]));
$filtered = jxAdvance($parentNotUp, $scope, 2);
jxCheck(!$filtered['has_more'] && $filtered['rows'] === [] && $filtered['cursor']['skipped_parent'] === 1, 'up child with absent/down parent is excluded, never joined by goods_id alone');
jxCheck(str_contains($filtered['note'], '排除未上架父商品'), 'excluded parent relation is visible in local progress note');
$repeatedSkipped = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1),
    jxPage([array_replace($listing, ['id' => 999])], 2), jxPage([array_replace($listing, ['id' => 999])], 2)]));
$skipScope = array_replace($scope, ['page_size' => 1]);
$skipFirst = jxAdvance($repeatedSkipped, $skipScope, 2);
jxFailure(fn() => $repeatedSkipped->fetchPage($auth, $skipFirst['cursor'], $skipScope), 'schema_changed');
$wrongOffset = new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse(['code' => 200,
    'data' => ['list' => [$parent], 'total' => 1, 'offset' => 100]])]));
jxFailure(fn() => $wrongOffset->fetchPage($auth, [], $scope), 'schema_changed');

// Every safety failure is local, typed and free of chained HTTP secrets.
$drifting = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent, array_replace($parent, ['id' => 15])], 3), jxPage([$parent], 4)]));
$beforeDrift = $drifting->fetchPage($auth, [], $scope);
jxFailure(fn() => $drifting->fetchPage($auth, $beforeDrift['cursor'], $scope), 'schema_changed');
$listedDrift = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing, $secondListing], 3), jxPage([$thirdListing], 4)]));
$beforeListedDrift = jxAdvance($listedDrift, $scope, 2);
jxFailure(fn() => $listedDrift->fetchPage($auth, $beforeListedDrift['cursor'], $scope), 'schema_changed');
$stockDrift = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1), jxPage([$row, $second], 3), jxPage([$third], 4)]));
$beforeStockDrift = jxAdvance($stockDrift, $scope, 3);
jxFailure(fn() => $stockDrift->fetchPage($auth, $beforeStockDrift['cursor'], $scope), 'schema_changed');
foreach ([['id' => 14, 'goods_id' => 999], ['id' => 14, 'status' => 2]] as $bad) {
    $badProvider = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([array_replace($listing, $bad)], 1)]));
    jxFailure(fn() => jxAdvance($badProvider, $scope, 2), isset($bad['goods_id']) ? 'scope_mismatch' : 'schema_changed');
}
$duplicateListing = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing, $listing], 2)]));
jxFailure(fn() => jxAdvance($duplicateListing, $scope, 2), 'schema_changed');
$duplicateStock = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1), jxPage([$row, $second], 3), jxPage([$row], 3)]));
$beforeDuplicate = jxAdvance($duplicateStock, $scope, 3);
jxFailure(fn() => $duplicateStock->fetchPage($auth, $beforeDuplicate['cursor'], $scope), 'schema_changed');
$unmatched = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1), jxPage([$second], 1)]));
jxFailure(fn() => jxAdvance($unmatched, $scope, 3), 'schema_changed');
$suffixOnly = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([array_replace($listing, ['imei' => '012345'])], 1), jxPage([$row], 1)]));
jxFailure(fn() => jxAdvance($suffixOnly, $scope, 3), 'schema_changed');
foreach (['-1.00', '-0.' . str_repeat('0', 400) . '1', 'unknown', null] as $stockNumber) {
    $notInStock = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1),
        jxPage([array_replace($row, ['stock_list_history_imei' => ['shop_id' => 'fixture-shop', 'stock_num' => $stockNumber]])], 1)]));
    jxFailure(fn() => jxAdvance($notInStock, $scope, 3), 'schema_changed');
}
$negativeZero = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1),
    jxPage([array_replace($row, ['stock_list_history_imei' => ['shop_id' => 'fixture-shop', 'stock_num' => '-0.00']])], 1)]));
jxCheck(count(jxAdvance($negativeZero, $scope, 3)['rows']) === 1, 'negative-zero decimal is still exactly zero without binary floating conversion');
$scopeFailure = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([array_replace($parent, ['shop_id' => 'other-shop'])], 1)]));
jxFailure(fn() => $scopeFailure->fetchPage($auth, [], $scope), 'scope_mismatch');
$stockScopeFailure = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1), jxPage([$listing], 1),
    jxPage([array_replace($row, ['shop_id' => 'other-shop', 'stock_list_history_imei' => []])], 1)]));
jxFailure(fn() => jxAdvance($stockScopeFailure, $scope, 3), 'scope_mismatch');
$driftScope = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 1)]));
$saved = $driftScope->fetchPage($auth, [], $scope);
jxFailure(fn() => $driftScope->fetchPage($auth, $saved['cursor'], array_replace($scope, ['page_size' => 1])), 'invalid_scope');
jxFailure(fn() => $driftScope->fetchPage($auth, $saved['cursor'], $scope + ['query' => ['time_start' => '2020-01-01']]), 'invalid_scope');
jxFailure(fn() => $driftScope->fetchPage($auth, array_replace($saved['cursor'], ['phase' => 'arbitrary']), $scope), 'invalid_scope');
jxFailure(fn() => $driftScope->fetchPage($auth, array_replace($saved['cursor'], ['page' => 101]), $scope), 'invalid_scope');
jxFailure(fn() => $driftScope->fetchPage($auth, array_replace($saved['cursor'], ['phase_page' => 0]), $scope), 'invalid_scope');
jxFailure(fn() => $driftScope->fetchPage($auth, array_replace($saved['cursor'], ['listed' => str_repeat('a', 4194305)]), $scope), 'invalid_scope');
jxFailure(fn() => $driftScope->fetchPage($auth, array_replace($saved['cursor'], ['seen_listed' => array_fill(0, 10001, true)]), $scope), 'invalid_scope');
$lastAllowedPage = new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$listing, $secondListing], 3)]));
jxFailure(fn() => $lastAllowedPage->fetchPage($auth, array_replace($saved['cursor'], ['page' => 100]), $scope), 'invalid_scope');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 10001)])))->fetchPage($auth, [], array_replace($scope, ['page_size' => 1])), 'invalid_scope');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent], 3)])))->fetchPage($auth, [], $scope), 'schema_changed');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxPage([$parent, $parent], 2)])))->fetchPage($auth, [], $scope), 'schema_changed');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse(['code' => 401, 'msg' => 'fixture-session-only'])])))->fetchPage($auth, [], $scope), 'auth_required');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([jxResponse('<html>请登录</html>')])))->fetchPage($auth, [], $scope), 'schema_changed');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([])))->fetchPage(['access_token' => "bad\r\nheader"], [], $scope), 'invalid_credentials');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([])))->fetchPage($auth, [], ['query' => ['writeback' => true]]), 'invalid_scope');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([])))->fetchPage($auth, [], ['query' => ['time_start' => '2026-02-30']]), 'invalid_scope');
jxFailure(fn() => (new JixiaoerProvider(new JixiaoerFixtureTransport([])))->fetchPage($auth, [], ['query' => ['time_start' => '2026-11-01', 'time_end' => '2026-10-30']]), 'invalid_scope');
$jsonOnly = new class implements TransportContract { public function post(string $endpoint, array $headers, array $body): array { throw new RuntimeException('JSON-only transport must not be called'); } };
jxFailure(fn() => (new JixiaoerProvider($jsonOnly))->login(['username' => 'u', 'password' => 'p']), 'unsupported_transport');

echo 'PASS ' . $checks . " Jixiaoer provider checks; synthetic HTTP fixtures only, no network/database/ERP mutations\n";
