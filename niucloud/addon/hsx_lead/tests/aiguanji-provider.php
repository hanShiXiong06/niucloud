<?php
declare(strict_types=1);

// Synthetic-only provider contract regression. No HTTP, account, file secrets or business database.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\{AiguanjiProvider, InteractiveAuthProviderContract, ProviderException, RequestTransportContract, TransportContract};

final class AiguanjiFixtureTransport implements RequestTransportContract
{
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $endpoint, array $headers, array $body): array
    {
        $this->calls[] = compact('endpoint', 'headers', 'body');
        $response = array_shift($this->responses);
        if (!is_array($response)) throw new RuntimeException('Missing synthetic response');
        return $response;
    }
    public function request(string $method, string $endpoint, array $headers, array $data, string $encoding): array
    {
        $this->calls[] = compact('method', 'endpoint', 'headers', 'data', 'encoding');
        $response = array_shift($this->responses);
        if (!is_array($response)) throw new RuntimeException('Missing synthetic raw response');
        if ($endpoint === AiguanjiProvider::LOGIN_ENDPOINT && !array_key_exists('status', $response)) {
            return ['status' => 200, 'headers' => ['Content-Type' => ['application/json']],
                'body' => json_encode($response, JSON_THROW_ON_ERROR)];
        }
        return $response;
    }
}

$checks = 0;
function checkAiguanji(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    $GLOBALS['checks']++;
}
function failAiguanji(callable $call, string $reason): void
{
    try {
        $call();
    } catch (ProviderException $error) {
        checkAiguanji($error->reason() === $reason, 'explicit safe failure reason '.$reason);
        checkAiguanji($error->getPrevious() === null, 'upstream exception cannot expose credentials');
        checkAiguanji(!preg_match('/discard-|synthetic-secret|synthetic-company-session/', $error->getMessage()), 'safe exception text never echoes remote messages or transient credentials');
        return;
    }
    throw new RuntimeException('FAIL: expected '.$reason);
}
function aiguanjiPage(array $rows, int $total, int $page = 1, int $size = 2): array
{
    return ['code' => 0, 'data' => ['total' => $total, 'per_page' => $size, 'current_page' => $page,
        'last_page' => $total === 0 ? 0 : (int) ceil($total / $size), 'data' => $rows, 'has_more' => $page * $size < $total]];
}

$row = ['id' => 101, 'company_id' => 42, 'warehouse_id' => 8, 'product_id' => 13, 'name' => '合成 iPhone 型号',
    'status' => 55, 'status_text' => '已上架', 'product_attr_memory' => '256GB', 'product_attr_color' => '银色',
    'product_brand' => ['id' => 1, 'name' => '合成品牌', 'contact' => 'discard-private'],
    'product_cate' => ['id' => 2, 'name' => '手机'], 'warehouse' => ['id' => 8, 'name' => '合成仓'],
    'purity' => ['id' => 5, 'name' => '良好'],
    'selected_attr' => ['内存' => '128GB', '颜色' => '白色', '电池健康度' => '95', '充电次数' => 200, '客户电话' => 'discard-private'],
    'pre_sale_amount' => '9000.01', 'sale_amount' => '8888.00', 'industry_amount' => '8000.00', 'cost_price' => '7000.00', 'remark' => 'discard-private',
    'product_no' => 'synthetic-product-number', 'imei' => '123456789012345', 'imei2' => '123456789012346',
    'url' => 'https://example.test/report', 'user' => ['password' => 'discard-password', 'mobile' => 'discard-private']];
