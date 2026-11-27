<?php
declare(strict_types=1);

// Pure synthetic regression. No network, paid task, real image, key or business database.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\auth\{CaptchaRecognizerContract, CaptchaTransportContract, GuzzleCaptchaTransport, TwoCaptchaRecognizer};
use addon\hsx_lead\app\provider\ProviderException;
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Response, Utils};

final class CaptchaFixtureTransport implements CaptchaTransportContract
{
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $method, #[SensitiveParameter] array $body): array
    {
        $this->calls[] = compact('method', 'body');
        $response = array_shift($this->responses);
        if ($response instanceof Throwable) throw $response;
        if (!is_array($response)) throw new RuntimeException('Missing fixture');
        return $response;
    }
}

$checks = 0;
function checkCaptcha(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    $GLOBALS['checks']++;
}
function failCaptcha(callable $call, string $reason): void
{
    try {
        $call();
    } catch (ProviderException $error) {
        checkCaptcha($error->reason() === $reason, 'safe expected reason '.$reason.', actual '.$error->reason());
        checkCaptcha($error->getPrevious() === null, 'sensitive upstream exception never becomes a cause');
        checkCaptcha(!preg_match('/discard-|clientKey|password|data:image|cookie|api\.2captcha\.com/i', $error->getMessage()), 'error never echoes upstream secrets or response text');
        return;
    }
    throw new RuntimeException('FAIL: expected '.$reason);
}
function captchaUri(string $type, string $bytes): string
{
    return 'data:'.$type.';base64,'.base64_encode($bytes);
}
function captchaPng(int $width = 1, int $height = 1): string
{
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).
        $chunk('IDAT', gzcompress("\0\xff\xff\xff")).$chunk('tEXt', 'fixture-only-no-user-data-0000000000').$chunk('IEND', '');
}
function captchaHttp(array $responses, array &$calls): GuzzleCaptchaTransport
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create(function ($request, $options) use ($mock, &$calls) {
        $calls[] = ['request' => $request, 'options' => $options];
        return $mock($request, $options);
    });
    return new GuzzleCaptchaTransport(new Client(['handler' => $stack]));
}

$key = str_repeat('a', 32);
$uri = captchaUri('image/png', captchaPng());
$transport = new CaptchaFixtureTransport([['errorId' => 0, 'taskId' => 72345678901],
    ['errorId' => 0, 'status' => 'processing', 'discard-private' => 'discard-meta'],
    ['errorId' => 0, 'status' => 'ready', 'solution' => ['text' => 'aB19'], 'cost' => '0.00025', 'ip' => 'discard-ip'],
    ['errorId' => 0, 'balance' => '0.93958']]);
$recognizer = new TwoCaptchaRecognizer($transport);
checkCaptcha($recognizer instanceof CaptchaRecognizerContract, 'client exposes the independent injectable contract');
checkCaptcha($recognizer->create($key, $uri) === '72345678901', 'successful create returns an opaque positive decimal task ID');
checkCaptcha($transport->calls[0] === ['method' => 'createTask', 'body' => ['clientKey' => $key, 'task' => [
    'type' => 'ImageToTextTask', 'body' => substr($uri, strpos($uri, ',') + 1), 'case' => true, 'numeric' => 0, 'minLength' => 4, 'maxLength' => 4]]],
    'only the image and API key leave the client, with exact case-sensitive four-character options');
checkCaptcha($recognizer->result($key, '72345678901') === ['status' => 'processing', 'cost' => null], 'processing is returned immediately without polling or metadata');
checkCaptcha($recognizer->result($key, '72345678901') === ['status' => 'ready', 'text' => 'aB19', 'cost' => '0.00025'], 'ready exposes only exact ASCII solution and safe exact decimal cost');
checkCaptcha($transport->calls[1]['body'] === ['clientKey' => $key, 'taskId' => 72345678901], 'result encodes taskId as documented integer');
checkCaptcha($recognizer->balance($key) === '0.93958', 'balance is a precise decimal string without invented currency');
checkCaptcha(count($transport->calls) === 4, 'each public call makes exactly one service call and never sleeps or retries');

$gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==', true).str_repeat("\0", 100);
// Synthetic JPEG frame is sufficient for the required metadata parser; never sent to a real service.
$jpeg = "\xff\xd8\xff\xc0".pack('nCnnC', 17, 8, 1, 1, 3)."\x01\x11\0\x02\x11\0\x03\x11\0\xff\xd9".str_repeat("\0", 100);
foreach ([['image/png', captchaPng()], ['image/gif', $gif], ['image/jpeg', $jpeg]] as [$type, $bytes]) {
    checkCaptcha((@getimagesizefromstring($bytes)['mime'] ?? '') === $type, 'synthetic fixture has valid bounded metadata for '.$type);
    $fixture = new CaptchaFixtureTransport([['errorId' => 0, 'taskId' => '123']]);
    checkCaptcha((new TwoCaptchaRecognizer($fixture))->create($key, captchaUri($type, $bytes)) === '123', 'supported raster MIME and magic accepted for '.$type);
}
foreach ([captchaUri('image/png', captchaPng(600, 600))] as $boundary) {
    checkCaptcha((new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'taskId' => 1]])))->create($key, $boundary) === '1', '600px boundary accepted without raising to conflicting 1000px claim');
}
$invalidImages = ['https://example.test/captcha', 'data:image/svg+xml;base64,'.base64_encode('<svg/>'),
    'data:text/html;base64,'.base64_encode(str_repeat('x', 100)), 'data:image/png;base64,not base64',
    'data:image/png;base64,'.base64_encode(captchaPng())."\n", captchaUri('image/png', $gif),
    captchaUri('image/jpeg', captchaPng()), captchaUri('image/gif', 'GIF89a'.str_repeat('x', 110)),
    captchaUri('image/png', "\x89PNG\r\n\x1a\n".str_repeat('x', 100)), captchaUri('image/png', substr(captchaPng(), 0, 99)),
    captchaUri('image/png', captchaPng(601, 1)), captchaUri('image/png', captchaPng(1, 601)),
    captchaUri('image/png', captchaPng(0, 1)), captchaUri('image/png', captchaPng().str_repeat('x', 100001))];
foreach ($invalidImages as $image) {
    $fixture = new CaptchaFixtureTransport([]);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($fixture))->create($key, $image), 'captcha_invalid_image');
    checkCaptcha($fixture->calls === [], 'bad/oversized/mismatched image never leaves the process');
}
foreach (['', 'YOUR_API_KEY', str_repeat('a', 31), str_repeat('a', 33), str_repeat('g', 32), str_repeat('a', 32)."\n"] as $badKey) {
    foreach (['create', 'result', 'balance'] as $operation) {
        $fixture = new CaptchaFixtureTransport([]);
        failCaptcha(fn () => match ($operation) {
            'create' => (new TwoCaptchaRecognizer($fixture))->create($badKey, $uri),
            'result' => (new TwoCaptchaRecognizer($fixture))->result($badKey, '123'), default => (new TwoCaptchaRecognizer($fixture))->balance($badKey)
        }, 'captcha_invalid_key');
        checkCaptcha($fixture->calls === [], 'invalid service key never reaches transport');
    }
}
foreach ([0, 3, 5, 99] as $length) {
    $fixture = new CaptchaFixtureTransport([]);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($fixture))->create($key, $uri, $length), 'captcha_invalid_request');
    failCaptcha(fn () => (new TwoCaptchaRecognizer($fixture))->result($key, '123', $length), 'captcha_invalid_request');
    checkCaptcha($fixture->calls === [], 'only the verified four-character task is supported');
}
foreach (['', '0', '01', '-1', '1.0', '1e3', "12\n", '9223372036854775808', '12345678901234567890'] as $id) {
    $fixture = new CaptchaFixtureTransport([]);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($fixture))->result($key, $id), 'captcha_invalid_request');
    checkCaptcha($fixture->calls === [], 'malformed or unrepresentable task ID cannot make a request');
}

