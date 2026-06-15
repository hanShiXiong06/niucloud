<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpOutboxEvent;
use addon\hsx_erp\app\support\ErpDomainEvent;
use core\base\BaseAdminService;

/**
 * 跨插件同步恢复服务（兜底）。
 *
 * 背景：回收设备入库 ERP 是同步的，但 ERP 写给中台/财务的领域事件走 outbox + 异步发布
 * （event('ErpDomainEvent')）。只要队列没跑或下游监听器报错，事件就停在 outbox(pending/failed)，
 * 下游步骤（如中台"待拍照"任务）不会发生 —— 表现为"设备入了 ERP 待销售定价，却没进中台拍照"。
 *
 * 本服务提供两件事，给上游插件按 source_device_id 调用：
 *  - health(): 判断某设备的同步是否"卡住"（没建资产 / 有未发布或失败的 outbox 事件）；
 *  - resync(): 把该资产卡住的 outbox 事件就地校验并重新派发，标记 published，补齐下游步骤。
 *
 * 幂等：只重发 status ∈ {pending,failed} 的事件；published 的不再发。下游监听器自身按 event_id 去重。
 */
class ErpSyncRecoveryService extends BaseAdminService
{
    /** 视为"未完成投递"的 outbox 状态 */
    private array $unsettled = ['pending', 'failed'];

    /**
     * 批量检查多台设备的下游同步健康度。
     * @param array $sourceDeviceIds 回收设备ID（= ERP 资产 source_device_id）
     * @return array source_device_id => ['stuck'=>bool,'has_asset'=>bool,'asset_id'=>int,'inventory_status'=>string,'pending'=>int,'failed'=>int,'reason'=>string]
     */
    public function health(array $sourceDeviceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $sourceDeviceIds))));
        $out = [];
        if (empty($ids)) {
            return $out;
        }

        // 资产：取每个 source_device_id 最新一条
        $assets = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->whereIn('source_device_id', $ids)
            ->order('id desc')
            ->select()
            ->toArray();
        $assetBySource = [];
        foreach ($assets as $a) {
            $sid = (int)($a['source_device_id'] ?? 0);
            if ($sid > 0 && !isset($assetBySource[$sid])) {
                $assetBySource[$sid] = $a;
            }
        }

        foreach ($ids as $sid) {
            $asset = $assetBySource[$sid] ?? null;
            if (!$asset) {
                // 没有资产 = 入库同步根本没落地
                $out[$sid] = [
                    'stuck' => true,
                    'has_asset' => false,
                    'asset_id' => 0,
                    'inventory_status' => '',
                    'pending' => 0,
                    'failed' => 0,
                    'reason' => '未在 ERP 建立资产，入库同步未完成',
                ];
                continue;
            }
            $assetId = (int)$asset['id'];
            $pending = (int)ErpOutboxEvent::where([
                ['site_id', '=', $this->site_id],
                ['aggregate_type', '=', 'asset'],
                ['aggregate_id', '=', $assetId],
                ['status', '=', 'pending'],
            ])->count();
            $failed = (int)ErpOutboxEvent::where([
                ['site_id', '=', $this->site_id],
                ['aggregate_type', '=', 'asset'],
                ['aggregate_id', '=', $assetId],
                ['status', '=', 'failed'],
            ])->count();
            $stuck = ($pending + $failed) > 0;
            $out[$sid] = [
                'stuck' => $stuck,
                'has_asset' => true,
                'asset_id' => $assetId,
                'inventory_status' => (string)($asset['inventory_status'] ?? ''),
                'pending' => $pending,
                'failed' => $failed,
                'reason' => $stuck ? '有未送达下游的事件（队列未发或下游报错）' : '',
            ];
        }

        return $out;
    }

    /**
     * 重新同步单台设备：把其 ERP 资产卡住的 outbox 事件就地重发，补齐下游步骤。
     * @param int $sourceDeviceId
     * @return array ['has_asset'=>bool,'asset_id'=>int,'flushed'=>int,'still_failed'=>int]
     */
    public function resync(int $sourceDeviceId): array
    {
        $sourceDeviceId = (int)$sourceDeviceId;
        if ($sourceDeviceId <= 0) {
            return ['has_asset' => false, 'asset_id' => 0, 'flushed' => 0, 'still_failed' => 0];
        }

        $asset = ErpAsset::where([['site_id', '=', $this->site_id]])
            ->where('source_device_id', '=', $sourceDeviceId)
            ->order('id desc')
            ->findOrEmpty();
        if ($asset->isEmpty()) {
            // 没有资产：交回上游(回收)去重新 dispatch 入库
            return ['has_asset' => false, 'asset_id' => 0, 'flushed' => 0, 'still_failed' => 0];
        }

        $assetId = (int)$asset->id;
        $events = ErpOutboxEvent::where([
            ['site_id', '=', $this->site_id],
            ['aggregate_type', '=', 'asset'],
            ['aggregate_id', '=', $assetId],
        ])->whereIn('status', $this->unsettled)->order('id asc')->select();

        $flushed = 0;
        $stillFailed = 0;
        foreach ($events as $row) {
            $now = time();
            try {
                $domainEvent = (array)$row->payload;
                ErpDomainEvent::validate($domainEvent);
                event('ErpDomainEvent', $domainEvent);
                $row->save([
                    'status' => 'published',
                    'attempts' => (int)$row->attempts + 1,
                    'published_at' => $now,
                    'error_message' => '',
                    'update_at' => $now,
                ]);
                $flushed++;
            } catch (\Throwable $e) {
                $stillFailed++;
                $row->save([
                    'status' => 'failed',
                    'attempts' => (int)$row->attempts + 1,
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    'update_at' => $now,
                ]);
            }
        }

        return [
            'has_asset' => true,
            'asset_id' => $assetId,
            'flushed' => $flushed,
            'still_failed' => $stillFailed,
        ];
    }
}
