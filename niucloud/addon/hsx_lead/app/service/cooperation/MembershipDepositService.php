<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\member\{MemberAccountTypeDict,MemberAccountChangeTypeDict};
use app\dict\pay\{PayDict,RefundDict};
use app\service\core\pay\CoreRefundService;
use core\exception\CommonException;
use think\facade\Db;

/** Deposit exit is a reviewed return of held money, never a service-fee refund.
 * Requests and stable source refund identities use the existing order JSON.
 * No blacklist, personal level, paid receipt or business table is erased.
 */
final class MembershipDepositService
{
    private const KEY='membership_deposit_return';
    private const EXIT_STATUSES=['pending','approved','refunding','refunded','attention'];
    private const PURCHASE_BLOCKING_STATUSES=['pending','approved','refunding','attention'];
    private const LABELS=['pending'=>'待平台审核','approved'=>'已审核，待发起原路退款','refunding'=>'原路退款处理中','refunded'=>'押金已退还，已退出协作','rejected'=>'申请未通过','attention'=>'原退款结果待核对'];
    public function __construct(private Context $context){}

    /** Read-only and non-recursive: usable by membership and matching guards. */
    public static function exited(int $site,int $merchant,bool $lock=false):bool
    {
        return !empty(self::exitedMany($site,[$merchant],$lock)[$merchant]);
    }

