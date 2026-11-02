<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\{Cipher,Data};
use app\dict\pay\PayDict;
use core\exception\CommonException;
use think\facade\{Db,Log};

/** Immutable business prices + framework payment facts + an append-only wallet. */
final class PaymentService
{
    public const TRADE_TYPE='hsx_lead_cooperation';
    private const MAX_CENTS=99999999999999;
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
                $key=$purpose.':'.$merchant['id'].':'.($purpose==='publishing'?(int)$partner['publisher_until']:(int)$partner['cert_expires_at']);
            }
            $existing=Store::query('coop_order',$this->context->site)->where('business_key',$key)->lock(true)->find();
            if ($existing) {
                if ($purpose==='commission' && array_key_exists('amount',$input) && Rules::cents($input['amount'])!==Rules::cents((string)$existing['amount'])) throw new CommonException('本单已有固定金额的待支付订单，请先按原金额核对付款，不会重复创建或改价');
                return (int)$existing['id'];
            }
            if ($purpose==='commission') {
                $split=Rules::split(Rules::cents($input['amount']??'50.00'),$config);
            } else {
                $amount=Rules::cents((string)$config[$purpose==='publishing'?'publishing_price':'certification_price']);
                if ($amount<=0) throw new CommonException('平台尚未设置该项收费价格，请联系管理员；当前未创建付款');
                $split=['amount'=>Rules::amount($amount),'fee_amount'=>'0.00','net_amount'=>'0.00'];
            }
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
        if ($row['purpose']!=='commission'&&(int)Store::partner($this->context->site,(int)$row['merchant_id'])['blocked_at']>0) throw new CommonException('门店已停止合作，不能继续购买权益；当前未发起支付');
        if ($row['purpose']==='commission') {
            $lead=Store::row('collaboration',$this->context->site,(int)$row['lead_id']);
            if ($lead['status']!=='unpaid' || (int)$lead['payment_order_id']!==$id || (int)$lead['receiver_id']!==(int)$row['merchant_id']) throw new CommonException('交付状态与付款订单不一致，请返回详情核对');
        }
        foreach(Db::name('pay')->where('site_id',$this->context->site)->where('trade_type',self::TRADE_TYPE)->where('trade_id',$id)->where('status','<>',PayDict::STATUS_CANCEL)->select()->toArray() as $pay) {
            if (Rules::cents((string)$pay['money'])!==Rules::cents((string)$row['amount']) || (int)$pay['main_id']!==$this->context->memberId) throw new CommonException('收银台金额或付款身份与业务不符，请联系平台核对，当前未发起支付');
        }
        return $row;
    }

    public static function paymentInfo(int $site,int $id): array
    {
        $row=Store::row('coop_order',$site,$id);
        if ($row['status']!=='unpaid' || Rules::cents((string)$row['amount'])<=0) throw new CommonException('该业务当前不可付款，请刷新核对');
        if ($row['purpose']==='commission') {
            $lead=Store::row('collaboration',$site,(int)$row['lead_id']);
            if ($lead['status']!=='unpaid'||(int)$lead['payment_order_id']!==$id) throw new CommonException('交付付佣状态已变化，请返回详情核对');
        }
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
                $partner=Store::partner($site,(int)$lead['publisher_id'],true);
                $net=Rules::cents((string)$order['net_amount']);
                self::entry($site,$partner,'income:'.$id,'commission_income',$id,$net,0,Rules::amount($net),'已支付协作佣金的发布方净收益',['total_income'=>self::checkedAdd((string)$partner['total_income'],$net)]);
                Store::query('collaboration',$site)->where('id',$lead['id'])->update(['status'=>'paid','paid_at'=>time(),'update_time'=>time()]);
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
                    $changes=['certification_status'=>$partner['certification_status']==='active'?'active':'pending','cert_expires_at'=>Rules::addMonths(max(time(),(int)$partner['cert_expires_at']),(int)($rule['months']??12))];
                }
                Store::query('partner',$site)->where('id',$partner['id'])->update($changes+['update_time'=>time()]);
            } else throw new CommonException('未知的收费业务，未确认到账');
            Store::query('coop_order',$site)->where('id',$id)->update(['status'=>'paid','out_trade_no'=>$no,'pay_time'=>time(),'update_time'=>time()]);
            Data::audit($site,0,(int)$order['member_id'],'coop_order',$id,'payment_confirmed',['purpose'=>$order['purpose'],'amount'=>$order['amount'],'out_trade_no'=>$no]);
            return $lead?(int)$lead['id']:0;
        });
        if ($noticeLead) self::notify($site,$noticeLead,'paid');
        return true;
    }

    public function wallet(array $params=[]): array
    {
        $merchant=$this->context->merchant();$partner=Store::partner($this->context->site,(int)$merchant['id']);
        $entries=Data::page(Store::query('wallet_entry',$this->context->site)->where('merchant_id',$merchant['id']),$params);
        $withdrawals=Data::page(Store::query('withdrawal',$this->context->site)->where('merchant_id',$merchant['id']),$params);
        $withdrawals['data']=array_map(fn(array $row):array=>$this->presentWithdrawal($row,false),$withdrawals['data']);
        return ['available'=>$partner['available_balance'],'frozen'=>$partner['frozen_balance'],'total_income'=>$partner['total_income'],'withdrawn'=>$partner['withdrawn_amount'],'entries'=>$entries,'withdrawals'=>$withdrawals];
    }

    public function withdraw(array $input): array
    {
        $merchant=$this->context->merchant();$request=Store::requestId($input);$amount=Rules::cents($input['amount']??'');
        if ($amount<=0) throw new CommonException('提现金额须大于0，且不能超过可提现收益');
        $type=Data::text($input,'payee_type',16,true);$name=Data::text($input,'payee_name',80,true);$account=Data::text($input,'payee_account',160,true);
        if (!in_array($type,['bank','wechat','alipay'],true)||preg_match('/[\x00-\x1F\x7F]/',$name.$account)) throw new CommonException('请核对收款方式、户名和账号');
        if ($type==='bank' && !preg_match('/^[0-9]{8,32}$/D',$account)) throw new CommonException('银行卡号请填写8—32位数字，不要包含空格');
        $id=Db::transaction(function()use($merchant,$request,$amount,$type,$name,$account):int {
            $partner=Store::partner($this->context->site,(int)$merchant['id'],true);
            $old=Store::query('withdrawal',$this->context->site)->where('merchant_id',$merchant['id'])->where('request_id',$request)->find();
            if ($old) {
                $payee=Cipher::decrypt((string)$old['payee_cipher'],$this->context->site,-1000000000-(int)$old['id']);
                if (Rules::cents((string)$old['amount'])!==$amount || $payee!==['type'=>$type,'name'=>$name,'account'=>$account]) throw new CommonException('该请求已提交不同的提现资料，请先查看原记录，不要重复申请');
                return (int)$old['id'];
            }
            if (Rules::cents((string)$partner['available_balance'])<$amount) throw new CommonException('可提现收益不足，请刷新余额；未付佣金不计入收益');
            $id=(int)Store::query('withdrawal',$this->context->site)->insertGetId(['site_id'=>$this->context->site,'merchant_id'=>$merchant['id'],'member_id'=>$this->context->memberId,
                'withdrawal_no'=>Data::number('CW'),'request_id'=>$request,'amount'=>Rules::amount($amount),'payee_cipher'=>'','status'=>'pending','create_time'=>time(),'update_time'=>time()]);
            Store::query('withdrawal',$this->context->site)->where('id',$id)->update(['payee_cipher'=>Cipher::encrypt(['type'=>$type,'name'=>$name,'account'=>$account],$this->context->site,-1000000000-$id)]);
            self::entry($this->context->site,$partner,'withdrawal:freeze:'.$id,'withdrawal_freeze',$id,-$amount,$amount,Rules::amount($amount),'申请提现，收益已冻结，等待平台实际打款');
            $this->context->audit('withdrawal',$id,'requested',['amount'=>Rules::amount($amount),'payee_type'=>$type]);
            return $id;
        });
        return $this->presentWithdrawal(Store::row('withdrawal',$this->context->site,$id),false);
    }

    public function adminOrders(array $params=[]): array
    {
        $this->finance();$q=Store::query('coop_order',$this->context->site);$this->filter($q,$params,['status','purpose','merchant_id'],'order_no|out_trade_no');
        if (in_array($params['requires_manual_review']??false,[true,1,'1'],true)) $q->whereLike('rule_json','%merchant_blocked_during_payment%');
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $r):array=>$this->merchantName(self::presentOrder($r)),$page['data']);return $page;
    }
    public function adminWithdrawals(array $params=[]): array
    {
        $this->finance();$q=Store::query('withdrawal',$this->context->site);$this->filter($q,$params,['status','merchant_id'],'withdrawal_no|receipt_no|remark');
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $r):array=>$this->merchantName($this->presentWithdrawal($r,false)),$page['data']);return $page;
    }
    public function withdrawalDetail(int $id): array
    {
        $this->finance();$row=Store::row('withdrawal',$this->context->site,$id);
        $result=$this->presentWithdrawal($row,true);$this->context->audit('withdrawal',$id,'payee_viewed',['merchant_id'=>(int)$row['merchant_id']]);return $result;
    }
    public function markPaid(int $id,array $input): array
    {
        $this->finance();if (!in_array($input['confirm']??null,[true,1,'1'],true)) throw new CommonException('请先确认已实际向该收款账户打款');
        $receipt=Data::text($input,'receipt_no',100,true);$remark=Data::text($input,'remark',2000);
        $row=Db::transaction(function()use($id,$receipt,$remark):array {
            $initial=Store::row('withdrawal',$this->context->site,$id);$partner=Store::partner($this->context->site,(int)$initial['merchant_id'],true);$row=Store::row('withdrawal',$this->context->site,$id,true);
            if ($row['status']==='paid') {if ($row['receipt_no']!==$receipt) throw new CommonException('此提现已处理，原打款凭据不可重复覆盖');return $row;}
            if ($row['status']!=='pending') throw new CommonException('此提现已驳回或已处理，请刷新查看');
            $amount=Rules::cents((string)$row['amount']);
            self::entry($this->context->site,$partner,'withdrawal:paid:'.$id,'withdrawal_paid',$id,0,-$amount,$row['amount'],'平台已实际打款',['withdrawn_amount'=>self::checkedAdd((string)$partner['withdrawn_amount'],$amount)]);
            $change=['status'=>'paid','receipt_no'=>$receipt,'remark'=>$remark,'operator_uid'=>$this->context->uid,'processed_at'=>time(),'update_time'=>time()];
            Store::query('withdrawal',$this->context->site)->where('id',$id)->update($change);$this->context->audit('withdrawal',$id,'paid',['amount'=>$row['amount'],'receipt_no'=>$receipt]);return array_replace($row,$change);
        });return $this->presentWithdrawal($row,true);
    }
    public function reject(int $id,array $input): array
    {
        $this->finance();$reason=Data::text($input,'reason',1000,true);
        $row=Db::transaction(function()use($id,$reason):array {
            $initial=Store::row('withdrawal',$this->context->site,$id);$partner=Store::partner($this->context->site,(int)$initial['merchant_id'],true);$row=Store::row('withdrawal',$this->context->site,$id,true);
            if ($row['status']==='rejected') return $row;
            if ($row['status']!=='pending') throw new CommonException('此提现已打款，不可再次驳回或解冻');
            $amount=Rules::cents((string)$row['amount']);self::entry($this->context->site,$partner,'withdrawal:release:'.$id,'withdrawal_release',$id,$amount,-$amount,$row['amount'],'提现驳回，冻结收益已退回');
            $change=['status'=>'rejected','remark'=>$reason,'operator_uid'=>$this->context->uid,'processed_at'=>time(),'update_time'=>time()];
            Store::query('withdrawal',$this->context->site)->where('id',$id)->update($change);$this->context->audit('withdrawal',$id,'rejected',['amount'=>$row['amount'],'reason'=>$reason]);return array_replace($row,$change);
        });return $this->presentWithdrawal($row,true);
    }

    private static function entry(int $site,array $partner,string $key,string $type,int $ref,int $delta,int $frozen,string $amount,string $remark,array $extra=[]): void
    {
        if (Store::query('wallet_entry',$site)->where('entry_key',$key)->find()) return;
        $available=self::checkedAdd((string)$partner['available_balance'],$delta);$held=self::checkedAdd((string)$partner['frozen_balance'],$frozen);
        Store::query('partner',$site)->where('id',$partner['id'])->update(['available_balance'=>$available,'frozen_balance'=>$held,'update_time'=>time()]+$extra);
        Store::query('wallet_entry',$site)->insert(['site_id'=>$site,'merchant_id'=>$partner['merchant_id'],'entry_key'=>$key,'type'=>$type,'ref_id'=>$ref,'amount'=>$amount,
            'balance_delta'=>Rules::amount($delta),'frozen_delta'=>Rules::amount($frozen),'balance_after'=>$available,'frozen_after'=>$held,'remark'=>$remark,'create_time'=>time()]);
    }
    private static function checkedAdd(string $value,int $delta): string
    {
        $next=Rules::cents($value)+$delta;if ($next<0||$next>self::MAX_CENTS) throw new CommonException('余额与冻结记录不一致或已超出系统金额精度，未执行资金变动，请人工核对');return Rules::amount($next);
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
        $row['review_message']=$row['requires_manual_review']?'实际收款时门店已停止合作，未开通权益；请平台核对原支付流水并处理退款事宜':'';
        unset($row['rule_json'],$row['request_id'],$row['business_key']);$row['trade_type']=self::TRADE_TYPE;
        $row['purpose_label']=['commission'=>'协作佣金','publishing'=>'发单权益','certification'=>'靠谱认证'][$row['purpose']]??'待核对';
        $row['status_label']=['unpaid'=>'待支付','paid'=>'已支付','closed'=>'已关闭'][$row['status']]??'待核对';return $row;
    }
    private function presentWithdrawal(array $row,bool $admin): array
    {
        $payee=Cipher::decrypt((string)$row['payee_cipher'],$this->context->site,-1000000000-(int)$row['id']);
        $account=(string)($payee['account']??'');$length=mb_strlen($account);$masked=$length<=4?str_repeat('*',max(1,$length)):str_repeat('*',min(8,$length-4)).mb_substr($account,-4);
        unset($row['payee_cipher'],$row['request_id']);$row['payee_type']=$payee['type']??'';$row['payee_name']=$payee['name']??'';$row['payee_account']=$admin?$account:$masked;$row['payee_account_masked']=$masked;
        $row['status_label']=['pending'=>'待审核打款','paid'=>'已打款','rejected'=>'已驳回并解冻'][$row['status']]??'待核对';return $row;
    }
    private static function notify(int $site,int $lead,string $action): void
    {
        try {event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$lead,'action'=>$action]);} catch (\Throwable $error) {Log::warning('协作资金业务已提交，通知发送失败：'.$error->getMessage());}
    }
}