$scope = ['tenant_id' => '42', 'store_id' => '42', 'page_size' => 2];
$credentials = ['access_token' => 'synthetic-aiguanji-token'];
$context = ['connection_id' => '7', 'tenant_id' => '42', 'store_id' => '42', 'fetched_at' => '2026-10-30T00:00:00Z'];
$provider = new AiguanjiProvider(new AiguanjiFixtureTransport([]));
$normalized = $provider->normalize($row, $context);
checkAiguanji($normalized['source_key'] === 'aiguanji:7:42:42:101', 'company-scoped identity never invents a branch');
checkAiguanji($normalized['source_store_id'] === '42' && $normalized['source_status']['warehouse_id'] === 8, 'company scope and warehouse attribute are separate');
checkAiguanji($normalized['title'] === $row['name'] && $normalized['brand'] === '合成品牌', 'verified model and nested brand fields normalize');
checkAiguanji($normalized['capacity'] === '256GB' && $normalized['color'] === '银色', 'flattened exact inventory attributes take precedence');
checkAiguanji($normalized['retail_price'] === '9000.01' && $normalized['trade_price'] === '8000.00', 'verified reference retail price and industry price use exact independent columns');
checkAiguanji($normalized['images'] === [] && $normalized['imei'] === '123456789012345', 'report URL is not an image; verified IMEI is distinct from source product number');
checkAiguanji($normalized['availability'] === 'available' && $normalized['listing_status'] === 'listed'
    && $normalized['stock_status'] === 'in_stock' && $normalized['source_status']['status'] === 55,
    'verified status 55 current stock-device row is a listed snapshot, not a reservation or sale');
checkAiguanji($normalized['quantity'] === null && $normalized['saleable_quantity'] === '1.000'
    && $normalized['quantity_basis'] === 'listed_stock_device', 'current listed device carries a separate explicit unit proof, no fabricated source count');
checkAiguanji(!isset($normalized['safe_snapshot']['sale_amount']) && !preg_match('/cost_price|discard-|"user"|"remark"/', json_encode($normalized)), 'safe snapshot excludes costs, transaction amounts, contacts, passwords and private notes');
checkAiguanji($normalized['safe_snapshot']['selected_attr']['电池健康度'] === '95' && !isset($normalized['safe_snapshot']['selected_attr']['客户电话']), 'only approved descriptive attributes are retained');
checkAiguanji($provider->normalize(array_replace($row, ['product_attr_memory' => '', 'product_attr_color' => '']), $context)['capacity'] === '128GB', 'empty flattened attribute uses the verified descriptive attribute');
foreach (['' => null, '***' => null, '-1' => null, '1.001' => null, '1e3' => null, '0' => '0.00', '0002.1' => '2.10'] as $value => $expected) {
    checkAiguanji($provider->normalize(array_replace($row, ['pre_sale_amount' => (string) $value]), $context)['retail_price'] === $expected, 'retail decimal preserves missing, zero, masked and invalid amounts without cost fallback');
}
checkAiguanji($provider->normalize(array_replace($row, ['pre_sale_amount' => 9000.01]), $context)['retail_price'] === null, 'untrusted binary float does not silently round into a price');
failAiguanji(fn () => $provider->normalize(array_replace($row, ['status' => 47]), $context), 'scope_mismatch');
failAiguanji(fn () => $provider->normalize($row, array_replace($context, ['store_id' => '43'])), 'scope_mismatch');
failAiguanji(fn () => $provider->normalize($row, array_replace($context, ['tenant_id' => '43'])), 'scope_mismatch');

