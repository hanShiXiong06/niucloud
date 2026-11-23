<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\{OpenApi,ReceiptGateway};
use addon\hsx_boyuan\app\support\{Contract,InterfaceTrace,Problem,ReceiptFacts};
use addon\hsx_erp_integration\app\service\BoundManualMappings;
use core\exception\CommonException;
use think\facade\Db;

/** Vendor has no receipt idempotency key or by-number readback. A durable attempt precludes every retry. */
final class SaleReceiptSyncService
{
    public const KIND='sale_receipt';
    public static function fact(int $id):string { return 'sale-receipt:settlement:'.$id; }
    public static function draft(array $t):bool
    { return ($t['kind']??'')===self::KIND && ($t['status']??'')==='awaiting_confirmation' && ($t['mode']??'')==='test'
        && ($t['side_effect']??'')==='none' && (int)($t['attempts']??-1)===0 && empty($t['resolution']); }

    public static function number(array $t):string
    {
        $r=Contract::decode($t['result_json']);$s=Contract::decode($t['snapshot_json']);$req=Contract::decode($t['request_json']);$no=(string)($t['external_no']??'');
        $h=$r['response']['head']??[];$line=$r['response']['receipt_details']['add_details'][0]??[];$key=$t['connection_key']??'';
        return $t['kind']===self::KIND && $t['mode']==='test' && $t['status']==='registered_review' && $t['side_effect']==='applied' && $key!==''
            && $no!=='' && ($r['external_no']??null)===$no && !empty($r['external_id']) && ($r['created']??false)===true
            && ($r['receipt_registered']??false)===true && ($r['verification_level']??'')==='receipt_create_receipt_matched'
            && ($req['schema']??'')===ReceiptGateway::SCHEMA && ($req['stage']??'')==='frozen' && ($req['amount']??'')===($s['amount']??null)
            && ($r['amount']??null)===$s['amount'] && ($req['customer_id']??null)===$s['customer_id'] && ($req['company_id']??null)===$s['company_id']
            && ($req['connection_key']??null)===$key && ($req['source_settlement_id']??null)===$s['settlement_id']
            && ($h['bill_type']??'')==='RECEIPT' && ($h['id']??'')===$r['external_id'] && ($h['bill_id']??'')===$no
            && ($h['big_gsdm']??null)===($req['account_code']??'') && ($h['company_id']??null)===$req['company_id']
            && ($h['supplier_cust_id']??null)===$req['customer_id'] && ($line['account_id']??null)===($req['account_id']??'')
            && ($r['financial_posting_verified']??null)===false && ($r['allocation_applied_verified']??null)===false ? $no:'';
    }

    public static function authorize(bool $write):void
    {
        $a=new PermissionService();
        $a->assertApi('get','boyuan/tasks/<id>');$a->assertApi('get','erp/sale/lists');$a->assertApi('get','erp/finance/receivable/lists');
        if($write){$a->assertApi('post','boyuan/receipt-tasks/<id>/submit');$a->assertApi('post','erp/finance/receivable/<id>/confirm_receipt');}
    }

    public static function enabled(int $site,array $config,array $p):bool
    {
        if(!ManualDocumentCommandService::eligible($config) || empty($p['app_id']) || empty($p['account_id']) || !class_exists(BoundManualMappings::class)) return false;
        return BoundManualMappings::connection($site,'boyuan','receipt',['mode'=>'test','api_url'=>$p['endpoint'],'gateway_path'=>$p['gateway_path'],
            'app_id'=>$p['app_id'],'account_code'=>$p['account_id']])>0;
    }

