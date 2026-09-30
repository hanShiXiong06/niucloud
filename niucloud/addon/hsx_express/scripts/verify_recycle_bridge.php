<?php
declare(strict_types=1);

// 纯内存测试，替身 HTTP Client；不会读取真实账号、连接数据库或请求快递。
namespace app\service\core\site {
    final class CoreSiteService
    {
        public function getAddonKeysBySiteId(int $site): array { return $site === 100005 ? ['hsx_express'] : []; }
    }
}
namespace addon\hsx_express\app\service\core {
    final class Kuaidi100Client
    {
        public static array $calls = [];
        public function request(string $endpoint, string $method, array $param, array $credentials): array
        {
            self::$calls[] = compact('endpoint', 'method', 'param', 'credentials');
            return ['result' => true, 'returnCode' => '200', 'data' => ['taskId' => 'fixture-task']];
        }
    }
}

namespace {
    $base = dirname(__DIR__);
    require $base . '/app/integration/RecycleKuaidi100Transport.php';
    require $base . '/app/listener/RecycleTransportRegistry.php';
    require dirname($base) . '/hsx_recycle/app/service/core/express/ExpressTransportRegistry.php';

    use addon\hsx_express\app\integration\RecycleKuaidi100Transport as Bridge;
    use addon\hsx_express\app\listener\RecycleTransportRegistry as Listener;
    use addon\hsx_express\app\service\core\Kuaidi100Client as Client;
    use addon\hsx_recycle\app\service\core\express\ExpressTransportRegistry as Registry;

    $responses = [];
    $seenContext = [];
    function event($name, $context): array
    {
        global $responses, $seenContext;
        if ($name !== 'HsxExpressTransportRegistry') throw new \RuntimeException('unexpected event');
        $seenContext = $context;
        return $responses;
    }
    $checks = 0;
    function check($ok, $label): void
    {
        global $checks;
        if (!$ok) throw new \RuntimeException('FAIL ' . $label);
        $checks++;
    }
    function reject(callable $action, string $label): void
    {
        try { $action(); } catch (\Throwable $e) { check(true, $label); return; }
        throw new \RuntimeException('FAIL expected rejection: ' . $label);
    }

    check(Registry::resolve('kuaidi100', 100005, 'production') === null, 'no optional plugin preserves built-in transport');
    $listener = new Listener();
    check($listener->handle(['site_id' => 100005, 'environment' => 'sandbox']) === [], 'sandbox stays in existing isolated pickup transport');
    check($listener->handle(['site_id' => 0, 'environment' => 'production']) === [], 'missing tenant is not registered');
    check($listener->handle(['site_id' => 100024, 'environment' => 'production']) === [], 'unlicensed tenant keeps existing pickup transport');
    $registration = $listener->handle(['site_id' => 100005, 'environment' => 'production']);
    check(Client::$calls === [], 'registration does not submit network actions');
    $responses = [$registration];
    $transport = Registry::resolve('kuaidi100', 100005, 'production');
    check(is_callable($transport), 'one handler resolved');
    check($seenContext['site_id'] === 100005, 'site forwarded into registration');
    check(Registry::resolve('yisu', 100005, 'production') === null, 'other supplier unchanged');
    $config = ['environment' => 'production', 'mode' => 'online', 'api_key' => 'fixture-recycle-key', 'secret' => 'fixture-recycle-secret', 'timeout' => 8];
    $result = $transport($config, 'bOrder', ['thirdOrderId' => 'fixture-order']);
    check(count(Client::$calls) === 1, 'one business request calls one handler once');
    check($result['data']['taskId'] === 'fixture-task', 'raw response remains available to recycle normalization');
    check(Client::$calls[0]['endpoint'] === 'https://poll.kuaidi100.com/order/borderapi.do', 'online endpoint');
    check(Client::$calls[0]['credentials']['key'] === 'fixture-recycle-key', 'uses recycle original account, not mall account');
    check(Client::$calls[0]['credentials']['timeout'] === 8, 'keeps configured timeout');
    Bridge::send(array_replace($config, ['mode' => 'offline']), 'cOrder', []);
    check(Client::$calls[1]['endpoint'] === 'https://order.kuaidi100.com/order/corderapi.do', 'offline endpoint');
    $responses = [$registration, $registration];
    reject(fn() => Registry::resolve('kuaidi100', 100005, 'production'), 'duplicate registration fails before network');
    $responses = [['transports' => ['kuaidi100' => 'MissingTransport']]];
    reject(fn() => Registry::resolve('kuaidi100', 100005, 'production'), 'missing extension fails visibly');
    reject(fn() => Bridge::send(array_replace($config, ['environment' => 'sandbox']), 'bOrder', []), 'no production dispatch for sandbox');
    reject(fn() => Bridge::send(array_replace($config, ['mode' => 'bad']), 'bOrder', []), 'invalid mode does not silently choose endpoint');
    check(count(Client::$calls) === 2, 'invalid and duplicate attempts made zero requests');
    echo "PASS: {$checks} pickup bridge checks; no external IO.\n";
}
