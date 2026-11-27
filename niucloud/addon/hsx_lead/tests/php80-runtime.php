<?php
declare(strict_types=1);

// Pure local runtime and privacy regression. No database or remote request.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\support\{PhpRuntime, Data, Cipher};
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\provider\{GuzzleJsonTransport, ProviderException};
use addon\hsx_lead\app\auth\{TwoCaptchaRecognizer, CaptchaTransportContract};

$checks = 0;
function runtimeCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $GLOBALS['checks']++;
}
function runtimeFailure(callable $call): Throwable
{
    try {
        $call();
    } catch (Throwable $error) {
        return $error;
    }
    throw new RuntimeException('FAIL: expected rejection');
}
function runtimeHidden(Throwable $error, string $marker, string $class, string $method): void
{
    $found = false;
    foreach ($error->getTrace() as $frame) {
        if (($frame['class'] ?? '') !== $class || ($frame['function'] ?? '') !== $method) continue;
        $found = true;
        if (PHP_VERSION_ID < 80200) runtimeCheck(!array_key_exists('args', $frame), 'legacy engine captured no sensitive method arguments');
        else runtimeCheck(($frame['args'][0] ?? null) instanceof SensitiveParameterValue, 'new engine keeps native sensitive parameter redaction');
    }
    runtimeCheck($found, 'real sensitive method exists in exception trace');
    runtimeCheck(!str_contains(json_encode($error->getTrace(), JSON_PARTIAL_OUTPUT_ON_ERROR), $marker), 'serialized trace contains no synthetic secret');
}
function runtimeOrdinary(string $value): void
{
    throw new RuntimeException('ordinary non-plugin failure');
}

if (in_array('--unavailable-guard', $argv, true)) {
    runtimeCheck(PHP_VERSION_ID < 80200, 'failure injection targets the legacy engine');
    runtimeCheck(!function_exists('ini_set'), 'failure injection disables runtime configuration changes');
    $sensitiveWork = false;
    $error = runtimeFailure(static function () use (&$sensitiveWork): void {
        $scope = PhpRuntime::guardSecrets();
        $sensitiveWork = true;
    });
    runtimeCheck($error instanceof RuntimeException && !$sensitiveWork, 'unavailable trace protection stops before any sensitive work');
    runtimeCheck($error->getMessage() === '无法启用凭据日志保护，未执行敏感操作', 'failure is a locally-authored safe message');
    echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION,
        'scope' => 'unavailable legacy privacy guard fails closed', 'remote_calls' => 0]), PHP_EOL;
    exit(0);
}
if (in_array('--preprotected-guard', $argv, true)) {
    runtimeCheck(PHP_VERSION_ID < 80200 && !function_exists('ini_set'), 'preprotected legacy engine cannot change runtime settings');
    runtimeCheck((bool) ini_get('zend.exception_ignore_args'), 'server already omits stack arguments');
    $scope = PhpRuntime::guardSecrets();
    $scope->close();
    runtimeCheck((bool) ini_get('zend.exception_ignore_args'), 'already-protected server needs no configuration mutation');
    echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION,
        'scope' => 'preprotected legacy engine with ini_set disabled', 'remote_calls' => 0]), PHP_EOL;
    exit(0);
}