$errors = ['ERROR_KEY_DOES_NOT_EXIST' => 'captcha_invalid_key', 'ERROR_ZERO_BALANCE' => 'captcha_zero_balance',
    'ERROR_IP_NOT_ALLOWED' => 'captcha_ip_not_allowed', 'ERROR_IP_BLOCKED' => 'captcha_ip_not_allowed',
    'ERROR_NO_SLOT_AVAILABLE' => 'captcha_no_slot', 'ERROR_CAPTCHA_UNSOLVABLE' => 'captcha_unsolvable',
    'ERROR_BAD_DUPLICATES' => 'captcha_unsolvable', 'ERROR_NO_SUCH_CAPCHA_ID' => 'captcha_not_found',
    'ERROR_ZERO_CAPTCHA_FILESIZE' => 'captcha_invalid_image', 'ERROR_TOO_BIG_CAPTCHA_FILESIZE' => 'captcha_invalid_image',
    'ERROR_IMAGE_TYPE_NOT_SUPPORTED' => 'captcha_invalid_image', 'ERROR_ACCOUNT_SUSPENDED' => 'captcha_temporary',
    'ERROR_BAD_PARAMETERS' => 'captcha_temporary', 'discard-unknown' => 'captcha_temporary'];
foreach ($errors as $code => $reason) {
    foreach (['create', 'result', 'balance'] as $operation) {
        $fixture = new CaptchaFixtureTransport([['errorId' => 1, 'errorCode' => $code, 'errorDescription' => 'discard-password-token-image']]);
        failCaptcha(fn () => match ($operation) {
            'create' => (new TwoCaptchaRecognizer($fixture))->create($key, $uri),
            'result' => (new TwoCaptchaRecognizer($fixture))->result($key, '123'), default => (new TwoCaptchaRecognizer($fixture))->balance($key)
        }, $reason);
        checkCaptcha(count($fixture->calls) === 1, 'an explicit business rejection is never retried or rebuilt by the client');
    }
}
foreach ([null, false, '0', 0.0, -1, [], 'bad'] as $badCode) {
    failCaptcha(fn () => (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => $badCode, 'taskId' => 123]])))->create($key, $uri), 'captcha_submit_unknown');
    failCaptcha(fn () => (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => $badCode, 'status' => 'ready', 'solution' => ['text' => 'aB19']]])))->result($key, '123'), 'captcha_schema_changed');
}
foreach ([null, false, 1.5, 0, '01', 'bad', '9223372036854775808'] as $id) {
    failCaptcha(fn () => (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'taskId' => $id]])))->create($key, $uri), 'captcha_submit_unknown');
}
foreach ([null, '', '123', '12345', '12-4', '１２３４', "aB19\n", ' aB1', 1234, []] as $text) {
    failCaptcha(fn () => (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'status' => 'ready', 'solution' => ['text' => $text]]])))->result($key, '123'), 'captcha_schema_changed');
}
foreach ([['errorId' => 0, 'status' => 'Ready', 'solution' => ['text' => 'aB19']], ['errorId' => 0, 'status' => 'ready', 'solution' => 'discard-private'], ['errorId' => 0]] as $badResult) {
    failCaptcha(fn () => (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([$badResult])))->result($key, '123'), 'captcha_schema_changed');
}
foreach ([null, [], true, -1, '-0.1', '1e-5', '01.2', 'NaN', 'INF', '0.123456789', NAN, INF, -INF, 0.0000001, 1.0e20, 0.123456789, -0.0] as $value) {
    failCaptcha(fn () => (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'balance' => $value]])))->balance($key), 'captcha_schema_changed');
    $result = (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'status' => 'ready', 'solution' => ['text' => 'aB19'], 'cost' => $value]])))->result($key, '123');
    checkCaptcha($result['cost'] === null, 'unknown or unsafe cost is not fabricated as money');
}
foreach ([0 => '0', 1 => '1', '0.00000001' => '0.00000001', '123456789012.99999999' => '123456789012.99999999'] as $value => $expected) {
    checkCaptcha((new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'balance' => $value]])))->balance($key) === $expected, 'supported nonnegative decimal balance remains exact');
}
foreach ([[0.93958, '0.93958'], [0.1, '0.1'], [1.0, '1.0'], [0.0, '0.0']] as [$value, $expected]) {
    checkCaptcha((new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'balance' => $value]])))->balance($key) === $expected,
        'official numeric balance decoded as a finite float supports stable JSON decimal display without claiming original monetary precision');
    $result = (new TwoCaptchaRecognizer(new CaptchaFixtureTransport([['errorId' => 0, 'status' => 'ready', 'solution' => ['text' => 'aB19'], 'cost' => $value]])))->result($key, '123');
    checkCaptcha($result['cost'] === $expected, 'finite cost from another transport is not forcibly rounded or converted from scientific notation');
}
foreach ([new RuntimeException('discard-apiKey-password'), new ProviderException('captcha_no_slot', 'discard-upstream-private'), new ProviderException('discard-unknown', 'discard-private')] as $error) {
    $fixture = new CaptchaFixtureTransport([$error]);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($fixture))->create($key, $uri), $error instanceof ProviderException && $error->reason() === 'captcha_no_slot' ? 'captcha_no_slot' : 'captcha_submit_unknown');
    checkCaptcha(count($fixture->calls) === 1, 'uncertain create is not retried');
}

