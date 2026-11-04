<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
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
        $merchant=$this->context->merchant();
        $purpose=Data::text($input,'purpose',24,true);
        if (!in_array($purpose,['commission','publishing','certification'],true)) throw new CommonException('请选择正确的付费业务');
        $request=Store::requestId($input);
        $id=Db::transaction(function()use($merchant,$purpose,$input,$request):int {
            $partner=Store::partner($this->context->site,(int)$merchant['id'],true);
            $old=Store::query('coop_order',$this->context->site)->where('merchant_id',$merchant['id'])->where('request_id',$request)->find();
            if ($old) {
                if ($old['purpose']!==$purpose || ($purpose==='commission' && (int)$old['lead_id']!==(int)($input['lead_id']??0))) throw new CommonException('同一请求标识不能用于不同付款，请刷新后核对');
                if ($purpose==='commission'&&array_key_exists('amount',$input)&&Rules::cents($input['amount'])!==Rules::cents((string)$old['amount'])) throw new CommonException('该请求已创建另一金额的付佣订单，请先核对原单，不会重复改价');
                if ($old['status']==='unpaid') {
                    self::assertFrameworkAmount(Rules::cents((string)$old['amount']));
                    if ($purpose==='commission') self::assertCommissionCapacity($this->context->site,Store::row('collaboration',$this->context->site,(int)$old['lead_id']),Rules::cents((string)$old['net_amount']));
                }
                return (int)$old['id'];
            }
            $config=(new ConfigService($this->context))->get();
            if (empty($config['enabled'])) throw new CommonException('平台尚未启用商户协作，请联系管理员完成配置');
            if ((int)$partner['blocked_at']>0 && $purpose!=='commission') throw new CommonException('该门店已停止合作，不能购买新的协作权益');
            $lead=null;
            if ($purpose==='commission') {
                $lead=Store::row('collaboration',$this->context->site,(int)($input['lead_id']??0),true);
                if ((int)$lead['receiver_id']!==(int)$merchant['id'] || !in_array($lead['status'],['unpaid','paid'],true)) throw new CommonException('仅承接本单且已反馈成交的门店可以付佣');
                $key='commission:'.$lead['id'];
            } else {
                if ($purpose==='publishing' && empty($config['publishing_required'])) throw new CommonException('本站发单暂不需要购买权益，请返回发单');
                if ($purpose==='certification' && $partner['certification_status']==='pending') throw new CommonException('认证已提交，正在等待平台审核，请勿重复付款');
                if ($purpose==='certification' && $partner['certification_status']==='active' && (int)$partner['cert_expires_at']===0) throw new CommonException('当前靠谱商家认证已永久有效，无需重复购买');
                $key=$purpose.':'.$merchant['id'].':'.($purpose==='publishing'?(int)$partner['publisher_until']:(int)$partner['cert_expires_at']);
            }
            $existing=Store::query('coop_order',$this->context->site)->where('business_key',$key)->lock(true)->find();
            if ($existing) {
                if ($purpose==='commission' && array_key_exists('amount',$input) && Rules::cents($input['amount'])!==Rules::cents((string)$existing['amount'])) throw new CommonException('本单已有固定金额的待支付订单，请先按原金额核对付款，不会重复创建或改价');
                if ($existing['status']==='unpaid') {
                    self::assertFrameworkAmount(Rules::cents((string)$existing['amount']));
                    if ($lead) self::assertCommissionCapacity($this->context->site,$lead,Rules::cents((string)$existing['net_amount']));
                }
                return (int)$existing['id'];
            }
            if ($purpose==='commission') {
                $split=Rules::split(Rules::cents($input['amount']??'50.00'),$config);
            } else {
                $amount=Rules::cents((string)$config[$purpose==='publishing'?'publishing_price':'certification_price']);
                if ($amount<=0) throw new CommonException('平台尚未设置该项收费价格，请联系管理员；当前未创建付款');
                $split=['amount'=>Rules::amount($amount),'fee_amount'=>'0.00','net_amount'=>'0.00'];
            }
            self::assertFrameworkAmount(Rules::cents($split['amount']));
            if ($lead) self::assertCommissionCapacity($this->context->site,$lead,Rules::cents($split['net_amount']));
            $snapshot=['purpose'=>$purpose,'fee_percent'=>$config['fee_percent'],'fee_minimum'=>$config['fee_minimum'],
                'commission_minimum'=>$config['commission_minimum'],'fee_confirmed'=>(bool)$config['fee_confirmed'],
                'months'=>$purpose==='publishing'?1:(int)$config['certification_months']];
            $id=(int)Store::query('coop_order',$this->context->site)->insertGetId($split+[
                'site_id'=>$this->context->site,'order_no'=>Data::number('CO'),'purpose'=>$purpose,
                'lead_id'=>$lead?(int)$lead['id']:0,'merchant_id'=>(int)$merchant['id'],'member_id'=>$this->context->memberId,
                'business_key'=>$key,'request_id'=>$request,'rule_json'=>Data::json($snapshot),'status'=>'unpaid','create_time'=>time(),'update_time'=>time()]);
            if ($lead) Store::query('collaboration',$this->context->site)->where('id',$lead['id'])->update([
                'payment_order_id'=>$id,'commission_amount'=>$split['amount'],'fee_amount'=>$split['fee_amount'],'net_amount'=>$split['net_amount'],'rule_json'=>Data::json($snapshot),'update_time'=>time()]);
            $this->context->audit('coop_order',$id,'created',['purpose'=>$purpose,'amount'=>$split['amount'],'lead_id'=>$lead?(int)$lead['id']:0]);
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
    public function assertPayable(int $id): array
    {
        $row=$this->order($id);
        if ($row['status']!=='unpaid') throw new CommonException('该业务已支付或已关闭，请返回刷新结果，请勿重复付款');
        self::assertFrameworkAmount(Rules::cents((string)$row['amount']));
        if ($row['purpose']!=='commission'&&(int)Store::partner($this->context->site,(int)$row['merchant_id'])['blocked_at']>0) throw new CommonException('门店已停止合作，不能继续购买权益；当前未发起支付');
        if ($row['purpose']==='commission') {
            $lead=Store::row('collaboration',$this->context->site,(int)$row['lead_id']);
            if ($lead['status']!=='unpaid' || (int)$lead['payment_order_id']!==$id || (int)$lead['receiver_id']!==(int)$row['merchant_id']) throw new CommonException('交付状态与付款订单不一致，请返回详情核对');
            self::assertCommissionCapacity($this->context->site,$lead,Rules::cents((string)$row['net_amount']));
        }
        foreach(Db::name('pay')->where('site_id',$this->context->site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status','<>',PayDict::STATUS_CANCEL)->select()->toArray() as $pay) {
            if (Rules::cents((string)$pay['money'])!==Rules::cents((string)$row['amount']) || (int)$pay['main_id']!==$this->context->memberId) throw new CommonException('收银台金额或付款身份与业务不符，请联系平台核对，当前未发起支付');
            if ((int)$pay['status']===(int)PayDict::STATUS_FINISH) throw new CommonException('原支付流水已收款，正在核对业务入账，请勿再次付款');
        }
        return $row;
    }

    public static function paymentInfo(int $site,int $id): array
    {
        $row=Store::row('coop_order',$site,$id);
        if ($row['status']!=='unpaid' || Rules::cents((string)$row['amount'])<=0) throw new CommonException('该业务当前不可付款，请刷新核对');
        self::assertFrameworkAmount(Rules::cents((string)$row['amount']));
        if ($row['purpose']==='commission') {
            $lead=Store::row('collaboration',$site,(int)$row['lead_id']);
            if ($lead['status']!=='unpaid'||(int)$lead['payment_order_id']!==$id) throw new CommonException('交付付佣状态已变化，请返回详情核对');
            self::assertCommissionCapacity($site,$lead,Rules::cents((string)$row['net_amount']));
        }
        if (Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status',PayDict::STATUS_FINISH)->find()) throw new CommonException('原支付流水已收款，正在核对业务入账，请勿再次付款');
        $ctx=new Context($site,0,(int)$row['member_id']);
        if ((int)$ctx->merchant()['id']!==(int)$row['merchant_id']) throw new CommonException('付款会员与商户归属已变化，请联系管理员核对');
        if ($row['purpose']!=='commission'&&(int)Store::partner($site,(int)$row['merchant_id'])['blocked_at']>0) throw new CommonException('门店已停止合作，不能继续购买权益');
        return ['main_type'=>PayDict::MEMBER,'main_id'=>(int)$row['member_id'],'money'=>$row['amount'],
            'trade_type'=>self::TRADE_TYPE,'trade_id'=>$id,'body'=>['commission'=>'协作佣金','publishing'=>'商户发单权益','certification'=>'靠谱商户认证'][$row['purpose']]??'商户协作'];
    }

    /** Only completed, matching framework payment facts can create earnings. */
    public static function confirm(array $event): bool
    {
        $site=(int)($event['site_id']??0);$id=(int)($event['trade_id']??0);$no=(string)($event['out_trade_no']??'');
        if (($event['trade_type']??'')!==self::TRADE_TYPE || $site<=0 || $id<=0 || $no==='') throw new CommonException('支付回调缺少可核对的业务凭据');
        $noticeLead=Db::transaction(function()use($site,$id,$no):int {
            PromotionService::lockSite($site);
            $initial=Store::row('coop_order',$site,$id);
            $lead=(int)$initial['lead_id']>0?Store::row('collaboration',$site,(int)$initial['lead_id'],true):null;
            $order=Store::row('coop_order',$site,$id,true);
            $pay=Db::name('pay')->where('site_id',$site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('out_trade_no',$no)->lock(true)->find();
            if (!$pay || (int)$pay['status']!==(int)PayDict::STATUS_FINISH || (int)$pay['main_id']!==(int)$order['member_id']
                || (string)$pay['main_type']!==PayDict::MEMBER || Rules::cents((string)$pay['money'])!==Rules::cents((string)$order['amount'])) throw new CommonException('尚未找到金额、身份和状态均匹配的已支付流水，不会记入收益');
            if ($order['status']==='paid') {
                if (!hash_equals((string)$order['out_trade_no'],$no)) throw new CommonException('发现同一业务的另一笔支付流水，请人工核对；不会重复入账');
                return 0;
            }
            if ($order['status']!=='unpaid') throw new CommonException('业务已关闭，支付结果需要人工核对；请勿再次付款');
            $rule=Data::decode($order['rule_json']);
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
                    (new CoreMemberAccountService())->addLog($site,(int)$member['member_id'],MemberAccountTypeDict::COMMISSION,
                        Rules::amount($net),self::INCOME_TYPE,'线索协作收益 '.$lead['lead_no'],$id);
                }
                Store::query('collaboration',$site)->where('id',$lead['id'])->update(['status'=>'paid','paid_at'=>time(),'rule_json'=>Data::json($rule),'update_time'=>time()]);
            } elseif (in_array($order['purpose'],['publishing','certification'],true)) {
                $partner=Store::partner($site,(int)$order['merchant_id'],true);
                if ((int)$partner['blocked_at']>0) {
                    // A real payment may arrive after an administrator stops cooperation.
                    // Record receipt without granting unusable rights, and expose a review queue.
                    $rule['requires_manual_review']='merchant_blocked_during_payment';$changes=[];
                    Store::query('coop_order',$site)->where('id',$id)->update(['rule_json'=>Data::json($rule)]);
                } elseif ($order['purpose']==='publishing') {
                    $changes=['publisher_until'=>Rules::addMonths(max(time(),(int)$partner['publisher_until']),1)];
                } else {
                    $months=(int)($rule['months']??12);
                    // Permanent orders and already-permanent grants must never become finite on renewal.
                    $permanent=$months===0||($partner['certification_status']==='active'&&(int)$partner['cert_expires_at']===0);
                    $changes=['certification_status'=>$partner['certification_status']==='active'?'active':'pending','cert_expires_at'=>$permanent?0:Rules::addMonths(max(time(),(int)$partner['cert_expires_at']),$months)];
                }
                Store::query('partner',$site)->where('id',$partner['id'])->update($changes+['update_time'=>time()]);
            } else throw new CommonException('未知的收费业务，未确认到账');
            Store::query('coop_order',$site)->where('id',$id)->update(['status'=>'paid','out_trade_no'=>$no,'rule_json'=>Data::json($rule),'pay_time'=>time(),'update_time'=>time()]);
            // Freeze first-level inviter/rules only after a matching real receipt. Same transaction, no client grants.
            if (empty($rule['requires_manual_review'])) PromotionService::recordPaid($site,array_replace($order,['status'=>'paid','out_trade_no'=>$no,'pay_time'=>time()]));
            Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'payment_confirmed',['purpose'=>$order['purpose'],'amount'=>$order['amount'],'out_trade_no'=>$no]);
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
        $this->finance();$q=Store::query('coop_order',$this->context->site);$this->filter($q,$params,['status','purpose','merchant_id'],'order_no|out_trade_no');
        if (in_array($params['requires_manual_review']??false,[true,1,'1'],true)) $q->whereLike('rule_json','%requires_manual_review%');
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $r):array=>$this->merchantName(self::presentOrder($r)),$page['data']);return $page;
    }
    private static function assertFrameworkAmount(int $amount): void
    {
        if ($amount<0 || $amount>self::MAX_CENTS) throw new CommonException('金额超出框架账户99,999,999.99元的精度范围，未执行资金变动，请平台核对');
    }
    /** Rechecked under the member row lock on callback; early checks prevent avoidable charges. */
    private static function assertCommissionCapacity(int $site,array $lead,int $net,bool $lock=false): array
    {
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
    private function finance(): void
    {
        Store::permission($this->context,'finance');if (!$this->context->ownership()->scope()['all_data']) throw new CommonException('资金处理还需要全站业务查看权限，请由管理员授权');
    }
    private function filter($query,array $params,array $fields,string $keywordFields): void
    {
        foreach($fields as $f) if (isset($params[$f])&&$params[$f]!=='') {
            if (!is_string($params[$f])&&!is_int($params[$f])) throw new CommonException('筛选条件格式不正确');
            $query->where($f,$params[$f]);
        }
        foreach(['start_time'=>'>=','end_time'=>'<='] as $f=>$op) if ((int)($params[$f]??0)>0) $query->where('create_time',$op,(int)$params[$f]);
        $term=Data::text($params,'keyword',100);if ($term!=='') {
            $like='%'.addcslashes($term,'\\%_').'%';$ids=Data::query('merchant',$this->context->site)->whereLike('name|contact_name',$like)->column('id');
            $query->where(function($sub)use($keywordFields,$like,$ids):void {$sub->whereLike($keywordFields,$like);if($ids)$sub->whereOr('merchant_id','in',$ids);});
        }
    }
    private function merchantName(array $row): array
    {
        $row['merchant_name']=(string)(Data::query('merchant',$this->context->site)->where('id',$row['merchant_id'])->value('name')??'原门店资料待核对');return $row;
    }
    private static function presentOrder(array $row): array
    {
        $rule=Data::decode($row['rule_json']??null);$row['requires_manual_review']=!empty($rule['requires_manual_review']);
        $row['review_message']=self::reviewMessage($rule);
        unset($row['rule_json'],$row['request_id'],$row['business_key']);$row['trade_type']=self::TRADE_TYPE;
        $row['purpose_label']=['commission'=>'协作佣金','publishing'=>'发单权益','certification'=>'靠谱认证'][$row['purpose']]??'待核对';
        $row['status_label']=['unpaid'=>'待支付','paid'=>'已支付','closed'=>'已关闭'][$row['status']]??'待核对';return $row;
    }
    public static function reviewMessage(array $rule): string
    {
        return [
            'merchant_blocked_during_payment'=>'实际收款时门店已停止合作，未开通权益；请平台核对原支付流水并处理退款事宜',
            'commission_credit_capacity_exceeded'=>'本单已实际收款；发布方会员佣金账户达到金额精度上限，收益尚未入账，请平台核对处理，勿重复付款',
            'publisher_member_unavailable'=>'本单已实际收款；发布门店的会员账户暂不可入账，请平台核对会员归属与原支付流水，勿重复付款',
        ][(string)($rule['requires_manual_review']??'')]??(!empty($rule['requires_manual_review'])?'本单已收款，结果需要平台人工核对，请勿重复付款':'');
    }
    private static function notify(int $site,int $lead,string $action): void
    {
        try {event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$lead,'action'=>$action]);} catch (\Throwable $error) {Log::warning('协作资金业务已提交，通知发送失败：'.$error->getMessage());}
    }
}
