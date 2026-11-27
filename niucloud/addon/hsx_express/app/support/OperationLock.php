<?php
declare(strict_types=1);
namespace addon\hsx_express\app\support;

use core\exception\CommonException;
use think\facade\Db;

final class OperationLock
{
    public static function run(int $siteId, string $key, callable $operation)
    {
        $connection = Db::connect();
        $connection->query('SELECT 1', [], true);
        $pdo = $connection->getPdo();
        $name = 'hsx_waybill_' . hash('sha1', $siteId . '|' . $key);
        $statement = $pdo->prepare('SELECT GET_LOCK(?, 2)');
        $statement->execute([$name]);
        if ((int)$statement->fetchColumn() !== 1) throw new CommonException('物流任务正在处理，请稍后刷新；请勿重复取号或切换服务商');
        try { return $operation(); }
        finally {
            try { $statement = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $statement->execute([$name]); }
            catch (\Throwable $e) { /* 连接关闭后数据库自动释放；不覆盖业务结果。 */ }
        }
    }
}
