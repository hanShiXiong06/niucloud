<?php
declare(strict_types=1);

namespace core\base {
    class BaseApiService {}
}

namespace app\service\core\weapp {
    class CoreWeappService
    {
        public static $application;
        public static array $sites = [];

        public static function app(int $siteId)
        {
            self::$sites[] = $siteId;
            return self::$application;
        }
    }
}

namespace {
    use addon\tk_jhkd\app\service\core\WeappDeliveryService;
    use app\service\core\weapp\CoreWeappService;
    use EasyWeChat\MiniApp\Application;
    use EasyWeChat\OpenPlatform\AuthorizerAccessToken;
    use Symfony\Component\HttpClient\Exception\ServerException;
    use Symfony\Component\HttpClient\Exception\TransportException;
    use Symfony\Component\HttpClient\MockHttpClient;
    use Symfony\Component\HttpClient\Response\MockResponse;

    // The installed SDK predates PHP 8.4 nullable-parameter deprecations.
    error_reporting(E_ALL & ~E_DEPRECATED);
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    require __DIR__ . '/../app/service/core/WeappDeliveryService.php';

    class SdkWeappDeliveryService extends WeappDeliveryService
    {
        public function sendForTest(string $endpoint, array $payload): array
        {
            return $this->postWechat(100005, $endpoint, $payload);
        }
    }

    class IsolatedOpenPlatform extends \EasyWeChat\OpenPlatform\Application
    {
        public array $authorizerCalls = [];

        public function getAuthorizerAccessToken(string $appId, string $refreshToken): string
        {
            // Only the remote refresh is substituted; the installed SDK's MiniApp factory is real.
            $this->authorizerCalls[] = [$appId, $refreshToken];
            return 'isolated-authorizer-token';
        }
    }

    $checks = 0;
    function checkSdk(bool $condition, string $label): void
    {
        $GLOBALS['checks']++;
        if (!$condition) throw new \RuntimeException($label);
    }

    /** All transports are real Symfony mocks; no request can leave this process. */
    function sdkFixture(callable $responses): array
    {
        $calls = new \ArrayObject();
        $transport = new MockHttpClient(function ($method, $url, $options) use ($calls, $responses) {
            $calls[] = ['method' => $method, 'url' => $url, 'options' => $options];
            return $responses(count($calls));
        }, 'https://api.weixin.qq.com/');
        $app = new Application(['http' => ['retry' => true, 'max_retries' => 2, 'timeout' => 5.0]]);
        $app->setHttpClient($transport);
        $token = new AuthorizerAccessToken('wx_isolated_test', 'isolated-fake-token');
        $app->setAccessToken($token);
        CoreWeappService::$application = $app;
        CoreWeappService::$sites = [];
        return [$app, $calls, $token];
    }

