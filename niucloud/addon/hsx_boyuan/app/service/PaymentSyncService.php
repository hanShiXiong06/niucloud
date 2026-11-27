<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\gateway\{OpenApi,PaymentGateway,Transport};
use addon\hsx_boyuan\app\support\{ArchiveView,Contract,Problem,ProductionVoucher};
use core\exception\CommonException;
use think\facade\Db;

/** Copy the already confirmed accounting fact; never confirm a local payment or send funds. */
final class PaymentSyncService
{
    public const SCHEMA='boyuan.purchase.payment.v1';
    public const KIND='purchase_payment';
    public const UAT_ENDPOINT='https://open-api-uat.139erp.com/api';
    // Persisted status is retained so existing matched receipts need no migration.
    public const REGISTERED='registered_review';
    public const REGISTERED_REASON='已登记到博远，无需再次操作';

    public static function isRegistered(array $row): bool
    {
        return ($row['kind']??'')===self::KIND && ($row['status']??'')===self::REGISTERED;
    }

    public function capture(int $site,int $parentId,int $uid,array $input): array
    {
        $this->permissions(true); $this->keys($input,['revision','mode','connection_key','settlement_id']);
        if(!is_int($input['settlement_id']??null) || $input['settlement_id']<=0) throw new Problem('PAYMENT_SOURCE_REQUIRED','请选择本站已经财务确认的真实付款单；不接受前端金额');
        $id=Db::transaction(function() use($site,$parentId,$uid,$input): int {
            $this->lockSite($site); [$config,$profile]=$this->context($site,true); $parent=$this->parent($site,$parentId,true); $this->version($parent,$config,$profile,$input);
            $snapshot=$this->source($site,$parent,$input['settlement_id'],true); $fact='purchase-payment:settlement:'.$input['settlement_id'];
            if($config['mode']==='production' && Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production','kind'=>self::KIND,'fact_key'=>$fact])->where('connection_key','<>',$profile['connection_key'])->count())
                throw new Problem('PAYMENT_ALREADY_TRACKED','原付款凭证已在另一正式连接留下记录；请核对旧回执，不重复登记');
            $where=[['site_id','=',$site],['connection_key','=',$profile['connection_key']],['fact_key','=',$fact]]; $old=Db::name('hsx_boyuan_task')->where($where)->lock(true)->find();
            if($old) {
                if($old['snapshot_json']!==Contract::json($snapshot)) throw new Problem('PAYMENT_FACT_CONFLICT','该结算单已用于其他来源或原事实变化，未新建或覆盖付款任务');
                return (int)$old['id'];
            }
            // Suggestions are read from the original voucher's current mappings when the draft is opened.
            // Persist only explicit choices. The purchase author and the person syncing are not the payer.
            $selection=['creator_id'=>'','account_id'=>''];
            $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'mode'=>$config['mode'],'kind'=>self::KIND,'fact_key'=>$fact,
                'event_id'=>'payment:settlement:'.$input['settlement_id'],'operation_id'=>'BPAY_'.hash('sha256',$site.':'.$profile['connection_key'].':'.$fact),'business_no'=>$snapshot['settlement_no'],
                'status'=>'awaiting_confirmation','reason'=>'原付款凭证已准备，金额来自本站已确认付款；尚未向博远登记',
                'snapshot_json'=>Contract::json($snapshot),'request_json'=>Contract::json(['schema'=>self::SCHEMA,'stage'=>'draft','source_task_id'=>$parentId,'voucher_actor_uid'=>$snapshot['operator_uid'],'selection'=>$selection]),'result_json'=>'{}',
                'next_at'=>0,'create_at'=>time(),'update_at'=>time()]);
            TaskService::audit($site,$id,$profile['connection_key'],'payment_capture','awaiting_confirmation','仅准备原付款凭证，未外发',$uid); return $id;
        });
        return $this->view($site,$id);
    }

    public function view(int $site,int $id): array
    {
        $this->permissions(false); [$config,$profile]=$this->context($site,false); $task=$this->task($site,$id); $snapshot=Contract::decode($task['snapshot_json']); $request=Contract::decode($task['request_json']);
        $selection=(array)($request['selection']??['creator_id'=>'','account_id'=>'']); $issues=[]; $selectionReady=false; $ap=$this->unverifiedAp(); $sourceCurrent=false;
        $mapped=['creator_id'=>'','account_id'=>''];
        if($task['status']==='awaiting_confirmation' && $task['side_effect']==='none' && (int)$task['attempts']===0 && ($request['stage']??'')==='draft') {
            $mapped=$this->voucherMappings($site,$task['connection_key'],$snapshot);
            // An older, unsent draft may contain a default copied from the buyer. Do not present it as finance's choice.
            if(($request['voucher_actor_uid']??null)!==$snapshot['operator_uid']) $selection['creator_id']='';
            foreach($mapped as $field=>$value) if($value!=='') $selection[$field]=$value;
        }
        $registered=self::isRegistered($task);
        if($registered) {
            // A stored matched receipt survives later edits to master data, settings and AP proof expiry.
            // It is not an invitation to re-open or repeat a once-only financial registration.
            $ap=$this->apEvidence($site,$task,$snapshot);
        } else {
            try {
                $parent=$this->parent($site,(int)$snapshot['source_task_id']);
                if(Contract::json($this->source($site,$parent,(int)$snapshot['settlement_id']))!==$task['snapshot_json']) throw new Problem('PAYMENT_SOURCE_CHANGED','本站付款或采购来源已变化；原快照不会覆盖');
                $sourceCurrent=true; $ap=$this->apEvidence($site,$task,$snapshot); $this->prepare($site,$task,$selection,false); $selectionReady=$selection['creator_id']!=='' && $selection['account_id']!=='';
                if($selection['creator_id']==='') $issues[]='请选择原付款登记财务对应的博远员工';
                if($selection['account_id']==='') $issues[]='请选择原付款账户对应的博远资金账户';
            } catch(Problem $error) { $issues[]=$error->getMessage(); }
        }
        $prod=$task['mode']==='production';
        if(!$prod && !$registered && !$ap['verified']) $issues[]=$ap['reason'];
        $enabled=$this->enabled($config,$profile); $active=$task['mode']===$config['mode'] && $task['connection_key']===$profile['connection_key']; $canOperate=$this->mayOperate();
        if(!$registered && (!$enabled || !$active)) $issues[]='本账套未开启手动付款凭证同步，或连接已暂停；原付款不受影响';
        $editable=$enabled && $active && $canOperate && $task['status']==='awaiting_confirmation' && $task['side_effect']==='none' && (int)$task['attempts']===0 && ($request['stage']??'')==='draft';
        $public=array_intersect_key($task,array_flip(['id','revision','mode','connection_key','kind','status','reason','external_no','business_no','attempts','side_effect','create_at','update_at']));
        foreach(['id','revision','attempts','create_at','update_at'] as $field) $public[$field]=(int)$public[$field]; $public['parent_purchase_task_id']=(int)$snapshot['source_task_id'];
        $result=$this->safeResult(Contract::decode($task['result_json']));
        $source=array_intersect_key($snapshot,array_flip(['source_task_id','source_document_no','external_purchase_no','settlement_id','settlement_no','confirmed_at','amount','currency','company_id','company_code','supplier_id','scope','operator_uid','capital_account_id']))+$this->voucherNames($site,$snapshot);
        $source['parent_purchase_task_id']=(int)$snapshot['source_task_id'];
        if($registered) $public['reason']=self::REGISTERED_REASON;
        $canRemember=false;
        if($editable) try { (new PermissionService())->assertApi('post','boyuan/mappings'); $canRemember=true; } catch(CommonException $e) {}
        return ['task'=>$public,'source'=>$source,'operator_complete'=>$registered,'can_remember'=>$canRemember,'mapped_fields'=>array_map(static fn($v)=>$v!=='',$mapped),
            'can_process_current'=>$sourceCurrent && $canOperate && self::currentEligible($config,$task),
            'current_account_code'=>$active && $config['mode']==='test'?(string)$profile['account_id']:'',
            'parent_purchase_task_id'=>(int)$snapshot['source_task_id'],'selection'=>$selection,'labels'=>$this->labels($site,$task['connection_key'],$snapshot,$selection),'ap_evidence'=>$ap,
            'editable'=>$editable,'can_submit'=>$editable && $selectionReady && $sourceCurrent && ($prod || $ap['verified']),
            'can_submit_experiment'=>!$prod && $editable && $selectionReady && $sourceCurrent && !$ap['verified'],'can_verify'=>false,'issues'=>array_values(array_unique($issues)),'result'=>$result,
            'uat_only'=>!$prod,'query_unavailable_reason'=>'当前没有已验证的官方PAYMENT查单或指定核销契约；手填单号不能标记成功，未知结果必须人工查原单且不得重建'];
    }
    public function saveDraft(int $site,int $id,int $uid,array $input): array { return $this->edit($site,$id,$uid,$input,false); }
    public function submit(int $site,int $id,int $uid,array $input): array { return $this->edit($site,$id,$uid,$input,true); }
    private function edit(int $site,int $id,int $uid,array $input,bool $submit): array
    {
        $this->permissions(true); $this->keys($input,['revision','mode','connection_key','selection','confirm_registration','confirm_unverified_ap','remember']);
        if(array_key_exists('remember',$input) && !is_bool($input['remember'])) throw new Problem('INPUT_INVALID','记住对应须为明确布尔选项');
        if(!empty($input['remember'])) (new PermissionService())->assertApi('post','boyuan/mappings');
        foreach(['confirm_registration','confirm_unverified_ap'] as $flag) if(array_key_exists($flag,$input) && !is_bool($input[$flag])) throw new Problem('PAYMENT_CONFIRM_REQUIRED','付款登记风险确认必须为明确的布尔选项');
        if($submit && ($input['confirm_registration']??null)!==true) throw new Problem('PAYMENT_CONFIRM_REQUIRED','请确认只同步这张已有付款凭证，不是再次打款或确认核销');
        Db::transaction(function() use($site,$id,$uid,$input,$submit): void {
            $this->lockSite($site); [$config,$profile]=$this->context($site,true); $task=$this->task($site,$id,true); $this->version($task,$config,$profile,$input); $stored=Contract::decode($task['request_json']);
            if($task['status']!=='awaiting_confirmation' || $task['side_effect']!=='none' || (int)$task['attempts']!==0 || ($stored['stage']??'')!=='draft') throw new Problem('PAYMENT_FROZEN','付款登记已提交或可能已外发，不能修改、重复登记或按未知结果重建');
            $snapshot=Contract::decode($task['snapshot_json']); $parent=$this->parent($site,(int)$snapshot['source_task_id'],true);
            if(Contract::json($this->source($site,$parent,(int)$snapshot['settlement_id'],true))!==$task['snapshot_json']) throw new Problem('PAYMENT_SOURCE_CHANGED','本站已确认付款或采购来源已变化，未外发');
            $selection=$this->selection($input['selection']??null); $experiment=$submit && ($input['confirm_unverified_ap']??false)===true;
            if($experiment && $task['mode']!=='test') throw new Problem('PAYMENT_UAT_ONLY','正式账套不支持测试实验');
            foreach($this->voucherMappings($site,$task['connection_key'],$snapshot) as $field=>$mapped) {
                if($mapped!=='' && $selection[$field]!==$mapped) throw new Problem('PAYMENT_MAPPING_CHANGED','原付款财务或账户已有有效对应，请刷新后按原付款凭证同步');
            }
            foreach(['creator_id'=>['staff','operator_uid'],'account_id'=>['account','capital_account_id']] as $field=>[$kind,$local]) {
                $external=$selection[$field]; if($external==='') continue;
                $ref=$kind.':'.$snapshot[$local];
                if(Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$task['connection_key'],'kind'=>$kind,'external_id'=>$external])
                    ->where('local_ref','<>',$ref)->count() || ($kind==='staff' && $this->bridgeVoucherStaff($site,$task['connection_key'],$ref,$external)!==$external))
                    throw new Problem('MAPPING_CONFLICT','所选博远人员或账户与原付款资料的对应关系冲突；请先核对资料，不会覆盖或重复投递');
            }
            if(!empty($input['remember'])) $this->rememberVoucherMappings($site,$task['connection_key'],$snapshot,$selection,$uid);
            $request=$this->prepare($site,$task,$selection,$submit,$experiment);
            Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['request_json'=>Contract::json($request),'status'=>$submit?'pending':'awaiting_confirmation',
                'reason'=>$submit?($experiment?'已确认UAT付款登记实验，等待单次登记':'已确认原付款凭证，等待博远登记；原采购核销待核对'):'已保存付款凭证选择，尚未外发',
                'revision'=>(int)$task['revision']+1,'query_only'=>0,'next_at'=>$submit?time()+1:0,'update_at'=>time()]);
            TaskService::audit($site,$id,$task['connection_key'],$submit?'payment_submit':'payment_draft',$submit?'pending':'awaiting_confirmation',$submit?'付款登记已冻结，最多执行一次创建；不发起转账':'付款登记草稿已保存，未外发',$uid);
        });
        if($submit) $this->dispatchLater($site,$id); return $this->view($site,$id);
    }

    public function candidates(int $site,int $parentId,array $input=[]): array
    {
        $this->permissions(false); $parent=$this->parent($site,$parentId); $snapshot=Contract::decode($parent['snapshot_json']); (new PurchaseSyncService())->assertClosureSourceCurrent($site,$snapshot);
        $assets=array_map(static fn($line)=>(int)substr($line['device_ref'],6),$snapshot['lines']);
        $payables=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->whereIn('source_id',$assets)->column('id');
        $ids=$payables?Db::name('erp_settlement_link')->where('site_id',$site)->where('target_type','payable')->whereIn('target_id',$payables)->column('settlement_id'):[];
        $ids=array_values(array_unique(array_map('intval',$ids))); $page=max(1,(int)($input['page']??1)); $limit=min(20,max(1,(int)($input['limit']??20)));
        if(isset($input['settlement_id'])) {
            $filter=$input['settlement_id'];
            if((!is_string($filter) && !is_int($filter)) || !preg_match('/^[1-9][0-9]*$/D',(string)$filter) || (string)(int)$filter!==(string)$filter)
                throw new Problem('PAYMENT_SOURCE_REQUIRED','原付款单标识无效，请重新打开 ERP 原单');
            $ids=in_array((int)$filter,$ids,true)?[(int)$filter]:[];
        }
        $total=$ids?Db::name('erp_settlement')->where('site_id',$site)->whereIn('id',$ids)->where('status','confirmed')->where('settlement_type','payment')->where('cash_direction','out')->count():0;
        $rows=$ids?Db::name('erp_settlement')->where('site_id',$site)->whereIn('id',$ids)->where('status','confirmed')->where('settlement_type','payment')->where('cash_direction','out')->field('id,settlement_no,confirmed_at,amount')->order('id desc')->page($page,$limit)->select()->toArray():[];
        $items=[];
        foreach($rows as $row) {
            $item=['settlement_id'=>(int)$row['id'],'settlement_no'=>$row['settlement_no'],'confirmed_at'=>(int)$row['confirmed_at'],'amount'=>null,'eligible'=>false,'reason'=>'','task_id'=>null];
            try { $source=$this->source($site,$parent,(int)$row['id']); $item['amount']=$source['amount']; $item['eligible']=true;
                $old=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$parent['connection_key'])->where('fact_key','purchase-payment:settlement:'.$row['id'])->find();
                if($old) { $item['task_id']=(int)$old['id']; if($old['status']!=='awaiting_confirmation') { $item['eligible']=false; $item['reason']=self::isRegistered($old)?self::REGISTERED_REASON:'该付款已有登记任务，请查看原任务；未知结果不得再次创建'; } }
            } catch(Problem $error) { $item['reason']=$error->getMessage(); }
            $items[]=$item;
        }
        return ['items'=>$items,'total'=>$total,'has_more'=>$page*$limit<$total,'next_page'=>$page*$limit<$total?$page+1:null];
    }
    public function summary(int $site,array $parent): array
    {
        $empty=['uat_only'=>($parent['mode']??'')!=='production','enabled'=>false,'can_capture'=>false,'candidates'=>['items'=>[],'total'=>0,'has_more'=>false,'next_page'=>null],
            'task_id'=>null,'status'=>'unavailable','operator_complete'=>false,'registered_amount'=>null,'allocation_verified'=>false,'bank_transfer_verified'=>false,'ap_evidence'=>$this->unverifiedAp(),'reason'=>'本连接未启用原付款凭证同步'];
        if((int)($parent['site_id']??0)!==$site || ($parent['kind']??'')!=='purchase') return $empty;
        try {
            $this->permissions(false); [$config,$profile]=$this->context($site,false);
            $uat=$this->uat($config,$profile); $prod=$config['mode']==='production' && ProductionVoucher::profile($profile);
            if((!$uat && !$prod) || $parent['mode']!==$config['mode'] || $parent['connection_key']!==$profile['connection_key']) return $empty;
            $empty['uat_context']=$uat; $empty['production_context']=$prod; $empty['enabled']=$this->enabled($config,$profile); $empty['candidates']=$this->candidates($site,(int)$parent['id']);
            $empty['status']='not_captured'; $empty['reason']='仅可同步本站财务已确认的付款凭证；不发起转账，核销另行核对';
            $empty['can_capture']=$empty['enabled'] && $this->mayOperate() && count(array_filter($empty['candidates']['items'],static fn($item)=>$item['eligible']))>0;
            $rows=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$parent['connection_key'])->where('mode',$parent['mode'])->where('kind',self::KIND)->order('id desc')->select()->toArray();
            foreach($rows as $row) {
                $source=Contract::decode($row['snapshot_json']); if(($source['source_task_id']??0)!==(int)$parent['id'] || ($source['source_snapshot_hash']??'')!==hash('sha256',$parent['snapshot_json'])) continue;
                $empty['task_id']=(int)$row['id']; $empty['status']=$row['status']; $empty['reason']=$row['reason']; $result=$this->safeResult(Contract::decode($row['result_json']));
                $empty['operator_complete']=self::isRegistered($row);
                if($empty['operator_complete']) $empty['reason']=self::REGISTERED_REASON;
                $empty['registered_amount']=$result['registered_amount']??null; $empty['ap_evidence']=$this->apEvidence($site,$row,$source); break;
            }
            if($empty['task_id']===null && $parent['status']==='succeeded' && (Contract::decode($parent['request_json'])['biz']['head']['cash_term']??null)===0) $empty['ap_evidence']=$this->unverifiedAp(true);
            return $empty;
        } catch(CommonException $error) { return array_replace($empty,['status'=>'permission_denied','reason'=>'无ERP应付查看权限，付款事实及登记金额不展示']); }
        catch(Problem $error) { return array_replace($empty,['reason'=>$error->getMessage()]); }
        catch(\Throwable $error) { $this->log($site,(int)($parent['id']??0),$error); return $empty; }
    }

    /** Only a never-sent, already frozen UAT voucher. This is not a second submission or a retry. */
    public static function currentEligible(array $config,array $task): bool
    {
        $r=Contract::decode($task['request_json']??''); $s=Contract::decode($task['snapshot_json']??''); $p=$config['profiles']['test']??[];
        return ManualDocumentCommandService::eligible($config) && !empty($config['uat_payment_enabled']) && !empty($p['account_id'])
            && ($task['mode']??null)==='test' && ($task['connection_key']??null)===$p['connection_key'] && ($task['kind']??null)===self::KIND
            && ($task['status']??null)==='pending' && ($task['side_effect']??null)==='none' && (int)($task['attempts']??-1)===0
            && empty($task['external_no']) && empty($task['resolution']) && empty($task['query_only']) && empty($task['lease_token']) && (int)($task['next_at']??PHP_INT_MAX)<=time()
            && ($r['schema']??null)===self::SCHEMA && ($r['stage']??null)==='frozen' && ($r['connection_key']??null)===$p['connection_key']
            && (int)($s['settlement_id']??0)>0 && (int)($s['source_task_id']??0)>0 && !empty($s['confirmed_at'])
            && ($task['fact_key']??null)==='purchase-payment:settlement:'.$s['settlement_id'] && ($task['business_no']??null)===($s['settlement_no']??null)
            && ($r['source_settlement_id']??null)===$s['settlement_id'] && ($r['source_task_id']??null)===$s['source_task_id'];
    }
    public function processCurrent(int $site,int $id,int $uid,array $input,?Transport $transport=null): array
    {
        $this->permissions(true); $this->keys($input,['revision','mode','connection_key','account_code','purchase_task_id','settlement_id']);
        [$config,$profile]=$this->context($site,true); $task=$this->task($site,$id); $s=Contract::decode($task['snapshot_json']);
        $this->version($task,$config,$profile,$input);
        if($uid<1 || !self::currentEligible($config,$task) || ($input['account_code']??null)!==$profile['account_id']
            || ($input['purchase_task_id']??null)!==$s['source_task_id'] || ($input['settlement_id']??null)!==$s['settlement_id'])
            throw new Problem('PAYMENT_CURRENT_UNAVAILABLE','只可处理原 UAT 账套已确认且尚未发送的本笔凭证。请刷新核对，不要重提付款。');
        $this->process($site,$id,$transport,$input+['config_revision'=>(int)$config['revision']]);
        [$fresh,$p]=$this->context($site,false);
        if($fresh['mode']!=='test' || $p['connection_key']!==$profile['connection_key'] || $p['account_id']!==$profile['account_id'])
            throw new Problem('CONNECTION_CHANGED','账套已变化，请在原 UAT 账套核对付款回执，不要再次发送。');
        return $this->view($site,$id);
    }
    public function process(int $site,int $id,?Transport $transport=null,?array $command=null): bool
    {
        try { [$config,$profile]=$this->context($site,true); } catch(Problem $error) { return false; }
        $task=null; $claimed=false; $lease=bin2hex(random_bytes(16)); $request=[];
        try {
            Db::transaction(function() use($site,$id,$lease,$command,&$task,&$claimed,&$config,&$profile,&$request): void {
                $this->lockSite($site); [$config,$profile]=$this->context($site,true); $task=$this->task($site,$id,true);
                // A worker or a settings edit may have won the race since opening the dialog.
                // In that case do not acquire the lease, update the task, or call the provider.
                if($command!==null) {
                    $s=Contract::decode($task['snapshot_json']);
                    if(!self::currentEligible($config,$task) || $command['mode']!==$task['mode'] || $command['connection_key']!==$task['connection_key']
                        || $command['config_revision']!==(int)$config['revision'] || $command['revision']!==(int)$task['revision']
                        || $command['account_code']!==$profile['account_id'] || $command['purchase_task_id']!==$s['source_task_id'] || $command['settlement_id']!==$s['settlement_id']) return;
                }
                if($task['connection_key']!==$profile['connection_key'] || $task['mode']!==$config['mode'] || (int)$task['next_at']>time()) return;
                $stale=$task['status']==='processing' && (int)$task['lease_until']<time(); if($task['status']!=='pending' && !$stale) return;
                $request=Contract::decode($task['request_json']);
                if(($request['stage']??'')!=='frozen') return;
                if($task['side_effect']!=='none' || (int)$task['attempts']>0) { Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'attention','reason'=>'原付款登记可能已发送，停止重建；官方查单未接通，请人工核查原PAYMENT','next_at'=>0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]); return; }
                $snapshot=Contract::decode($task['snapshot_json']); $parent=$this->parent($site,(int)$snapshot['source_task_id'],true);
                if(Contract::json($this->source($site,$parent,(int)$snapshot['settlement_id'],true))!==$task['snapshot_json']) throw new Problem('PAYMENT_SOURCE_CHANGED','付款登记来源变化，未发送');
                $expected=$this->prepare($site,$task,$request['selection']??[],true,($request['uat_registration_experiment']??false)===true);
                if(Contract::json($expected)!==$task['request_json']) throw new Problem('PAYMENT_REQUEST_CHANGED','冻结付款请求、档案或应付证据发生变化，未发送');
                Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'processing','lease_token'=>$lease,'lease_until'=>time()+90,'attempts'=>1,'side_effect'=>'unknown','update_at'=>time()]); $claimed=true;
            });
            if(!$claimed) return false;
            [$fresh,$freshProfile]=$this->context($site,true); if($fresh['mode']!==$config['mode'] || $freshProfile!==$profile) throw new Problem('PAYMENT_CONNECTION_CHANGED','发送前UAT连接配置已变化，停止登记','unknown');
            $api=$transport??(new ConfigService())->transport($site,$profile,$config['mode'],\addon\hsx_boyuan\app\support\InterfaceTrace::task($task)); if(!$api instanceof OpenApi) throw new Problem('PAYMENT_PROTOCOL_REQUIRED','只允许当前账套的博远官方网关','unknown');
            $reply=(new PaymentGateway($api))->create($request); $result=$this->safeResult($reply);
            $result['ap_evidence_verified']=($request['ap_evidence_verified']??false)===true; $result['uat_registration_experiment']=($request['uat_registration_experiment']??false)===true;
            if(($reply['created']??false)!==true || ($reply['payment_registered']??false)!==true || ($reply['verification_level']??'')!=='payment_create_receipt_matched' || ($reply['amount']??null)!==$request['amount']
                || ($reply['verified']??null)!==false || ($reply['financial_posting_verified']??null)!==false || ($reply['allocation_applied_verified']??null)!==false || ($reply['bank_transfer_verified']??null)!==false) throw new Problem('PAYMENT_RECEIPT_INVALID','付款回执未按冻结金额及登记阶段核验，结果未知；不得重复创建','unknown');
            Contract::id($reply['external_id']??null); Contract::id($reply['external_no']??null);
            $owned=$this->owned($site,$id,$task,$lease);
            Db::transaction(function() use($site,$id,$task,$request,$result,$reply,$owned): void {
                $this->lockSite($site); $row=Db::name('hsx_boyuan_task')->where($owned)->lock(true)->find(); if(!$row) return;
                $valid=true; try { $snapshot=Contract::decode($row['snapshot_json']); $parent=$this->parent($site,(int)$snapshot['source_task_id'],true); if(Contract::json($this->source($site,$parent,(int)$snapshot['settlement_id'],true))!==$row['snapshot_json']) $valid=false; } catch(Problem $error) { $valid=false; }
                $source=Contract::decode($row['snapshot_json']); $where=[['site_id','=',$site],['connection_key','=',$row['connection_key']],['kind','=','payment_registration'],['local_ref','=','settlement:'.$source['settlement_id']]];
                $existing=Db::name('hsx_boyuan_binding')->where($where)->lock(true)->find();
                $conflict=$existing && ((int)$existing['task_id']!==$id || (string)$existing['external_id']!==$reply['external_id'] || (string)$existing['external_no']!==$reply['external_no']);
                $others=Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('connection_key',$row['connection_key'])->where('kind','payment_registration')->where('local_ref','<>','settlement:'.$source['settlement_id']);
                // The same remote voucher cannot satisfy multiple local settlements. Site lock serializes this check.
                if((clone $others)->where('external_id',$reply['external_id'])->find()) $conflict=true;
                foreach($others->where('external_no',$reply['external_no'])->column('detail_json') as $detail) {
                    $otherCompany=Contract::decode($detail)['evidence']['company_id']??'';
                    if($otherCompany==='' || $otherCompany===$source['company_id']) $conflict=true;
                }
                $status=$valid && !$conflict?self::REGISTERED:'attention'; $reason=$valid?self::REGISTERED_REASON:'登记期间本地来源变化，已保留付款创建回执；必须人工核查，禁止重新登记';
                if($conflict) $reason='原付款登记绑定与本次创建回执冲突，回执已保留；禁止覆盖绑定或重新登记，请人工核查';
                Db::name('hsx_boyuan_task')->where($owned)->update(['status'=>$status,'external_no'=>$reply['external_no'],'side_effect'=>'unknown','result_json'=>Contract::json($result),
                    'reason'=>$reason,'revision'=>(int)$row['revision']+1,'next_at'=>0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
                // Registration binding is not an AP or allocation binding and cannot settle local payables.
                if(!$existing && !$conflict) Db::name('hsx_boyuan_binding')->insert(['site_id'=>$site,'connection_key'=>$row['connection_key'],'kind'=>'payment_registration','local_ref'=>'settlement:'.$source['settlement_id'],
                    'task_id'=>$id,'external_id'=>$reply['external_id'],'external_no'=>$reply['external_no'],'detail_json'=>Contract::json($result),'update_at'=>time()]);
                TaskService::audit($site,$id,$row['connection_key'],'payment_result',$status,$reason,0,['external_no'=>$reply['external_no']]);
            });
        } catch(\Throwable $error) {
            if(!$task) throw $error;
            $where=$claimed?$this->owned($site,$id,$task,$lease):[['site_id','=',$site],['id','=',$id],['connection_key','=',$task['connection_key']],['mode','=',$task['mode']],['revision','=',(int)$task['revision']],['status','=',$task['status']],['lease_token','=',$task['lease_token']],['lease_until','=',(int)$task['lease_until']],['attempts','=',(int)$task['attempts']]];
            $reason=$error instanceof Problem?$error->getMessage():'付款登记未取得确定回执，请人工核查；禁止重复登记';
            $saved=Contract::decode($task['result_json']); $saved['last_issue']=\addon\hsx_boyuan\app\support\ReceiptFacts::issue($error,$claimed?'submit':'prepare');
            Db::name('hsx_boyuan_task')->where($where)->update(['status'=>'attention','reason'=>mb_substr($reason,0,1000),'result_json'=>Contract::json($saved),'next_at'=>0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
            TaskService::audit($site,$id,$task['connection_key'],'payment_process','attention',$reason,0,['code'=>$error instanceof Problem?$error->reason:'LOCAL_ERROR']); if(!$error instanceof Problem) $this->log($site,$id,$error);
            return true;
        }
        return true;
    }

    private function source(int $site,array $parent,int $settlementId,bool $lock=false): array
    {
        $captured=Contract::decode($parent['snapshot_json']); $purchase=Contract::decode($parent['request_json']); $receipt=Contract::decode($parent['result_json']);
        if($parent['status']!=='succeeded' || ($purchase['schema']??'')!=='boyuan.purchase.v154' || ($receipt['verified']??false)!==true || ($receipt['verification_level']??'')!=='purchase_imei_records_matched') throw new Problem('PAYMENT_PURCHASE_REQUIRED','原采购记录尚未逐台核验，不能登记付款');
        if($parent['mode']==='production') {
            $head=$receipt['provider_result']??[];
            if(($purchase['biz']['head']['cash_term']??null)!==0 || empty($parent['external_no']) || ($receipt['external_no']??null)!==$parent['external_no'] ||
                ($receipt['evidence']['record_is_effective']??false)!==true || ($head['bill_id']??null)!==$parent['external_no'] ||
                empty($receipt['external_id']) || ($head['id']??null)!==$receipt['external_id'] || ($head['bill_type']??'')!=='PUR' || ($head['cash_term']??null)!==0)
                throw new Problem('PAYMENT_PURCHASE_REQUIRED','请先取得原采购的挂账回执；结算方式未确认或已现结时，不再向博远登记付款凭证。');
        }
        (new PurchaseSyncService())->assertClosureSourceCurrent($site,$captured);
        $settlement=Db::name('erp_settlement')->where('site_id',$site)->where('id',$settlementId)->lock($lock)->field('id,settlement_no,party_id,settlement_type,amount,cash_direction,capital_account_id,status,operator_uid,confirmed_at')->find();
        if(!$settlement || $settlement['status']!=='confirmed' || $settlement['settlement_type']!=='payment' || $settlement['cash_direction']!=='out' || (int)$settlement['confirmed_at']<=0 || (int)$settlement['confirmed_at']>time()+300 || (int)$settlement['operator_uid']<=0 || (int)$settlement['capital_account_id']<=0) throw new Problem('PAYMENT_SOURCE_REQUIRED','仅支持本站有效员工已财务确认的payment/out结算，不接受临时金额或未确认付款');
        $amount=$this->cents($settlement['amount']); if($amount<=0) throw new Problem('PAYMENT_AMOUNT_INVALID','已确认付款金额必须为正的精确两位金额');
        if('party:'.$settlement['party_id']!==$captured['party']['ref']) throw new Problem('PAYMENT_PARTY_MISMATCH','本地付款对象与原采购供应商不一致');
        $byAsset=[]; foreach($captured['lines'] as $line) $byAsset[(int)substr($line['device_ref'],6)]=$line;
        $links=Db::name('erp_settlement_link')->where('site_id',$site)->where('settlement_id',$settlementId)->lock($lock)->field('id,target_type,target_id,applied_amount')->order('id')->select()->toArray();
        if(!$links || count($links)>100) throw new Problem('PAYMENT_LINKS_REQUIRED','付款必须具备1至100条真实应付分配');
        $lines=[]; $sum=0; $seenPayables=[];
        foreach($links as $link) {
            if($link['target_type']!=='payable') throw new Problem('PAYMENT_SINGLE_BATCH_REQUIRED','首版仅支持完全覆盖一个采购批次的付款；混合目标不支持');
            $payable=Db::name('erp_payable')->where('site_id',$site)->where('id',(int)$link['target_id'])->lock($lock)->field('id,party_id,source_type,source_id,amount,settled_amount,status')->find();
            $line=$payable && $payable['source_type']==='purchase_asset'?($byAsset[(int)$payable['source_id']]??null):null;
            if(!$line || !in_array($payable['status'],['pending','partial','settled'],true) || 'party:'.$payable['party_id']!==$captured['party']['ref']) throw new Problem('PAYMENT_SINGLE_BATCH_REQUIRED','该付款跨越采购批次、包含非采购或已作废应付；本期不拆分登记，请核查原结算');
            $applied=$this->cents($link['applied_amount']);
            $payableId=(int)$payable['id']; $payableAmount=$this->cents($payable['amount']); $settled=$this->cents($payable['settled_amount']);
            if(isset($seenPayables[$payableId])) throw new Problem('PAYMENT_LINK_DUPLICATE','同一付款单包含重复应付分配，不能放大金额或重复登记'); $seenPayables[$payableId]=true;
            if($applied<=0 || $payableAmount!==Contract::cents($line['amount']) || $settled>$payableAmount || $applied>$settled) throw new Problem('PAYMENT_SOURCE_CHANGED','付款分配与有效本站应付不一致，未登记');
            $confirmedTotal=0;
            foreach(Db::name('erp_settlement_link')->where('site_id',$site)->where('target_type','payable')->where('target_id',$payableId)->lock($lock)->select()->toArray() as $allocation) {
                $payment=Db::name('erp_settlement')->where('site_id',$site)->where('id',(int)$allocation['settlement_id'])->lock($lock)->find();
                if(!$payment) throw new Problem('PAYMENT_SOURCE_CHANGED','应付分配缺少本站结算依据，未登记');
                if($payment['status']!=='confirmed' || $payment['settlement_type']!=='payment' || $payment['cash_direction']!=='out') continue;
                $part=$this->cents($allocation['applied_amount']);
                if((int)$payment['confirmed_at']<=0 || $confirmedTotal>PHP_INT_MAX-$part) throw new Problem('PAYMENT_SOURCE_CHANGED','已确认付款分配或金额范围无效，未登记');
                $confirmedTotal+=$part;
            }
            if($confirmedTotal!==$settled) throw new Problem('PAYMENT_SOURCE_CHANGED','应付已结算额与有效本站确认付款分配不一致，未登记');
            $binding=Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('connection_key',$parent['connection_key'])->where('kind','line')->where('local_ref',$line['source_line_id'])->find();
            $detail=$binding?Contract::decode($binding['detail_json']):[];
            if(!$binding || ($detail['external_document_id']??'')!==($receipt['external_id']??'') || $binding['external_no']!==$parent['external_no']) throw new Problem('PAYMENT_PURCHASE_BINDING_REQUIRED','缺少本采购批次逐台博远记录绑定，不使用整单第一张或其他批次单号替代');
            $lines[]=['payable_id'=>(int)$payable['id'],'source_line_id'=>$line['source_line_id'],'device_ref'=>$line['device_ref'],'amount'=>Contract::amount($applied),'payable_amount'=>Contract::amount($this->cents($payable['amount'])),
                'external_document_id'=>$detail['external_document_id'],'external_line_id'=>$binding['external_id']]; $sum+=$applied;
        }
        if($sum!==$amount) throw new Problem('PAYMENT_TOTAL_MISMATCH','原付款金额与本批次全部真实分配合计不一致，未登记');
        $company=CatalogStore::query('companies')->where('site_id',$site)->where('connection_key',$parent['connection_key'])->where('external_id',(string)$purchase['selection']['company_id'])->find();
        $companyCode=$company?(string)(Contract::decode($company['extra_json'])['gsdm']??''):'';
        return ['kind'=>self::KIND,'scope'=>'single_purchase_batch','source_task_id'=>(int)$parent['id'],'source_snapshot_hash'=>hash('sha256',$parent['snapshot_json']),
            'source_request_hash'=>hash('sha256',$parent['request_json']),'source_document_id'=>$captured['source_document_id'],'source_document_no'=>$parent['business_no'],
            'external_purchase_no'=>$parent['external_no'],'external_purchase_id'=>(string)$receipt['external_id'],'company_id'=>(string)$purchase['selection']['company_id'],'company_code'=>$companyCode,
            'supplier_id'=>(string)$purchase['biz']['head']['supplier_id'],'settlement_id'=>(int)$settlement['id'],'settlement_no'=>$settlement['settlement_no'],
            'confirmed_at'=>(int)$settlement['confirmed_at'],'operator_uid'=>(int)$settlement['operator_uid'],'capital_account_id'=>(int)$settlement['capital_account_id'],
            'amount'=>Contract::amount($amount),'currency'=>'CNY','lines'=>$lines];
    }

    private function voucherNames(int $site,array $snapshot): array
    {
        $row=Db::name('erp_settlement')->where(['site_id'=>$site,'id'=>$snapshot['settlement_id'],'operator_uid'=>$snapshot['operator_uid'],'capital_account_id'=>$snapshot['capital_account_id']])->field('operator_name,capital_account_name')->find();
        return ['operator_name'=>(string)($row['operator_name']??''),'capital_account_name'=>(string)($row['capital_account_name']??'')];
    }

    private function voucherMappings(int $site,string $key,array $snapshot): array
    {
        return ['creator_id'=>$this->mappedVoucherField($site,$key,$snapshot,'staff'),'account_id'=>$this->mappedVoucherField($site,$key,$snapshot,'account')];
    }

    private function voucherField(int $site,string $kind,array $snapshot): array
    {
        $staff=$kind==='staff'; $id=(int)$snapshot[$staff?'operator_uid':'capital_account_id']; $ref=$kind.':'.$id;
        MappingSourceService::assertMatches($site,$kind,$ref);
        if(!$staff && !Db::name('erp_capital_account')->where(['site_id'=>$site,'id'=>$id,'status'=>1])->where('account_no','not like','system:%')->count())
            throw new Problem('PAYMENT_ACCOUNT_MAPPING_REQUIRED','原付款账户未启用或不是实际资金账户，不能记住对应');
        return [$ref,$staff?'staff':'capital_accounts'];
    }

    private function mappedVoucherField(int $site,string $key,array $snapshot,string $kind,string $business='payment'): string
    {
        try {
            [$ref,$resource]=$this->voucherField($site,$kind,$snapshot);
            $links=Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>$kind]);
            $external=(string)(clone $links)->where('local_ref',$ref)->value('external_id');
            if($kind==='staff') $external=$this->bridgeVoucherStaff($site,$key,$ref,$external,$business);
            if($external==='' || (clone $links)->where('external_id',$external)->where('local_ref','<>',$ref)->count()) return '';
            $latest=Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'connection_key'=>$key,'resource'=>$resource])->order('id desc')->value('status');
            if($latest!=='completed') return '';
            $archive=$this->archive($site,$key,$resource,$external,true);
            $this->companyScope($archive,$snapshot['company_id'],$snapshot['company_code'],'PAYMENT_MAPPING_SCOPE');
            return $external;
        } catch(Problem $e) { return ''; }
    }

    private function bridgeVoucherStaff(int $site,string $key,string $ref,string $native,string $business='payment'): string
    {
        $identity='staff:'.$ref;
        return (string)(BridgeMasterSelections::merge($site,$key,$business,[$ref],[$identity=>$native])[$identity]??'');
    }

    /** Use the actual voucher author and capital account. A receipt is not a purchase payment. */
    public function receiptSelections(int $site,string $key,array $snapshot,array $choice=[]):array
    {
        if(array_diff(array_keys($choice),['staff_id','account_id'])) throw new Problem('RECEIPT_SELECTION','只可选择缺失的制单人及资金账户');
        $company=$this->archive($site,$key,'companies',$snapshot['company_id'],true);$snapshot['company_code']=(string)($company['extra']['gsdm']??'');
        $selection=$labels=[];$missing=[];
        foreach(['staff_id'=>'staff','account_id'=>'account'] as $field=>$kind){
            $mapped=$this->mappedVoucherField($site,$key,$snapshot,$kind,'receipt');
            $value=$choice[$field]??$mapped;
            if(!is_string($value) || ($mapped!=='' && $value!==$mapped)) throw new Problem('RECEIPT_MAPPING_CHANGED','制单人或资金账户对应与原凭证不一致，请刷新后核对');
            $selection[$field]=$value;$labels[$field]='';if($value===''){$missing[]=$field;continue;}
            $archive=$this->archive($site,$key,$kind==='staff'?'staff':'capital_accounts',$value,true);
            $this->companyScope($archive,$snapshot['company_id'],$snapshot['company_code'],'RECEIPT_COMPANY_SCOPE');
            $labels[$field]=(string)$archive['name'];
        }
        return ['selection'=>$selection,'labels'=>$labels,'missing'=>$missing];
    }

    /** Explicit, same-transaction insert only. Never replace a different identity, even if it is stale. */
    private function rememberVoucherMappings(int $site,string $key,array $snapshot,array $selection,int $uid): void
    {
        $names=$this->voucherNames($site,$snapshot);
        foreach(['creator_id'=>'staff','account_id'=>'account'] as $field=>$kind) {
            $external=$selection[$field]; if($external==='') continue;
            // Do not duplicate a valid bridge staff mapping in the native store when only the account was missing.
            if($this->mappedVoucherField($site,$key,$snapshot,$kind)===$external) continue;
            [$ref,$resource]=$this->voucherField($site,$kind,$snapshot);
            if(Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'connection_key'=>$key,'resource'=>$resource])->order('id desc')->value('status')!=='completed')
                throw new Problem('PAYMENT_MAPPING_STALE','请先完成博远员工或资金账户的资料同步，再记住本次对应');
            $archive=$this->archive($site,$key,$resource,$external,true);
            $this->companyScope($archive,$snapshot['company_id'],$snapshot['company_code'],'PAYMENT_MAPPING_SCOPE');
            $keys=['site_id'=>$site,'connection_key'=>$key,'kind'=>$kind];
            $old=Db::name('hsx_boyuan_mapping')->where($keys+['local_ref'=>$ref])->lock(true)->find();
            if(($old && $old['external_id']!==$external) || Db::name('hsx_boyuan_mapping')->where($keys+['external_id'=>$external])->where('local_ref','<>',$ref)->count())
                throw new Problem('MAPPING_CONFLICT','付款资料已有其他对应，请在资料对应中核对；本次没有覆盖或投递');
            if(!$old) Db::name('hsx_boyuan_mapping')->insert($keys+['local_ref'=>$ref,'local_name'=>mb_substr($names[$kind==='staff'?'operator_name':'capital_account_name'],0,255),
                'external_id'=>$external,'external_code'=>$archive['code'],'external_name'=>$archive['name'],'extra_json'=>'{}','revision'=>1,'operator_uid'=>$uid,'update_at'=>time()]);
        }
    }

    private function prepare(int $site,array $task,array $selection,bool $complete,bool $experiment=false): array
    {
        $selection=$this->selection($selection); $snapshot=Contract::decode($task['snapshot_json']); $key=$task['connection_key'];
        $company=$this->archive($site,$key,'companies',$snapshot['company_id'],true); $supplier=$this->archive($site,$key,'parties',$snapshot['supplier_id'],true);
        if(!in_array($supplier['extra']['is_supplier']??null,[1,'1',true],true)) throw new Problem('PAYMENT_SUPPLIER_INVALID','所选采购往来单位不具备供应商属性');
        $creator=$this->archive($site,$key,'staff',$selection['creator_id'],$complete); $account=$this->archive($site,$key,'capital_accounts',$selection['account_id'],$complete);
        if($creator && (strlen($creator['name'])>64 || preg_match('/[\x00-\x1f\x7f]/',$creator['name']))) throw new Problem('PAYMENT_CREATOR_INVALID','制单人名称超出官方限制');
        $companyCode=(string)($company['extra']['gsdm']??'');
        if($creator) $this->companyScope($creator,$snapshot['company_id'],$companyCode,'PAYMENT_CREATOR_SCOPE');
        if($account) {
            $this->companyScope($account,$snapshot['company_id'],$companyCode,'PAYMENT_ACCOUNT_SCOPE');
            if($task['mode']==='production' && $this->mappedVoucherField($site,$key,$snapshot,'account')!==$account['external_id'])
                throw new Problem('PAYMENT_ACCOUNT_MAPPING_REQUIRED','请先将本站原付款账户与博远资金账户唯一关联，不能把这笔钱登记到另一个账户');
        }
        if(!$complete) return ['schema'=>self::SCHEMA,'stage'=>'draft','source_task_id'=>(int)$snapshot['source_task_id'],'voucher_actor_uid'=>$snapshot['operator_uid'],'selection'=>$selection];
        $proof=$this->apEvidence($site,$task,$snapshot);
        $prod=$task['mode']==='production';
        if($prod && $experiment) throw new Problem('PAYMENT_UAT_ONLY','正式凭证不能沿用实验确认');
        if(!$prod && !$proof['verified'] && !$experiment) throw new Problem('PAYMENT_AP_EVIDENCE_REQUIRED','尚无真实博远应付形成证据；标准登记阻断。仅经独立风险确认的UAT登记实验可继续，不代表核销');
        $date=(new \DateTimeImmutable('@'.$snapshot['confirmed_at']))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d');
        $head=['company_id'=>$snapshot['company_id'],'business_date'=>$date,'supplier_cust_id'=>$snapshot['supplier_id'],'is_auto_generate_supplier'=>0,'is_auto_generate_subject'=>0,
            'scope_flag'=>3,'creater_id'=>$creator['external_id'],'creater_name'=>$creator['name'],'remark'=>mb_substr(($prod?'ERP已付凭证 ':'UAT ERP付款登记 ').$snapshot['settlement_no'].' / 采购 '.$snapshot['source_document_no'].'；非银行转账，指定核销未核验',0,256)];
        $scope=[];
        if($prod) { [$c,$p]=$this->context($site,true); if($c['mode']!=='production' || $key!==$p['connection_key']) throw new Problem('CONNECTION_CHANGED','正式连接已改变'); $scope=['scope'=>'confirmed_erp_voucher','mode'=>'production','target_account'=>$p['account_id']]; }
        return $scope+['schema'=>self::SCHEMA,'stage'=>'frozen','source_task_id'=>(int)$snapshot['source_task_id'],'source_snapshot_hash'=>$snapshot['source_snapshot_hash'],'connection_key'=>$task['connection_key'],
            'company_id'=>$snapshot['company_id'],'supplier_id'=>$snapshot['supplier_id'],'account_id'=>$account['external_id'],'amount'=>$snapshot['amount'],'selection'=>$selection,
            'uat_registration_experiment'=>$experiment,'ap_evidence_verified'=>$proof['verified'],'source_settlement_id'=>(int)$snapshot['settlement_id'],
            'biz'=>['head'=>$head,'payment_details'=>['add_details'=>[['account_id'=>$account['external_id'],'handling_fee'=>'0.00','amounts'=>$snapshot['amount'],'seq_no'=>1]]]]];
    }
    private function apEvidence(int $site,array $task,array $snapshot): array
    {
        if(empty($snapshot['lines'])) return $this->unverifiedAp();
        foreach($snapshot['lines'] as $line) {
            $binding=Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('connection_key',$task['connection_key'])->where('kind','financial')->where('local_ref','payable:'.$line['payable_id'])->find();
            $proof=$binding?Contract::decode($binding['detail_json']):[];
            if(!$binding || !is_string($binding['external_id']??null) || $binding['external_id']==='' || preg_match('/[\x00-\x20]/',$binding['external_id'])
                || ($proof['schema']??'')!=='boyuan.purchase.ap.v1' || ($proof['verified']??false)!==true || ($proof['verification_level']??'')!=='purchase_payable_records_matched'
                || ($proof['source_task_id']??null)!==$snapshot['source_task_id'] || ($proof['source_snapshot_hash']??'')!==$snapshot['source_snapshot_hash'] || ($proof['source_line_id']??'')!==$line['source_line_id']
                || ($proof['company_id']??'')!==$snapshot['company_id'] || ($proof['supplier_id']??'')!==$snapshot['supplier_id'] || ($proof['external_document_id']??'')!==$line['external_document_id']
                || ($proof['external_line_id']??'')!==$line['external_line_id'] || ($proof['amount']??null)!==$line['payable_amount'] || ($proof['currency']??'')!=='CNY'
                || !is_string($proof['unsettled_amount']??null) || $this->cents($proof['unsettled_amount'])<$this->cents($line['amount'])
                || !is_int($proof['verified_at']??null) || $proof['verified_at']<time()-300 || $proof['verified_at']>time()+5) return $this->unverifiedAp(true);
        }
        return ['verified'=>true,'status'=>'verified','reason'=>'已保存同采购批次、公司、供应商及逐台来源匹配的官方应付证据；不表示付款核销已核验'];
    }
    private function companyScope(array $archive,string $companyId,string $companyCode,string $reason): void
    {
        $owner=(string)($archive['extra']['company_id']??''); $code=(string)($archive['extra']['gsdm']??'');
        if(($owner==='' && $code==='') || ($owner!=='' && $owner!==$companyId) || ($code!=='' && ($companyCode==='' || $code!==$companyCode))) throw new Problem($reason,'制单人与付款账户必须明确属于该采购公司；未知归属、跨公司或相互矛盾的归属均不允许');
    }
    private function archive(int $site,string $key,string $resource,string $id,bool $required): ?array
    {
        if($id==='') { if($required) throw new Problem('PAYMENT_SELECTION_REQUIRED','请选择博远制单人与本采购公司的启用付款账户'); return null; }
        Contract::id($id); if(strlen($id)>36) throw new Problem('PAYMENT_ARCHIVE_INVALID','博远档案身份超过官方字段长度');
        $row=CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)->where('external_id',$id)->where('enabled',1)->find(); $run=ReferenceService::latestRun($site,$key,$resource);
        if(!$row || $run==='' || $row['seen_run']!==$run) throw new Problem('PAYMENT_ARCHIVE_STALE','请先完整同步并选择当前UAT连接最新启用档案'); $row['extra']=ArchiveView::extras($row['extra_json']); unset($row['extra_json']); return $row;
    }
    private function selection($value): array
    {
        if(!is_array($value)) throw new Problem('PAYMENT_SELECTION_INVALID','付款登记选择格式无效'); $this->keys($value,['creator_id','account_id']);
        foreach(['creator_id','account_id'] as $field) if(!is_string($value[$field]??null)) throw new Problem('PAYMENT_SELECTION_INVALID','外部档案ID必须为字符串'); return $value;
    }
    private function labels(int $site,string $key,array $snapshot,array $selection): array
    {
        $get=static function(string $resource,string $id) use($site,$key): ?array { if($id==='') return null; $row=CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)->where('external_id',$id)->field('external_id,name,code')->find(); return $row?['id'=>$row['external_id'],'name'=>$row['name'],'code'=>$row['code']]:null; };
        return ['company'=>$get('companies',$snapshot['company_id']),'supplier'=>$get('parties',$snapshot['supplier_id']),'creator'=>$get('staff',(string)($selection['creator_id']??'')),'account'=>$get('capital_accounts',(string)($selection['account_id']??''))];
    }
    private function safeResult(array $reply): array
    {
        $safe=array_intersect_key($reply,array_flip(['created','payment_registered','verified','external_no','external_id','amount','verification_level','financial_posting_verified','allocation_applied_verified','bank_transfer_verified','ap_evidence_verified','uat_registration_experiment']));
        if(($safe['payment_registered']??false)===true) $safe['registered_amount']=$safe['amount']??null;
        if(is_array($reply['provider_result']??null)) $safe['provider_result']=\addon\hsx_boyuan\app\support\ReceiptFacts::head($reply['provider_result']);
        if(isset($reply['response']) && is_array($reply['response'])) {
            $safe['response']['head']=array_intersect_key((array)($reply['response']['head']??[]),array_flip(['id','big_gsdm','bill_id','bill_type','company_id','supplier_cust_id','business_date','scope_flag','creater_id']));
            $safe['response']['payment_details']['add_details']=array_map(static fn($row)=>array_intersect_key((array)$row,array_flip(['id','account_id','amounts','handling_fee','subject_id','payment_type_id','seq_no'])),(array)($reply['response']['payment_details']['add_details']??[]));
        }
        if(isset($reply['evidence']) && is_array($reply['evidence'])) $safe['evidence']=array_intersect_key($reply['evidence'],array_flip(['response_contract','environment','scope_flag','company_id','supplier_id','account_id','amount','checked_at','payment_service','limitations'])); return $safe;
    }
    private function context(int $site,bool $write): array
    {
        $service=new ConfigService(); if($site<=0 || !$service->installed($site)) throw new Problem('ADDON_MISSING','本站需安装ERP及博远插件'); $config=$service->get($site,false); $profile=$config['profiles'][$config['mode']];
        if($write && !$this->enabled($config,$profile)) throw new Problem('PAYMENT_UAT_ONLY','请先启用本账套的手动付款凭证同步；未核验或已暂停的连接不可提交'); return [$config,$profile];
    }
    private function uat(array $config,array $profile): bool { return $config['mode']==='test' && ($profile['protocol']??'')==='openapi_v153' && ($profile['endpoint']??'')===self::UAT_ENDPOINT && ($profile['gateway_path']??'')==='/gateway.do'; }
    private function enabled(array $config,array $profile): bool { return (ProductionVoucher::ready($config,$profile) && ProductionVoucher::released($profile)) || ($this->uat($config,$profile) && !empty($config['uat_payment_enabled']) && !empty($config['enabled']) && !empty($config['purchase_enabled']) && empty($config['paused']) && !empty($profile['probe_at']) && !empty($profile['contract_confirmed'])); }
    private function permissions(bool $write): void
    {
        $permission=new PermissionService(); $permission->assertApi('get','boyuan/tasks/<id>'); $permission->assertApi('get','erp/finance/payable/lists');
        if($write) { $permission->assertApi('post','boyuan/tasks/<id>/action'); $permission->assertApi('post','erp/finance/payable/party/<party_id>/confirm_items_payment'); }
    }
    private function mayOperate(): bool { try { $this->permissions(true); return true; } catch(CommonException $error) { return false; } }
    private function parent(int $site,int $id,bool $lock=false): array { $row=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->lock($lock)->find(); if(!$row || $row['kind']!=='purchase') throw new Problem('PURCHASE_TASK_MISSING','本站原采购任务不存在'); return $row; }
    private function task(int $site,int $id,bool $lock=false): array { $row=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->lock($lock)->find(); if(!$row || $row['kind']!==self::KIND || (Contract::decode($row['request_json'])['schema']??'')!==self::SCHEMA) throw new Problem('PAYMENT_TASK_MISSING','本站付款登记任务不存在'); return $row; }
    private function version(array $task,array $config,array $profile,array $input): void { if(!is_int($input['revision']??null) || $input['revision']!==(int)$task['revision']) throw new Problem('TASK_STALE','任务已更新，请刷新'); if(($input['mode']??null)!==$task['mode'] || ($input['connection_key']??null)!==$task['connection_key'] || $task['mode']!==$config['mode'] || $task['connection_key']!==$profile['connection_key']) throw new Problem('CONNECTION_CHANGED','任务不属于当前UAT连接，请刷新'); }
    private function cents($value): int { if(is_int($value) && $value>=0) $value=(string)$value.'.00'; return Contract::cents($value); }
    private function keys(array $input,array $allowed): void { if(array_diff(array_keys($input),$allowed)) throw new Problem('PAYMENT_READ_ONLY_FACT','公司、供应商、金额和付款分配来自已确认事实，不接受前端修改或补造'); }
    private function lockSite(int $site): void { if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在'); }
    private function unverifiedAp(bool $ruleConfirmed=false): array { return ['verified'=>false,'status'=>$ruleConfirmed?'rule_confirmed':'unverified','formation_expected'=>$ruleConfirmed,'balance_verified'=>false,
        'reason'=>$ruleConfirmed?'已核对非现结采购，按官方保存即形成供应商应付规则满足；尚无实单应付身份、实时余额和指定核销查询证据':'未取得博远应付实单及余额证据；不能用本站应付或未结金额0替代，不代表远端应付为零或不存在']; }
    private function owned(int $site,int $id,array $task,string $lease): array { return [['site_id','=',$site],['id','=',$id],['connection_key','=',$task['connection_key']],['mode','=',$task['mode']],['status','=','processing'],['lease_token','=',$lease]]; }
    private function log(int $site,int $id,\Throwable $error): void { try { \think\facade\Log::warning('hsx_boyuan payment command unavailable',['site_id'=>$site,'task_id'=>$id,'error_type'=>get_class($error)]); } catch(\Throwable $ignored) {} }
    private function dispatchLater(int $site,int $id): void { if(!function_exists('env') || !env('queue.state',false)) return; try { \addon\hsx_boyuan\app\job\PaymentProcess::dispatch('doJob',[$site,$id],1); } catch(\Throwable $error) { $this->log($site,$id,$error); } }
}
