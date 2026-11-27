<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway,Transport};
use addon\hsx_boyuan\app\support\{ArchiveView,Contract,Problem,ProductionPurchasePilot as Pilot,ReceiptFacts};
use think\facade\Db;

/** Purchase-only adapter. A captured fact is never an authorization to send it. */
final class PurchaseSyncService
{
    public const SCHEMA = 'boyuan.purchase.v154';
    private const INVALIDATED = '[source_invalidated]';

    private function context(int $site,bool $write=false): array
    {
        $service=new ConfigService();
        if($site<=0 || !$service->installed($site)) throw new Problem('ADDON_MISSING','请先安装本站ERP及博远插件');
        $config=$service->get($site,false); $profile=$config['profiles'][$config['mode']];
        if($config['mode']==='mock' || ($profile['protocol']??'')!=='openapi_v153' || empty($profile['connection_key'])) throw new Problem('PURCHASE_CONNECTION_REQUIRED','采购核对仅适用于已保存的博远开放平台真实连接');
        if($write && (empty($config['enabled']) || empty($config['purchase_enabled']) || !empty($config['paused']) || empty($profile['probe_at']))) throw new Problem('PURCHASE_PAUSED','采购投递未启用、已暂停或账套尚未核验；没有发送任何新单');
        return [$config,$profile];
    }

    public function capture(int $site,array $event): array
    {
        return $this->captureEvent($site,$event);
    }

