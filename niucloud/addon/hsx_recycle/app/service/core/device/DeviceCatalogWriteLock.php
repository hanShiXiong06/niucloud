<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\service\core\device;

use core\exception\CommonException;
use think\facade\Db;

/** 与一键转公共互斥；服务对象释放时归还当前连接的命名锁。 */
final class DeviceCatalogWriteLock
{
    private bool $held = false;
    public function acquire(): void
    {
        if ($this->held) return;
        $rows = Db::query("SELECT GET_LOCK('hsx_recycle_catalog_write_v1', 10) AS acquired", [], true);
        if ((int)($rows[0]['acquired'] ?? 0) !== 1) throw new CommonException('资料库正在更新，请稍后重试；本次未修改数据');
        $this->held = true;
    }
    public function __destruct()
    {
        if ($this->held) {
            try { Db::query("SELECT RELEASE_LOCK('hsx_recycle_catalog_write_v1')", [], true); } catch (\Throwable $e) {}
        }
    }
}
