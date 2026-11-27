<?php
declare(strict_types=1);

// Pure local request-capability regression. All HTTP is handled by Guzzle MockHandler.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\{GuzzleJsonTransport, ProviderException, RequestTransportContract, TransportContract};
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};

$checks = 0;
function requestCheck(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    $GLOBALS['checks']++;
}
function requestRejected(callable $action, string $reason): ProviderException
{
    try {
        $action();
    } catch (ProviderException $error) {
        requestCheck($error->reason() === $reason, 'explicit safe reason: '.$reason);
        requestCheck($error->getPrevious() === null, 'unsafe HTTP exception is not chained');
        requestCheck(!str_contains($error->getMessage(), 'synthetic-secret'), 'error contains no request or response secret');
        return $error;
    }
    throw new RuntimeException('FAIL: expected safe rejection '.$reason);
}
function requestTransport(array $responses, array &$history): GuzzleJsonTransport
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new GuzzleJsonTransport(new Client(['handler' => $stack]));
}

$jxLogin = 'https://jx2.guanql.cn/admin/login/in';
$jxIdentity = 'https://jx2.guanql.cn/admin';
$jxStock = 'https://jx2.guanql.cn/admin/serial-follow/get-list';
$jxCatalog = 'https://jx2.guanql.cn/mall/goods/get-list';
$jxListed = 'https://jx2.guanql.cn/mall/imei-goods/get-list';
$aiProfile = 'https://api.aiguanji.com/api/v1/index/index';
$aiStock = 'https://api.aiguanji.com/api/v1/storage/product/index';
$history = [];
$http = requestTransport([
    new Response(200, ['Set-Cookie' => ['gql_repair_token=synthetic-secret-cookie; Path=/; HttpOnly']], '{"code":200,"data":[]}'),
    new Response(200, ['Content-Type' => 'text/html'], '<select id="base_shop_name"><option value="42" selected>合成门店</option></select>'),
    new Response(200, [], '{"code":200,"data":{"total":0,"list":[]}}'),
    new Response(200, [], '{"code":0,"data":{"user":{"id":9}}}'),
    new Response(200, [], '{"code":0,"data":{"total":0,"data":[]}}'),
], $history);
requestCheck($http instanceof RequestTransportContract && $http instanceof TransportContract, 'optional capability preserves old JSON transport type');

$form = ['phone' => '18800000000', 'password' => ' synthetic-secret +&= 密码 ', 'login_type' => 'admin'];
$login = $http->request('POST', $jxLogin, ['X-Requested-With' => 'XMLHttpRequest'], $form, 'form');
requestCheck($login['status'] === 200 && str_contains($login['body'], '"code":200'), 'raw request returns status and original response body');
requestCheck($login['headers']['Set-Cookie'][0] === 'gql_repair_token=synthetic-secret-cookie; Path=/; HttpOnly', 'session cookie headers stay available in memory for adapter parsing');
$request = $history[0]['request'];
$decoded = [];
parse_str((string) $request->getBody(), $decoded);
requestCheck($request->getMethod() === 'POST' && (string) $request->getUri() === $jxLogin, 'form method and target are exact');
requestCheck($decoded === $form, 'form preserves literal password whitespace and special characters');
requestCheck(str_starts_with($request->getHeaderLine('Content-Type'), 'application/x-www-form-urlencoded'), 'form request uses form encoding, not JSON');

$identity = $http->request('GET', $jxIdentity, ['Accept' => 'text/html', 'Cookie' => 'gql_repair_token=synthetic-secret-cookie'], [], 'query');
requestCheck(str_contains($identity['body'], '合成门店'), 'HTML response is not incorrectly JSON-decoded');
requestCheck($history[1]['request']->getHeaderLine('Accept') === 'text/html', 'identity accepts ordinary HTML');
requestCheck($history[1]['request']->getHeaderLine('Cookie') === 'gql_repair_token=synthetic-secret-cookie', 'only explicitly supplied session header is forwarded');
requestCheck(!$history[1]['request']->hasHeader('Content-Type') && (string) $history[1]['request']->getBody() === '', 'GET identity has no JSON body');

