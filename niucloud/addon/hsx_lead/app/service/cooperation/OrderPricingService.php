<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\{Db,Log};

/** Repricing closes an old trade and creates another; paid facts never change. */
final class OrderPricingService
{
    private const SCHEMA='hsx.lead.order.repricing.v1';
    private ?\Closure $providerFactory;
    public function __construct(private Context $context,?callable $providerFactory=null){$this->providerFactory=$providerFactory===null?null:\Closure::fromCallable($providerFactory);}

    public static function adminMetadata(array $row): array
    {
        $rule=Data::decode($row['rule_json']??null);$intent=$rule['pricing_intent']??[];$open=$row['status']==='unpaid'&&empty($rule['requires_manual_review']);
        return ['can_reprice'=>$open&&!$intent&&($row['purpose']==='commission'||($rule['cost_schema']??'')===MembershipCostService::SCHEMA),
            'can_resume_reprice'=>in_array($row['status'],['unpaid','closed'],true)&&empty($rule['requires_manual_review'])&&($intent['schema']??'')===self::SCHEMA&&empty($rule['replacement_order_id']),
            'pricing_request_id'=>(string)($intent['request_id']??''),'pricing_target_amount'=>(string)($intent['amount']??''),
            'pricing_target_service_amount'=>(string)($intent['service_amount']??''),'pricing_reason'=>(string)($intent['reason']??''),
            'pricing_minimum'=>(string)($rule['commission_minimum']??''),'pricing_fee_percent'=>(string)($rule['fee_percent']??''),'pricing_fee_minimum'=>(string)($rule['fee_minimum']??'')];
    }

    public function logs(int $id,array $params=[]): array
    {
        Store::permission($this->context,'finance');$this->context->permission('hsx_lead_cooperation_order_price_log');
        if(!$this->context->ownership()->scope()['all_data'])throw new CommonException('查看支付处理日志需要全站业务查看权限');
        Store::row('coop_order',$this->context->site,$id);
        $page=Data::page(Data::query('audit',$this->context->site)->where('entity_type','coop_order')->where('entity_id',$id)->whereIn('action',['repricing_requested','repriced','replaces_repriced_order','repricing_pending','closed']),$params);
        $uids=array_values(array_unique(array_filter(array_column($page['data'],'operator_uid'))));$names=[];
        if($uids)foreach(Db::name('sys_user')->whereIn('uid',$uids)->field('uid,username,real_name')->select()->toArray()as$user)$names[(int)$user['uid']]=(string)($user['real_name']?:$user['username']);
        $labels=['repricing_requested'=>'申请改价','repriced'=>'已改价建新单','replaces_repriced_order'=>'由原单改价生成','repricing_pending'=>'改价待继续核对','closed'=>'原付款已关闭'];
        $page['data']=array_map(static function(array $row)use($names,$labels):array {
            return ['id'=>(int)$row['id'],'operator_uid'=>(int)$row['operator_uid'],'operator_name'=>$names[(int)$row['operator_uid']]??'系统','create_time'=>(int)$row['create_time'],'action'=>$row['action'],'action_label'=>$labels[$row['action']]??'支付处理',
                'detail'=>array_intersect_key(Data::decode($row['detail_json']??''),array_flip(['original_amount','amount','service_amount','reason','replacement_order_id','source_order_id','state','message']))];
        },$page['data']);return$page;
    }

    public function reprice(int $id,array $input): array
    {
        Store::permission($this->context,'finance');$this->context->permission('hsx_lead_cooperation_order_price');$this->context->permission('hsx_lead_cooperation_order_close');
        if(!$this->context->ownership()->scope()['all_data'])throw new CommonException('改价需要全站业务查看权限');
        $pdo=Db::connect()->getPdo();if($pdo instanceof \PDO&&$pdo->inTransaction())throw new CommonException('改价核对不能嵌套在其他数据库事务中，请在原事务提交后重试');
        if(!in_array($input['confirm']??null,[true,1,'1'],true))throw new CommonException('请确认关闭原未付订单，并按新金额创建付款单');
        // An advisory lock serializes this workflow, but no DB transaction spans
        // provider reconciliation/closure. A confirmed provider receipt commits.
        $connection=Db::connect();$scope=(string)$connection->getConfig('database').':'.(string)$connection->getConfig('prefix');
        $key='lead_order_price:'.substr(hash('sha256',$scope.':'.$this->context->site.':'.$id),0,36);
        $locked=$connection->query('SELECT GET_LOCK(:lock_key,3) AS acquired',['lock_key'=>$key],true);
        if((int)($locked[0]['acquired']??0)!==1)throw new CommonException('该订单正在核对改价，请稍后刷新，不要重复提交');
        try{return $this->perform($id,$input);}
        finally{try{$connection->query('SELECT RELEASE_LOCK(:lock_key) AS released',['lock_key'=>$key],true);}catch(\Throwable $ignored){}}
    }

