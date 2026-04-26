<?php
/**
 * 数据库锁优化效果测试脚本
 *
 * 使用方法:
 * 1. 在执行爬虫任务前运行: php test_optimization.php before
 * 2. 在执行爬虫任务时运行: php test_optimization.php during
 * 3. 在执行爬虫任务后运行: php test_optimization.php after
 */

// require_once __DIR__ . '/../../../../../../../../../vendor/autoload.php';
namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\service\admin\quotation\DatabaseHelper;
use addon\recycle\app\service\admin\quotation\DatabaseLockMonitor;
use think\facade\Db;

class OptimizationTest
{
    public static function testBefore()
    {
        echo "=== 执行前检查 ===\n";
        echo "检查数据库连接...\n";

        if (DatabaseHelper::reconnectIfNeeded()) {
            echo "✓ 数据库连接正常\n";
        } else {
            echo "✗ 数据库连接失败\n";
            return;
        }

        echo "\n检查当前锁状态...\n";
        DatabaseLockMonitor::runFullCheck();

        echo "\n✓ 执行前检查完成,可以开始执行爬虫任务\n";
    }

    public static function testDuring()
    {
        echo "=== 执行中监控 ===\n";

        for ($i = 0; $i < 5; $i++) {
            echo "\n--- 第 " . ($i + 1) . " 次检查 ---\n";

            // 检查锁
            $locks = DatabaseHelper::checkLocks();
            if (!empty($locks)) {
                echo "⚠️  发现锁等待!\n";
                foreach ($locks as $lock) {
                    echo "   等待线程: {$lock['waiting_thread']}\n";
                    echo "   阻塞线程: {$lock['blocking_thread']}\n";
                }
            } else {
                echo "✓ 无锁等待\n";
            }

            // 检查事务
            $transactions = DatabaseHelper::getRunningTransactions();
            if (!empty($transactions)) {
                echo "\n当前运行事务数: " . count($transactions) . "\n";
                foreach ($transactions as $trx) {
                    echo "   线程: {$trx['trx_mysql_thread_id']}, 运行时长: {$trx['duration_seconds']}秒\n";
                }
            } else {
                echo "✓ 无运行中的事务\n";
            }

            if ($i < 4) {
                echo "\n等待10秒后继续监控...\n";
                sleep(10);
            }
        }

        echo "\n✓ 监控完成\n";
    }

    public static function testAfter()
    {
        echo "=== 执行后检查 ===\n";

        echo "\n1. 检查是否还有残留的锁...\n";
        $locks = DatabaseHelper::checkLocks();
        if (empty($locks)) {
            echo "✓ 无残留锁\n";
        } else {
            echo "⚠️  发现残留锁,数量: " . count($locks) . "\n";
        }

        echo "\n2. 检查是否还有未提交的事务...\n";
        $transactions = DatabaseHelper::getRunningTransactions();
        if (empty($transactions)) {
            echo "✓ 无未提交事务\n";
        } else {
            echo "⚠️  发现未提交事务,数量: " . count($transactions) . "\n";
        }

        echo "\n3. 检查数据完整性...\n";
        try {
            // 检查今天的数据
            $today = date('Y-m-d');
            $count = Db::table('recycle_quotation_data')
                ->where('price_date', $today)
                ->where('is_current', 1)
                ->count();

            echo "✓ 今日报价数据: {$count} 条\n";

            // 检查是否有重复数据
            $duplicates = Db::query("
                SELECT
                    quotation_id,
                    price_name,
                    model_id,
                    capacity_id,
                    grade_spec_id,
                    COUNT(*) as count
                FROM recycle_quotation_data
                WHERE price_date = ? AND is_current = 1
                GROUP BY quotation_id, price_name, model_id, capacity_id, grade_spec_id
                HAVING count > 1
            ", [$today]);

            if (empty($duplicates)) {
                echo "✓ 无重复数据\n";
            } else {
                echo "⚠️  发现重复数据,数量: " . count($duplicates) . "\n";
            }
        } catch (\Exception $e) {
            echo "✗ 检查数据失败: {$e->getMessage()}\n";
        }

        echo "\n✓ 执行后检查完成\n";
    }

    public static function comparePerformance()
    {
        echo "=== 性能对比测试 ===\n";

        // 模拟批量插入
        echo "\n测试1: 批量插入1000条数据\n";

        $testData = [];
        for ($i = 0; $i < 1000; $i++) {
            $testData[] = [
                'site_id' => 1,
                'quotation_id' => 114,
                'price_name' => '测试',
                'model_id' => 1,
                'capacity_id' => 1,
                'grade_spec_id' => 1,
                'price' => 100.00,
                'price_date' => date('Y-m-d'),
                'is_current' => 0, // 测试数据标记为非当前
                'create_at' => time(),
                'update_at' => time(),
            ];
        }

        // 方式1: 一次性插入(旧方式)
        $start = microtime(true);
        try {
            Db::startTrans();
            Db::table('recycle_quotation_data')->insertAll($testData);
            Db::commit();
            $time1 = microtime(true) - $start;
            echo "一次性插入: " . round($time1 * 1000, 2) . "ms\n";

            // 清理测试数据
            Db::table('recycle_quotation_data')->where('is_current', 0)->delete();
        } catch (\Exception $e) {
            Db::rollback();
            echo "一次性插入失败: {$e->getMessage()}\n";
        }

        // 方式2: 分批插入(新方式)
        $start = microtime(true);
        try {
            $chunks = array_chunk($testData, 100);
            foreach ($chunks as $chunk) {
                Db::startTrans();
                Db::table('recycle_quotation_data')->insertAll($chunk);
                Db::commit();
                usleep(10000);
            }
            $time2 = microtime(true) - $start;
            echo "分批插入(100条/批): " . round($time2 * 1000, 2) . "ms\n";

            // 清理测试数据
            Db::table('recycle_quotation_data')->where('is_current', 0)->delete();

            echo "\n对比结果:\n";
            if ($time2 > $time1) {
                $diff = (($time2 - $time1) / $time1) * 100;
                echo "分批插入比一次性插入慢 " . round($diff, 2) . "%\n";
                echo "但是: 分批插入减少了锁持有时间,提高了并发性能\n";
            } else {
                echo "分批插入性能相当或更好\n";
            }
        } catch (\Exception $e) {
            Db::rollback();
            echo "分批插入失败: {$e->getMessage()}\n";
        }

        echo "\n✓ 性能对比完成\n";
    }
}

// 命令行入口
if (php_sapi_name() === 'cli') {
    $mode = $argv[1] ?? 'help';

    switch ($mode) {
        case 'before':
            OptimizationTest::testBefore();
            break;
        case 'during':
            OptimizationTest::testDuring();
            break;
        case 'after':
            OptimizationTest::testAfter();
            break;
        case 'compare':
            OptimizationTest::comparePerformance();
            break;
        default:
            echo "使用方法:\n";
            echo "  php test_optimization.php before   - 执行前检查\n";
            echo "  php test_optimization.php during   - 执行中监控\n";
            echo "  php test_optimization.php after    - 执行后检查\n";
            echo "  php test_optimization.php compare  - 性能对比测试\n";
    }
}
