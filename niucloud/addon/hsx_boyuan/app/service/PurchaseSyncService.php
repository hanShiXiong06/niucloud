<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway,Transport};
use addon\hsx_boyuan\app\support\{Contract,Problem};
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
        [$config,$profile]=$this->context($site);
        if(empty($config['enabled']) || empty($config['purchase_enabled'])) return [];
        if((int)($event['site_id']??0)!==$site || ($event['event_version']??null)!==1 || !is_string($event['event_id']??null) || $event['event_id']==='' || strlen($event['event_id'])>128) throw new Problem('EVENT_INVALID','ERP事件身份、站点或版本无效');
        $name=$event['event_name']??''; $snapshot=(array)($event['payload']??[]);
        if($name==='erp.integration.document.invalidated.v1') return $this->invalidate($site,$profile['connection_key'],$snapshot);
        if($name!=='erp.integration.document.confirmed.v1' || ($snapshot['kind']??'')!=='purchase') return [];
        if((int)($event['occurred_at']??0)<$this->captureStart($config)) return [];
        $this->facts($snapshot);
        $created=false; $automatic=false;
        $id=Db::transaction(function() use($site,$event,$snapshot,&$created,&$automatic): int {
            $this->lockSite($site); [$config,$profile]=$this->context($site);
            if(empty($config['enabled']) || empty($config['purchase_enabled']) || (int)$event['occurred_at']<$this->captureStart($config)) return 0;
            $fact=$snapshot['source_document_id'].':'.$snapshot['batch_ref'];
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
            $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'mode'=>$config['mode'],
                'fact_key'=>$fact,'event_id'=>$event['event_id'],'operation_id'=>'BY_'.hash('sha256',$site.':'.$profile['connection_key'].':'.$fact),'kind'=>'purchase',
                'business_no'=>$snapshot['source_document_no'],'status'=>'awaiting_confirmation','reason'=>'请核对外部资料后提交；尚未向博远创建采购单',
                'snapshot_json'=>Contract::json($snapshot),'request_json'=>Contract::json(['schema'=>self::SCHEMA,'stage'=>'draft','selection'=>$selection,'auto_verify'=>($config['purchase_mode']??'manual')==='automatic']),'result_json'=>'{}',
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
        [$config,$profile]=$this->context($site);
        if(empty($config['enabled']) || empty($config['purchase_enabled'])) throw new Problem('PURCHASE_DISABLED','请先启用本站采购同步');
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',$purchaseId)->find();
        if(!$head || ($head['status']??'')!=='completed') throw new Problem('PURCHASE_UNCONFIRMED','仅可获取本站已确认且未撤销的采购单');
        $found=[];
        foreach(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$profile['connection_key'])->where('kind','purchase')->order('id')->select()->toArray() as $task) {
            if((Contract::decode($task['snapshot_json'])['source_document_id']??'')==='purchase:'.$purchaseId && (Contract::decode($task['request_json'])['schema']??'')===self::SCHEMA) $found[]=(int)$task['id'];
        }
        // An append to an older order is eligible only through its own new confirmed event.
        $events=Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.confirmed.v1')->where('occurred_at','>=',$this->captureStart($config))->order('id')->select()->toArray();
        foreach($events as $row) {
            $event=Contract::decode((string)$row['payload_json']);
            if(($event['payload']['source_document_id']??'')!=='purchase:'.$purchaseId) continue;
            $accepted=$this->capture($site,$event); if(!empty($accepted['task_id'])) $found[]=(int)$accepted['task_id'];
        }
        if(!$found) throw new Problem('PURCHASE_FACT_MISSING','未找到启用后的采购确认事实，请检查ERP事件投递；不会凭当前库存重新构造历史事实');
        $ids=array_values(array_unique($found)); sort($ids,SORT_NUMERIC); $target=$ids[count($ids)-1];
        foreach(array_reverse($ids) as $candidate) {
            if(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$candidate)->value('status')!=='succeeded') { $target=$candidate; break; }
        }
        $view=$this->view($site,$target);
        TaskService::audit($site,$target,$profile['connection_key'],'purchase_open',$view['task']['status'],'操作员打开已确认采购的核对任务，未发送',$uid);
        return ['task_id'=>$target,'task_ids'=>$ids,'view'=>$view];
    }

    public function view(int $site,int $taskId): array
    {
        [$config,$profile]=$this->context($site); $task=$this->task($site,$taskId); $snapshot=Contract::decode($task['snapshot_json']); $request=Contract::decode($task['request_json']);
        $active=$task['mode']===$config['mode'] && $task['connection_key']===$profile['connection_key'];
        $editable=$active && $task['status']==='awaiting_confirmation' && $task['side_effect']==='none' && ($request['stage']??'')==='draft' && !str_starts_with((string)$task['resolution'],self::INVALIDATED);
        $selection=(array)($request['selection']??$this->defaults($site,$task['connection_key'],$snapshot)); $issues=[];
        try { $this->assertCurrentSource($site,$snapshot,false); $this->prepare($site,$task,$selection,true); }
        catch(Problem $e) { $issues[]=$e->getMessage(); }
        $enabled=!empty($config['enabled']) && !empty($config['purchase_enabled']) && empty($config['paused']) && !empty($profile['probe_at']);
        if(!$active) $issues[]='任务属于原连接，不能在当前连接提交';
        if(!$enabled) $issues[]='采购投递未启用、已暂停或账套尚未核验';
        $public=array_intersect_key($task,array_flip(['id','revision','mode','connection_key','kind','business_no','status','reason','external_no','side_effect','attempts','create_at','update_at']));
        foreach(['id','revision','attempts','create_at','update_at'] as $key) $public[$key]=(int)$public[$key];
        $inventory=(new PurchaseInventoryService())->summary($site,$task); $canCheckInventory=$inventory['can_check']; unset($inventory['can_check']);
        $payment=(new PaymentSyncService())->summary($site,$task);
        return ['task'=>$public,'source'=>$this->publicSource($snapshot),'selection'=>$selection,'labels'=>$this->labels($site,$task['connection_key'],$selection),
            'editable'=>$editable,'can_submit'=>$editable && $enabled && !$issues,
            'can_verify'=>$active && $enabled && ($request['stage']??'')==='frozen' && !in_array($task['status'],['processing','succeeded'],true),
            'inventory'=>$inventory,'can_check_inventory'=>$canCheckInventory,'payment'=>$payment,
            'issues'=>$issues,'result'=>$this->safeResult(Contract::decode($task['result_json'])),'closure'=>(new PurchaseClosureService())->view($site,$task,$inventory,$payment)];
    }

    public function saveDraft(int $site,int $taskId,int $uid,array $input): array
    {
        return $this->edit($site,$taskId,$uid,$input,false);
    }
    public function submit(int $site,int $taskId,int $uid,array $input): array
    {
        return $this->edit($site,$taskId,$uid,$input,true);
    }
    private function edit(int $site,int $taskId,int $uid,array $input,bool $submit): array
    {
        $this->keys($input,['revision','mode','connection_key','selection','remember']);
        if(isset($input['remember']) && !is_bool($input['remember'])) throw new Problem('INPUT_INVALID','记住对应须为明确布尔选项');
        Db::transaction(function() use($site,$taskId,$uid,$input,$submit): void {
            $this->lockSite($site); [$config,$profile]=$this->context($site,$submit); $task=$this->task($site,$taskId,true); $this->version($task,$config,$profile,$input);
            $old=Contract::decode($task['request_json']);
            if($task['status']!=='awaiting_confirmation' || $task['side_effect']!=='none' || ($old['stage']??'')!=='draft' || (int)$task['attempts']!==0 || str_starts_with((string)$task['resolution'],self::INVALIDATED)) throw new Problem('PURCHASE_FROZEN','任务已提交、已发送或原采购已失效，不能修改或重复创建');
            $snapshot=Contract::decode($task['snapshot_json']); $selection=$this->selection($snapshot,$input['selection']??null); $request=$this->prepare($site,$task,$selection,$submit);
            if($submit) $this->assertCurrentSource($site,$snapshot,true);
            if(!empty($input['remember'])) $this->remember($site,$task,$selection,$snapshot,$uid);
            $values=['request_json'=>Contract::json($request),'revision'=>(int)$task['revision']+1,'update_at'=>time()];
            if($submit) $values+=['status'=>'pending','reason'=>'已核对并冻结，等待异步创建采购；不自动登记付款','query_only'=>0,'next_at'=>time()+1];
            Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$taskId)->update($values);
            TaskService::audit($site,$taskId,$task['connection_key'],$submit?'purchase_submit':'purchase_draft',$submit?'pending':'awaiting_confirmation',$submit?'采购请求已冻结，等待异步发送；未登记付款':'仅保存外部资料选择，未外发',$uid,['remember'=>!empty($input['remember'])]);
        });
        return $this->view($site,$taskId);
    }

    public function queueVerify(int $site,int $taskId,int $uid,array $input): array
    {
        $this->keys($input,['revision','mode','connection_key','external_no']);
        Db::transaction(function() use($site,$taskId,$uid,$input): void {
            $this->lockSite($site); [$config,$profile]=$this->context($site,true); $task=$this->task($site,$taskId,true); $this->version($task,$config,$profile,$input);
            $request=Contract::decode($task['request_json']);
            if(($request['stage']??'')!=='frozen' || in_array($task['status'],['processing','succeeded'],true)) throw new Problem('VERIFY_STATE','请先提交并冻结采购核对；处理中或已完成任务不能重复操作');
            $number=$input['external_no']??$task['external_no'];
            if(!is_string($number) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',$number)) throw new Problem('EXTERNAL_NO_REQUIRED','请提供有效的博远采购单号，只排队只读核验');
            if($task['external_no']!=='' && $number!==$task['external_no']) throw new Problem('EXTERNAL_NO_CONFLICT','已取得的博远原单号不能被另一个手工单号覆盖');
            Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$taskId)->update(['external_no'=>$number,'status'=>'confirming','query_only'=>1,'side_effect'=>'unknown',
                'reason'=>'已排队只读核验原采购及串号事实；不会重新创建或登记付款','revision'=>(int)$task['revision']+1,'next_at'=>time()+1,'update_at'=>time()]);
            TaskService::audit($site,$taskId,$task['connection_key'],'purchase_verify','confirming','只读核验已排队；人工单号不代表成功',$uid,['external_no'=>$number]);
        });
        return $this->view($site,$taskId);
    }

    public function process(int $site,int $id,?Transport $transport=null): bool
    {
        try { [$config,$profile]=$this->context($site); }
        catch(Problem $error) { if(in_array($error->reason,['ADDON_MISSING','PURCHASE_CONNECTION_REQUIRED'],true)) return false; throw $error; }
        if(empty($config['enabled']) || empty($config['purchase_enabled']) || !empty($config['paused']) || empty($profile['probe_at'])) return false;
        $lease=bin2hex(random_bytes(16)); $claimed=false; $task=null; $request=[];
        try {
            Db::transaction(function() use($site,$id,$lease,&$claimed,&$task,&$request,&$config,&$profile): void {
                $this->lockSite($site); [$config,$profile]=$this->context($site,true); $task=$this->task($site,$id,true);
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
                if(!$task['query_only']) {
                    $this->assertCurrentSource($site,Contract::decode($task['snapshot_json']),true);
                    // Revalidate the exact frozen choices; never silently refresh from mappings.
                    $expected=$this->prepare($site,$task,$request['selection']??[],true);
                    if(Contract::json($expected)!==Contract::json($request)) throw new Problem('REQUEST_CONFLICT','冻结请求与原事实不一致，已停止发送');
                }
                Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'processing','lease_token'=>$lease,'lease_until'=>time()+90,
                    'attempts'=>(int)$task['attempts']+1,'side_effect'=>'unknown','update_at'=>time()]);
                $claimed=true;
            });
            if(!$claimed) return false;
            $owned=[['site_id','=',$site],['id','=',$id],['mode','=',$config['mode']],['connection_key','=',$profile['connection_key']],['status','=','processing'],['lease_token','=',$lease]];
            // The durable unknown side effect above precedes transport creation and the network call.
            [$latest,$latestProfile]=$this->context($site,true);
            if($latest['mode']!==$config['mode'] || $latestProfile!==$profile) throw new Problem('CONNECTION_CHANGED','任务领取后连接配置发生变化，已停止外发，请刷新核对','unknown');
            $gatewayTransport=$transport??(new ConfigService())->transport($site,$profile,$config['mode']);
            if(!$gatewayTransport instanceof OpenApi) throw new Problem('PURCHASE_PROTOCOL_INVALID','采购创建仅允许官方开放平台连接','unknown');
            $gateway=new PurchaseGateway($gatewayTransport); $prior=Contract::decode($task['result_json']);
            $reply=$task['query_only'] ? $gateway->verify($request+(!empty($prior['external_id'])?['external_id'=>$prior['external_id']]:[]),$task['external_no']) : $gateway->create($request);
            $result=$this->safeResult($reply);
            if(($task['query_only'] && ($reply['verified']??false)!==true) || (!$task['query_only'] && ($reply['created']??false)!==true)) throw new Problem('PURCHASE_RESULT_INVALID','博远采购回执未确认预期阶段，请人工核对','unknown');
            $number=(string)($reply['external_no']??''); $externalId=(string)($reply['external_id']??''); Contract::id($number); Contract::id($externalId);
            if($task['query_only'] && ($number!==$task['external_no'] || ($reply['evidence']['company_id']??'')!==($request['selection']['company_id']??''))) throw new Problem('PURCHASE_RESULT_IDENTITY','核验单号或公司与冻结选择不一致，不能完成','unknown');
            Db::transaction(function() use($site,$id,$owned,$task,$request,$result,$number,$externalId): void {
                $this->lockSite($site); $row=Db::name('hsx_boyuan_task')->where($owned)->lock(true)->find(); if(!$row) return;
                $invalidated=str_starts_with((string)$row['resolution'],self::INVALIDATED); $sourceIssue='';
                try { $this->assertCurrentSource($site,Contract::decode($row['snapshot_json']),true); }
                catch(Problem $error) { $invalidated=true; $sourceIssue=$error->getMessage(); }
                $autoVerify=!$task['query_only'] && !$invalidated && ($request['auto_verify']??false)===true;
                $status=$invalidated?'attention':($task['query_only']?'succeeded':($autoVerify?'confirming':'created_review'));
                $reason=$invalidated?'原采购已撤销或事实变化，博远核验回执已保留；请人工核对原单，不会自动冲销或重建'.($sourceIssue!==''?'；'.$sourceIssue:''):($task['query_only']?'采购单及入库串号记录匹配；未核验实时库存余额、未登记或核验付款':($autoVerify?'已取得原采购单号，自动排队只读核验采购及入库串号事实；不会重新创建':'博远已返回采购单号，尚待核验采购及入库串号事实'));
                if($task['query_only'] && !$invalidated) $this->bindings($row,$request,$result,$number,$externalId);
                Db::name('hsx_boyuan_task')->where($owned)->update(['status'=>$status,'side_effect'=>$task['query_only'] && !$invalidated?'applied':'unknown','external_no'=>$number,'result_json'=>Contract::json($result),
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
            Db::name('hsx_boyuan_task')->where($where)->update(['status'=>$status,'reason'=>mb_substr($reason,0,1000),'next_at'=>0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
            TaskService::audit($site,$id,(string)$task['connection_key'],'purchase_process',$status,$reason,0,['code'=>$error instanceof Problem?$error->reason:'LOCAL_ERROR']);
            return true;
        }
        return true;
    }

    private function prepare(int $site,array $task,array $selection,bool $complete): array
    {
        $snapshot=Contract::decode($task['snapshot_json']); $this->facts($snapshot); $selection=$this->selection($snapshot,$selection); $key=$task['connection_key'];
        // Captured execution intent is immutable. Switching today's config must not change it.
        $stored=Contract::decode($task['request_json']); $autoVerify=$stored['auto_verify']??false;
        if(!is_bool($autoVerify)) throw new Problem('PURCHASE_INTENT_INVALID','任务自动核验意图无效，已停止提交或外发');
        $intent=array_key_exists('auto_verify',$stored)?['auto_verify'=>$autoVerify]:[];
        $company=$this->archive($site,$key,'companies',$selection['company_id'],$complete);
        $supplier=$this->archive($site,$key,'parties',$selection['supplier_id'],$complete,'supplier');
        $creator=$this->archive($site,$key,'staff',$selection['creator_id'],$complete);
        if($creator && (!is_string($creator['name']) || $creator['name']==='' || strlen($creator['name'])>64 || preg_match('/[\x00-\x1f\x7f]/',$creator['name']))) throw new Problem('CREATOR_NAME_INVALID','所选博远制单人名称缺失或超过官方64字节限制，请核对档案');
        $buyer=$this->archive($site,$key,'staff',$selection['buyer_id'],false,'buyer'); $lines=[]; $details=[]; $warehouses=[];
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
        if($buyer) $head['sales_id']=$buyer['external_id'];
        return ['schema'=>self::SCHEMA,'stage'=>'frozen','third_no'=>$third,'selection'=>$selection,'biz'=>['head'=>$head,'product_details'=>['add_details'=>$details]],'lines'=>$lines]+$intent;
    }

    private function archive(int $site,string $key,string $resource,string $id,bool $required,string $role=''): ?array
    {
        if($id==='') { if($required) throw new Problem('PURCHASE_SELECTION_REQUIRED','请完整选择公司、供应商、制单人及每台设备的商品和仓库'); return null; }
        Contract::id($id); if(strlen($id)>36) throw new Problem('ARCHIVE_ID_INVALID','博远档案编号超过官方字段长度');
        $run=ReferenceService::latestRun($site,$key,$resource);
        $row=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$key)->where('resource',$resource)->where('external_id',$id)->where('enabled',1)->find();
        if(!$row || $run==='' || $row['seen_run']!==$run) throw new Problem('ARCHIVE_STALE','所选资料不在当前连接最近完整同步的启用档案中，请重新同步并核对');
        $row['extra']=Contract::decode($row['extra_json']);
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
        if(!in_array($extra['imei_count']??null,[1,'1'],true)) throw new Problem('PRODUCT_SERIAL_UNSUPPORTED','所选商品未明确为单串号商品；首版不支持无串号或多串号商品');
        $length=$extra['imei_len']??null;
        if(!is_numeric($length) || (int)$length<1 || (int)$length>64 || (string)(int)$length!==(string)$length || strlen($source['imei'])!==(int)$length) throw new Problem('PRODUCT_SERIAL_LENGTH','IMEI长度与所选博远商品规则不符，请核对商品对应');
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
        if((string)$head['purchase_no']!==$snapshot['source_document_no'] || 'party:'.$head['party_id']!==$snapshot['party']['ref'] || (int)$head['purchase_at']!==$snapshot['occurred_at']) throw new Problem('PURCHASE_SOURCE_CHANGED','原采购的单号、供应商或业务日期已变化，请重新核对；原快照不会被覆盖');
        foreach($snapshot['lines'] as $line) {
            $row=Db::name('erp_purchase_item')->where('site_id',$site)->where('purchase_order_id',$id)->where('id',(int)substr($line['source_line_id'],14))->lock($lock)->find();
            $asset=Db::name('erp_asset')->where('site_id',$site)->where('id',(int)substr($line['device_ref'],6))->lock($lock)->find();
            if(!$row || !$asset || 'asset:'.$row['asset_id']!==$line['device_ref']) throw new Problem('PURCHASE_SOURCE_CHANGED','原采购设备行或资产身份已变化，不能发送');
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

    private function defaults(int $site,string $key,array $snapshot): array
    {
        $maps=[]; foreach(Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$key)->select()->toArray() as $map) $maps[$map['kind'].':'.$map['local_ref']]=$map['external_id'];
        $selection=['company_id'=>'','supplier_id'=>(string)($maps['supplier:'.($snapshot['party']['ref']??'')]??''),'creator_id'=>(string)($maps['staff:'.($snapshot['operator']['operator_ref']??'')]??''),'buyer_id'=>'','lines'=>[]]; $companies=[];
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',(int)substr((string)$snapshot['source_document_id'],9))->find();
        if((int)($head['purchaser_uid']??0)>0) $selection['buyer_id']=(string)($maps['staff:staff:'.$head['purchaser_uid']]??'');
        foreach($snapshot['lines'] as $line) {
            $warehouse=(string)($maps['warehouse:'.$line['warehouse_ref']]??'');
            $selection['lines'][]=['source_line_id'=>$line['source_line_id'],'product_id'=>(string)($maps['product:'.$line['product_ref']]??''),'warehouse_id'=>$warehouse];
            if($warehouse!=='') {
                $row=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$key)->where('resource','warehouses')->where('external_id',$warehouse)->find();
                $company=(string)(Contract::decode($row['extra_json']??'{}')['company_id']??''); if($company!=='') $companies[$company]=true;
            }
        }
        if(count($companies)===1) $selection['company_id']=(string)array_key_first($companies);
        return $selection;
    }

    private function remember(int $site,array $task,array $selection,array $snapshot,int $uid): void
    {
        $items=[['supplier',$snapshot['party']['ref'],$snapshot['party']['name'],$selection['supplier_id'],'parties']];
        if($selection['creator_id']!=='' && preg_match('/^staff:[1-9][0-9]*$/D',(string)($snapshot['operator']['operator_ref']??''))) $items[]=['staff',$snapshot['operator']['operator_ref'],(string)($snapshot['operator']['name']??''),$selection['creator_id'],'staff'];
        $head=Db::name('erp_purchase_order')->where('site_id',$site)->where('id',(int)substr($snapshot['source_document_id'],9))->find();
        if($selection['buyer_id']!=='' && (int)($head['purchaser_uid']??0)>0) $items[]=['staff','staff:'.$head['purchaser_uid'],(string)($head['purchaser_name']??''),$selection['buyer_id'],'staff'];
        foreach($snapshot['lines'] as $index=>$line) { $items[]=['product',$line['product_ref'],$line['product_name'],$selection['lines'][$index]['product_id'],'products']; $items[]=['warehouse',$line['warehouse_ref'],$line['warehouse_ref'],$selection['lines'][$index]['warehouse_id'],'warehouses']; }
        $seen=[];
        foreach($items as [$kind,$local,$name,$external,$resource]) {
            if($external==='') continue; $identity=$kind.':'.$local;
            if(isset($seen[$identity]) && $seen[$identity]!==$external) throw new Problem('MAPPING_CONFLICT','同一本地资料选择了不同博远档案，不能记为全局对应'); $seen[$identity]=$external;
            // Creator and buyer are independent roles; role validation already ran in prepare().
            $archive=$this->archive($site,$task['connection_key'],$resource,$external,true,$kind==='supplier'?'supplier':'');
            $where=[['site_id','=',$site],['connection_key','=',$task['connection_key']],['kind','=',$kind],['local_ref','=',$local]];
            $old=Db::name('hsx_boyuan_mapping')->where($where)->lock(true)->find();
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
            if($id==='') return null; $row=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$key)->where('resource',$resource)->where('external_id',$id)->field('external_id,code,name')->find();
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
        if(isset($result['lines']) && is_array($result['lines'])) $safe['lines']=array_map(static fn($line)=>array_intersect_key((array)$line,array_flip(['source_line_id','device_ref','external_line_id','external_document_id','imei'])),array_slice($result['lines'],0,100));
        if(isset($result['evidence']) && is_array($result['evidence'])) $safe['evidence']=array_intersect_key($result['evidence'],array_flip(['purchase_detail_count','imei_detail_count','bill_type','business_date','company_id','gsdm','request_third_no','created_head_id_matched','document_head_id_verified','response_contract','external_line_id_source','supplier_id','warehouse_id','record_is_effective','inventory_evidence','limitations']));
        return $safe;
    }
}