    private function perform(int $id,array $input): array
    {
        $prepared=Db::transaction(function()use($id,$input):array {
            PromotionService::lockSite($this->context->site);$old=Store::row('coop_order',$this->context->site,$id,true);$rule=Data::decode($old['rule_json']);$existing=$rule['pricing_intent']??[];
            $resume=in_array($input['resume']??false,[true,1,'1'],true);
            if($resume){
                if(($existing['schema']??'')!==self::SCHEMA)throw new CommonException('没有可继续处理的原改价请求，请刷新订单');
                $request=(string)$existing['request_id'];$reason=(string)$existing['reason'];$amount=(string)$existing['amount'];$service=(string)($existing['service_amount']??'');
            }else{
                $request=Store::requestId($input);$reason=Data::text($input,'reason',500,true);if(mb_strlen($reason)<4)throw new CommonException('请填写至少4个字符的改价原因');
                if($old['purpose']==='membership'){$service=Rules::amount(Rules::cents($input['service_amount']??null));$amount=Rules::amount(Rules::cents($service)+Rules::cents((string)($rule['deposit_amount']??'0.00'))+Rules::cents((string)($rule['experience_amount']??'0.00')));}
                elseif($old['purpose']==='commission'){$service='';$amount=Rules::amount(Rules::cents($input['amount']??null));}
                else throw new CommonException('此订单类型不支持改价');
            }
            if(Rules::cents($amount)<1||Rules::cents($amount)>9999999999)throw new CommonException('新应付金额须为0.01—99,999,999.99元');
            $hash=hash('sha256',Data::json([$request,$old['purpose'],$amount,$service,$reason]));
            if($existing){
                if(($existing['schema']??'')!==self::SCHEMA||!is_string($existing['fingerprint']??null)||!hash_equals($existing['fingerprint'],$hash))throw new CommonException('原单已有另一改价请求，请刷新后继续原处理，或进入已生成的新订单');
                if(!empty($rule['replacement_order_id']))return ['old'=>$old,'intent'=>$existing,'replacement'=>Store::row('coop_order',$this->context->site,(int)$rule['replacement_order_id'],true)];
                if(!in_array($old['status'],['unpaid','closed'],true)||!empty($rule['requires_manual_review']))return ['old'=>$old,'intent'=>$existing,'blocked'=>true];
            }else{
                if($old['status']!=='unpaid'||!empty($rule['requires_manual_review']))throw new CommonException('仅未收款且状态可核实的待支付订单允许改价');
                if(Rules::cents($amount)===Rules::cents((string)$old['amount']))throw new CommonException('新金额与原金额相同，无需改价');
            }
            // Validate business rules before closing; validate once again after
            // closure because entitlement/deposit/experience facts may change.
            if($old['purpose']==='membership')EntityMembershipService::prepareRepricedSnapshot($this->context->site,$old,$service,['operator_uid'=>$this->context->uid,'reason'=>$reason,'at'=>time()]);
            else PaymentService::prepareRepricedCommission($this->context->site,$old,$amount);
            PaymentService::assertReplacementChain($this->context->site,$old,true);
            if(!$existing){
                $intent=['schema'=>self::SCHEMA,'request_id'=>$request,'fingerprint'=>$hash,'amount'=>$amount,'service_amount'=>$service,'original_amount'=>(string)$old['amount'],'reason'=>$reason,'operator_uid'=>$this->context->uid,'at'=>time(),'state'=>'prepared','message'=>'改价已记录，正在核对原付款'];
                $rule['pricing_intent']=$intent;Store::query('coop_order',$this->context->site)->where('id',$id)->update(['rule_json'=>Data::json($rule),'update_time'=>time()]);
                $this->context->audit('coop_order',$id,'repricing_requested',['request_id'=>$request,'original_amount'=>$old['amount'],'amount'=>$amount,'service_amount'=>$service,'reason'=>$reason]);
            }else $intent=$existing;
            return ['old'=>$old,'intent'=>$intent];
        });
        $intent=$prepared['intent'];
        if(isset($prepared['replacement']))return $this->result($id,(int)$prepared['replacement']['id'],true,'原改价已完成，返回同一笔新支付单');
        if(!empty($prepared['blocked']))return $this->result($id,0,false,'原订单已收款或仍需核对，未另建支付单');
        try{
            // Never wrap this call in the replacement creation transaction.
            $closed=(new PaymentOrderLifecycle($this->context,$this->providerFactory))->close($id,['confirm'=>true,'reason'=>mb_substr('后台改价：'.$intent['reason'],0,500)],true);
            if(($closed['status']??'')!=='closed'||!empty($closed['requires_manual_review'])){
                $message=($closed['status']??'')==='paid'?'原订单已实际收款，不再改价或另建付款单':(string)($closed['close_message']??'原付款尚未核实关闭，未另建支付单');
                $this->mark($id,$intent,'blocked',$message);return $this->result($id,0,false,$message);
            }
        }catch(\Throwable $error){
            $message=$this->safeFailure($error,'原付款尚未确认关闭，未另建支付单；请刷新核对后继续原改价请求');$this->mark($id,$intent,'blocked',$message);return $this->result($id,0,false,$message);
        }
        try{
            $newId=Db::transaction(function()use($id,$intent):int {
                PromotionService::lockSite($this->context->site);$old=Store::row('coop_order',$this->context->site,$id,true);$rule=Data::decode($old['rule_json']);$stored=$rule['pricing_intent']??[];
                if(($stored['fingerprint']??'')!==$intent['fingerprint'])throw new CommonException('原改价记录已变化，请刷新核对');
                if(!empty($rule['replacement_order_id']))return(int)$rule['replacement_order_id'];
                if($old['status']!=='closed'||!empty($rule['requires_manual_review']))throw new CommonException('原订单未安全关闭或存在迟到收款，请平台核对，不会另建付款');
                PaymentService::assertReplacementChain($this->context->site,$old,true);
                $request='reprice_'.substr(hash('sha256',$this->context->site.':'.$id.':'.$intent['request_id']),0,56);
                $provenance=['operator_uid'=>$this->context->uid,'reason'=>$intent['reason'],'at'=>time(),'request_id'=>$intent['request_id']];
                if($old['purpose']==='membership')$new=$this->membershipReplacement($old,$intent,$request,$provenance);
                else $new=PaymentService::repriceClosedCommissionOrder($this->context->site,$old,$intent['amount'],$request,$provenance);
                PaymentService::assertReplacementChain($this->context->site,$new,true);
                $newId=(int)$new['id'];$rule['replacement_order_id']=$newId;$rule['pricing_intent']=array_replace($stored,['state'=>'replaced','message'=>'原单已关闭，新金额支付单已创建','completed_at'=>time(),'completed_by'=>$this->context->uid]);
                Store::query('coop_order',$this->context->site)->where('id',$id)->update(['rule_json'=>Data::json($rule),'update_time'=>time()]);
                $this->context->audit('coop_order',$id,'repriced',['replacement_order_id'=>$newId,'original_amount'=>$old['amount'],'amount'=>$new['amount'],'service_amount'=>$intent['service_amount'],'reason'=>$intent['reason'],'request_id'=>$intent['request_id']]);
                $this->context->audit('coop_order',$newId,'replaces_repriced_order',['source_order_id'=>$id,'original_amount'=>$old['amount'],'amount'=>$new['amount'],'reason'=>$intent['reason']]);
                return $newId;
            });
            return $this->result($id,$newId,true,'原订单已核实关闭，新支付单已创建；尚未扣款');
        }catch(\Throwable $error){
            $message=$this->safeFailure($error,'原单已关闭，但新支付单尚未确认创建；请刷新后继续原改价请求，勿重复建单');$this->mark($id,$intent,'failed',$message);return $this->result($id,0,false,'原订单已关闭；'.$message);
        }
    }

