<?php
declare(strict_types=1);

// Pure contract regression: every ERP/HTTP response below is synthetic.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_lead\app\provider\{SoujikeProvider, ProviderException, TransportContract, GuzzleJsonTransport, ProviderRegistry};
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

final class SoujikeAuthMock implements TransportContract
{
    public array $calls = [];
    public function __construct(private array $responses) {}
    public function post(string $endpoint, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] array $body): array
    {
        $this->calls[] = compact('endpoint', 'headers', 'body');
        if (!$this->responses) throw new RuntimeException('Unexpected synthetic request');
        $next = array_shift($this->responses);
        if ($next instanceof Throwable) throw $next;
        if (!is_array($next)) throw new RuntimeException('Invalid synthetic response');
        return $next;
    }
}
$checks = 0;
function sjkAuthCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $GLOBALS['checks']++;
}
function sjkAuthFailure(callable $call, string $reason): ProviderException
{
    try { $call(); } catch (ProviderException $error) {
        sjkAuthCheck($error->reason() === $reason, 'safe expected reason ' . $reason);
        sjkAuthCheck($error->getPrevious() === null, 'no sensitive upstream cause is chained');
        sjkAuthCheck(!preg_match('/private-|passwords|tokenValue|account-secret|recovery-token/i', $error->getMessage()), 'error message contains only locally-authored safe information');
        return $error;
    }
    throw new RuntimeException('FAIL: expected rejection ' . $reason);
}
function sjkAuthLogin(string $store = '9007199254740993', string $token = 'synthetic-login-token'): array
{
    return ['code' => 200, 'data' => ['storeId' => $store, 'storeInfo' => ['storeName' => '合成门店', 'balance' => 'private-balance'],
        'tokenInfo' => ['tokenName' => 'recovery-token', 'tokenValue' => $token, 'privateExtra' => 'private-token-data'],
        'id' => 'private-account-secret', 'realName' => 'private-account-name', 'customerList' => ['private-customer']]];
}
function sjkAuthPage(array $records = [], int $page = 1, int $size = 100, ?int $total = null): array
{
    return ['code' => 200, 'data' => ['records' => $records, 'current' => $page, 'size' => $size, 'total' => $total ?? count($records)]];
}

$credentials = ['auth_mode' => 'password', 'username' => '18800000001', 'password' => ' synthetic-password +&= 中文 '];
$mock = new SoujikeAuthMock([sjkAuthLogin(), sjkAuthPage()]);
$provider = new SoujikeProvider($mock);
$login = $provider->login($credentials);
sjkAuthCheck(count($mock->calls) === 1 && $mock->calls[0]['endpoint'] === SoujikeProvider::LOGIN_ENDPOINT, 'password authorization uses the exact existing member-card login endpoint');
sjkAuthCheck($mock->calls[0]['headers'] === [], 'new normal login never forwards old authorization headers');
sjkAuthCheck($mock->calls[0]['body'] === ['openId' => '', 'phoneNumber' => '18800000001', 'passwords' => $credentials['password']], 'body exactly matches the reference contract and preserves password bytes');
sjkAuthCheck($login['access_token'] === 'synthetic-login-token' && $login['identity']['store_id'] === '9007199254740993', 'normal login returns canonical token and preserves a large store ID');
sjkAuthCheck($login['identity']['verified'] === true && $login['identity']['tenant_id'] === null && $login['identity']['store_name'] === '合成门店', 'authenticated store is proven without inventing tenant from account ID');
sjkAuthCheck(!str_contains(json_encode($login), 'private-') && !str_contains(json_encode($login), $credentials['password']), 'login DTO excludes raw account, customer and financial response fields');
$page = $provider->fetchPage(['access_token' => $login['access_token']], [], ['store_id' => '9007199254740993']);
sjkAuthCheck($page['rows'] === [] && $page['has_more'] === false, 'authenticated empty inventory is an actual complete empty result');
sjkAuthCheck(count($mock->calls) === 2 && $mock->calls[1]['endpoint'] === SoujikeProvider::STOCK_ENDPOINT, 'normal login is followed only by the known inventory endpoint');
sjkAuthCheck($mock->calls[1]['headers'] === ['recovery-token' => 'synthetic-login-token'] && $mock->calls[1]['body']['shopIds'] === [], 'derived token stays in fixed recovery header without substituting an authenticated store ID for a branch ID');
sjkAuthCheck($mock->calls[1]['body']['saleStatus'] === null && $mock->calls[1]['body']['isNew'] === null && $mock->calls[1]['body']['brandType'] === '' && $mock->calls[1]['body']['categoryIds'] === [], 'inventory is not silently narrowed by sale status, device newness, brand or category');

$wrongScope = new SoujikeAuthMock([sjkAuthLogin()]);
$wrongProvider = new SoujikeProvider($wrongScope);
$wrongLogin = $wrongProvider->login($credentials);
sjkAuthFailure(static fn () => $wrongProvider->fetchPage(['access_token' => $wrongLogin['access_token']], [], ['store_id' => 'another-store']), 'scope_mismatch');
sjkAuthCheck(count($wrongScope->calls) === 1, 'authenticated store mismatch is rejected before any stock request');

foreach ([['access_token' => 'synthetic-manual-token', 'auth_mode' => 'token'], ['token' => 'synthetic-manual-token']] as $manual) {
    $manualMock = new SoujikeAuthMock([sjkAuthPage()]);
    $manualProvider = new SoujikeProvider($manualMock);
    $manualLogin = $manualProvider->login($manual + ['store_id' => '36183']);
    sjkAuthCheck($manualMock->calls === [] && $manualLogin['identity']['verified'] === false, 'temporary token fallback never calls or fabricates password login');
    $manualProvider->fetchPage($manual + ['access_token' => $manualLogin['access_token']], [], ['store_id' => '36183']);
    sjkAuthCheck(count($manualMock->calls) === 1 && $manualMock->calls[0]['headers'] === ['recovery-token' => 'synthetic-manual-token'], 'manual token remains usable for current isolated testing');
}