$transport = new AiguanjiFixtureTransport([
    aiguanjiPage([$row, array_replace($row, ['id' => 102, 'warehouse_id' => 9])], 3),
    aiguanjiPage([array_replace($row, ['id' => 103])], 3, 2),
]);
$provider = new AiguanjiProvider($transport);
$first = $provider->fetchPage($credentials, [], $scope);
$last = $provider->fetchPage($credentials, $first['cursor'], $scope);
checkAiguanji($first['has_more'] && !$last['has_more'] && $last['cursor']['page'] === 3, 'complete pagination carries the exact total');
checkAiguanji($first['cursor']['company_id'] === '42' && $last['cursor']['total'] === 3, 'cursor binds subsequent pages to one observed company and total');
checkAiguanji($transport->calls[0]['endpoint'] === AiguanjiProvider::STOCK_ENDPOINT, 'only the verified stock endpoint is used');
checkAiguanji($transport->calls[0]['headers'] === ['Authorization' => 'synthetic-aiguanji-token', 'version' => '2026102201', 'X-Client-Route' => '/storage/product'], 'raw authorization is not prefixed with Bearer');
checkAiguanji($transport->calls[0]['body']['status'] === ['55'] && $transport->calls[0]['body']['warehouse_id'] === [], 'verified listed-only filter does not silently choose one warehouse');
checkAiguanji($transport->calls[0]['body']['selected_attr'] instanceof stdClass && $transport->calls[1]['body']['page'] === 2, 'empty attribute object and page parameter preserve request shape');
checkAiguanji(!str_contains(json_encode($first['rows']), 'discard-'), 'private data is stripped before leaving the fetch boundary');
$empty = (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiPage([], 0, 1, 20)])))->fetchPage($credentials, [], ['page_size' => 100]);
checkAiguanji($empty['rows'] === [] && !$empty['has_more'] && $empty['cursor']['total'] === 0, 'real zero-on-sale response with last_page zero is a successful empty list');
$bounded = new AiguanjiFixtureTransport([aiguanjiPage([], 0, 1, 20)]);
(new AiguanjiProvider($bounded))->fetchPage($credentials, [], ['page_size' => 100]);
checkAiguanji($bounded->calls[0]['body']['limit'] === 20, 'unverified large-page settings are conservatively bounded to the observed size');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([])))->fetchPage($credentials, [], ['page_size' => 101]), 'invalid_scope');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([])))->fetchPage($credentials, [], $scope + ['query' => ['status' => []]]), 'invalid_scope');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([])))->fetchPage($credentials, ['company_id' => '43'], $scope), 'scope_mismatch');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiPage([$row, $row], 2)])))->fetchPage($credentials, [], $scope), 'schema_changed');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiPage([$row, array_replace($row, ['id' => 102, 'company_id' => 43])], 2)])))->fetchPage($credentials, [], $scope), 'scope_mismatch');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiPage([array_replace($row, ['status' => 47])], 1)])))->fetchPage($credentials, [], $scope), 'scope_mismatch');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiPage([$row], 2)])))->fetchPage($credentials, [], $scope), 'schema_changed');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiPage([$row], 1)])))->fetchPage($credentials, ['total' => 2], $scope), 'schema_changed');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([array_replace_recursive(aiguanjiPage([$row], 1), ['data' => ['has_more' => true]])])))->fetchPage($credentials, [], $scope), 'schema_changed');
foreach ([5 => 'auth_required', 2 => 'client_version_changed', 10 => 'auth_challenge', 1 => 'upstream_error'] as $code => $reason) {
    $fixture = new AiguanjiFixtureTransport([['code' => $code, 'msg' => 'discard-private-password']]);
    failAiguanji(fn () => (new AiguanjiProvider($fixture))->fetchPage($credentials, [], $scope), $reason);
}
failAiguanji(fn () => $provider->fetchPage(['access_token' => "token\r\nheader"], [], $scope), 'invalid_credentials');

$profile = ['code' => 0, 'data' => ['user' => ['id' => 99, 'name' => 'discard-user-name', 'mobile' => '13800000000',
    'company' => ['id' => 42, 'name' => '合成公司', 'balance' => '999.99', 'contacts' => ['mobile' => 'discard-private']],
    'teams' => [['id' => 42], ['id' => 43]], 'password' => 'discard-password'],
    'cache' => ['secret' => 'discard-cache'], 'menus' => ['discard-menus'], 'routes' => [], 'permission' => ['discard-permissions']]];
