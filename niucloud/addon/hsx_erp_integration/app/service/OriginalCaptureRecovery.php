<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\support\{BridgeFact,ConnectionFields,DisjointManualRoutes};
use addon\hsx_erp_integration\app\contract\ScopedManualCaptureInterface;
use app\service\admin\auth\AuthService;
use app\service\core\site\CoreSiteService;
use think\facade\Db;

/** One immutable purchase or confirmed payment voucher. Never replay to other subscribers. */
final class OriginalCaptureRecovery
{
    private function event(int $site,int $source,string $kind,int $purchase): array
    {
        $payment=$kind==='payment';
        $auth=(new AuthService())->getAuthApiList();
        if(!in_array($kind,['purchase','payment'],true) || $site<=0 || $source<=0 || (int)request()->siteId()!==$site || (int)request()->uid()<=0
            || !in_array('hsx_erp_integration',(new CoreSiteService())->getAddonKeysBySiteId($site),true)
            || !in_array($payment?'erp/finance/payable/lists':'erp/purchase/lists',$auth['get']??[],true) || !in_array('integration/task/<id>',$auth['get']??[],true)
            || !in_array('integration/task/prepare/<id>',$auth['post']??[],true)) throw new \RuntimeException('没有处理本站原采购的权限');
        $routes=Db::name('erp_integration_connection')->where(['site_id'=>$site,'status'=>'enabled'])->field('id,site_id,adapter_code,config,sync_mode,business_types,last_test_result,bridge_started_at')->select()->toArray();
        $routes=array_values(array_filter($routes,static fn($r)=>in_array($kind,ConnectionFields::values($r['business_types']),true)));
        if(!$routes || count($routes)>2 || array_filter($routes,static fn($r)=>$r['sync_mode']!=='manual' || $r['last_test_result']!=='success' || (int)$r['bridge_started_at']<=0))
            throw new \RuntimeException('本站未启用唯一且已核验的手动采购连接');
        $po=Db::name('erp_purchase_order')->where(['site_id'=>$site,'id'=>$payment?$purchase:$source,'status'=>'completed'])->value('purchase_no');
        if(!$po) throw new \RuntimeException('原采购不存在或未完成，不可恢复关联');
        $number=$payment?Db::name('erp_settlement')->where(['site_id'=>$site,'id'=>$source,'status'=>'confirmed','cash_direction'=>'out','settlement_type'=>'payment'])->where('confirmed_at','>',0)->value('settlement_no'):$po;
        if(!$number) throw new \RuntimeException('找不到已确认的本站原付款凭证，请勿重复登记付款');
        $rows=Db::name('erp_outbox_event')->where(['site_id'=>$site,'event_name'=>$payment?BridgeFact::PAYMENT:BridgeFact::PURCHASE])->where('occurred_at','>=',(int)min(array_column($routes,'bridge_started_at')))
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.aggregate_id')) = ?",[(string)$source])->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.aggregate_type')) = ?",[$payment?'settlement':'purchase'])
            ->order('id')->limit(2)->field('event_id,payload_json,occurred_at')->select()->toArray();
        if(count($rows)!==1) throw new \RuntimeException('未能定位唯一的启用后原采购批次，未补造事件或重提单据');
        $e=ConnectionFields::values($rows[0]['payload_json']);$fact=BridgeFact::project($e,$kind);
        if(BridgeFact::kind($e)!==$kind || $rows[0]['event_id']!==$e['event_id'] || (int)$e['site_id']!==$site
            || $fact['source_id']!==$source || $fact['source_no']!==$number || !empty($fact['error_message'])) throw new \RuntimeException('原始事件与本单不一致，不能恢复关联');
        if($payment) foreach($e['payload']['targets'] as $t) if(($t['source_document_ref']??'')!=='purchase:'.$purchase)
            throw new \RuntimeException('该凭证跨采购或不属于本原单，不拆分或重复登记');
        $chosen=$routes[0];
        if(count($routes)===2) {
            $driver=AdapterRegistry::forSite($site)->getAdapter($chosen['adapter_code']);
            $chosen=DisjointManualRoutes::forEvent($site,$routes,$driver,$e);
            if(!$chosen || (int)$rows[0]['occurred_at']<(int)$chosen['bridge_started_at'] || (int)($e['occurred_at']??0)<(int)$chosen['bridge_started_at'])
                throw new \RuntimeException('原单不在对应连接的启用时间或限定范围内，不能恢复关联');
        }
        $config=ConnectionFields::values($chosen['config']);
        if(count($routes)===1 && !empty($config['manual_scope'])) {
            $driver=AdapterRegistry::forSite($site)->getAdapter($chosen['adapter_code']);
            if(!$driver instanceof ScopedManualCaptureInterface || !$driver->admitsManualFact($site,$config,$e))
                throw new \RuntimeException('该单不在本连接核准的单据范围内，不能恢复关联');
        }
        return $e;
    }

    public function available(int $site,int $source,string $kind='purchase',int $purchase=0): bool
    {
        try { $this->event($site,$source,$kind,$purchase);return true; } catch(\RuntimeException|\InvalidArgumentException $e) { return false; }
        catch(\Throwable $e) {
            // An unavailable optional recovery action must not hide an already displayed original receipt.
            \think\facade\Log::warning('集成中心暂不能读取原事件关联入口',['site_id'=>$site,'kind'=>$kind,'source_id'=>$source,'type'=>get_class($e)]);
            return false;
        }
    }

    public function recover(int $site,int $source,int $uid,string $kind='purchase',int $purchase=0): array
    {
        $e=$this->event($site,$source,$kind,$purchase);$capture=(new DomainCaptureService())->accept($e);$ids=$capture['task_ids']??[];
        if(count($ids)!==1) throw new \RuntimeException('原采购尚未关联到当前连接，请核对启用范围');
        $id=(int)$ids[0];
        $manual=new ManualTaskService();
        // Check first: an existing native draft, an issued purchase or an uncertain attempt is never recreated.
        $receipt=$manual->run($site,$id,$uid,true);
        if(empty($receipt['vendor_task_id']) && $receipt['status']==='waiting') $receipt=$manual->run($site,$id,$uid,false);
        return ['task_id'=>$id]+$receipt;
    }

    public function recoverSaleReceipt(int $site,int $sid,int $uid,int $sale): array
    {
        if ($site<1 || $sale<1 || $sid<1 || $uid<1 || (int)request()->siteId()!==$site || (int)request()->uid()!==$uid)
            throw new \RuntimeException('请使用本站财务身份打开销售原单');
        $auth=(new AuthService())->getAuthApiList();
        if (array_diff(['erp/finance/receivable/lists','erp/sale/lists','integration/task/<id>'],$auth['get']??[])
            || !in_array('integration/task/prepare/<id>',$auth['post']??[],true)) throw new \RuntimeException('没有查看销售应收及关联原凭证的权限');
        $s=Db::name('erp_settlement')->where(['site_id'=>$site,'id'=>$sid,'status'=>'confirmed','settlement_type'=>'receipt','cash_direction'=>'in'])->find();
        if (!$s || !$s['confirmed_at']) throw new \RuntimeException('未找到已确认的收款原凭证，请勿再次收款');
        $so=Db::name('erp_sale_order')->where(['site_id'=>$site,'id'=>$sale,'party_id'=>$s['party_id'],'status'=>'completed'])->value('sale_no');
        if (!$so) throw new \RuntimeException('没有找到同一客户的已出库销售原单');
        $rows=Db::name('erp_outbox_event')->where(['site_id'=>$site,'event_name'=>BridgeFact::PAYMENT])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.aggregate_type')) = 'settlement' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.aggregate_id')) = ?",[(string)$sid])
            ->limit(2)->field('payload_json,event_id')->select()->toArray();
        if (count($rows)!==1) throw new \RuntimeException('未找到唯一的收款原事件，不会重建凭证');
        $e=ConnectionFields::values($rows[0]['payload_json']); $fact=BridgeFact::project($e,'receipt'); $p=$e['payload'];
        if (BridgeFact::kind($e)!=='receipt' || $rows[0]['event_id']!==$e['event_id'] || (int)$e['site_id']!==$site || $fact['source_id']!==$sid
            || $fact['source_no']!==$s['settlement_no'] || $fact['error_message'] || (int)$p['party_id']!==(int)$s['party_id']
            || (int)$p['capital_account_id']!==(int)$s['capital_account_id'] || bccomp((string)$p['amount'],(string)$s['amount'],2)!==0)
            throw new \RuntimeException('原凭证与已保存收款事件不一致，请核对');
        foreach ($p['targets'] as $t) if (($t['source_no']??'')!==$so || ($t['source_document_ref']??'')!=='sale:'.$sale)
            throw new \RuntimeException('这张凭证不完全属于本销售单，不能拆分后重复登记');
        $capture=(new DomainCaptureService())->acceptOriginalSaleReceipt($e,$sale); $ids=$capture['task_ids']??[];
        if (count($ids)!==1) throw new \RuntimeException('请先启用本站已核验的手动收款同步连接；未发送');
        $id=(int)$ids[0]; $manual=new ManualTaskService();
        $r=$manual->run($site,$id,$uid,true);
        if (empty($r['vendor_task_id']) && $r['status']==='waiting') $r=$manual->run($site,$id,$uid,false);
        if(empty($r['vendor_task_id'])) throw new \RuntimeException($r['message']?:'未准备原收款凭证，未发送');
        return ['task_id'=>$id]+$r;
    }
}
