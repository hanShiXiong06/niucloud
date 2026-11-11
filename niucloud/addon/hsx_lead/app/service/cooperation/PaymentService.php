<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\support\AdminKeywordSearch;
use app\dict\member\{MemberAccountTypeDict,MemberCashOutDict};
use app\dict\pay\PayDict;
use app\service\core\member\CoreMemberAccountService;
use core\exception\CommonException;
use think\facade\{Db,Log};

/** Business prices and payment facts; member commission and withdrawals belong to NiuCloud. */
final class PaymentService
{
    public const TRADE_TYPE='hsx_lead_cooperation';
    public const INCOME_TYPE='hsx_lead_cooperation_income';
    private const MAX_CENTS=9999999999; // Framework member/pay DECIMAL(10,2), not a business commission ceiling.
    public function __construct(private Context $context) {}

    public function createOrder(array $input): array
    {
        $purpose=Data::text($input,'purpose',24,true);
        if ($purpose==='membership') return (new EntityMembershipService($this->context))->createOrder($input);
        if ($purpose!=='commission') throw new CommonException('请选择主体会员或成交协作付佣');
        $merchant=$this->context->merchant();
        $request=Store::requestId($input);
        $id=Db::transaction(function()use($merchant,$purpose,$input,$request):int {
            $partner=Store::partner($this->context->site,(int)$merchant['id'],true);
            $old=Store::query('coop_order',$this->context->site)->where('merchant_id',$merchant['id'])->where('request_id',$request)->find();
            if ($old) {
                if ($old['purpose']!==$purpose || ($purpose==='commission' && (int)$old['lead_id']!==(int)($input['lead_id']??0))) throw new CommonException('同一请求标识不能用于不同付款，请刷新后核对');
                if ($purpose==='commission'&&array_key_exists('amount',$input)&&Rules::cents($input['amount'])!==Rules::cents((string)$old['amount'])) throw new CommonException('该请求已创建另一金额的付佣订单，请先核对原单，不会重复改价');
                if (!in_array($old['status'],['unpaid','paid','closed'],true)) throw new CommonException('原付款订单状态待核实，请联系平台核对，不会另建付款');
                if ($old['status']==='unpaid') {
                    self::assertCommissionRules($old);
                    self::assertFrameworkAmount(Rules::cents((string)$old['amount']));
                    if ($purpose==='commission') self::assertCommissionCapacity($this->context->site,Store::row('collaboration',$this->context->site,(int)$old['lead_id']),Rules::cents((string)$old['net_amount']));
                }
                return (int)$old['id'];
            }
            // Historical accepted work remains payable when a plan expires or new
            // collaboration is switched off; this is not a new membership grant.
            $lead=Store::row('collaboration',$this->context->site,(int)($input['lead_id']??0),true);
            self::assertExternalLead($lead);
            if ((int)$lead['receiver_id']!==(int)$merchant['id'] || !in_array($lead['status'],['unpaid','paid'],true)) throw new CommonException('仅承接本单且已反馈成交的门店可以付佣');
            $key='commission:'.$lead['id'];
            if (strlen($key)>100) throw new CommonException('付款业务标识超出长度限制，当前未创建付款');
            $existing=Store::query('coop_order',$this->context->site)->where('business_key',$key)->lock(true)->find();$replaced=null;
            if ($existing) {
                if ($purpose==='commission' && array_key_exists('amount',$input) && Rules::cents($input['amount'])!==Rules::cents((string)$existing['amount'])) throw new CommonException('本单已有固定金额的待支付订单，请先按原金额核对付款，不会重复创建或改价');
                if ($existing['status']==='unpaid') {
                    self::assertCommissionRules($existing);
                    self::assertFrameworkAmount(Rules::cents((string)$existing['amount']));
                    if ($lead) self::assertCommissionCapacity($this->context->site,$lead,Rules::cents((string)$existing['net_amount']));
                    return (int)$existing['id'];
                }
                if ($existing['status']==='paid') return (int)$existing['id'];
                if ($existing['status']!=='closed') throw new CommonException('原付款订单状态待核实，请联系平台核对，不会另建付款');
                self::assertClosedReplacement($this->context->site,$existing);
                if ($lead&&($lead['status']!=='unpaid'||(int)$lead['payment_order_id']!==(int)$existing['id'])) throw new CommonException('原付佣单与交付状态不一致，未重新创建，请刷新核对');
                $archiveKey='closed:'.$existing['id'].':'.hash('sha256',$key);
                if (strlen($archiveKey)>100) throw new CommonException('原付款归档标识超出长度限制，当前未创建付款');
                Store::query('coop_order',$this->context->site)->where('id',$existing['id'])->where('status','closed')->update(['business_key'=>$archiveKey]);
                $replaced=$existing;
            }
            if ($replaced) {
                // Explicit new request after confirmed closure: preserve original price and rules, never fake a receipt.
                $split=array_intersect_key($replaced,array_flip(['amount','fee_amount','net_amount']));$ruleConfig=Data::decode($replaced['rule_json']);
            } else {
                $ruleConfig=Data::decode($lead['rule_json']);
                if (!array_key_exists('commission_minimum',$ruleConfig)||!array_key_exists('fee_confirmed',$ruleConfig)) throw new CommonException('原线索缺少可核对的收费规则，请联系平台；当前未创建付款');
                $split=Rules::split(Rules::cents($input['amount']??$ruleConfig['commission_minimum']),$ruleConfig);
            }
            self::assertFrameworkAmount(Rules::cents($split['amount']));
            if ($lead) self::assertCommissionCapacity($this->context->site,$lead,Rules::cents($split['net_amount']));
            $snapshot=$replaced?$ruleConfig:array_replace($ruleConfig,['purpose'=>'commission','fee_percent'=>$ruleConfig['fee_percent'],'fee_minimum'=>$ruleConfig['fee_minimum'],
                'commission_minimum'=>$ruleConfig['commission_minimum'],'fee_confirmed'=>(bool)$ruleConfig['fee_confirmed']]);
            $id=(int)Store::query('coop_order',$this->context->site)->insertGetId($split+[
                'site_id'=>$this->context->site,'order_no'=>Data::number('CO'),'purpose'=>$purpose,
                'lead_id'=>$lead?(int)$lead['id']:0,'merchant_id'=>(int)$merchant['id'],'member_id'=>$this->context->memberId,
                'business_key'=>$key,'request_id'=>$request,'rule_json'=>Data::json($snapshot),'status'=>'unpaid','create_time'=>time(),'update_time'=>time()]);
            if ($lead) Store::query('collaboration',$this->context->site)->where('id',$lead['id'])->update([
                'payment_order_id'=>$id,'commission_amount'=>$split['amount'],'fee_amount'=>$split['fee_amount'],'net_amount'=>$split['net_amount'],'rule_json'=>Data::json($snapshot),'update_time'=>time()]);
            $this->context->audit('coop_order',$id,'created',['purpose'=>$purpose,'amount'=>$split['amount'],'lead_id'=>$lead?(int)$lead['id']:0]);
            if ($replaced) {
                $this->context->audit('coop_order',(int)$replaced['id'],'closed_order_recreated',['replacement_order_id'=>$id,'original_business_key'=>$key]);
                $this->context->audit('coop_order',$id,'replaces_closed_order',['closed_order_id'=>(int)$replaced['id']]);
            }
            return $id;
        });
        return $this->order($id);
    }