$profileTransport = new AiguanjiFixtureTransport([$profile]);
$verified = (new AiguanjiProvider($profileTransport))->login(['username' => ' 13800000000 ', 'access_token' => 'synthetic-profile-token']);
checkAiguanji($verified['identity'] === ['tenant_id' => '42', 'store_id' => '42', 'store_name' => '合成公司', 'verified' => true], 'HTTPS profile binds current company independently of inventory presence or other available teams');
checkAiguanji($verified['access_token'] === 'synthetic-profile-token' && $verified['expires_at'] === null && $verified['warnings'] !== [], 'verified token is retained without inventing cookie-based expiration');
checkAiguanji($profileTransport->calls[0]['endpoint'] === AiguanjiProvider::PROFILE_ENDPOINT && $profileTransport->calls[0]['body'] === [], 'profile verification has no account/password payload; transport encodes the empty body as JSON object');
checkAiguanji($profileTransport->calls[0]['headers'] === ['Authorization' => 'synthetic-profile-token', 'version' => '2026102201', 'X-Client-Route' => '/storage/product'], 'profile uses the observed raw authorization contract');
checkAiguanji(!preg_match('/discard-|balance|mobile|password|"user"|"teams"|"cache"|"menus"|"permission"/', json_encode($verified)), 'only safe company proof leaves login, never the full private profile');
$invalidInputs = [[], ['username' => ''], ['username' => '1380000000'], ['username' => '138000000000'], ['username' => '138 00000000'], ['username' => 13800000000]];
foreach ($invalidInputs as $input) {
    $fixture = new AiguanjiFixtureTransport([]);
    failAiguanji(fn () => (new AiguanjiProvider($fixture))->login($input + ['access_token' => 'synthetic-token']), 'invalid_credentials');
    checkAiguanji($fixture->calls === [], 'invalid account format is rejected before any upstream request');
}
$fixture = new AiguanjiFixtureTransport([]);
failAiguanji(fn () => (new AiguanjiProvider($fixture))->login(['username' => '13800000000', 'access_token' => "bad\r\nvalue"]), 'invalid_credentials');
checkAiguanji($fixture->calls === [], 'invalid authorization cannot reach the transport');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$profile])))->login(['username' => '13900000000', 'access_token' => 'synthetic-token']), 'scope_mismatch');
foreach ([null, [], [['id' => 42, 'name' => '公司一'], ['id' => 43, 'name' => '公司二']], ['id' => null, 'name' => '公司'], ['id' => 0, 'name' => '公司'], ['id' => 'not-an-id', 'name' => '公司'], ['id' => 42, 'name' => ''], ['id' => 42, 'name' => str_repeat('x', 121)]] as $company) {
    $bad = $profile;
    $bad['data']['user']['company'] = $company;
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$bad])))->login(['username' => '13800000000', 'access_token' => 'synthetic-token']), 'schema_changed');
}
foreach ([null, '138****0000', 13800000000] as $phone) {
    $bad = $profile;
    $bad['data']['user']['mobile'] = $phone;
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$bad])))->login(['username' => '13800000000', 'access_token' => 'synthetic-token']), is_string($phone) ? 'scope_mismatch' : 'schema_changed');
}
foreach ([5, 401, 403] as $code) {
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([['code' => $code, 'msg' => 'discard-password']])))->login(['username' => '13800000000', 'access_token' => 'synthetic-token']), 'auth_required');
}
$badShape = ['code' => 0, 'data' => ['token' => 'wrong-login-path']];
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$badShape])))->login(['username' => '13800000000', 'access_token' => 'synthetic-token']), 'schema_changed');

// Unlisted records can validate real field mappings without becoming eligible stock.
$pending = array_replace($row, ['status' => 47, 'status_text' => '待审核(销售)', 'pre_sale_amount' => 0,
    'product_attr_memory' => '256G', 'product_attr_color' => '银色',
    'selected_attr' => ['内存' => '256G', '颜色' => '银色', '国别' => '国行', '保修期' => '30天≤保修时长<180天',
        '充电次数' => '180', '电池健康度' => '95%', '客户电话' => 'discard-private']]);
$inspect = $provider->inspectSnapshot($pending, $context);
checkAiguanji(!$inspect['eligibility']['listed'] && $inspect['normalized']['source_status']['listed'] === false, 'unlisted device parsing explicitly remains ineligible');
checkAiguanji($inspect['normalized']['availability'] !== 'available' && $inspect['normalized']['quantity'] === null
    && $inspect['normalized']['saleable_quantity'] === null && $inspect['normalized']['quantity_basis'] === 'unknown',
    'unlisted inspection never inherits listed device stock proof');
checkAiguanji($inspect['normalized']['capacity'] === '256G' && $inspect['normalized']['color'] === '银色' &&
    $inspect['normalized']['retail_price'] === '0.00', 'actual unlisted shape maps independent specifications and an explicit zero public price');
checkAiguanji($inspect['normalized']['safe_snapshot']['selected_attr']['电池健康度'] === '95%' &&
    $inspect['normalized']['safe_snapshot']['selected_attr']['充电次数'] === '180', 'battery text and cycle count remain separate exact source attributes');
checkAiguanji(!preg_match('/discard-|cost_price|"user"|"remark"/', json_encode($inspect)), 'unlisted inspection uses the same sensitive-data whitelist');
failAiguanji(fn () => $provider->normalize($pending, $context), 'scope_mismatch');
checkAiguanji($provider->inspectSnapshot($row, $context)['normalized'] === $provider->normalize($row, $context), 'listed normalization and inspection reuse one mapping implementation');
checkAiguanji($provider->inspectSnapshot(array_replace($pending, ['status' => 99]), $context)['eligibility']['listed'] === false, 'unknown numerical status never defaults to listed');
foreach ([null, [], 'not-status', -1, 55.0] as $status) {
    failAiguanji(fn () => $provider->inspectSnapshot(array_replace($pending, ['status' => $status]), $context), 'schema_changed');
}
failAiguanji(fn () => $provider->inspectSnapshot($pending, array_replace($context, ['tenant_id' => 'different-company'])), 'scope_mismatch');

