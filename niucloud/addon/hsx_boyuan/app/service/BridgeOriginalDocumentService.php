<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\{Contract,Problem,ProductionVoucher,ProductionPurchasePilot};
use addon\hsx_boyuan\app\gateway\WholesaleGateway;
use core\exception\CommonException;
use think\facade\Db;

/**
 * Local handoff only. The legacy ERP listener and this bridge share exactly the same
 * vendor fact keys, tasks, frozen requests, workers and receipts. Never submit here.
 */
final class BridgeOriginalDocumentService
{
    private const PILOT_SCOPE=ProductionPurchasePilot::FIRST_SCOPE;

    public function manualDisjointScopes(int $site): array
    {
        if($site!==ProductionPurchasePilot::SITE) return [];
        $c=(new ConfigService())->get($site,false);$p=$c['profiles']['production']??[];
        return ProductionPurchasePilot::profile($site,$c,$p)?[[ProductionPurchasePilot::NEXT_SCOPE,ProductionPurchasePilot::THIRD_SCOPE]]:[];
    }

    /** The scope lives in the bridge connection JSON and cannot expand if the ordinary release later opens. */
    public function admitsManualFact(int $site,array $target,array $event): bool
    {
        $scope=$target['manual_scope']??'';
        if ($scope==='' && ($site!==ProductionPurchasePilot::SITE || ($target['mode']??null)!=='production' || ($target['account_code']??null)!==ProductionPurchasePilot::ACCOUNT)) return true;
        if ($scope!=='' && !in_array($scope,[self::PILOT_SCOPE,ProductionPurchasePilot::NEXT_SCOPE,ProductionPurchasePilot::THIRD_SCOPE],true)) return false;
        $c=(new ConfigService())->get($site,false);
        try { $p=$this->profile($c,$target); } catch(Problem $e) { return false; }
        $pilot=ProductionPurchasePilot::profile($site,$c,$p);
        if (!$pilot && $scope==='') return true;
        return $pilot && ProductionPurchasePilot::scoped($scope,$event['payload']['lines'][0]['imei']??null) && ProductionPurchasePilot::event($site,$event);
    }

    /** Export only the already-saved target identity; no probe, credential copy or network access. */
    public function publicProfile(int $site): array
    {
        if($site<=0 || $site!==(int)request()->siteId() || (int)request()->uid()<=0) throw new Problem('SITE_MISMATCH','请使用本站有效员工账号查看原连接');
        (new PermissionService())->assertApi('get','boyuan/config');
        $service=new ConfigService();
        if(!$service->installed($site)) throw new Problem('ADDON_MISSING','本站需先启用博远和 ERP 插件');
        $config=$service->get($site,false); $mode=$config['mode']; $p=$config['profiles'][$mode]??[];
        $public=['api_url'=>(string)($p['endpoint']??''),'gateway_path'=>(string)($p['gateway_path']??''),'app_id'=>(string)($p['app_id']??''),
            'account_code'=>(string)($p['account_id']??''),'mode'=>$mode];
        if(!in_array($mode,['test','production'],true) || $public['app_id']==='' || $public['account_code']==='' || $public['api_url']==='')
            throw new Problem('PROFILE_INCOMPLETE','请先在博远连接设置保存测试或正式账套、AppID 与 API 地址；模拟账套不能绑定');
        $this->profile($config,$public);
        if (ProductionPurchasePilot::profile($site,$config,$p)) {
            $public['manual_scope']=self::PILOT_SCOPE;
            $public['manual_scope_options']=[['value'=>ProductionPurchasePilot::NEXT_SCOPE,'label'=>'定价人试单 · '.ProductionPurchasePilot::NEXT_IMEI.' · 1 元挂账（不付款）'],
                ['value'=>ProductionPurchasePilot::THIRD_SCOPE,'label'=>'指定回收单 '.ProductionPurchasePilot::THIRD_RECYCLE_NO.' · 串号 '.ProductionPurchasePilot::THIRD_IMEI.' · 1 元挂账（不付款）']];
            $public['manual_scope_types']=['purchase'];
            $public['manual_scope_hint']='每个范围仅限所列单机，旧连接不授权新单。均为单台 1 元挂账、不付款；启用不会发单。';
        }
        $public['checked']=!empty($p['probe_at']);
        if(!$public['checked']) $public['reason']='博远当前连接尚无有效的只读探测回执，请先在博远后台核验账套。';
        return $public;
    }

