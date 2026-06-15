<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpSyncRecoveryService;

/**
 * 设备下游同步健康度提供者（事件 GetErpDeviceSyncHealth 的应答方）。
 *
 * 回收等上游插件在设备列表里发 event('GetErpDeviceSyncHealth', ['source_device_ids'=>[...]])，
 * 本监听器返回每台设备的同步是否"卡住"（没建资产 / 有未送达的 outbox 事件），
 * 用于只在"确实失效"时才显示「重新同步」按钮。
 * 故障隔离：异常返回空数组（调用方据此视为健康、不显示按钮）。
 */
class DeviceSyncHealthProvider
{
    public function handle(array $params): array
    {
        try {
            $ids = (array)($params['source_device_ids'] ?? []);
            return (new ErpSyncRecoveryService())->health($ids);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
