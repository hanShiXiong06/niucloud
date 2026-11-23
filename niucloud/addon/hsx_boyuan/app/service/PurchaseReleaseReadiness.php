<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\support\Contract;
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;

/** A UAT receipt supports a manual purchase release decision; it never becomes a production business receipt. */
final class PurchaseReleaseReadiness
{
    private const ENDPOINT = 'https://open-api.139erp.com/api';

    public function inspect(int $site,array $config): array
    {
        $prod=$config['profiles']['production']??[];
        $test=$config['profiles']['test']??[];
        $recorded=trim((string)($prod['acceptance_ref']??''));
        $result=['recorded_ref'=>'','candidate'=>null,'eligible'=>false];
        if($site<=0 || ($config['mode']??'')!=='production' || ($prod['protocol']??'')!=='openapi_v153' ||
            ($prod['endpoint']??'')!==self::ENDPOINT || ($prod['gateway_path']??'')!=='/gateway.do' ||
            empty($prod['probe_at']) || empty($prod['connection_key']) || empty($prod['account_id']) || empty($prod['contract_confirmed'])) return $result;
        $result['recorded_ref']=$recorded;
        $canStart=empty($config['finance_enabled']) && empty($config['uat_payment_enabled']) && (empty($config['enabled']) || !empty($config['paused']));
        if($recorded!=='') { $result['eligible']=$canStart; return $result; }
        if(($test['protocol']??'')!=='openapi_v153' || ($test['endpoint']??'')!==PaymentSyncService::UAT_ENDPOINT ||
            ($test['gateway_path']??'')!=='/gateway.do' || empty($test['probe_at']) || empty($test['connection_key']) || empty($test['account_id'])) return $result;

        $q=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'connection_key'=>$test['connection_key'],
            'mode'=>'test','kind'=>'purchase','status'=>'succeeded','side_effect'=>'applied','query_only'=>1,'resolution'=>''])
            ->where('external_no','<>','')->field('id,fact_key,business_no,external_no,snapshot_json,request_json,result_json');
        $cursor=null;
        do {
            $page=clone $q;
            if($cursor!==null) $page->where('id','<',$cursor);
            $rows=$page->order('id desc')->limit(20)->select()->toArray();
            foreach($rows as $row) {
                $cursor=(int)$row['id'];
                if(!self::matches($site,$test['connection_key'],$row)) continue;
                $result['candidate']=['task_id'=>$cursor,'test_account'=>(string)$test['account_id'],'test_bill'=>$row['external_no']];
                $result['eligible']=$canStart;
                return $result;
            }
        } while(count($rows)===20);
        return $result;
    }

    private static function matches(int $site,string $key,array $row): bool
    {
        try { $r=Contract::decode($row['result_json']); $s=Contract::decode($row['snapshot_json']); $req=Contract::decode($row['request_json']); }
        catch(\JsonException|\TypeError $e) { return false; }
        $e=$r['evidence']??[]; $no=$row['external_no']; $lines=$s['lines']??[]; $third='BP'.substr(hash('sha256',$site.':'.$key.':'.$row['fact_key']),0,30);
        if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/D',$no) || ($req['schema']??'')!==PurchaseSyncService::SCHEMA ||
            ($req['stage']??'')!=='frozen' || ($req['third_no']??null)!==$third || ($req['biz']['head']['third_no']??null)!==$third ||
            ($r['verified']??false)!==true || ($r['external_no']??null)!==$no || empty($r['external_id']) ||
            ($r['verification_level']??'')!=='purchase_imei_records_matched' || ($e['record_is_effective']??false)!==true ||
            ($e['bill_type']??'')!=='PUR' || ($e['response_contract']??'')!==OpenApi::PURCHASE_DIALECT_UAT ||
            ($e['request_third_no']??null)!==$third || empty($e['company_id']) || $e['company_id']!==($req['selection']['company_id']??null) ||
            ($s['kind']??'')!=='purchase' || ($s['source_document_no']??null)!==$row['business_no'] ||
            (($s['source_document_id']??'').':'.($s['batch_ref']??''))!==$row['fact_key'] ||
            !$lines || !is_array($lines) || !is_array($r['lines']??null) || !is_array($req['lines']??null) ||
            count($lines)!==count($r['lines']) || count($lines)!==count($req['lines']) || ($e['imei_detail_count']??0)!==count($lines) ||
            ($e['purchase_detail_count']??0)!==count($lines)) return false;
        $seen=[];
        foreach($lines as $i=>$line) {
            $verified=$r['lines'][$i]??[]; $frozen=$req['lines'][$i]??[];
            if(empty($verified['external_line_id']) || isset($seen[$verified['external_line_id']]) || ($verified['external_document_id']??null)!==$r['external_id']) return false;
            foreach(['source_line_id','device_ref','imei'] as $field)
                if(!is_string($line[$field]??null) || $line[$field]==='' || $line[$field]!==($verified[$field]??null) || $line[$field]!==($frozen[$field]??null)) return false;
            $seen[$verified['external_line_id']]=true;
        }
        return true;
    }

    /** Never accepts a free-text receipt, credentials, task payload or financial switch. */
    public function enable(int $site,int $uid,array $in): array
    {
        if($uid<1 || $site<1) throw new Problem('SITE_INVALID','请以本站管理员身份操作');
        return Db::transaction(function() use($site,$uid,$in) {
            if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            $service=new ConfigService(); $c=$service->get($site,false); $p=$c['profiles']['production'];
            if(($c['mode']??'')!=='production' || ($in['mode']??null)!=='production' || (int)($in['revision']??-1)!==(int)$c['revision'] ||
                $p['connection_key']==='' || ($in['connection_key']??null)!==$p['connection_key']) throw new Problem('CONFIG_STALE','正式连接已变化，请重新载入');
            if(($in['confirm_account']??null)!==$p['account_id']) throw new Problem('ACCOUNT_CONFIRM_REQUIRED','请确认目标正式账套');
            $state=$this->inspect($site,$c); $candidate=$state['candidate'];
            if(!$state['eligible']) throw new Problem('PURCHASE_RELEASE_UNAVAILABLE','当前不能开启：请核验正式连接、确认接口已开通，并查看本站已核验的测试采购。没有发单。');
            if(Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production','connection_key'=>$p['connection_key'],'query_only'=>0])
                ->whereIn('status',['pending','processing'])->count())
                throw new Problem('PURCHASE_RELEASE_IN_FLIGHT','当前账套还有待发或处理中的单据。请先核对原任务，开启手动采购不会替你批量发旧单。');
            if((int)($in['candidate_task_id']??-1)!==(int)($candidate['task_id']??0)) throw new Problem('CONFIG_STALE','测试采购记录已变化，请重新核对');
            $ref=$state['recorded_ref']!==''?$state['recorded_ref']:$candidate['test_bill'];
            $service->save($site,$uid,['mode'=>'production','revision'=>$c['revision'],'confirm_account'=>$p['account_id'],
                'profile'=>['acceptance_ref'=>$ref],'enabled'=>true,'paused'=>false,'purchase_enabled'=>true,'purchase_mode'=>'manual']);
            TaskService::audit($site,0,$p['connection_key'],'purchase_release','manual_enabled','管理员确认正式账套并开启手动采购；未发送采购或付款',$uid,
                $candidate?['uat_task_id'=>$candidate['task_id'],'uat_account'=>$candidate['test_account'],'uat_purchase'=>$ref]:['recorded_ref'=>$ref]);
            return ['mode'=>'production','account_id'=>$p['account_id'],'manual_purchase'=>true,'revision'=>(int)$c['revision']+1,'documents_sent'=>0];
        });
    }

    /** A separate manager action enables manual copies of original vouchers only. No queued work is released. */
    public function vouchers(int $site,int $uid,array $in): array
    {
        return Db::transaction(function() use($site,$uid,$in) {
            if($site<1 || $uid<1 || !Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','请以本站管理员身份操作');
            $svc=new ConfigService(); $c=$svc->get($site,false); $p=$c['profiles']['production'];
            if($c['mode']!=='production' || ($in['mode']??null)!=='production' || (int)($in['revision']??-1)!==(int)$c['revision'] ||
                $p['connection_key']==='' || $p['connection_key']!==($in['connection_key']??null) || $p['account_id']!==($in['confirm_account']??null))
                throw new Problem('CONFIG_STALE','正式账套或配置已变化，请刷新');
            $on=$in['enable']??null;
            if(!is_bool($on)) throw new Problem('VOUCHER_CONFIRM_REQUIRED','请明确选择开启或关闭凭证同步');
            if($on) {
                if(!\addon\hsx_boyuan\app\support\ProductionVoucher::ready($c,$p)) throw new Problem('VOUCHER_NOT_RELEASED','请先核验正式账套并开启手动采购同步');
                if(Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production','connection_key'=>$p['connection_key'],'kind'=>PaymentSyncService::KIND])->whereIn('status',['pending','processing'])->count())
                    throw new Problem('VOUCHER_IN_FLIGHT','还有等待处理的原付款任务，请先核对，不能在开启时批量发送');
                $p['voucher_release']=['target'=>$p['connection_key'],'account'=>$p['account_id'],'uid'=>$uid,'at'=>time()];
            } else unset($p['voucher_release']);
            $c['profiles']['production']=$p; ++$c['revision']; $svc->persist($site,$c);
            TaskService::audit($site,0,$p['connection_key'],'voucher_release',$on?'manual_enabled':'paused','仅变更正式凭证手动同步权限，没有提交单据或付款',$uid);
            return ['enabled'=>$on,'account_id'=>$p['account_id'],'revision'=>$c['revision'],'documents_sent'=>0];
        });
    }
}