    public function handle(array $event,array $context,bool $readOnly,bool $localOnly=false): array
    {
        try {
            $site=(int)($context['site_id']??0); $uid=(int)($context['operator_uid']??0);
            if($site<1 || (int)($event['site_id']??0)!==$site || (int)request()->siteId()!==$site || $uid<1 || (int)request()->uid()!==$uid)
                throw new Problem('SITE_MISMATCH','请使用本站有效员工账号核对原单');
            $permissions=new PermissionService();
            $permissions->assertApi('get','boyuan/tasks/<id>');
            $sale=($event['event_name']??null)==='erp.integration.document.confirmed.v1' && ($event['payload']['kind']??null)==='sale';
            $payment=($event['event_name']??null)==='erp.settlement.completed.v1';
            $receipt=$payment && ($event['payload']['settlement_type']??'')==='receipt';
            if(!$readOnly && !$receipt) $permissions->assertApi('post',$sale?'boyuan/wholesale/<id>/submit':'boyuan/tasks/<id>/action');
            $permissions->assertApi('get',$receipt?'erp/finance/receivable/lists':($payment?'erp/finance/payable/lists':($sale?'erp/sale/lists':'erp/purchase/lists')));
            if($localOnly && (!$readOnly || $sale)) throw new Problem('LOCAL_READ_ONLY','仅可读取已有采购、付款回执');
            $work=function() use($site,$uid,$event,$context,$readOnly,$payment,$receipt,$sale,$localOnly):array {
                // ConfigService::save, legacy capture and vendor capture serialize on this site.
                if(!$readOnly && !Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_MISSING','未找到当前站点');
                $config=(new ConfigService())->get($site,false);
                $profile=$this->profile($config,$context);
                $event=$this->persistedEvent($site,$event);
                if (!$readOnly && !empty($context['manual_scope']) && !$this->admitsManualFact($site,$context,$event))
                    throw new Problem('PILOT_SCOPE','此试单连接已失效或原事件不符合批准范围，未发单');
                if($receipt) return (new SaleReceiptSyncService())->handoff($site,$uid,$event,$config,$profile,$context,$readOnly);
                if($payment) return $this->payment($site,$uid,$event,$config,$profile,$context,$readOnly,$localOnly);
                if($sale) return $this->sale($site,$uid,$event,$config,$profile,$context,$readOnly);
                return $this->purchase($site,$event,$config,$profile,$context,$readOnly);
            };
            return $readOnly?$work():Db::transaction($work);
        } catch(Problem $e) {
            $reply=$this->state('blocked',$e->getMessage());
            // A failed lookup by this employee is not a change to the shared vendor receipt.
            if($readOnly || in_array($e->reason,['SITE_MISMATCH','SITE_MISSING','OTHER_CONNECTION'],true)) $reply['persist']=false;
            return $reply;
        } catch(CommonException $e) {
            return $this->state('blocked','没有本站原业务单或博远操作权限')+['persist'=>false];
        } catch(\Throwable $e) {
            return $this->state('unknown','未取得可验证的原任务结果，请核对原单；不要再次创建。')+['persist'=>false];
        }
    }

    private function profile(array $config,array $context): array
    {
        $mode=$context['mode']??'';
        $expected=['test'=>PaymentSyncService::UAT_ENDPOINT,'production'=>ProductionVoucher::ENDPOINT];
        $profile=$config['profiles'][$config['mode']]??[];
        if(!is_string($mode) || !isset($expected[$mode]) || $config['mode']!==$mode || ($profile['protocol']??null)!=='openapi_v153'
            || ($profile['endpoint']??null)!==$expected[$mode] || ($profile['gateway_path']??null)!=='/gateway.do' || empty($profile['connection_key']))
            throw new Problem('CONNECTION_MISMATCH','桥连接和博远当前环境或网关不一致；不在其他账套处理');
        foreach(['api_url'=>'endpoint','gateway_path'=>'gateway_path','app_id'=>'app_id','account_code'=>'account_id'] as $local=>$vendor) {
            if(!is_string($context[$local]??null) || $context[$local]==='' || $context[$local]!==($profile[$vendor]??null))
                throw new Problem('CONNECTION_MISMATCH','桥连接与博远当前客户账套、应用或网关不一致，请核对原连接');
        }
        return $profile;
    }

    private function persistedEvent(int $site,array $event): array
    {
        $id=$event['event_id']??''; $name=$event['event_name']??'';
        if(!is_string($id) || $id==='' || strlen($id)>128 || ($event['event_version']??null)!==1
            || !in_array($name,['erp.integration.document.confirmed.v1','erp.settlement.completed.v1'],true))
            throw new Problem('EVENT_INVALID','只支持本站原采购入库、已确认的原付款凭证');
        $raw=Db::name('erp_outbox_event')->where(['site_id'=>$site,'event_id'=>$id,'event_name'=>$name])->value('payload_json');
        if(!$raw) throw new Problem('EVENT_MISSING','找不到本站原始业务事件，不可用请求参数补造');
        $stored=Contract::decode($raw);
        unset($event['_delivery'],$stored['_delivery']);
        // Compare JSON canonically by object key; preserve list order and scalar types.
        if(!\addon\hsx_erp_integration\app\support\OriginalEventIdentity::same($stored,$event)) throw new Problem('EVENT_CHANGED','桥快照与本地原始业务事件不一致');
        return $stored;
    }

    private static function canonical(array $value): array
    {
        foreach($value as &$item) if(is_array($item)) $item=self::canonical($item);
        unset($item);
        ksort($value);
        return $value;
    }

    private function purchase(int $site,array $event,array $config,array $profile,array $context,bool $readOnly): array
    {
        $p=$event['payload']; $id=(int)($event['aggregate_id']??0);
        if(($p['kind']??'')!=='purchase' || $id<1 || ($event['aggregate_type']??null)!=='purchase' || ($p['source_document_id']??'')!=='purchase:'.$id
            || empty($p['batch_ref']) || empty($p['lines'])) throw new Problem('PURCHASE_INVALID','事件不是带有原采购批次与明细的设备入库');
        if(Db::name('erp_purchase_item')->where('site_id',$site)->where('purchase_order_id',$id)->where('asset_id',0)->count())
            throw new Problem('PURCHASE_MIXED','本期只支持设备采购；含数量库存或未绑定设备的原单须整单核对，不单独推送其中几台');
        $fact=$p['source_document_id'].':'.$p['batch_ref'];
        $row=$this->existing($site,$config,$profile,$fact,'purchase',$context);
        if(!$row) {
            if($readOnly) return $this->state('waiting','未关联博远采购任务；此次仅查看');
            $pilot=ProductionPurchasePilot::profile($site,$config,$profile);
            if (!$pilot) $this->canPrepare($config,$profile);
            elseif (!ProductionPurchasePilot::event($site,$event)) throw new Problem('PILOT_SCOPE','当前仅允许已批准的单台一元原采购，不接管其他业务');
            // In automatic mode only its original listener may initiate the configured workflow.
            if(($config['purchase_mode']??null)!=='manual') throw new Problem('PURCHASE_AUTOMATIC','当前为博远自动采购模式，请等待原监听接收，桥不另行触发');
            $service=new PurchaseSyncService();
            $received=$pilot?$service->capturePilot($site,(int)$context['operator_uid'],(int)$context['task_id'],$event):$service->capture($site,$event);
            if(empty($received['task_id'])) throw new Problem('PURCHASE_OUT_OF_SCOPE','原采购不在本次启用范围，未建立博远任务');
            $row=$this->existing($site,$config,$profile,$fact,'purchase',$context);
            if(!$row || (int)$row['id']!==(int)$received['task_id']) throw new Problem('TASK_MISSING','未能确认唯一原采购任务');
        }
        if(self::canonical(Contract::decode($row['snapshot_json']))!==self::canonical($p))
            throw new Problem('FACT_CONFLICT','同一原单批次的博远快照不一致，不能覆盖原任务');
        try { (new PurchaseSyncService())->assertClosureSourceCurrent($site,$p); }
        catch(Problem $e) {
            $issued=self::returnedNumber($row,Contract::decode($row['result_json']));
            return $this->state($row['side_effect']==='none'?'blocked':'unknown',$e->getMessage().'；请核对原厂商任务，不重发。',(int)$row['id'],$issued);
        }
        return $this->result($row,false);
    }

    private function payment(int $site,int $uid,array $event,array $config,array $profile,array $context,bool $readOnly,bool $localOnly=false): array
    {
        $p=$event['payload']; $id=(int)($p['settlement_id']??0);
        if($id<1 || $id!==(int)($event['aggregate_id']??0) || ($event['aggregate_type']??null)!=='settlement'
            || ($p['settlement_type']??null)!=='payment' || ($p['cash_direction']??null)!=='out' || empty($p['targets']))
            throw new Problem('PAYMENT_REQUIRED','只支持已确认的原ERP付款凭证，收款、折账不能当作付款登记');
        $fact='purchase-payment:settlement:'.$id;
        $row=$this->existing($site,$config,$profile,$fact,PaymentSyncService::KIND,$context);
        $origin=''; $expected=[];
        foreach($p['targets'] as $t) {
            $doc=$t['source_document_ref']??''; $line=$t['source_line_ref']??''; $target=(int)($t['target_id']??0);
            if(($t['target_type']??'')!=='payable' || ($t['source_type']??'')!=='purchase_asset' || $target<=0
                || !is_string($doc) || !preg_match('/^purchase:[1-9][0-9]*$/D',$doc)
                || !is_string($line) || !preg_match('/^purchase_line:[1-9][0-9]*$/D',$line)
                || ($origin!=='' && $doc!==$origin) || isset($expected[$line]))
                throw new Problem('MIXED_PAYMENT','付款分摊缺少唯一采购原单行、跨原单或有重复；不可拆分或猜测挂账');
            $origin=$doc; $expected[$line]=[$target,$this->amount($t['applied_amount']??null)];
        }
        $amount=$this->amount($p['amount']??null);
        if(array_sum(array_map(static fn($v)=>Contract::cents($v[1]),$expected))!==Contract::cents($amount))
            throw new Problem('TOTAL_MISMATCH','原付款金额与本次各行分摊合计不一致');
        if(!$row) {
            $bindings=Db::name('hsx_boyuan_binding')->where(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'kind'=>'line'])
                ->whereIn('local_ref',array_keys($expected))->select()->toArray();
            $parents=array_unique(array_map('intval',array_column($bindings,'task_id')));
            if(count($parents)>1) throw new Problem('PAYMENT_BATCH','该付款跨博远采购批次，不能拆成重复的整单付款登记');
            if(count($bindings)!==count($expected) || !$parents) return $this->state('waiting','请先核验该批次原采购入库及每行的博远回执，再准备原付款凭证');
            $parent=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'mode'=>$config['mode'],'id'=>(int)reset($parents),'kind'=>'purchase'])->find();
            if(!$parent || (Contract::decode($parent['snapshot_json'])['source_document_id']??'')!==$origin)
                throw new Problem('PAYMENT_BATCH','分摊行与原采购任务不匹配');
            if($parent['status']!=='succeeded') return $this->state('waiting','博远原采购尚未核验，付款凭证未登记');
            if($readOnly) return $this->state('waiting','原采购行已核验；此次仅查看，尚未准备原付款凭证');
            $this->canPrepare($config,$profile);
            $capture=(new PaymentSyncService())->capture($site,(int)$parent['id'],$uid,[
                'revision'=>(int)$parent['revision'],'mode'=>$config['mode'],'connection_key'=>$profile['connection_key'],'settlement_id'=>$id,
            ]);
            $row=$this->existing($site,$config,$profile,$fact,PaymentSyncService::KIND,$context);
            if(!$row || (int)$row['id']!==(int)$capture['task']['id']) throw new Problem('TASK_MISSING','未能确认唯一原付款凭证任务');
        }
        $snapshot=Contract::decode($row['snapshot_json']); $actual=[];
        foreach($snapshot['lines']??[] as $line) {
            $ref=$line['source_line_id']; if(isset($actual[$ref])) throw new Problem('PAYMENT_CONFLICT','厂商任务快照含重复分摊行');
            $actual[$ref]=[(int)$line['payable_id'],$line['amount']];
        }
        ksort($expected); ksort($actual);
        if($actual!==$expected || ($snapshot['source_document_id']??'')!==$origin || ($snapshot['amount']??'')!==$amount
            || (int)($snapshot['settlement_id']??0)!==$id || ($snapshot['settlement_no']??'')!==($p['settlement_no']??null)
            || (int)($snapshot['capital_account_id']??0)!==(int)($p['capital_account_id']??0))
            throw new Problem('PAYMENT_CONFLICT','已有付款凭证任务与本次分摊、金额、账户或原单不一致，不新建或覆盖');
        return $this->result($row,true,$localOnly);
    }

    private function amount($value): string
    {
        if((!is_int($value) && !is_float($value) && !is_string($value)) || !preg_match('/^(0|[1-9]\d{0,9})(?:\.\d{1,2})?$/D',(string)$value))
            throw new Problem('AMOUNT_INVALID','付款事件含无效金额');
        [$whole,$fraction]=array_pad(explode('.',(string)$value),2,'');
        $amount=$whole.'.'.str_pad($fraction,2,'0'); if(Contract::cents($amount)<=0) throw new Problem('AMOUNT_INVALID','付款及每笔分摊金额必须大于零');
        return $amount;
    }

    private function sale(int $site,int $uid,array $event,array $config,array $profile,array $context,bool $readOnly): array
    {
        $s=$event['payload']; $id=(int)($event['aggregate_id']??0); $number=$s['source_document_no']??'';
        if($config['mode']!=='test' || ($s['sale_type']??'')!=='wholesale' || ($s['payment_method_ref']??'')!=='method:挂账'
            || $id<1 || ($event['aggregate_type']??'')!=='sale' || ($s['source_document_id']??'')!=='sale:'.$id
            || !is_string($number) || $number==='' || empty($s['lines']))
            throw new Problem('SALE_SCOPE','仅支持已核验测试账套的同行设备挂账出库；正式、零售或现结均不可投递');
        $service=new WholesaleSyncService(); $fact=$s['source_document_id'].':wholesale-credit';
        $row=$this->existing($site,$config,$profile,$fact,WholesaleSyncService::KIND,$context);
        if($row && self::canonical(Contract::decode($row['snapshot_json']))!==self::canonical($s))
            throw new Problem('FACT_CONFLICT','已有批发任务与本次原单快照冲突，不新建或覆盖');
        $draft=!$row || ($row['side_effect']==='none' && (int)$row['attempts']===0);
        try { $service->checkOriginalLines($site,$profile['connection_key'],$s,$draft); }
        catch(Problem $e) {
            $issued=$row?self::returnedNumber($row,Contract::decode($row['result_json'])['receipt']??[]):'';
            return $this->state(!$draft?'unknown':($e->reason==='SALE_INBOUND_REQUIRED'?'waiting':'blocked'),$e->getMessage(),(int)($row['id']??0),$issued);
        }
        if(!$row) {
            if($readOnly) return $this->state('waiting','原采购设备已核验；本次仅查看，未准备批发任务');
            $this->canPrepare($config,$profile);
            $created=$service->prepareSource($site,$uid,$id,$number);
            $row=$this->existing($site,$config,$profile,$fact,WholesaleSyncService::KIND,$context);
            if(!$row || (int)$row['id']!==(int)$created['task_id'] || self::canonical(Contract::decode($row['snapshot_json']))!==self::canonical($s))
                throw new Problem('FACT_CONFLICT','未找到同一批发原单及相同快照，不继续处理');
        }
        $task=(int)$row['id']; $req=Contract::decode($row['request_json']); $r=Contract::decode($row['result_json']);
        $ext=self::returnedNumber($row,$r['receipt']??[]);
        if(($req['schema']??'')!==WholesaleGateway::SCHEMA) throw new Problem('TASK_SCHEMA','批发任务契约不匹配');
        if(!empty($row['resolution'])) return $this->state($draft?'blocked':'unknown','本地原销售已调整或退回，请人工核对原单，不重新出库',$task,$ext);
        if($row['status']==='succeeded') {
            $receipt=$r['receipt']??[]; $verify=$r['verification']??[];
            if($row['side_effect']!=='applied' || !$ext || ($receipt['created']??false)!==true || ($verify['verified']??false)!==true
                || ($verify['verification_level']??null)!=='wholesale_records_matched' || ($receipt['external_no']??null)!==$ext || ($verify['external_no']??null)!==$ext
                || empty($receipt['external_id']) || $receipt['external_id']!==($verify['external_id']??'') || ($receipt['lines']??[])!==($verify['lines']??null)
                || empty($req['company_id']) || $req['company_id']!==($verify['company_id']??null) || ($req['biz']['head']['cash_term']??null)!==0)
                return $this->state('unknown','原批发回执和逐台出库核验不完整，只查原单',$task,$ext);
            return $this->state('confirmed','测试账套原批发单及逐台设备出库明细已匹配；不是客户收款回执。',$task,$ext);
        }
        if(in_array($row['status'],['created_review','confirming'],true)) return $this->state($ext!==''?'linked':'unknown','原出库已尝试，等待原单的只读核验，不重新出库',$task,$ext);
        if(!$draft || in_array($row['status'],['processing','attention','needs_review'],true)) return $this->state('unknown','原出库可能已提交；只核对原任务，不重发或据候选单确认成功',$task,$ext);
        if(!in_array($row['status'],['awaiting_confirmation','pending'],true)) return $this->state('blocked','请核对原批发任务，不重新创建',$task,$ext);
        return $this->state('linked','已关联原挂账批发任务；提交和只读核验仍在原厂商工作台操作。',$task,$ext);
    }

    private function canPrepare(array $config,array $profile): void
    {
        if(empty($config['enabled']) || empty($config['purchase_enabled']) || !empty($config['paused']) || empty($profile['probe_at'])
            || empty($profile['contract_confirmed']) || empty($profile['app_secret_cipher']) || ($config['mode']==='production' && !ProductionVoucher::profile($profile)))
            throw new Problem('CONNECTION_DISABLED','博远连接未启用、已暂停或未通过核验，未准备新的原单');
    }

    private function existing(int $site,array $config,array $profile,string $fact,string $kind,array $context): ?array
    {
        $query=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>$config['mode'],'fact_key'=>$fact]);
        $row=(clone $query)->where('connection_key',$profile['connection_key'])->find();
        $pinned=(int)($context['vendor_task_id']??0);
        if(($pinned>0 && (!$row || (int)$row['id']!==$pinned)) || ($row && $row['kind']!==$kind))
            throw new Problem('VENDOR_TASK_CONFLICT','已关联的厂商任务与本站、原事实或目标连接不一致，不替换任务');
        if(!$row && $config['mode']==='production' && $query->where('connection_key','<>',$profile['connection_key'])->count())
            throw new Problem('OTHER_CONNECTION','同一原事实已在其他正式连接留下记录，请核对原回执，不重新建单');
        return $row?:null;
    }

    private function result(array $row,bool $payment,bool $localOnly=false): array
    {
        $request=Contract::decode($row['request_json']); $result=Contract::decode($row['result_json']);
        if(($request['schema']??'')!==($payment?PaymentSyncService::SCHEMA:PurchaseSyncService::SCHEMA))
            throw new Problem('TASK_SCHEMA','原厂商任务类型或协议不匹配，不另建任务');
        $id=(int)$row['id']; $ext=self::returnedNumber($row,$result); $status=(string)$row['status'];
        if(!empty($row['resolution'])) return $this->state($row['side_effect']==='none'?'blocked':'unknown','原任务已标记需复核，保留已取得的原单回执，不替换任务',$id,$ext);
        if(!$payment && ($unsent=ProductionPurchasePilot::unsentNotice($row,$result))!=='') return $this->state('blocked',$unsent,$id);
        if(in_array($status,['succeeded',PaymentSyncService::REGISTERED],true)) {
            $matched=$ext!=='' && ($result['external_no']??null)===$ext && !empty($result['external_id']) && ($request['stage']??'')==='frozen';
            if($payment) {
                $matched=$matched && PaymentSyncService::isRegistered($row) && ($result['created']??null)===true && ($result['payment_registered']??null)===true
                    && ($result['verification_level']??null)==='payment_create_receipt_matched' && ($result['amount']??'')===($request['amount']??null)
                    && ($result['verified']??null)===false && ($result['financial_posting_verified']??null)===false
                    && ($result['bank_transfer_verified']??null)===false && ($result['allocation_applied_verified']??null)===false;
            } else {
                $matched=$matched && $status==='succeeded' && $row['side_effect']==='applied' && ($result['verified']??null)===true
                    && ($result['verification_level']??null)==='purchase_imei_records_matched';
            }
            if(!$matched) return $this->state('unknown','原任务缺少匹配冻结请求的有效回执，只核对原单，不重建',$id);
            $reply=$this->state('confirmed',$payment?'原付款凭证已登记；不表示银行转账、财务过账或原应付核销成功。':'采购及入库串号已匹配；不代表已付货款。',$id,$ext);
            if($payment && !$localOnly) {
                try { $reply['allocation']=PurchaseSettlementPort::forVoucher($row); }
                catch(\Throwable $e) {
                    // Allocation lookup is a separate observation; its outage must not erase a matched voucher.
                    $reply['allocation']=['status'=>'pending','verified'=>false,'applied_amount'=>null,'remaining_amount'=>null,'reason'=>'allocation_unavailable'];
                }
            }
            return $reply;
        }
        if($status==='created_review' || $status==='confirming') return $this->state($ext===''?'unknown':'linked',
            $ext===''?'原任务仅持有待查单号，没有匹配的厂商回执；只能只读核验。':'已有博远采购原单号；请在原任务只读核验，不重新创建',$id,$ext);
        if(in_array($status,['processing','attention','needs_review'],true) || $row['side_effect']!=='none' || (int)$row['attempts']>0)
            return $this->state('unknown','原操作处理中或结果不确定，请仅核对原厂商任务，禁止重发',$id,$ext);
        if(!in_array($status,['awaiting_confirmation','pending'],true) || !empty($row['resolution']))
            return $this->state('blocked','原任务不可提交，请在本张原单核对原因，不重新创建',$id,$ext);
        return $this->state('linked',$status==='pending'?'原任务已排队，请刷新本单结果，不要重复提交。':($payment?'已关联本站付款凭证，请在本单核对后同步登记，不需要再次打款。':'采购资料已准备，请在本单核对后提交，尚未发送博远。'),$id);
    }

    private function state(string $status,string $reason,int $id=0,string $number=''): array
    {
        return ['status'=>$status,'vendor_task_id'=>$id,'external_no'=>$number,'reason'=>$reason,'message'=>$reason];
    }

    /** external_no may be a manually provided search hint. Export only an issued or verified number. */
    private static function returnedNumber(array $row,array $result): string
    {
        $num=(string)($row['external_no']??'');
        return $num!=='' && ($result['external_no']??null)===$num && !empty($result['external_id'])
            && (($result['created']??false)===true || ($result['verified']??false)===true) ? $num : '';
    }
}