// Actual Guzzle request options and byte decoder, with an in-memory handler only.
$calls = [];
$http = captchaHttp([new Response(200, ['Content-Type' => 'application/json'], '{"errorId":0,"taskId":123}'),
    new Response(200, [], '{"errorId":0,"status":"ready","solution":{"text":"Ab19"},"cost":0.00025}'),
    new Response(200, [], '{"errorId":0,"balance":0.93958000}')], $calls);
$realClient = new TwoCaptchaRecognizer($http);
checkCaptcha($realClient->create($key, $uri) === '123', 'production transport can be fully tested with a mock handler');
checkCaptcha($realClient->result($key, '123')['cost'] === '0.00025', 'raw numeric task cost avoids binary-float conversion');
checkCaptcha($realClient->balance($key) === '0.93958000', 'raw numeric balance preserves decimal precision and trailing digits');
foreach ($calls as $index => $call) {
    $request = $call['request'];
    $options = $call['options'];
    checkCaptcha((string) $request->getUri() === 'https://api.2captcha.com/'.['createTask', 'getTaskResult', 'getBalance'][$index] && $request->getMethod() === 'POST', 'only the three fixed HTTPS POST endpoints are used');
    checkCaptcha($options['verify'] === true && $options['allow_redirects'] === false && $options['cookies'] === false && $options['debug'] === false && $options['http_errors'] === false, 'TLS, redirect, cookie, debug and status behavior are pinned per request');
    checkCaptcha($options['connect_timeout'] === 5 && $options['timeout'] === 12 && $options['read_timeout'] === 12 && $options['stream'] === true, 'request and streamed read have bounded timeouts');
    checkCaptcha(!$request->hasHeader('Cookie') && !$request->hasHeader('Authorization') && $request->getHeaderLine('Content-Type') === 'application/json', 'no ERP cookie, Authorization or form payload is forwarded');
    $body = json_decode((string) $request->getBody(), true, 32, JSON_THROW_ON_ERROR);
    checkCaptcha(!preg_match('/callbackUrl|softId|websiteURL|password|username|device_id|store_id|company_id/', json_encode($body)), 'no account, business scope, callback, site URL or referral fields are sent');
}
$calls = [];
$http = captchaHttp([], $calls);
foreach (['https://api.2captcha.com/createTask', '../createTask', 'createTask?x=1', 'reportCorrect', 'GET'] as $method) {
    failCaptcha(fn () => $http->post($method, ['clientKey' => $key]), 'captcha_invalid_request');
}
foreach ([['clientKey' => $key, 'callbackUrl' => 'https://example.test'], ['clientKey' => $key, 'taskId' => '123'], ['clientKey' => 'bad']] as $body) {
    failCaptcha(fn () => $http->post(array_key_exists('taskId', $body) ? 'getTaskResult' : 'getBalance', $body), 'captcha_invalid_request');
}
$validCreate = $transport->calls[0]['body'];
foreach ([array_merge($validCreate, ['softId' => 1]), array_replace_recursive($validCreate, ['task' => ['type' => 'RecaptchaV2TaskProxyless']]),
    array_replace_recursive($validCreate, ['task' => ['case' => false]]), array_replace_recursive($validCreate, ['task' => ['numeric' => '0']]),
    array_replace_recursive($validCreate, ['task' => ['websiteURL' => 'https://example.test']])] as $body) {
    failCaptcha(fn () => $http->post('createTask', $body), 'captcha_invalid_request');
}
checkCaptcha($calls === [], 'invalid endpoint and forbidden payloads stop before the HTTP handler');
foreach ([302, 400, 401, 403, 429, 500, 503] as $status) {
    $calls = [];
    $http = captchaHttp([new Response($status, ['Location' => 'https://example.test/steal'], '{"errorId":0,"taskId":123}')], $calls);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($http))->create($key, $uri), 'captcha_submit_unknown');
    checkCaptcha(count($calls) === 1, 'HTTP failure never follows a redirect or automatically repeats a possibly billed create');
    $calls = [];
    $http = captchaHttp([new Response($status, [], 'discard-secret')], $calls);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($http))->balance($key), 'captcha_temporary');
    checkCaptcha(count($calls) === 1, 'balance failure never triggers task creation or an internal retry');
}
foreach (['<html>discard-private</html>', '[]', 'null', '{"errorId":', str_repeat('x', 65537)] as $bytes) {
    $calls = [];
    $http = captchaHttp([new Response(200, [], $bytes)], $calls);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($http))->create($key, $uri), 'captcha_submit_unknown');
    checkCaptcha(count($calls) === 1, 'malformed or over-64KiB response does not produce a second create');
}
$base = '{"errorId":0,"taskId":123,"padding":""}';
$boundary = str_replace('"padding":""', '"padding":"'.str_repeat('x', 65536 - strlen($base)).'"', $base);
$calls = [];
$stream = Utils::streamFor($boundary);
$http = captchaHttp([new Response(200, [], $stream)], $calls);
checkCaptcha((new TwoCaptchaRecognizer($http))->create($key, $uri) === '123' && strlen($boundary) === 65536, 'an exactly 64KiB JSON object remains within the response limit');
checkCaptcha(!$stream->isReadable(), 'successful response stream is closed after bounded consumption');
foreach ([new Response(200, [], Utils::streamFor(str_repeat('x', 65537))), new Response(302, [], Utils::streamFor('discard-private'))] as $response) {
    $stream = $response->getBody();
    $calls = [];
    $http = captchaHttp([$response], $calls);
    failCaptcha(fn () => (new TwoCaptchaRecognizer($http))->create($key, $uri), 'captcha_submit_unknown');
    checkCaptcha(!$stream->isReadable(), 'error and oversized streams are closed before a safe exception escapes');
}
foreach (['create', 'result', 'balance'] as $operation) {
    $calls = [];
    $http = captchaHttp([new RuntimeException('discard-timeout-request-clientKey')], $calls);
    failCaptcha(fn () => match ($operation) {
        'create' => (new TwoCaptchaRecognizer($http))->create($key, $uri),
        'result' => (new TwoCaptchaRecognizer($http))->result($key, '123'), default => (new TwoCaptchaRecognizer($http))->balance($key)
    },
        $operation === 'create' ? 'captcha_submit_unknown' : 'captcha_temporary');
    checkCaptcha(count($calls) === 1, 'transport exception is redacted without automatic retry');
}

echo json_encode(['passed' => true, 'checks' => $checks, 'scope' => 'synthetic bounded ImageToTextTask client and in-memory HTTP contract only',
    'remote_calls' => 0, 'paid_tasks' => 0, 'database_writes' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