    /** Same latest embedded request as current(), batched for read-only lists. */
    public static function exitedMany(int $site,array $merchantIds,bool $lock=false):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$merchantIds),static fn(int $id):bool=>$id>0)));
        if(!$ids)return[];$out=[];$completed=[];
        $rows=self::requestRows($site,$ids,$lock);$epochs=self::epochsFromRows($site,$rows);
        foreach($rows as$row){$id=(int)$row['merchant_id'];if(array_key_exists($id,$out))continue;$request=Data::decode($row['rule_json'])[self::KEY]??null;if(!is_array($request))continue;$status=$request['status']??'';$out[$id]=in_array($status,self::EXIT_STATUSES,true);if($status==='refunded')$completed[]=$id;}
        if(!$completed)return$out;
        // A new draft or a client success callback cannot end the old exit.
        // Only a freshly quoted membership in this completed-exit epoch, with
        // an exact native paid receipt and no grant exception, can reopen it.
        $query=Store::query('coop_order',$site)->whereIn('merchant_id',$completed)->where('purpose','membership')->where('status','paid')->order('id');if($lock)$query->lock(true);
        $candidates=[];
        foreach($query->select()->toArray()as$order){$id=(int)$order['merchant_id'];$rule=Data::decode($order['rule_json']);if(empty($epochs[$id])||($rule['membership_deposit_epoch']??'')!==$epochs[$id]||!empty($rule['requires_manual_review']))continue;
            try{EntityMembershipService::assertSnapshot($order,$rule);}catch(CommonException$error){continue;}$candidates[(int)$order['id']]=$order;}
        if(!$candidates)return$out;
        $payments=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->whereIn('trade_id',array_keys($candidates))->where('status',PayDict::STATUS_FINISH);if($lock)$payments->lock(true);
        foreach($payments->select()->toArray()as$pay){$order=$candidates[(int)$pay['trade_id']]??null;if(!$order||$pay['main_type']!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$order['member_id']||$pay['out_trade_no']!==$order['out_trade_no'])continue;
            try{if(Rules::cents((string)$pay['money'])===Rules::cents((string)$order['amount']))$out[(int)$order['merchant_id']]=false;}catch(CommonException$error){continue;}}
        return$out;
    }

    /** Exit of old rights is distinct from a refund still blocking new money. */
    public static function purchaseState(int $site,int $merchant,bool $lock=false):array
    {
        $row=self::current($site,$merchant,$lock);$status=(string)($row['request']['status']??'');
        return ['deposit_purchase_blocked'=>in_array($status,self::PURCHASE_BLOCKING_STATUSES,true),'deposit_request_status'=>$status];
    }

    /** Stable scope of new entitlements after a completed exit. Historical
     * receipts and refund facts are never rewritten or used again as credit.
     */
    public static function membershipEpoch(int $site,int $merchant,bool $lock=false):string
    {
        return self::membershipEpochs($site,[$merchant],$lock)[$merchant]??'';
    }
    public static function membershipEpochs(int $site,array $merchantIds,bool $lock=false):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$merchantIds),static fn(int $id):bool=>$id>0)));
        return $ids?self::epochsFromRows($site,self::requestRows($site,$ids,$lock)):[];
    }
    private static function requestRows(int $site,array $ids,bool $lock):array
    {
        $query=Store::query('coop_order',$site)->whereIn('merchant_id',$ids)->where('purpose','membership')->whereLike('rule_json','%"'.self::KEY.'"%')->field('id,merchant_id,rule_json')->order('id desc');if($lock)$query->lock(true);return$query->select()->toArray();
    }
    private static function epochsFromRows(int $site,array $rows):array
    {
        $out=[];
        foreach($rows as$row){$id=(int)$row['merchant_id'];if(isset($out[$id]))continue;$rule=Data::decode($row['rule_json']);$history=$rule['membership_deposit_return_history']??[];$requests=is_array($history)?array_reverse($history):[];array_unshift($requests,$rule[self::KEY]??null);
            foreach($requests as$request){if(!is_array($request)||($request['status']??'')!=='refunded')continue;$out[$id]=hash('sha256',Data::json([$site,$id,(int)$row['id'],(string)($request['request_id']??''),(int)($request['created_at']??0)]));break;}}
        return$out;
    }

    public function summary():array
    {
        $merchant=TeamService::resolve($this->context);$id=(int)$merchant['id'];$owner=(int)$merchant['member_id']===$this->context->memberId;
        $balances=MembershipCostService::balances($this->context->site,$id);$blocking=$this->blocking($id);$current=self::current($this->context->site,$id);
        if($balances['review_required'])$blocking[]=['key'=>'financial_review','title'=>'押金、付款或退款记录需要平台核对','count'=>1];
        if(Rules::cents($balances['balance'])>0)try{$this->sources($id,false);}catch(CommonException$error){$blocking[]=['key'=>'refund_route','title'=>$error->getMessage(),'count'=>1];}
        $active=self::exited($this->context->site,$id);
        return ['merchant_id'=>$id,'is_owner'=>$owner,'balance'=>$balances['balance'],'can_apply'=>$owner&&!$active&&!$blocking&&Rules::cents($balances['balance'])>0,'blocking_reasons'=>$blocking,'cooperation_exited'=>$active,'request'=>$current?$this->present($current['order'],$current['request'],false):null]+self::purchaseState($this->context->site,$id);
    }

    public function apply(array $input):array
    {
        if(!MembershipCostService::flag($input['confirm']??false,'确认申请退出并退还押金'))throw new CommonException('请明确确认申请后暂停新的协作，平台审核后原路退还押金');
        $request=Store::requestId($input);$reason=Data::text($input,'reason',300);$merchant=TeamService::assertOwner($this->context);$site=$this->context->site;$mid=(int)$merchant['id'];
        Db::transaction(function()use($site,$mid,$request,$reason):void{
            PromotionService::lockSite($site);TeamService::assertOwner($this->context,$mid,true);
            $current=self::current($site,$mid,true);
            if($current&&($current['request']['request_id']??'')===$request)return;
            // A delayed POST for a rejected older request is still a replay,
            // not fresh consent to suspend cooperation again. Keep all stored
            // histories intact and acknowledge it with the current summary.
            if(self::seenRequest($site,$mid,$request,true))return;
            if($current&&self::exited($site,$mid,true))throw new CommonException('已有押金退还申请，请核对原申请，不会重复退款');
            $blocking=$this->blocking($mid,true);if($blocking)throw new CommonException($blocking[0]['title'].'；请先完成后再申请');
            $balances=MembershipCostService::balances($site,$mid,true);if($balances['review_required'])throw new CommonException('原押金或退款记录需要平台核对，暂不能申请');
            $sources=$this->sources($mid,true);$amount=array_sum(array_column($sources,'amount_cents'));
            if($amount<=0||$amount!==Rules::cents($balances['balance']))throw new CommonException('尚无可核实的可退押金，或押金合计需要平台核对');
            $anchor=Store::row('coop_order',$site,(int)$sources[0]['order_id'],true);$rule=Data::decode($anchor['rule_json']);
            if(isset($rule[self::KEY]))$rule['membership_deposit_return_history'][]=$rule[self::KEY];
            $items=[];foreach($sources as $source)$items[]=['order_id'=>$source['order_id'],'amount'=>Rules::amount($source['amount_cents']),'refund_no'=>'','status'=>'reserved','dispatch_started_at'=>0];
            $rule[self::KEY]=['id'=>(int)$anchor['id'],'merchant_id'=>$mid,'member_id'=>$this->context->memberId,'request_id'=>$request,'status'=>'pending','amount'=>Rules::amount($amount),'reason'=>$reason,'review_reason'=>'','created_at'=>time(),'update_at'=>time(),'review_at'=>0,'reviewer_uid'=>0,'sources'=>$items];
            self::save((int)$anchor['id'],$site,$rule);$this->context->audit('membership_deposit',(int)$anchor['id'],'applied',['merchant_id'=>$mid,'amount'=>Rules::amount($amount)]);
        });return $this->summary();
    }

    public function adminList(array $params=[]):array
    {
        $this->admin('manage');$status=Data::text($params,'status',20);if($status!==''&&!isset(self::LABELS[$status]))throw new CommonException('押金申请状态无效');
        $keyword=Data::text($params,'keyword',80);$rows=[];
        foreach(Store::query('coop_order',$this->context->site)->where('purpose','membership')->whereLike('rule_json','%"'.self::KEY.'"%')->order('id desc')->select()->toArray()as$order){$rule=Data::decode($order['rule_json']);$r=$rule[self::KEY]??null;if(!is_array($r)||($status!==''&&($r['status']??'')!==$status))continue;$row=$this->present($order,$r,true);if($keyword!==''&&mb_stripos($row['merchant_name'].' '.$row['member_name'].' '.$row['id'],$keyword)===false)continue;$rows[]=$row;}
        return Store::pageArray($rows,$params);
    }

    public function detail(int $id):array
    {
        $this->admin('detail');[$order,$request]=$this->anchor($id);return $this->present($order,$request,true);
    }

    public function review(int $id,array $input):array
    {
        $this->admin('review');$decision=Data::text($input,'decision',10,true);if(!in_array($decision,['approve','reject'],true))throw new CommonException('请选择通过或拒绝');$reason=Data::text($input,'reason',300);if($decision==='reject'&&$reason==='')throw new CommonException('请填写拒绝原因');$version=Data::text($input,'request_version',64,true);if(!preg_match('/^[a-f0-9]{64}$/D',$version))throw new CommonException('申请核对标识无效，请重新读取原申请');
        Db::transaction(function()use($id,$decision,$reason,$version):void{
            PromotionService::lockSite($this->context->site);[$order,$r]=$this->anchor($id,true);
            if(!hash_equals(self::version($r),$version))throw new CommonException('申请已发生变化，请重新读取并确认本次申请；未执行审核或退款');
            if(($r['status']??'')!=='pending'){if($decision==='approve'&&in_array($r['status'],['approved','refunding','refunded','attention'],true))return;if($decision==='reject'&&$r['status']==='rejected')return;throw new CommonException('申请状态已经变化，请重新核对原申请');}
            if($decision==='reject'){$r['status']='rejected';}
            else{
                $blocks=$this->blocking((int)$order['merchant_id'],true);if($blocks)throw new CommonException($blocks[0]['title'].'；当前未发起退款');
                $balance=MembershipCostService::balances($this->context->site,(int)$order['merchant_id'],true);if($balance['review_required']||$balance['balance']!==$r['amount'])throw new CommonException('押金或原退款状态已变化，当前未发起退款');
                $fresh=$this->sources((int)$order['merchant_id'],true);$map=[];foreach($fresh as$s)$map[$s['order_id']]=$s['amount_cents'];
                foreach($r['sources'] as &$source){if(($map[$source['order_id']]??-1)!==Rules::cents($source['amount']))throw new CommonException('原押金来源已变化，未创建退款');$original=Store::row('coop_order',$this->context->site,(int)$source['order_id'],true);$originalRule=Data::decode($original['rule_json']);
                    $reservation=$this->reserveRefund($original,$source['amount']);$number=$reservation['refund_no'];
                    $source['refund_no']=$number;$source['refund_record_id']=$reservation['id'];$source['status']='reserved';$originalRule['membership_deposit_return_anchor']=$id;$originalRule['membership_refund_allocations'][$number]=['deposit'=>$source['amount'],'service'=>'0.00','experience'=>'0.00','quantity'=>'0.00'];self::save((int)$original['id'],$this->context->site,$originalRule);
                }unset($source);$r['status']='approved';
            }
            $r['review_reason']=$reason;$r['review_at']=time();$r['reviewer_uid']=$this->context->uid;$r['update_at']=time();$latest=Store::row('coop_order',$this->context->site,$id,true);$rule=Data::decode($latest['rule_json']);$rule[self::KEY]=$r;self::save($id,$this->context->site,$rule);$this->context->audit('membership_deposit',$id,$decision,['merchant_id'=>(int)$order['merchant_id'],'amount'=>$r['amount']]);
        });
        if($decision==='approve')$this->dispatch($id);
        return $this->detailForAllowed($id);
    }

    /** Queries only previously persisted refund numbers. Never first-dispatches. */
    public function refresh(int $id):array
    {
        $this->admin('refresh');$this->recover($id);return $this->detailForAllowed($id);
    }

    /** Bounded compensation for already submitted originals only. A scheduler
     * never approves an application, creates a refund or dispatches a transfer.
     */
    public static function tick(int $limit=20):array
    {
        $json="CASE WHEN JSON_VALID(rule_json) THEN rule_json ELSE '{}' END";
        $rows=Db::name('hsx_lead_coop_order')->where('purpose','membership')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT($json,'$.membership_deposit_return.status')) IN ('refunding','attention')")
            ->order('update_time,id')->limit(max(1,min(50,$limit)))->select()->toArray();
        $out=['examined'=>0,'refunded'=>0,'attention'=>0];
        foreach($rows as$row){
            $out['examined']++;$service=new self(new Context((int)$row['site_id'],0,(int)$row['member_id']));
            try{$service->recover((int)$row['id']);}catch(\Throwable$error){
                // Preserve the durable original; a broken row must not starve
                // later requests, or be turned into an untracked new transfer.
                \think\facade\Log::warning('押金原退款补偿待核对：site='.(int)$row['site_id'].', order='.(int)$row['id'].', type='.get_class($error));
            }
            $current=Store::row('coop_order',(int)$row['site_id'],(int)$row['id']);
            $status=Data::decode($current['rule_json'])[self::KEY]['status']??'attention';$out[$status==='refunded'?'refunded':'attention']++;
            Store::query('coop_order',(int)$row['site_id'])->where('id',$row['id'])->update(['update_time'=>time()]);
        }
        return$out;
    }

    private function recover(int $id):void
    {
        [$order,$r]=$this->anchor($id);$failed=false;
        foreach($r['sources'] as$index=>$source){
            if(empty($source['refund_no']))continue;
            try{
                $refund=$this->refundRecord($order,$source);if(!$refund)continue;
                $original=Store::row('coop_order',$this->context->site,(int)$source['order_id']);
                if(ManualRefundService::resume($this->context->site,$original,$refund))continue;
                if(empty($source['dispatch_started_at']))continue;
                if($refund['status']===RefundDict::SUCCESS){
                    // The query path may have saved SUCCESS before a listener
                    // failed. Replay only the established success hooks inside
                    // one transaction; do not call refund() or credit again.
                    Db::transaction(function()use($order,$source):void{
                        PromotionService::lockSite($this->context->site);$current=$this->refundRecord($order,$source,true);
                        if(!$current||$current['status']!==RefundDict::SUCCESS)throw new CommonException('原退款成功凭据已变化，请核对');
                        if((new CoreRefundService())->refundSuccess($this->context->site,['out_trade_no'=>$current['out_trade_no'],'refund_no'=>$current['refund_no'],'trade_type'=>PaymentService::TRADE_TYPE,'trade_id'=>(string)$source['order_id']])!==true)throw new CommonException('原押金退款业务回调尚未完成');
                    });
                }elseif(in_array($refund['status'],[RefundDict::WAIT,RefundDict::DEALING],true)){
                    if(!$this->recoverBalanceReceipt($order,$source))(new CoreRefundService())->check($this->context->site,['out_trade_no'=>$refund['out_trade_no'],'refund_no'=>$refund['refund_no']]);
                }
            }catch(\Throwable$error){$failed=true;$this->markAttention($id,$index);}
        }
        if(!$failed)$this->reconcile($id);
    }

    /** Called only after the normal matching-payment refund-success listener. */
    public static function refundConfirmed(int $site,array $source):void
    {
        $rule=Data::decode($source['rule_json']);$anchor=(int)($rule['membership_deposit_return_anchor']??0);
        if($anchor<=0)return;
        (new self(new Context($site,0,(int)$source['member_id'])))->reconcile($anchor);
    }

    /** Retain the framework's refund quota and payment validation. Its current
     * create_no() restarts Snowflake's sequence on every call, so two fresh rows
     * can share a millisecond identity. Before any dispatch, re-key ONLY the
     * strictly identified row just inserted on this same transactional PDO.
     * No historical or already submitted refund identity is ever rewritten.
     */
    private function reserveRefund(array $original,string $amount):array
    {
        PaymentRefundService::assertRefundRoute($original,null,true);
        return FrameworkRefundReservation::create($this->context->site,$original,$amount,'商户正常退出，仅退还押金','LD');
    }

    /** Exact original-receipt verification, also used by the bounded scheduler.
     * The official balance driver
     * commits its account credit before business callbacks. If that callback
     * failed, an exact unique original refund ledger can prove receipt without
     * ever calling refund() or crediting money again. Other channels are never
     * inferred from local balance, notes, time proximity or approximate values.
     */
    private function recoverBalanceReceipt(array $anchor,array $source):bool
    {
        return Db::transaction(function()use($anchor,$source):bool{
            $site=$this->context->site;PromotionService::lockSite($site);[$current,$request]=$this->anchor((int)$anchor['id'],true);
            if(!in_array($request['status'],['approved','refunding','attention'],true))return false;
            $originalSource=null;foreach($request['sources']as$item)if((int)$item['order_id']===(int)$source['order_id']&&($item['refund_no']??'')===($source['refund_no']??'')){$originalSource=$item;break;}
            if(!$originalSource||empty($originalSource['dispatch_started_at']))return false;
            $refund=$this->refundRecord($current,$originalSource,true);if(!$refund)return false;
            if($refund['status']===RefundDict::SUCCESS)return true;
            if(!in_array($refund['status'],[RefundDict::WAIT,RefundDict::DEALING],true)||$refund['type']!==PayDict::BALANCEPAY||($refund['refund_type']??'')!==RefundDict::BACK)return false;
            $original=Store::row('coop_order',$site,(int)$originalSource['order_id'],true);$rule=Data::decode($original['rule_json']);EntityMembershipService::assertSnapshot($original,$rule);
            $allocation=$rule['membership_refund_allocations'][(string)$refund['refund_no']]??null;
            if($allocation!==['deposit'=>$originalSource['amount'],'service'=>'0.00','experience'=>'0.00','quantity'=>'0.00'])return false;
            $payments=Db::name('pay')->where('site_id',$site)->where('out_trade_no',$refund['out_trade_no'])->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$original['id'])->lock(true)->select()->toArray();
            if(count($payments)!==1)return false;$pay=$payments[0];
            if($pay['type']!==PayDict::BALANCEPAY||$pay['main_type']!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$original['member_id']||(int)$pay['status']!==(int)PayDict::STATUS_FINISH||Rules::cents((string)$pay['money'])!==Rules::cents((string)$original['amount'])||$pay['channel']!==$refund['channel'])return false;
            $dictionary=MemberAccountChangeTypeDict::getType(MemberAccountTypeDict::BALANCE);$official=$dictionary['order_refund']??null;
            if(!is_array($official)||(int)($official['inc']??0)!==1||(int)($official['dec']??1)!==0||(int)($official['is_change_get']??1)!==0)return false;
            // Deliberately read every entry for this exact refund identity, not
            // just a matching positive row: duplicates or a reversal fail shut.
            $logs=Db::name('member_account_log')->where('site_id',$site)->where('related_id',(string)$refund['refund_no'])->lock(true)->select()->toArray();
            if(count($logs)!==1)return false;$log=$logs[0];$amount=Rules::cents((string)$refund['money']);
            if($amount<=0||(int)$log['member_id']!==(int)$original['member_id']||$log['account_type']!==MemberAccountTypeDict::BALANCE||$log['from_type']!=='order_refund'||Rules::cents((string)$log['account_data'])!==$amount)return false;
            if((new CoreRefundService())->refundSuccess($site,['out_trade_no'=>$refund['out_trade_no'],'refund_no'=>$refund['refund_no'],'trade_type'=>PaymentService::TRADE_TYPE,'trade_id'=>(string)$original['id']])!==true)throw new CommonException('原余额退款回调仍未完成，请平台核对；未再次退钱');
            $this->context->audit('membership_deposit',(int)$current['id'],'balance_refund_receipt_reconciled',['source_order_id'=>(int)$original['id'],'refund_no'=>(string)$refund['refund_no'],'account_log_id'=>(int)$log['id'],'amount'=>Rules::amount($amount)]);
            return true;
        });
    }

    private function dispatch(int $id):void
    {
        // Every first dispatch claims a stable refund identity before an external call.
        [$order,$r]=$this->anchor($id);foreach($r['sources'] as $index=>$source){
            $send=Db::transaction(function()use($id,$index):?string{PromotionService::lockSite($this->context->site);[$order,$r]=$this->anchor($id,true);$s=$r['sources'][$index]??null;if(!$s||empty($s['refund_no'])||!empty($s['dispatch_started_at'])||!in_array($r['status'],['approved','refunding','attention'],true))return null;PaymentRefundService::assertRefundRoute(Store::row('coop_order',$this->context->site,(int)$s['order_id'],true),null,true);$refund=$this->refundRecord($order,$s,true);if(!$refund||$refund['status']!==RefundDict::WAIT)throw new CommonException('原退款记录状态无法核对，未重发退款');
                if($refund['type']===PayDict::OFFLINEPAY){$r['sources'][$index]['manual_refund_required']=true;$r['update_at']=time();$rule=Data::decode($order['rule_json']);$rule[self::KEY]=$r;self::save($id,$this->context->site,$rule);return null;}
                $r['sources'][$index]['dispatch_started_at']=time();$r['sources'][$index]['status']='dispatching';$r['status']='refunding';$r['update_at']=time();$rule=Data::decode(Store::row('coop_order',$this->context->site,$id,true)['rule_json']);$rule[self::KEY]=$r;self::save($id,$this->context->site,$rule);return(string)$s['refund_no'];});
            if($send!==null)try{(new CoreRefundService())->refund($this->context->site,$send,'',RefundDict::BACK);}catch(\Throwable $error){$this->markAttention($id,$index);}
        }$this->reconcile($id);
    }

    private function markAttention(int $id,int $index):void
    {
        Db::transaction(function()use($id,$index):void{PromotionService::lockSite($this->context->site);[$order,$r]=$this->anchor($id,true);$r['sources'][$index]['status']='attention';$r['status']='attention';$r['update_at']=time();$rule=Data::decode($order['rule_json']);$rule[self::KEY]=$r;self::save($id,$this->context->site,$rule);});
    }

    private function reconcile(int $id):void
    {
        Db::transaction(function()use($id):void{PromotionService::lockSite($this->context->site);[$order,$r]=$this->anchor($id,true);if(in_array($r['status'],['pending','rejected'],true))return;$all=true;$attention=false;$pending=false;foreach($r['sources'] as &$s){$refund=$this->refundRecord($order,$s,true);if($refund&&$refund['status']===RefundDict::SUCCESS){$s['status']='success';}else{$all=false;if(!$refund||in_array($refund['status'],[RefundDict::FAIL,RefundDict::CANCEL],true)||($s['status']??'')==='attention'){$s['status']='attention';$attention=true;}elseif(!empty($s['dispatch_started_at'])){$s['status']='pending';$pending=true;}}}unset($s);$r['status']=$all?'refunded':($attention?'attention':($pending?'refunding':'approved'));$r['update_at']=time();$rule=Data::decode(Store::row('coop_order',$this->context->site,$id,true)['rule_json']);$rule[self::KEY]=$r;self::save($id,$this->context->site,$rule);});
    }

    private function refundRecord(array $anchor,array $source,bool $lock=false):?array
    {
        if(empty($source['refund_no']))return null;$q=Db::name('pay_refund')->where('site_id',$this->context->site)->where('refund_no',$source['refund_no']);if($lock)$q->lock(true);$rows=$q->select()->toArray();if(count($rows)!==1)throw new CommonException('原退款编号缺失或重复，请平台核对，未重复退款');$row=$rows[0];$original=Store::row('coop_order',$this->context->site,(int)$source['order_id'],$lock);if((int)$original['merchant_id']!==(int)$anchor['merchant_id']||$row['out_trade_no']!==$original['out_trade_no']||$row['trade_type']!==PaymentService::TRADE_TYPE||(int)$row['trade_id']!==(int)$original['id']||(isset($source['refund_record_id'])&&(int)$source['refund_record_id']!==(int)$row['id'])||Rules::cents((string)$row['money'])!==Rules::cents($source['amount']))throw new CommonException('原退款来源或金额不符，请平台核对');
        $pay=OrderPaymentReadService::payment($original,$lock);if(!$pay)throw new CommonException('原押金收款凭据无法核对');OrderPaymentReadService::assertManualSource($original,$pay);
        if($row['type']===PayDict::OFFLINEPAY&&$row['status']===RefundDict::SUCCESS)ManualRefundService::assertRefundReceipt($this->context->site,$original,$row);return$row;
    }
    public static function manualConfirmation(int $site,array $source,array $refund,bool $mark): bool
    {
        $sourceRule=Data::decode($source['rule_json']);$anchor=(int)($sourceRule['membership_deposit_return_anchor']??0);if($anchor<=0)return false;
        $order=Store::row('coop_order',$site,$anchor,true);$rule=Data::decode($order['rule_json']);$r=$rule[self::KEY]??[];
        if($source['purpose']!=='membership'||(int)$order['merchant_id']!==(int)$source['merchant_id']||!in_array($r['status']??'',['approved','refunding','attention','refunded'],true))throw new CommonException('原押金退款尚未审核通过');
        $found=false;foreach($r['sources']??[]as$index=>$item){if((int)$item['order_id']!==(int)$source['id']||($item['refund_no']??'')!==$refund['refund_no'])continue;
            if((int)($item['refund_record_id']??0)!==(int)$refund['id']||Rules::cents((string)$item['amount'])!==Rules::cents((string)$refund['money'])||$refund['type']!==PayDict::OFFLINEPAY)throw new CommonException('原押金退款凭据不一致');$found=true;
            if($mark&&$r['status']!=='refunded'){$r['sources'][$index]['dispatch_started_at']=($item['dispatch_started_at']??0)?:time();$r['sources'][$index]['status']='pending';$r['sources'][$index]['manual_refund_required']=true;$r['status']='refunding';$r['update_at']=time();}
        }
        if(!$found)throw new CommonException('本笔退款不属于原押金退还申请');if($mark){$rule[self::KEY]=$r;self::save($anchor,$site,$rule);}return true;
    }

    private function sources(int $merchant,bool $lock):array
    {
        $q=Store::query('coop_order',$this->context->site)->where('merchant_id',$merchant)->where('purpose','membership')->where('status','paid')->order('id');if($lock)$q->lock(true);$out=[];
        foreach($q->select()->toArray()as$order){
            $rule=Data::decode($order['rule_json']);if(($rule['cost_schema']??'')!==MembershipCostService::SCHEMA)continue;EntityMembershipService::assertSnapshot($order,$rule);
            $pay=Db::name('pay')->where('site_id',$this->context->site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$order['id'])->where('out_trade_no',$order['out_trade_no'])->where('status',PayDict::STATUS_FINISH);if($lock)$pay->lock(true);$p=$pay->find();
            if(!$p||$p['main_type']!==PayDict::MEMBER||(int)$p['main_id']!==(int)$order['member_id']||Rules::cents((string)$p['money'])!==Rules::cents((string)$order['amount']))throw new CommonException('原押金付款凭据无法核对');
            OrderPaymentReadService::assertManualSource($order,$p);
            $rq=Db::name('pay_refund')->where('site_id',$this->context->site)->where('out_trade_no',$order['out_trade_no']);if($lock)$rq->lock(true);$ref=MembershipCostService::refundedComponents($order,$rule,$rq->select()->toArray());
            if($ref['review']||$ref['pending']||!empty($rule['requires_manual_review']))throw new CommonException('原押金或退款仍需平台核对');$amount=Rules::cents($rule['deposit_amount'])-$ref['deposit'];if($amount>0){PaymentRefundService::assertRefundRoute($order,$p,$lock);$out[]=['order_id'=>(int)$order['id'],'amount_cents'=>$amount];}
        }return$out;
    }

    private function blocking(int $merchant,bool $lock=false):array
    {
        return self::blockingFor($this->context->site,$merchant,$lock);
    }
    /** Shared deposit release prerequisite; neither chooses a refund nor changes merchant state. */
    public static function blockingFor(int $site,int $merchant,bool $lock=false):array
    {
        $out=[];$q=Store::query('collaboration',$site)->where(function($q)use($merchant):void{$q->where('publisher_id',$merchant)->whereOr('receiver_id',$merchant);})->whereIn('status',['selecting','assigned','following','unpaid']);if($lock)$q->lock(true);$count=count($q->field('id')->select()->toArray());if($count)$out[]=['key'=>'unfinished_collaboration','title'=>'还有未完成的线索或待付佣协作','count'=>$count];
        $q=Store::query('coop_order',$site)->where('merchant_id',$merchant)->where('status','unpaid');if($lock)$q->lock(true);$count=count($q->field('id')->select()->toArray());if($count)$out[]=['key'=>'unpaid_order','title'=>'还有未处理的付款订单','count'=>$count];return$out;
    }

    private static function current(int $site,int $merchant,bool $lock=false):?array
    {
        $q=Store::query('coop_order',$site)->where('merchant_id',$merchant)->where('purpose','membership')->whereLike('rule_json','%"'.self::KEY.'"%')->order('id desc');if($lock)$q->lock(true);foreach($q->select()->toArray()as$order){$r=Data::decode($order['rule_json'])[self::KEY]??null;if(is_array($r))return['order'=>$order,'request'=>$r];}return null;
    }
    private static function seenRequest(int $site,int $merchant,string $request,bool $lock):bool
    {
        $query=Store::query('coop_order',$site)->where('merchant_id',$merchant)->where('purpose','membership')->whereLike('rule_json','%"'.self::KEY.'%');if($lock)$query->lock(true);
        foreach($query->field('rule_json')->select()->toArray()as$order){$rule=Data::decode($order['rule_json']);$current=$rule[self::KEY]??null;if(is_array($current)&&($current['request_id']??'')===$request)return true;$history=$rule['membership_deposit_return_history']??[];if(!is_array($history))continue;foreach($history as$previous)if(is_array($previous)&&($previous['request_id']??'')===$request)return true;}return false;
    }
    private function anchor(int $id,bool $lock=false):array
    {
        $order=Store::row('coop_order',$this->context->site,$id,$lock);$r=Data::decode($order['rule_json'])[self::KEY]??null;if($order['purpose']!=='membership'||!is_array($r)||(int)($r['id']??0)!==$id||(int)($r['merchant_id']??0)!==(int)$order['merchant_id'])throw new CommonException('押金退还申请不存在，或不属于本站');return[$order,$r];
    }
    private function present(array $order,array $r,bool $admin):array
    {
        $sources=[];$canContinue=false;$hasManual=false;$routeBlock='';
        $canManual=$admin&&in_array($r['status'],['approved','refunding','attention'],true)&&$this->context->ownership()->allows('hsx_lead_cooperation_order_manual_refund')&&$this->context->ownership()->allows('hsx_lead_cooperation_finance');
        foreach($r['sources']??[]as$s){
            $status=(string)($s['status']??'reserved');$refund=null;$original=Store::row('coop_order',$this->context->site,(int)$s['order_id']);
            $sourceBlock='';try{PaymentRefundService::assertRefundRoute($original);}catch(CommonException$error){$sourceBlock=$error->getMessage();$routeBlock=$sourceBlock;}
            if(!empty($s['refund_no']))try{$refund=$this->refundRecord($order,$s);if($refund&&$refund['status']===RefundDict::SUCCESS)$status='success';if($refund&&$refund['type']!==PayDict::OFFLINEPAY&&$refund['status']===RefundDict::WAIT&&empty($s['dispatch_started_at']))$canContinue=true;}catch(CommonException$e){$status='attention';}
            $item=['order_id'=>(int)$s['order_id'],'source_order_id'=>(int)$s['order_id'],'amount'=>$s['amount'],'status'=>$status,'status_label'=>['reserved'=>'待发起','dispatching'=>'已提交，待核对','pending'=>'退款处理中','success'=>'原路退款已确认','attention'=>'原退款待核对'][$status]??'待核对','message'=>$status==='attention'?'仅核对原退款，不会重复发起':($status==='success'?'已核实框架原路退款成功':'等待原路退款结果')];
            $manual=ManualRefundService::metadata($original,$refund,$canManual&&$sourceBlock==='');$item=array_replace($item,$manual);
            if($manual['manual_refund_required']){$hasManual=true;$item['status_label']=$status==='success'?'线下退款已确认':($manual['manual_refund_receipt_id']>0?'实际退款已登记，待核对':'待人工退款');$item['message']=$manual['manual_refund_message'];}
            if($sourceBlock!==''){$item['message']=$sourceBlock;$item['can_manual_refund']=false;}
            if($admin){$item['order_no']=(string)$original['order_no'];$item['refund_no']=(string)($s['refund_no']??'');}$sources[]=$item;
        }
        $out=array_intersect_key($r,array_flip(['id','status','amount','reason','review_reason','created_at','review_at']));$out['request_version']=self::version($r);$out['updated_at']=(int)($r['update_at']??0);$out['status_name']=self::LABELS[$r['status']]??'待核对';$out['sources']=$sources;$out['can_refresh']=in_array($r['status'],['approved','refunding','attention'],true);$out['can_review']=$r['status']==='pending';$out['can_continue']=$canContinue&&in_array($r['status'],['approved','refunding','attention'],true);
        if($hasManual&&$r['status']==='approved')$out['status_name']='待人工退款';
        if($routeBlock!==''){$out['can_review']=false;$out['can_continue']=false;$out['block_reason']=$routeBlock;$out['message']=$routeBlock;}
        if($admin){$merchant=Data::query('merchant',$this->context->site)->where('id',$order['merchant_id'])->field('id,name')->find();$member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$r['member_id'])->field('member_id,nickname')->find();$out+=['merchant_id'=>(int)$order['merchant_id'],'merchant_name'=>(string)($merchant['name']??'原门店'),'member_id'=>(int)$r['member_id'],'member_name'=>(string)($member['nickname']??'原申请会员')];}return$out;
    }
    private function detailForAllowed(int $id):array{[$order,$r]=$this->anchor($id);return$this->present($order,$r,true);}
    private function admin(string $action):void{$this->context->permission('hsx_lead_deposit_'.$action);if(!$this->context->ownership()->scope()['all_data'])throw new CommonException('押金退还处理需要全站管理权限');}
    private static function version(array $r):string{return hash('sha256',Data::json([(int)($r['id']??0),(int)($r['merchant_id']??0),(string)($r['request_id']??''),(int)($r['created_at']??0)]));}
    private static function save(int $id,int $site,array $rule):void{Store::query('coop_order',$site)->where('id',$id)->update(['rule_json'=>Data::json($rule),'update_time'=>time()]);}
}
