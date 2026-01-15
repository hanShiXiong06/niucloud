<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use think\facade\Db;
use think\facade\Log;

/**
 * 数据库连接管理助手类
 * 用于优化长时间运行的脚本中的数据库连接管理
 */
class DatabaseHelper
{
    /**
     * 设置数据库连接超时参数
     * 在执行长时间运行的任务前调用
     *
     * @param int $waitTimeout 等待超时时间(秒),默认300秒(5分钟)
     * @param int $interactiveTimeout 交互超时时间(秒),默认300秒(5分钟)
     * @return void
     */
    public static function setConnectionTimeout(int $waitTimeout = 300, int $interactiveTimeout = 300): void
    {
        try {
            // 设置MySQL连接超时参数,防止长时间执行导致连接超时
            Db::execute("SET SESSION wait_timeout = ?", [$waitTimeout]);
            Db::execute("SET SESSION interactive_timeout = ?", [$interactiveTimeout]);

            Log::info('数据库连接超时参数已设置', [
                'wait_timeout' => $waitTimeout,
                'interactive_timeout' => $interactiveTimeout,
            ]);
        } catch (\Exception $e) {
            Log::warning('设置数据库连接超时失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查并重新连接数据库(如果连接已断开)
     *
     * @return bool 是否成功连接
     */
    public static function reconnectIfNeeded(): bool
    {
        try {
            // 执行简单查询检测连接
            Db::query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            Log::warning('数据库连接已断开,尝试重新连接', [
                'error' => $e->getMessage(),
            ]);

            try {
                // 关闭旧连接
                Db::close();

                // 重新连接
                Db::connect();

                Log::info('数据库重新连接成功');
                return true;
            } catch (\Exception $reconnectException) {
                Log::error('数据库重新连接失败', [
                    'error' => $reconnectException->getMessage(),
                    'trace' => $reconnectException->getTraceAsString(),
                ]);
                return false;
            }
        }
    }

    /**
     * 显式关闭数据库连接
     * 在长时间运行的脚本结束时调用,确保连接被正确释放
     *
     * @return void
     */
    public static function closeConnection(): void
    {
        try {
            Db::close();
            Log::info('数据库连接已关闭');
        } catch (\Exception $e) {
            Log::warning('关闭数据库连接失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查当前数据库锁情况
     * 用于诊断锁问题
     *
     * @return array 锁信息列表
     */
    public static function checkLocks(): array
    {
        try {
            // 查询InnoDB锁等待情况
            $locks = Db::query("
                SELECT
                    r.trx_id waiting_trx_id,
                    r.trx_mysql_thread_id waiting_thread,
                    r.trx_query waiting_query,
                    b.trx_id blocking_trx_id,
                    b.trx_mysql_thread_id blocking_thread,
                    b.trx_query blocking_query
                FROM information_schema.innodb_lock_waits w
                INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
                INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id
            ");

            if (!empty($locks)) {
                Log::warning('检测到数据库锁等待', [
                    'lock_count' => count($locks),
                    'locks' => $locks,
                ]);
            }

            return $locks;
        } catch (\Exception $e) {
            Log::error('检查数据库锁失败', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 获取当前运行的事务列表
     * 用于诊断长时间运行的事务
     *
     * @return array 事务列表
     */
    public static function getRunningTransactions(): array
    {
        try {
            $transactions = Db::query("
                SELECT
                    trx_id,
                    trx_state,
                    trx_started,
                    trx_mysql_thread_id,
                    trx_query,
                    TIMESTAMPDIFF(SECOND, trx_started, NOW()) as duration_seconds
                FROM information_schema.innodb_trx
                ORDER BY trx_started
            ");

            if (!empty($transactions)) {
                Log::info('当前运行的事务', [
                    'transaction_count' => count($transactions),
                    'transactions' => $transactions,
                ]);
            }

            return $transactions;
        } catch (\Exception $e) {
            Log::error('获取运行事务失败', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 执行带重试的数据库操作
     *
     * @param callable $callback 要执行的数据库操作
     * @param int $maxRetries 最大重试次数
     * @param int $retryDelay 重试延迟(毫秒)
     * @return mixed 操作结果
     * @throws \Exception
     */
    public static function executeWithRetry(callable $callback, int $maxRetries = 3, int $retryDelay = 100)
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;

                // 检查是否是可重试的错误(死锁、锁等待超时等)
                $errorCode = $e->getCode();
                $isRetryable = in_array($errorCode, [
                    1213, // Deadlock found when trying to get lock
                    1205, // Lock wait timeout exceeded
                    2006, // MySQL server has gone away
                    2013, // Lost connection to MySQL server during query
                ]);

                if (!$isRetryable || $attempts >= $maxRetries) {
                    break;
                }

                Log::warning('数据库操作失败,准备重试', [
                    'attempt' => $attempts,
                    'max_retries' => $maxRetries,
                    'error_code' => $errorCode,
                    'error' => $e->getMessage(),
                ]);

                // 如果是连接问题,尝试重连
                if (in_array($errorCode, [2006, 2013])) {
                    self::reconnectIfNeeded();
                }

                // 延迟后重试
                usleep($retryDelay * 1000);
                $retryDelay *= 2; // 指数退避
            }
        }

        // 所有重试都失败,抛出最后一次的异常
        Log::error('数据库操作多次重试后失败', [
            'attempts' => $attempts,
            'error' => $lastException->getMessage(),
        ]);

        throw $lastException;
    }
}
