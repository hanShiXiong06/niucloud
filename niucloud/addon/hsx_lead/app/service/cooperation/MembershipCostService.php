<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use think\facade\Db;

/** Membership money has three independent purposes; a deposit is never revenue. */
final class MembershipCostService
{
    public const SCHEMA='hsx_lead.membership_cost.v1';
    public const FIELDS=['cost_schema','deposit_required','deposit_balance','deposit_amount','service_full_amount','service_credit_amount','service_amount','experience_purchase_enabled','experience_unit_price','buy_experience','experience_quantity','experience_amount','promotion_base_amount'];
    public static function flag($value,string $label): bool
    {
        if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new CommonException($label.'请明确选择开启或关闭');
        return in_array($value,[true,1,'1'],true);
    }
    public static function money($value,string $label): string
    {
        $cents=Rules::cents($value);if($cents>9999999999)throw new CommonException($label.'超出支付精度');return Rules::amount($cents);
    }
    /** Pure, integer-only pricing. Quantity also uses hundredths of one point. */
    public static function calculate(array $tier,array $period,array $experience,array $deposit,bool $buyExperience,int $serviceCredit=0): array
    {
        $required=Rules::cents($tier['deposit_amount']??'0.00');$balance=Rules::cents($deposit['balance']??'0.00');
        $full=Rules::cents($period['price']);if($serviceCredit<0||$serviceCredit>$full)throw new CommonException('会员服务费抵扣超出原价');
        $enabled=self::flag($tier['experience_purchase_enabled']??false,'购买经验');$unit=Rules::cents($tier['experience_unit_price']??'0.00');
        if($enabled&&$unit<=0)throw new CommonException('请先配置有效的经验单价');
        $gap=max(0,Rules::cents($tier['experience_required'])-Rules::cents($experience['value']??'0.00'));
        if($buyExperience&&!$enabled)throw new CommonException('本等级未开放购买经验，请通过真实成交积累经验');
        $quantity=$buyExperience?$gap:0;
        // Ceil to one cent: fractional experience cannot create free paid points.
        $experienceAmount=$quantity===0?0:(int)bcdiv(bcadd(bcmul((string)$quantity,(string)$unit,0),'99',0),'100',0);
        $topup=max(0,$required-$balance);$service=$full-$serviceCredit;$total=$topup+$service+$experienceAmount;
        if($total>9999999999)throw new CommonException('本次开通合计超出支付金额范围');
        return ['cost_schema'=>self::SCHEMA,'deposit_required'=>Rules::amount($required),'deposit_balance'=>Rules::amount($balance),'deposit_amount'=>Rules::amount($topup),
            'service_full_amount'=>Rules::amount($full),'service_credit_amount'=>Rules::amount($serviceCredit),'service_amount'=>Rules::amount($service),
            'experience_purchase_enabled'=>$enabled,'experience_unit_price'=>Rules::amount($unit),'buy_experience'=>$buyExperience,
            'experience_quantity'=>Rules::amount($quantity),'experience_amount'=>Rules::amount($experienceAmount),'promotion_base_amount'=>Rules::amount($service),'payable_amount'=>Rules::amount($total)];
    }
    public static function assertSnapshot(array $order,array $rule): void
    {
        if(($rule['cost_schema']??'')!==self::SCHEMA)throw new CommonException('会员三项费用快照不完整');
        foreach(['deposit_required','deposit_balance','deposit_amount','service_full_amount','service_credit_amount','service_amount','experience_unit_price','experience_quantity','experience_amount','promotion_base_amount'] as $key)if(!is_string($rule[$key]??null))throw new CommonException('会员费用明细缺失：'.$key);
        $tier=['deposit_amount'=>$rule['deposit_required'],'experience_purchase_enabled'=>$rule['experience_purchase_enabled']??null,'experience_unit_price'=>$rule['experience_unit_price'],'experience_required'=>$rule['experience_required']];
        $expected=self::calculate($tier,['price'=>$rule['service_full_amount']],['value'=>$rule['experience_value']??'0.00'],['balance'=>$rule['deposit_balance']],self::flag($rule['buy_experience']??null,'购买经验'),Rules::cents($rule['service_credit_amount']));
        foreach(self::FIELDS as $key)if(($rule[$key]??null)!==$expected[$key])throw new CommonException('会员分项费用与原报价不一致：'.$key);
        if(Rules::cents($rule['full_amount'])!==Rules::cents($rule['service_full_amount'])||Rules::cents($rule['credit_amount'])!==Rules::cents($rule['service_credit_amount'])||Rules::cents((string)$order['amount'])!==Rules::cents($expected['payable_amount'])||($rule['payable_amount']??null)!==$expected['payable_amount'])throw new CommonException('会员三项费用合计与原实付不一致');
    }
    /** Refund allocation must be explicit, or a genuine full refund. Never guess deposit first. */
    public static function refundedComponents(array $order,array $rule,array $refunds): array
    {
        $out=['deposit'=>0,'service'=>0,'experience'=>0,'quantity'=>0,'review'=>false,'pending'=>false,'total'=>0];$total=0;$success=[];$seen=[];
        foreach($refunds as $refund){if(in_array($refund['status'],[RefundDict::WAIT,RefundDict::DEALING],true))$out['pending']=true;if($refund['status']===RefundDict::SUCCESS){$amount=Rules::cents((string)$refund['money']);$no=(string)($refund['refund_no']??'');if($no==='')throw new CommonException('退款凭据缺少编号');if(isset($seen[$no])){if($seen[$no]!==$amount)throw new CommonException('同一退款凭据金额不一致');continue;}$seen[$no]=$amount;$total+=$amount;$success[]=$refund;}}
        $out['total']=$total;
        if($total>Rules::cents((string)$order['amount']))throw new CommonException('会员累计退款超过原实付');
        if($total===Rules::cents((string)$order['amount'])&&$total>0)return array_replace($out,['deposit'=>Rules::cents($rule['deposit_amount']),'service'=>Rules::cents($rule['service_amount']),'experience'=>Rules::cents($rule['experience_amount']),'quantity'=>Rules::cents($rule['experience_quantity'])]);
        foreach($success as $refund){
            $part=$rule['membership_refund_allocations'][(string)$refund['refund_no']]??null;
            if(!is_array($part)){$out['review']=true;continue;}
            $values=[];foreach(['deposit','service','experience','quantity'] as $key)$values[$key]=Rules::cents($part[$key]??'');
            if($values['deposit']+$values['service']+$values['experience']!==Rules::cents((string)$refund['money']))throw new CommonException('会员分项退款与真实退款金额不一致');
            // Purchased experience is revoked as a whole lot; partial point refunds need explicit review.
            if(($values['experience']>0||$values['quantity']>0)&&($values['experience']!==Rules::cents($rule['experience_amount'])||$values['quantity']!==Rules::cents($rule['experience_quantity'])))throw new CommonException('经验费用退款需核对原整笔经验');
            foreach($values as $key=>$value)$out[$key]+=$value;
        }
        foreach(['deposit'=>'deposit_amount','service'=>'service_amount','experience'=>'experience_amount','quantity'=>'experience_quantity'] as $key=>$field)if($out[$key]>Rules::cents($rule[$field]))throw new CommonException('分项累计退款超过原实付');
        return $out;
    }
    /** A closed trade is financially settled only by verified, complete original refunds. */
    public static function receiptRefund(int $site,array $order,array $receipt,bool $lock=false): array
    {
        if((int)($receipt['site_id']??0)!==$site||($receipt['trade_type']??'')!==PaymentService::TRADE_TYPE||(int)($receipt['trade_id']??0)!==(int)$order['id']||($receipt['main_type']??'')!==PayDict::MEMBER||(int)($receipt['main_id']??0)!==(int)$order['member_id']||(int)($receipt['status']??0)!==(int)PayDict::STATUS_FINISH||Rules::cents((string)$receipt['money'])!==Rules::cents((string)$order['amount'])||($receipt['out_trade_no']??'')==='')throw new CommonException('原成功付款凭据不属于此会员订单');
        $q=Db::name('pay_refund')->where('site_id',$site)->where('out_trade_no',$receipt['out_trade_no'])->order('id');if($lock)$q->lock(true);
        $sum=0;$seen=[];$proof=[];$pending=false;$conflict=false;
        foreach($q->select()->toArray()as$refund){
            if(in_array($refund['status'],[RefundDict::WAIT,RefundDict::DEALING],true))$pending=true;
            if($refund['status']!==RefundDict::SUCCESS)continue;
            $no=(string)($refund['refund_no']??'');$amount=Rules::cents((string)$refund['money']);
            if($no===''||$amount<=0){$conflict=true;continue;}
            if(isset($seen[$no])){if($seen[$no]!==$amount)$conflict=true;continue;}
            $seen[$no]=$amount;$sum+=$amount;$proof[]=['refund_no'=>$no,'amount'=>Rules::amount($amount)];
        }
        $full=!$pending&&!$conflict&&$sum===Rules::cents((string)$receipt['money']);
        return ['full'=>$full,'amount'=>Rules::amount($sum),'pending'=>$pending,'conflict'=>$conflict,'receipts'=>$proof];
    }
    /** Immutable verified receipts, independent of membership expiry or current catalog edits. */
    public static function balances(int $site,int $merchant,bool $lock=false,int $excludeOrder=0): array
    {
        $q=Store::query('coop_order',$site)->where('merchant_id',$merchant)->where('purpose','membership')->whereIn('status',['paid','unpaid','closed'])->order('id');if($excludeOrder>0)$q->where('id','<>',$excludeOrder);if($lock)$q->lock(true);
        $deposit=0;$points=0;$review=false;$updated=0;$facts=[];$unreconciled=[];
        foreach($q->select()->toArray() as $order){
            if($order['status']!=='paid'){
                // A provider receipt can precede (or contradict) the business callback.
                // Only matching finished payments count: ordinary unpaid/closed orders do not.
                $pending=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$order['id'])->where('status',PayDict::STATUS_FINISH)
                    ->where('main_type',PayDict::MEMBER)->where('main_id',(int)$order['member_id'])->where('money',(string)$order['amount'])->where('out_trade_no','<>','');
                if($lock)$pending->lock(true);$receipts=$pending->order('id')->select()->toArray();$unsettled=[];
                foreach($receipts as$receipt){try{$settlement=self::receiptRefund($site,$order,$receipt,$lock);}catch(CommonException$ignored){$settlement=['full'=>false];}if(!$settlement['full'])$unsettled[]=['id'=>(int)$receipt['id'],'out_trade_no'=>$receipt['out_trade_no'],'pay_time'=>(int)$receipt['pay_time']];}
                if($unsettled){$review=true;$unreconciled[]=(int)$order['id'];$facts[]=[(int)$order['id'],'collected_pending_reconciliation',$order['status'],$unsettled];}
                continue;
            }
            $rule=Data::decode($order['rule_json']);if(($rule['schema_version']??'')!=='hsx_lead.entity_membership.v4')continue;
            try{
                EntityMembershipService::assertSnapshot($order,$rule);
                $pay=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$order['id'])->where('out_trade_no',$order['out_trade_no'])->where('status',PayDict::STATUS_FINISH);if($lock)$pay->lock(true);$receipt=$pay->find();
                if(!$receipt||$receipt['main_type']!==PayDict::MEMBER||(int)$receipt['main_id']!==(int)$order['member_id']||Rules::cents((string)$receipt['money'])!==Rules::cents((string)$order['amount']))throw new CommonException('会员费用缺少匹配的已付款凭据');
                $rq=Db::name('pay_refund')->where('site_id',$site)->where('out_trade_no',$order['out_trade_no'])->order('id');if($lock)$rq->lock(true);$refunded=self::refundedComponents($order,$rule,$rq->select()->toArray());
                $review=$review||$refunded['review']||$refunded['pending']||!empty($rule['requires_manual_review']);
                $deposit+=Rules::cents($rule['deposit_amount'])-$refunded['deposit'];
                if(!$refunded['review']&&empty($rule['requires_manual_review']))$points+=Rules::cents($rule['experience_quantity'])-$refunded['quantity'];
                $updated=max($updated,(int)$order['update_time']);$facts[]=[(int)$order['id'],(string)$order['out_trade_no'],$rule['deposit_amount'],$rule['experience_quantity'],$refunded,!empty($rule['requires_manual_review'])];
            }catch(CommonException $error){$review=true;$facts[]=[(int)$order['id'],'review'];}
        }
        return ['balance'=>Rules::amount($deposit),'purchased_value'=>Rules::amount($points),'review_required'=>$review,'review_message'=>$unreconciled?'存在已收款但会员业务尚未核对的付款单，请联系平台处理原单，暂不重复收费或退押金':'','review_order_ids'=>$unreconciled,'updated_at'=>$updated,'fingerprint'=>hash('sha256',Data::json($facts))];
    }
    public static function promotionBase(array $order): int
    {
        $rule=Data::decode($order['rule_json']??'');
        if(($rule['schema_version']??'')==='hsx_lead.entity_membership.v4'){EntityMembershipService::assertSnapshot($order,$rule);return Rules::cents($rule['service_amount']);}
        return Rules::cents((string)$order['amount']);
    }
}
