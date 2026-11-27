<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\{AiguanjiProvider,ProviderException,ProviderRegistry,RequestTransportContract};
use addon\hsx_lead\app\service\{AuthChallengeService,Context};
use addon\hsx_lead\app\support\{Cipher,Data};
use core\exception\CommonException;
use think\facade\{Cache,Db};

final class ChallengeMock implements RequestTransportContract
{
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $endpoint, array $headers, array $body): array
    {
        return $this->respond('POST', $endpoint, $headers, $body, 'json');
    }
    public function request(string $method, string $endpoint, array $headers, array $data, string $encoding): array
    {
        $response = $this->respond($method, $endpoint, $headers, $data, $encoding);
        // Login now consumes the actual HTTP envelope so non-200 validation failures
        // can be classified safely. Profile still uses the original JSON post contract.
        if ($method === 'POST' && $endpoint === AiguanjiProvider::LOGIN_ENDPOINT
            && $encoding === 'json' && !array_key_exists('status', $response)) {
            return loginHttpResponse($response);
        }
        return $response;
    }
    private function respond(string $method, string $endpoint, array $headers, array $data, string $encoding): array
    {
        challengeCheck(!Db::connect()->getPdo()->inTransaction(), 'upstream request is outside a DB transaction');
        $this->calls[] = compact('method', 'endpoint', 'headers', 'data', 'encoding');
        $response = array_shift($this->responses);
        if ($response instanceof Closure) $response = $response($this->calls[count($this->calls) - 1]);
        if ($response instanceof Throwable) throw $response;
        if (!is_array($response)) throw new RuntimeException('Unexpected synthetic auth request');
        return $response;
    }
}