    private function membershipReplacement(array $old,array $intent,string $request,array $provenance): array
    {
        $quote=EntityMembershipService::prepareRepricedSnapshot($this->context->site,$old,$intent['service_amount'],$provenance);
        if(Rules::cents($quote['amount'])!==Rules::cents($intent['amount']))throw new CommonException('会员费用拆分已变化，请重新核对，未创建新支付单');
        $existing=Store::query('coop_order',$this->context->site)->where('merchant_id',$old['merchant_id'])->where('request_id',$request)->lock(true)->find();
        if($existing){$snapshot=Data::decode($existing['rule_json']);if((int)($snapshot['reprices_order_id']??0)!==(int)$old['id']||Rules::cents((string)$existing['amount'])!==Rules::cents($intent['amount']))throw new CommonException('改价请求已关联其他会员付款，请核对');return $existing;}
        $rule=$quote['rule'];$rule['reprices_order_id']=(int)$old['id'];$now=time();
        $id=(int)Store::query('coop_order',$this->context->site)->insertGetId(['site_id'=>$this->context->site,'order_no'=>Data::number('CO'),'purpose'=>'membership','lead_id'=>0,'merchant_id'=>$old['merchant_id'],'member_id'=>$old['member_id'],'business_key'=>'membership:'.$old['merchant_id'].':'.substr(hash('sha256',$request),0,48),'request_id'=>$request,'amount'=>$quote['amount'],'fee_amount'=>'0.00','net_amount'=>'0.00','rule_json'=>Data::json($rule),'status'=>'unpaid','create_time'=>$now,'update_time'=>$now]);
        return Store::row('coop_order',$this->context->site,$id,true);
    }

