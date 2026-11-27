<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\contract\ManualDocumentAdapterInterface;
use addon\hsx_erp_integration\app\contract\ScopedManualCaptureInterface;
use addon\hsx_erp_integration\app\support\{BridgeFact, BridgeTarget, ConnectionFields, DisjointManualRoutes, OriginalEventIdentity};
use think\facade\Db;

/** Local-only subscriber. No adapter write, query, finance service or queue is reachable from capture. */
final class DomainCaptureService
{
    private const TABLE = 'erp_integration_sync_task';
    private ?\Closure $supports;

    public function __construct(?\Closure $supports = null) { $this->supports = $supports; }

    public function accept(array $event): array
    {
        return $this->capture($event,0);
    }

    /** Explicit recovery of ONE persisted receipt on an already verified original sale.
     * Normal outbox capture always keeps the connection start-time cutoff. */
    public function acceptOriginalSaleReceipt(array $event,int $sale): array
    {
        if ($sale<1 || BridgeFact::kind($event)!=='receipt') throw new \DomainException('只能关联指定销售单的原收款凭证');
        return $this->capture($event,$sale);
    }

    private function capture(array $event,int $sale): array
    {
        $kind = BridgeFact::kind($event); $site = (int)($event['site_id'] ?? 0);
        if (!$kind || $site < 1) return [];
        if(!$this->supports && !in_array('hsx_erp_integration',(new \app\service\core\site\CoreSiteService())->getAddonKeysBySiteId($site),true)) return [];
        unset($event['_delivery']);
        if (in_array($kind,['invalidation','return_review'],true)) return $this->invalidate($site, BridgeFact::project($event, $kind));
        return Db::transaction(function() use ($event,$site,$kind,$sale) {
            // Consistent lock order for overlapping events and connections during a configuration change.
            $connections = Db::name('erp_integration_connection')->where('site_id',$site)->where('status','enabled')
                ->order('id asc')->lock(true)->select()->toArray();
            $routes = array_filter($connections, static fn($c)=>in_array($kind,ConnectionFields::values($c['business_types'] ?? []),true));
            if (!array_filter($routes,static fn($c)=>($c['sync_mode'] ?? '')==='manual')) return [];
            $c = reset($routes);
            $registry = $this->supports ? null : AdapterRegistry::forSite($site);
            $driver = $registry && $registry->hasAdapter($c['adapter_code']) ? $registry->getAdapter($c['adapter_code']) : null;
            $shared=count($routes)>1;
            if($shared) {
                $c=DisjointManualRoutes::forEvent($site,$routes,$driver,$event);
                if($c===null) return [];
            }
            if (($c['last_test_result'] ?? '') !== 'success' || (int)($c['bridge_started_at'] ?? 0) < 1)
                throw new \DomainException('原单路由未完成目标核验或缺少启用时间；未接管');
            if (!$sale && (int)($event['occurred_at'] ?? 0) < (int)$c['bridge_started_at']) return [];
            $config = ConnectionFields::values($c['config']);
            $mode = $config['mode'] ?? '';
            $supported = $this->supports ? ($this->supports)($site, $c['adapter_code'], $kind, $mode)
                : ($driver instanceof ManualDocumentAdapterInterface && in_array($kind, $driver->manualBusinessTypes($mode), true));
            if (!$supported) throw new \DomainException('本站启用的手动连接没有原单处理能力，未接管');
            if (!empty($config['manual_scope']) && !($driver instanceof ScopedManualCaptureInterface)) return [];
            if (!$shared && $driver instanceof ScopedManualCaptureInterface && !$driver->admitsManualFact($site,$config,$event)) return [];
            $target = BridgeTarget::key($c);
            $fact = BridgeFact::project($event, $kind);
            $query = Db::name(self::TABLE)->where('site_id',$site)->where('target_key',$target);
            if ($sale) {
                if ($mode!=='test' || $fact['error_message']) throw new \DomainException('仅限测试账套的已确认原收款凭证');
                $saleNo=(string)($event['payload']['targets'][0]['source_no']??'');
                foreach ($event['payload']['targets'] as $t) {
                    if (($t['source_document_ref']??'')!=='sale:'.$sale || ($t['source_no']??'')!==$saleNo) throw new \DomainException('该收款凭证并非完全分配给此销售原单');
                }
                $original=(clone $query)->where(['source_id'=>$sale,'source_type'=>'erp_sale','source_no'=>$saleNo,'business_type'=>'sale'])->lock(true)->limit(2)->select()->toArray();
                $proof=count($original)===1 ? OriginalDocumentResults::match($site,$original[0],['document_id'=>$sale,'number'=>$saleNo]) : null;
                if (!$proof || $proof['status']!=='succeeded' || !$proof['external_no'] || !empty($original[0]['error_code'])) throw new \DomainException('请先核对同一账套的销售出库回执，未提交收款凭证');
            }
            $sameEvent = (clone $query)->where('origin_event_id',$event['event_id'])->lock(true)->find();
            if ($sameEvent && $sameEvent['fact_key'] !== $fact['fact_key']) throw new \DomainException('同一原事件对应了不同单据或批次，未接管');
            $old = $sameEvent ?: (clone $query)->where('fact_key',$fact['fact_key'])->lock(true)->find();
            if ($old) {
                if($shared && (int)$old['connection_id']!==(int)$c['id']) throw new \DomainException('此原单已归属另一条连接；保留原任务，不重复建单');
                $original = ConnectionFields::values($old['payload']);
                if (OriginalEventIdentity::payload($kind,$original['payload'] ?? []) !== OriginalEventIdentity::payload($kind,$event['payload']))
                    throw new \DomainException('同一原单或批次的内容发生冲突，请核对原任务；没有覆盖或另建任务');
                return ['consumer'=>'hsx_erp_integration','status'=>'duplicate','task_ids'=>[(int)$old['id']]];
            }
            $reason = $fact['error_message'];
            if ($kind==='sale' && (clone $query)->where('source_type','erp_sale')->where('source_id',$fact['source_id'])->lock(true)->find())
                $reason='同行销售必须核对一份完整原单，本销售已有其他批次；整单未发送';
            if ($kind === 'purchase') {
                $incoming = array_column((array)($event['payload']['lines'] ?? []), 'source_line_id');
                $others = (clone $query)->where('source_type',$fact['source_type'])->where('source_id',$fact['source_id'])->lock(true)->column('payload');
                foreach ($others as $other) {
                    $lines = ConnectionFields::values($other)['payload']['lines'] ?? [];
                    if (array_intersect($incoming, array_column((array)$lines, 'source_line_id'))) {
                        $reason = '采购批次与已跟踪的入库行重叠，请核对原任务；整批未发送'; break;
                    }
                }
            }
            $now = time();
            $id = (int)Db::name(self::TABLE)->insertGetId(array_merge($fact, [
                'site_id'=>$site, 'connection_id'=>(int)$c['id'], 'target_key'=>$target, 'origin_event_id'=>$event['event_id'],
                'status'=>$reason ? 'failed' : 'awaiting_confirmation', 'error_code'=>$reason ? 'BRIDGE_SOURCE_REVIEW' : null,
                'error_message'=>$reason, 'payload'=>self::snapshot($event),
                'created_at'=>$now, 'updated_at'=>$now,
            ]));
            return ['consumer'=>'hsx_erp_integration','status'=>'processed','task_ids'=>[$id]];
        });
    }

