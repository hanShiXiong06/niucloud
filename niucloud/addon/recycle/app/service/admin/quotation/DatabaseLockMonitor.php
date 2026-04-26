<?php
/**
 * 数据库锁监控诊断脚本
 *
 * 使用方法:
 * php think run /path/to/check_database_locks.php
 *
 * 或者在命令行直接运行:
 * php check_database_locks.php
 */

namespace addon\recycle\app\service\admin\quotation;

use think\facade\Db;
use think\facade\Log;

class DatabaseLockMonitor
{
    /**
     * 执行完整的数据库锁检查
     */
    public static function runFullCheck(): array
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'locks' => [],
            'transactions' => [],
            'processes' => [],
            'recommendations' => [],
        ];

        echo "\n=== 数据库锁诊断报告 ===\n";
        echo "生成时间: {$report['timestamp']}\n\n";

        // 1. 检查锁等待
        echo "1. 检查锁等待情况...\n";
        $report['locks'] = self::checkLockWaits();

        // 2. 检查长时间运行的事务
        echo "\n2. 检查长时间运行的事务...\n";
        $report['transactions'] = self::checkLongRunningTransactions();

        // 3. 检查进程列表
        echo "\n3. 检查当前数据库进程...\n";
        $report['processes'] = self::checkProcessList();

        // 4. 生成建议
        echo "\n4. 生成诊断建议...\n";
        $report['recommendations'] = self::generateRecommendations($report);

        // 5. 输出报告
        self::printReport($report);

        return $report;
    }

    /**
     * 检查锁等待情况
     */
    private static function checkLockWaits(): array
    {
        try {
            // MySQL 8.0+ 使用新的表结构
            $locks = Db::query("
                SELECT
                    waiting_pid AS waiting_thread,
                    waiting_query,
                    blocking_pid AS blocking_thread,
                    blocking_query,
                    wait_age AS wait_duration
                FROM sys.innodb_lock_waits
            ");

            if (empty($locks)) {
                // 尝试旧版本的查询
                $locks = Db::query("
                    SELECT
                        r.trx_mysql_thread_id AS waiting_thread,
                        r.trx_query AS waiting_query,
                        b.trx_mysql_thread_id AS blocking_thread,
                        b.trx_query AS blocking_query,
                        TIMESTAMPDIFF(SECOND, r.trx_wait_started, NOW()) AS wait_duration
                    FROM information_schema.innodb_lock_waits w
                    INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
                    INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id
                ");
            }

            if (!empty($locks)) {
                echo "   ⚠️  发现 " . count($locks) . " 个锁等待!\n";
                foreach ($locks as $lock) {
                    echo "   - 等待线程: {$lock['waiting_thread']}\n";
                    echo "     被阻塞线程: {$lock['blocking_thread']}\n";
                    echo "     等待时长: {$lock['wait_duration']}秒\n";
                }
            } else {
                echo "   ✓ 没有发现锁等待\n";
            }

            return $locks;
        } catch (\Exception $e) {
            echo "   ✗ 检查失败: {$e->getMessage()}\n";
            return [];
        }
    }

    /**
     * 检查长时间运行的事务
     */
    private static function checkLongRunningTransactions(int $thresholdSeconds = 30): array
    {
        try {
            $transactions = Db::query("
                SELECT
                    trx_id,
                    trx_state,
                    trx_started,
                    trx_mysql_thread_id,
                    LEFT(trx_query, 100) AS trx_query,
                    TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_seconds
                FROM information_schema.innodb_trx
                WHERE TIMESTAMPDIFF(SECOND, trx_started, NOW()) > ?
                ORDER BY trx_started
            ", [$thresholdSeconds]);

            if (!empty($transactions)) {
                echo "   ⚠️  发现 " . count($transactions) . " 个长时间运行的事务 (>{$thresholdSeconds}秒)!\n";
                foreach ($transactions as $trx) {
                    echo "   - 线程ID: {$trx['trx_mysql_thread_id']}\n";
                    echo "     状态: {$trx['trx_state']}\n";
                    echo "     运行时长: {$trx['duration_seconds']}秒\n";
                    echo "     查询: " . ($trx['trx_query'] ?: '(无)') . "\n";
                }
            } else {
                echo "   ✓ 没有发现长时间运行的事务\n";
            }

            return $transactions;
        } catch (\Exception $e) {
            echo "   ✗ 检查失败: {$e->getMessage()}\n";
            return [];
        }
    }

    /**
     * 检查进程列表
     */
    private static function checkProcessList(int $timeThreshold = 10): array
    {
        try {
            $processes = Db::query("
                SELECT
                    ID,
                    USER,
                    HOST,
                    DB,
                    COMMAND,
                    TIME,
                    STATE,
                    LEFT(INFO, 100) AS INFO
                FROM information_schema.PROCESSLIST
                WHERE COMMAND != 'Sleep' AND TIME > ?
                ORDER BY TIME DESC
                LIMIT 20
            ", [$timeThreshold]);

            if (!empty($processes)) {
                echo "   ⚠️  发现 " . count($processes) . " 个活跃进程 (运行>{$timeThreshold}秒)!\n";
                foreach ($processes as $proc) {
                    echo "   - 进程ID: {$proc['ID']}, 用户: {$proc['USER']}\n";
                    echo "     命令: {$proc['COMMAND']}, 状态: {$proc['STATE']}\n";
                    echo "     运行时长: {$proc['TIME']}秒\n";
                    echo "     查询: " . ($proc['INFO'] ?: '(无)') . "\n";
                }
            } else {
                echo "   ✓ 没有发现长时间运行的进程\n";
            }

            return $processes;
        } catch (\Exception $e) {
            echo "   ✗ 检查失败: {$e->getMessage()}\n";
            return [];
        }
    }

    /**
     * 生成诊断建议
     */
    private static function generateRecommendations(array $report): array
    {
        $recommendations = [];

        // 如果有锁等待
        if (!empty($report['locks'])) {
            $recommendations[] = [
                'type' => 'critical',
                'message' => '发现数据库锁等待,可能需要终止阻塞的进程',
                'action' => '使用 KILL {blocking_thread_id} 命令终止阻塞的进程',
            ];
        }

        // 如果有长时间运行的事务
        if (!empty($report['transactions'])) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => '发现长时间运行的事务,可能导致锁积累',
                'action' => '检查应用代码,确保事务尽快提交或回滚',
            ];
        }

        // 如果有长时间运行的查询
        if (!empty($report['processes'])) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => '发现长时间运行的查询,可能影响性能',
                'action' => '优化慢查询,考虑添加索引或重写查询',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => '数据库运行正常,未发现明显问题',
                'action' => '继续监控',
            ];
        }

        return $recommendations;
    }

    /**
     * 打印完整报告
     */
    private static function printReport(array $report): void
    {
        echo "\n=== 诊断建议 ===\n";
        foreach ($report['recommendations'] as $rec) {
            $prefix = $rec['type'] === 'critical' ? '🔴' : ($rec['type'] === 'warning' ? '⚠️' : '✓');
            echo "{$prefix} {$rec['message']}\n";
            echo "   建议: {$rec['action']}\n\n";
        }

        // 记录到日志
        Log::info('数据库锁诊断报告', $report);
    }

    /**
     * 终止指定的进程
     */
    public static function killProcess(int $processId): bool
    {
        try {
            Db::execute("KILL ?", [$processId]);
            echo "✓ 已终止进程 {$processId}\n";
            Log::warning("手动终止数据库进程", ['process_id' => $processId]);
            return true;
        } catch (\Exception $e) {
            echo "✗ 终止进程失败: {$e->getMessage()}\n";
            return false;
        }
    }
}

// 如果直接运行此脚本
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'check_database_locks.php') {
    require_once __DIR__ . '/../../../../../../../../../vendor/autoload.php';
    DatabaseLockMonitor::runFullCheck();
}
