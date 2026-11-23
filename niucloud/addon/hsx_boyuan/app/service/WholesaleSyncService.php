<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\gateway\{OpenApi,Transport,WholesaleGateway};
use addon\hsx_boyuan\app\support\{ArchiveView,Contract,Problem};
use think\facade\Db;

/** One confirmed local sale = at most one remote write. Capture alone never authorizes dispatch. */
final class WholesaleSyncService
{
    public const KIND='wholesale_outbound';
    public static function dispatchEnabled(array $c,array $p): bool
    {
        return ($c['mode']??'')==='test' && ($p['protocol']??'')==='openapi_v153' && !empty($p['connection_key']) && !empty($c['enabled'])
            && !empty($c['purchase_enabled']) && empty($c['paused']) && !empty($p['probe_at']) && !empty($p['contract_confirmed']);
    }
    private function context(int $site,bool $active=false): array
    {
        $service=new ConfigService();
        if(!$service->installed($site)) throw new Problem('ADDON_MISSING','本站未安装 ERP 和博远插件');
        $c=$service->get($site,false); $p=$c['profiles'][$c['mode']];
        if($c['mode']!=='test' || $p['protocol']!=='openapi_v153' || !$p['connection_key'])
            throw new Problem('SALE_UAT_ONLY','批发出库只开放测试账套，不会向生产发送');
        if($active && !self::dispatchEnabled($c,$p))
            throw new Problem('CONNECTION_PAUSED','请先核验并开启当前账套，再处理销售单');
        return [$c,$p];
    }

    /** Preparing a sale never fabricates an event from today's order or sends it to Boyuan. */
    private function sourceEvent(int $site,int $id,string $number,array $c,string $key,bool $lock): array
    {
        $start=max((int)$c['started_at'],(int)($c['purchase_started_at']??0));
        if ($id<=0 || $number==='' || strlen($number)>128 || $start<=0) throw new Problem('SALE_SOURCE_INVALID','未找到启用后该笔销售的原始事件');
        $src='sale:'.$id;
        $found=Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.confirmed.v1')->where('occurred_at','>=',$start)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(payload_json),payload_json,'{}'), '$.payload.source_document_id')) = ?",[$src])
            ->field('event_id,occurred_at,payload_json')->limit(2)->select()->toArray();
        if (count($found)!==1) throw new Problem('SALE_FACT_MISSING','没有找到唯一的原销售确认事件，不能补造历史出库');
        $row=$found[0]; $e=Contract::decode($row['payload_json']); $s=$e['payload']??[];
        if (($e['site_id']??null)!==$site || ($e['event_version']??null)!==1 || ($e['event_name']??'')!=='erp.integration.document.confirmed.v1'
            || ($e['event_id']??'')==='' || $e['event_id']!==$row['event_id'] || (int)($e['occurred_at']??0)!==(int)$row['occurred_at']
            || ($e['aggregate_type']??'')!=='sale' || ($e['aggregate_id']??null)!==$id || ($e['source']['plugin']??'')!=='hsx_erp'
            || ($e['source']['type']??'')!=='sale' || ($e['source']['id']??null)!==$id || ($s['kind']??'')!=='sale'
            || ($s['source_document_id']??'')!==$src || ($s['source_document_no']??'')!==$number || ($s['sale_type']??'')!=='wholesale'
            || ($s['payment_method_ref']??'')!=='method:挂账' || empty($s['lines'])) throw new Problem('SALE_SOURCE_INVALID','原单身份或挂账出库事实不一致，不能准备出库');
        $this->source($site,$s,true,$lock);
        foreach ($s['lines'] as $line) $this->verifiedPurchaseLine($site,$key,$line);
        return $e;
    }

    /** Read-only ERP detail projection: does not add, dispatch, acknowledge or repair anything. */
    public function canPrepareSource(int $site,int $id,string $number): bool
    {
        try {
            [$c,$p]=$this->context($site,true); $this->sourceEvent($site,$id,$number,$c,$p['connection_key'],false);
            return true;
        } catch(Problem $e) { return false; }
    }