    private static function snapshot(array $event): string
    {
        $raw = json_encode($event, JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
        // Never allow non-strict MySQL to silently truncate the immutable original.
        if (strlen($raw) > 16777215) throw new \DomainException('原事件超出任务快照容量，已保留ERP原始事件；请核对整批，未截取或发送部分明细');
        return $raw;
    }

    private function invalidate(int $site, array $fact): array
    {
        return Db::transaction(function() use ($site,$fact) {
            $rows = Db::name(self::TABLE)->where('site_id',$site)->where('source_type',$fact['source_type'])->where('source_id',$fact['source_id'])
                ->whereNotNull('fact_key')->order('id asc')->lock(true)->select()->toArray();
            foreach ($rows as $r) {
                if ($r['error_code'] === 'BRIDGE_SOURCE_INVALIDATED') continue;
                // Preserve external identifiers and receipt evidence. Never issue a corrective business document here.
                $neverSent = $r['status'] === 'awaiting_confirmation' || ($r['status'] === 'failed' && $r['error_code'] === 'BRIDGE_SOURCE_REVIEW');
                Db::name(self::TABLE)->where('id',$r['id'])->where('site_id',$site)->update([
                    'status'=>$neverSent ? 'failed' : 'unverified', 'error_code'=>'BRIDGE_SOURCE_INVALIDATED',
                    'error_message'=>'原单已变更、退货或撤销，请核对原件；集成中心不发送补单或冲销单', 'updated_at'=>time(),
                ]);
            }
            return $rows ? ['consumer'=>'hsx_erp_integration','status'=>'processed','task_ids'=>array_column($rows,'id')] : [];
        });
    }

}