$query = ['statusIn' => 0, 'store' => 0, 'imei' => '', 'limit' => 100, 'offset' => 100, 'time_start' => '1970-01-01', 'time_end' => '2099-12-31'];
$http->request('GET', $jxStock, ['Cookie' => 'gql_repair_token=synthetic-secret-cookie'], $query, 'query');
$seenQuery = [];
parse_str($history[2]['request']->getUri()->getQuery(), $seenQuery);
requestCheck($history[2]['request']->getMethod() === 'GET' && $history[2]['request']->getUri()->getPath() === '/admin/serial-follow/get-list', 'inventory remains GET at fixed path');
requestCheck($seenQuery === array_map('strval', $query), 'query keeps zero-valued state/store and empty filters');
requestCheck((string) $history[2]['request']->getBody() === '', 'GET query does not transmit inventory filters in a request body');

$profile = $http->post($aiProfile, ['Authorization' => 'synthetic-secret-token', 'version' => '2026102201'], []);
requestCheck($profile['code'] === 0 && $profile['data']['user']['id'] === 9, 'new authenticated profile uses existing JSON post contract');
requestCheck((string) $history[3]['request']->getBody() === '{}', 'verified AI profile sends an empty JSON object, not a JSON list');
requestCheck($history[3]['request']->getHeaderLine('Authorization') === 'synthetic-secret-token', 'raw-token authorization is not silently converted to Bearer');
$http->post($aiStock, ['Authorization' => 'synthetic-secret-token'], ['status' => ['55'], 'page' => 1, 'limit' => 20]);
requestCheck(json_decode((string) $history[4]['request']->getBody(), true)['status'] === ['55'], 'listed-only JSON filter remains exact');
requestCheck(count($history) === 5, 'no implicit extra HTTP call or retry');
foreach ($history as $entry) {
    $options = $entry['options'];
    requestCheck($options['verify'] === true && $options['allow_redirects'] === false, 'TLS validation enforced and redirects disabled');
    requestCheck($options['cookies'] === false && $options['debug'] === false, 'no global cookie jar or debug output');
    requestCheck($options['http_errors'] === false && $options['stream'] === true, 'bounded stream reader handles all response states');
    requestCheck($options['timeout'] === 30 && $options['read_timeout'] === 30 && $options['connect_timeout'] === 10, 'connect/read/overall timeouts remain bounded');
}

foreach ([
    ['POST', $jxIdentity, 'json'], ['POST', $jxStock, 'form'], ['GET', $jxLogin, 'query'],
    ['POST', $jxLogin, 'json'], ['GET', $aiProfile, 'query'], ['PUT', $aiStock, 'json'],
    ['POST', $aiStock, 'form'], ['post', $aiStock, 'json'], ['POST', $aiStock, 'multipart'],
    ['GET', 'http://jx2.guanql.cn/admin', 'query'], ['GET', $jxIdentity.'/', 'query'],
    ['GET', $jxIdentity.'?url=https://example.test', 'query'], ['GET', $jxIdentity.'#fragment', 'query'],
    ['GET', 'https://example.test/admin', 'query'], ['GET', 'https://jx2.guanql.cn:443/admin', 'query'],
    ['GET', 'https://user:synthetic-secret@jx2.guanql.cn/admin', 'query'],
] as [$method, $endpoint, $encoding]) {
    requestRejected(fn () => $http->request($method, $endpoint, ['Authorization' => 'synthetic-secret-token'], [], $encoding), 'forbidden_endpoint');
}
requestCheck(count($history) === 5, 'disallowed endpoint/method/encoding never reaches HTTP handler');
foreach ([['Authorization' => "synthetic-secret\r\nInjected: true"], ["Cookie\nBad" => 'synthetic-secret'], ['Cookie' => ['synthetic-secret']], [0 => 'synthetic-secret']] as $headers) {
    requestRejected(fn () => $http->request('GET', $jxIdentity, $headers, [], 'query'), 'invalid_credentials');
}
requestCheck(count($history) === 5, 'invalid request headers are rejected before HTTP');

