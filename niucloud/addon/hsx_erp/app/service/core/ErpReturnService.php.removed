<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpCostLedger;
use addon\hsx_erp\app\model\ErpStockLedger;
use think\facade\Db;
use think\facade\Log;

/**
 * 退货出库冲销服务
 *
 * 由回收侧"撤销回收"事件(recycle.device.cancelled.v1)触发：
 * 把对应 ERP 资产做退货出库 + 成本反向冲销(只追加反向流水、不物理删除)，资产转"已退货"。
 * 设计：幂等(已退货则跳过)、故障隔离(异常吞掉记日志，绝不回抛影响回收)、不跨插件读表。
 *
 * Class ErpReturnService
 * @package addon\hsx_erp\app\service\core
 */
class ErpReturnService
{
    /**
     * 处理回收撤销 → 退货出库冲销
     * @param array $event
     * @return array
     */
    public function returnByRecycleCancel(array $event): array
    {
        try {
            $siteId = (int)($event['site_id'] ?? 0);
            $sourceDeviceId = (int)($event['source_device_id'] ?? 0);
            if ($siteId <= 0 || $sourceDeviceId <= 0) {
                return ['skipped' => true, 'reason' => 'invalid_event'];
            }

            // 找该回收设备最新的 ERP 资产
            $asset = ErpAsset::where([
                ['site_id', '=', $siteId],
                ['source_device_id', '=', $sourceDeviceId],
            ])->order('id desc')->findOrEmpty();
            if ($asset->isEmpty()) {
                // 还没同步进 ERP，无需冲销
                return ['skipped' => true, 'reason' => 'asset_not_found'];
            }

            $status = (string)$asset->inventory_status;
            // 幂等：已退货则跳过
            if ($status === ErpDict::INVENTORY_RETURNED) {
                return ['skipped' => true, 'reason' => 'already_returned', 'asset_id' => (int)$asset->id];
            }

            $now = time();
            $beforeCost = (string)($asset->current_cost ?? '0.00');
            $operatorId = (int)($event['operator']['id'] ?? 0);
            $operatorName = (string)($event['operator']['name'] ?? '');

            Db::startTrans();
            try {
                ErpStockLedger::create([
                    'site_id' => $siteId,
                    'ledger_no' => $this->makeNo('SL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'stock_order_id' => 0,
                    'action' => 'return_outbound',
                    'before_status' => $status,
                    'after_status' => ErpDict::INVENTORY_RETURNED,
                    'warehouse_id' => (int)$asset->warehouse_id,
                    'location_id' => (int)$asset->location_id,
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'occurred_at' => $now,
                    'payload' => [
                        'reason' => (string)($event['reason'] ?? ''),
                        'refund_amount' => (float)($event['refund_amount'] ?? 0),
                        'source' => 'recycle.device.cancelled.v1',
                    ],
                ]);

                ErpCostLedger::create([
                    'site_id' => $siteId,
                    'ledger_no' => $this->makeNo('CL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'cost_type' => 'return_reverse',
                    'amount_delta' => (string)(-1 * (float)$beforeCost),
                    'before_cost' => $beforeCost,
                    'after_cost' => '0.00',
                    'source_plugin' => 'hsx_recycle',
                    'source_type' => 'recycle_device',
                    'source_id' => $sourceDeviceId,
                    'counterparty_id' => (int)$asset->counterparty_id,
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'occurred_at' => $now,
                    'remark' => '回收撤销退货，成本反向冲销',
                ]);

                $asset->save([
                    'inventory_status' => ErpDict::INVENTORY_RETURNED,
                    'current_cost' => '0.00',
                    'version' => (int)$asset->version + 1,
                    'update_at' => $now,
                ]);

                ErpAssetCycle::where([
                    ['site_id', '=', $siteId],
                    ['id', '=', (int)$asset->cycle_id],
                ])->update([
                    'status' => ErpDict::INVENTORY_RETURNED,
                    'update_at' => $now,
                ]);

                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }

            return ['returned' => true, 'asset_id' => (int)$asset->id];
        } catch (\Throwable $e) {
            try {
                Log::error('[hsx_erp] 退货出库冲销失败: ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . random_int(100000, 999999);
    }
}