    private function mark(int $id,array $intent,string $state,string $message): void
    {
        Db::transaction(function()use($id,$intent,$state,$message):void {
            PromotionService::lockSite($this->context->site);$old=Store::row('coop_order',$this->context->site,$id,true);$rule=Data::decode($old['rule_json']);
            if(!empty($rule['replacement_order_id'])||($rule['pricing_intent']['fingerprint']??'')!==$intent['fingerprint'])return;
            $rule['pricing_intent']=array_replace($rule['pricing_intent'],['state'=>$state,'message'=>$message,'last_attempt_at'=>time(),'last_operator_uid'=>$this->context->uid]);
            Store::query('coop_order',$this->context->site)->where('id',$id)->update(['rule_json'=>Data::json($rule),'update_time'=>time()]);
            $this->context->audit('coop_order',$id,'repricing_pending',['state'=>$state,'message'=>$message,'request_id'=>$intent['request_id']]);
        });
    }
    private function safeFailure(\Throwable $error,string $fallback): string
    {
        Log::warning('商户协作改价需继续核对：site='.$this->context->site.', type='.get_class($error));
        return $error instanceof CommonException?$error->getMessage():$fallback;
    }
    private function result(int $id,int $replacement,bool $repriced,string $message): array
    {
        $old=Store::row('coop_order',$this->context->site,$id);$oldRule=Data::decode($old['rule_json']);$replacement=(int)($oldRule['replacement_order_id']??$replacement);
        $new=$replacement>0?Store::row('coop_order',$this->context->site,$replacement):null;
        if($new&&((int)$new['merchant_id']!==(int)$old['merchant_id']||(int)$new['member_id']!==(int)$old['member_id']||$new['purpose']!==$old['purpose']||(int)(Data::decode($new['rule_json'])['reprices_order_id']??0)!==$id))throw new CommonException('改价订单关联异常，请平台核对');
        return ['repriced'=>$repriced&&$new!==null,'old_order'=>PaymentService::presentOrder($old)+self::adminMetadata($old),'replacement_order'=>$new?PaymentService::presentOrder($new)+self::adminMetadata($new):null,'replacement_id'=>$replacement,'message'=>$message];
    }
}