    /** Same source/serial gates as the signed request, for the optional local bridge; never freezes or sends. */
    public function checkOriginalLines(int $site,string $key,array $snapshot,bool $beforeSubmission): void
    {
        $this->source($site,$snapshot,$beforeSubmission,false);
        foreach($snapshot['lines'] as $line) $this->verifiedPurchaseLine($site,$key,$line);
    }

    public function prepareSource(int $site,int $uid,int $id,string $number): array
    {
        if($uid<=0 || $site<=0 || $id<=0 || $number==='') throw new Problem('SALE_SOURCE_INVALID','缺少本站销售原单或当前操作员');
        return Db::transaction(function() use($site,$uid,$id,$number): array {
            if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            [$c,$p]=$this->context($site); $src='sale:'.$id;
            $head=Db::name('erp_sale_order')->where('site_id',$site)->where('id',$id)->find();
            if (!$head || $head['sale_no']!==$number) throw new Problem('SALE_SOURCE_INVALID','本站销售单号不符，请从原单重新进入');
            if ($old=$this->tasks($site,$p)->where('fact_key',$src.':wholesale-credit')->find()) {
                $snap=Contract::decode($old['snapshot_json']);
                if($old['business_no']!==$number || ($snap['source_document_id']??'')!==$src || ($snap['source_document_no']??'')!==$number
                    || (Contract::decode($old['request_json'])['schema']??'')!==WholesaleGateway::SCHEMA) throw new Problem('FACT_CONFLICT','原单关联不一致，未重新创建任务');
                return ['task_id'=>(int)$old['id'],'sale_no'=>$number];
            }
            if(!self::dispatchEnabled($c,$p)) throw new Problem('CONNECTION_PAUSED','当前账套已暂停或未经确认，未创建出库任务');
            $e=$this->sourceEvent($site,$id,$number,$c,$p['connection_key'],true);
            $out=$this->capture($site,$e);
            if(empty($out['task_id'])) throw new Problem('SALE_NOT_PREPARED','尚未准备出库，请刷新核对原单');
            TaskService::audit($site,(int)$out['task_id'],$p['connection_key'],'wholesale_open','awaiting_confirmation','从原销售单准备核对任务，未发送',$uid);
            return ['task_id'=>(int)$out['task_id'],'sale_no'=>$number];
        });
    }