// Normal image CAPTCHA authorization: all responses here are synthetic, not solved challenges.
function aiguanjiCaptcha(string $type = 'image/jpeg', string $body = "\xff\xd8\xffsynthetic-image", array $extra = []): array
{
    return ['status' => 200, 'headers' => ['Content-Type' => [$type]] + $extra, 'body' => $body];
}
$captchaTransport = new AiguanjiFixtureTransport([aiguanjiCaptcha()]);
$interactive = new AiguanjiProvider($captchaTransport);
checkAiguanji($interactive instanceof InteractiveAuthProviderContract, 'interactive authorization is an optional provider capability');
$challenge = $interactive->beginAuthorization();
checkAiguanji(str_starts_with($challenge['public']['captcha_image'], 'data:image/jpeg;base64,') &&
    $challenge['public']['expires_in'] === 180, 'only a bounded raster data URL and short TTL are public');
checkAiguanji($challenge['state']['stage'] === 'captcha' && $challenge['state']['version'] === 1 &&
    preg_match('/^[a-f0-9]{32}$/D', $challenge['state']['device_id']) === 1, 'normal login uses a fresh integration-client identity kept in server challenge state');
checkAiguanji(!preg_match('/device_id|password|token|cookie/', json_encode($challenge['public'])), 'private device/session state is not placed in public challenge data');
checkAiguanji($captchaTransport->calls[0]['method'] === 'GET' && $captchaTransport->calls[0]['endpoint'] === AiguanjiProvider::CAPTCHA_ENDPOINT &&
    $captchaTransport->calls[0]['encoding'] === 'query' && $captchaTransport->calls[0]['headers'] === [], 'CAPTCHA uses the observed cookie-free GET endpoint without account information');
checkAiguanji(preg_match('/^0\.[0-9]{16}$/D', $captchaTransport->calls[0]['data']['v']) === 1, 'cache-buster follows the observed ordinary decimal format');
foreach ([['image/png', "\x89PNG\r\n\x1a\nsynthetic"], ['image/gif', 'GIF89asynthetic'], ['image/gif', 'GIF87asynthetic']] as [$type, $bytes]) {
    $result = (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiCaptcha($type, $bytes)])))->beginAuthorization();
    checkAiguanji(str_starts_with($result['public']['captcha_image'], 'data:'.$type.';base64,'), 'only supported raster image types are accepted');
}
foreach ([aiguanjiCaptcha('image/svg+xml', '<svg onload="alert(1)"/>'), aiguanjiCaptcha('text/html', '<html>private</html>'),
    aiguanjiCaptcha('image/jpeg', '<html>not-image</html>'), aiguanjiCaptcha('image/png', "\xff\xd8\xffwrong-mime"),
    aiguanjiCaptcha('image/jpeg', "\xff\xd8\xff".str_repeat('x', 131070)),
    aiguanjiCaptcha('image/jpeg', "\xff\xd8\xffsynthetic", ['content-type' => ['image/png']])] as $invalidCaptcha) {
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$invalidCaptcha])))->beginAuthorization(), 'schema_changed');
}
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiCaptcha('image/jpeg', "\xff\xd8\xffimage",
    ['sEt-CoOkIe' => ['session=discard-private; Path=/']])])))->beginAuthorization(), 'auth_challenge');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([['status' => 302, 'headers' => ['Location' => ['https://example.test/steal']], 'body' => 'discard']])))->beginAuthorization(), 'auth_challenge');
$jsonOnly = new class implements TransportContract {
    public function post(string $endpoint, array $headers, array $body): array
    {
        throw new RuntimeException('No request expected');
    }
};
failAiguanji(fn () => (new AiguanjiProvider($jsonOnly))->beginAuthorization(), 'unsupported_transport');

