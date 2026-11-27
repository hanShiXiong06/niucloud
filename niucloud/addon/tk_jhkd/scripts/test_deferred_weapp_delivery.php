<?php
declare(strict_types=1);

// php -n addon/tk_jhkd/scripts/test_deferred_weapp_delivery.php
// 真实 DeferredWeappDelivery、ThinkPHP Event/Channel/File；runtime/业务执行使用内存替身。
// 日志验证写入系统临时目录，检查后删除，不访问生产日志。
// 不加载应用配置，不访问数据库、Redis、微信、物流、支付或退款接口。
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace think {
    class App {
        public $event;
        public function __construct() { $this->event = new Event($this); }
        public function invoke($call, array $params) { return $call(...$params); }
        public function make(string $class) { return new $class(); }
    }
}
namespace think\facade {
    class Log {
        public static $channel;
        public static function error($message, array $context = []): void { self::$channel->error($message, $context); }
    }
}
namespace addon\tk_jhkd\app\service\core {
    class WeappDeliveryService {
        public static array $calls = [];
        public function runDeferred(string $operation, array $params): array {
            self::$calls[] = [$operation, $params];
            return ['status' => 'unknown', 'reason' => '测试：未确认微信结果'];
        }
        public function uploadShippingInfo($params) { throw new \LogicException('不能递归调用公开上报入口'); }
    }
}
namespace {
    $root = dirname(__DIR__, 3);
    require $root . '/vendor/topthink/framework/src/think/Event.php';
    require $root . '/vendor/topthink/framework/src/think/event/HttpEnd.php';
    require dirname(__DIR__) . '/app/service/core/DeferredWeappDelivery.php';
    function app() { return $GLOBALS['deferred_test_app']; }

    class TestDeferredDelivery extends \addon\tk_jhkd\app\service\core\DeferredWeappDelivery {
        public static string $runtimeKind = 'fpm';
        public static array $executions = [], $logs = [];
        public static bool $responseSent = false, $registrationFails = false, $loggerFails = false, $runtimeFails = false, $useDelegate = false;
        public static int $registrations = 0;
        public static function reset(string $runtime = 'fpm'): void {
            static::$pending = []; static::$registered = false; static::$flushing = false;
            self::$runtimeKind = $runtime; self::$executions = self::$logs = [];
            self::$responseSent = self::$registrationFails = self::$loggerFails = self::$runtimeFails = self::$useDelegate = false;
            self::$registrations = 0;
            $GLOBALS['deferred_test_app'] = new \think\App();
            \addon\tk_jhkd\app\service\core\WeappDeliveryService::$calls = [];
        }
        public static function pendingCount(): int { return count(static::$pending); }
        public static function writeRealLog(string $operation, array $params, \Throwable $error): void { parent::logFailure($operation, $params, $error); }
        protected static function runtime(): string {
            if (self::$runtimeFails) throw new \RuntimeException('模拟runtime检查失败');
            return self::$runtimeKind;
        }
        protected static function registerHttpEnd(): void {
            self::$registrations++;
            if (self::$registrationFails) throw new \RuntimeException('模拟事件注册失败');
            parent::registerHttpEnd();
        }
        protected static function execute(string $operation, array $params) {
            self::$executions[] = ['operation' => $operation, 'params' => $params, 'response_sent' => self::$responseSent, 'pending_count' => static::pendingCount()];
            if ($params['order_id'] === 'throw') throw new \RuntimeException('模拟微信上报执行异常');
            if (self::$useDelegate) return parent::execute($operation, $params);
            return ['status' => 'done'];
        }
        protected static function logFailure(string $operation, array $params, \Throwable $error): void {
            self::$logs[] = ['operation' => $operation, 'params' => $params, 'reason' => $error->getMessage()];
            if (self::$loggerFails) throw new \RuntimeException('模拟日志不可写');
        }
    }

    $checks = 0;
    function check(string $name, bool $condition): void {
        global $checks;
        if (!$condition) throw new \RuntimeException($name);
        $checks++; echo 'PASS ' . $name . PHP_EOL;
    }
    function enqueueTestDelivery(string $operation = 'order', array $overrides = []): array {
        return TestDeferredDelivery::enqueue($operation, array_replace(['site_id' => 100005, 'order_id' => 'JH20261112001', 'out_trade_no' => 'PAY_ORIGINAL_001'], $overrides));
    }
    function sendResponseThenEnd(): array {
        TestDeferredDelivery::$responseSent = true;
        return app()->event->trigger('HttpEnd', new \stdClass());
    }