$checks = 0;
$failed = false;
$pdo = null;
$schema = '';
function challengeCheck(bool $condition, string $description): void
{
    if (!$condition)throw new RuntimeException('FAIL '.$description);
    $GLOBALS['checks']++;
}
function challengeDenied(callable $action, string $description): Throwable
{
    try {
        $action();
    } catch (CommonException|ProviderException $error) {
        challengeCheck($error->getPrevious() === null, $description.' has no chained secret exception');
        challengeCheck(!str_contains($error->getMessage(), 'POISON_'), $description.' has a safe message');
        return $error;
    }
    throw new RuntimeException('Expected rejection: '.$description);
}
function captchaResponse(): array
{
    return ['status' => 200, 'headers' => ['Content-Type' => ['image/png']],
        'body' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jp1sAAAAASUVORK5CYII=', true)];
}
function profileResponse(int $company = 42): array
{
    return ['code' => 0, 'data' => ['user' => ['mobile' => '18800000001', 'company' => ['id' => $company, 'name' => '合成公司'],
        'password' => 'POISON_PROFILE_PASSWORD'], 'permission' => ['POISON_PERMISSION']]];
}
function loginResponse(): array
{
    return ['code' => 0, 'data' => ['token' => 'POISON_SYNTHETIC_ACCESS_TOKEN', 'uuid' => 'synthetic-uuid']];
}
function loginHttpResponse(array|string $body, int $status = 200): array
{
    return ['status' => $status, 'headers' => ['Content-Type' => ['application/json']],
        'body' => is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $body];
}
function companyResponse(): array
{
    return ['code' => 7, 'data' => ['token' => 'POISON_TEMPORARY_TOKEN', 'teams' => [
        ['id' => 42, 'name' => '合成公司甲', 'password' => 'POISON_COMPANY_PASSWORD'], ['id' => 43, 'name' => '合成公司乙']]]];
}
function challengeKey(string $token): string
{
    return 'hsx_lead.auth.'.hash('sha256', $token);
}
function clearChallengeRate(int $site, int $merchant, int $uid = 1): void
{
    Cache::delete('hsx_lead.auth.rate.'.hash('sha256', Data::json(['site' => $site, 'uid' => $uid, 'member_id' => 0, 'merchant_id' => $merchant, 'provider' => 'aiguanji'])));
}
function mutateChallenge(array $challenge, int $site, int $merchant, callable $edit): void
{
    $key = challengeKey($challenge['challenge_token']);
    $data = Cipher::decrypt(Cache::get($key), $site, -$merchant);
    $edit($data);
    Cache::set($key, Cipher::encrypt($data, $site, -$merchant), 180);
}

try {
    [$app,$pdo,$schema,$site] = leadBoot();
    $merchant = (int) Data::query('merchant', $site)->insertGetId(['site_id' => $site, 'name' => 'Synthetic auth merchant', 'status' => 1]);
    $other = (int) Data::query('merchant', $site)->insertGetId(['site_id' => $site, 'name' => 'Synthetic other merchant', 'status' => 1]);
    $cross = (int) Data::query('merchant', $site + 1)->insertGetId(['site_id' => $site + 1, 'name' => 'Synthetic cross-site merchant', 'status' => 1]);
    $connection = (int) Data::query('connection', $site)->insertGetId(['site_id' => $site, 'merchant_id' => $merchant, 'provider' => 'aiguanji',
        'config_version' => 1, 'expected_tenant_id' => '42', 'store_id' => '42', 'published_run_id' => 123]);
    Data::query('connection', $site)->where('id', $connection)->update(['credentials_cipher' => Cipher::encrypt(
        ['username' => 'synthetic-existing-account', 'access_token' => 'POISON_EXISTING_TOKEN'], $site, $connection)]);
    $original = Data::row('connection', $site, $connection);
    $credentials = ['username' => '18800000001'];
    $input = ['provider' => 'aiguanji', 'credentials' => $credentials];
    $answer = ['password' => ' POISON_SYNTHETIC_PASSWORD ', 'code' => 'Ab12'];
    $service = static fn (ChallengeMock $mock, ?Context $ctx = null): AuthChallengeService => new AuthChallengeService($ctx ?? new Context($site, 1), new ProviderRegistry($mock));

    $mock = new ChallengeMock([captchaResponse(), loginResponse(), profileResponse()]);
    $auth = $service($mock);
    $begin = $auth->begin($merchant, $input);
    challengeCheck($begin['status'] === 'challenge' && preg_match('/^[a-f0-9]{64}$/D', $begin['challenge_token']) === 1, 'captcha starts with opaque random proof');
    challengeCheck(str_starts_with($begin['captcha_image'], 'data:image/png;base64,') && $begin['expires_at'] <= time() + 180, 'safe raster image and bounded expiry are public');
    $cached = Cache::get(challengeKey($begin['challenge_token']));
    challengeCheck(is_string($cached) && !str_contains($cached, 'device_id') && !str_contains($cached, '18800000001'), 'challenge private state is encrypted, not raw identity or device state');
    $private = Cipher::decrypt($cached, $site, -$merchant);
    challengeCheck($private['status'] === 'ready' && $private['uid'] === 1 && $private['merchant_id'] === $merchant, 'challenge belongs to current operator and merchant');
    $done = $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]);
    challengeCheck($done === ['status' => 'authorized', 'credentials' => ['username' => '18800000001', 'access_token' => 'POISON_SYNTHETIC_ACCESS_TOKEN']],
        'authorized server result has only identity and session token, no transient password or context');
    challengeCheck($mock->calls[1]['data']['password'] === $answer['password'], 'password whitespace is preserved in the normal login request');
    challengeCheck(Cache::get(challengeKey($begin['challenge_token'])) === null, 'successful challenge is consumed');
    challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'completed challenge replay');
    challengeCheck(count($mock->calls) === 3 && Data::row('connection', $site, $connection) === $original, 'authorization never mutates the old connection or repeats consumed login');

    // A wrong caller must not consume the rightful operator's challenge.
    clearChallengeRate($site, $merchant);
    $mock = new ChallengeMock([captchaResponse(), loginResponse(), profileResponse()]);
    $auth = $service($mock);
    $begin = $auth->begin($merchant, $input);
    challengeDenied(fn () => $service($mock, new Context($site, 2))->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'other operator');
    challengeDenied(fn () => $auth->authorize($other, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'other merchant');
    challengeDenied(fn () => $service($mock, new Context($site + 1, 1))->authorize($cross, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'other site');
    challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', ['username' => '18800000002'], $answer + ['challenge_token' => $begin['challenge_token']]), 'other upstream account');
    challengeDenied(fn () => $auth->authorize($merchant, 'wanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'noninteractive provider');
    challengeCheck(count($mock->calls) === 1, 'scope failures cause no login call');
    challengeCheck($auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']])['status'] === 'authorized', 'valid caller may still consume a challenge after mismatched callers');
    challengeDenied(fn () => $service(new ChallengeMock([]), new Context($site, 0, 101))->begin($merchant, $input), 'member cannot begin operator ERP authorization');

    foreach (['expired', 'claimed', 'purpose', 'cipher'] as $case) {
        clearChallengeRate($site, $merchant);
        $mock = new ChallengeMock([captchaResponse()]);
        $auth = $service($mock);
        $begin = $auth->begin($merchant, $input);
        if ($case === 'cipher') Cache::set(challengeKey($begin['challenge_token']), 'POISON_INVALID_CIPHER', 180);
        else mutateChallenge($begin, $site, $merchant, static function (array &$state) use ($case) {
            if ($case === 'expired')$state['expires_at'] = time() - 1;
            if ($case === 'claimed') {
                $state['status'] = 'claimed';
                $state['claim_nonce'] = 'already-claimed';
            }
            if ($case === 'purpose')$state['purpose'] = 'another-cache-purpose';
        });
        challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), $case.' challenge');
        challengeCheck(count($mock->calls) === 1, 'invalid cached challenge never reaches upstream');
    }

    // Claim is committed before HTTP: a simultaneous submission is refused.
    clearChallengeRate($site, $merchant);
    $inflight = null;
    $inflightToken = '';
    $mock = new ChallengeMock([captchaResponse(), function () use (&$inflight, &$inflightToken, $merchant, $credentials, $answer) {
        challengeDenied(fn () => $inflight->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $inflightToken]), 'simultaneous submitted challenge');
        return loginResponse();
    }, profileResponse()]);
    $inflight = $service($mock);
    $begin = $inflight->begin($merchant, $input);
    $inflightToken = $begin['challenge_token'];
    challengeCheck($inflight->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $inflightToken])['status'] === 'authorized', 'first claim completes while concurrent replay is blocked');
    challengeCheck(count($mock->calls) === 3, 'simultaneous replay cannot send a second password request');

    foreach (['missing_code', 'early_company', 'bad_captcha', 'unexpected_exception'] as $case) {
        clearChallengeRate($site, $merchant);
        $responses = [captchaResponse()];
        if ($case === 'bad_captcha')$responses[] = ['code' => 1, 'msg' => '验证码不正确 POISON_PASSWORD'];
        if ($case === 'unexpected_exception')$responses[] = new RuntimeException('POISON_PRIVATE_EXCEPTION');
        $mock = new ChallengeMock($responses);
        $auth = $service($mock);
        $begin = $auth->begin($merchant, $input);
        $attempt = $answer;
        if ($case === 'missing_code')unset($attempt['code']);
        if ($case === 'early_company')$attempt['company_id'] = '42';
        challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, $attempt + ['challenge_token' => $begin['challenge_token']]), $case);
        challengeCheck(Cache::get(challengeKey($begin['challenge_token'])) === null, 'failed authorized attempt consumes its challenge');
        challengeCheck(Data::row('connection', $site, $connection) === $original, 'failed authorization preserves old connection');
    }

    // A real provider may report a wrong CAPTCHA using HTTP 500. Preserve that
    // business failure only for safe classification, never as a successful login.
    foreach ([
        'invalid_captcha' => loginHttpResponse(['code' => 1003, 'msg' => '验证码不正确 POISON_PRIVATE_MESSAGE', 'data' => ['token' => 'POISON_FAILURE_TOKEN']], 500),
        'invalid_credentials' => loginHttpResponse(['code' => 1002, 'msg' => '密码不正确 POISON_PRIVATE_MESSAGE'], 500),
        'http_error_success' => loginHttpResponse(['code' => 0, 'data' => ['token' => 'POISON_NON200_SUCCESS_TOKEN']], 500),
        'http_error_body' => loginHttpResponse('<pre>POISON_PRIVATE_UPSTREAM_BODY</pre>', 500),
    ] as $case => $failure) {
        clearChallengeRate($site, $merchant);
        $mock = new ChallengeMock([captchaResponse(), $failure]);
        $auth = $service($mock);
        $begin = $auth->begin($merchant, $input);
        $rejected = challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'HTTP 500 '.$case);
        $reason = str_starts_with($case, 'http_error') ? 'http_error' : $case;
        challengeCheck($rejected instanceof ProviderException && $rejected->reason() === $reason, 'non-200 login failure has the explicit safe classification');
        challengeCheck(Cache::get(challengeKey($begin['challenge_token'])) === null, 'non-200 failure consumes the one-time challenge');
        challengeCheck(Data::row('connection', $site, $connection) === $original, 'non-200 failure cannot replace the old connection');
        challengeCheck(count($mock->calls) === 2, 'non-200 failure sends one login and never attempts profile verification');
        challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), 'replay after HTTP 500 '.$case);
        challengeCheck(count($mock->calls) === 2, 'failed non-200 login is never silently retried');
    }

    // Multi-company state and previous password are encrypted, never reflected.
    clearChallengeRate($site, $merchant);
    $mock = new ChallengeMock([captchaResponse(), companyResponse(), loginResponse(), profileResponse()]);
    $auth = $service($mock);
    $begin = $auth->begin($merchant, $input);
    $choose = $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]);
    challengeCheck($choose['status'] === 'choose_company' && $choose['public']['challenge_token'] !== $begin['challenge_token'], 'company selection rotates its single-use challenge');
    challengeCheck($choose['public']['companies'] === [['id' => '42', 'name' => '合成公司甲'], ['id' => '43', 'name' => '合成公司乙']], 'only safe provider-returned company options are visible');
    challengeCheck(!str_contains(Data::json($choose), 'POISON_') && !str_contains(Data::json($choose), 'password') && !str_contains(Data::json($choose), 'device_id'), 'company selection response has no temporary secrets');
    challengeCheck(Cache::get(challengeKey($begin['challenge_token'])) === null, 'first company challenge is consumed before new one is returned');
    $next = $choose['public']['challenge_token'];
    $cache = Cache::get(challengeKey($next));
    challengeCheck(is_string($cache) && !str_contains($cache, 'POISON_'), 'company temporary token and password are encrypted');
    $state = Cipher::decrypt($cache, $site, -$merchant);
    challengeCheck($state['private_state']['password'] === $answer['password'] && $state['expires_at'] <= $begin['expires_at'], 'company temporary state is bounded to the original expiry');
    $done = $auth->authorize($merchant, 'aiguanji', $credentials, ['challenge_token' => $next, 'company_id' => '42', 'token' => 'POISON_FRONTEND_TOKEN']);
    challengeCheck($done['status'] === 'authorized' && !isset($done['credentials']['password']), 'company authorization yields only persistent credential fields');
    challengeCheck($mock->calls[2]['data']['company_id'] === '42' && $mock->calls[2]['data']['token'] === 'POISON_TEMPORARY_TOKEN', 'second login uses the allowed company and server-owned temporary token');
    challengeCheck(Cache::get(challengeKey($next)) === null && count($mock->calls) === 4, 'company challenge is consumed without duplicate login');

    foreach (['unlisted_company', 'conflicting_company'] as $case) {
        clearChallengeRate($site, $merchant);
        $responses = [captchaResponse(), companyResponse()];
        if ($case === 'conflicting_company')$responses = [...$responses, loginResponse(), profileResponse(43)];
        $mock = new ChallengeMock($responses);
        $auth = $service($mock);
        $begin = $auth->begin($merchant, $input);
        $choose = $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]);
        $next = $choose['public']['challenge_token'];
        challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, ['challenge_token' => $next, 'company_id' => $case === 'unlisted_company' ? '999' : '42']), $case);
        challengeCheck(Cache::get(challengeKey($next)) === null && Data::row('connection', $site, $connection) === $original, 'bad company cannot save credentials or retain reusable proof');
        if ($case === 'unlisted_company')challengeCheck(count($mock->calls) === 2, 'unknown company is rejected before upstream submission');
    }

    // A changed revision or paused merchant cannot receive an old in-flight login result.
    foreach (['revision_before', 'revision_during', 'pause_during'] as $case) {
        clearChallengeRate($site, $merchant);
        $responses = [captchaResponse()];
        if ($case !== 'revision_before')$responses = [...$responses, function () use ($site, $connection, $merchant, $case) {
            if ($case === 'revision_during')Data::query('connection', $site)->where('id', $connection)->update(['config_version' => 2]);
            else Data::query('merchant', $site)->where('id', $merchant)->update(['status' => 0]);
            return loginResponse();
        }, profileResponse()];
        $mock = new ChallengeMock($responses);
        $auth = $service($mock);
        $begin = $auth->begin($merchant, $input);
        if ($case === 'revision_before')Data::query('connection', $site)->where('id', $connection)->update(['config_version' => 2]);
        challengeDenied(fn () => $auth->authorize($merchant, 'aiguanji', $credentials, $answer + ['challenge_token' => $begin['challenge_token']]), $case);
        challengeCheck(Data::row('connection', $site, $connection)['credentials_cipher'] === $original['credentials_cipher'], 'stale in-flight result never replaces existing credentials');
        if ($case === 'revision_before')challengeCheck(count($mock->calls) === 1, 'stale configuration is rejected before login');
        Data::query('connection', $site)->where('id', $connection)->update(['config_version' => 1]);
        Data::query('merchant', $site)->where('id', $merchant)->update(['status' => 1]);
    }

    clearChallengeRate($site, $merchant);
    $mock = new ChallengeMock(array_fill(0, 6, captchaResponse()));
    $auth = $service($mock);
    for ($i = 0;$i < 5;$i++)$auth->begin($merchant, $input);
    challengeDenied(fn () => $auth->begin($merchant, $input), 'captcha start rate limit');
    challengeCheck(count($mock->calls) === 5, 'rate limit runs before upstream captcha request');
    challengeCheck(Data::row('connection', $site, $connection) === $original, 'all challenges preserve the existing formal ERP connection');
    leadAssertIsolated($schema);
    echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'interactive auth challenge encryption, replay, scope, company, revision and no long DB HTTP transaction',
        'remote_calls' => 0, 'database' => 'owned random localhost schema', 'existing_business_rows_read' => 0]),PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    $message = $error instanceof PDOException ? 'isolated MySQL SQLSTATE '.$error->getCode() : $error->getMessage();
    fwrite(STDERR, 'FAIL '.get_class($error).': '.$message.' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema !== '') {
        try {
            leadCleanup($pdo, $schema);
        } catch (Throwable $error) {
            $failed = true;
            fwrite(STDERR, "FAIL owned test database cleanup requires attention\n");
        }
    }
}
exit($failed ? 1 : 0);