$state = $challenge['state'];
$authCredentials = ['username' => '13800000000'];
$answer = ['password' => ' synthetic-secret +& 密码 ', 'code' => '19A8'];
$authTransport = new AiguanjiFixtureTransport([['code' => 0, 'data' => ['uuid' => 'discard-uuid', 'token' => 'synthetic-new-token']], $profile]);
$authorized = (new AiguanjiProvider($authTransport))->authorize($authCredentials, $state, $answer);
checkAiguanji($authorized['status'] === 'authorized' && $authorized['credentials'] === ['username' => '13800000000', 'access_token' => 'synthetic-new-token'], 'permanent credentials contain only account and verified token after ordinary password authorization');
checkAiguanji($authorized['identity']['verified'] === true && $authorized['identity']['tenant_id'] === '42', 'normal login still verifies the authenticated profile and current company');
checkAiguanji(!preg_match('/password|device_id|discard-|19A8|temporary_token|companies/', json_encode($authorized)), 'temporary password/CAPTCHA/device/company data is absent from authorized result');
checkAiguanji($authTransport->calls[0]['endpoint'] === AiguanjiProvider::LOGIN_ENDPOINT && $authTransport->calls[0]['headers'] ===
    ['version' => AiguanjiProvider::CLIENT_VERSION, 'X-Client-Route' => '/login'], 'normal authorization uses only the observed login endpoint and route metadata');
checkAiguanji($authTransport->calls[0]['method'] === 'POST' && $authTransport->calls[0]['encoding'] === 'json', 'interactive login prefers the bounded raw request contract to preserve safe upstream failure explanations');
checkAiguanji($authTransport->calls[0]['data'] === ['mobile' => '13800000000', 'password' => $answer['password'], 'code' => '19A8',
    'device_id' => $state['device_id']], 'login sends ordinary password literally plus the human-entered CAPTCHA and integration identity');
checkAiguanji(count($authTransport->calls) === 2 && $authTransport->calls[1]['headers']['Authorization'] === 'synthetic-new-token', 'successful token is verified via exactly one profile read, not accepted blindly');
$otherProfile = $profile;
$otherProfile['data']['user']['mobile'] = '13900000000';
$otherProfile['data']['user']['company'] = ['id' => 77, 'name' => '另一合成公司'];
$other = (new AiguanjiProvider(new AiguanjiFixtureTransport([['code' => 0, 'data' => ['token' => 'synthetic-other-token']], $otherProfile])))
    ->authorize(['username' => '13900000000'], $state, $answer);
checkAiguanji($other['identity']['tenant_id'] === '77' && $other['credentials']['username'] === '13900000000', 'another account and company use the same adapter without fixed identifiers');
foreach ([null, '', [], str_repeat('x', 4097)] as $password) {
    $mock = new AiguanjiFixtureTransport([]);
    failAiguanji(fn () => (new AiguanjiProvider($mock))->authorize($authCredentials, $state, array_replace($answer, ['password' => $password])), 'invalid_credentials');
    checkAiguanji($mock->calls === [], 'invalid transient password never reaches upstream');
}
foreach ([null, '', [], '123', '12345', '12-4', "12\n4"] as $code) {
    $mock = new AiguanjiFixtureTransport([]);
    failAiguanji(fn () => (new AiguanjiProvider($mock))->authorize($authCredentials, $state, array_replace($answer, ['code' => $code])), 'invalid_captcha');
    checkAiguanji($mock->calls === [], 'invalid CAPTCHA input never reaches upstream');
}
foreach ([[], array_replace($state, ['device_id' => 'untrusted']), array_replace($state, ['stage' => 'skip-captcha']), array_replace($state, ['version' => 2])] as $badState) {
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([])))->authorize($authCredentials, $badState, $answer), 'invalid_challenge');
}
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([])))->authorize($authCredentials, $state, $answer + ['company_id' => 42]), 'invalid_challenge');
foreach ([['code' => 1, 'msg' => '验证码错误 discard-secret'], ['code' => 10, 'msg' => 'CAPTCHA expired discard-secret']] as $failure) {
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$failure])))->authorize($authCredentials, $state, $answer), 'invalid_captcha');
}
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([['code' => 1, 'msg' => '密码错误 discard-secret']])))->authorize($authCredentials, $state, $answer), 'invalid_credentials');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([['code' => 0, 'data' => ['access_token' => 'wrong-key']]])))->authorize($authCredentials, $state, $answer), 'schema_changed');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([['code' => 0, 'data' => ['token' => 'synthetic-token']], $otherProfile])))->authorize($authCredentials, $state, $answer), 'scope_mismatch');

