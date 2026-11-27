<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use think\facade\Db;

/** Subject-owned experience, derived from verified net cooperation receipts.
 * One yuan of actually confirmed commission equals one point; all arithmetic
 * uses integer cents. No member growth/level, grant table or counter is written.
 */
final class EntityExperienceService
{
    public static function summary(int $site,int $merchant,bool $lock=false): array
    {
        if ($site<=0||$merchant<=0) throw new CommonException('门店经验所属主体无效');
        $query=Store::query('coop_order',$site)->where('merchant_id',$merchant)->where('purpose','commission')->where('status','paid')->order('id');if($lock)$query->lock(true);
        $paid=0;$refunded=0;$count=0;$excluded=0;$updated=0;
        foreach($query->select()->toArray() as $order) {
            try {
                $rule=Data::decode($order['rule_json']);
                if (($rule['purpose']??'')!=='commission'||!empty($rule['requires_manual_review'])||empty($order['out_trade_no'])||(int)$order['lead_id']<=0) {$excluded++;continue;}
                $leadQuery=Store::query('collaboration',$site)->where('id',(int)$order['lead_id']);if($lock)$leadQuery->lock(true);$lead=$leadQuery->find();
                // A free internal hand-off is not an external paid delivery.
                if (!$lead||$lead['status']!=='paid'||(int)$lead['receiver_id']!==$merchant||(int)$lead['publisher_id']<=0||(int)$lead['publisher_id']===$merchant||(int)$lead['payment_order_id']!==(int)$order['id']) {$excluded++;continue;}
                $amount=Rules::cents((string)$order['amount']);if($amount<=0){$excluded++;continue;}
                $payQuery=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',(int)$order['id'])->where('out_trade_no',(string)$order['out_trade_no'])->where('status',PayDict::STATUS_FINISH);if($lock)$payQuery->lock(true);
                $pay=$payQuery->find();
                if (!$pay||$pay['main_type']!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$order['member_id']||Rules::cents((string)$pay['money'])!==$amount) {$excluded++;continue;}
                $refundQuery=Db::name('pay_refund')->where('site_id',$site)->where('out_trade_no',(string)$order['out_trade_no'])->where('status',RefundDict::SUCCESS)->order('id');if($lock)$refundQuery->lock(true);
                $returned=0;$seen=[];$refundUpdated=0;
                foreach($refundQuery->field('refund_no,money,create_time,refund_time')->select()->toArray() as $refund) {
                    $no=(string)$refund['refund_no'];$value=Rules::cents((string)$refund['money']);
                    if ($no==='') throw new CommonException('退款凭据缺少编号');
                    if (isset($seen[$no])) {if($seen[$no]!==$value)throw new CommonException('同一退款凭据金额不一致');continue;}
                    $seen[$no]=$value;$returned=min($amount,$returned+$value);
                    $refundUpdated=max($refundUpdated,(int)$refund['create_time'],(int)$refund['refund_time']);
                }
                if ($paid>PHP_INT_MAX-$amount||$refunded>PHP_INT_MAX-$returned) throw new CommonException('门店经验累计超出系统精度');
                $paid+=$amount;$refunded+=$returned;$count++;$updated=max($updated,(int)$order['update_time'],(int)$pay['pay_time'],$refundUpdated);
            } catch(CommonException $error) {$excluded++;}
        }
        $costs=MembershipCostService::balances($site,$merchant,$lock);$earned=max(0,$paid-$refunded);$purchased=Rules::cents($costs['purchased_value']);
        if($earned>PHP_INT_MAX-$purchased)throw new CommonException('门店经验累计超出系统精度');
        return ['value'=>Rules::amount($earned+$purchased),'earned_value'=>Rules::amount($earned),'purchased_value'=>Rules::amount($purchased),'paid_amount'=>Rules::amount($paid),'refunded_amount'=>Rules::amount($refunded),'completed_orders'=>$count,'excluded_orders'=>$excluded,'updated_at'=>max($updated,$costs['updated_at']),'rule'=>'confirmed_commission_and_purchased_experience','cost_review_required'=>$costs['review_required']];
    }

    /** Every new opening, upgrade AND renewal is assessed on current facts. */
    public static function eligible(array $experience,array $tier): bool
    {
        return Rules::cents($experience['value']??'0.00')>=Rules::cents(self::required($tier));
    }

    /** Missing fields preserve the original contract; disabling a gate never
     * changes the native level's growth or the subject's earned experience. */
    public static function gateEnabled(array $tier): bool
    {
        if(!array_key_exists('experience_gate_enabled',$tier))return true;
        if(!is_bool($tier['experience_gate_enabled']))throw new CommonException('经验门槛快照请明确选择开启或关闭');
        return $tier['experience_gate_enabled'];
    }
    public static function required(array $tier): string
    {
        return self::gateEnabled($tier)?Rules::amount(Rules::cents($tier['experience_required']??'0.00')):'0.00';
    }

    public static function decorate(array $tiers,array $experience): array
    {
        $value=Rules::cents($experience['value']??'0.00');$out=[];
        foreach($tiers as $tier){$tier['experience_required']=self::required($tier);$required=Rules::cents($tier['experience_required']);$tier['experience_eligible']=$value>=$required;$tier['experience_remaining']=Rules::amount(max(0,$required-$value));$out[]=$tier;}return $out;
    }

    public static function nextLevel(array $tiers,array $state,array $experience): ?array
    {
        $rank=(int)($state['rank']??0);$next=null;
        // New subjects start at the explicitly designated entry, never at an
        // implicitly promoted first remaining tier after an administrator pause.
        if ($rank===0) foreach($tiers as $tier) if(!empty($tier['is_entry'])){$next=$tier;break;}
        if (!$next) foreach($tiers as $tier) if((int)$tier['rank']>$rank){$next=$tier;break;}
        if (!$next) return null;
        $required=Rules::cents(self::required($next));$value=Rules::cents($experience['value']);
        return ['code'=>$next['code'],'name'=>$next['name'],'experience_gate_enabled'=>self::gateEnabled($next),'experience_required'=>Rules::amount($required),'experience_remaining'=>Rules::amount(max(0,$required-$value)),'progress_percent'=>$required===0?100:($value>=$required?100:intdiv($value*100,$required)),'experience_eligible'=>$value>=$required,'level_color'=>$next['level_color'],'level_visual'=>$next['level_visual']];
    }
}