    /** Within the bridge's site-locked transaction on writes; GET never creates a task. */
    public function handoff(int $site,int $uid,array $e,array $c,array $p,array $context,bool $read):array
    {
        self::authorize(!$read);
        if($c['mode']!=='test') throw new Problem('RECEIPT_UAT_ONLY','只允许博远测试账套登记原收款凭证');
        [$id]=SaleReceiptSource::refs($e);$q=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'fact_key'=>self::fact($id),'mode'=>'test']);
        $t=(clone $q)->where('connection_key',$p['connection_key'])->find();
        if(((int)($context['vendor_task_id']??0)>0 && (int)($t['id']??0)!==(int)$context['vendor_task_id']) || ($t && $t['kind']!==self::KIND))
            throw new Problem('RECEIPT_TASK_CONFLICT','已关联任务不匹配，请核对原任务');
        if(!$t && (clone $q)->count()) throw new Problem('OTHER_CONNECTION','这张凭证已关联其他 UAT 连接，不再登记');
        if(!$t) {
            if($read) return ['status'=>'waiting','vendor_task_id'=>0,'external_no'=>'','reason'=>'尚未同步这张现有收款凭证','message'=>'本站已收，无需再收'];
            if(!self::enabled($site,$c,$p)) throw new Problem('RECEIPT_DISABLED','当前测试账套尚未启用手动收款同步，未发送');
            $s=SaleReceiptSource::snapshot($site,$p['connection_key'],$e,true);$fact=self::fact($id);
            $taskId=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'mode'=>'test','connection_key'=>$p['connection_key'],'kind'=>self::KIND,'fact_key'=>$fact,
                'event_id'=>$e['event_id'],'operation_id'=>'BRC_'.hash('sha256',$site.':'.$p['connection_key'].':'.$fact),'business_no'=>$s['settlement_no'],
                'status'=>'awaiting_confirmation','reason'=>'本站已收，待确认是否登记到博远','snapshot_json'=>Contract::json($s),
                'request_json'=>Contract::json(['schema'=>ReceiptGateway::SCHEMA,'stage'=>'draft','account_code'=>$p['account_id'],'app_id'=>$p['app_id']]),
                'result_json'=>'{}','next_at'=>0,'create_at'=>time(),'update_at'=>time()]);
            TaskService::audit($site,$taskId,$p['connection_key'],'sale_receipt_capture','awaiting_confirmation','已关联原收款凭证；未外发',$uid);
            $t=(clone $q)->where('id',$taskId)->find();
        }
        $s=Contract::decode($t['snapshot_json']);[$sid,$sale,$ar]=SaleReceiptSource::refs($e);
        if($t['event_id']!==$e['event_id'] || ($s['settlement_id']??0)!==$sid || ($s['sale_id']??0)!==$sale || ($s['receivable_id']??0)!==$ar
            || $s['settlement_no']!==$e['payload']['settlement_no']) throw new Problem('RECEIPT_CONFLICT','本次事件与原收款任务不一致');
        $no=self::number($t);$status=$no!==''?'confirmed':(self::draft($t)?'linked':'unknown');
        return ['status'=>$status,'external_no'=>$no,'vendor_task_id'=>(int)$t['id'],'reason'=>$t['reason'],'message'=>$t['reason'],
            'allocation'=>['status'=>'pending','verified'=>false,'reason'=>'no_vendor_allocation_evidence']];
    }

    private function context(int $site):array
    {
        if($site<=0 || (int)request()->siteId()!==$site) throw new Problem('SITE_MISMATCH','请使用本站员工账号');
        $c=(new ConfigService())->get($site,false);$p=$c['profiles']['test'];
        if($c['mode']!=='test' || empty($p['connection_key']) || ($p['endpoint']??'')!==PaymentSyncService::UAT_ENDPOINT || ($p['gateway_path']??'')!=='/gateway.do')
            throw new Problem('RECEIPT_UAT_ONLY','仅支持原博远 UAT 连接；不向正式账套发送');
        return [$c,$p];
    }

    private function task(int $site,int $id,array $p,bool $lock):array
    {
        $t=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'connection_key'=>$p['connection_key'],'mode'=>'test','kind'=>self::KIND,'id'=>$id])->lock($lock)->find();
        if(!$t) throw new Problem('RECEIPT_MISSING','未找到本站当前账套的原收款任务');
        $r=Contract::decode($t['request_json']);
        if(($r['schema']??'')!==ReceiptGateway::SCHEMA || ($r['app_id']??'')!==$p['app_id'] || ($r['account_code']??'')!==$p['account_id'])
            throw new Problem('RECEIPT_TARGET_CHANGED','凭证绑定账套与当前连接不一致');
        return $t;
    }

    private function source(int $site,array $t,bool $lock):array
    {
        $raw=Db::name('erp_outbox_event')->where(['site_id'=>$site,'event_id'=>$t['event_id'],'event_name'=>'erp.settlement.completed.v1'])->value('payload_json');
        if(!$raw) throw new Problem('RECEIPT_EVENT_MISSING','找不到原收款事件，不重新创建');
        $s=SaleReceiptSource::snapshot($site,$t['connection_key'],Contract::decode($raw),$lock);
        if(Contract::json($s)!==$t['snapshot_json']) throw new Problem('RECEIPT_CHANGED','原销售或收款事实已变化，不向博远投递');return $s;
    }

    public function view(int $site,int $id):array
    {
        self::authorize(false);[$c,$p]=$this->context($site);$t=$this->task($site,$id,$p,false);$s=Contract::decode($t['snapshot_json']);
        $v=Db::name('erp_settlement')->where(['site_id'=>$site,'id'=>$s['settlement_id'],'settlement_no'=>$s['settlement_no']])->field('operator_name,capital_account_name')->find()?:[];
        $editable=false;$reason=$t['reason'];$r=Contract::decode($t['request_json']);
        $select=['selection'=>$r['selection']??['staff_id'=>'','account_id'=>''],'labels'=>$r['labels']??[],'missing'=>[]];
        if(self::draft($t)) {
            try { $this->source($site,$t,false); $select=(new PaymentSyncService())->receiptSelections($site,$p['connection_key'],$s);
                self::authorize(true);$editable=self::enabled($site,$c,$p);if(!$editable)$reason='当前未启用手动收款同步；本地已收，无需重复收款';
            } catch(Problem|CommonException $e){$reason=$e->getMessage();}
        }
        $public=array_intersect_key($t,array_flip(['id','revision','mode','connection_key','kind','status','reason','attempts','side_effect']));
        foreach(['id','revision','attempts'] as $f)$public[$f]=(int)$public[$f];
        if($t['status']==='processing' && time()>(int)$t['lease_until']){$public['status']='attention';$reason='发送后未取得确定回执。请凭本地凭证号向博远查原单，禁止重新登记';}
        return $select+['task'=>$public,'source'=>array_intersect_key($s,array_flip(['sale_id','sale_no','sale_external_no','settlement_id','settlement_no','amount','confirmed_at']))+$v,
            'can_submit'=>$editable,'external_no'=>self::number($t),'allocation_status'=>'待核对','reason'=>$reason,'account_code'=>$p['account_id']];
    }

    public function submit(int $site,int $id,int $uid,array $in):array
    {
        self::authorize(true);
        if($uid<1 || (int)request()->uid()!==$uid || ($in['confirm_registration']??null)!==true) throw new Problem('RECEIPT_CONFIRM','请明确确认仅登记这张已有收款凭证');
        $lease=bin2hex(random_bytes(16));
        [$t,$p,$req,$rev]=Db::transaction(function() use($site,$id,$uid,$lease,$in){
            if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_MISSING','无效站点');
            [$c,$p]=$this->context($site);$t=$this->task($site,$id,$p,true);$s=Contract::decode($t['snapshot_json']);
            if(!self::enabled($site,$c,$p) || !self::draft($t) || ($in['revision']??null)!==(int)$t['revision']
                || ($in['mode']??'')!=='test' || ($in['connection_key']??'')!==$p['connection_key'] || ($in['settlement_id']??null)!==$s['settlement_id']
                || ($in['sale_id']??null)!==$s['sale_id']) throw new Problem('RECEIPT_STALE','当前账套未开启、任务已处理或原单已变化；请刷新，勿重复登记');
            $this->source($site,$t,true);
            $choice=(new PaymentSyncService())->receiptSelections($site,$p['connection_key'],$s,$in['selection']??[]);
            if($choice['missing'])throw new Problem('RECEIPT_SELECTION','请补齐原财务及资金账户对应');
            $date=(new \DateTimeImmutable('@'.$s['confirmed_at']))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d');
            $req=['schema'=>ReceiptGateway::SCHEMA,'mode'=>'test','stage'=>'frozen','account_code'=>$p['account_id'],'app_id'=>$p['app_id'],'connection_key'=>$p['connection_key'],
                'source_settlement_id'=>$s['settlement_id'],'company_id'=>$s['company_id'],'customer_id'=>$s['customer_id'],'account_id'=>$choice['selection']['account_id'],'amount'=>$s['amount'],
                'selection'=>$choice['selection'],'labels'=>$choice['labels'],
                'biz'=>['head'=>['company_id'=>$s['company_id'],'supplier_cust_id'=>$s['customer_id'],'business_date'=>$date,'scope_flag'=>3,
                    'creater_id'=>$choice['selection']['staff_id'],'creater_name'=>$choice['labels']['staff_id'],'is_auto_generate_cust'=>0,'is_auto_generate_subject'=>0,
                    'remark'=>'ERP已收凭证 '.$s['settlement_no'].' / 对应销售 '.$s['sale_external_no']],
                    'receipt_details'=>['add_details'=>[['account_id'=>$choice['selection']['account_id'],'amounts'=>$s['amount'],'handling_fee'=>'0.00','seq_no'=>1]]]]];
            ReceiptGateway::validate($req);
            Db::name('hsx_boyuan_task')->where(['id'=>$id,'site_id'=>$site])->update(['status'=>'processing','reason'=>'原收款凭证已领取投递，禁止重发','attempts'=>1,'side_effect'=>'unknown',
                'lease_token'=>$lease,'lease_until'=>time()+120,'request_json'=>Contract::json($req),'revision'=>(int)$t['revision']+1,'update_at'=>time()]);
            TaskService::audit($site,$id,$p['connection_key'],'sale_receipt_submit','processing','准备单次登记原收款凭证',$uid);
            return [$t,$p,$req,(int)$c['revision']];
        });
        $result=[];$no='';$status='attention';$reason='博远登记结果不明；仅核对原凭证，不可重新发送';$effect='unknown';
        try { [$c,$fresh]=$this->context($site);
            if($fresh!==$p || $rev!==(int)$c['revision'] || !self::enabled($site,$c,$p))throw new Problem('RECEIPT_DISABLED','账套或开关已改变，停止本次处理');
            $api=(new ConfigService())->transport($site,$p,'test',InterfaceTrace::task($t)+['operator_uid'=>$uid]);
            if(!$api instanceof OpenApi)throw new Problem('RECEIPT_UAT_ONLY','仅支持博远 UAT 官方签名接口');
            $result=(new ReceiptGateway($api))->create($req);$no=(string)$result['external_no'];$effect='applied';$status='registered_review';
            $reason='博远已登记原收款凭证；指定销售核销待核对，无需再收款';
        } catch(\Throwable $e){$result['last_issue']=ReceiptFacts::issue($e,'submit');if($e instanceof Problem)$reason=$e->getMessage().'；请核对原单，禁止重发';}
        $updated=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'id'=>$id,'mode'=>'test','connection_key'=>$p['connection_key'],'lease_token'=>$lease,'status'=>'processing'])
            ->update(['status'=>$status,'external_no'=>$no,'side_effect'=>$effect,'result_json'=>Contract::json($result),'reason'=>$reason,'lease_token'=>'','lease_until'=>0,
                'revision'=>(int)$t['revision']+2,'update_at'=>time()]);
        if($updated!==1)throw new Problem('RECEIPT_UNKNOWN','回执未能写回原任务，请核对博远原凭证，禁止重发');
        TaskService::audit($site,$id,$p['connection_key'],'sale_receipt_result',$status,$reason,$uid,['external_no'=>$no]);
        return $this->view($site,$id);
    }
}