$choices = ['code' => 3, 'data' => ['token' => 'synthetic-company-session', 'teams' => [
    ['id' => 42, 'name' => '合成公司一', 'balance' => 'discard-balance'], ['id' => 43, 'name' => '合成公司二', 'contact' => 'discard-contact']]]];
$companyProfile = $profile;
$companyProfile['data']['user']['company'] = ['id' => 43, 'name' => '合成公司二'];
$companyTransport = new AiguanjiFixtureTransport([$choices, ['code' => 0, 'data' => ['token' => 'synthetic-company-token']], $companyProfile]);
$companyProvider = new AiguanjiProvider($companyTransport);
$choose = $companyProvider->authorize($authCredentials, $state, $answer);
checkAiguanji($choose['status'] === 'choose_company' && $choose['public']['companies'] === [
    ['id' => '42', 'name' => '合成公司一'], ['id' => '43', 'name' => '合成公司二']], 'multi-company flow publishes only explicit safe candidate IDs and names');
checkAiguanji(!preg_match('/token|password|19A8|discard-|device_id/', json_encode($choose['public'])), 'public company choices contain no transient secrets or account objects');
checkAiguanji($choose['state']['password'] === $answer['password'] && $choose['state']['code'] === '19A8'
    && $choose['state']['stage'] === 'company', 'upstream-required temporary fields stay only in the encrypted server-state branch');
$chosen = $companyProvider->authorize($authCredentials, $choose['state'], ['company_id' => '43']);
checkAiguanji($chosen['status'] === 'authorized' && $chosen['identity']['tenant_id'] === '43', 'explicit candidate selection is rechecked against authenticated current company');
checkAiguanji($companyTransport->calls[1]['data'] === ['mobile' => '13800000000', 'password' => $answer['password'], 'code' => '19A8',
    'device_id' => $state['device_id'], 'company_id' => '43', 'token' => 'synthetic-company-session'], 'company continuation follows the observed same-form submission with temporary token');
foreach ([null, '', '44', '42.0', [], -1] as $id) {
    $mock = new AiguanjiFixtureTransport([]);
    failAiguanji(fn () => (new AiguanjiProvider($mock))->authorize($authCredentials, $choose['state'], ['company_id' => $id]), 'invalid_challenge');
    checkAiguanji($mock->calls === [], 'foreign or malformed company cannot trigger a login attempt');
}
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([])))->authorize(['username' => '13900000000'], $choose['state'], ['company_id' => '43']), 'scope_mismatch');
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([['code' => 0, 'data' => ['token' => 'synthetic-token']], $profile])))->authorize($authCredentials, $choose['state'], ['company_id' => '43']), 'scope_mismatch');
foreach ([null, [], [['id' => 42, 'name' => 'A'], ['id' => 42, 'name' => 'B']], [['company_id' => 42, 'company_name' => 'unverified-path']],
    [['id' => 42, 'name' => '']], [['id' => 42, 'name' => "name\ninvalid"]]] as $teams) {
    $bad = $choices;
    $bad['data']['teams'] = $teams;
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([$bad])))->authorize($authCredentials, $state, $answer), 'schema_changed');
}