$prior = ini_get('zend.exception_ignore_args');
$hasListFunction = function_exists('array_is_list'); // Composer may already supply its own polyfill.
try {
    foreach ([
        [[], true], [[0 => 'x'], true], [[0 => null, 1 => false], true],
        [[1 => 'x'], false], [[-1 => 'x'], false], [[0 => 'x', 2 => 'y'], false],
        [[1 => 'x', 0 => 'y'], false], [['a' => 'x'], false],
        [['00' => 'x'], false], [['0' => 'x'], true], [[0 => [], 1 => new stdClass()], true],
    ] as [$value, $expected]) {
        runtimeCheck(PhpRuntime::isList($value) === $expected, 'list helper preserves exact contiguous integer-key semantics');
        if (function_exists('array_is_list')) runtimeCheck(PhpRuntime::isList($value) === array_is_list($value), 'helper matches native implementation where available');
    }
    runtimeCheck(function_exists('array_is_list') === $hasListFunction, 'plugin does not add or replace a global list function');

    $context = new Context(987654321, 7, 0);
    runtimeCheck($context->site === 987654321 && $context->uid === 7 && $context->memberId === 0, 'read-only identity remains readable including member zero');
    runtimeCheck(isset($context->site, $context->uid, $context->memberId) && !isset($context->other), 'identity isset contract remains compatible');
    foreach (['site', 'uid', 'memberId', 'other'] as $name) {
        runtimeCheck(runtimeFailure(static function () use ($context, $name): void { $context->$name = 999; }) instanceof Error, 'identity cannot be rebound via public assignment');
        runtimeCheck(runtimeFailure(static function () use ($context, $name): void { unset($context->$name); }) instanceof Error, 'identity cannot be unset');
    }
    runtimeCheck(runtimeFailure(static fn () => $context->other) instanceof Error, 'unknown identity field is rejected');
    runtimeCheck(runtimeFailure(static fn () => $context->__construct(1, 999, 999)) instanceof Error, 'explicit repeated constructor cannot rebind identity');
    runtimeCheck($context->site === 987654321 && $context->uid === 7 && $context->memberId === 0, 'identity survives all attempted mutations');

    ini_set('zend.exception_ignore_args', '0');
    $first = PhpRuntime::guardSecrets();
    $second = PhpRuntime::guardSecrets();
    runtimeCheck((bool) ini_get('zend.exception_ignore_args') === (PHP_VERSION_ID < 80200), 'legacy guard is active while new engine keeps native setting');
    $first->close();
    runtimeCheck((bool) ini_get('zend.exception_ignore_args') === (PHP_VERSION_ID < 80200), 'out-of-order outer release keeps remaining scope protected');
    $second->close();
    $second->close();
    runtimeCheck(ini_get('zend.exception_ignore_args') === '0', 'last release restores exact prior setting and double-close is harmless');

    $secret = 'synthetic-php80-secret-DO-NOT-LOG';
    $error = runtimeFailure(static fn () => Data::text(['password' => $secret, 'name' => []], 'name'));
    runtimeHidden($error, $secret, Data::class, 'text');
    runtimeCheck(ini_get('zend.exception_ignore_args') === '0', 'direct validation failure restores trace setting');
    $error = runtimeFailure(static fn () => Data::secret(['password' => [$secret]], 'password', 100));
    runtimeHidden($error, $secret, Data::class, 'secret');
    $error = runtimeFailure(static fn () => Cipher::decrypt($secret.'!', 1, 1));
    runtimeHidden($error, $secret, Cipher::class, 'decrypt');
    $error = runtimeFailure(static fn () => GuzzleJsonTransport::decode('{"token":"'.$secret.'"'));
    runtimeHidden($error, $secret, GuzzleJsonTransport::class, 'decode');
    runtimeCheck($error instanceof ProviderException && $error->getPrevious() === null, 'JSON decoder failure never chains original response exception');

    $transport = new class implements CaptchaTransportContract {
        public int $calls = 0;
        public function post(string $method, #[SensitiveParameter] array $body): array
        {
            $this->calls++;
            throw new RuntimeException('FAIL: invalid credentials must not create a task');
        }
    };
    $recognizer = new TwoCaptchaRecognizer($transport);
    $error = runtimeFailure(static fn () => $recognizer->balance($secret));
    runtimeHidden($error, $secret, TwoCaptchaRecognizer::class, 'balance');
    runtimeCheck($transport->calls === 0, 'local invalid recognition key is rejected without remote request');
    runtimeCheck(ini_get('zend.exception_ignore_args') === '0', 'nested service/transport guards unwind to prior state');

    if (PHP_VERSION_ID < 80200) {
        $error = runtimeFailure(static function () use ($secret): void {
            $scope = PhpRuntime::guardSecrets();
            Data::text($secret, 'name'); // Intentional pre-body TypeError under outer request/job guard.
        });
        runtimeCheck($error instanceof TypeError, 'type mismatch remains a real PHP type rejection');
        foreach ($error->getTrace() as $frame) runtimeCheck(!array_key_exists('args', $frame), 'outer guard protects pre-body type errors across every captured frame');
        runtimeCheck(!str_contains(json_encode($error->getTrace()), $secret), 'outer type-error trace contains no secret');
        runtimeCheck(ini_get('zend.exception_ignore_args') === '0', 'outer exception scope restores original setting');
        $ordinary = runtimeFailure(static fn () => runtimeOrdinary($secret));
        $ordinaryArgs = null;
        foreach ($ordinary->getTrace() as $frame) if (($frame['function'] ?? '') === 'runtimeOrdinary') $ordinaryArgs = $frame['args'] ?? null;
        runtimeCheck($ordinaryArgs === [$secret], 'ordinary non-plugin trace behavior is restored after sensitive scope');
    }
    ini_set('zend.exception_ignore_args', '1');
    $scope = PhpRuntime::guardSecrets();
    $scope->close();
    runtimeCheck(ini_get('zend.exception_ignore_args') === '1', 'pre-existing stricter setting is retained');

    $source = file_get_contents(dirname(__DIR__).'/app/middleware/SafeErrors.php');
    runtimeCheck(strpos($source, '$app->instance(Handle::class,$safe)') < strpos($source, 'PhpRuntime::guardSecrets()'), 'safe handler is installed before runtime protection may reject the request');
    runtimeCheck(str_contains($source, '$secretTrace->close()'), 'HTTP boundary explicitly closes runtime protection');
    $job = file_get_contents(dirname(__DIR__).'/app/job/SyncInventory.php');
    runtimeCheck(strpos($job, 'guardSecrets()') < strpos($job, '(new SyncService())') && str_contains($job, 'finally'), 'scheduled worker protects credentials and restores process settings');

    echo json_encode(['passed' => true, 'checks' => $checks, 'php' => PHP_VERSION,
        'scope' => 'local PHP runtime, immutable identity, scoped trace privacy and list semantics',
        'remote_calls' => 0, 'database_access' => false]), PHP_EOL;
} finally {
    if ($prior !== false) ini_set('zend.exception_ignore_args', $prior);
}
