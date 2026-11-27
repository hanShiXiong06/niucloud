<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
ini_set('zend.exception_ignore_args', '0');
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\auth\CaptchaRecognizerContract;
use addon\hsx_lead\app\service\{CaptchaConfigService,Context};
use addon\hsx_lead\app\support\{Cipher,Data};
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\{Cache,Db};

$checks = 0;
$failed = false;
$pdo = null;
$schema = '';
function captchaConfigCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: '.$label);
    $GLOBALS['checks']++;
}
function captchaConfigDenied(callable $action, string $label): void
{
    try {
        $action();
    } catch (CommonException $error) {
        captchaConfigCheck(true, $label);
        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}

/** Child processes receive only this owned random schema via private stdin, never command arguments/files. */
function captchaConfigChild(): void
{
    try {
        $payload = json_decode(stream_get_contents(STDIN, 131072), true, 32, JSON_THROW_ON_ERROR);
        $schema = $payload['schema'] ?? '';
        $database = $payload['database'] ?? [];
        $connection = $database['connections']['mysql'] ?? [];
        if (!is_string($schema) || preg_match('/^hsx_lead_test_[a-f0-9]{16}$/D', $schema) !== 1
            || ($database['default'] ?? '') !== 'mysql' || ($connection['hostname'] ?? '') !== '127.0.0.1'
            || ($connection['database'] ?? '') !== $schema || ($connection['prefix'] ?? '') !== 'lt_'
            || !empty($connection['deploy']) || !is_string($payload['auth_key'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $payload['auth_key']) !== 1) throw new RuntimeException('Unsafe child configuration');
        $root = dirname(__DIR__, 3);
        $app = new think\App($root);
        require_once $app->getThinkPath().'helper.php';
        require_once $root.'/app/common.php';
        $app->config->set($database, 'database');
        $app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => sys_get_temp_dir().'/'.$schema.'/cache/']]], 'cache');
        $app->env->set('app.auth_key', $payload['auth_key']);
        $app->instance('request', new app\Request());
        request()->siteId((int) $payload['site']);
        request()->uid(1);
        $manager = new think\DbManager();
        $manager->setConfig($database);
        $app->instance('think\DbManager', $manager);
        $GLOBALS['hsx_lead_test_owned_schema'] = $schema;
        leadAssertIsolated($schema);
        try {
            (new CaptchaConfigService(new Context((int) $payload['site'], 1)))->reserveAttempt((string) $payload['revision']);
            echo json_encode(['reserved' => true]),PHP_EOL;
        } catch (CommonException $error) {
            echo json_encode(['reserved' => false]),PHP_EOL;
        }
        // Only the parent created this database; children must not drop it.
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, "FAIL isolated budget child\n");
        exit(2);
    }
}
if (in_array('--reserve-child', $argv, true)) captchaConfigChild();