// Upstream login sometimes uses a non-200 HTTP status for an ordinary business rejection.
function aiguanjiLoginHttp(int $status, array $response): array
{
    return ['status' => $status, 'headers' => ['Content-Type' => ['application/json']],
        'body' => json_encode($response, JSON_THROW_ON_ERROR)];
}
foreach ([400, 401, 403, 422, 500] as $status) {
    foreach ([['code' => 1, 'msg' => '验证码错误 discard-private'], ['code' => '1', 'msg' => 'CAPTCHA expired discard-private']] as $rejection) {
        $fixture = new AiguanjiFixtureTransport([aiguanjiLoginHttp($status, $rejection)]);
        failAiguanji(fn () => (new AiguanjiProvider($fixture))->authorize($authCredentials, $state, $answer), 'invalid_captcha');
        checkAiguanji(count($fixture->calls) === 1, 'non-200 CAPTCHA rejection is not retried and never invokes profile verification');
    }
    $fixture = new AiguanjiFixtureTransport([aiguanjiLoginHttp($status, ['code' => 1, 'msg' => '账号密码错误 discard-private'])]);
    failAiguanji(fn () => (new AiguanjiProvider($fixture))->authorize($authCredentials, $state, $answer), 'invalid_credentials');
    checkAiguanji(count($fixture->calls) === 1, 'non-200 password rejection is not retried');
    $fixture = new AiguanjiFixtureTransport([aiguanjiLoginHttp($status, ['code' => 0, 'data' => ['token' => 'discard-fake-token']]), $profile]);
    failAiguanji(fn () => (new AiguanjiProvider($fixture))->authorize($authCredentials, $state, $answer), 'http_error');
    checkAiguanji(count($fixture->calls) === 1, 'a token and code zero on non-200 never become authorized or reach profile');
    $fixture = new AiguanjiFixtureTransport([aiguanjiLoginHttp($status, ['code' => 1, 'msg' => 'discard-private-error'])]);
    failAiguanji(fn () => (new AiguanjiProvider($fixture))->authorize($authCredentials, $state, $answer), 'http_error');
    $rawChoice = (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiLoginHttp($status, $choices)])))
        ->authorize($authCredentials, $state, $answer);
    checkAiguanji($rawChoice['status'] === 'choose_company' && $rawChoice['public'] === $choose['public'], 'an explicit strict company challenge remains a challenge, not authorized, on supported failure statuses');
}
$fixture = new AiguanjiFixtureTransport([aiguanjiLoginHttp(429, $choices)]);
failAiguanji(fn () => (new AiguanjiProvider($fixture))->authorize($authCredentials, $state, $answer), 'rate_limited');
checkAiguanji(count($fixture->calls) === 1, 'rate limiting dominates other body contents and never automatically retries');
foreach ([201, 302, 307, 404, 502] as $status) {
    $fixture = new AiguanjiFixtureTransport([aiguanjiLoginHttp($status, ['code' => 0, 'data' => ['token' => 'discard-fake-token']])]);
    failAiguanji(fn () => (new AiguanjiProvider($fixture))->authorize($authCredentials, $state, $answer), 'http_error');
    checkAiguanji(count($fixture->calls) === 1, 'unknown HTTP status never silently authorizes or follows a redirect');
}
foreach ([['code' => 0, 'msg' => '验证码错误 discard-private'], ['msg' => '验证码错误 discard-private'],
    ['code' => false, 'data' => $choices['data']], ['code' => -1, 'data' => $choices['data']]] as $invalidFailure) {
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiLoginHttp(500, $invalidFailure)])))
        ->authorize($authCredentials, $state, $answer), 'http_error');
}
$badCompanyFailure = $choices;
$badCompanyFailure['data']['teams'] = [['id' => 42, 'name' => 'A'], ['id' => 42, 'name' => 'B']];
failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([aiguanjiLoginHttp(500, $badCompanyFailure)])))
    ->authorize($authCredentials, $state, $answer), 'schema_changed');
foreach (['<html>discard-upstream-private</html>', 'null', '[]', '{"code":', str_repeat('x', 131073)] as $bytes) {
    failAiguanji(fn () => (new AiguanjiProvider(new AiguanjiFixtureTransport([
        ['status' => 500, 'headers' => [], 'body' => $bytes]])))->authorize($authCredentials, $state, $answer), 'http_error');
}
$legacyTransport = new class([['code' => 0, 'data' => ['token' => 'synthetic-legacy-token']], $profile]) implements TransportContract {
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $endpoint, array $headers, array $body): array
    {
        $this->calls[] = compact('endpoint', 'headers', 'body');
        return array_shift($this->responses);
    }
};
$legacy = (new AiguanjiProvider($legacyTransport))->authorize($authCredentials, $state, $answer);
checkAiguanji($legacy['status'] === 'authorized' && count($legacyTransport->calls) === 2 &&
    $legacyTransport->calls[0]['endpoint'] === AiguanjiProvider::LOGIN_ENDPOINT, 'JSON-only legacy transports retain their strict HTTP-200 post contract');

echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'synthetic interactive authorization, profile, stock and independent snapshot contracts; no live requests or business data',
    'remote_calls' => 0, 'database_writes' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