    TestDeferredDelivery::reset();
    $first = enqueueTestDelivery('order', ['openid' => '必须丢弃', 'password' => '必须丢弃', 'payload' => ['private' => '必须丢弃']]);
    check('FPM先登记，不提前执行微信', $first['status'] === 'deferred' && TestDeferredDelivery::$executions === [] && TestDeferredDelivery::pendingCount() === 1);
    check('动态注册框架HttpEnd静态回调', TestDeferredDelivery::$registrations === 1 && app()->event->hasListener(\think\event\HttpEnd::class));
    check('相同业务与原流水在请求内去重', enqueueTestDelivery()['status'] === 'duplicate' && TestDeferredDelivery::pendingCount() === 1);
    enqueueTestDelivery('add', ['out_trade_no' => 'ADD_PAY_002']);
    enqueueTestDelivery('receive');
    check('不同操作保持独立且只注册一次', TestDeferredDelivery::pendingCount() === 3 && TestDeferredDelivery::$registrations === 1);
    $laterListener = 0;
    app()->event->listen(\think\event\HttpEnd::class, function ($event) use (&$laterListener) { $laterListener++; return 'other_listener'; });
    $results = sendResponseThenEnd();
    check('真实Think事件仅在响应发送后调用执行器', count(TestDeferredDelivery::$executions) === 3 && array_column(TestDeferredDelivery::$executions, 'response_sent') === [true, true, true]);
    check('执行前清空请求内待办', array_column(TestDeferredDelivery::$executions, 'pending_count') === [0, 0, 0] && TestDeferredDelivery::pendingCount() === 0);
    check('仅保存站点、原订单和原支付单号', TestDeferredDelivery::$executions[0]['params'] === ['site_id' => 100005, 'order_id' => 'JH20261112001', 'out_trade_no' => 'PAY_ORIGINAL_001']);
    check('flush返回null，不阻止其他结束监听', $results[0] === null && $laterListener === 1);
    TestDeferredDelivery::flush(new \stdClass());
    check('重复HttpEnd不重复执行任务', count(TestDeferredDelivery::$executions) === 3);

    TestDeferredDelivery::reset();
    enqueueTestDelivery('order', ['order_id' => 123]);
    check('数字和字符串订单号统一去重', enqueueTestDelivery('order', ['order_id' => '123'])['status'] === 'duplicate');
    enqueueTestDelivery('order', ['order_id' => '123', 'out_trade_no' => 'PAY_OTHER']);
    check('不同原流水不误去重', TestDeferredDelivery::pendingCount() === 2);

    TestDeferredDelivery::reset();
    enqueueTestDelivery('order', ['order_id' => 'throw']); enqueueTestDelivery('add');
    TestDeferredDelivery::$loggerFails = true;
    $otherExecuted = false;
    app()->event->listen(\think\event\HttpEnd::class, function () use (&$otherExecuted) { $otherExecuted = true; });
    sendResponseThenEnd();
    check('单项执行失败不丢掉后续任务', count(TestDeferredDelivery::$executions) === 2 && TestDeferredDelivery::$executions[1]['operation'] === 'add');
    check('日志失败也不阻断其他HttpEnd监听', $otherExecuted && count(TestDeferredDelivery::$logs) === 1);
    check('失败任务无内存残留', TestDeferredDelivery::pendingCount() === 0);

    TestDeferredDelivery::reset('http'); enqueueTestDelivery(); sendResponseThenEnd();
    check('非FPM环境不回退同步外调', TestDeferredDelivery::$executions === [] && TestDeferredDelivery::pendingCount() === 0);
    check('不支持环境留明确核实日志', strpos(TestDeferredDelivery::$logs[0]['reason'], 'unsupported_runtime') !== false && strpos(TestDeferredDelivery::$logs[0]['reason'], 'fastcgi_finish_request') !== false);

    TestDeferredDelivery::reset('cli'); TestDeferredDelivery::$useDelegate = true;
    $cli = enqueueTestDelivery();
    check('CLI直接调用runDeferred，不等待HttpEnd', $cli['status'] === 'executed' && $cli['deferred'] === false && TestDeferredDelivery::$registrations === 0 && TestDeferredDelivery::pendingCount() === 0);
    check('CLI执行委托不走公开入口且保留未知微信结果', $cli['result']['status'] === 'unknown' && count(\addon\tk_jhkd\app\service\core\WeappDeliveryService::$calls) === 1);
    $cliFailed = enqueueTestDelivery('order', ['order_id' => 'throw']);
    check('CLI业务异常只记日志，不破坏原调用方', $cliFailed['status'] === 'failed' && count(TestDeferredDelivery::$logs) === 1);