$mallHistory = [];
$mall = requestTransport([
    new Response(200, [], '{"code":200,"data":{"total":0,"list":[]}}'),
    new Response(200, [], '{"code":200,"data":{"total":0,"list":[]}}'),
], $mallHistory);
$mallQueries = [
    [$jxCatalog, ['limit' => 100, 'offset' => 0, 'goods_number' => '', 'goods_bar_code' => '', 'goods_name' => '',
        'category_id' => 0, 'brand_id' => 0, 'goods_id' => 0, 'fineness' => 0, 'fineness2' => '', 'imei' => '',
        'status' => 1, 'time_start' => '', 'time_end' => '', 'zero_stock' => 0, 'sort_name' => 'sort', 'sort_val' => 'asc']],
    [$jxListed, ['limit' => 100, 'offset' => 100, 'goods_number' => '', 'goods_bar_code' => '', 'goods_name' => '',
        'goods_id' => 0, 'category_id' => 0, 'status' => 1, 'time_start' => '', 'time_end' => '', 'imei' => '',
        'fineness' => 0, 'fineness2' => '']],
];
foreach ($mallQueries as $index => [$endpoint, $params]) {
    $result = $mall->request('GET', $endpoint, ['Cookie' => 'gql_repair_token=synthetic-secret-cookie'], $params, 'query');
    $actual = $mallHistory[$index]['request'];
    $seen = [];
    parse_str($actual->getUri()->getQuery(), $seen);
    requestCheck($result['status'] === 200 && $actual->getMethod() === 'GET', 'mall index is a single read-only GET');
    requestCheck((string) $actual->getUri()->withQuery('') === $endpoint, 'mall index keeps its exact allowlisted endpoint');
    requestCheck($seen === array_map('strval', $params), 'mall query preserves exact caller parameters including zero filters and offsets');
    requestCheck((string) $actual->getBody() === '', 'mall GET never turns filters into a body');
    requestCheck(!str_contains((string) $actual->getUri(), 'synthetic-secret'), 'mall session is not copied into URL parameters');
    foreach ([['POST', 'json'], ['POST', 'form'], ['GET', 'json'], ['PUT', 'query'], ['DELETE', 'query']] as [$method, $encoding]) {
        requestRejected(fn () => $mall->request($method, $endpoint, [], [], $encoding), 'forbidden_endpoint');
    }
}
requestCheck(count($mallHistory) === 2, 'mall methods other than the fixed GET/query contract never reach HTTP');

foreach ([401 => 'auth_required', 403 => 'auth_required', 301 => 'http_error', 302 => 'http_error', 307 => 'http_error', 500 => 'http_error'] as $status => $reason) {
    $failedHistory = [];
    $failed = requestTransport([new Response($status, ['Location' => 'https://example.test/steal'], 'synthetic-secret-response')], $failedHistory);
    requestRejected(fn () => $failed->request('GET', $jxIdentity, ['Cookie' => 'synthetic-secret-cookie'], [], 'query'), $reason);
    requestCheck(count($failedHistory) === 1, 'failure does not redirect or replay credentials');
}
$networkHistory = [];
$network = requestTransport([new ConnectException('synthetic-secret-password', new Request('GET', $jxIdentity))], $networkHistory);
requestRejected(fn () => $network->request('GET', $jxIdentity, [], [], 'query'), 'http_error');
requestCheck(count($networkHistory) === 1, 'network exception is not retried');

$largeHistory = [];
$large = requestTransport([new Response(200, [], str_repeat('x', 20 * 1024 * 1024 + 1))], $largeHistory);
requestRejected(fn () => $large->request('GET', $jxIdentity, [], [], 'query'), 'response_too_large');
$exactHistory = [];
$exact = requestTransport([new Response(200, [], str_repeat('x', 20 * 1024 * 1024))], $exactHistory);
requestCheck(strlen($exact->request('GET', $jxIdentity, [], [], 'query')['body']) === 20 * 1024 * 1024, 'exact safe response size is accepted');

$legacyHistory = [];
$legacy = requestTransport([new Response(200, [], '{"code":200}')], $legacyHistory);
$legacy->post('https://lite.wanjisaas.com/lite-api/erp/stock/selectMachineDetailV2', [], []);
requestCheck((string) $legacyHistory[0]['request']->getBody() === '[]', 'profile object handling does not rewrite older provider payloads');