    public function capture(int $site,array $event): array
    {
        [$c,$p]=$this->context($site);
        $s=$event['payload']??[];
        if(!$c['enabled'] || !$c['purchase_enabled'] || !empty($c['paused']) || ($event['occurred_at']??0)<max((int)$c['started_at'],(int)($c['purchase_started_at']??0))
            || ($event['event_name']??'')!=='erp.integration.document.confirmed.v1'
            || ($s['kind']??'')!=='sale' || ($s['sale_type']??'')!=='wholesale' || ($s['payment_method_ref']??'')!=='method:挂账') return [];
        if((int)($event['site_id']??0)!==$site || ($event['event_version']??0)!==1 || empty($event['event_id'])
            || !preg_match('/^sale:[1-9][0-9]*$/D',$s['source_document_id']??'') || empty($s['lines']))
            throw new Problem('EVENT_INVALID','销售来源身份无效');
        $id=Db::transaction(function() use($site,$p,$event,$s): int {
            Db::name('site')->where('site_id',$site)->lock(true)->find();
            [$current,$profile]=$this->context($site);
            if(!$current['enabled'] || !$current['purchase_enabled'] || !empty($current['paused']) || (int)$event['occurred_at']<max((int)$current['started_at'],(int)($current['purchase_started_at']??0)) || $profile['connection_key']!==$p['connection_key']) return 0;
            $fact=$s['source_document_id'].':wholesale-credit'; $q=$this->tasks($site,$p)->where('fact_key',$fact);
            if($old=$q->find()) {
                if($old['snapshot_json']!==Contract::json($s)) throw new Problem('FACT_CONFLICT','同一销售单出现不同快照；未覆盖原记录');
                return (int)$old['id'];
            }
            $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'mode'=>'test','connection_key'=>$p['connection_key'],
                'fact_key'=>$fact,'event_id'=>$event['event_id'],'operation_id'=>'BS_'.hash('sha256',$site.':'.$p['connection_key'].':'.$fact),
                'kind'=>self::KIND,'business_no'=>$s['source_document_no'],'status'=>'awaiting_confirmation','reason'=>'本地已出库；核对后可提交博远挂账批发单。',
                'snapshot_json'=>Contract::json($s),'request_json'=>Contract::json(['schema'=>WholesaleGateway::SCHEMA]),'result_json'=>'{}',
                'create_at'=>time(),'update_at'=>time()]);
            TaskService::audit($site,$id,$p['connection_key'],'wholesale_capture','awaiting_confirmation','已接收挂账批发出库，尚未外发');
            return $id;
        });
        return $id?['consumer'=>'hsx_boyuan','status'=>'processed','task_id'=>$id]:[];
    }

    /** A void/physical return is a different fact: never automatically reverse or recreate a Boyuan bill. */
    public function flagCorrection(int $site,array $event): array
    {
        $s=$event['payload']??[]; $source=(string)($s['original_document_ref']??'');
        if((int)($event['site_id']??0)!==$site || ($event['event_version']??null)!==1 || !preg_match('/^sale:[1-9][0-9]*$/D',$source)
            || !(($event['event_name']??'')==='erp.integration.document.invalidated.v1' || (($event['event_name']??'')==='erp.integration.document.confirmed.v1' && ($s['kind']??'')==='sale_return'))) return [];
        [, $p]=$this->context($site);
        return Db::transaction(function() use($site,$p,$source): array {
            $q=$this->tasks($site,$p)->where('fact_key',$source.':wholesale-credit');
            $t=(clone $q)->lock(true)->find();
            if(!$t) return [];
            if(($t['resolution']??'')!=='source_changed') {
                $reason=$t['side_effect']==='none'?'本地销售已撤销或退货，已停止提交博远':'本地销售已撤销或退货，请在博远核对原销售单及退货，系统不会自动冲销或重建';
                $values=['resolution'=>'source_changed','reason'=>$reason,'revision'=>(int)$t['revision']+1,'update_at'=>time()];
                // Preserve the lease on an in-flight write so that its original receipt is retained.
                if($t['status']!=='processing') $values+=['status'=>$t['side_effect']==='none'?'blocked':'needs_review','next_at'=>0];
                $q->update($values);
                TaskService::audit($site,(int)$t['id'],$p['connection_key'],'wholesale_source_changed','needs_review',$reason);
            }
            return ['consumer'=>'hsx_boyuan','status'=>'processed','task_id'=>(int)$t['id']];
        });
    }

    private function tasks(int $site,array $p)
    {
        return Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('mode','test')->where('kind',self::KIND);
    }
    private function mapping(int $site,string $key,string $kind,string $local): string
    {
        return (string)Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$key)->where('kind',$kind)->where('local_ref',$local)->value('external_id');
    }
    private function archiveRow(int $site,string $key,string $resource,string $id): ?array
    {
        $run=ReferenceService::latestRun($site,$key,$resource);
        $row=$run!=='' && $id!=='' ? CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)
            ->where('external_id',$id)->where('seen_run',$run)->where('enabled',1)->find():null;
        return $row && $row['external_id']===$id ? $row : null;
    }
    private function archive(int $site,string $key,string $resource,string $id): array
    {
        $row=$this->archiveRow($site,$key,$resource,$id);
        if(!$row) throw new Problem('SALE_ARCHIVE_STALE','商品、客户或仓库不在当前账套的有效档案内，请先同步基础资料');
        return ArchiveView::extras($row['extra_json']);
    }
    private function matchedCreator(int $site,string $key,array $snapshot): string
    {
        $ref=(string)($snapshot['operator']['operator_ref']??'');
        return preg_match('/^staff:[1-9][0-9]*$/D',$ref) ? $this->mapping($site,$key,'staff',$ref) : '';
    }
    private function creator(int $site,string $key,string $id,string $company): array
    {
        if($id==='') throw new Problem('SALE_CREATOR_REQUIRED','请选择博远制单人，或先关联原销售单的经办员工');
        $staff=$this->archiveRow($site,$key,'staff',$id);
        if(!$staff || $company==='' || (Contract::decode($staff['extra_json'])['company_id']??null)!==$company)
            throw new Problem('SALE_CREATOR_INVALID','请选择出库仓所属公司当前启用的博远员工');
        $name=(string)$staff['name'];
        if(strlen($id)>36 || trim($name)==='' || strlen($name)>64 || preg_match('/[\x00-\x1f\x7f]/',$name))
            throw new Problem('SALE_CREATOR_INVALID','博远制单人档案不完整或姓名超过64字节，请核对后重新选择');
        return ['id'=>$id,'name'=>$name];
    }
    /** UI offers the original sale operator's mapping; never uses the previous purchase's creator or salesperson. */
    public function creatorDraft(int $site,string $key,array $snapshot): array
    {
        $party=(string)($snapshot['party']['ref']??'');
        $row=preg_match('/^party:[1-9][0-9]*$/D',$party) ? $this->archiveRow($site,$key,'parties',$this->mapping($site,$key,'customer',$party)) : null;
        $customer=$row && in_array(Contract::decode($row['extra_json'])['is_cust']??null,[1,'1',true],true)
            ? ['id'=>$row['external_id'],'name'=>$row['name'],'code'=>$row['code']] : null;
        $companies=[];
        foreach(array_unique(array_column($snapshot['lines']??[],'warehouse_ref')) as $ref) {
            $id=$this->mapping($site,$key,'warehouse',(string)$ref); $row=$this->archiveRow($site,$key,'warehouses',$id);
            $co=$row?(string)(Contract::decode($row['extra_json'])['company_id']??''):'';
            if($co==='') return ['company_id'=>'','mapped'=>null,'customer'=>$customer];
            $companies[$co]=true;
        }
        $company=count($companies)===1?(string)array_key_first($companies):'';
        $id=$this->matchedCreator($site,$key,$snapshot); $mapped=null;
        if($company!=='' && $id!=='') {
            try { $mapped=$this->creator($site,$key,$id,$company); } catch(Problem $ignored) {}
        }
        return ['company_id'=>$company,'mapped'=>$mapped,'customer'=>$customer];
    }
    /** Settling a genuine credit sale later is not a change to the original outbound fact. */
    private function source(int $site,array $s,bool $beforeWrite,bool $lock=true): array
    {
        $order=(int)substr($s['source_document_id'],5);
        $head=Db::name('erp_sale_order')->where('site_id',$site)->where('id',$order)->lock($lock)->find();
        if(!$head || $head['sale_no']!==$s['source_document_no'] || $head['status']!=='completed' || $head['settle_method']!=='挂账'
            || ($beforeWrite && (float)$head['received_amount']!==0.0) || 'party:'.$head['party_id']!==$s['party']['ref'] || (int)$head['sale_at']!==(int)$s['occurred_at']
            || !in_array((string)($head['sale_channel_key']?:$head['sale_channel']),['erp_peer','wholesale','批发'],true))
            throw new Problem('SALE_SOURCE_CHANGED','本地原销售单或挂账条件已变化，请核对原单');
        $rows=Db::name('erp_sale_item')->where('site_id',$site)->where('sale_order_id',$order)->order('id')->lock($lock)->select()->toArray();
        if(count($rows)!==count($s['lines'])) throw new Problem('SALE_SOURCE_CHANGED','销售明细数量已变化');
        $sum=0;
        foreach($s['lines'] as $i=>$line) {
            $row=$rows[$i]; $asset=Db::name('erp_asset')->where('site_id',$site)->where('id',$row['asset_id'])->lock($lock)->find();
            if(!$asset || 'sale_line:'.$row['id']!==$line['source_line_id'] || 'asset:'.$row['asset_id']!==$line['device_ref']
                || $row['status']!=='sold' || $asset['status']!=='sold' || (int)$asset['sale_order_id']!==$order || (int)$asset['sale_item_id']!==(int)$row['id']
                || $asset['imei']!==$line['imei'] || $row['imei']!==$line['imei'] || $asset['ownership_type']!=='owned'
                || 'warehouse:'.$row['warehouse_id']!==$line['warehouse_ref'] || number_format((float)$row['sale_price'],2,'.','')!==$line['amount'])
                throw new Problem('SALE_SOURCE_CHANGED','设备已退回、调整或非自有库存；请核对原销售明细');
            $sum+=Contract::cents($line['amount']);
        }
        if($sum!==Contract::cents(number_format((float)$head['total_amount'],2,'.',''))) throw new Problem('SALE_TOTAL_CHANGED','销售金额与原销售明细不符');
        return $head;
    }
    /** A sale row may only use its own verified purchase line: order lookup is not a SKU or IMEI search. */
    private function verifiedPurchaseLine(int $site,string $key,array $line): array
    {
        $ref=$line['inbound_line_ref']??'';
        $binding=preg_match('/^purchase_line:[1-9][0-9]*$/D',$ref)?Db::name('hsx_boyuan_binding')->where('site_id',$site)
            ->where('connection_key',$key)->where('kind','line')->where('local_ref',$ref)->find():null;
        $purchase=$binding?Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$key)->where('mode','test')
            ->where('kind','purchase')->where('status','succeeded')->where('id',$binding['task_id'])->find():null;
        return self::matchInboundReceipt($line,$binding?:[],$purchase?:[]);
    }

    /** Both dispatch and the read-only sale screen use this same line-level evidence check. */
    public static function matchInboundReceipt(array $line,array $binding,array $purchase): array
    {
        $ref=$line['inbound_line_ref']??'';
        $bound=$binding?Contract::decode($binding['detail_json']):[]; $req=$purchase?Contract::decode($purchase['request_json']):[];
        $in=array_column($req['lines']??[],null,'source_line_id')[$ref]??[];
        $proof=$purchase?Contract::decode($purchase['result_json']):[]; $lineProof=array_column($proof['lines']??[],null,'source_line_id')[$ref]??[];
        if (($req['schema']??'')!==PurchaseSyncService::SCHEMA || ($req['stage']??'')!=='frozen' || ($proof['verified']??false)!==true
            || ($proof['verification_level']??'')!=='purchase_imei_records_matched' || empty($purchase['external_no'])
            || $purchase['external_no']!==($proof['external_no']??null) || $purchase['external_no']!==($binding['external_no']??null)
            || ($in['device_ref']??null)!==$line['device_ref'] || ($in['imei']??null)!==$line['imei'] || empty($line['imei'])
            || ($bound['imei']??null)!==$line['imei'] || ($bound['device_ref']??null)!==$line['device_ref'] || empty($binding['external_id'])
            || ($bound['external_line_id']??null)!==$binding['external_id'] || ($lineProof['external_line_id']??null)!==$binding['external_id']
            || ($lineProof['imei']??null)!==$line['imei'] || ($lineProof['device_ref']??null)!==$line['device_ref'])
            throw new Problem('SALE_INBOUND_REQUIRED','请先完成该设备的博远采购入库核验，再同步出库');
        return [$req,$in];
    }

    /** Trace each serial to the purchase that was independently verified in the same account. */
    private function freeze(int $site,array $task,string $customer,string $creatorId): array
    {
        $s=Contract::decode($task['snapshot_json']); $key=$task['connection_key']; $head=$this->source($site,$s,true);
        if(($s['operator']['operator_ref']??'')!=='staff:'.(int)$head['operator_uid']) throw new Problem('SALE_SOURCE_CHANGED','原销售单制单员工已变化，请核对原单');
        if($customer==='') $customer=$this->mapping($site,$key,'customer',$s['party']['ref']);
        if(!$customer) throw new Problem('SALE_CUSTOMER_REQUIRED','请选择对应的博远客户');
        if(!in_array($this->archive($site,$key,'parties',$customer)['is_cust']??null,[1,'1',true],true))
            throw new Problem('SALE_CUSTOMER_INVALID','该往来单位不是启用的博远客户');
        $lines=[]; $warehouses=[]; $company='';
        foreach($s['lines'] as $line) {
            [$req,$in]=$this->verifiedPurchaseLine($site,$key,$line);
            $wid=$this->mapping($site,$key,'warehouse',$line['warehouse_ref']);
            if($wid==='' || $wid!==$in['warehouse_id']) throw new Problem('SALE_WAREHOUSE_MISMATCH','出库仓与已核验入库仓不一致或尚未关联；不会自动调拨');
            $this->archive($site,$key,'products',$in['product_id']); $wh=$this->archive($site,$key,'warehouses',$wid);
            $company=(string)($wh['company_id']??'');
            if(!$company || $company!==($req['selection']['company_id']??''))
                throw new Problem('SALE_COMPANY_CHANGED','仓库公司与原采购单不一致');
            $warehouses[$wid]=true;
            $lines[]=['source_line_id'=>$line['source_line_id'],'device_ref'=>$line['device_ref'],'imei'=>$line['imei'],'product_id'=>$in['product_id'],'amount'=>$line['amount']];
        }
        if(count($warehouses)!==1) throw new Problem('SALE_WAREHOUSE_MISMATCH','一张博远批发出库单只能使用一个仓库');
        $number='BS'.substr(hash('sha256',$task['operation_id']),0,30);
        $date=(new \DateTimeImmutable('@'.$s['occurred_at']))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d');
        $creator=$this->creator($site,$key,$creatorId!==''?$creatorId:$this->matchedCreator($site,$key,$s),$company);
        return WholesaleGateway::freeze($number,$date,$customer,(string)array_key_first($warehouses),$lines,$creator['id'],$creator['name'])+['company_id'=>$company];
    }

    public function submit(int $site,int $id,int $uid,array $input): array { return $this->queue($site,$id,$uid,$input,false); }
    public function verify(int $site,int $id,int $uid,array $input): array { return $this->queue($site,$id,$uid,$input,true); }
    private function queue(int $site,int $id,int $uid,array $input,bool $read): array
    {
        return Db::transaction(function() use($site,$id,$uid,$input,$read): array {
            Db::name('site')->where('site_id',$site)->lock(true)->find(); [$c,$p]=$this->context($site,true);
            $q=$this->tasks($site,$p)->where('id',$id); $task=(clone $q)->lock(true)->find();
            if(!$task || ($input['mode']??null)!==$c['mode'] || ($input['connection_key']??null)!==$p['connection_key']
                || ($input['revision']??null)!==(int)$task['revision']) throw new Problem('TASK_STALE','任务或账套已更新，请刷新');
            $v=['revision'=>(int)$task['revision']+1,'update_at'=>time(),'next_at'=>time(),'query_only'=>(int)$read];
            if($read) {
                if($task['side_effect']==='none' || !in_array($task['status'],['attention','needs_review','created_review'],true)) throw new Problem('SALE_STATE_INVALID','当前没有待核对的出库结果');
                $v+=['status'=>'confirming','reason'=>'正在核对原销售单，不会重新出库'];
            } else {
                if($task['resolution']==='source_changed') throw new Problem('SALE_SOURCE_CHANGED','原销售已撤销或退货，不能重新出库');
                if(!in_array($task['status'],['awaiting_confirmation','blocked'],true) || $task['side_effect']!=='none' || (int)$task['attempts']>0) throw new Problem('SALE_ALREADY_SENT','不能重复提交出库，请核对原销售单');
                if(!is_string($input['creator_id']??'')) throw new Problem('SALE_CREATOR_INVALID','博远制单人编号无效');
                $v+=['request_json'=>Contract::json($this->freeze($site,$task,(string)($input['customer_id']??''),$input['creator_id']??'')),'status'=>'pending','reason'=>'已确认，等待提交博远'];
            }
            $q->update($v);
            TaskService::audit($site,$id,$p['connection_key'],$read?'wholesale_verify':'wholesale_submit',$v['status'],$v['reason'],$uid);
            return ['id'=>$id,'status'=>$v['status'],'revision'=>$v['revision']];
        });
    }

    public function process(int $site,int $id,?Transport $transport=null,?array $expected=null): bool
    {
        [$c,$p]=$this->context($site); if(!$c['enabled'] || !$c['purchase_enabled'] || $c['paused'] || !$p['probe_at'] || !$p['contract_confirmed']) return false;
        $lease=bin2hex(random_bytes(16)); $q=$this->tasks($site,$p)->where('id',$id);
        $task=Db::transaction(function() use($q,$site,$lease,$expected): ?array {
            Db::name('site')->where('site_id',$site)->lock(true)->find();
            $t=(clone $q)->lock(true)->find(); if(!$t) return null;
            if($expected!==null && !ManualDocumentCommandService::claimable((new ConfigService())->get($site,false),$t,$expected)) return null;
            if($t['status']==='processing' && (int)$t['lease_until']<time()) {
                $q->update(['status'=>'attention','reason'=>'执行中断；仅可核对原单，不会重新出库','lease_token'=>'','lease_until'=>0,'revision'=>(int)$t['revision']+1,'update_at'=>time()]); return null;
            }
            if(!in_array($t['status'],['pending','confirming'],true) || (int)$t['next_at']>time()) return null;
            $r=Contract::decode($t['request_json']);
            if(!$t['query_only']) {
                try {
                    if($t['side_effect']!=='none' || (int)$t['attempts']>0) throw new Problem('SALE_ALREADY_SENT','不能重复提交出库');
                    if(empty($r['biz']['head']['creater_id'])) throw new Problem('SALE_CREATOR_REQUIRED','原待发送确认缺少制单人，请重新确认；未发出');
                    if($r!==$this->freeze($site,$t,$r['biz']['head']['cust_id'],$r['biz']['head']['creater_id'])) throw new Problem('SALE_SOURCE_CHANGED','出库资料已改变，请重新核对');
                } catch(Problem $e) {
                    $out=Contract::decode($t['result_json']); $out['last_issue']=\addon\hsx_boyuan\app\support\ReceiptFacts::issue($e,'prepare');
                    $q->update(['status'=>$t['side_effect']==='none'?'blocked':'attention','reason'=>$e->getMessage(),'result_json'=>Contract::json($out),'revision'=>(int)$t['revision']+1,'update_at'=>time()]);
                    TaskService::audit($site,(int)$t['id'],$t['connection_key'],'wholesale_preflight','blocked',$e->getMessage(),0,['code'=>$e->reason]); return null;
                }
            }
            $q->update(['status'=>'processing','side_effect'=>'unknown','lease_token'=>$lease,'lease_until'=>time()+300,'attempts'=>(int)$t['attempts']+1,'update_at'=>time()]);
            return $t;
        });
        if(!$task) return false;
        $r=Contract::decode($task['request_json']); $prior=Contract::decode($task['result_json']);
        $read=(bool)$task['query_only']; $values=[];
        try {
            [$current, $fresh]=$this->context($site,true);
            if($p!==$fresh || ($expected!==null && !ManualDocumentCommandService::sameConfig($current,$expected))) throw new Problem('CONNECTION_CHANGED','账套配置发生变化，请核对原单');
            $api=$transport??(new ConfigService())->transport($site,$fresh,'test',\addon\hsx_boyuan\app\support\InterfaceTrace::task($task));
            if(!$api instanceof OpenApi) throw new Problem('SALE_PROTOCOL_INVALID','仅允许博远开放平台签名接口');
            $g=new WholesaleGateway($api);
            if(!$read) {
                $receipt=$g->create($r); $prior=['receipt'=>$receipt]; $values=['status'=>'created_review','reason'=>'博远已返回出库单号，请核对原单明细','external_no'=>$receipt['external_no']];
            } elseif(!empty($prior['receipt'])) {
                $verified=$g->verify($r,$prior['receipt']);
                if(($verified['verified']??false)!==true || ($verified['company_id']??'')!==($r['company_id']??'')) throw new Problem('SALE_VERIFY_MISMATCH','批发销售明细的公司与入库公司不符');
                $prior['verification']=$verified; $values=['status'=>'succeeded','side_effect'=>'applied','reason'=>'已核对原批发单及逐台出库明细；不代表客户已付款'];
            } else {
                $prior['lookup']=$g->locate($r);
                $reason=match($prior['lookup']['outcome']) {
                    'matched_candidates'=>'找到 '.$prior['lookup']['candidate_count'].' 张待核对的候选单；尚无原始串号回执，不代表出库已核实。',
                    'unidentifiable'=>'本次查询未取得可唯一关联原请求的信息；请将手工单号交给博远核查，不能认定未出单。',
                    default=>'本次查询没有找到匹配本次手工单号的出库单；这不证明未出单。',
                };
                $values=['status'=>'needs_review','reason'=>$reason.' 禁止重复出库。'];
            }
        } catch(\Throwable $e) {
            $values=['status'=>$read?'needs_review':'attention','reason'=>$e instanceof Problem?$e->getMessage():'未能确认博远结果；请核对原单，不要重复出库'];
            $prior['last_issue']=\addon\hsx_boyuan\app\support\ReceiptFacts::issue($e,$read?'verify':'submit');
            if ($read && empty($prior['receipt'])) {
                if(isset($prior['lookup']) && ($prior['lookup']['outcome']??'')!=='failed') $prior['previous_completed_lookup']=$prior['lookup'];
                $prior['lookup']=['verified'=>false,'outcome'=>'failed','queried_at'=>time(),'candidate_count'=>null,'candidates'=>[],'can_resend'=>false];
                $values['reason']='本次核对未完成，不是零候选。'.$values['reason'];
            }
        }
        if(in_array($values['status'],['created_review','succeeded'],true)) unset($prior['last_issue']);
        Db::transaction(function() use($site,$id,$p,$q,$lease,$values,$prior,$read,$task,$r) {
            $owned=(clone $q)->where('status','processing')->where('lease_token',$lease);
            if(!$row=(clone $owned)->lock(true)->find()) return;
            if($values['status']==='succeeded') {
                try { $this->source($site,Contract::decode($task['snapshot_json']),false); }
                catch(Problem $e) { $values=['status'=>'needs_review','reason'=>'博远回执已保留；本地销售或资料已变化，请人工核对两边原单']; }
            }
            if($row['resolution']==='source_changed') {
                $values['status']='needs_review'; $values['reason']='原销售已撤销或退货，请人工核对两边原单；不会自动冲销或重新出库';
                unset($values['side_effect']);
            }
            $owned->update($values+['result_json'=>Contract::json($prior),'lease_token'=>'','lease_until'=>0,'next_at'=>0,'revision'=>(int)$row['revision']+1,'update_at'=>time()]);
            TaskService::audit($site,$id,$p['connection_key'],$read?'wholesale_readback':'wholesale_create',$values['status'],$values['reason']);
        });
        return true;
    }
}
