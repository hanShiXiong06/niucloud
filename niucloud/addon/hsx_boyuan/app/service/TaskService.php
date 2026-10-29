<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\{Contract,Plan,Problem};
use addon\hsx_boyuan\app\gateway\Transport;
use think\facade\Db;
final class TaskService
{
    public static function audit(int $site,int $task,string $connection,string $action,string $outcome,string $message,int $uid=0,array $detail=[]): void
    {
        Db::name('hsx_boyuan_log')->insert(['site_id'=>$site,'task_id'=>$task,'connection_key'=>$connection,'operation_id'=>(string)($detail['operation_id']??''),
            'action'=>$action,'outcome'=>$outcome,'message'=>mb_substr($message,0,1000),'detail_json'=>Contract::json(Contract::redact($detail)),'operator_uid'=>$uid,'create_at'=>time()]);
    }
    public function accept(array $event): array
    {
        if (!in_array($event['event_name']??'',['erp.integration.document.confirmed.v1','erp.integration.document.invalidated.v1','erp.settlement.completed.v1'],true)) return [];
        $site=(int)($event['site_id']??0); $configService=new ConfigService();
        if ($site<=0 || !$configService->installed($site)) return [];
        $config=$configService->get($site,false); $p=$config['profiles'][$config['mode']];
        if (!$config['enabled'] || (int)($event['occurred_at']??0)<(int)$config['started_at']) return [];
        if (($event['event_version']??0)!==1 || empty($event['event_id'])) throw new Problem('EVENT_INVALID','ERP事件身份或版本无效');
        $snapshot=(array)($event['payload']??[]);
        if ($event['event_name']==='erp.integration.document.invalidated.v1') {
            $matched=false;
            foreach (Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$p['connection_key'])->column('snapshot_json') as $raw) if ((Contract::decode($raw)['source_document_id']??'')===($snapshot['original_document_ref']??'')) $matched=true;
            if (!$matched) return [];
        }
        if ($config['mode']==='mock') $snapshot['_mock_scenario']=$p['scenario'];
        if ($event['event_name']==='erp.settlement.completed.v1') {
            if (!$config['finance_enabled']) return [];
            $snapshot=$this->settlementSnapshot($snapshot,$event);
            if ($config['mode']==='mock') $snapshot['_mock_scenario']=$p['scenario'];
        }
        if (isset($snapshot['lines']) && $snapshot['kind']!=='purchase') {
            $enrolled=[];
            foreach (Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('kind','purchase')->column('snapshot_json') as $raw) {
                foreach (Contract::decode($raw)['lines']??[] as $line) $enrolled[$line['source_line_id']]=true;
            }
            foreach ($snapshot['lines'] as $line) if (!isset($enrolled[$line['inbound_line_ref']])) $snapshot['outside_scope']=true;
        }
        Db::transaction(function () use ($site,$config,$p,$snapshot,$event): void {
            $this->enqueue($site,$config['mode'],$p['connection_key'],$snapshot,$event['event_id']);
            if (isset($snapshot['party']) && empty($snapshot['outside_scope'])) {
                $role=in_array($snapshot['kind'],['purchase','purchase_return'],true)?'supplier':'customer';
                if (!Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('kind',$role)->where('local_ref',$snapshot['party']['ref'])->count()) $this->enqueue($site,$config['mode'],$p['connection_key'],['kind'=>'party_resolve','occurred_at'=>$snapshot['occurred_at'],
                    'source_document_id'=>'party:'.$role.':'.$snapshot['party']['ref'],'source_document_no'=>$snapshot['party']['name'],'batch_ref'=>'initial',
                    'data'=>['source_party_ref'=>$snapshot['party']['ref'],'role'=>$role,'mode'=>$role==='customer'?'match_or_create':'match_only',
                        'name'=>$snapshot['party']['name'],'mobile'=>$snapshot['party']['mobile']?:null]],$event['event_id']);
            }
            if ($config['finance_enabled'] && empty($snapshot['outside_scope']) && !empty($snapshot['financial_items'])) {
                $this->enqueue($site,$config['mode'],$p['connection_key'],['kind'=>'financial_item_ensure','occurred_at'=>$snapshot['occurred_at'],
                    'source_document_id'=>'finance:'.$snapshot['source_document_id'],'source_document_no'=>$snapshot['source_document_no'],'batch_ref'=>$snapshot['batch_ref'],'items'=>$snapshot['financial_items']],$event['event_id']);
            }
        });
        return ['consumer'=>'hsx_boyuan','status'=>'processed'];
    }
    public function enqueue(int $site,string $mode,string $connection,array $snapshot,string $event): int
    {
        $fact=($snapshot['source_document_id']??$snapshot['source_settlement_id']??'').':'.($snapshot['batch_ref']??'initial');
        if (strlen($fact)>160 || $fact===':initial') throw new Problem('FACT_INVALID','来源事实编号无效');
        $where=[['site_id','=',$site],['connection_key','=',$connection],['fact_key','=',$fact]];
        $existing=Db::name('hsx_boyuan_task')->where($where)->find();
        if ($existing) {
            if ($snapshot['kind']==='party_resolve') return (int)$existing['id'];
            if ($existing['snapshot_json']!==Contract::json($snapshot)) throw new Problem('FACT_CONFLICT','来源事实内容发生冲突，未覆盖原任务');
            return (int)$existing['id'];
        }
        $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'connection_key'=>$connection,'mode'=>$mode,'fact_key'=>$fact,'event_id'=>$event,
            'operation_id'=>'BY_'.hash('sha256',$site.':'.$connection.':'.$fact),'kind'=>$snapshot['kind'],'business_no'=>mb_substr((string)($snapshot['source_document_no']??$snapshot['source_settlement_no']??''),0,128),
            'snapshot_json'=>Contract::json($snapshot),'request_json'=>'{}','result_json'=>'{}','create_at'=>time(),'update_at'=>time(),'next_at'=>time()+1]);
        self::audit($site,$id,$connection,'receive','pending','业务事实已持久接收，等待异步处理'); return $id;
    }
    private function settlementSnapshot(array $payload,array $event): array
    {
        $direction=($payload['cash_direction']??'')==='out'?'payment':'receipt'; $allocations=[];
        foreach ($payload['targets']??[] as $target) {
            $side=$target['target_type']??$target['type']??'';
            $allocations[]=['source_financial_item_id'=>$side.':'.($target['target_id']??$target['id']??0),
                'source_document_id'=>(string)($target['source_document_ref']??''),'source_line_id'=>$target['source_line_ref']??null,
                'amount'=>number_format((float)($target['applied_amount']??$target['amount']??0),2,'.','')];
        }
        return ['kind'=>'settlement_register','source_settlement_id'=>'settlement:'.$payload['settlement_id'],'source_settlement_no'=>$payload['settlement_no'],
            'occurred_at'=>$payload['confirmed_at'],'party_ref'=>'party:'.$payload['party_id'],'party_role'=>$direction==='payment'?'supplier':'customer',
            'account_ref'=>'account:'.$payload['capital_account_id'],'method_ref'=>'method:'.($payload['capital_account_type']??''),'direction'=>$direction,
            'amount'=>number_format((float)$payload['amount'],2,'.',''),'operator'=>['operator_ref'=>'staff:'.($event['actor']['id']??0),'name'=>$event['actor']['name']??'财务'],
            'allocations'=>$allocations,'manual_required'=>array_filter($allocations,static fn($a)=>$a['source_document_id']==='')?'该退款或调整款项不属于本期自动记款契约，请财务在博远核验后记录处理结果':''];
    }
    private function maps(int $site,string $connection): array
    {
        $maps=[];
        foreach (Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$connection)->select()->toArray() as $map) {
            $map['extra']=Contract::decode($map['extra_json']); $maps[$map['kind'].':'.$map['local_ref']]=$map;
        }
        return $maps;
    }
    private function bindings(int $site,string $connection): array
    {
        $items=[];
        foreach (Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('connection_key',$connection)->select()->toArray() as $b) {
            $b['detail']=Contract::decode($b['detail_json']); $items[$b['kind'].':'.$b['local_ref']]=$b;
        }
        return $items;
    }
    public function process(int $site,int $id,?Transport $transport=null): bool
    {
        $configService=new ConfigService(); if (!$configService->installed($site)) return false;
        $config=$configService->get($site,false); $profile=$config['profiles'][$config['mode']];
        if (!$config['enabled'] || $config['paused']) return false;
        $base=[['site_id','=',$site],['id','=',$id],['connection_key','=',$profile['connection_key']],['mode','=',$config['mode']]];
        if (!$config['finance_enabled']) {
            $candidate=Db::name('hsx_boyuan_task')->where($base)->field('kind,query_only')->find();
            if ($candidate && in_array($candidate['kind'],['financial_item_ensure','settlement_register'],true) && !$candidate['query_only']) return false;
        }
        $lease=bin2hex(random_bytes(16));
        $claimed=Db::name('hsx_boyuan_task')->where($base)->where('next_at','<=',time())->where(function ($q): void {
            $q->whereIn('status',['pending','retry','waiting','confirming'])->whereOr(function ($stale): void { $stale->where('status','processing')->where('lease_until','<',time()); });
        })->update(['status'=>'processing','lease_token'=>$lease,'lease_until'=>time()+90,'update_at'=>time()]);
        if ($claimed!==1) return false;
        $owned=array_merge($base,[['lease_token','=',$lease],['status','=','processing']]);
        $task=Db::name('hsx_boyuan_task')->where($owned)->find();
        $captured=Contract::decode($task['snapshot_json']);
        if ($task['mode']==='mock') $profile['scenario']=$captured['_mock_scenario']??'success';
        $previousAttempts=(int)$task['attempts']; $task['attempts']=$previousAttempts+1;
        Db::name('hsx_boyuan_task')->where($owned)->update(['attempts'=>$task['attempts']]);
        try {
            $request=Contract::decode($task['request_json']);
            if (!$request) {
                $snapshot=Contract::decode($task['snapshot_json']);
                if (!empty($snapshot['manual_required'])) throw new Problem('MANUAL_REVIEW',$snapshot['manual_required']);
                if (isset($snapshot['party']) && empty($snapshot['outside_scope'])) {
                    $role=in_array($snapshot['kind'],['purchase','purchase_return'],true)?'supplier':'customer';
                    $child=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$profile['connection_key'])->where('fact_key','party:'.$role.':'.$snapshot['party']['ref'].':initial')->find();
                    if ($child && in_array($child['status'],['pending','processing','waiting','confirming'],true)) throw new Problem('DEPENDENCY_PENDING','正在核对往来单位，完成后将自动继续');
                }
                if ($snapshot['kind']==='settlement_register') $this->refreshBalances($task,$snapshot,$profile,$transport??$configService->transport($site,$profile,$config['mode']));
                $request=Plan::build($snapshot,$this->maps($site,$profile['connection_key']),$this->bindings($site,$profile['connection_key']),$task['operation_id']);
                Contract::request($request);
                Db::name('hsx_boyuan_task')->where($owned)->update(['request_json'=>Contract::json($request)]);
            }
            $fresh=$configService->get($site,false);
            if ($fresh['paused'] || !$fresh['enabled'] || $fresh['mode']!==$config['mode'] || $fresh['profiles'][$fresh['mode']]['connection_key']!==$profile['connection_key']) throw new Problem('MODE_PAUSED','连接已暂停或切换，任务仍保留在原环境');
            if (!$fresh['finance_enabled'] && in_array($request['operation_type'],['financial_item_ensure','settlement_register'],true) && !$task['query_only']) throw new Problem('FINANCE_PAUSED','财务同步已关闭，款项任务保留且未继续写入');
            $gateway=$transport??$configService->transport($site,$profile,$config['mode']);
            $reply=null;
            if ($task['side_effect']!=='none' || $previousAttempts>0) {
                $reply=$gateway->send('GET',$profile['paths']['operations'].'/'.$task['operation_id']);
                if (($reply['code']??'')==='OPERATION_NOT_FOUND') {
                    if ($task['query_only']) throw new Problem('OPERATION_NOT_FOUND','尚未找到原操作，未重新发送；请核验人工单号或服务状态','unknown');
                    $reply=null;
                }
            }
            if ($reply===null) {
                Db::name('hsx_boyuan_task')->where($owned)->update(['side_effect'=>'unknown']);
                $task['side_effect']='unknown';
                self::audit($site,$id,$profile['connection_key'],'send','started','发送已冻结的原始请求',0,['operation_id'=>$task['operation_id'],'request'=>$request]);
                $endpoint=match ($request['operation_type']) { 'party_resolve'=>'parties','financial_item_ensure'=>'financial','settlement_register'=>'settlements',default=>'documents' };
                $reply=$gateway->send('POST',$profile['paths'][$endpoint],$request);
            }
            $result=Contract::response($reply,$request); $state=$result['operation_status'];
            $status=match ($state) { 'effective'=>$config['mode']==='mock'?'simulated':'succeeded','accepted','processing','unknown'=>'confirming','partial'=>'partial','rejected'=>'rejected' };
            if ($status==='confirming' && $task['attempts']>=10) $status='attention';
            $allNone=!empty($result['errors']) && !array_filter($result['errors'],static fn($error)=>($error['side_effect']??'unknown')!=='none' || !empty($error['external_ids']));
            $noObjects=empty($result['documents']) && empty($result['items']) && empty($result['settlement']);
            $effect=$state==='effective'?'applied':($state==='rejected' && $allNone && $noObjects?'none':($state==='partial'?'partial':'unknown'));
            Db::transaction(function () use ($site,$id,$owned,$task,$profile,$result,$request,$status,$effect): void {
                $row=Db::name('hsx_boyuan_task')->where($owned)->lock(true)->find(); if (!$row) return;
                if (in_array($status,['succeeded','simulated'],true)) $this->applyBindings($row,$request,$result);
                $nos=array_column($result['documents']??[],'external_document_no');
                $reason=in_array($status,['succeeded','simulated'],true)?'':mb_substr((string)($result['errors'][0]['message']??'已接收，等待博远确认实际结果'),0,1000);
                Db::name('hsx_boyuan_task')->where($owned)->update(['status'=>$status,'side_effect'=>$effect,'reason'=>$reason,'result_json'=>Contract::json(Contract::redact($result)),
                    'external_no'=>mb_substr(implode(',',$nos),0,255),'next_at'=>time()+30,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
                self::audit($site,$id,$profile['connection_key'],'result',$status,$status==='simulated'?'模拟完成，未访问博远':($status==='succeeded'?'约定内容已核验生效':$reason),0,['operation_id'=>$task['operation_id'],'response'=>$result]);
            });
        } catch (\Throwable $error) {
            $effect=$task['side_effect']!=='none'?'unknown':($error instanceof Problem?$error->effect:'none');
            $reason=$error instanceof Problem?$error->getMessage():'任务处理异常，请按任务编号检查服务日志';
            $code=$error instanceof Problem?$error->reason:'LOCAL_ERROR';
            $status=$effect!=='none'?'confirming':($code==='DEPENDENCY_PENDING'?'waiting':'blocked');
            if (in_array($code,['MANUAL_REVIEW','HISTORICAL_MANUAL','ORIGINAL_SPLIT_REVIEW','EXISTING_FUNDS_REVIEW_REQUIRED','FINANCE_SCOPE_REQUIRED'],true)) $status='attention';
            if ($status==='confirming' && (int)$task['attempts']>=10) $status='attention';
            Db::name('hsx_boyuan_task')->where($owned)->update(['status'=>$status,'side_effect'=>$effect,'reason'=>mb_substr($reason,0,1000),
                'next_at'=>time()+min(1800,30*(2**min(5,(int)$task['attempts']))),'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
            self::audit($site,$id,$profile['connection_key'],'process',$status,$reason,0,['code'=>$code,'operation_id'=>$task['operation_id']]);
        }
        return true;
    }
    private function bind(array $task,string $kind,string $ref,string $id,string $no,array $detail): void
    {
        $where=[['site_id','=',$task['site_id']],['connection_key','=',$task['connection_key']],['kind','=',$kind],['local_ref','=',$ref]];
        $existing=Db::name('hsx_boyuan_binding')->where($where)->find();
        if ($existing && $existing['external_id']!==$id) throw new Problem('BINDING_CONFLICT','同一来源对应不同博远对象，需人工核验','unknown');
        if ($existing && $kind==='financial') $detail=array_replace(Contract::decode($existing['detail_json']),$detail);
        $value=['external_id'=>$id,'external_no'=>$no,'detail_json'=>Contract::json($detail),'update_at'=>time()];
        if ($existing) Db::name('hsx_boyuan_binding')->where($where)->update($value);
        else Db::name('hsx_boyuan_binding')->insert($value+['site_id'=>$task['site_id'],'connection_key'=>$task['connection_key'],'kind'=>$kind,'local_ref'=>$ref,'task_id'=>$task['id']]);
    }
    private function applyBindings(array $task,array $request,array $result): void
    {
        foreach ($result['documents']??[] as $document) {
            foreach ($document['lines'] as $line) $this->bind($task,'line',$line['source_line_id'],$line['external_line_id'],$document['external_document_no'],$line+['external_document_id'=>$document['external_document_id']]);
            $existing=Db::name('hsx_boyuan_binding')->where('site_id',$task['site_id'])->where('connection_key',$task['connection_key'])->where('kind','document')->where('local_ref',$document['source_document_id'])->find();
            if (!$existing) $this->bind($task,'document',$document['source_document_id'],$document['external_document_id'],$document['external_document_no'],$document);
        }
        if ($request['operation_type']==='party_resolve') {
            $party=$result['party']??[];
            if (!in_array($result['match_status']??'',['matched','created'],true) || empty($party['id']) || empty($party['enabled'])) throw new Problem('PARTY_UNRESOLVED','往来单位未唯一匹配，请人工对应','none');
            $where=[['site_id','=',$task['site_id']],['connection_key','=',$task['connection_key']],['kind','=',$request['data']['role']],['local_ref','=',$request['data']['source_party_ref']]];
            if (!Db::name('hsx_boyuan_mapping')->where($where)->count()) Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$task['site_id'],'connection_key'=>$task['connection_key'],'kind'=>$request['data']['role'],'local_ref'=>$request['data']['source_party_ref'],
                'local_name'=>$request['data']['name'],'external_id'=>$party['id'],'external_code'=>$party['code'],'external_name'=>$party['name'],'extra_json'=>'{}','update_at'=>time()]);
        }
        foreach ($result['items']??[] as $item) $this->bind($task,'financial',$item['source_financial_item_id'],$item['external_financial_item_id'],$item['external_financial_item_no'],$item);
        foreach ($result['settlement']['allocations']??[] as $item) $this->bind($task,'financial',$item['source_financial_item_id'],$item['external_financial_item_id'],$item['external_financial_item_no']??'',$item);
    }
    public function action(int $site,int $id,int $uid,string $action,string $note='',string $externalNo=''): void
    {
        Db::transaction(function () use ($site,$id,$uid,$action,$note,$externalNo): void {
            $where=[['site_id','=',$site],['id','=',$id]]; $task=Db::name('hsx_boyuan_task')->where($where)->lock(true)->find();
            if (!$task) throw new Problem('TASK_MISSING','任务不存在');
            if ($task['status']==='processing' || ($action!=='query' && in_array($task['status'],['simulated','succeeded'],true))) throw new Problem('TASK_STATE','任务正在处理或已完成，不能重复操作');
            $values=['next_at'=>0,'update_at'=>time()];
            if ($action==='record_manual') {
                if (mb_strlen(trim($note))<5 || mb_strlen($note)>1000 || mb_strlen($externalNo)>255) throw new Problem('NOTE_REQUIRED','请填写5至1000字处理说明和有效单号');
                $values+=['status'=>'manual_pending','resolution'=>$note,'external_no'=>$externalNo,'query_only'=>1];
            } elseif ($action==='query') {
                if ($task['request_json']==='{}') throw new Problem('NOT_SENT','任务尚未发送，请补齐资料后重试');
                $values+=['status'=>'confirming','side_effect'=>'unknown','query_only'=>1];
            } elseif ($action==='retry') {
                if ($task['kind']==='manual_review') throw new Problem('MANUAL_REQUIRED','请先在博远核对原单并记录人工处理，不重建原业务');
                if ($task['side_effect']!=='none') throw new Problem('QUERY_REQUIRED','存在已生效或未知结果，请先查单，不能重新建单');
                $values+=['status'=>'pending','reason'=>'','query_only'=>0];
                if ($task['status']==='rejected') {
                    $snapshot=Contract::decode($task['snapshot_json']); $snapshot['supersedes_operation_id']=$task['operation_id'];
                    if ($task['mode']==='mock') $snapshot['_mock_scenario']=(new ConfigService())->get($site)['profiles']['mock']['scenario'];
                    $values+=['operation_id'=>'BY_'.hash('sha256',$task['operation_id'].':repair:'.$task['revision']), 'request_json'=>'{}','snapshot_json'=>Contract::json($snapshot),'revision'=>(int)$task['revision']+1];
                }
            } else throw new Problem('ACTION_INVALID','不支持的任务操作');
            Db::name('hsx_boyuan_task')->where($where)->update($values);
            self::audit($site,$id,$task['connection_key'],$action,$values['status'],$action==='record_manual'?'已记录人工处理，尚未核验博远结果':'人工请求继续原任务',$uid,['operation_id'=>$task['operation_id'],'external_no'=>$externalNo,'note'=>$note]);
        });
    }
    public function tick(int $site,int $limit=50): array
    {
        Db::transaction(function () use($site): void {
            Db::name('site')->where('site_id',$site)->lock(true)->find();
            $key=ConfigService::KEY.'_HEARTBEAT'; $row=\app\model\sys\SysConfig::where('site_id',$site)->where('config_key',$key)->findOrEmpty();
            if ($row->isEmpty()) \app\model\sys\SysConfig::create(['site_id'=>$site,'config_key'=>$key,'value'=>['at'=>time()]]); else $row->save(['value'=>['at'=>time()]]);
        });
        $config=(new ConfigService())->get($site); $p=$config['profiles'][$config['mode']]; $done=0;
        $queue=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$p['connection_key']);
        if (!$config['finance_enabled']) $queue->where(function($q): void { $q->whereNotIn('kind',['financial_item_ensure','settlement_register'])->whereOr('query_only',1); });
        $ids=$queue->whereIn('status',['pending','retry','waiting','confirming','processing'])->where('next_at','<=',time())->order('id')->limit(min(100,max(1,$limit)))->column('id');
        foreach ($ids as $id) if ($this->process($site,(int)$id)) $done++;
        return ['processed'=>$done];
    }
    private function refreshBalances(array $task,array $snapshot,array $profile,Transport $transport): void
    {
        $ids=array_column($snapshot['allocations'],'source_financial_item_id');
        if (!$ids || count($ids)>100) throw new Problem('ALLOCATIONS_INVALID','缺少可同步的收付款分配');
        $bindings=$this->bindings((int)$task['site_id'],$task['connection_key']);
        foreach ($ids as $id) if (!isset($bindings['financial:'.$id])) throw new Problem('DEPENDENCY_PENDING','等待原应收应付核验：'.$id);
        $reply=$transport->send('POST',$profile['paths']['balances'],['financial_item_type'=>$snapshot['direction']==='payment'?'payable':'receivable','source_financial_item_ids'=>$ids]);
        if (($reply['code']??'')!=='OK' || count($reply['data']['items']??[])!==count($ids)) throw new Problem('BALANCE_UNVERIFIED','博远没有返回唯一完整的当前余额，未登记款项');
        $actual=array_column($reply['data']['items']??[],null,'source_financial_item_id');
        foreach ($ids as $id) {
            $row=$actual[$id]??null; $old=$bindings['financial:'.$id];
            if (!$row || ($row['external_financial_item_id']??'')!==$old['external_id']
                || ($row['external_party_id']??'')!==$old['detail']['external_party_id']
                || ($row['financial_item_type']??'')!==$old['detail']['financial_item_type']) throw new Problem('BALANCE_UNVERIFIED','博远当前往来身份不一致，未登记款项');
            try { Contract::financialBalance($row); }
            catch (Problem $e) { throw new Problem('BALANCE_UNVERIFIED','博远余额回执未通过核对，尚未登记款项'); }
            $this->bind($task,'financial',$id,$old['external_id'],$old['external_no'],$row);
        }
    }
}
