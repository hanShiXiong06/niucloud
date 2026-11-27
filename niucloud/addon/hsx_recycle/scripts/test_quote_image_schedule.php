<?php
declare(strict_types=1);

// 真实 Job + 内存服务替身：验证计划任务返回值可由框架直接写入 TEXT 日志。
// 不启动定时器，不访问数据库、Redis、网络或云存储。
namespace core\base { abstract class BaseJob {} }
namespace addon\hsx_recycle\app\service\core\quote_image {
    class QuoteImageSyncService
    {
        public static array $result = [];
        public static int $calls = 0;
        public static ?\Throwable $error = null;
        public function tick(): array
        {
            self::$calls++;
            if (self::$error) throw self::$error;
            return self::$result;
        }
    }
}
namespace {
    use addon\hsx_recycle\app\job\schedule\QuoteImageSyncTick;
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncService;

    require __DIR__ . '/../app/job/schedule/QuoteImageSyncTick.php';
    $checks = 0;
    function check(string $name, callable $operation): void
    {
        global $checks;
        $operation();
        $checks++;
        echo 'PASS ' . $name . PHP_EOL;
    }
    function same($actual, $expected): void
    {
        if ($actual !== $expected) throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $job = new QuoteImageSyncTick();
    check('关联统计返回 JSON 字符串，不触发框架 ORM 数组表达式分支', static function () use ($job): void {
        QuoteImageSyncService::$result = ['started' => 1, 'failed' => 0];
        $result = $job->doJob();
        same(is_string($result), true);
        same(is_array($result), false);
        same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), QuoteImageSyncService::$result);
    });
    check('没有符合条件的站点仍保留零项统计', static function () use ($job): void {
        QuoteImageSyncService::$result = ['started' => 0, 'failed' => 0];
        same($job->doJob(), '{"started":0,"failed":0}');
    });
    check('队列关闭的中文原因可正常写入日志', static function () use ($job): void {
        QuoteImageSyncService::$result = ['started' => 0, 'failed' => 0, 'message' => '异步队列未开启，未发起自动采集'];
        $result = $job->doJob();
        same(strpos($result, '异步队列未开启') !== false, true);
        same(json_decode($result, true, 512, JSON_THROW_ON_ERROR), QuoteImageSyncService::$result);
    });
    check('每次计划执行只调用一次 tick', static function (): void {
        same(QuoteImageSyncService::$calls, 3);
    });
    check('服务异常继续交给框架记录失败，不伪装执行成功', static function () use ($job): void {
        QuoteImageSyncService::$error = new \RuntimeException('fixture failure');
        try { $job->doJob(); }
        catch (\RuntimeException $error) { same($error->getMessage(), 'fixture failure'); return; }
        throw new \RuntimeException('Expected service failure');
    });
    echo '完成：' . $checks . ' 项计划任务日志契约检查；未访问外部服务或业务数据。' . PHP_EOL;
}