    function sdkJson(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body, JSON_UNESCAPED_UNICODE), [
            'http_code' => $status,
            'response_headers' => ['content-type: application/json', 'retry-after: 0'],
        ]);
    }

    $service = new SdkWeappDeliveryService();
    $payload = ['order_key' => ['order_number_type' => 2, 'transaction_id' => '4200001234202611111234567890'],
        'shipping_list' => [['item_desc' => '聚合快递隔离测试']], 'logistics_type' => 3];

    foreach (['upload_shipping_info', 'notify_confirm_receive', 'set_msg_jump_path'] as $action) {
        [$app, $calls, $token] = sdkFixture(fn ($count) => sdkJson(['errcode' => 0]));
        $cachedClient = $app->getClient(); // Deliberately create the original retry-enabled client first.
        $result = $service->sendForTest('wxa/sec/order/' . $action, $payload);
        checkSdk($result === ['errcode' => 0] && count($calls) === 1, $action . ': one successful POST');
        $call = $calls[0];
        checkSdk($call['method'] === 'POST' && strpos($call['url'], '/wxa/sec/order/' . $action . '?') !== false,
            $action . ': endpoint and HTTP method preserved');
        checkSdk(json_decode($call['options']['body'], true) === $payload, $action . ': explicit json option is the exact wire body');
        checkSdk($call['options']['timeout'] === 5.0 && $call['options']['max_duration'] === 8.0,
            $action . ': timeout options do not leak into payload');
        checkSdk(($call['options']['query']['access_token'] ?? '') === 'isolated-fake-token' && $app->getAccessToken() === $token,
            $action . ': original authorizer-token provider preserved');
        checkSdk($app->getClient() === $cachedClient && $app->getConfig()->get('http.retry') === true
            && $app->getConfig()->get('http.max_retries') === 2, $action . ': cached shared client and configuration preserved');
        checkSdk(CoreWeappService::$sites === [100005], $action . ': tenant passed to application factory');
    }

    [$app, $calls] = sdkFixture(fn ($count) => sdkJson(['errcode' => 40013, 'errmsg' => 'synthetic rejection']));
    checkSdk($service->sendForTest('wxa/sec/order/upload_shipping_info', $payload)['errcode'] === 40013
        && count($calls) === 1, 'explicit WeChat rejection returned for service classification');

    [$app, $calls] = sdkFixture(fn ($count) => sdkJson(['errcode' => 0], $count === 1 ? 503 : 200));
    $app->getClient();
    try {
        $service->sendForTest('wxa/sec/order/upload_shipping_info', $payload);
        throw new \RuntimeException('HTTP 503 unexpectedly succeeded');
    } catch (ServerException $expected) {
        checkSdk(count($calls) === 1, 'HTTP 503 does not trigger a hidden second mutation');
        checkSdk($app->getConfig()->get('http.retry') === true && $app->getConfig()->get('http.max_retries') === 2,
            'SDK configuration restored after HTTP failure');
    }

    $dnsThenSuccess = function (int $count): MockResponse {
        if ($count === 1) return new MockResponse('', ['error' => 'isolated DNS failure', 'primary_ip' => '']);
        return sdkJson(['errcode' => 0]);
    };
    [$app, $calls] = sdkFixture($dnsThenSuccess);
    $cachedClient = $app->getClient();
    try {
        $service->sendForTest('wxa/sec/order/notify_confirm_receive', $payload);
        throw new \RuntimeException('DNS failure unexpectedly retried');
    } catch (TransportException $expected) {
        checkSdk(count($calls) === 1, 'pre-header DNS failure also makes only one physical POST attempt');
        checkSdk($app->getClient() === $cachedClient && $app->getConfig()->get('http.retry') === true,
            'cached retry-enabled client was not reused or mutated');
    }

    // Positive control: the installed SDK retries pre-header DNS failures when retry=true.
    [$app, $calls] = sdkFixture($dnsThenSuccess);
    $result = $app->getClient()->postJson('wxa/sec/order/notify_confirm_receive', ['json' => $payload])->toArray();
    checkSdk($result === ['errcode' => 0] && count($calls) === 2, 'control fixture proves hidden SDK retry would be detected');

    $platform = new IsolatedOpenPlatform([
        'app_id' => 'wx_isolated_component', 'secret' => 'isolated-fake-secret',
        'token' => 'isolated-fake-token', 'aes_key' => str_repeat('a', 43),
        'http' => ['retry' => true, 'max_retries' => 2],
    ]);
    // This is the same factory path used by CoreWeappService::app() for authorized MiniApps.
    $authorized = $platform->getMiniAppWithRefreshToken('wx_isolated_authorizer', 'isolated-refresh-token');
    $authorizerToken = $authorized->getAccessToken();
    $authorizedCalls = new \ArrayObject();
    $authorized->setHttpClient(new MockHttpClient(function ($method, $url, $options) use ($authorizedCalls) {
        $authorizedCalls[] = [$method, $url, $options];
        return sdkJson(['errcode' => 0]);
    }, 'https://api.weixin.qq.com/'));
    $originalClient = $authorized->getClient();
    CoreWeappService::$application = $authorized;
    checkSdk($service->sendForTest('wxa/sec/order/upload_shipping_info', $payload) === ['errcode' => 0]
        && count($authorizedCalls) === 1, 'real authorized-MiniApp factory branch sends exactly one POST');
    checkSdk($authorizerToken instanceof AuthorizerAccessToken && $authorized->getAccessToken() === $authorizerToken
        && $authorizerToken->getAppId() === 'wx_isolated_authorizer', 'authorized-MiniApp original token provider remains unchanged');
    checkSdk(($authorizedCalls[0][2]['query']['access_token'] ?? '') === 'isolated-authorizer-token',
        'authorized-MiniApp request uses authorizer token rather than component or direct-app token');
    checkSdk($authorized->getClient() === $originalClient && $authorized->getConfig()->get('http.retry') === true,
        'authorized-MiniApp shared client and configuration are preserved');
    checkSdk($platform->authorizerCalls === [['wx_isolated_authorizer', 'isolated-refresh-token']],
        'authorized-MiniApp factory does not introduce a second refresh call');

    echo "PASS {$checks} real-SDK isolated checks; mock HTTP and fake token only, no database/Redis/WeChat access.\n";
}
