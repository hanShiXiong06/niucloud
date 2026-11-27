<?php
declare(strict_types=1);

namespace addon\tk_jhkd\app\service\core;

use think\event\HttpEnd;
use think\facade\Log;

/**
 * 微信发货附属上报在 HTTP 响应发送后执行，避免微信慢请求拖住物流/支付回调。
 *
 * 这不是持久队列：仍占用当前 FPM 进程，进程被终止时内存任务无法保证送达。
 * 计费、退款、订单状态等关键业务不得放入本类；执行结果由原业务服务记录。
 */
class DeferredWeappDelivery
{
    protected static array $pending = [];
    protected static bool $registered = false;
    protected static bool $flushing = false;

    /**
     * 仅保留业务定位参数，执行时重新读取已提交的订单及原支付流水。
     * executed 表示已调用业务服务，不表示微信已确认成功。
     */
    public static function enqueue(string $operation, array $params): array
    {
        try {
            $clean = static::cleanParams($operation, $params);
        } catch (\Throwable $error) {
            static::reportFailure($operation, [], $error);
            return ['status' => 'rejected', 'deferred' => false];
        }

        try {
            if (static::runtime() === 'cli') {
                return ['status' => 'executed', 'deferred' => false, 'result' => static::execute($operation, $clean)];
            }
            if (static::$flushing) {
                throw new \RuntimeException('微信发货后置处理期间不能递归登记同类任务');
            }
            $key = hash('sha256', json_encode([$operation, $clean], JSON_THROW_ON_ERROR));
            if (isset(static::$pending[$key])) return ['status' => 'duplicate', 'deferred' => true];

            // 只登记请求内任务；禁止通过 Dispatch 在队列关闭时回退为同步执行。
            static::$pending[$key] = ['operation' => $operation, 'params' => $clean];
            try {
                if (!static::$registered) {
                    static::registerHttpEnd();
                    static::$registered = true;
                }
            } catch (\Throwable $error) {
                unset(static::$pending[$key]);
                throw $error;
            }
            return ['status' => 'deferred', 'deferred' => true];
        } catch (\Throwable $error) {
            static::reportFailure($operation, $clean, $error);
            return ['status' => 'failed', 'deferred' => false];
        }
    }

    /**
     * 只由框架 HttpEnd 在 Response::send() 之后调用；每项错误均不能改变已发响应。
     * 动态注册无需刷新插件事件缓存，后续其他 HttpEnd listener 仍应正常执行。
     */
    public static function flush($event = null): void
    {
        if (static::$flushing) return;
        $tasks = static::$pending;
        static::$pending = []; // 先清再执行，防重复 HttpEnd 或任务异常残留。
        if (!$tasks) return;

        try {
            $runtime = static::runtime();
        } catch (\Throwable $error) {
            foreach ($tasks as $task) static::reportFailure($task['operation'], $task['params'], $error);
            return;
        }
        if ($runtime !== 'fpm') {
            $error = new \RuntimeException('unsupported_runtime：当前 HTTP 环境不支持 fastcgi_finish_request，未同步调用微信；请使用 PHP-FPM 并核实发货上报');
            foreach ($tasks as $task) static::reportFailure($task['operation'], $task['params'], $error);
            return;
        }

        static::$flushing = true;
        try {
            foreach ($tasks as $task) {
                try {
                    static::execute($task['operation'], $task['params']);
                } catch (\Throwable $error) {
                    static::reportFailure($task['operation'], $task['params'], $error);
                }
            }
        } catch (\Throwable $error) {
            // 保护 HttpEnd：任何附属上报错误都不能中断框架日志及其他结束监听。
            static::reportFailure('flush', [], $error);
        } finally {
            static::$flushing = false;
        }
    }

    protected static function cleanParams(string $operation, array $params): array
    {
        if (!in_array($operation, ['order', 'add', 'receive'], true)) throw new \InvalidArgumentException('不支持的微信发货上报操作');
        $siteId = $params['site_id'] ?? null;
        if ((!is_int($siteId) && !is_string($siteId)) || !preg_match('/^[1-9][0-9]*$/D', (string)$siteId)
            || filter_var($siteId, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('微信发货上报缺少有效站点');
        }
        $clean = ['site_id' => (int)$siteId, 'order_id' => '', 'out_trade_no' => ''];
        foreach (['order_id', 'out_trade_no'] as $field) {
            $value = $params[$field] ?? '';
            if (!is_string($value) && !is_int($value)) throw new \InvalidArgumentException('微信发货上报业务编号无效');
            $value = trim((string)$value);
            if (strlen($value) > 128 || preg_match('/[\x00-\x20\x7f]/', $value)) throw new \InvalidArgumentException('微信发货上报业务编号格式无效');
            $clean[$field] = $value;
        }
        if (($operation === 'receive' && $clean['out_trade_no'] === '')
            || ($operation !== 'receive' && $clean['order_id'] === '')) {
            throw new \InvalidArgumentException('微信发货上报缺少原业务编号');
        }
        return $clean;
    }

    /** 与浏览器请求区分；CLI/队列不等待不存在的 HttpEnd。 */
    protected static function runtime(): string
    {
        if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) return 'cli';
        return PHP_SAPI === 'fpm-fcgi' && function_exists('fastcgi_finish_request') ? 'fpm' : 'http';
    }

    protected static function registerHttpEnd(): void
    {
        app()->event->listen(HttpEnd::class, [static::class, 'flush']);
    }

    protected static function execute(string $operation, array $params)
    {
        // 不调用公开 upload 方法，避免再次进入 enqueue 形成递归。
        return (new WeappDeliveryService())->runDeferred($operation, $params);
    }

    protected static function logFailure(string $operation, array $params, \Throwable $error): void
    {
        // 不记录任意底层异常原文；HTTP 客户端异常可能带 access_token、Secret 或完整URL。
        $knownReasons = [
            '不支持的微信发货上报操作',
            '微信发货上报缺少有效站点',
            '微信发货上报业务编号无效',
            '微信发货上报业务编号格式无效',
            '微信发货上报缺少原业务编号',
            '微信发货后置处理期间不能递归登记同类任务',
            'unsupported_runtime：当前 HTTP 环境不支持 fastcgi_finish_request，未同步调用微信；请使用 PHP-FPM 并核实发货上报',
        ];
        $reason = in_array($error->getMessage(), $knownReasons, true) ? $error->getMessage()
            : get_class($error) . '：后置执行异常，异常明文已隐藏，请核对该订单的微信上报日志';
        $context = [
            'operation' => $operation,
            'site_id' => $params['site_id'] ?? 0,
            'order_id' => $params['order_id'] ?? '',
            'out_trade_no' => $params['out_trade_no'] ?? '',
            'reason' => substr($reason, 0, 1000),
        ];
        // ThinkPHP 的 context 只用于 {占位} 替换，不会自动附加到日志正文。
        Log::error('聚合快递微信发货后置处理未完成 ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    private static function reportFailure(string $operation, array $params, \Throwable $error): void
    {
        try { static::logFailure($operation, $params, $error); }
        catch (\Throwable $ignored) { /* 日志故障也不能改变已经完成的财务/物流业务。 */ }
    }
}