    TestDeferredDelivery::reset(); TestDeferredDelivery::$registrationFails = true;
    check('事件注册失败无同步fallback', enqueueTestDelivery()['status'] === 'failed' && TestDeferredDelivery::$executions === [] && TestDeferredDelivery::pendingCount() === 0);
    TestDeferredDelivery::$registrationFails = false;
    check('事件注册失败后允许再次正常登记', enqueueTestDelivery()['status'] === 'deferred' && TestDeferredDelivery::pendingCount() === 1);
    TestDeferredDelivery::$runtimeFails = true;
    TestDeferredDelivery::flush();
    check('结束环境检查异常全兜底且清待办', TestDeferredDelivery::pendingCount() === 0 && TestDeferredDelivery::$executions === []);

    foreach ([['site_id' => 0], ['site_id' => true], ['site_id' => '1.5'], ['order_id' => []], ['out_trade_no' => new \stdClass()], ['order_id' => ''], ['order_id' => "JH\n001"], ['out_trade_no' => str_repeat('x', 129)]] as $bad) {
        TestDeferredDelivery::reset();
        check('无效参数拒绝且不执行 ' . json_encode($bad), enqueueTestDelivery('order', $bad)['status'] === 'rejected' && TestDeferredDelivery::$executions === [] && TestDeferredDelivery::pendingCount() === 0);
    }
    TestDeferredDelivery::reset();
    check('仅允许order/add/receive操作', enqueueTestDelivery('refund')['status'] === 'rejected');
    check('收货提醒必须有原支付流水', enqueueTestDelivery('receive', ['out_trade_no' => ''])['status'] === 'rejected');
    check('收货提醒可仅携带原支付流水', enqueueTestDelivery('receive', ['order_id' => ''])['status'] === 'deferred');

    // 真实 Channel::record + File::save，不能只断言 mock 收到了 context。
    require $root . '/vendor/psr/log/src/LoggerInterface.php';
    require $root . '/vendor/psr/log/src/LoggerTrait.php';
    require $root . '/vendor/psr/log/src/LogLevel.php';
    require $root . '/vendor/topthink/framework/src/think/contract/LogHandlerInterface.php';
    require $root . '/vendor/topthink/framework/src/think/event/LogRecord.php';
    require $root . '/vendor/topthink/framework/src/think/event/LogWrite.php';
    require $root . '/vendor/topthink/framework/src/think/log/Channel.php';
    require $root . '/vendor/topthink/framework/src/think/log/driver/File.php';
    $logDir = sys_get_temp_dir() . '/jhkd-deferred-test-' . bin2hex(random_bytes(6));
    try {
        $driver = new \think\log\driver\File(app(), ['path' => $logDir, 'single' => 'deferred']);
        $channel = new \think\log\Channel('deferred_test', $driver, [], true, app()->event);
        \think\facade\Log::$channel = $channel;
        TestDeferredDelivery::writeRealLog('order', ['site_id' => 100005, 'order_id' => 'JH_LOG_001', 'out_trade_no' => 'PAY_ORIGINAL_001'],
            new \RuntimeException('unsupported_runtime：当前 HTTP 环境不支持 fastcgi_finish_request，未同步调用微信；请使用 PHP-FPM 并核实发货上报'));
        TestDeferredDelivery::writeRealLog('add', ['site_id' => 100005, 'order_id' => 'JH_LOG_002', 'out_trade_no' => 'PAY_ADD_002'],
            new \RuntimeException('https://api.example.com?access_token=do-not-log-this-token secret=do-not-log-this-secret'));
        check('真实Think日志通道成功保存', $channel->save());
        $savedLog = file_get_contents($logDir . '/deferred.log');
        check('落盘日志包含站点、订单、原流水与原因', strpos($savedLog, '100005') !== false && strpos($savedLog, 'JH_LOG_001') !== false
            && strpos($savedLog, 'PAY_ORIGINAL_001') !== false && strpos($savedLog, 'unsupported_runtime') !== false && strpos($savedLog, 'fastcgi_finish_request') !== false);
        check('任意底层异常不泄露URL或凭据', strpos($savedLog, 'do-not-log-this-token') === false && strpos($savedLog, 'do-not-log-this-secret') === false
            && strpos($savedLog, 'api.example.com') === false && strpos($savedLog, '异常明文已隐藏') !== false && strpos($savedLog, 'JH_LOG_002') !== false);
    } finally {
        if (is_file($logDir . '/deferred.log')) unlink($logDir . '/deferred.log');
        if (is_dir($logDir)) rmdir($logDir);
    }
    echo '完成：' . $checks . ' 项响应后调度隔离检查；未访问数据库、Redis、微信、物流或资金接口。' . PHP_EOL;
}