final class CaptchaConfigMock implements CaptchaRecognizerContract
{
    public int $balances = 0;
    public int $creates = 0;
    public int $results = 0;
    public string $amount = '12.345678';
    public bool $throws = false;
    public ?Closure $onBalance = null;
    public function balance(#[SensitiveParameter] string $apiKey): string
    {
        $this->balances++;
        if ($this->onBalance) ($this->onBalance)();
        if ($this->throws) throw new RuntimeException('synthetic-private-upstream-key-'.$apiKey);
        return $this->amount;
    }
    public function create(#[SensitiveParameter] string $apiKey, #[SensitiveParameter] string $dataUri, int $length = 4): string
    {
        $this->creates++;
        throw new RuntimeException('Paid recognition forbidden in configuration test');
    }
    public function result(#[SensitiveParameter] string $apiKey, string $taskId, int $length = 4): array
    {
        $this->results++;
        throw new RuntimeException('Recognition polling forbidden in configuration test');
    }
}

try {
    [$app,$pdo,$schema,$site] = leadBoot();
    $mock = new CaptchaConfigMock();
    $service = new CaptchaConfigService(new Context($site, 1), $mock);
    $raw = static fn (string $key, int $target = 0): array => Data::decode(Db::name('sys_config')->where('site_id', $target ?: $site)->where('config_key', $key)->value('value'));
    $today = static fn (): string => (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    $key = '0123456789abcdef0123456789abcdef';
    $otherKey = 'fedcba9876543210fedcba9876543210';
    $before = (int) Db::name('sys_config')->count();
    $initial = $service->get();
    $service->get();
    captchaConfigCheck($initial['provider'] === '2captcha' && !$initial['enabled'] && !$initial['configured'] && $initial['daily_limit'] === 20, 'safe disabled defaults without a key');
    captchaConfigCheck($initial['used_today'] === 0 && $initial['remaining_today'] === 20 && $initial['revision'] === '0', 'default persistent budget presentation');
    captchaConfigCheck((int) Db::name('sys_config')->count() === $before, 'get does not initialize or mutate configuration');
    captchaConfigCheck($mock->balances === 0 && $mock->creates === 0, 'get never checks balance or creates a paid task');
    $member = new CaptchaConfigService(new Context($site, 0, 101), $mock);
    foreach (['get', 'balance', 'credentials'] as $method) captchaConfigDenied(fn () => $member->$method(), 'member cannot access '.$method);
    captchaConfigDenied(fn () => $member->save(['api_key' => $key]), 'member cannot edit credential configuration');
    captchaConfigDenied(fn () => $member->reserveAttempt('0'), 'member cannot spend an automatic-recognition budget');
    captchaConfigDenied(fn () => (new CaptchaConfigService(new Context($site + 99, 1), $mock))->get(), 'nonexistent site is rejected');
    captchaConfigDenied(fn () => $service->balance(), 'unconfigured balance never contacts recognizer');
    captchaConfigDenied(fn () => $service->reserveAttempt('0'), 'unconfigured reservation is rejected');
    captchaConfigCheck($raw(CaptchaConfigService::USAGE_KEY) === [], 'unconfigured rejection does not consume budget');
    foreach (['short', str_repeat('z', 32), $key.' ', ['api_key' => 'poison']] as $invalid) {
        captchaConfigDenied(fn () => $service->save(['api_key' => $invalid]), 'invalid API key shape rejected');
    }
    foreach ([0, 1001, -1, 1.5, '01', null, []] as $invalid) captchaConfigDenied(fn () => $service->save(['daily_limit' => $invalid]), 'invalid daily limit rejected');
    captchaConfigDenied(fn () => $service->save(['provider' => 'unknown', 'api_key' => $key]), 'unsupported recognizer cannot be selected');
    captchaConfigDenied(fn () => $service->save(['enabled' => true, 'consent' => true]), 'enabling requires a configured key');
    foreach ([false, 1, 'true', null] as $consent) captchaConfigDenied(fn () => $service->save(['api_key' => $key, 'enabled' => true, 'consent' => $consent]), 'enabling requires literal explicit billing and image consent');
    captchaConfigCheck($raw(CaptchaConfigService::CONFIG_KEY) === [], 'rejected consent does not write key or partial config');
    $saved = $service->save(['api_key' => strtoupper($key), 'enabled' => true, 'consent' => true, 'daily_limit' => 3, 'site_id' => $site + 1]);
    $revision = $saved['revision'];
    $stored = $raw(CaptchaConfigService::CONFIG_KEY);
    captchaConfigCheck($saved['configured'] && $saved['enabled'] && $saved['key_hint'] === '已配置（••••）', 'configured key is represented only by the fixed mask');
    captchaConfigCheck(preg_match('/^[a-f0-9]{32}$/D', $revision) === 1 && $revision !== '0', 'material change generates a new opaque revision');
    captchaConfigCheck(!str_contains(Data::json($stored), $key) && !isset($stored['api_key']), 'plain sys_config stores no API key');
    captchaConfigCheck(Cipher::decrypt($stored['credentials_cipher'], $site, -2147483000) === ['api_key' => $key], 'key is authenticated-encrypted in the prescribed site-bound scope');
    captchaConfigDenied(fn () => Cipher::decrypt($stored['credentials_cipher'], $site + 1, -2147483000), 'cipher cannot be decrypted for another site');
    captchaConfigDenied(fn () => Cipher::decrypt($stored['credentials_cipher'], $site, -2147482999), 'cipher cannot be reused for another purpose');
    captchaConfigCheck($service->credentials() === ['enabled' => true, 'api_key' => $key, 'daily_limit' => 3, 'revision' => $revision], 'only internal credentials method returns key to the local broker');
    // The next checks intentionally administer the second isolated site. An
    // explicit active membership is required, even for this synthetic uid.
    Db::name('sys_user_role')->insert(['uid' => 1, 'site_id' => $site + 1, 'status' => 1, 'is_admin' => 1]);
    captchaConfigCheck((new CaptchaConfigService(new Context($site + 1, 1), $mock))->get()['configured'] === false, 'site_id in request cannot overwrite or expose another site');
    captchaConfigCheck($mock->balances === 0 && $mock->creates === 0 && $mock->results === 0, 'saving does not contact external services');
    captchaConfigCheck(!str_contains(Data::json($saved), $key) && !array_key_exists('credentials_cipher', $saved), 'public save response has neither key nor cipher');
    $unchanged = $service->save(['api_key' => '', 'enabled' => true, 'consent' => true, 'daily_limit' => 3, 'revision' => $revision]);
    captchaConfigCheck($unchanged['revision'] === $revision && $raw(CaptchaConfigService::CONFIG_KEY)['credentials_cipher'] === $stored['credentials_cipher'], 'empty key preserves existing secret and no-op save preserves revision/cipher');
    captchaConfigDenied(fn () => $service->save(['revision' => '0', 'daily_limit' => 4]), 'stale configuration edits cannot overwrite newer settings');
    captchaConfigDenied(fn () => $service->reserveAttempt('0'), 'stale job revision cannot spend budget');
    $balance = $service->balance();
    captchaConfigCheck($balance === ['provider' => '2captcha', 'balance' => '12.345678', 'currency' => null, 'message' => '余额单位请以2Captcha账户为准；只查余额未创建识别任务'], 'balance is exact public decimal with no invented currency');
    captchaConfigCheck($mock->balances === 1 && $mock->creates === 0 && $mock->results === 0 && $service->get()['used_today'] === 0, 'explicit balance consumes neither recognition task nor local attempt');
    $mock->throws = true;
    try {
        $service->balance();
        throw new RuntimeException('Expected safe balance failure');
    } catch (CommonException $error) {
        captchaConfigCheck($error->getMessage() === '识别服务余额查询失败，请核对密钥或稍后再试' && $error->getPrevious() === null, 'balance failure has only local text and no poisoned previous cause');
    }
    $mock->throws = false;
    foreach (['captcha_invalid_key' => 'API Key 无效', 'captcha_ip_not_allowed' => '服务器出口IP',
        'captcha_temporary' => '暂时无法查询余额', 'unknown_reason' => '余额查询失败'] as $reason => $expectedText) {
        $mock->onBalance = static function () use ($reason) {throw new addon\hsx_lead\app\provider\ProviderException($reason, 'synthetic-private-upstream-key');};
        try {
            $service->balance();
            throw new RuntimeException('Expected safe classified balance error');
        } catch (CommonException $error) {
            captchaConfigCheck(str_contains($error->getMessage(), $expectedText) && !str_contains($error->getMessage(), 'synthetic-private')
                && $error->getPrevious() === null, 'known balance failures show a local action, unknown details stay private');
        }
        captchaConfigCheck($mock->creates === 0 && $service->get()['used_today'] === 0, 'classified balance failures do not consume recognition attempts');
    }
    $mock->onBalance = null;
    $mock->amount = 'synthetic-private-non-numeric';
    captchaConfigDenied(fn () => $service->balance(), 'invalid balance response is never returned');
    $mock->amount = '12.345678';
    $service->reserveAttempt($revision);
    captchaConfigCheck($service->get()['used_today'] === 1 && $service->get()['remaining_today'] === 2, 'reservation is durable before paid submission');
    Cache::clear();
    captchaConfigCheck($service->get()['used_today'] === 1, 'clearing framework cache does not reset attempts');
    (new CoreConfigService())->getConfigValue($site, CaptchaConfigService::USAGE_KEY);
    $service->reserveAttempt($revision);
    captchaConfigCheck($service->get()['used_today'] === 2, 'budget reads fresh database rather than stale framework cache');
    $service->reserveAttempt($revision);
    captchaConfigDenied(fn () => $service->reserveAttempt($revision), 'daily budget cannot be exceeded');
    captchaConfigCheck($service->get()['used_today'] === 3 && $service->get()['remaining_today'] === 0, 'failed or unused submissions conservatively retain their attempts');
    $rekey = $service->save(['api_key' => $otherKey, 'enabled' => true, 'consent' => true, 'daily_limit' => 3]);
    captchaConfigCheck($rekey['revision'] !== $revision && $rekey['used_today'] === 3, 'changing key changes revision but never resets current-day budget');
    captchaConfigDenied(fn () => $service->reserveAttempt($revision), 'old credential revision is rejected after rotation');
    captchaConfigDenied(fn () => $service->save(['api_key' => $key, 'clear_key' => true]), 'clearing and replacing simultaneously is rejected');
    $cleared = $service->save(['clear_key' => true, 'enabled' => true]);
    captchaConfigCheck(!$cleared['configured'] && !$cleared['enabled'] && $cleared['key_hint'] === '未配置' && $cleared['used_today'] === 3, 'explicit clear removes key and disables without resetting budget');
    captchaConfigCheck($raw(CaptchaConfigService::CONFIG_KEY)['credentials_cipher'] === '' && $service->credentials()['api_key'] === '', 'clear removes the encrypted key rather than masking a retained secret');
    captchaConfigDenied(fn () => $service->reserveAttempt($cleared['revision']), 'disabled configuration cannot consume attempts');
    captchaConfigDenied(fn () => $service->balance(), 'cleared key cannot be used to query balance');

    // Reuse the second isolated site for true concurrent reservation processes.
    $other = new CaptchaConfigService(new Context($site + 1, 1), $mock);
    $parallel = $other->save(['api_key' => $key, 'enabled' => true, 'consent' => true, 'daily_limit' => 2]);
    captchaConfigCheck($parallel['used_today'] === 0 && $service->get()['used_today'] === 3, 'attempt budgets are isolated per site');
    $children = [];
    try {
        for ($i = 0;$i < 4;$i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, '-d', 'error_reporting=22527', '-d',
                'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect', '-d', 'allow_url_fopen=0',
                __FILE__, '--isolated-local-mysql', '--reserve-child'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Unable to create isolated reservation worker');
            $payload = ['schema' => $schema, 'database' => $app->config->get('database'), 'auth_key' => (string) $app->env->get('app.auth_key'),
                'site' => $site + 1, 'revision' => $parallel['revision']];
            fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
            $children[] = [$process, $pipes];
        }
        // All workers wait for stdin EOF, then race with independent MySQL connections.
        foreach ($children as [$process,$pipes]) fclose($pipes[0]);
        $reserved = 0;
        foreach ($children as [$process,$pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $result = json_decode(trim($output), true);
            captchaConfigCheck($exit === 0 && $errors === '' && is_bool($result['reserved'] ?? null), 'isolated parallel budget worker returns safe result');
            if ($result['reserved']) $reserved++;
        }
        $children = [];
        captchaConfigCheck($reserved === 2 && $other->get()['used_today'] === 2, 'four concurrent requests cannot exceed the two-attempt daily limit');
        captchaConfigCheck((int) Db::name('sys_config')->where('site_id', $site + 1)->where('config_key', CaptchaConfigService::USAGE_KEY)->count() === 1, 'concurrent first reservations create one usage row without new indexes');
    } finally {
        foreach ($children as [$process,$pipes]) {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
    $yesterday = (new DateTimeImmutable('yesterday', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    (new CoreConfigService())->setConfig($site + 1, CaptchaConfigService::USAGE_KEY, ['date' => $yesterday, 'count' => 999]);
    captchaConfigCheck($other->get()['used_today'] === 0 && $raw(CaptchaConfigService::USAGE_KEY, $site + 1)['count'] === 999, 'next-day public reads do not mutate the durable prior-day record');
    $other->reserveAttempt($parallel['revision']);
    captchaConfigCheck($raw(CaptchaConfigService::USAGE_KEY, $site + 1) === ['date' => $today(), 'count' => 1], 'reservation rolls over budget using Asia/Shanghai day');
    $mock->onBalance = static function () use ($other): void {$other->save(['enabled' => false]);};
    captchaConfigDenied(fn () => $other->balance(), 'balance from an account changed during request is ignored');
    $mock->onBalance = null;
    captchaConfigCheck($other->balance()['balance'] === '12.345678', 'disabled recognizer can still explicitly query its configured balance');
    $future = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    (new CoreConfigService())->setConfig($site + 1, CaptchaConfigService::USAGE_KEY, ['date' => $future, 'count' => 1]);
    captchaConfigDenied(fn () => $other->get(), 'future budget date fails closed instead of resetting count');
    (new CoreConfigService())->setConfig($site + 1, CaptchaConfigService::USAGE_KEY, ['date' => $today(), 'count' => '1']);
    captchaConfigDenied(fn () => $other->get(), 'malformed durable budget fails closed');
    (new CoreConfigService())->setConfig($site + 1, CaptchaConfigService::USAGE_KEY, ['date' => '2020-13-01', 'count' => 1]);
    captchaConfigDenied(fn () => $other->get(), 'invalid calendar day cannot silently reset the quota');
    (new CoreConfigService())->setConfig($site + 1, CaptchaConfigService::USAGE_KEY, ['date' => $today(), 'count' => 1]);
    $bad = $raw(CaptchaConfigService::CONFIG_KEY, $site + 1);
    $bad['credentials_cipher'] = 'synthetic-corrupt-cipher';
    (new CoreConfigService())->setConfig($site + 1, CaptchaConfigService::CONFIG_KEY, $bad);
    captchaConfigCheck(!$other->get()['configured'] && !$other->get()['enabled'], 'unreadable cipher is never represented as a ready recognizer');
    captchaConfigDenied(fn () => $other->credentials(), 'internal reads cannot silently use corrupt key');
    captchaConfigDenied(fn () => $other->save(['api_key' => '']), 'empty key does not silently clear or overwrite unreadable credentials');
    captchaConfigCheck($other->save(['clear_key' => true])['configured'] === false, 'explicit clear can repair unreadable encrypted credentials');
    $audit = json_encode(Db::name('hsx_lead_audit')->select()->toArray());
    captchaConfigCheck(!str_contains($audit, $key) && !str_contains($audit, $otherKey) && !str_contains($audit, 'credentials_cipher'), 'audit stores only whitelisted configuration metadata');
    $logs = json_encode(think\facade\Log::getLog());
    captchaConfigCheck(!str_contains($logs, $key) && !str_contains($logs, $otherKey), 'configuration and failures do not log keys');
    $cacheDirectory = sys_get_temp_dir().'/'.$schema.'/cache/';
    $cacheSafe = true;
    if (is_dir($cacheDirectory)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDirectory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $contents = file_get_contents($file->getPathname());
            if (str_contains($contents, $key) || str_contains($contents, $otherKey)) $cacheSafe = false;
        }
    }
    captchaConfigCheck($cacheSafe, 'private isolated cache files never contain plaintext API keys');
    captchaConfigCheck($mock->creates === 0 && $mock->results === 0, 'entire configuration regression creates and polls zero paid recognitions');
    leadAssertIsolated($schema);
    echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'encrypted site CAPTCHA configuration, consent, durable quota, concurrent reservation and safe mock balance',
        'database' => 'owned random localhost schema', 'remote_calls' => 0, 'paid_tasks' => 0, 'existing_business_rows_read' => 0]),PHP_EOL;
} catch (Throwable $error) {
    $failed = true;
    $message = $error instanceof PDOException ? 'isolated MySQL failure SQLSTATE '.$error->getCode() : $error->getMessage();
    fwrite(STDERR, 'FAIL after '.$checks.' checks '.get_class($error).': '.$message.' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
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
