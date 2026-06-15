<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpSyncRecoveryService;

/**
 * 设备重新同步执行器（事件 ResyncErpDevice 的应答方）。
 *
 * 回收点「重新同步」时发 event('ResyncErpDevice', ['source_device_id'=>..])，
 * 本监听器把该设备 ERP 资产卡住的 outbox 事件就地重发，补齐下游（如中台待拍照任务）。
 * 返回 has_asset=false 表示 ERP 还没这台资产，由回收侧改走重新 dispatch 入库。
 * 故障隔离：异常返回安全结构，绝不影响调用方。
 */
class DeviceResyncListener
{
    public function handle(array $params): array
    {
        try {
            $sid = (int)($params['source_device_id'] ?? 0);
            return (new ErpSyncRecoveryService())->resync($sid);
        } catch (\Throwable $e) {
            return ['has_asset' => false, 'asset_id' => 0, 'flushed' => 0, 'still_failed' => 0, 'error' => $e->getMessage()];
        }
    }
}