    /** Explicitly prepare ONE closed-scope draft from an already persisted bridge event. No network or finance. */
    public function capturePilot(int $site,int $uid,int $bridgeTaskId,array $event): array
    {
        Pilot::admin($site,$uid);
        return Db::transaction(function() use($site,$uid,$bridgeTaskId,$event):array {
            $this->lockSite($site); [$c,$p]=$this->context($site);
            if(!Pilot::profile($site,$c,$p) || !Pilot::event($site,$event)) throw new Problem('PILOT_SCOPE','不是本次允许的单机正式试单');
            $s=$event['payload']; $key=$p['connection_key']; $this->assertCurrentSource($site,$s,true);
            $imei=$s['lines'][0]['imei'];$op=Pilot::operation($imei);
            Pilot::assertBinding($site,$p,$bridgeTaskId,$event['event_id'],$s);
            $existing=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production','kind'=>'purchase'])
                ->where(function($q)use($s,$op,$imei){$q->where('operation_id',$op)->whereOr('business_no',$s['source_document_no'])->whereOr('snapshot_json','like','%'.$imei.'%');})->lock(true)->select()->toArray();
            if($existing) {
                $t=$existing[0]; $prior=Contract::decode($t['request_json']);
                if(count($existing)!==1 || $t['operation_id']!==$op || $key!==$t['connection_key'] || $t['event_id']!==$event['event_id']
                    || ($prior['pilot_bridge_id']??null)!==$bridgeTaskId || Contract::decode($t['snapshot_json'])!==$s)
                    throw new Problem('PILOT_ALREADY_TRACKED','已有正式采购任务涉及本串号或原单，即使切换连接也不可重建');
                return ['consumer'=>'hsx_boyuan','status'=>'processed','task_id'=>(int)$t['id']];
            }
            $r=['schema'=>self::SCHEMA,'stage'=>'draft','selection'=>$this->defaults($site,$key,$s),'auto_verify'=>false,
                'original_event_id'=>$event['event_id'],'pilot_bridge_id'=>$bridgeTaskId];
            $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'connection_key'=>$key,'mode'=>'production','kind'=>'purchase',
                'fact_key'=>$s['source_document_id'].':'.$s['batch_ref'],'event_id'=>$event['event_id'],'operation_id'=>$op,'business_no'=>$s['source_document_no'],
                'status'=>'awaiting_confirmation','reason'=>'单台正式采购试单：等待本站管理员明确确认，尚未发送','snapshot_json'=>Contract::json($s),
                'request_json'=>Contract::json($r),'result_json'=>'{}','next_at'=>0,'create_at'=>time(),'update_at'=>time()]);
            TaskService::audit($site,$id,$key,'purchase_pilot','awaiting_confirmation','从桥关联单台正式挂账采购草稿，未投递、未付款',$uid,['bridge_task_id'=>$bridgeTaskId]);
            return ['consumer'=>'hsx_boyuan','status'=>'processed','task_id'=>$id];
        });
    }

    private function pilotTask(array $task): bool { return in_array($task['operation_id']??null,[Pilot::OP,Pilot::NEXT_OP,Pilot::THIRD_OP],true); }
    private function taskContext(int $site,array $task,bool $write): array
    {
        if(!$this->pilotTask($task)) return $this->context($site,$write);
        [$c,$p]=$this->context($site);
        if($task['mode']!=='production' || $task['connection_key']!==$p['connection_key'] || !Pilot::profile($site,$c,$p) ||
            Pilot::operation(Contract::decode($task['snapshot_json'])['lines'][0]['imei']??null)!==$task['operation_id'])
            throw new Problem('PILOT_TARGET_CHANGED','正式试单配置或连接已改变，不能投递本单');
        return [$c,$p];
    }

    /** The optional original can only come from the locked, explicit single-order preparation below. */
    private function captureEvent(int $site,array $event,?array $original=null): array
    {
        [$config,$profile]=$this->context($site);
        if(empty($config['enabled']) || empty($config['purchase_enabled'])) return [];
        if((int)($event['site_id']??0)!==$site || ($event['event_version']??null)!==1 || !is_string($event['event_id']??null) || $event['event_id']==='' || strlen($event['event_id'])>128) throw new Problem('EVENT_INVALID','ERP事件身份、站点或版本无效');
        $name=$event['event_name']??''; $snapshot=(array)($event['payload']??[]);
        if($name==='erp.integration.document.invalidated.v1') return $this->invalidate($site,$profile['connection_key'],$snapshot);
        if($name!=='erp.integration.document.confirmed.v1' || ($snapshot['kind']??'')!=='purchase') return [];
        if($original===null && (int)($event['occurred_at']??0)<$this->captureStart($config)) return [];
        $this->facts($snapshot);
        $created=false; $automatic=false;
        $id=Db::transaction(function() use($site,$event,$snapshot,$original,&$created,&$automatic): int {
            $this->lockSite($site); [$config,$profile]=$this->context($site);
            if(empty($config['enabled']) || empty($config['purchase_enabled']) || ($original===null && (int)$event['occurred_at']<$this->captureStart($config))) return 0;
            if($original!==null && (!$this->originalTarget($config,$profile) || $original['mode']!==$config['mode'] ||
                $original['connection_key']!==$profile['connection_key'] || $original['event_id']!==$event['event_id'] || $original['revision']!==(int)$config['revision']))
                throw new Problem('CONFIG_STALE','原采购或连接已变化，请重新核对');
            $fact=$snapshot['source_document_id'].':'.$snapshot['batch_ref'];
            if($site===Pilot::SITE && $config['mode']==='production') foreach(array_column($snapshot['lines'],'imei') as $imei) {
                $op=Pilot::operation($imei);
                if($op!==null && (Pilot::pricerOperation($op) || Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production','operation_id'=>$op])->count()))
                    throw new Problem('PILOT_ALREADY_TRACKED','正式单机试单只可走原限定连接，不能另建采购任务');
            }
            if(strlen($fact)>160) throw new Problem('FACT_INVALID','来源事实编号过长');
            $where=[['site_id','=',$site],['connection_key','=',$profile['connection_key']],['fact_key','=',$fact]];
            $old=Db::name('hsx_boyuan_task')->where($where)->lock(true)->find();
            if($old) {
                if($old['snapshot_json']!==Contract::json($snapshot)) throw new Problem('FACT_CONFLICT','来源事实内容冲突，未覆盖原任务');
                return (int)$old['id'];
            }
            $this->assertCurrentSource($site,$snapshot,true);
            // A source line belongs to one captured fact in this connection, even if batch ordering changed.
            $incoming=array_fill_keys(array_column($snapshot['lines'],'source_line_id'),true);
            foreach(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$profile['connection_key'])->where('kind','purchase')->column('snapshot_json') as $raw) {
                foreach(Contract::decode($raw)['lines']??[] as $line) if(isset($incoming[$line['source_line_id']??''])) throw new Problem('PURCHASE_ALREADY_CAPTURED','本采购设备已在其他同步任务内，请打开原任务，不重复建单');
            }
            $selection=$this->defaults($site,$profile['connection_key'],$snapshot);
            $request=['schema'=>self::SCHEMA,'stage'=>'draft','selection'=>$selection,'auto_verify'=>($config['purchase_mode']??'manual')==='automatic'];
            if($original!==null) $request['original_event_id']=$event['event_id'];
            $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'mode'=>$config['mode'],
                'fact_key'=>$fact,'event_id'=>$event['event_id'],'operation_id'=>'BY_'.hash('sha256',$site.':'.$profile['connection_key'].':'.$fact),'kind'=>'purchase',
                'business_no'=>$snapshot['source_document_no'],'status'=>'awaiting_confirmation','reason'=>'请核对外部资料后提交；尚未向博远创建采购单',
                'snapshot_json'=>Contract::json($snapshot),'request_json'=>Contract::json($request),'result_json'=>'{}',
                'next_at'=>0,'create_at'=>time(),'update_at'=>time()]);
            TaskService::audit($site,$id,$profile['connection_key'],'purchase_capture','awaiting_confirmation','采购新事实已接收，等待核对，未外发');
            $created=true; $automatic=($config['purchase_mode']??'manual')==='automatic' && empty($config['paused']);
            return $id;
        });
        if(!$id) return [];
        if($created && $automatic) {
            $view=$this->view($site,$id);
            if($view['editable'] && $view['can_submit']) {
                try { $this->submit($site,$id,0,['revision'=>$view['task']['revision'],'mode'=>$view['task']['mode'],'connection_key'=>$view['task']['connection_key'],'selection'=>$view['selection'],'remember'=>false]); }
                catch(Problem $e) { TaskService::audit($site,$id,$profile['connection_key'],'purchase_automatic','awaiting_confirmation','自动匹配不足或原事实变化，保留人工核对',0,['code'=>$e->reason]); }
            }
        }
        return ['consumer'=>'hsx_boyuan','status'=>'processed','task_id'=>$id];
    }

    /** Preview is read-only. A separate confirmation may prepare ONE existing device as a draft; no backdating or replay. */
    public function original(int $site,int $uid,int $id,?array $confirmation=null): array
    {
        return Db::transaction(function() use($site,$uid,$id,$confirmation):array {
            $this->lockSite($site); [$c,$p]=$this->context($site,true);
            if($uid<1 || !$this->originalTarget($c,$p))
                throw new Problem('ORIGINAL_PURCHASE_NOT_ELIGIBLE','请先启用并核验本站独立测试账套或已验收的正式账套，使用手动采购同步');
            $rows=Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.confirmed.v1')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(payload_json),payload_json,'{}'), '$.payload.source_document_id')) = ?",['purchase:'.$id])
                ->field('id,event_id,event_name,occurred_at,payload_json')->order('id')->limit(2)->select()->toArray();
            if(count($rows)!==1) throw new Problem('ORIGINAL_PURCHASE_AMBIGUOUS','仅支持有一份完整入库记录的单机采购；未凭现状重建原单');
            $row=$rows[0]; $event=Contract::decode($row['payload_json']);$s=$event['payload']??[];
            if((int)$row['occurred_at']>=$this->captureStart($c) || ($event['event_version']??null)!==1 || ($event['event_id']??null)!==$row['event_id']
                || ($event['event_name']??null)!==$row['event_name'] || ($event['site_id']??null)!==$site || ($event['occurred_at']??null)!==(int)$row['occurred_at']
                || ($event['aggregate_type']??null)!=='purchase' || (int)($event['aggregate_id']??0)!==$id || ($s['source_document_id']??null)!=='purchase:'.$id
                || ($s['kind']??null)!=='purchase' || !is_array($s['lines']??null) || count($s['lines'])!==1)
                throw new Problem('ORIGINAL_PURCHASE_AMBIGUOUS','原入库记录与当前采购不匹配或不是启用前的单机采购，没有创建任务');
            $this->assertCurrentSource($site,$s,true);
            $line=$s['lines'][0]; $asset=(int)substr($line['device_ref'],6);
            if(Db::name('erp_purchase_item')->where(['site_id'=>$site,'purchase_order_id'=>$id])->count()!=1 ||
                !Db::name('erp_asset')->where(['site_id'=>$site,'id'=>$asset,'status'=>'in_stock','purchase_order_id'=>$id,'imei'=>$line['imei']])->lock(true)->find())
                throw new Problem('ORIGINAL_PURCHASE_CHANGED','仅支持原串号仍在库的单机采购；本单未投递');
            $fact=$s['source_document_id'].':'.$s['batch_ref'];
            $prior=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'kind'=>'purchase','mode'=>$c['mode']])->where(function($q)use($fact,$s,$row){
                $q->where('fact_key',$fact)->whereOr('event_id',$row['event_id'])->whereOr('business_no',$s['source_document_no']);
            })->lock(true)->select()->toArray();
            if($prior) {
                if(count($prior)===1 && $prior[0]['connection_key']===$p['connection_key'] && $prior[0]['fact_key']===$fact &&
                    $prior[0]['event_id']===$row['event_id'] && $prior[0]['snapshot_json']===Contract::json($s)) {
                    $task=(int)$prior[0]['id']; return ['task_id'=>$task,'task_ids'=>[$task]]; // no overwrite, no resubmit
                }
                throw new Problem('ORIGINAL_PURCHASE_ALREADY_TRACKED','本环境的其他连接或批次已有这张原单。请核对原任务，不再准备。');
            }
            $scope=['mode'=>$c['mode'],'connection_key'=>$p['connection_key'],'revision'=>(int)$c['revision'],'event_id'=>$row['event_id'],
                'purchase_no'=>$s['source_document_no'],'imei'=>$line['imei'],'amount'=>$line['amount'],'account_id'=>$p['account_id']];
            if($confirmation===null) return ['original_review'=>$scope];
            foreach($scope as $key=>$value) if(($confirmation[$key]??null)!==$value) throw new Problem('ORIGINAL_PURCHASE_STALE','原采购、串号、金额或目标账套已变化，请刷新原单');
            $result=$this->captureEvent($site,$event,$scope); $task=(int)($result['task_id']??0);
            if(!$task) throw new Problem('ORIGINAL_PURCHASE_STALE','未能准备原单，请刷新');
            TaskService::audit($site,$task,$p['connection_key'],'original_purchase_prepared','awaiting_confirmation','操作员仅对指定旧采购准备核对草稿，未发送或登记付款',$uid,['event_id'=>$row['event_id'],'purchase_id'=>$id]);
            return ['task_id'=>$task,'task_ids'=>[$task]];
        });
    }

    private function originalTarget(array $c,array $p): bool
    {
        if(empty($c['enabled']) || empty($c['purchase_enabled']) || !empty($c['paused']) || ($c['purchase_mode']??'')!=='manual' ||
            empty($p['probe_at']) || empty($p['contract_confirmed']) || ($p['protocol']??'')!=='openapi_v153' || ($p['gateway_path']??'')!=='/gateway.do') return false;
        if($c['mode']==='production') return $p['endpoint']==='https://open-api.139erp.com/api' && trim((string)($p['acceptance_ref']??''))!=='';
        $prod=$c['profiles']['production']??[];
        return $c['mode']==='test' && $p['endpoint']===PaymentSyncService::UAT_ENDPOINT && !empty($p['app_id']) && !empty($p['account_id'])
            && $p['app_id']!==($prod['app_id']??'') && empty($c['finance_enabled']) && empty($c['uat_payment_enabled']);
    }

    private function invalidate(int $site,string $connection,array $snapshot): array
    {
        $ref=(string)($snapshot['original_document_ref']??'');
        if(!preg_match('/^purchase:[1-9][0-9]*$/D',$ref)) return [];
        return Db::transaction(function() use($site,$connection,$ref): array {
            $this->lockSite($site); $ids=[];
            foreach(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$connection)->where('kind','purchase')->lock(true)->select()->toArray() as $task) {
                if((Contract::decode($task['snapshot_json'])['source_document_id']??'')!==$ref) continue;
                $request=Contract::decode($task['request_json']); if(($request['schema']??'')!==self::SCHEMA) continue;
                $values=['reason'=>'原采购已撤销或调整，请人工核对博远原单；不会自动重建或冲销','resolution'=>self::INVALIDATED,'revision'=>(int)$task['revision']+1,'update_at'=>time()];
                // Keep an in-flight lease so that its eventual bill number can still be persisted.
                if($task['status']!=='processing') $values+=['status'=>'attention','next_at'=>0];
                Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$task['id'])->update($values); $ids[]=(int)$task['id'];
                TaskService::audit($site,(int)$task['id'],$connection,'purchase_invalidate','attention',$values['reason']);
            }
            return $ids?['consumer'=>'hsx_boyuan','status'=>'processed','task_ids'=>$ids]:[];
        });
    }

    /** Explicit single-order entry, not a historical scan/import. Reuses domain-event facts. */
    public function capturePurchaseId(int $site,int $uid,int $purchaseId): array
    {
        return Db::transaction(function()use($site,$uid,$purchaseId):array{
        $this->lockSite($site); // Serialize explicit source entry with configuration changes.
        [$config,$profile]=$this->context($site);
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',$purchaseId)->find();
        if(!$head) throw new Problem('PURCHASE_UNCONFIRMED','未找到本站原采购单');
        $found=[];
        foreach(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$profile['connection_key'])->where('mode',$config['mode'])->where('kind','purchase')
            ->where('business_no',$head['purchase_no'])->where('fact_key','like','purchase:'.$purchaseId.':%')->field('id,snapshot_json,request_json')->order('id')->select()->toArray() as $task) {
            $src=Contract::decode($task['snapshot_json']);
            if(($src['source_document_id']??'')==='purchase:'.$purchaseId && ($src['source_document_no']??'')===$head['purchase_no'] && (Contract::decode($task['request_json'])['schema']??'')===self::SCHEMA) $found[]=(int)$task['id'];
        }
        // An append to an older order is eligible only through its own new confirmed event.
        $active=!empty($config['enabled']) && !empty($config['purchase_enabled']) && empty($config['paused']);
        if(!$found && !$active) throw new Problem('PURCHASE_DISABLED','当前账套没有这笔采购的同步记录，且本站已暂停投递；没有创建任务或发送采购');
        if(!$found && ($head['status']??'')!=='completed') throw new Problem('PURCHASE_UNCONFIRMED','仅可获取本站已确认且未撤销的采购单');
        $events=$active && $head['status']==='completed' ? Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.confirmed.v1')->where('occurred_at','>=',$this->captureStart($config))
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.payload.source_document_id')) = ?",['purchase:'.$purchaseId])->order('id')->select()->toArray():[];
        foreach($events as $row) {
            $event=Contract::decode((string)$row['payload_json']);
            if(($event['payload']['source_document_id']??'')!=='purchase:'.$purchaseId) continue;
            $accepted=$this->capture($site,$event); if(!empty($accepted['task_id'])) $found[]=(int)$accepted['task_id'];
        }
        if(!$found) throw new Problem('PURCHASE_FACT_MISSING','未找到启用后的采购确认事实，请检查ERP事件投递；不会凭当前库存重新构造历史事实');
        $ids=array_values(array_unique($found)); sort($ids,SORT_NUMERIC); $target=$ids[count($ids)-1];
        $states=Db::name('hsx_boyuan_task')->where('site_id',$site)->whereIn('id',$ids)->field('id,status')->order('id desc')->select()->toArray();
        foreach($states as $row) if($row['status']!=='succeeded'){ $target=(int)$row['id'];break; }
        $view=$this->view($site,$target);
        TaskService::audit($site,$target,$profile['connection_key'],'purchase_open',$view['task']['status'],'操作员打开已确认采购的核对任务，未发送',$uid);
        return ['task_id'=>$target,'task_ids'=>$ids,'view'=>$view];
        });
    }

    public function view(int $site,int $taskId): array
    {
        $cfg=new ConfigService(); if($site<=0 || !$cfg->installed($site)) throw new Problem('ADDON_MISSING','请先安装本站ERP及博远插件');
        $task=$this->task($site,$taskId); $config=$cfg->get($site,false); $profile=$config['profiles'][$config['mode']]??[];
        $snapshot=Contract::decode($task['snapshot_json']); $request=Contract::decode($task['request_json']);
        $active=$config['mode']!=='mock' && ($profile['protocol']??'')==='openapi_v153' && $task['mode']===$config['mode'] && $task['connection_key']!=='' && $task['connection_key']===($profile['connection_key']??'');
        $editable=$active && $task['status']==='awaiting_confirmation' && $task['side_effect']==='none' && ($request['stage']??'')==='draft' && !str_starts_with((string)$task['resolution'],self::INVALIDATED);
        $pricing=$this->pricingBuyer($site,$task['connection_key'],$snapshot,$editable);
        // A newly captured draft is a suggestion, not a signed-off decision. A read may
        // refresh suggestions, never the stored draft, revision or a frozen request.
        $selection=$editable ? $this->defaults($site,$task['connection_key'],$snapshot,(int)$task['revision']>1?(array)($request['selection']??[]):[],$pricing)
            : (array)($request['selection']??[]); $issues=[];
        $ready=false;
        try { $this->assertCurrentSource($site,$snapshot,false); if($editable) { $this->prepare($site,$task,$selection,true,0,false,$pricing); $ready=true; } }
        catch(Problem $e) { if(!$editable || $e->reason!=='PURCHASE_SELECTION_REQUIRED') $issues[]=$e->getMessage(); }
        $pilot=$this->pilotTask($task);
        $enabled=$pilot ? Pilot::profile($site,$config,$profile) : (!empty($config['enabled']) && !empty($config['purchase_enabled']) && empty($config['paused']) && !empty($profile['probe_at']));
        if(!$active) $issues[]='任务属于原连接，不能在当前连接提交';
        if(!$enabled) $issues[]='采购投递未启用、已暂停或账套尚未核验';
        $public=array_intersect_key($task,array_flip(['id','revision','mode','connection_key','kind','business_no','status','reason','external_no','side_effect','attempts','create_at','update_at']));
        foreach(['id','revision','attempts','create_at','update_at'] as $key) $public[$key]=(int)$public[$key];
        $inventory=(new PurchaseInventoryService())->summary($site,$task); $canCheckInventory=$inventory['can_check']; unset($inventory['can_check']);
        $payment=(new PaymentSyncService())->summary($site,$task);
        if($editable && $pricing['mapped_id']!=='' && $selection['company_id']!=='' && $selection['buyer_id']!==$pricing['mapped_id'])
            $pricing=array_replace($pricing,['status'=>'conflict','message'=>'实际定价人对应的博远采购业务员不属于所选公司。请核对公司、仓库和员工对应后再同步。']);
        $savedResult=Contract::decode($task['result_json']);
        if(($unsent=Pilot::unsentNotice($task,$savedResult))!=='') { $public['status']='blocked'; $public['reason']=$unsent; }
        if($pricing['required']) $pricing=array_replace($pricing,ReceiptFacts::purchaseBuyerReceipt($request['biz']['head']??[],$savedResult));
        return ['task'=>$public,'production_pilot'=>$pilot,'source'=>$this->publicSource($snapshot),'pricing_buyer'=>$pricing,'selection'=>$selection,'labels'=>$this->labels($site,$task['connection_key'],$selection),
            'active_connection'=>$active,'write_enabled'=>$active && $enabled,'editable'=>$editable,'can_submit'=>$editable && $enabled && $ready,
            'can_process_current'=>ManualDocumentCommandService::canProcess($config,$task),
            'can_verify'=>$active && $enabled && ($request['stage']??'')==='frozen' && !in_array($task['status'],['processing','succeeded'],true) &&
                !(Pilot::pricerOperation($task['operation_id']??'') && ($task['side_effect']??'')==='none'),
            'inventory'=>$inventory,'can_check_inventory'=>$canCheckInventory,'payment'=>$payment,
            'issues'=>$issues,'result'=>$this->safeResult($savedResult),'closure'=>(new PurchaseClosureService())->view($site,$task,$inventory,$payment)];
    }

    public function saveDraft(int $site,int $taskId,int $uid,array $input): array
    {
        return $this->edit($site,$taskId,$uid,$input,false);
    }
    public function submit(int $site,int $taskId,int $uid,array $input,bool $interactive=false,?Transport $transport=null): array
    {
        $view=$this->edit($site,$taskId,$uid,$input,true,$interactive);
        if(empty($view['production_pilot'])) return $view;
        $this->process($site,$taskId,$transport);
        return $this->view($site,$taskId);
    }
    private function edit(int $site,int $taskId,int $uid,array $input,bool $submit,bool $interactive=false): array
    {
        $this->keys($input,['revision','mode','connection_key','selection','remember','confirm_pilot','confirm_pricing_buyer']);
        if(isset($input['confirm_pricing_buyer']) && !is_bool($input['confirm_pricing_buyer'])) throw new Problem('INPUT_INVALID','定价人对应须由经办人明确确认');
        if(isset($input['remember']) && !is_bool($input['remember'])) throw new Problem('INPUT_INVALID','记住对应须为明确布尔选项');
        Db::transaction(function() use($site,$taskId,$uid,$input,$submit,$interactive): void {
            $this->lockSite($site); $task=$this->task($site,$taskId,true); [$config,$profile]=$this->taskContext($site,$task,$submit); $this->version($task,$config,$profile,$input);
            $old=Contract::decode($task['request_json']);
            if($task['status']!=='awaiting_confirmation' || $task['side_effect']!=='none' || ($old['stage']??'')!=='draft' || (int)$task['attempts']!==0 || str_starts_with((string)$task['resolution'],self::INVALIDATED)) throw new Problem('PURCHASE_FROZEN','任务已提交、已发送或原采购已失效，不能修改或重复创建');
            $snapshot=Contract::decode($task['snapshot_json']); $selection=$this->selection($snapshot,$input['selection']??null);
            $request=$this->prepare($site,$task,$selection,$submit,($input['confirm_pricing_buyer']??false)===true?$uid:0,!empty($input['remember']));
            $pilot=$this->pilotTask($task);
            if($pilot) {
                Pilot::admin($site,$uid);
                Pilot::assertBinding($site,$profile,(int)($old['pilot_bridge_id']??0),$task['event_id'],$snapshot,$taskId);
                if($submit) {
                    if(($input['confirm_pilot']??null)!==true) throw new Problem('PILOT_CONFIRM_REQUIRED','请明确确认仅向正式账套 89096 创建指定单台1元挂账采购；不登记付款');
                    $request['pilot_authorized_uid']=$uid;
                }
            } elseif(!empty($input['confirm_pilot'])) throw new Problem('PILOT_SCOPE','此任务不是指定试单');
            if($submit) $this->assertCurrentSource($site,$snapshot,true);
            if(!empty($input['remember'])) $this->remember($site,$task,$selection,$snapshot,$uid);
            $values=['request_json'=>Contract::json($request),'revision'=>(int)$task['revision']+1,'update_at'=>time()];
            if($submit) $values+=['status'=>'pending','reason'=>'已核对并冻结，等待创建采购；不自动登记付款','query_only'=>0,'next_at'=>time()+($pilot || ($interactive && ManualDocumentCommandService::eligible($config) && empty($request['auto_verify']))?0:1)];
            Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$taskId)->update($values);
            TaskService::audit($site,$taskId,$task['connection_key'],$submit?'purchase_submit':'purchase_draft',$submit?'pending':'awaiting_confirmation',$submit?'采购请求已冻结，等待异步发送；未登记付款':'仅保存外部资料选择，未外发',$uid,['remember'=>!empty($input['remember'])]);
        });
        return $this->view($site,$taskId);
    }

    public function queueVerify(int $site,int $taskId,int $uid,array $input,bool $interactive=false): array
    {
        $this->keys($input,['revision','mode','connection_key','external_no']);
        Db::transaction(function() use($site,$taskId,$uid,$input,$interactive): void {
            $this->lockSite($site); $task=$this->task($site,$taskId,true); [$config,$profile]=$this->taskContext($site,$task,true); $this->version($task,$config,$profile,$input);
            $request=Contract::decode($task['request_json']);
            if(($request['stage']??'')!=='frozen' || in_array($task['status'],['processing','succeeded'],true)) throw new Problem('VERIFY_STATE','请先提交并冻结采购核对；处理中或已完成任务不能重复操作');
            $pilot=$this->pilotTask($task);
            if($pilot) {
                Pilot::admin($site,$uid);
                if(!is_int($request['pilot_authorized_uid']??null) || $request['pilot_authorized_uid']<=0 || (int)$task['attempts']<=0 ||
                    (Pilot::pricerOperation($task['operation_id']??'') && ($task['side_effect']??'')==='none'))
                    throw new Problem('PILOT_UNSENT','此单尚未投递，不允许手填编号核验');
            }
            $number=$input['external_no']??$task['external_no'];
            if(!is_string($number) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',$number)) throw new Problem('EXTERNAL_NO_REQUIRED','请提供有效的博远采购单号，只排队只读核验');
            if($task['external_no']!=='' && $number!==$task['external_no']) throw new Problem('EXTERNAL_NO_CONFLICT','已取得的博远原单号不能被另一个手工单号覆盖');
            Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$taskId)->update(['external_no'=>$number,'status'=>'confirming','query_only'=>1,'side_effect'=>'unknown',
                'reason'=>'已排队只读核验原采购及串号事实；不会重新创建或登记付款','revision'=>(int)$task['revision']+1,'next_at'=>time()+($pilot || ($interactive && ManualDocumentCommandService::eligible($config) && empty($request['auto_verify']))?0:1),'update_at'=>time()]);
            TaskService::audit($site,$taskId,$task['connection_key'],'purchase_verify','confirming','只读核验已排队；人工单号不代表成功',$uid,['external_no'=>$number]);
        });
        if($this->pilotTask($this->task($site,$taskId))) $this->process($site,$taskId);
        return $this->view($site,$taskId);
    }

    public function process(int $site,int $id,?Transport $transport=null,?array $expected=null): bool
    {
        try { [$config,$profile]=$this->context($site); }
        catch(Problem $error) { if(in_array($error->reason,['ADDON_MISSING','PURCHASE_CONNECTION_REQUIRED'],true)) return false; throw $error; }
        if(empty($config['enabled']) || empty($config['purchase_enabled']) || !empty($config['paused']) || empty($profile['probe_at'])) {
            if(!Pilot::profile($site,$config,$profile) || !Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production',
                'connection_key'=>$profile['connection_key'],'id'=>$id,'kind'=>'purchase'])->whereIn('operation_id',[Pilot::OP,Pilot::NEXT_OP,Pilot::THIRD_OP])->count()) return false;
        }
        $lease=bin2hex(random_bytes(16)); $claimed=false; $task=null; $request=[]; $checkpoint='';
        try {
            Db::transaction(function() use($site,$id,$lease,&$claimed,&$task,&$request,&$config,&$profile,$expected): void {
                $this->lockSite($site); $task=$this->task($site,$id,true); [$config,$profile]=$this->taskContext($site,$task,true);
                if($expected!==null && !ManualDocumentCommandService::claimable($config,$task,$expected)) return;
                if($task['mode']!==$config['mode'] || $task['connection_key']!==$profile['connection_key'] || (int)$task['next_at']>time()) return;
                $stale=$task['status']==='processing' && (int)$task['lease_until']<time();
                if(!in_array($task['status'],['pending','confirming'],true) && !$stale) return;
                $request=Contract::decode($task['request_json']);
                if(($request['stage']??'')!=='frozen') return; // Manual drafts never reach a gateway.
                if(!$task['query_only'] && ($task['side_effect']!=='none' || (int)$task['attempts']>0)) {
                    Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'attention','reason'=>'原创建可能已发出，已停止自动重发；请查到原单号后只读核验','side_effect'=>'unknown','next_at'=>0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
                    return;
                }
                if($task['query_only'] && $task['external_no']==='') throw new Problem('EXTERNAL_NO_REQUIRED','没有可核验的博远原单号；不会重新创建');
                if($this->pilotTask($task)) {
                    if(!is_int($request['pilot_authorized_uid']??null) || $request['pilot_authorized_uid']<1)
                        throw new Problem('PILOT_CONFIRM_REQUIRED','正式试单没有管理员的明确授权');
                    if(!$task['query_only']) {
                        if(!Pilot::activeAdmin($site,$request['pilot_authorized_uid'])) throw new Problem('PILOT_ADMIN_REQUIRED','试单授权人不再是本站有效管理员');
                        Pilot::assertBinding($site,$profile,(int)($request['pilot_bridge_id']??0),$task['event_id'],Contract::decode($task['snapshot_json']),$id);
                        $imei=Contract::decode($task['snapshot_json'])['lines'][0]['imei'];
                        if(Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'production','kind'=>'purchase'])->where('id','<>',$id)
                            ->where(function($q)use($task,$imei){$q->where('operation_id',$task['operation_id'])->whereOr('business_no',$task['business_no'])->whereOr('snapshot_json','like','%'.$imei.'%');})->count())
                            throw new Problem('PILOT_ALREADY_TRACKED','另一正式任务涉及本试单，停止投递，不允许重复建单');
                    }
                }
                if(!$task['query_only']) {
                    $this->assertCurrentSource($site,Contract::decode($task['snapshot_json']),true);
                    // Revalidate the exact frozen choices; never silently refresh from mappings.
                    $frozen=$this->prepare($site,$task,$request['selection']??[],true);
                    if(Contract::json($frozen)!==Contract::json($request)) throw new Problem('REQUEST_CONFLICT','冻结请求与原事实不一致，已停止发送');
                }
                Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'processing','lease_token'=>$lease,'lease_until'=>time()+90,
                    'attempts'=>(int)$task['attempts']+1,'side_effect'=>'unknown','update_at'=>time()]);
                $claimed=true;
            });
            if(!$claimed) return false;
            $owned=[['site_id','=',$site],['id','=',$id],['mode','=',$config['mode']],['connection_key','=',$profile['connection_key']],['status','=','processing'],['lease_token','=',$lease]];
            // The durable unknown side effect above precedes transport creation and the network call.
            [$latest,$latestProfile]=$this->taskContext($site,$task,true);
            if($latest['mode']!==$config['mode'] || $latestProfile!==$profile || ($expected!==null && !ManualDocumentCommandService::sameConfig($latest,$expected))) throw new Problem('CONNECTION_CHANGED','任务领取后连接配置发生变化，已停止外发，请刷新核对','unknown');
            $gatewayTransport=$transport??(new ConfigService())->transport($site,$profile,$config['mode'],\addon\hsx_boyuan\app\support\InterfaceTrace::task($task));
            if(!$gatewayTransport instanceof OpenApi) throw new Problem('PURCHASE_PROTOCOL_INVALID','采购创建仅允许官方开放平台连接','unknown');
            $gateway=new PurchaseGateway($gatewayTransport); $prior=Contract::decode($task['result_json']);
            if(!$task['query_only'] && isset($request['original_event_id'])) {
                if($site===Pilot::SITE && $task['mode']==='production' && Pilot::pricerOperation($task['operation_id']??''))
                    $checkpoint='duplicate_check';
                $gateway->checkExistingSerial($request);
                if($checkpoint!=='') $checkpoint='post_check';
                [$current,$target]=$this->taskContext($site,$task,true);
                if($current['revision']!==$config['revision'] || $target!==$profile || $current['purchase_mode']!=='manual' ||
                    !Db::name('hsx_boyuan_task')->where($owned)->where('lease_until','>',time()+10)->count())
                    throw new Problem('ORIGINAL_PURCHASE_STALE','排重期间连接、处理权或处理方式变化，本次未创建采购单');
                if($this->pilotTask($task)) Pilot::assertBinding($site,$target,$request['pilot_bridge_id'],$task['event_id'],Contract::decode($task['snapshot_json']),$id);
            }
            if($task['query_only']) {
                $verify=$request+(!empty($prior['external_id'])?['external_id'=>$prior['external_id']]:[]);
                $reply=$this->pilotTask($task)
                    ? $gateway->verify($verify,$task['external_no'],\addon\hsx_boyuan\app\support\PurchasePilotReadback::saved($site,$task,$request,$prior))
                    : $gateway->verify($verify,$task['external_no']);
            } else {
                $checkpoint=''; // From here a timeout or malformed CREATE reply is an unknown write, never a safe retry.
                $reply=$gateway->create($request);
            }
            $result=$this->safeResult($reply);
            if(($task['query_only'] && ($reply['verified']??false)!==true) || (!$task['query_only'] && ($reply['created']??false)!==true)) throw new Problem('PURCHASE_RESULT_INVALID','博远采购回执未确认预期阶段，请人工核对','unknown');
            $number=(string)($reply['external_no']??''); $externalId=(string)($reply['external_id']??''); Contract::id($number); Contract::id($externalId);
            if($task['query_only'] && ($number!==$task['external_no'] || ($reply['evidence']['company_id']??'')!==($request['selection']['company_id']??''))) throw new Problem('PURCHASE_RESULT_IDENTITY','核验单号或公司与冻结选择不一致，不能完成','unknown');
            // A detail lookup is a new observation. Keep the original create-head snapshot for this exact bill.
            if($task['query_only'] && ($prior['provider_result']['bill_id']??null)===$number && ($prior['provider_result']['id']??null)===$externalId)
                $result['provider_result']=\addon\hsx_boyuan\app\support\ReceiptFacts::head($prior['provider_result']);
            if($task['query_only'] && ($prior['external_no']??'')===$number && ($prior['external_id']??'')===$externalId &&
                ($result['evidence']['pricing_buyer']['status']??'not_returned')==='not_returned') {
                // Silence in a later search cannot erase a prior explicit contradiction for the same bill.
                foreach(['pricing_buyer','previous_buyer_conflict'] as $key) {
                    $priorBuyer=ReceiptFacts::buyerEvidence((array)($prior['evidence'][$key]??[]));
                    if(($priorBuyer['status']??'')==='mismatch') { $result['evidence']['previous_buyer_conflict']=$priorBuyer; break; }
                }
            }
            Db::transaction(function() use($site,$id,$owned,$task,$request,$result,$number,$externalId): void {
                $this->lockSite($site); $row=Db::name('hsx_boyuan_task')->where($owned)->lock(true)->find(); if(!$row) return;
                $invalidated=str_starts_with((string)$row['resolution'],self::INVALIDATED); $sourceIssue='';
                try { $this->assertCurrentSource($site,Contract::decode($row['snapshot_json']),true); }
                catch(Problem $error) { $invalidated=true; $sourceIssue=$error->getMessage(); }
                $autoVerify=!$task['query_only'] && !$invalidated && ($request['auto_verify']??false)===true;
                $status=$invalidated?'attention':($task['query_only']?'succeeded':($autoVerify?'confirming':'created_review'));
                $reason=$invalidated?'原采购已撤销或事实变化，博远核验回执已保留；请人工核对原单，不会自动冲销或重建'.($sourceIssue!==''?'；'.$sourceIssue:''):($task['query_only']?'采购单及入库串号记录匹配；未核验实时库存余额、未登记或核验付款':($autoVerify?'已取得原采购单号，自动排队只读核验采购及入库串号事实；不会重新创建':'博远已返回采购单号，尚待核验采购及入库串号事实'));
                $head=$request['biz']['head']??[];
                $buyerConflict=$task['query_only'] && !empty($head['sales_name']) && ReceiptFacts::purchaseBuyerReceipt($head,$result)['receipt_status']==='mismatch';
                if($buyerConflict && !$invalidated) {
                    $result['verified']=false; $status='needs_review'; $reason='采购单及入库串号匹配，但博远返回的采购业务员与实际定价人不一致。请核对博远原单，不要重新建单。';
                }
                if($task['query_only'] && !$invalidated && !$buyerConflict) $this->bindings($row,$request,$result,$number,$externalId);
                Db::name('hsx_boyuan_task')->where($owned)->update(['status'=>$status,'side_effect'=>$task['query_only'] && !$invalidated && !$buyerConflict?'applied':'unknown','external_no'=>$number,'result_json'=>Contract::json($result),
                    'reason'=>$reason,'revision'=>(int)$row['revision']+1,'query_only'=>$task['query_only'] || $autoVerify?1:0,'next_at'=>$autoVerify?time()+1:0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
                TaskService::audit($site,$id,$row['connection_key'],$task['query_only']?'purchase_verified':'purchase_created',$status,$reason,0,['external_no'=>$number,'external_id'=>$externalId]);
            });
        } catch(\Throwable $error) {
            if(!$task) throw $error;
            $reason=$error instanceof Problem?$error->getMessage():'采购处理异常，请按任务编号核查服务日志；不会自动重发';
            $status=$claimed?($task['query_only']?'needs_review':'attention'):'attention';
            $where=[['site_id','=',$site],['id','=',$id],['mode','=',$task['mode']],['connection_key','=',$task['connection_key']]];
            if($claimed) $where=array_merge($where,[['lease_token','=',$lease],['status','=','processing']]);
            else $where=array_merge($where,[['revision','=',(int)$task['revision']],['status','=',$task['status']],['lease_token','=',$task['lease_token']],['lease_until','=',(int)$task['lease_until']],['attempts','=',(int)$task['attempts']]]);
            if($claimed && !$task['query_only'] && $site===Pilot::SITE && $task['mode']==='production' && Pilot::pricerOperation($task['operation_id']??'') && Pilot::buyerPreflightFailure($error)) $checkpoint='buyer_check';
            $preflightOnly=$checkpoint!=='';
            $saved=Contract::decode($task['result_json']); $saved['last_issue']=\addon\hsx_boyuan\app\support\ReceiptFacts::issue($error,$task['query_only']?'verify':($claimed && !$preflightOnly?'submit':'prepare'));
            if($preflightOnly) { $saved['last_issue']=Pilot::prepareIssue($error,$checkpoint); $reason=$saved['last_issue']['notice']; }
            Db::name('hsx_boyuan_task')->where($where)->update(['status'=>$status,'reason'=>mb_substr($reason,0,1000),'result_json'=>Contract::json($saved),'next_at'=>0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]+($preflightOnly?['side_effect'=>'none']:[]));
            TaskService::audit($site,$id,(string)$task['connection_key'],'purchase_process',$status,$reason,0,['code'=>$saved['last_issue']['code']]);
            return true;
        }
        return true;
    }

    private function prepare(int $site,array $task,array $selection,bool $complete,int $confirmedBy=0,bool $remember=false,?array $pricing=null): array
    {
        $snapshot=Contract::decode($task['snapshot_json']); $this->facts($snapshot); $selection=$this->selection($snapshot,$selection); $key=$task['connection_key'];
        // Captured execution intent is immutable. Switching today's config must not change it.
        $stored=Contract::decode($task['request_json']); $autoVerify=$stored['auto_verify']??false;
        if(!is_bool($autoVerify)) throw new Problem('PURCHASE_INTENT_INVALID','任务自动核验意图无效，已停止提交或外发');
        $intent=array_key_exists('auto_verify',$stored)?['auto_verify'=>$autoVerify]:[];
        if(array_key_exists('original_event_id',$stored)) {
            if($autoVerify || !in_array($task['mode'],['production','test'],true) || ($stored['original_event_id']??'')!==$task['event_id'])
                throw new Problem('PURCHASE_INTENT_INVALID','单笔原采购的来源或处理方式不匹配，未提交');
            $intent['original_event_id']=$task['event_id'];
        }
        if($this->pilotTask($task)) {
            if(!isset($intent['original_event_id']) || !is_int($stored['pilot_bridge_id']??null) || $stored['pilot_bridge_id']<1)
                throw new Problem('PILOT_SCOPE','单笔试单缺少固定的桥来源');
            $intent['pilot_bridge_id']=$stored['pilot_bridge_id'];
            if(Pilot::pricerOperation($task['operation_id'])) {
                $intent['pilot_operation_id']=$task['operation_id'];
                if($task['operation_id']===Pilot::THIRD_OP) $intent['pilot_recycle_no']=Pilot::THIRD_RECYCLE_NO;
                $intent['pilot_pricer_ref']=$snapshot['lines'][0]['pricing_operator']['ref']??'';
            }
        }
        $company=$this->archive($site,$key,'companies',$selection['company_id'],$complete);
        $supplier=$this->archive($site,$key,'parties',$selection['supplier_id'],$complete,'supplier');
        $creator=$this->archive($site,$key,'staff',$selection['creator_id'],$complete);
        if($creator && (!is_string($creator['name']) || $creator['name']==='' || strlen($creator['name'])>64 || preg_match('/[\x00-\x1f\x7f]/',$creator['name']))) throw new Problem('CREATOR_NAME_INVALID','所选博远制单人名称缺失或超过官方64字节限制，请核对档案');
        $pricing=$pricing??$this->pricingBuyer($site,$key,$snapshot);
        $buyer=$this->archive($site,$key,'staff',$selection['buyer_id'],false,'buyer'); $lines=[]; $details=[]; $warehouses=[];
        if($pricing['required']) {
            if($pricing['status']!=='known' && ($complete || $buyer)) throw new Problem('PURCHASE_PRICER_UNAVAILABLE',$pricing['message']);
            if($complete && !$buyer) throw new Problem('PURCHASE_SELECTION_REQUIRED','请为实际定价人「'.$pricing['name'].'」选择博远采购业务员，不能留空');
            if($buyer) {
                $ref=$pricing['local_ref']; $remote=$buyer['external_id'];
                if(!is_string($buyer['name']) || trim($buyer['name'])==='' || strlen($buyer['name'])>32 || preg_match('/[[:cntrl:]]/',$buyer['name']))
                    throw new Problem('PURCHASE_BUYER_NAME_INVALID','所选博远采购业务员姓名为空或超过32字节，未提交采购');
                if($pricing['mapped_id']!=='' && $pricing['mapped_id']!==$remote)
                    throw new Problem('PURCHASE_BUYER_CONFLICT','所选业务员与实际定价人已确认的博远人员对应不一致');
                $choice=BridgeMasterSelections::merge($site,$key,'purchase',[$ref],['staff:'.$ref=>$remote],$snapshot);
                if(($choice['staff:'.$ref]??'')!==$remote || Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>'staff','external_id'=>$remote])->where('local_ref','<>',$ref)->count())
                    throw new Problem('PURCHASE_BUYER_CONFLICT','所选博远员工已对应其他人员或映射不一致，请先核对人员对应');
                $previous=($stored['stage']??'')==='frozen'?($stored['pricing_buyer_confirmation']??[]):[];
                $valid=($previous['local_ref']??null)===$ref && ($previous['buyer_id']??null)===$remote && is_int($previous['uid']??null) && $previous['uid']>0;
                if($pricing['mapped_id']==='' && ($complete || $remember) && !$valid && $confirmedBy<=0)
                    throw new Problem('PRICING_BUYER_CONFIRM_REQUIRED','请确认本站定价人「'.$pricing['name'].'」对应博远采购业务员「'.$buyer['name'].'」，未发送采购');
                if($complete && ($valid || ($pricing['mapped_id']==='' && $confirmedBy>0)))
                    $intent['pricing_buyer_confirmation']=$valid?$previous:['local_ref'=>$ref,'buyer_id'=>$remote,'uid'=>$confirmedBy];
            }
        }
        // Keep the exact frozen ordering: initial submission appends pilot approval after pricing identity.
        if($this->pilotTask($task) && isset($stored['pilot_authorized_uid'])) $intent['pilot_authorized_uid']=$stored['pilot_authorized_uid'];
        foreach([$creator,$buyer] as $staff) if($company && $staff && !$this->staffBelongs($staff['extra'],$company['external_id'],$company['extra']))
            throw new Problem('PURCHASE_STAFF_SCOPE','制单人和采购业务员必须属于所选博远公司，请重新选择人员');
        foreach($snapshot['lines'] as $index=>$source) {
            $pick=$selection['lines'][$index]; $product=$this->archive($site,$key,'products',$pick['product_id'],$complete);
            $warehouse=$this->archive($site,$key,'warehouses',$pick['warehouse_id'],$complete);
            if($warehouse) {
                $owner=(string)($warehouse['extra']['company_id']??'');
                if($owner==='' || ($company && $owner!==$company['external_id'])) throw new Problem('WAREHOUSE_COMPANY_MISMATCH','仓库公司归属不明确或与所选公司不符，请选择同一公司的真实仓库');
                $warehouses[$warehouse['external_id']]=true;
            }
            if($product) $this->serial($source,$product);
            $lines[]=['source_line_id'=>$source['source_line_id'],'device_ref'=>$source['device_ref'],'imei'=>$source['imei'],'product_id'=>$pick['product_id'],
                'warehouse_id'=>$pick['warehouse_id'],'supplier_id'=>$selection['supplier_id'],'quantity'=>'1','amount'=>$source['amount']];
            $details[]=['imei'=>$source['imei'],'product_id'=>$pick['product_id'],'qty'=>1,'unit_price'=>$source['amount'],'amounts'=>$source['amount'],'remark'=>$source['source_line_id']];
        }
        if(count($warehouses)>1) throw new Problem('PURCHASE_MULTI_WAREHOUSE','首版采购任务只支持同一真实仓库；请核实统一仓库后选择，不会自动调拨或改写本地库存');
        if(!$complete) return ['schema'=>self::SCHEMA,'stage'=>'draft','selection'=>$selection]+$intent;
        $third='BP'.substr(hash('sha256',$site.':'.$key.':'.$task['fact_key']),0,30);
        $head=['warehouse_id'=>(string)array_key_first($warehouses),'third_no'=>$third,'business_date'=>(new \DateTimeImmutable('@'.$snapshot['occurred_at']))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d'),
            'cash_term'=>0,'is_auto_generate_supplier'=>0,'supplier_id'=>$supplier['external_id'],'creater_id'=>$creator['external_id'],'creater_name'=>$creator['name']];
        if($buyer) { $head['sales_id']=$buyer['external_id']; if($pricing['required']) $head['sales_name']=$buyer['name']; }
        if($this->pilotTask($task)) $head['remark']=Pilot::REMARK;
        if(Pilot::pricerOperation($task['operation_id']??'')) {
            if(($snapshot['lines'][0]['pricing_operator']['ref']??'')!=='staff:1') throw new Problem('PILOT_SCOPE','仅允许原定价人为本站 admin 的指定设备');
            Pilot::requireNewSelection($head,$details[0],$selection);
        }
        return ['schema'=>self::SCHEMA,'stage'=>'frozen','third_no'=>$third,'selection'=>$selection,'biz'=>['head'=>$head,'product_details'=>['add_details'=>$details]],'lines'=>$lines]+$intent;
    }

    private function archive(int $site,string $key,string $resource,string $id,bool $required,string $role=''): ?array
    {
        if($id==='') { if($required) throw new Problem('PURCHASE_SELECTION_REQUIRED','请完整选择公司、供应商、制单人及每台设备的商品和仓库'); return null; }
        Contract::id($id); if(strlen($id)>36) throw new Problem('ARCHIVE_ID_INVALID','博远档案编号超过官方字段长度');
        $run=ReferenceService::latestRun($site,$key,$resource);
        $row=CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)->where('external_id',$id)->where('enabled',1)->find();
        if(!$row || $run==='' || $row['seen_run']!==$run) throw new Problem('ARCHIVE_STALE','所选资料不在当前连接最近完整同步的启用档案中，请重新同步并核对');
        $row['extra']=ArchiveView::extras($row['extra_json']); unset($row['extra_json']);
        if($role==='supplier' && !in_array($row['extra']['is_supplier']??null,[1,'1',true],true)) throw new Problem('SUPPLIER_ROLE_INVALID','所选往来单位不是启用的供应商');
        if($role==='buyer' && !in_array($row['extra']['is_buyer']??null,[1,'1',true],true)) throw new Problem('BUYER_ROLE_INVALID','所选业务员不具备采购员属性');
        return $row;
    }

    private function facts(array $snapshot): void
    {
        if(($snapshot['kind']??'')!=='purchase' || !preg_match('/^purchase:[1-9][0-9]*$/D',(string)($snapshot['source_document_id']??'')) || !preg_match('/^[a-f0-9]{64}$/D',(string)($snapshot['batch_ref']??''))) throw new Problem('PURCHASE_FACT_INVALID','采购来源身份或批次无效');
        Contract::id($snapshot['source_document_no']??null); if(mb_strlen($snapshot['source_document_no'])>128) throw new Problem('PURCHASE_FACT_INVALID','采购单号过长');
        if(!is_int($snapshot['occurred_at']??null) || $snapshot['occurred_at']<946656000 || $snapshot['occurred_at']>time()+300) throw new Problem('PURCHASE_DATE_INVALID','采购日期必须为有效且不晚于当前的业务日期');
        if(!preg_match('/^party:[1-9][0-9]*$/D',(string)($snapshot['party']['ref']??''))) throw new Problem('SUPPLIER_SOURCE_INVALID','采购缺少本站供应商身份');
        $lines=$snapshot['lines']??null; if(!is_array($lines) || !array_is_list($lines) || count($lines)<1 || count($lines)>100) throw new Problem('PURCHASE_LINES_INVALID','采购设备明细必须为1至100行');
        $ids=[]; $devices=[]; $serials=[];
        foreach($lines as $line) {
            foreach(['source_line_id'=>'purchase_line','device_ref'=>'asset','warehouse_ref'=>'warehouse'] as $field=>$prefix) if(!preg_match('/^'.$prefix.':[1-9][0-9]*$/D',(string)($line[$field]??''))) throw new Problem('PURCHASE_IDENTITY_INVALID','采购行、设备或本地仓库身份无效');
            if(!preg_match('/^model:[a-f0-9]{64}$/D',(string)($line['product_ref']??''))) throw new Problem('PRODUCT_SOURCE_INVALID','本地型号规格颜色身份无效');
            if(array_key_exists('catalog_product_id',$line) && (!is_int($line['catalog_product_id']) || $line['catalog_product_id']<1)) throw new Problem('PRODUCT_SOURCE_INVALID','采购商品目录身份无效');
            $id=$line['source_line_id']; $device=$line['device_ref']; $imei=$line['imei']??null;
            if(!$this->oneQuantity($line['quantity']??null) || !is_string($imei) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',$imei) || isset($ids[$id]) || isset($devices[$device]) || isset($serials[$imei])) throw new Problem('PURCHASE_SERIAL_INVALID','必须一机一行、数量为1、IMEI唯一且有效；不将SN猜作IMEI');
            $ids[$id]=$devices[$device]=$serials[$imei]=true;
            if(Contract::cents($line['amount']??null)>9999999999) throw new Problem('PURCHASE_AMOUNT_INVALID','设备成本超过博远金额安全范围');
        }
        if($snapshot['batch_ref']!==hash('sha256',implode('|',array_column($lines,'source_line_id')))) throw new Problem('PURCHASE_BATCH_INVALID','采购批次与设备行身份不一致');
    }

    private function serial(array $source,array $product): void
    {
        $extra=$product['extra'];
        // Only single-serial products are supported; extra serials cannot be invented or dropped.
        $name=mb_substr((string)($product['name']??'所选商品'),0,100);
        if(!in_array($extra['imei_count']??null,[1,'1'],true)) {
            $mode=$extra['imei_count']??null;
            $label=match($mode){0,'0'=>'无串号',2,'2'=>'双串号',3,'3'=>'三串号',4,'4'=>'四串号',default=>'串号规则未明确'};
            throw new Problem('PRODUCT_SERIAL_UNSUPPORTED',"博远商品「{$name}」是{$label}档案。请改选同型号的单串号商品；若没有，请先在博远维护后同步商品。未发送采购单。");
        }
        $length=$extra['imei_len']??null;
        if(!(is_int($length)||is_string($length)) || (int)$length<1 || (int)$length>64 || (string)(int)$length!==(string)$length)
            throw new Problem('PRODUCT_SERIAL_LENGTH','博远商品「'.$name.'」没有明确有效的串号长度，请核对商品档案；未发送采购单。');
        $actual=strlen($source['imei']);
        if($actual!==(int)$length)
            throw new Problem('PRODUCT_SERIAL_LENGTH','原设备串号是 '.$actual.' 位，博远商品「'.$name.'」要求 '.$length.' 位。请核对机身串号和商品档案；不会补位、截断或发送采购单。');
    }

    private function selection(array $snapshot,$selection): array
    {
        if(!is_array($selection)) throw new Problem('PURCHASE_SELECTION_INVALID','请提供外部资料选择');
        $this->keys($selection,['company_id','supplier_id','creator_id','buyer_id','lines']); $result=[];
        foreach(['company_id','supplier_id','creator_id','buyer_id'] as $field) { if(!is_string($selection[$field]??null)) throw new Problem('PURCHASE_SELECTION_INVALID','资料编号必须为字符串'); $result[$field]=$selection[$field]; }
        $picks=$selection['lines']??null; if(!is_array($picks) || !array_is_list($picks) || count($picks)!==count($snapshot['lines'])) throw new Problem('PURCHASE_SELECTION_INVALID','必须保留全部原采购行，仅可调整外部商品及仓库');
        $byId=[];
        foreach($picks as $pick) {
            if(!is_array($pick)) throw new Problem('PURCHASE_SELECTION_INVALID','采购行选择无效'); $this->keys($pick,['source_line_id','product_id','warehouse_id']);
            foreach(['source_line_id','product_id','warehouse_id'] as $field) if(!is_string($pick[$field]??null)) throw new Problem('PURCHASE_SELECTION_INVALID','采购行身份及外部编号必须为字符串');
            if(isset($byId[$pick['source_line_id']])) throw new Problem('PURCHASE_SELECTION_INVALID','采购行不可重复'); $byId[$pick['source_line_id']]=$pick;
        }
        $result['lines']=[];
        foreach($snapshot['lines'] as $source) { if(!isset($byId[$source['source_line_id']])) throw new Problem('PURCHASE_SELECTION_INVALID','采购行身份已被修改'); $result['lines'][]=$byId[$source['source_line_id']]; }
        return $result;
    }

    private function assertCurrentSource(int $site,array $snapshot,bool $lock): void
    {
        $this->facts($snapshot); $id=(int)substr($snapshot['source_document_id'],9);
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',$id)->lock($lock)->find();
        if(!$head || ($head['status']??'')!=='completed') throw new Problem('PURCHASE_SOURCE_INVALID','原采购单已撤销、不存在或未确认，不能发送');
        if(isset($snapshot['purchase_source']) && (($snapshot['purchase_source']==='ordinary_customer')!== (($head['origin_plugin']??'')==='hsx_recycle')))
            throw new Problem('PURCHASE_SOURCE_CHANGED','采购来源与 ERP 原单不一致，不能发送');
        if((string)$head['purchase_no']!==$snapshot['source_document_no'] || 'party:'.$head['party_id']!==$snapshot['party']['ref'] || (int)$head['purchase_at']!==$snapshot['occurred_at']) throw new Problem('PURCHASE_SOURCE_CHANGED','原采购的单号、供应商或业务日期已变化，请重新核对；原快照不会被覆盖');
        foreach($snapshot['lines'] as $line) {
            $row=Db::name('erp_purchase_item')->where('site_id',$site)->where('purchase_order_id',$id)->where('id',(int)substr($line['source_line_id'],14))->lock($lock)->find();
            $asset=Db::name('erp_asset')->where('site_id',$site)->where('id',(int)substr($line['device_ref'],6))->lock($lock)->find();
            if(!$row || !$asset || 'asset:'.$row['asset_id']!==$line['device_ref']) throw new Problem('PURCHASE_SOURCE_CHANGED','原采购设备行或资产身份已变化，不能发送');
            if(isset($line['pricing_operator'])) {
                $spec=$row['spec_json']??null; if(is_string($spec)) $spec=json_decode($spec,true);
                $priced=$spec['pricing_operator']??null; $who=$line['pricing_operator'];
                if(!is_array($priced) || !is_array($who) || ($head['origin_plugin']??'')!=='hsx_recycle' || ($priced['source']??'')!=='recycle_final_price'
                    || ($who['ref']??null)!==((int)($priced['uid']??0)>0?'staff:'.(int)$priced['uid']:'')
                    || ($who['name']??null)!==((int)($priced['uid']??0)>0?(string)($priced['name']??''):''))
                    throw new Problem('PURCHASE_PRICER_CHANGED','实际定价人与原采购行的来源记录不一致，请先核对；未提交新采购');
            }
            if(isset($line['catalog_product_id']) && ((int)($asset['catalog_product_id']??0)!==$line['catalog_product_id'] || (int)($row['catalog_product_id']??0)!==$line['catalog_product_id']))
                throw new Problem('PURCHASE_SOURCE_CHANGED','设备或采购行的商品目录身份已变化，请核对原单，不发送旧事实');
            $product=['model'=>(string)($asset['model']??$row['model']??''),'spec'=>(string)($asset['spec']??$row['spec']??''),'color'=>(string)($asset['color']??$row['color']??'')];
            $amount=$this->sourceMoney($row['purchase_cost']??null);
            if((string)($row['imei']??$asset['imei']??'')!==$line['imei'] || (string)($row['sn']??$asset['sn']??'')!==(string)($line['sn']??'') || !$this->oneQuantity($row['quantity']??'1') || $amount!==$line['amount']
                || 'model:'.hash('sha256',json_encode($product,JSON_UNESCAPED_UNICODE))!==$line['product_ref'] || 'warehouse:'.($row['warehouse_id']??$asset['warehouse_id']??0)!==$line['warehouse_ref']) throw new Problem('PURCHASE_SOURCE_CHANGED','原采购的IMEI、数量、成本、型号规格颜色或仓库发生变化；请人工核对，不发送旧事实');
        }
    }
    /** @internal Shared read-only guard for the batch closure view; not an API or state transition. */
    public function assertClosureSourceCurrent(int $site,array $snapshot): void { $this->assertCurrentSource($site,$snapshot,false); }

    private function sourceMoney($value): string
    {
        if(!is_string($value) && !is_int($value)) throw new Problem('PURCHASE_AMOUNT_INVALID','原采购成本不是可精确核验的十进制金额');
        if(!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D',(string)$value,$m)) throw new Problem('PURCHASE_AMOUNT_INVALID','原采购成本不是有效两位小数金额');
        return $m[1].'.'.str_pad($m[2]??'',2,'0');
    }
    private function oneQuantity($value): bool { return $value===1 || is_string($value) && preg_match('/^1(?:\.0{1,3})?$/D',$value)===1; }

    private function staffBelongs(array $staff,string $company,array $companyExtra): bool
    {
        $id=(string)($staff['company_id']??''); $code=(string)($staff['gsdm']??''); $ownCode=(string)($companyExtra['gsdm']??'');
        return $company!=='' && $id===$company && ($code==='' || $ownCode==='' || $code===$ownCode);
    }
    /** A purchase header has ONE Boyuan buyer. Only the posted per-device final-pricing identity qualifies. */
    private function pricingBuyer(int $site,string $key,array $snapshot,bool $lookup=true): array
    {
        $info=['required'=>false,'status'=>'not_required','local_ref'=>'','name'=>'','message'=>'','mapped_id'=>''];
        $kind=$snapshot['purchase_source']??'';
        if($kind==='') {
            $id=(int)substr((string)($snapshot['source_document_id']??''),9);
            if(!Db::name('erp_purchase_order')->where(['site_id'=>$site,'id'=>$id,'origin_plugin'=>'hsx_recycle'])->count()) return $info;
        } elseif($kind!=='ordinary_customer') return $info;
        $info['required']=true; $ids=[];
        foreach($snapshot['lines']??[] as $line) {
            $who=$line['pricing_operator']??[]; $ref=$who['ref']??''; $name=$who['name']??'';
            if(!is_string($ref) || !preg_match('/^staff:[1-9][0-9]*$/D',$ref) || !is_string($name) || trim($name)==='') {
                return array_replace($info,['status'=>'missing','message'=>'本批设备缺少实际定价人记录，不能用录单人、报价员或制单人代替；请核对回收原单。']);
            }
            $ids[$ref]=$name;
        }
        if(count($ids)!==1) return array_replace($info,['status'=>'mixed','message'=>'每张博远采购单只能有一位采购业务员；请按实际定价人分别入库，不能任选一人代替整批。']);
        $ref=array_key_first($ids); $info['status']='known'; $info['local_ref']=$ref; $info['name']=$ids[$ref];
        if(!$lookup) return $info;
        $index='staff:'.$ref; $native=[];
        $m=Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>'staff','local_ref'=>$ref])->find();
        if($m) $native[$index]=(string)$m['external_id'];
        $merged=BridgeMasterSelections::merge($site,$key,'purchase',[$ref],$native,$snapshot);
        if(!array_key_exists($index,$merged)) return $info;
        $remote=(string)$merged[$index];
        try {
            if(!$remote || !$this->archive($site,$key,'staff',$remote,true,'buyer')
                || Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>'staff','external_id'=>$remote])->where('local_ref','<>',$ref)->count())
                throw new Problem('PURCHASE_BUYER_CONFLICT','人员对应冲突');
        } catch(Problem $e) {
            return array_replace($info,['status'=>'conflict','message'=>'实际定价人「'.$info['name'].'」的博远人员对应冲突、已停用或不具备采购员属性，请在人员对应和博远档案核对后再同步。']);
        }
        $info['mapped_id']=$remote;
        return $info;
    }
    private function defaults(int $site,string $key,array $snapshot,array $saved=[],?array $pricing=null): array
    {
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',(int)substr((string)$snapshot['source_document_id'],9))->find();
        $recycle=$this->recycleSkuIdentities($site,$head??[],$snapshot['lines']);
        $productRefs=$recycle?array_column(array_filter($recycle),'ref'):array_column($snapshot['lines'],'product_ref');
        $refs=array_unique(array_filter(array_merge([$snapshot['party']['ref']??'', $snapshot['operator']['operator_ref']??'','staff:'.($head['purchaser_uid']??0)],$productRefs,array_column($snapshot['lines'],'warehouse_ref'))));
        $maps=[]; $remembered=[];
        foreach(Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$key)->whereIn('local_ref',array_values($refs))->select()->toArray() as $map) {
            $maps[$map['kind'].':'.$map['local_ref']]=$map['external_id'];
            if ($recycle && $map['kind']==='product') $remembered[$map['local_ref']]=$map;
        }
        $maps=BridgeMasterSelections::merge($site,$key,'purchase',$refs,$maps,$snapshot);
        $pricing=$pricing??$this->pricingBuyer($site,$key,$snapshot);
        $selection=['company_id'=>(string)($saved['company_id']??''),'supplier_id'=>(string)(($saved['supplier_id']??'')?:($maps['supplier:'.($snapshot['party']['ref']??'')]??'')),
            'creator_id'=>(string)(($saved['creator_id']??'')?:($maps['staff:'.($snapshot['operator']['operator_ref']??'')]??'')),
            'buyer_id'=>$pricing['required']?($pricing['status']==='known'?($pricing['mapped_id']?:($saved['buyer_id']??'')):''):(string)($saved['buyer_id']??$maps['staff:staff:'.($head['purchaser_uid']??0)]??''),'lines'=>[]];
        $savedLines=array_column($saved['lines']??[],null,'source_line_id');
        $fromOpening=!$recycle && array_column($snapshot['lines'],'catalog_product_id') ? OpeningMasterService::purchaseProductDefaults($site,$key,$snapshot['lines']) : [];
        foreach($snapshot['lines'] as $line) {
            $ref=$line['source_line_id']; $manual=$savedLines[$ref]??[];
            $suggest=$recycle ? ($remembered[$recycle[$ref]['ref']??'']['external_id']??'') : ($maps['product:'.$line['product_ref']]??$fromOpening[$ref]??'');
            $selection['lines'][]=['source_line_id'=>$ref,'product_id'=>(string)(($manual['product_id']??'')?:$suggest),
                'warehouse_id'=>(string)(($manual['warehouse_id']??'')?:($maps['warehouse:'.$line['warehouse_ref']]??''))];
        }
        $read=static function(string $resource,array $ids) use($site,$key): array {
            $ids=array_values(array_unique(array_filter($ids,'strlen'))); if(!$ids) return [];
            $run=ReferenceService::latestRun($site,$key,$resource); if($run==='') return [];
            $rows=[];
            foreach(CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)->where('seen_run',$run)->where('enabled',1)->whereIn('external_id',$ids)->select()->toArray() as $row) {
                $rows[$row['external_id']]=ArchiveView::extras($row['extra_json']);
                if ($resource==='products') $rows[$row['external_id']]['_archive_name']=$row['name'];
            }
            return $rows;
        };
        $warehouses=$read('warehouses',array_column($selection['lines'],'warehouse_id')); $products=$read('products',array_column($selection['lines'],'product_id'));
        $owners=[]; $families=[];
        foreach($selection['lines'] as $index=>&$pick) {
            $ref=$pick['source_line_id'];
            if ($recycle && $pick['product_id']!=='' && empty($savedLines[$ref]['product_id'])) {
                $identity=$recycle[$ref]??null; $prior=$remembered[$identity['ref']??'']??[]; $current=$products[$pick['product_id']]??[];
                $old=json_decode((string)($prior['extra_json']??''),true);
                if (!$identity || !is_array($old) || ($prior['external_name']??null)!==($current['_archive_name']??null)
                    || !RecyclePurchaseSku::unchanged($old,$current) || !RecyclePurchaseSku::unambiguous($site,$key,$pick['product_id'],$identity,$current,$families)) $pick['product_id']='';
            }
            if(!isset($products[$pick['product_id']])) $pick['product_id']='';
            else { try { $this->serial($snapshot['lines'][$index],['extra'=>$products[$pick['product_id']]]); } catch(Problem $e) { $pick['product_id']=''; } }
            $owner=(string)($warehouses[$pick['warehouse_id']]['company_id']??'');
            if($owner==='' || ($selection['company_id']!=='' && $owner!==$selection['company_id'])) $pick['warehouse_id']='';
            $owners[$owner]=true;
        }
        unset($pick);
        $co=$selection['company_id']?:((count($owners)===1)?(string)array_key_first($owners):'');
        $companies=$read('companies',array_merge([$co],array_map('strval',array_keys($owners)))); $selection['company_id']=isset($companies[$co])?$co:'';
        // This adapter creates one physical warehouse receipt. Conflicting mappings
        // must be reviewed, never present an impossible multi-warehouse draft as ready.
        $multiple=count(array_unique(array_filter(array_column($selection['lines'],'warehouse_id'),'strlen')))>1;
        foreach($selection['lines'] as &$pick) {
            $owner=(string)($warehouses[$pick['warehouse_id']]['company_id']??'');
            if(!isset($companies[$owner]) || ($multiple && empty($savedLines[$pick['source_line_id']]['warehouse_id']))) $pick['warehouse_id']='';
        }
        unset($pick);
        $parties=$read('parties',[$selection['supplier_id']]); if(!in_array($parties[$selection['supplier_id']]['is_supplier']??null,[1,'1',true],true)) $selection['supplier_id']='';
        $staff=$read('staff',[$selection['creator_id'],$selection['buyer_id']]);
        foreach(['creator_id','buyer_id'] as $field) {
            $person=$staff[$selection[$field]]??null;
            if(!$person || !$this->staffBelongs($person,$selection['company_id'],$companies[$co]??[]) || ($field==='buyer_id' && !in_array($person['is_buyer']??null,[1,'1',true],true))) $selection[$field]='';
        }
        return $selection;
    }

    private function recycleSkuIdentities(int $site,array $head,array $lines): array
    {
        if (($head['origin_plugin']??'')!=='hsx_recycle' && ($head['source_plugin']??'')!=='hsx_recycle') return [];
        return RecyclePurchaseSku::identities($site,$head,$lines);
    }

    private function remember(int $site,array $task,array $selection,array $snapshot,int $uid): void
    {
        $items=[['supplier',$snapshot['party']['ref'],$snapshot['party']['name'],$selection['supplier_id'],'parties']];
        if($selection['creator_id']!=='' && preg_match('/^staff:[1-9][0-9]*$/D',(string)($snapshot['operator']['operator_ref']??''))) $items[]=['staff',$snapshot['operator']['operator_ref'],(string)($snapshot['operator']['name']??''),$selection['creator_id'],'staff'];
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',(int)substr($snapshot['source_document_id'],9))->find();
        $pricing=$this->pricingBuyer($site,$task['connection_key'],$snapshot,false);
        if($selection['buyer_id']!=='' && $pricing['required'] && $pricing['status']==='known') $items[]=['staff',$pricing['local_ref'],$pricing['name'],$selection['buyer_id'],'staff'];
        elseif($selection['buyer_id']!=='' && !$pricing['required'] && (int)($head['purchaser_uid']??0)>0) $items[]=['staff','staff:'.$head['purchaser_uid'],(string)($head['purchaser_name']??''),$selection['buyer_id'],'staff'];
        $recycle=$this->recycleSkuIdentities($site,$head??[],$snapshot['lines']); $variants=[];
        foreach($snapshot['lines'] as $index=>$line) {
            $identity=$recycle[$line['source_line_id']]??null;
            if (!$recycle || $identity) {
                $local=$identity['ref']??$line['product_ref'];
                $items[]=['product',$local,$identity['label']??$line['product_name'],$selection['lines'][$index]['product_id'],'products'];
                if ($identity) $variants[$local]=$identity;
            }
            $items[]=['warehouse',$line['warehouse_ref'],$line['warehouse_ref'],$selection['lines'][$index]['warehouse_id'],'warehouses'];
        }
        $proposed=[];
        foreach($items as [$kind,$local,,$external]) if($external!=='' && $kind!=='product') $proposed[$kind.':'.$local]=$external;
        $consistent=BridgeMasterSelections::merge($site,$task['connection_key'],'purchase',array_column($items,1),$proposed,$snapshot);
        foreach($proposed as $ref=>$external) if(($consistent[$ref]??'')!==$external)
            throw new Problem('MAPPING_CONFLICT','集成中心已有不同或不唯一的资料对应，本次没有覆盖。请核对映射，或取消“记住对应”仅用于本单。');
        $seen=[]; $families=[];
        foreach($items as [$kind,$local,$name,$external,$resource]) {
            if($external==='') continue; $identity=$kind.':'.$local;
            // Creator and buyer are independent roles; role validation already ran in prepare().
            $archive=$this->archive($site,$task['connection_key'],$resource,$external,true,$kind==='supplier'?'supplier':'');
            if (isset($variants[$local]) && !RecyclePurchaseSku::unambiguous($site,$task['connection_key'],$external,$variants[$local],$archive['extra'],$families)) continue;
            if(isset($seen[$identity]) && $seen[$identity]!==$external) throw new Problem('MAPPING_CONFLICT','同一本地资料选择了不同博远档案，不能记为全局对应'); $seen[$identity]=$external;
            $where=[['site_id','=',$site],['connection_key','=',$task['connection_key']],['kind','=',$kind],['local_ref','=',$local]];
            $old=Db::name('hsx_boyuan_mapping')->where($where)->lock(true)->find();
            if ($old && isset($variants[$local])) {
                // Re-learn only an obsolete official SKU, inside this operator's explicit 'remember' transaction.
                $previous=$old['external_id']===$external?$archive:null;
                if (!$previous) {
                    try { $previous=$this->archive($site,$task['connection_key'],'products',$old['external_id'],true); }
                    catch (Problem $e) { if ($e->reason!=='ARCHIVE_STALE') throw $e; }
                }
                $baseline=json_decode((string)$old['extra_json'],true);
                if ($previous && is_array($baseline) && $old['external_name']===$previous['name'] && RecyclePurchaseSku::unambiguous($site,$task['connection_key'],$old['external_id'],$variants[$local],$previous['extra'],$families)
                    && RecyclePurchaseSku::unchanged($baseline,$previous['extra'])) {
                    if ($old['external_id']!==$external) throw new Problem('MAPPING_CONFLICT','该回收规格已有有效的博远商品对应。如仅本单例外，请取消“记住对应”后再保存；不会覆盖原对应');
                    continue;
                }
                Db::name('hsx_boyuan_mapping')->where($where)->update(['local_name'=>mb_substr((string)$name,0,255),'external_id'=>$external,'external_code'=>$archive['code'],'external_name'=>$archive['name'],
                    'extra_json'=>Contract::json($archive['extra']),'revision'=>(int)$old['revision']+1,'operator_uid'=>$uid,'update_at'=>time()]);
                continue;
            }
            if($old) { if($old['external_id']!==$external) throw new Problem('MAPPING_CONFLICT','已存在不同的全局对应，请在资料对应中单独核对；本次不会覆盖'); continue; }
            if($kind==='staff' && Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$task['connection_key'])->where('kind','staff')->where('external_id',$external)->where('local_ref','<>',$local)->count()) throw new Problem('STAFF_ALREADY_BOUND','博远业务员已对应其他本站人员，不能覆盖');
            Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$site,'connection_key'=>$task['connection_key'],'kind'=>$kind,'local_ref'=>$local,'local_name'=>mb_substr((string)$name,0,255),
                'external_id'=>$external,'external_code'=>$archive['code'],'external_name'=>$archive['name'],'extra_json'=>Contract::json($archive['extra']),'revision'=>1,'operator_uid'=>$uid,'update_at'=>time()]);
        }
    }

    private function bindings(array $task,array $request,array $result,string $number,string $externalId): void
    {
        if(($result['verification_level']??'')!=='purchase_imei_records_matched') throw new Problem('PURCHASE_EVIDENCE_REQUIRED','采购及串号事实证据不足，不能完成','unknown');
        $items=[['document',Contract::decode($task['snapshot_json'])['source_document_id'],$externalId,['verification_level'=>$result['verification_level']]]];
        $expected=array_column($request['lines'],null,'source_line_id'); $seen=[];
        foreach($result['lines']??[] as $line) {
            $ref=(string)($line['source_line_id']??''); $source=$expected[$ref]??null;
            if(!$source || isset($seen[$ref]) || ($line['device_ref']??'')!==$source['device_ref'] || ($line['imei']??'')!==$source['imei'] || ($line['external_document_id']??'')!==$externalId) throw new Problem('PURCHASE_EVIDENCE_MISMATCH','核验明细与冻结设备身份不一致','unknown');
            Contract::id($line['external_line_id']??null); $seen[$ref]=true; $items[]=['line',$ref,$line['external_line_id'],$line];
        }
        if(count($seen)!==count($expected)) throw new Problem('PURCHASE_EVIDENCE_INCOMPLETE','采购串号核验缺少设备行，不能标记整单成功','unknown');
        foreach($items as [$kind,$local,$external,$detail]) {
            $where=[['site_id','=',$task['site_id']],['connection_key','=',$task['connection_key']],['kind','=',$kind],['local_ref','=',$local]];
            $old=Db::name('hsx_boyuan_binding')->where($where)->lock(true)->find();
            // Append batches can create more than one vendor head for a local order; lines remain authoritative.
            if($old && $old['external_id']!==$external) { if($kind==='document') continue; throw new Problem('BINDING_CONFLICT','该设备已关联其他博远行，请人工核验','unknown'); }
            $values=['external_id'=>$external,'external_no'=>$number,'detail_json'=>Contract::json($detail),'update_at'=>time()];
            if($old) Db::name('hsx_boyuan_binding')->where($where)->update($values);
            else Db::name('hsx_boyuan_binding')->insert($values+['site_id'=>$task['site_id'],'connection_key'=>$task['connection_key'],'kind'=>$kind,'local_ref'=>$local,'task_id'=>$task['id']]);
        }
    }

    private function task(int $site,int $id,bool $lock=false): array
    {
        $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->lock($lock)->find();
        if(!$task || $task['kind']!=='purchase' || (Contract::decode($task['request_json'])['schema']??'')!==self::SCHEMA) throw new Problem('PURCHASE_TASK_MISSING','采购核对任务不存在或属于其他同步协议');
        return $task;
    }
    private function lockSite(int $site): void
    {
        if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
    }
    private function captureStart(array $config): int { return (int)($config['purchase_started_at']??$config['started_at']??0); }
    private function version(array $task,array $config,array $profile,array $input): void
    {
        if(!is_int($input['revision']??null) || $input['revision']!==(int)$task['revision']) throw new Problem('TASK_STALE','采购核对已被其他操作更新，请刷新后重试');
        if(($input['mode']??null)!==$task['mode'] || ($input['connection_key']??null)!==$task['connection_key'] || $task['mode']!==$config['mode'] || $task['connection_key']!==$profile['connection_key']) throw new Problem('CONNECTION_CHANGED','连接已切换或任务不属于当前连接，请刷新');
    }
    private function keys(array $input,array $allowed): void
    {
        if(array_diff(array_keys($input),$allowed)) throw new Problem('PURCHASE_READ_ONLY_FACT','只能调整外部资料选择，不能修改来源IMEI、数量、成本或本地身份');
    }
    private function labels(int $site,string $key,array $selection): array
    {
        $get=static function(string $resource,string $id) use($site,$key): ?array {
            if($id==='') return null; $row=CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)->where('external_id',$id)->field('external_id,code,name')->find();
            return $row?['id'=>$row['external_id'],'code'=>$row['code'],'name'=>$row['name']]:null;
        };
        $labels=['company'=>$get('companies',(string)($selection['company_id']??'')),'supplier'=>$get('parties',(string)($selection['supplier_id']??'')),'creator'=>$get('staff',(string)($selection['creator_id']??'')),'buyer'=>$get('staff',(string)($selection['buyer_id']??'')),'lines'=>[]];
        foreach($selection['lines']??[] as $line) $labels['lines'][]=['source_line_id'=>$line['source_line_id'],'product'=>$get('products',$line['product_id']),'warehouse'=>$get('warehouses',$line['warehouse_id'])];
        return $labels;
    }
    private function publicSource(array $snapshot): array
    {
        return Contract::redact(array_intersect_key($snapshot,array_flip(['kind','source_document_id','source_document_no','batch_ref','occurred_at','party','operator','lines','purchase_source'])));
    }
    private function safeResult(array $result): array
    {
        $safe=array_intersect_key($result,array_flip(['created','verified','external_no','external_id','verification_level','stock_balance_verified','financial_posting_verified']));
        if(is_array($result['provider_result']??null)) $safe['provider_result']=\addon\hsx_boyuan\app\support\ReceiptFacts::head($result['provider_result']);
        if(isset($result['lines']) && is_array($result['lines'])) $safe['lines']=array_map(static fn($line)=>array_intersect_key((array)$line,array_flip(['source_line_id','device_ref','external_line_id','external_document_id','imei'])),array_slice($result['lines'],0,100));
        if(isset($result['evidence']) && is_array($result['evidence'])) $safe['evidence']=array_intersect_key($result['evidence'],array_flip(['purchase_detail_count','imei_detail_count','bill_type','business_date','company_id','gsdm','request_third_no','created_head_id_matched','document_head_id_verified','response_contract','external_line_id_source','supplier_id','warehouse_id','record_is_effective','inventory_evidence','limitations']));
        if(is_array($result['evidence']['pricing_buyer']??null) && ($buyer=ReceiptFacts::buyerEvidence($result['evidence']['pricing_buyer']))) $safe['evidence']['pricing_buyer']=$buyer;
        if(is_array($result['evidence']['previous_buyer_conflict']??null) && ($prior=ReceiptFacts::buyerEvidence($result['evidence']['previous_buyer_conflict'])) && $prior['status']==='mismatch') $safe['evidence']['previous_buyer_conflict']=$prior;
        return $safe;
    }
}