    public function order(int $id): array
    {
        $merchant=$this->context->merchant();
        $row=Store::row('coop_order',$this->context->site,$id);
        if ((int)$row['merchant_id']!==(int)$merchant['id'] || (int)$row['member_id']!==$this->context->memberId) throw new CommonException('付款订单不存在，或不属于当前会员');
        return self::presentOrder($row);
    }

    /** Called by the plugin-owned guard before every framework cashier request. */
    public function assertPayable(int $id,bool $reuseVirtualPending=false): array
    {
        $this->order($id);$raw=Store::row('coop_order',$this->context->site,$id,true);$row=self::presentOrder($raw);
        ManualPaymentService::assertNoPendingReceipt($this->context->site,$id);
        if ($row['status']!=='unpaid') throw new CommonException('该业务已支付或已关闭，请返回刷新结果，请勿重复付款');
        if(!empty($row['requires_manual_review']))throw new CommonException($row['review_message']?:'原付款结果待平台核对，请勿重复付款');
        if(!empty(Data::decode($raw['rule_json'])['pricing_intent']))throw new CommonException('平台正在核对这笔订单的改价，请刷新订单后使用确认的新付款单，勿继续支付旧金额');
        self::assertReplacementChain($this->context->site,$raw,true);
        if (PaymentOrderLifecycle::expiresAt($raw)<=time()) throw new CommonException('本次付款已过期，请关闭付款窗口，重新确认费用后支付');
        self::assertCommissionRules($raw);
        if ($row['purpose']==='membership') EntityMembershipService::assertOrderPayable($this->context->site,$raw);
        self::assertFrameworkAmount(Rules::cents((string)$row['amount']));
        if ($row['purpose']!=='commission'&&(int)Store::partner($this->context->site,(int)$row['merchant_id'],true)['blocked_at']>0) throw new CommonException('门店已停止合作，不能继续购买权益；当前未发起支付');
        if ($row['purpose']==='commission') {
            $lead=Store::row('collaboration',$this->context->site,(int)$row['lead_id']);
            self::assertExternalLead($lead);
            if ($lead['status']!=='unpaid' || (int)$lead['payment_order_id']!==$id || (int)$lead['receiver_id']!==(int)$row['merchant_id']) throw new CommonException('交付状态与付款订单不一致，请返回详情核对');
            self::assertCommissionCapacity($this->context->site,$lead,Rules::cents((string)$row['net_amount']));
        }
        foreach(Db::name('pay')->where('site_id',$this->context->site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status','<>',PayDict::STATUS_CANCEL)->lock(true)->select()->toArray() as $pay) {
            if (Rules::cents((string)$pay['money'])!==Rules::cents((string)$row['amount']) || (int)$pay['main_id']!==$this->context->memberId) throw new CommonException('收银台金额或付款身份与业务不符，请联系平台核对，当前未发起支付');
            if ((int)$pay['status']===(int)PayDict::STATUS_FINISH) throw new CommonException('原支付流水已收款，正在核对业务入账，请勿再次付款');
            if (self::hasUnconfirmedBalanceDebit($this->context->site,$pay)) throw new CommonException('发现本次付款的账户扣款记录，请联系平台核对，暂勿重复支付');
            if ((int)$pay['status']===(int)PayDict::STATUS_ING&&(!$reuseVirtualPending||($pay['type']??'')!==\addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE)) throw new CommonException('正在确认上次付款结果，请稍后再次点击付款，暂勿重复支付');
        }
        return $row;
    }

    public static function paymentInfo(int $site,int $id): array
    {
        $row=Store::row('coop_order',$site,$id,true);
        ManualPaymentService::assertNoPendingReceipt($site,$id);
        if ($row['status']!=='unpaid' || Rules::cents((string)$row['amount'])<=0) throw new CommonException('该业务当前不可付款，请刷新核对');
        if(!empty(Data::decode($row['rule_json'])['requires_manual_review']))throw new CommonException('原付款结果待平台核对，请勿重复付款');
        if(!empty(Data::decode($row['rule_json'])['pricing_intent']))throw new CommonException('平台正在核对这笔订单的改价，请刷新订单后使用确认的新付款单，勿继续支付旧金额');
        self::assertReplacementChain($site,$row,true);
        if (PaymentOrderLifecycle::expiresAt($row)<=time()) throw new CommonException('本次付款已过期，请关闭付款窗口，重新确认费用后支付');
        self::assertCommissionRules($row);
        if ($row['purpose']==='membership') EntityMembershipService::assertOrderPayable($site,$row);
        self::assertFrameworkAmount(Rules::cents((string)$row['amount']));
        if ($row['purpose']==='commission') {
            $lead=Store::row('collaboration',$site,(int)$row['lead_id']);
            self::assertExternalLead($lead);
            if ($lead['status']!=='unpaid'||(int)$lead['payment_order_id']!==$id) throw new CommonException('交付付佣状态已变化，请返回详情核对');
            self::assertCommissionCapacity($site,$lead,Rules::cents((string)$row['net_amount']));
        }
        if (Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status',PayDict::STATUS_FINISH)->lock(true)->find()) throw new CommonException('原支付流水已收款，正在核对业务入账，请勿再次付款');
        if (Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status',PayDict::STATUS_ING)->lock(true)->find()) throw new CommonException('正在确认上次付款结果，请稍后再次点击付款，暂勿重复支付');
        foreach(Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status',PayDict::STATUS_WAIT)->select()->toArray()as$pay) {
            if (self::hasUnconfirmedBalanceDebit($site,$pay)) throw new CommonException('发现本次付款的账户扣款记录，请联系平台核对，暂勿重复支付');
        }
        $ctx=new Context($site,0,(int)$row['member_id']);
        if ((int)$ctx->merchant()['id']!==(int)$row['merchant_id']) throw new CommonException('付款会员与商户归属已变化，请联系管理员核对');
        if ($row['purpose']!=='commission'&&(int)Store::partner($site,(int)$row['merchant_id'],true)['blocked_at']>0) throw new CommonException('门店已停止合作，不能继续购买权益');
        return ['main_type'=>PayDict::MEMBER,'main_id'=>(int)$row['member_id'],'money'=>$row['amount'],
            'trade_type'=>self::TRADE_TYPE,'trade_id'=>$id,'body'=>['commission'=>'协作佣金','membership'=>'门店主体会员'][$row['purpose']]??'商户协作'];
    }

    /** An unfinished cashier is not proof of non-payment if a local debit exists. */
    public static function hasUnconfirmedBalanceDebit(int $site,array $pay): bool
    {
        if ((int)($pay['site_id']??0)!==$site||($pay['main_type']??'')!==PayDict::MEMBER
            ||!in_array((int)($pay['status']??-1),[(int)PayDict::STATUS_WAIT,(int)PayDict::STATUS_ING],true)
            ||(string)($pay['out_trade_no']??'')==='') return false;
        return Db::name('member_account_log')->where('site_id',$site)->where('member_id',(int)$pay['main_id'])
            ->where('account_type','balance')->where('from_type','order')->where('account_data','<',0)
            ->where('related_id',(string)$pay['out_trade_no'])->value('id')!==null;
    }

    /** Only completed, matching framework payment facts can create earnings. */
    public static function confirm(array $event): bool
    {
        $site=(int)($event['site_id']??0);$id=(int)($event['trade_id']??0);$no=(string)($event['out_trade_no']??'');
        if (($event['trade_type']??'')!==self::TRADE_TYPE || $site<=0 || $id<=0 || $no==='') throw new CommonException('支付回调缺少可核对的业务凭据');
        $noticeLead=Db::transaction(function()use($site,$id,$no):int {
            PromotionService::lockSite($site);
            $initial=Store::row('coop_order',$site,$id,true);
            $lead=(int)$initial['lead_id']>0?Store::row('collaboration',$site,(int)$initial['lead_id'],true):null;
            $order=Store::row('coop_order',$site,$id,true);
            if (!in_array($order['purpose'],['commission','membership'],true)) throw new CommonException('未知的收费业务，未确认到账');
            $pay=Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('out_trade_no',$no)->lock(true)->find();
            if (!$pay || (int)$pay['status']!==(int)PayDict::STATUS_FINISH || (int)$pay['main_id']!==(int)$order['member_id']
                || (string)$pay['main_type']!==PayDict::MEMBER || Rules::cents((string)$pay['money'])!==Rules::cents((string)$order['amount'])) throw new CommonException('尚未找到金额、身份和状态均匹配的已支付流水，不会记入收益');
            OrderPaymentReadService::assertManualSource($order,$pay);
            if ($order['purpose']==='commission') self::assertCommissionRules($order);
            if ($order['status']==='paid') {
                if (!hash_equals((string)$order['out_trade_no'],$no)) throw new CommonException('发现同一业务的另一笔支付流水，请人工核对；不会重复入账');
                return 0;
            }
            if($order['status']==='closed'){
                // A matching successful provider receipt must survive the transaction.
                // Never silently reopen a closed purchase, grant rights, or automatically refund.
                $closedRule=Data::decode($order['rule_json']);$seen=isset($closedRule['closed_payment_receipts'][$no]);
                $closedRule['requires_manual_review']=$order['purpose']==='membership'?'membership_closed_receipt_conflict':'commission_closed_receipt_conflict';
                $closedRule['closed_payment_receipts'][$no]=['out_trade_no'=>$no,'money'=>(string)$pay['money'],'pay_time'=>(int)($pay['pay_time']??0)];
                $firstNo=(string)($order['out_trade_no']??'');if($firstNo==='')$firstNo=$no;
                Store::query('coop_order',$site)->where('id',$id)->update(['out_trade_no'=>$firstNo,'pay_time'=>max((int)$order['pay_time'],(int)($pay['pay_time']??0)),'rule_json'=>Data::json($closedRule),'update_time'=>time()]);
                if(!$seen)Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'closed_'.$order['purpose'].'_receipt_review',['purpose'=>$order['purpose'],'amount'=>$order['amount'],'out_trade_no'=>$no]);
                if($order['purpose']==='membership')EntityMembershipService::resolveClosedReceipt($site,Store::row('coop_order',$site,$id,true));
                return 0;
            }
            if ($order['status']!=='unpaid') throw new CommonException('业务已关闭，支付结果需要人工核对；请勿再次付款');
            $rule=Data::decode($order['rule_json']);
            try { self::assertReplacementChain($site,$order,true); }
            catch(CommonException $error){
                // A genuine new receipt cannot be rolled back just because an
                // earlier, cancelled trade later reported another real payment.
                $rule['requires_manual_review']='replacement_receipt_conflict';
                Store::query('coop_order',$site)->where('id',$id)->update(['status'=>'paid','out_trade_no'=>$no,'pay_time'=>(int)($pay['pay_time']?:time()),'rule_json'=>Data::json($rule),'update_time'=>time()]);
                Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'replacement_receipt_review',['amount'=>$order['amount'],'out_trade_no'=>$no]);return 0;
            }
            if($order['purpose']==='commission'&&$lead&&(int)$lead['publisher_id']>0&&(int)$lead['publisher_id']===(int)$lead['receiver_id']){
                // Preserve a real unexpected receipt, but never manufacture
                // internal-store income, experience or invitation rewards.
                $rule['requires_manual_review']='internal_commission_receipt_conflict';
                Store::query('coop_order',$site)->where('id',$id)->update(['status'=>'paid','out_trade_no'=>$no,'pay_time'=>time(),'rule_json'=>Data::json($rule),'update_time'=>time()]);
                Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'internal_commission_receipt_review',['amount'=>$order['amount'],'out_trade_no'=>$no]);return 0;
            }
            $confirmedAt=time();
            if ($order['purpose']==='commission') {
                if (!$lead || $lead['status']!=='unpaid' || (int)$lead['payment_order_id']!==$id || (int)$lead['receiver_id']!==(int)$order['merchant_id']) throw new CommonException('支付与原交付状态不一致，暂未入账，请联系平台核对');
                if (Rules::cents((string)$order['fee_amount'])+Rules::cents((string)$order['net_amount'])!==Rules::cents((string)$order['amount'])) throw new CommonException('分账快照不平衡，未增加余额');
                $net=Rules::cents((string)$order['net_amount']);
                try {
                    $member=self::assertCommissionCapacity($site,$lead,$net,true);
                } catch (CommonException $error) {
                    // A completed provider receipt must never become an invitation to pay again.
                    // Preserve it and explicitly queue the uncredited income for human handling.
                    $rule['requires_manual_review']=str_contains($error->getMessage(),'精度范围')?'commission_credit_capacity_exceeded':'publisher_member_unavailable';
                    $member=null;
                }
                if ($member) {
                    // The locked order and member account update share one transaction.
                    $incomeLog=(new CoreMemberAccountService())->addLog($site,(int)$member['member_id'],MemberAccountTypeDict::COMMISSION,
                        Rules::amount($net),self::INCOME_TYPE,'线索协作收益 '.$lead['lead_no'],$id);
                    $rule['publisher_income']=['merchant_id'=>(int)$lead['publisher_id'],'member_id'=>(int)$member['member_id'],'account_log_id'=>(int)$incomeLog];
                }
                Store::query('collaboration',$site)->where('id',$lead['id'])->update(['status'=>'paid','paid_at'=>time(),'rule_json'=>Data::json($rule),'update_time'=>time()]);
            } elseif ($order['purpose']==='membership') {
                $partner=Store::partner($site,(int)$order['merchant_id'],true);$changes=[];
                if ((int)$partner['blocked_at']>0) $rule['requires_manual_review']='merchant_blocked_during_payment';
                else try {$changes=EntityMembershipService::grant($site,$order,$rule,(int)($pay['pay_time']??0));} catch(CommonException $error) {$rule['requires_manual_review']='membership_receipt_conflict';}
                Store::query('partner',$site)->where('id',$partner['id'])->update($changes+['update_time'=>$confirmedAt]);
            } else throw new CommonException('未知的收费业务，未确认到账');
            Store::query('coop_order',$site)->where('id',$id)->update(['status'=>'paid','out_trade_no'=>$no,'rule_json'=>Data::json($rule),'pay_time'=>$confirmedAt,'update_time'=>$confirmedAt]);
            // Freeze first-level inviter/rules only after a matching real receipt. Same transaction, no client grants.
            if (empty($rule['requires_manual_review'])) {
                try {
                    PromotionService::recordPaid($site,array_replace($order,['status'=>'paid','out_trade_no'=>$no,'pay_time'=>$confirmedAt]));
                } catch (\Throwable $error) {
                    // A promotion failure must not invite a second payment or revoke
                    // rights already backed by a valid receipt. Retain any frozen
                    // promotion decision, then surface a separate reconciliation task.
                    $retained=Data::decode(Store::query('coop_order',$site)->where('id',$id)->value('rule_json'));
                    $retained['promotion_review_required']=true;
                    Store::query('coop_order',$site)->where('id',$id)->update(['rule_json'=>Data::json($retained),'update_time'=>time()]);
                    Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'promotion_pending_review',['reason'=>'推广奖励生成未完成，请核对原单冻结规则，不得重复收费']);
                    Log::warning('商户协作付款已确认，推广奖励待核对：site='.$site.', order='.$id.', type='.get_class($error));
                }
            }
            Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'payment_confirmed',['purpose'=>$order['purpose'],'amount'=>$order['amount'],'out_trade_no'=>$no]);
            if($lead&&empty($rule['requires_manual_review'])) CooperationNoticeOutbox::record($site,(int)$lead['id'],'paid');
            return $lead&&empty($rule['requires_manual_review'])?(int)$lead['id']:0;
        });
        if ($noticeLead) self::notify($site,$noticeLead,'paid');
        return true;
    }

    public function wallet(array $params=[]): array
    {
        $merchant=$this->context->merchant();
        $prefix=(string)config('database.connections.mysql.prefix');
        $q=Db::name('hsx_lead_coop_order')->alias('o')->join([$prefix.'hsx_lead_collaboration'=>'c'],'c.payment_order_id=o.id AND c.site_id=o.site_id')
            ->join([$prefix.'member_account_log'=>'a'],"a.related_id=o.id AND a.site_id=o.site_id AND a.account_type='commission' AND a.from_type='".self::INCOME_TYPE."'")
            ->where('o.site_id',$this->context->site)->where('c.publisher_id',(int)$merchant['id'])->where('c.status','paid')
            ->where('a.member_id',$this->context->memberId)->where('o.purpose','commission')->where('o.status','paid');
        $page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??$params['page_size']??20)));
        $entries=['data'=>(clone $q)->field('o.id,o.status,o.net_amount,o.pay_time,c.customer_name,c.lead_no')->order('o.id desc')->page($page,$size)->select()->toArray(),
            'total'=>(int)(clone $q)->count(),'per_page'=>$size,'current_page'=>$page];
        return self::accountSummary($this->context->site,$this->context->memberId)+['entries'=>$entries];
    }

    /** Shared member commission account, not a second plugin-specific balance. */
    public static function accountSummary(int $site,int $memberId): array
    {
        $member=Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->where('delete_time',0)
            ->field('commission,commission_cash_outing,commission_get')->find();
        if (!$member || $memberId<=0) throw new CommonException('当前会员账户不存在，请重新登录后核对');
        $withdrawals=Db::name('member_cash_out')->where('site_id',$site)->where('member_id',$memberId)
            ->where('account_type',MemberAccountTypeDict::COMMISSION)->where('status',MemberCashOutDict::TRANSFERED);
        return ['framework_cash_out'=>true,'account_scope'=>'member_commission','available'=>(string)$member['commission'],'frozen'=>(string)$member['commission_cash_outing'],
            'total_income'=>(string)$member['commission_get'],'withdrawn'=>bcadd((string)(clone $withdrawals)->sum('apply_money'),'0',2),
            'withdrawn_received'=>bcadd((string)(clone $withdrawals)->sum('money'),'0',2)];
    }

    public function adminOrders(array $params=[]): array
    {
        $this->finance();$q=Store::query('coop_order',$this->context->site);$this->filter($q,$params,['status','purpose','merchant_id']);
        $phoneNumbers=AdminKeywordSearch::orderMobile($q,$this->context->site,$params['mobile']??'',$params['mobile_scope']??'payer');
        if(isset($params['id'])&&$params['id']!==''){if(!preg_match('/^[1-9][0-9]{0,18}$/D',(string)$params['id']))throw new CommonException('订单编号无效');$q->where('id',$params['id']);}
        if (in_array($params['requires_manual_review']??false,[true,1,'1'],true)) $q->whereLike('rule_json','%requires_manual_review%');
        if (in_array($params['promotion_review_required']??false,[true,1,'1'],true)) $q->whereLike('rule_json','%"promotion_review_required":true%');
        OrderPaymentReadService::filter($q,$params,$this->context->site);
        $page=Data::page($q,$params);$page['data']=AdminKeywordSearch::orderContacts($page['data'],$this->context->site,$phoneNumbers,$params['mobile_scope']??'payer');$page['data']=self::adminManualReceipts($page['data'],$this->context->site);$page['data']=ManualPaymentService::withPendingScope($this->context->site,array_column($page['data'],'id'),fn():array=>array_map(fn(array $r):array=>array_replace(self::presentOrder($r)+OrderPricingService::adminMetadata($r),PaymentRefundService::adminSummary($r)),$page['data']));$page['filters']=OrderPaymentReadService::options();return $page;
    }
    /** Bounded page lookup; administrative contact/receipt details never enter the mobile DTO. */
    private static function adminManualReceipts(array $rows,int $site): array
    {
        if(!$rows)return[];$ids=array_map('intval',array_column($rows,'id'));$receipts=[];
        try{$records=Db::name('hsx_lead_manual_receipt')->where('site_id',$site)->where('kind','payment')->where(function($q)use($ids):void{$q->whereIn('order_id',$ids)->whereOr('target_order_id','in',$ids);})->field('id,order_id,target_order_id,status,message')->order('id desc')->select()->toArray();}
        catch(\Throwable$error){if(str_contains($error->getMessage(),'42S02')||str_contains($error->getMessage(),'1146'))$records=[];else throw$error;}
        foreach($records as$record)foreach([(int)$record['order_id'],(int)$record['target_order_id']]as$id)if($id>0&&!isset($receipts[$id]))$receipts[$id]=$record;
        foreach($rows as&$row){$r=$receipts[(int)$row['id']]??[];$row['manual_receipt_id']=(int)($r['id']??0);$row['manual_receipt_status']=(string)($r['status']??'');$row['manual_receipt_message']=(string)($r['message']??'');}unset($row);return$rows;
    }
    private static function assertFrameworkAmount(int $amount): void
    {
        if ($amount<0 || $amount>self::MAX_CENTS) throw new CommonException('金额超出框架账户99,999,999.99元的精度范围，未执行资金变动，请平台核对');
    }

    /** Reuse the original lead's frozen rules, never today's edited fee rules. */
    public static function prepareRepricedCommission(int $site,array $old,string $amount): array
    {
        if((int)$old['site_id']!==$site||$old['purpose']!=='commission'||!in_array($old['status'],['unpaid','closed'],true))throw new CommonException('仅待支付协作佣金可申请改价');
        $rule=Data::decode($old['rule_json']);
        if(!empty($rule['requires_manual_review'])||!empty($rule['closed_payment_receipts']))throw new CommonException('原订单收款需人工核对，不能改价另建付款');
        self::assertCommissionRules($old);$split=Rules::split(Rules::cents($amount),$rule);
        self::assertFrameworkAmount(Rules::cents($split['amount']));
        $lead=Store::row('collaboration',$site,(int)$old['lead_id'],true);self::assertExternalLead($lead);
        if($lead['status']!=='unpaid'||(int)$lead['payment_order_id']!==(int)$old['id']||(int)$lead['receiver_id']!==(int)$old['merchant_id'])throw new CommonException('原付佣单已被替换或交付状态已变化，请刷新核对');
        self::assertCommissionCapacity($site,$lead,Rules::cents($split['net_amount']),true);
        foreach(['closure','close_check','pricing_intent','replacement_order_id','reprices_order_id','promotion_snapshot','promotion_review_required']as$key)unset($rule[$key]);
        return ['split'=>$split,'rule'=>$rule,'lead'=>$lead];
    }

    /** Caller owns the site lock and transaction; no gateway call occurs here. */
    public static function repriceClosedCommissionOrder(int $site,array $old,string $amount,string $request,array $provenance): array
    {
        self::assertClosedReplacement($site,$old);$prepared=self::prepareRepricedCommission($site,$old,$amount);$rule=$prepared['rule'];$lead=$prepared['lead'];
        self::assertReplacementChain($site,$old,true);
        $existing=Store::query('coop_order',$site)->where('merchant_id',$old['merchant_id'])->where('request_id',$request)->lock(true)->find();
        if($existing){$existingRule=Data::decode($existing['rule_json']);if((int)($existingRule['reprices_order_id']??0)!==(int)$old['id']||Rules::cents((string)$existing['amount'])!==Rules::cents($amount))throw new CommonException('改价请求已被用于其他订单，请刷新核对');return $existing;}
        $key='commission:'.$old['lead_id'];
        $active=Store::query('coop_order',$site)->where('business_key',$key)->lock(true)->find();
        if(!$active||(int)$active['id']!==(int)$old['id'])throw new CommonException('该协作已有其他支付单，请先核对原单，不会重复改价');
        Store::query('coop_order',$site)->where('id',$old['id'])->where('status','closed')->update(['business_key'=>'closed:'.$old['id'].':'.hash('sha256',$key),'update_time'=>time()]);
        $rule['reprices_order_id']=(int)$old['id'];$rule['price_override']=$provenance+['source_order_id'=>(int)$old['id'],'original_amount'=>(string)$old['amount'],'amount'=>$prepared['split']['amount']];
        $now=time();$id=(int)Store::query('coop_order',$site)->insertGetId($prepared['split']+['site_id'=>$site,'order_no'=>Data::number('CO'),'purpose'=>'commission','lead_id'=>$old['lead_id'],'merchant_id'=>$old['merchant_id'],'member_id'=>$old['member_id'],'business_key'=>$key,'request_id'=>$request,'rule_json'=>Data::json($rule),'status'=>'unpaid','create_time'=>$now,'update_time'=>$now]);
        Store::query('collaboration',$site)->where('id',$lead['id'])->update(['payment_order_id'=>$id,'commission_amount'=>$prepared['split']['amount'],'fee_amount'=>$prepared['split']['fee_amount'],'net_amount'=>$prepared['split']['net_amount'],'rule_json'=>Data::json($rule),'update_time'=>$now]);
        return Store::row('coop_order',$site,$id,true);
    }

    /** A late receipt on any replaced predecessor blocks another charge. */
    public static function assertReplacementChain(int $site,array $order,bool $lock=false): void
    {
        if($site<=0||(int)($order['site_id']??0)!==$site||(int)($order['id']??0)<=0)throw new CommonException('原支付关联站点无效，当前不能再次付款');
        $seen=[(int)$order['id']=>true];$current=$order;$depth=0;
        while($prior=Data::decode($current['rule_json']??null)['reprices_order_id']??0){
            if((!is_int($prior)&&!ctype_digit((string)$prior))||(int)$prior<=0||isset($seen[(int)$prior])||++$depth>20)throw new CommonException('原支付关联异常，请平台核对，当前不能再次付款');
            $seen[(int)$prior]=true;$old=Store::row('coop_order',$site,(int)$prior,$lock);$rule=Data::decode($old['rule_json']);
            if((int)$old['member_id']!==(int)$order['member_id']||(int)$old['merchant_id']!==(int)$order['merchant_id']||$old['purpose']!==$order['purpose']||($old['purpose']==='commission'&&(int)$old['lead_id']!==(int)$order['lead_id'])||$old['status']!=='closed'||!empty($rule['requires_manual_review'])||!empty($rule['closed_payment_receipts']))throw new CommonException('原改价订单存在收款或状态待核对，请勿再次付款');
            $pays=Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$old['id']);if($lock)$pays->lock(true);$known=(string)$old['out_trade_no']==='';
            foreach($pays->select()->toArray()as$pay){if((int)$pay['status']!==(int)PayDict::STATUS_CANCEL||$pay['main_type']!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$old['member_id']||Rules::cents((string)$pay['money'])!==Rules::cents((string)$old['amount']))throw new CommonException('原改价支付流水尚未全部核实关闭，请勿再次付款');if((string)$pay['out_trade_no']===(string)$old['out_trade_no'])$known=true;}
            if(!$known)throw new CommonException('原改价流水去向待核实，请勿再次付款');$current=$old;
        }
    }
    private static function assertClosedReplacement(int $site,array $order): void
    {
        if ($order['status']!=='closed') throw new CommonException('只有已明确关闭的付款业务可以重新创建');
        self::assertFrameworkAmount(Rules::cents((string)$order['amount']));self::assertCommissionRules($order);
        $rule=Data::decode($order['rule_json']);
        if (!$rule||($rule['purpose']??null)!==$order['purpose']) throw new CommonException('原付款规则快照不完整，未重新创建，请平台核对');
        $pays=Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$order['id'])->lock(true)->select()->toArray();$knownNo=(string)$order['out_trade_no']==='';
        foreach($pays as $pay) {
            if ((int)$pay['status']!==(int)PayDict::STATUS_CANCEL||$pay['main_type']!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$order['member_id']||Rules::cents((string)$pay['money'])!==Rules::cents((string)$order['amount'])) throw new CommonException('原支付流水尚未全部确认关闭或已收款，请先核对原单，不会另建付款');
            if ((string)$pay['out_trade_no']===(string)$order['out_trade_no']) $knownNo=true;
        }
        if (!$knownNo) throw new CommonException('原付款流水去向待核实，未重新创建，请平台核对');
    }
    private static function assertCommissionRules(array $order): void
    {
        if ($order['purpose']==='membership') {EntityMembershipService::assertSnapshot($order,Data::decode($order['rule_json']??null));return;}
        if ($order['purpose']!=='commission') throw new CommonException('未知的收费业务，当前未执行资金变动');
        $rule=Data::decode($order['rule_json']??null);
        if (!isset($rule['commission_minimum'],$rule['fee_minimum'],$rule['fee_percent'],$rule['fee_confirmed'])) throw new CommonException('付佣订单缺少收费规则快照，当前未执行资金变动，请平台核对');
        $split=Rules::split(Rules::cents((string)$order['amount']),$rule);
        foreach(['amount','fee_amount','net_amount'] as $field) if (Rules::cents((string)$order[$field])!==Rules::cents($split[$field])) throw new CommonException('付佣金额与原收费快照不一致，当前未执行资金变动，请平台核对');
    }
    /** Rechecked under the member row lock on callback; early checks prevent avoidable charges. */
    private static function assertCommissionCapacity(int $site,array $lead,int $net,bool $lock=false): array
    {
        self::assertExternalLead($lead);
        $publisher=Data::row('merchant',$site,(int)$lead['publisher_id']);$memberId=(int)$publisher['member_id'];
        $q=Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->where('delete_time',0);
        if ($lock) $q->lock(true);
        $member=$q->find();
        if (!$member || $memberId<=0) throw new CommonException('发布门店未绑定有效会员，当前不能收取协作佣金，请平台核对');
        self::assertFrameworkAmount($net);
        self::assertFrameworkAmount(Rules::cents((string)$member['commission'])+$net);
        self::assertFrameworkAmount(Rules::cents((string)$member['commission_get'])+$net);
        return $member;
    }
    private static function assertExternalLead(array $lead): void
    {
        if((int)($lead['publisher_id']??0)>0&&(int)$lead['publisher_id']===(int)($lead['receiver_id']??0))throw new CommonException('本店内部交付不收协作佣金，无需创建或支付付款订单');
    }
    private function finance(): void
    {
        Store::permission($this->context,'finance');if (!$this->context->ownership()->scope()['all_data']) throw new CommonException('资金处理还需要全站业务查看权限，请由管理员授权');
    }
    private function filter($query,array $params,array $fields): void
    {
        foreach($fields as $f) if (isset($params[$f])&&$params[$f]!=='') {
            if (!is_string($params[$f])&&!is_int($params[$f])) throw new CommonException('筛选条件格式不正确');
            $query->where($f,$params[$f]);
        }
        foreach(['start_time'=>'>=','end_time'=>'<='] as $f=>$op) if ((int)($params[$f]??0)>0) $query->where('create_time',$op,(int)$params[$f]);
        $term=Data::text($params,'keyword',100);\addon\hsx_lead\app\support\AdminKeywordSearch::orders($query,$this->context->site,$term);
    }
    private function merchantName(array $row): array
    {
        $row['merchant_name']=(string)(Data::query('merchant',$this->context->site)->where('id',$row['merchant_id'])->value('name')??'原门店资料待核对');return $row;
    }
    public static function presentOrder(array $row): array
    {
        $raw=$row;
        $rule=Data::decode($row['rule_json']??null);$row['requires_manual_review']=!empty($rule['requires_manual_review']);
        $row['replacement_order_id']=(int)($rule['replacement_order_id']??0);$row['reprices_order_id']=(int)($rule['reprices_order_id']??0);
        $row['repricing_state']=(string)($rule['pricing_intent']['state']??'');$row['repricing_message']=(string)($rule['pricing_intent']['message']??'');
        $row['pricing_reason']=(string)($rule['price_override']['reason']??$rule['pricing_intent']['reason']??'');
        $row['replacement_order_no']=$row['replacement_order_id']>0?(string)(Store::query('coop_order',(int)$row['site_id'])->where('id',$row['replacement_order_id'])->where('merchant_id',$row['merchant_id'])->where('member_id',$row['member_id'])->where('purpose',$row['purpose'])->value('order_no')??''):'';
        $row['review_message']=self::reviewMessage($rule);
        $row['promotion_review_required']=!empty($rule['promotion_review_required']);
        $row['promotion_review_message']=$row['promotion_review_required']?'原付款已确认，邀请奖励待平台核对；不影响本单已开通权益，请勿重复付款':'';
        $row['entitlement_months']=null;$row['entitlement_cycle']='';$row['entitlement_label']='';
        $row['full_amount']=(string)$row['amount'];$row['credit_amount']='0.00';$row['payable_amount']=(string)$row['amount'];
        $row['period_start_at']=0;$row['period_end_at']=0;$row['credit_sources']=[];
        unset($row['rule_json'],$row['request_id'],$row['business_key']);$row['trade_type']=self::TRADE_TYPE;
        $row['purpose_label']=['commission'=>'协作佣金','membership'=>'门店主体会员'][$row['purpose']]??'待核对';
        if ($row['purpose']==='membership') {
            $row['virtual_product_key']=MembershipProductIdentity::forOrder((int)$row['site_id'],(int)$row['id']);
            $row['purpose_label']='门店主体会员';$row['level_code']=(string)($rule['level_code']??'');$row['level_name']=(string)($rule['level_name']??'');$row['cycle']=(string)($rule['cycle']??'');
            $row['level_id']=(int)($rule['level_id']??0);$row['rank']=(int)($rule['rank']??0);$row['display_rank']=(int)($rule['display_rank']??0);
            $row['level_color']=(string)($rule['level_color']??'');$row['experience_required']=(string)($rule['experience_required']??'');
            $row['experience_gate_enabled']=EntityExperienceService::gateEnabled($rule);
            $row['cycle_name']=(string)($rule['cycle_name']??'');$row['account_limit']=(int)($rule['account_limit']??1);$row['unlimited']=(bool)($rule['unlimited']??false);
            $row['entitlement_cycle']=$row['cycle'];$row['entitlement_months']=(int)($rule['months']??0);$row['entitlement_label']=$row['cycle_name'];
            $row['benefits']=is_array($rule['benefits']??null)?$rule['benefits']:[];
            $row['capabilities']=MembershipCatalogService::emptyCapabilities();
            foreach($row['capabilities'] as $key=>$unused) $row['capabilities'][$key]=($rule['capabilities'][$key]??false)===true;
            foreach(['full_amount','credit_amount','payable_amount'] as $key) $row[$key]=(string)($rule[$key]??($key==='credit_amount'?'0.00':$row['amount']));
            if (($rule['cost_schema']??'')===MembershipCostService::SCHEMA) {
                foreach(MembershipCostService::FIELDS as $key) $row[$key]=$rule[$key]??null;
                foreach(['experience_value','experience_remaining'] as $key) $row[$key]=(string)($rule[$key]??'0.00');
            } else {
                // Presentation only for existing receipts; never rewrite their original amount or snapshot.
                $row+=['cost_schema'=>'','deposit_required'=>'0.00','deposit_balance'=>'0.00','deposit_amount'=>'0.00',
                    'service_full_amount'=>$row['full_amount'],'service_credit_amount'=>$row['credit_amount'],'service_amount'=>(string)$row['amount'],
                    'experience_purchase_enabled'=>false,'experience_unit_price'=>'0.00','buy_experience'=>false,
                    'experience_quantity'=>'0.00','experience_amount'=>'0.00','promotion_base_amount'=>(string)$row['amount']];
            }
            foreach(['period_start_at','period_end_at'] as $key) $row[$key]=(int)($rule[$key]??0);$row['credit_sources']=$rule['credit_sources']??[];$row['membership_refund_status']=$rule['membership_refund_status']??'';
            $row['quote_expires_at']=(int)($rule['quoted_at']??0)+600;$row['quote_expired']=$row['status']==='unpaid'&&$row['quote_expires_at']<time();
        }
        $row['status_label']=['unpaid'=>'待支付','paid'=>'已支付','closed'=>'已关闭'][$row['status']]??'待核对';$presented=PaymentOrderLifecycle::decorate($raw,$row);
        if($row['status']==='unpaid'&&!empty($rule['pricing_intent'])){$presented['can_pay']=false;$presented['can_pay_reason']='平台正在核对改价，请刷新后使用确认的新付款单';}
        $pending=ManualPaymentService::pending((int)$raw['site_id'],(int)$raw['id']);
        if($pending)$pending['can_pay_reason']=$pending['manual_payment_message'];
        return array_replace(OrderPaymentReadService::present($raw,$presented),$pending);
    }
    public static function reviewMessage(array $rule): string
    {
        return [
            'merchant_blocked_during_payment'=>'实际收款时门店已停止合作，未开通权益；请平台核对原支付流水并处理退款事宜',
            'commission_credit_capacity_exceeded'=>'本单已实际收款；发布方会员佣金账户达到金额精度上限，收益尚未入账，请平台核对处理，勿重复付款',
            'publisher_member_unavailable'=>'本单已实际收款；发布门店的会员账户暂不可入账，请平台核对会员归属与原支付流水，勿重复付款',
            'membership_receipt_conflict'=>'原会员权益或抵扣已变化，本单已实际收款但尚未开通新等级；请平台核对原收款，勿重复付款',
            'membership_closed_receipt_conflict'=>'本会员订单已关闭，但已核实收到款项；未重新开通权益，也未自动退款。请平台核对原收款，处理前不要重复付款或申请退押金',
            'commission_closed_receipt_conflict'=>'原付佣订单已关闭，但已核实收到款项；未重复给发布方或邀请人入账。请平台核对原收款和当前交付，处理前不要重复付款',
            'internal_commission_receipt_conflict'=>'本店内部交付无需付佣，但本单已核实收到款项；未产生收益、经验或推广奖励，请平台核对原收款并处理退款',
            'gateway_payment_receipt_review'=>'支付渠道已确认收款，原付款与业务结果待平台核对；订单没有取消，请勿重复付款',
            'replacement_receipt_conflict'=>'改价前后的支付出现重复收款或关联异常，本单真实收款已保留，暂未开通权益或分配收益；请平台核对，勿重复付款',
            'membership_partial_refund_review'=>'已发生部分退款，相关会员期间已暂停，退款与权益时长需平台核对；不会自动估算剩余天数',
            'membership_credit_refund_review'=>'升级抵扣的原付款已退款，依赖该来源的高级权益已暂停，请平台核对原收款和退款',
            'refund_receipt_after_failure_review'=>'原失败退款又收到成功回执，请平台核对实际退款、已释放收益及后续退款，未再次发起',
        ][(string)($rule['requires_manual_review']??'')]??(!empty($rule['requires_manual_review'])?'本单已收款，结果需要平台人工核对，请勿重复付款':'');
    }
    private static function notify(int $site,int $lead,string $action): void
    {
        try {event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$lead,'action'=>$action]);} catch (\Throwable $error) {Log::warning('协作资金业务已提交，通知发送失败：'.$error->getMessage());}
    }
}
