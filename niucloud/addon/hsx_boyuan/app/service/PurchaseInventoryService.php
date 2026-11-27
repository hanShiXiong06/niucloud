<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\gateway\{InventoryGateway,OpenApi,Transport};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

/** Independent, bounded read-only stock queries; never sends a purchase or payment command. */
final class PurchaseInventoryService
{
    public const SCHEMA='boyuan.purchase.inventory.v1';
    public const KIND='purchase_inventory_verify';
    public const MAX_AGE=300;
    private const MAX_ATTEMPTS=3;

    public function queue(int $site,int $parentId,int $uid,array $input,bool $interactive=false,?Transport $transport=null): array
    {
        if(array_diff(array_keys($input),['revision','mode','connection_key'])) throw new Problem('INVENTORY_INPUT_INVALID','库存核验只接收原采购版本及连接身份，不接收修改设备或库存的参数');
        $queuedId=0; $guard=null;
        Db::transaction(function() use($site,$parentId,$uid,$input,$interactive,&$guard,&$queuedId): void {
            $this->lockSite($site); [$config,$profile]=$this->context($site,true); $parent=$this->parent($site,$parentId,true);
            if(!is_int($input['revision']??null) || $input['revision']!==(int)$parent['revision']) throw new Problem('TASK_STALE','原采购任务已更新，请刷新后核验库存');
            if(($input['mode']??null)!==$parent['mode'] || ($input['connection_key']??null)!==$parent['connection_key']) throw new Problem('CONNECTION_CHANGED','原采购连接身份已变化，请刷新');
            $request=$this->request($site,$parent,$config,$profile); $fact=$this->fact($request);
            if($interactive && ManualDocumentCommandService::eligible($config)) $guard=['mode'=>$parent['mode'],'connection_key'=>$parent['connection_key'],
                'config_revision'=>(int)$config['revision'],'parent'=>$parentId,'fact'=>$fact];
            $where=[['site_id','=',$site],['connection_key','=',$parent['connection_key']],['fact_key','=',$fact]];
            $existing=Db::name('hsx_boyuan_task')->where($where)->lock(true)->find();
            if($existing) {
                if($existing['request_json']!==Contract::json($request)) throw new Problem('INVENTORY_FACT_CONFLICT','既有库存核验冻结身份冲突，未覆盖原请求');
                if(in_array($existing['status'],['pending','processing','confirming'],true) || $existing['status']==='succeeded' && $this->fresh(Contract::decode($existing['result_json']),$request)) {
                    if($guard!==null && in_array($existing['status'],['pending','confirming'],true)) { $queuedId=(int)$existing['id']; $guard['revision']=(int)$existing['revision']; }
                    return;
                }
                if($guard!==null) $guard['revision']=(int)$existing['revision']+1;
                Db::name('hsx_boyuan_task')->where($where)->update(['status'=>'pending','reason'=>'已重新排队只读核验本批次当前在库，不重建采购','attempts'=>0,'query_only'=>1,'side_effect'=>'none',
                    'result_json'=>'{}','revision'=>(int)$existing['revision']+1,'next_at'=>time()+($guard===null?1:0),'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
                $queuedId=(int)$existing['id'];
                TaskService::audit($site,(int)$existing['id'],$parent['connection_key'],'inventory_queue','pending','重新排队本批次当前在库只读核验',$uid); return;
            }
            $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'connection_key'=>$parent['connection_key'],'mode'=>$parent['mode'],'kind'=>self::KIND,
                'fact_key'=>$fact,'event_id'=>'inventory:'.$parentId.':'.$request['query_date'],'operation_id'=>'BI_'.hash('sha256',$site.':'.$parent['connection_key'].':'.$fact),
                'business_no'=>$parent['business_no'],'status'=>'pending','reason'=>'本批次当前在库只读核验已排队，不重建采购、不调整本站库存或款项','query_only'=>1,'side_effect'=>'none',
                'snapshot_json'=>Contract::json($request),'request_json'=>Contract::json($request),'result_json'=>'{}','next_at'=>time()+($guard===null?1:0),'create_at'=>time(),'update_at'=>time()]);
            if($guard!==null) $guard['revision']=1;
            TaskService::audit($site,$id,$parent['connection_key'],'inventory_queue','pending','本批次当前在库只读核验已排队',$uid);
            $queuedId=$id;
        });
        if($queuedId>0) {
            if($guard!==null) $this->process($site,$queuedId,$transport,$guard);
            else $this->dispatchLater($site,$queuedId,1);
        }
        return (new PurchaseSyncService())->view($site,$parentId);
    }

