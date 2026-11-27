<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\erp;

use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpSourceCatalog;
use think\facade\Db;

/** 为已入库设备只读解析真实型号 ID，不回填历史数据、不凭设备名称猜测。 */
final class ErpSourceCatalogRequested
{
    public function handle(array $event = []): array
    {
        if (($event['source_plugin'] ?? '') !== 'hsx_recycle' || (int)($event['site_id'] ?? 0) <= 0) return [];
        $siteId = (int)$event['site_id']; $deviceId = (int)($event['source_device_id'] ?? 0);
        if ($deviceId <= 0) return ['provider' => 'hsx_recycle', 'catalog' => []];
        $device = Db::name('recycle_device')->where('site_id', $siteId)->where('id', $deviceId)->field('category_id')->find();
        return ['provider' => 'hsx_recycle', 'catalog' => $device ? RecycleErpSourceCatalog::fromModel($siteId, (int)$device['category_id']) : []];
    }
}
