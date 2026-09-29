<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use core\exception\CommonException;
use think\facade\Db;

/** 数据库连接级锁：多进程/多服务器共用同一数据库，不依赖本机缓存。 */
class ExpressOperationLock
{
    public static function run(int $siteId, string $key, callable $operation)
    {
        $connection = Db::connect();
        // getPdo 本身不会建立连接；显式初始化主库，避免冷启动或读写分离时锁失效。
        $connection->query('SELECT 1', [], true);
        $pdo = $connection->getPdo();
        $name = 'hsx_express_' . hash('sha1', $siteId . '|' . $key);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 2)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new CommonException('该预约正在处理，请稍后查看结果，不要重复提交');
        }
        try {
            return $operation();
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$name]);
            } catch (\Throwable $e) {
                // 连接断开会由 MySQL 释放锁，不把已完成的预约误报成可重试的失败。
                \think\facade\Log::warning('预约锁释放异常', ['site_id' => $siteId, 'reason' => $e->getMessage()]);
            }
        }
    }
}