    public function summary(int $site,array $parent): array
    {
        $summary=['task_id'=>null,'status'=>'not_checked','query_date'=>null,'checked_at'=>null,'expires_at'=>null,'total_count'=>null,'completed_count'=>null,'in_stock_count'=>null,
            'reason'=>'请先完成原采购逐台记录核验，再只读核对本批次当前在库','can_check'=>false];
        if((int)($parent['site_id']??0)!==$site || ($parent['kind']??'')!=='purchase') return array_replace($summary,['status'=>'unavailable','reason'=>'库存核验来源不属于本站采购']);
        try {
            [$config,$profile]=$this->context($site,false);
            $snapshot=Contract::decode($parent['snapshot_json']); (new PurchaseSyncService())->assertClosureSourceCurrent($site,$snapshot);
            if($parent['mode']!==$config['mode'] || $parent['connection_key']!==$profile['connection_key']) return array_replace($summary,['status'=>'stale','reason'=>'当前连接已改变，原连接库存证据不代表当前连接库存']);
            if(!$this->purchaseVerified($parent)) return $summary;
            $request=$this->request($site,$parent,$config,$profile); $summary['total_count']=count($request['lines']);
            $summary['can_check']=!empty($config['enabled']) && !empty($config['purchase_enabled']) && empty($config['paused']) && !empty($profile['probe_at']);
            $summary['reason']=$summary['can_check']?'尚未核验本批次当前在库；只读查询不会重建采购或调整库存':'当前连接未启用或已暂停，只读核验尚未排队';
            $child=null;
            foreach(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$parent['connection_key'])->where('fact_key','like','inventory:'.(int)$parent['id'].':%')->where('mode',$parent['mode'])->where('kind',self::KIND)->order('id desc')->select()->toArray() as $candidate) {
                $captured=Contract::decode($candidate['request_json']);
                if(($captured['schema']??'')===self::SCHEMA && ($captured['source_task_id']??0)===(int)$parent['id']
                    && ($captured['source_snapshot_hash']??'')===$request['source_snapshot_hash'] && ($captured['source_request_hash']??'')===$request['source_request_hash']) { $child=$candidate; break; }
            }
            if(!$child) return $summary;
            $captured=Contract::decode($child['request_json']); $result=Contract::decode($child['result_json']);
            $summary['task_id']=(int)$child['id']; $summary['query_date']=$captured['query_date']??null;
            $summary['checked_at']=is_int($result['checked_at']??null)?$result['checked_at']:null;
            $summary['expires_at']=$summary['checked_at']!==null?$summary['checked_at']+self::MAX_AGE:null;
            $summary['completed_count']=is_int($result['progress']['completed_count']??null)?$result['progress']['completed_count']:0;
            if(($captured['query_date']??'')!==$request['query_date'] || ($captured['company_code']??'')!==$request['company_code'] || ($captured['company_id']??'')!==$request['company_id']) return array_replace($summary,['status'=>'stale','total_count'=>null,'completed_count'=>null,'reason'=>'原核验日期或公司代码已变化，请显式重新核对本批次当前在库']);
            if(in_array($child['status'],['pending','confirming','processing'],true)) {
                $canContinue=$summary['can_check'] && ManualDocumentCommandService::eligible($config) && $child['status']!=='processing' && (bool)$child['query_only']
                    && (int)$child['next_at']<=time();
                return array_replace($summary,['status'=>$child['status']==='processing'?'processing':'pending','can_check'=>$canContinue,'can_process_current'=>$canContinue,
                    'reason'=>$canContinue?'可继续核对本批剩余设备，只查询库存，不重建采购。':'本批次当前在库只读核验已排队或正在查询，请刷新结果；不会重建采购']);
            }
            if($child['status']==='succeeded') {
                if(!$this->fresh($result,$captured) || $captured['query_date']!==$request['query_date']) return array_replace($summary,['status'=>'stale','reason'=>'上次库存证据已超过有效期或不是今天；请重新核对本批次当前在库']);
                $this->receipt($result,$captured);
                return array_replace($summary,['status'=>'succeeded','can_check'=>false,'completed_count'=>$result['in_stock_count'],'in_stock_count'=>$result['in_stock_count'],'reason'=>'本批次逐台串号已在查询时点匹配所选公司、商品、仓库及在库状态；不是全仓余额核验']);
            }
            return array_replace($summary,['status'=>'failed','reason'=>$child['reason']!==''?$child['reason']:'库存核验未取得完整证据；没有把失败当作零库存']);
        } catch(Problem $error) { return array_replace($summary,['status'=>'unavailable','total_count'=>null,'in_stock_count'=>null,'can_check'=>false,'reason'=>$error->getMessage()]); }
        catch(\Throwable $error) {
            $this->log($site,(int)($parent['id']??0),$error);
            return array_replace($summary,['status'=>'unavailable','total_count'=>null,'in_stock_count'=>null,'can_check'=>false,'reason'=>'库存核验状态暂不可用，未取得数量；请刷新核查']);
        }
    }

    public function process(int $site,int $id,?Transport $transport=null,?array $guard=null): bool
    {
        try { [$config,$profile]=$this->context($site,true); } catch(Problem $error) { return false; }
        $lease=bin2hex(random_bytes(16)); $task=null; $request=[]; $claimed=false;
        try {
            Db::transaction(function() use($site,$id,$lease,&$config,&$profile,&$task,&$request,&$claimed,$guard): void {
                $this->lockSite($site); [$config,$profile]=$this->context($site,true);
                $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->lock(true)->find();
                if(!$task || $task['kind']!==self::KIND || $task['connection_key']!==$profile['connection_key'] || $task['mode']!==$config['mode'] || (int)$task['next_at']>time()) return;
                if($guard!==null && (!ManualDocumentCommandService::sameConfig($config,$guard) || $task['fact_key']!==$guard['fact'] || (int)$task['revision']!==$guard['revision']
                    || !in_array($task['status'],['pending','confirming'],true) || !$task['query_only'] || (Contract::decode($task['request_json'])['source_task_id']??null)!==$guard['parent'])) return;
                $stale=$task['status']==='processing' && (int)$task['lease_until']<time();
                if(!in_array($task['status'],['pending','confirming'],true) && !$stale) return;
                $request=Contract::decode($task['request_json']);
                $parent=$this->parent($site,(int)($request['source_task_id']??0),true);
                if(Contract::json($this->request($site,$parent,$config,$profile))!==$task['request_json'] || $task['snapshot_json']!==$task['request_json'] || !(int)$task['query_only']) throw new Problem('INVENTORY_SOURCE_CHANGED','库存核验冻结来源已变化或不是今天，请从原采购页重新排队');
                Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'processing','lease_token'=>$lease,'lease_until'=>time()+90,'attempts'=>(int)$task['attempts']+1,'update_at'=>time()]);
                $claimed=true;
            });
            if(!$claimed) return false;
            [$latest,$latestProfile]=$this->context($site,true);
            if($latest['mode']!==$config['mode'] || $latestProfile!==$profile || ($guard!==null && !ManualDocumentCommandService::sameConfig($latest,$guard))) throw new Problem('CONNECTION_CHANGED','查询前连接已变化，请从原采购页重新排队');
            $api=$transport??(new ConfigService())->transport($site,$profile,$config['mode'],\addon\hsx_boyuan\app\support\InterfaceTrace::task($task));
            if(!$api instanceof OpenApi) throw new Problem('INVENTORY_PROTOCOL_INVALID','当前在库核验仅允许官方开放平台只读接口');
            $gateway=new InventoryGateway($api); $prior=Contract::decode($task['result_json']); $steps=(array)($prior['steps']??[]); $offset=0;
            foreach($steps as $step) {
                if(($step['offset']??null)!==$offset || !is_int($step['next_offset']??null)) throw new Problem('INVENTORY_PROGRESS_INVALID','库存分步进度不连续，请从原采购页重新排队');
                if(!is_int($step['checked_at']??null) || $step['checked_at']<time()-self::MAX_AGE) throw new Problem('INVENTORY_EVIDENCE_STALE','先前分步库存证据已过期，请显式重新核验，不继续沿用旧证据');
                $offset=$step['next_offset'];
            }
            $gatewayRequest=$this->gatewayRequest($request);
            $step=$this->step($gateway->verifyStep($gatewayRequest,$offset,3),$request,$offset); $steps[]=$step;
            $complete=$step['next_offset']===null;
            $result=$complete?$this->receipt($gateway->complete($gatewayRequest,$steps),$request):['progress'=>['completed_count'=>$step['next_offset'],'total_count'=>count($request['lines'])],'steps'=>$steps];
            $owned=$this->owned($site,$id,$task,$lease);
            Db::transaction(function() use($site,$id,$task,$request,$result,$owned,$complete): void {
                $this->lockSite($site); $row=Db::name('hsx_boyuan_task')->where($owned)->lock(true)->find(); if(!$row) return;
                [$config,$profile]=$this->context($site,true); $parent=$this->parent($site,(int)$request['source_task_id'],true);
                if(Contract::json($this->request($site,$parent,$config,$profile))!==$task['request_json']) throw new Problem('INVENTORY_SOURCE_CHANGED','查询期间原采购、连接或日期已变化，结果不能作为当前在库证据');
                Db::name('hsx_boyuan_task')->where($owned)->update(['status'=>$complete?'succeeded':'confirming','side_effect'=>'none','result_json'=>Contract::json($result),'attempts'=>0,
                    'reason'=>$complete?'本批次当前在库逐台只读核验完成；未调整本站库存、采购或款项':'已核对本批次'.$result['progress']['completed_count'].'/'.count($request['lines']).'台，等待下一轮只读核验',
                    'revision'=>(int)$row['revision']+1,'next_at'=>$complete?0:time()+1,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
                TaskService::audit($site,$id,$task['connection_key'],'inventory_result',$complete?'succeeded':'confirming',$complete?'本批次当前在库逐台只读核验完成':'本批次当前在库分步进度已保存');
            });
            if(!$complete && $guard===null) $this->dispatchLater($site,$id,1);
        } catch(\Throwable $error) {
            if(!$task || $task['kind']!==self::KIND) throw $error;
            $attempt=(int)$task['attempts']+($claimed?1:0);
            $retry=$guard===null && $claimed && $attempt<self::MAX_ATTEMPTS && $this->transient($error);
            $where=$claimed?$this->owned($site,$id,$task,$lease):[['site_id','=',$site],['id','=',$id],['mode','=',$task['mode']],['connection_key','=',$task['connection_key']],['revision','=',(int)$task['revision']],['status','=',$task['status']],['lease_token','=',$task['lease_token']],['lease_until','=',(int)$task['lease_until']],['attempts','=',(int)$task['attempts']]];
            $reason=$error instanceof Problem?$error->getMessage():'库存只读查询暂未取得完整结果，请刷新或稍后重新核验';
            Db::name('hsx_boyuan_task')->where($where)->update(['status'=>$retry?'confirming':'attention','side_effect'=>'none','reason'=>mb_substr($reason,0,1000),'next_at'=>$retry?time()+30:0,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
            TaskService::audit($site,$id,$task['connection_key'],'inventory_query',$retry?'confirming':'attention',$reason,0,['code'=>$error instanceof Problem?$error->reason:'LOCAL_ERROR']);
            if(!$error instanceof Problem) $this->log($site,$id,$error);
            if($retry) $this->dispatchLater($site,$id,30);
            return true;
        }
        return true;
    }

    private function request(int $site,array $parent,array $config,array $profile): array
    {
        if($parent['mode']!==$config['mode'] || $parent['connection_key']!==$profile['connection_key']) throw new Problem('CONNECTION_CHANGED','原采购不属于当前连接，不能核验库存');
        if(!$this->purchaseVerified($parent)) throw new Problem('INVENTORY_DEPENDENCY_REQUIRED','原采购尚未完成逐台记录核验，不能核对本批次当前在库');
        $snapshot=Contract::decode($parent['snapshot_json']); (new PurchaseSyncService())->assertClosureSourceCurrent($site,$snapshot);
        $purchase=Contract::decode($parent['request_json']); $companyId=(string)($purchase['selection']['company_id']??'');
        $archive=CatalogStore::query('companies')->where('site_id',$site)->where('connection_key',$parent['connection_key'])->where('external_id',$companyId)->where('enabled',1)->find();
        $run=ReferenceService::latestRun($site,$parent['connection_key'],'companies');
        if(!$archive || $run==='' || $archive['seen_run']!==$run) throw new Problem('INVENTORY_COMPANY_STALE','所选公司未在本连接最近完整同步中确认，请先同步公司档案');
        $extra=Contract::decode($archive['extra_json']); $code=$extra['gsdm']??null;
        if(!is_string($code) || $code==='' || strlen($code)>36 || preg_match('/[\x00-\x20,]/',$code)) throw new Problem('INVENTORY_COMPANY_CODE_REQUIRED','公司档案缺少已确认的gsdm公司代码，未猜测账套或公司ID代替');
        $lines=[];
        foreach($purchase['lines']??[] as $line) $lines[]=array_intersect_key($line,array_flip(['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity']));
        if(!$lines || count($lines)!==count($snapshot['lines'])) throw new Problem('INVENTORY_SOURCE_INCOMPLETE','原采购冻结逐台清单不完整');
        return ['schema'=>self::SCHEMA,'stage'=>'frozen','source_task_id'=>(int)$parent['id'],'source_snapshot_hash'=>hash('sha256',$parent['snapshot_json']),
            'source_request_hash'=>hash('sha256',$parent['request_json']),'connection_key'=>$parent['connection_key'],'mode'=>$parent['mode'],'company_id'=>$companyId,'company_code'=>$code,'query_date'=>$this->today(),'lines'=>$lines];
    }
    private function receipt(array $result,array $request): array
    {
        if(($result['verified']??false)!==true || ($result['current_stock_verified']??false)!==true || ($result['verification_level']??'')!=='batch_current_stock_records_matched'
            || ($result['query_date']??'')!==$request['query_date'] || !is_int($result['checked_at']??null) || $result['checked_at']>time()+5 || $result['checked_at']<time()-self::MAX_AGE
            || ($result['in_stock_count']??null)!==count($request['lines']) || !is_array($result['matched_lines']??null) || count($result['matched_lines'])!==count($request['lines'])) throw new Problem('INVENTORY_EVIDENCE_INVALID','库存回执缺少本批次完整、当前的逐台证据，未推断数量');
        $expected=array_column($request['lines'],null,'source_line_id'); $seen=[]; $lines=[];
        foreach($result['matched_lines'] as $line) {
            $ref=$line['source_line_id']??''; $original=$expected[$ref]??null;
            if(!$original || isset($seen[$ref]) || ($line['imei_in_stock_status']??null)!==1) throw new Problem('INVENTORY_EVIDENCE_MISMATCH','库存回执设备重复、缺失或不是明确在库状态');
            foreach(['device_ref','imei','product_id','warehouse_id','supplier_id','quantity'] as $key) if(($line[$key]??null)!==($original[$key]??null)) throw new Problem('INVENTORY_EVIDENCE_MISMATCH','库存回执与冻结设备、商品、仓库或供应商不一致');
            $seen[$ref]=true; $lines[]=array_intersect_key($line,array_flip(['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity','imei_in_stock_status']));
        }
        $evidence=$result['evidence']??[];
        if(($evidence['scope']??'')!=='purchase_batch' || ($evidence['company_id']??'')!==$request['company_id'] || ($evidence['company_code']??'')!==$request['company_code']
            || ($evidence['source_task_id']??null)!==$request['source_task_id'] || ($evidence['source_snapshot_hash']??'')!==$request['source_snapshot_hash'] || ($evidence['device_count']??null)!==count($request['lines'])
            || ($evidence['aggregate_verified']??null)!==false || ($evidence['whole_warehouse_reconciled']??null)!==false || ($evidence['future_saleability_verified']??null)!==false || ($evidence['financial_posting_verified']??null)!==false) throw new Problem('INVENTORY_EVIDENCE_SCOPE','库存证据父任务、来源、公司或批次范围不一致，不能作为全仓余额');
        return ['verified'=>true,'current_stock_verified'=>true,'verification_level'=>'batch_current_stock_records_matched','query_date'=>$result['query_date'],'checked_at'=>$result['checked_at'],
            'in_stock_count'=>$result['in_stock_count'],'matched_lines'=>$lines,'evidence'=>array_intersect_key($evidence,array_flip(['response_contract','scope','company_id','company_code','device_count','aggregate_verified','source_task_id','source_snapshot_hash','scope_hash','checked_from','checked_until','evidence_ttl','whole_warehouse_reconciled','future_saleability_verified','financial_posting_verified','limitations']))];
    }
    private function step(array $step,array $request,int $offset): array
    {
        $expectedEnd=min(count($request['lines']),$offset+3); $next=$expectedEnd===count($request['lines'])?null:$expectedEnd;
        if(($step['step_verified']??null)!==true || ($step['verified']??null)!==false || ($step['current_stock_verified']??null)!==false || ($step['verification_level']??'')!=='batch_current_stock_step_matched' || ($step['matched_count']??null)!==$expectedEnd-$offset || ($step['offset']??null)!==$offset
            || ($step['total_count']??null)!==count($request['lines']) || !array_key_exists('next_offset',$step) || $step['next_offset']!==$next || ($step['query_date']??'')!==$request['query_date']
            || !is_string($step['scope_hash']??null) || !preg_match('/^[a-f0-9]{64}$/D',$step['scope_hash']) || !is_int($step['checked_at']??null) || $step['checked_at']>time()+5 || $step['checked_at']<time()-self::MAX_AGE
            || !is_array($step['matched_lines']??null) || count($step['matched_lines'])!==$expectedEnd-$offset) throw new Problem('INVENTORY_PROGRESS_INVALID','库存分步回执缺少连续且当前的只读证据');
        $safe=[];
        foreach($step['matched_lines'] as $index=>$line) {
            $original=$request['lines'][$offset+$index];
            foreach(['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity'] as $key) if(($line[$key]??null)!==($original[$key]??null)) throw new Problem('INVENTORY_PROGRESS_INVALID','库存分步回执逐台身份与冻结顺序不一致');
            if(($line['imei_in_stock_status']??null)!==1) throw new Problem('INVENTORY_PROGRESS_INVALID','库存分步回执不是明确在库状态');
            foreach(['company_id','company_code'] as $key) if(($line[$key]??null)!==$request[$key]) throw new Problem('INVENTORY_PROGRESS_INVALID','库存分步回执公司与冻结批次不一致');
            $safe[]=array_intersect_key($line,array_flip(['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity','qty','imei_in_stock_status','company_id','company_code']));
        }
        return array_intersect_key($step,array_flip(['scope_hash','offset','total_count','next_offset','checked_at','query_date','step_verified','verified','current_stock_verified','verification_level','matched_count']))+['matched_lines'=>$safe];
    }
    private function purchaseVerified(array $parent): bool
    {
        $result=Contract::decode($parent['result_json']); $request=Contract::decode($parent['request_json']);
        if($parent['status']!=='succeeded' || ($request['schema']??'')!=='boyuan.purchase.v154' || ($request['stage']??'')!=='frozen'
            || ($result['verified']??false)!==true || ($result['verification_level']??'')!=='purchase_imei_records_matched' || empty($result['lines']) || empty($parent['external_no']) || count($result['lines'])!==count($request['lines']??[])) return false;
        $expected=array_column($request['lines'],null,'source_line_id'); $seen=[];
        foreach($result['lines'] as $line) {
            $ref=$line['source_line_id']??''; $original=$expected[$ref]??null;
            if(!$original || isset($seen[$ref]) || ($line['device_ref']??null)!==$original['device_ref'] || ($line['imei']??null)!==$original['imei'] || !is_string($line['external_line_id']??null) || $line['external_line_id']==='') return false;
            $seen[$ref]=true;
        }
        return count($seen)===count($expected);
    }
    private function fresh(array $result,array $request): bool
    {
        return $request['query_date']===$this->today() && ($result['query_date']??'')===$request['query_date'] && is_int($result['checked_at']??null) && $result['checked_at']>=time()-self::MAX_AGE && $result['checked_at']<=time()+5;
    }
    private function context(int $site,bool $enabled): array
    {
        $service=new ConfigService(); if($site<=0 || !$service->installed($site)) throw new Problem('ADDON_MISSING','本站未安装ERP及博远插件');
        $config=$service->get($site,false); $profile=$config['profiles'][$config['mode']];
        if($config['mode']==='mock' || ($profile['protocol']??'')!=='openapi_v153' || empty($profile['connection_key'])) throw new Problem('INVENTORY_CONNECTION_REQUIRED','当前在库核验仅适用于官方开放平台真实连接');
        if($enabled && (empty($config['enabled']) || empty($config['purchase_enabled']) || !empty($config['paused']) || empty($profile['probe_at']))) throw new Problem('INVENTORY_PAUSED','连接未启用、已暂停或未通过账套核验，未排队或查询库存');
        return [$config,$profile];
    }
    private function parent(int $site,int $id,bool $lock=false): array
    {
        $row=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->lock($lock)->find();
        if(!$row || $row['kind']!=='purchase') throw new Problem('PURCHASE_TASK_MISSING','本站原采购任务不存在'); return $row;
    }
    private function lockSite(int $site): void { if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在'); }
    private function today(): string { return (new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d'); }
    private function fact(array $request): string { return 'inventory:'.$request['source_task_id'].':'.$request['query_date'].':'.substr(hash('sha256',$request['source_snapshot_hash'].':'.$request['source_request_hash'].':'.$request['company_id'].':'.$request['company_code']),0,32); }
    private function gatewayRequest(array $request): array { return array_intersect_key($request,array_flip(['schema','source_task_id','source_snapshot_hash','connection_key','company_id','company_code','query_date','lines'])); }
    private function owned(int $site,int $id,array $task,string $lease): array { return [['site_id','=',$site],['id','=',$id],['connection_key','=',$task['connection_key']],['mode','=',$task['mode']],['status','=','processing'],['lease_token','=',$lease]]; }
    private function transient(\Throwable $error): bool { return !$error instanceof Problem || preg_match('/^(?:OPENAPI_|REMOTE_|DNS_|TIMEOUT|NETWORK_)/',$error->reason)===1; }
    private function log(int $site,int $id,\Throwable $error): void { try { \think\facade\Log::warning('hsx_boyuan inventory read unavailable',['site_id'=>$site,'task_id'=>$id,'error_type'=>get_class($error)]); } catch(\Throwable $ignored) {} }
    private function dispatchLater(int $site,int $id,int $seconds): void
    {
        // Dispatch's queue-off fallback can execute synchronously only with secs=0. Never use it here.
        if(!function_exists('env') || !env('queue.state',false)) return;
        try { \addon\hsx_boyuan\app\job\InventoryProcess::dispatch('doJob',[$site,$id],max(1,$seconds)); }
        catch(\Throwable $error) { $this->log($site,$id,$error); } // Minute cron remains the durable compensation path.
    }
}