foreach ([[], ['username' => '', 'password' => 'x'], ['username' => 'bad', 'password' => 'x'], ['username' => [], 'password' => 'x'],
    ['username' => '18800000001', 'password' => ''], ['username' => '18800000001', 'password' => []],
    ['username' => '18800000001', 'password' => str_repeat('x', 4097)]] as $invalid) {
    $bad = new SoujikeAuthMock([]);
    sjkAuthFailure(static fn () => (new SoujikeProvider($bad))->login(['auth_mode' => 'password'] + $invalid + ['access_token' => 'must-not-fallback']), 'invalid_credentials');
    sjkAuthCheck($bad->calls === [], 'invalid password form never silently reuses token or contacts ERP');
}
$bad = new SoujikeAuthMock([]);
sjkAuthFailure(static fn () => (new SoujikeProvider($bad))->login(['auth_mode' => 'unknown'] + $credentials), 'invalid_credentials');
sjkAuthCheck($bad->calls === [], 'unknown mode is rejected locally');
$bad = new SoujikeAuthMock([]);
sjkAuthFailure(static fn () => (new SoujikeProvider($bad))->login(['username' => '', 'password' => '', 'access_token' => 'must-not-fallback']), 'invalid_credentials');
sjkAuthCheck($bad->calls === [], 'present but empty password fields cannot silently fall back to an old token');

$responseCases = [
    ['code' => 401, 'msg' => 'private-expired-password'], ['code' => 403, 'msg' => 'private-blocked-password'],
    ['code' => 500, 'msg' => 'private-failed-password'], ['code' => 200, 'data' => 'private-broken-data'],
    ['code' => 200, 'data' => []], ['code' => 200, 'data' => ['tokenInfo' => []]],
];
foreach ($responseCases as $response) {
    $bad = new SoujikeAuthMock([$response]);
    sjkAuthFailure(static fn () => (new SoujikeProvider($bad))->login($credentials), in_array($response['code'], [401, 403], true) ? 'auth_required' : ($response['code'] === 200 ? 'schema_changed' : 'upstream_error'));
    sjkAuthCheck(count($bad->calls) === 1, 'failed normal login never retries or starts inventory reads');
}
foreach ([
    static function (array $r): array { $r['data']['tokenInfo']['tokenValue'] = "bad\r\nprivate-token"; return $r; },
    static function (array $r): array { $r['data']['tokenInfo']['tokenValue'] = ''; return $r; },
    static function (array $r): array { $r['data']['tokenInfo']['tokenValue'] = []; return $r; },
    static function (array $r): array { $r['data']['tokenInfo']['tokenName'] = 'Authorization'; return $r; },
    static function (array $r): array { $r['data']['tokenInfo']['tokenName'] = "recovery-token\r\nX-Private"; return $r; },
    static function (array $r): array { $r['data']['tokenInfo']['tokenName'] = []; return $r; },
    static function (array $r): array { unset($r['data']['storeId']); return $r; },
    static function (array $r): array { $r['data']['storeId'] = []; return $r; },
    static function (array $r): array { $r['data']['storeInfo'] = 'private-string'; return $r; },
    static function (array $r): array { $r['data']['storeInfo']['storeName'] = []; return $r; },
] as $mutate) {
    $bad = new SoujikeAuthMock([$mutate(sjkAuthLogin())]);
    sjkAuthFailure(static fn () => (new SoujikeProvider($bad))->login($credentials), 'schema_changed');
    sjkAuthCheck(count($bad->calls) === 1, 'malformed authentication contract stops without inventory request');
}
foreach ([null, '', 'Recovery-Token'] as $name) {
    $response = sjkAuthLogin();
    if ($name === null) unset($response['data']['tokenInfo']['tokenName']);
    else $response['data']['tokenInfo']['tokenName'] = $name;
    $safe = (new SoujikeProvider(new SoujikeAuthMock([$response])))->login($credentials);
    sjkAuthCheck($safe['access_token'] === 'synthetic-login-token', 'known fixed header default and case-insensitive equivalent are accepted');
}

$history = [];
$stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(sjkAuthLogin()))]));
$stack->push(Middleware::history($history));
$realTransport = new GuzzleJsonTransport(new Client(['handler' => $stack]));
$actualLogin = (new SoujikeProvider($realTransport))->login($credentials);
sjkAuthCheck($actualLogin['identity']['store_id'] === '9007199254740993' && count($history) === 1, 'fixed login endpoint passes the actual allowlisted Guzzle pipeline');
sjkAuthCheck((string)$history[0]['request']->getUri() === SoujikeProvider::LOGIN_ENDPOINT && $history[0]['request']->getMethod() === 'POST', 'transport never selects a caller-supplied login URL or method');
$sent = json_decode((string)$history[0]['request']->getBody(), true);
sjkAuthCheck($sent === ['openId' => '', 'phoneNumber' => '18800000001', 'passwords' => $credentials['password']], 'actual encoded login retains the reference payload without extra fields');
sjkAuthCheck($history[0]['options']['allow_redirects'] === false && $history[0]['options']['verify'] === true, 'login TLS remains verified and credentials are not redirected');

echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION,
    'scope' => 'Soujike normal password login, fixed transport and temporary token fallback; HTTP fully synthetic',
    'remote_calls' => 0, 'database_access' => false]), PHP_EOL;