$authHistory = [];
$captchaEndpoint = 'https://api.aiguanji.com/api/v1/common/captcha/index';
$authEndpoint = 'https://api.aiguanji.com/api/v1/index/login';
$authHttp = requestTransport([
    new Response(200, ['Content-Type' => 'image/jpeg'], "\xff\xd8\xffsynthetic-image"),
    new Response(200, [], '{"code":0,"data":{"token":"synthetic-secret-token","uuid":"synthetic"}}'),
], $authHistory);
$captchaRaw = $authHttp->request('GET', $captchaEndpoint, [], ['v' => '0.1234'], 'query');
requestCheck($captchaRaw['body'] === "\xff\xd8\xffsynthetic-image" && $captchaRaw['headers']['Content-Type'] === ['image/jpeg'],
    'captcha returns bounded raster bytes and MIME without JSON decoding');
requestCheck($authHistory[0]['request']->getMethod() === 'GET' && $authHistory[0]['request']->getUri()->getQuery() === 'v=0.1234'
    && !$authHistory[0]['request']->hasHeader('Authorization'), 'captcha uses only the fixed public challenge endpoint');
$normalAuth = ['mobile' => '18800000001', 'password' => ' synthetic-secret +&= 密码 ', 'code' => 'Ab12', 'device_id' => str_repeat('a', 32)];
$authHttp->post($authEndpoint, ['version' => '2026102201', 'X-Client-Route' => '/login'], $normalAuth);
requestCheck(json_decode((string) $authHistory[1]['request']->getBody(), true) === $normalAuth,
    'normal password and captcha are transmitted exactly as JSON, without hashing or trimming secrets');
requestCheck(!$authHistory[1]['request']->hasHeader('Authorization') && $authHistory[1]['options']['allow_redirects'] === false
    && $authHistory[1]['options']['verify'] === true, 'normal login does not replay a saved account token or weaken TLS');
foreach ([['POST', $captchaEndpoint, 'json'], ['GET', $captchaEndpoint, 'json'], ['GET', $authEndpoint, 'query'],
    ['POST', $authEndpoint, 'form'], ['PUT', $authEndpoint, 'json']] as [$method,$endpoint,$encoding]) {
    requestRejected(fn () => $authHttp->request($method, $endpoint, [], [], $encoding), 'forbidden_endpoint');
}
requestCheck(count($authHistory) === 2, 'challenge/login do not add arbitrary-method access or automatic retries');

foreach ([400, 401, 403, 422, 429, 500] as $status) {
    $errorHistory = [];
    $rawAuth = requestTransport([new Response($status, [], '{"code":1,"msg":"验证码错误 synthetic-secret"}')], $errorHistory);
    $raw = $rawAuth->request('POST', $authEndpoint, [], ['code' => 'synthetic'], 'json');
    requestCheck($raw['status'] === $status && str_contains($raw['body'], '验证码错误'),
        'only the fixed raw login endpoint preserves a bounded business-validation error');
    requestCheck(count($errorHistory) === 1 && $errorHistory[0]['options']['allow_redirects'] === false,
        'raw auth failure is not followed or retried');
    $legacyErrorHistory = [];
    $legacyAuth = requestTransport([new Response($status, [], '{"code":0,"data":{"token":"synthetic-secret"}}')], $legacyErrorHistory);
    requestRejected(fn () => $legacyAuth->post($authEndpoint, [], []), in_array($status, [401, 403], true) ? 'auth_required' : 'http_error');
}
$redirectAuthHistory = [];
$redirectAuth = requestTransport([new Response(302, ['Location' => 'https://example.test/steal'], 'synthetic-secret')], $redirectAuthHistory);
requestRejected(fn () => $redirectAuth->request('POST', $authEndpoint, [], [], 'json'), 'http_error');
requestCheck(count($redirectAuthHistory) === 1, 'special auth validation handling never permits redirects');

echo 'PASS '.$checks." request transport checks; synthetic HTTP only, no network/database/ERP mutations\n";
