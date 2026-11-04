<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\member\MemberAccountTypeDict;
use app\dict\pay\{PayDict,RefundDict};
use app\service\core\member\CoreMemberAccountService;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** Real framework member.pid relationships and commission accounts; no parallel wallet. */
final class PromotionService
{
    public const CONFIG_KEY='HSX_LEAD_PROMOTION';
    public const PROVIDER_PREFIX='HSX_LEAD_PROMOTER_';
    public const RELATION_PREFIX='HSX_LEAD_PROMOTION_RELATION_';
    public const INCOME_TYPE='hsx_lead_promotion_income';
    private const MAX_CENTS=9999999999;
    public function __construct(private Context $context) {}

    private static function query(int $site) { if ($site<=0) throw new CommonException('推广站点无效');return Db::name('hsx_lead_promotion_reward')->where('site_id',$site); }
    private static function value(int $site,string $key): array
    {
        // Payment snapshots must observe committed current rules, not process-level cached configuration.
        $raw=Db::name('sys_config')->where('site_id',$site)->where('config_key',$key)->value('value');
        return is_array($raw)?$raw:Data::decode($raw);
    }
    public static function configuration(int $site): array { return PromotionRules::config(self::value($site,self::CONFIG_KEY)); }
    public static function provider(int $site,int $id): ?array
    {
        $row=self::value($site,self::PROVIDER_PREFIX.$id);if (!$row) return null;
        return ['member_id'=>$id,'enabled'=>!empty($row['enabled']),'city'=>(string)($row['city']??''),'remark'=>(string)($row['remark']??''),'update_time'=>(int)($row['update_time']??0)];
    }
    /** Serialize relation/config/settlement and payment snapshots per tenant, never a global lock. */
    public static function lockSite(int $site): void
    {
        if ($site<=0||!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new CommonException('推广所属站点不存在');
    }
    private function admin(string $key): void
    {
        $this->context->permission('hsx_lead_promotion_'.$key);
        if (!$this->context->ownership()->scope()['all_data']) throw new CommonException('推广管理还需要全站业务查看权限');
    }
    private static function member(int $site,int $id,bool $lock=false): array
    {
        $q=Db::name('member')->where('site_id',$site)->where('member_id',$id)->where('status',1)->where('is_del',0)->where('delete_time',0);
        if ($lock) $q->lock(true);$row=$q->find();if (!$row) throw new CommonException('会员不存在、已停用或不属于本站');return $row;
    }
    private function own(): array { return self::member($this->context->site,$this->context->memberId); }
    private static function summary(array $member): array
    {
        $phone=(string)($member['mobile']??'');
        return ['member_id'=>(int)$member['member_id'],'name'=>(string)($member['nickname']?:($member['username']?:'本站会员')),
            'mobile_masked'=>strlen($phone)>=7?substr($phone,0,3).'****'.substr($phone,-4):'','avatar'=>(string)($member['headimg']??'')];
    }
    private function upstream(int $pid): ?array
    {
        if ($pid<=0) return null;
        $member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$pid)->where('is_del',0)->where('delete_time',0)->find();
        if (!$member) return null;$provider=self::provider($this->context->site,$pid);
        return self::summary($member)+['provider_enabled'=>(bool)($provider['enabled']??false),'city'=>(string)($provider['city']??'')];
    }
    public function overview(): array
    {
        $member=$this->own();$id=(int)$member['member_id'];$q=self::query($this->context->site)->where('provider_member_id',$id);
        return ['enabled'=>self::configuration($this->context->site)['enabled'],'member'=>self::summary($member),'provider'=>$this->publicProvider(self::provider($this->context->site,$id)),
            'upstream'=>$this->upstream((int)$member['pid']),
            'stats'=>['team_count'=>(int)$this->teamQuery($id)->count(),'pending'=>(string)(clone $q)->where('status','pending')->sum('amount'),
                'settled'=>(string)(clone $q)->where('status','settled')->sum('amount'),'held'=>(string)(clone $q)->where('status','held')->sum('amount')],
            'account'=>PaymentService::accountSummary($this->context->site,$id),'rules'=>self::configuration($this->context->site),
            'share'=>['mid'=>$id,'path'=>'/addon/hsx_lead/pages/profile/index?mid='.$id.'&promotion_invite=1']];
    }
    private function publicProvider(?array $provider): ?array { if ($provider) unset($provider['remark']);return $provider; }
    private function teamQuery(int $id)
    {
        return Db::name('member')->where('site_id',$this->context->site)->where('pid',$id)->where('is_del',0)->where('delete_time',0);
    }
    private static function memberPage($query,array $params): array
    {
        $page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??20)));
        return ['data'=>(clone $query)->order('member_id desc')->page($page,$size)->select()->toArray(),'total'=>(int)(clone $query)->count(),'per_page'=>$size,'current_page'=>$page];
    }
    public function team(array $params=[]): array
    {
        $member=$this->own();$q=$this->teamQuery((int)$member['member_id']);$keyword=Data::text($params,'keyword',80);
        if ($keyword!=='') {
            $like='%'.addcslashes($keyword,'\\%_').'%';
            $ids=Data::query('merchant',$this->context->site)->whereLike('name|contact_name',$like)->column('member_id');
            $q->where(function($part)use($like,$ids):void {$part->whereLike('nickname|username',$like);if($ids)$part->whereOr('member_id','in',$ids);});
        }
        $page=self::memberPage($q,$params);$rows=[];
        foreach($page['data'] as $row) {
            $shop=Data::query('merchant',$this->context->site)->where('member_id',$row['member_id'])->find();
            $region=$shop?Data::regionNames($shop):[];
            $rows[]=self::summary($row)+['merchant_name'=>(string)($shop['name']??''),'region_text'=>(string)($region['region_text']??''),
                'stage'=>$shop?'已登记门店':'待完善门店','create_time'=>(int)$row['create_time']];
        }
        $page['data']=$rows;return $page;
    }
    public function invitation(int $id): array
    {
        $own=$this->own();$inviter=self::member($this->context->site,$id);$provider=self::provider($this->context->site,$id);
        $reason='';
        if (!self::configuration($this->context->site)['enabled']) $reason='本站暂未开启推广';
        elseif ($id===(int)$own['member_id']) $reason='不能把自己设为邀请人';
        elseif (empty($provider['enabled'])) $reason='该邀请人的服务商资格尚未开通或已停用';
        elseif ((int)$own['pid']>0) $reason=(int)$own['pid']===$id?'邀请关系已存在，无需重复绑定':'已有邀请人，本次不会覆盖原关系';
        return ['inviter'=>self::summary($inviter)+['city'=>(string)($provider['city']??'')],'current_upstream'=>$this->upstream((int)$own['pid']),'can_bind'=>$reason==='','reason'=>$reason];
    }
    public function bind(array $input): array
    {
        if (!in_array($input['confirm']??null,[true,1,'1'],true)) throw new CommonException('请明确确认邀请关系');
        $id=\addon\hsx_lead\app\service\OwnershipScope::ownerId($input['mid']??null);
        return Db::transaction(function()use($id):array {
            self::lockSite($this->context->site);$own=self::member($this->context->site,$this->context->memberId,true);
            if ((int)$own['pid']===$id) return ['upstream'=>$this->upstream($id),'already_bound'=>true];
            $check=$this->invitation($id);if (!$check['can_bind']) throw new CommonException($check['reason']);
            $seen=[];$cursor=$id;
            while($cursor>0) {
                if ($cursor===$this->context->memberId||isset($seen[$cursor])||count($seen)>=1000) throw new CommonException('邀请关系存在循环或过深，不能绑定');
                $seen[$cursor]=true;$ancestor=self::member($this->context->site,$cursor,true);$cursor=(int)$ancestor['pid'];
            }
            Db::name('member')->where('site_id',$this->context->site)->where('member_id',$this->context->memberId)->where('pid',0)->update(['pid'=>$id]);
            (new CoreConfigService())->setConfig($this->context->site,self::RELATION_PREFIX.$this->context->memberId,['pid'=>$id,'bound_at'=>time()]);
            $this->context->audit('promotion_relation',$this->context->memberId,'bound',['inviter_member_id'=>$id]);
            return ['upstream'=>$this->upstream($id),'already_bound'=>false];
        });
    }
    public function rules(): array { $this->own();return self::configuration($this->context->site); }
    public function adminConfig(): array { $this->admin('config');return self::configuration($this->context->site); }
    public function saveConfig(array $input): array
    {
        $this->admin('config_save');$next=PromotionRules::config($input);
        Db::transaction(function()use($next):void {self::lockSite($this->context->site);(new CoreConfigService())->setConfig($this->context->site,self::CONFIG_KEY,$next);$this->context->audit('promotion_config',0,'saved',['enabled'=>$next['enabled']]);});
        return $next;
    }
    public function providers(array $params=[]): array
    {
        $this->admin('providers');$q=Db::name('member')->where('site_id',$this->context->site)->where('is_del',0)->where('delete_time',0);$keyword=Data::text($params,'keyword',80);
        if ($keyword!=='') {
            $like='%'.addcslashes($keyword,'\\%_').'%';$ids=Data::query('merchant',$this->context->site)->whereLike('name|contact_name',$like)->column('member_id');
            $q->where(function($part)use($like,$ids):void {$part->whereLike('nickname|username|mobile',$like);if($ids)$part->whereOr('member_id','in',$ids);});
        }
        if (($params['enabled']??'')!=='') {
            $enabled=PromotionRules::bool($params['enabled']);$ids=[];
            foreach(Db::name('sys_config')->where('site_id',$this->context->site)->whereLike('config_key',addcslashes(self::PROVIDER_PREFIX,'\\%_').'%')->field('config_key,value')->select()->toArray() as $saved) {
                $value=is_array($saved['value'])?$saved['value']:Data::decode($saved['value']);$id=substr($saved['config_key'],strlen(self::PROVIDER_PREFIX));
                if (!empty($value['enabled'])&&ctype_digit($id)) $ids[]=(int)$id;
            }
            if ($enabled) $q->whereIn('member_id',$ids?:[0]);elseif ($ids) $q->whereNotIn('member_id',$ids);
        }
        $page=self::memberPage($q,$params);$page['data']=array_map(function(array $row):array {
            $provider=self::provider($this->context->site,(int)$row['member_id']);
            $shop=Data::query('merchant',$this->context->site)->where('member_id',$row['member_id'])->find();$region=$shop?Data::regionNames($shop):[];
            return self::summary($row)+['status'=>(int)$row['status'],'provider'=>$provider,'enabled'=>(bool)($provider['enabled']??false),'city'=>(string)($provider['city']??''),
                'remark'=>(string)($provider['remark']??''),'merchant_name'=>(string)($shop['name']??''),'region_text'=>(string)($region['region_text']??''),'upstream_name'=>(string)($this->upstream((int)$row['pid'])['name']??'')];
        },$page['data']);return $page;
    }
    public function saveProvider(int $id,array $input): array
    {
        $this->admin('providers_save');$enabled=PromotionRules::bool($input['enabled']??null);$city=Data::text($input,'city',120);$remark=Data::text($input,'remark',1000);
        if ($enabled&&$city==='') throw new CommonException('请填写服务商负责的城市');
        Db::transaction(function()use($id,$enabled,$city,$remark):void {
            self::lockSite($this->context->site);self::member($this->context->site,$id,true);
            (new CoreConfigService())->setConfig($this->context->site,self::PROVIDER_PREFIX.$id,['enabled'=>$enabled,'city'=>$city,'remark'=>$remark,'update_time'=>time(),'operator_uid'=>$this->context->uid]);
            $this->context->audit('promotion_provider',$id,'saved',['enabled'=>$enabled,'city'=>$city]);
        });return self::provider($this->context->site,$id)??[];
    }
    /** Invoked only within PaymentService::confirm's original transaction after matching receipt checks. */
    public static function recordPaid(int $site,array $order): void
    {
        $config=self::configuration($site);if (!$config['enabled']) return;
        if (($order['status']??'')!=='paid'||empty($order['out_trade_no'])) throw new CommonException('推广奖励缺少已支付凭据');
        if (self::query($site)->where('coop_order_id',$order['id'])->find()) return;
        $saved=Store::row('coop_order',$site,(int)$order['id'],true);
        $receipt=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$saved['id'])->where('out_trade_no',$saved['out_trade_no'])->find();
        if ($saved['status']!=='paid'||$saved['out_trade_no']!==$order['out_trade_no']||!$receipt||(int)$receipt['status']!==(int)PayDict::STATUS_FINISH||$receipt['main_type']!==PayDict::MEMBER||(int)$receipt['main_id']!==(int)$saved['member_id']||Rules::cents((string)$receipt['money'])!==Rules::cents((string)$saved['amount'])) throw new CommonException('未找到可核对的已支付事实，不会生成推广奖励');
        if (!empty(Data::decode($saved['rule_json'])['requires_manual_review'])) return;
        $order=$saved;
        $source=PromotionRules::source((string)$order['purpose']);if ($source===''||empty($config['sources'][$source]['enabled'])) return;
        $payer=self::member($site,(int)$order['member_id'],true);$providerId=(int)$payer['pid'];
        if ($providerId<=0||$providerId===(int)$payer['member_id']) return;
        $provider=self::provider($site,$providerId);if (empty($provider['enabled'])) return;
        try {self::member($site,$providerId,true);}catch(CommonException $ignored){return;}
        $relation=self::value($site,self::RELATION_PREFIX.$payer['member_id']);
        $boundAt=(int)($relation['pid']??0)===$providerId?(int)($relation['bound_at']??0):(int)$payer['create_time'];
        $renewal=$source==='maintenance'?false:Store::query('coop_order',$site)->where('member_id',$payer['member_id'])->where('purpose',$order['purpose'])->where('status','paid')->where('id','<>',$order['id'])->count()>0;
        $paidAt=(int)$order['pay_time'];if (!PromotionRules::eligible($config,$source,$renewal,$boundAt,$paidAt)) return;
        $base=Rules::cents((string)($source==='maintenance'?$order['fee_amount']:$order['amount']));$rate=$config['sources'][$source]['rate'];$amount=PromotionRules::reward($base,$rate);if ($amount<=0) return;
        $snapshot=['source'=>$source,'rate'=>$rate,'renewal'=>$renewal,'settlement_days'=>$config['settlement_days'],'eligibility_months'=>$config['eligibility_months'],'bound_at'=>$boundAt,'provider_city'=>$provider['city'],'inviter_member_id'=>$providerId];
        self::query($site)->insert(['site_id'=>$site,'coop_order_id'=>$order['id'],'out_trade_no'=>$order['out_trade_no'],'source'=>$source,'payer_member_id'=>$payer['member_id'],'provider_member_id'=>$providerId,
            'base_amount'=>Rules::amount($base),'rate'=>$rate,'amount'=>Rules::amount($amount),'status'=>'pending','rule_json'=>Data::json($snapshot),
            'available_at'=>$paidAt+$config['settlement_days']*86400,'create_time'=>$paidAt,'update_time'=>$paidAt]);
    }
    public function rewards(array $params=[],bool $admin=false): array
    {
        if ($admin) $this->admin('rewards');else $this->own();$q=self::query($this->context->site);
        if (!$admin) $q->where('provider_member_id',$this->context->memberId);
        elseif (($params['provider_member_id']??'')!=='') $q->where('provider_member_id',\addon\hsx_lead\app\service\OwnershipScope::ownerId($params['provider_member_id']));
        if (($params['status']??'')!=='') {if (!in_array($params['status'],['pending','settled','held'],true)) throw new CommonException('推广奖励状态无效');$q->where('status',$params['status']);}
        if (($params['source']??'')!=='') {if (!in_array($params['source'],['publishing','certification','maintenance'],true)) throw new CommonException('推广奖励来源无效');$q->where('source',$params['source']);}
        foreach(['start_time'=>'>=','end_time'=>'<='] as $key=>$op) if ((int)($params[$key]??0)>0) $q->where('create_time',$op,(int)$params[$key]);
        $keyword=Data::text($params,'keyword',80);
        if ($keyword!=='') {
            $members=Db::name('member')->where('site_id',$this->context->site)->whereLike('nickname|username','%'.addcslashes($keyword,'\\%_').'%')->column('member_id');
            $orders=Store::query('coop_order',$this->context->site)->whereLike('order_no','%'.addcslashes($keyword,'\\%_').'%')->column('id');
            $q->where(function($part)use($members,$orders,$admin):void {$part->whereIn('payer_member_id',$members?:[0]);if($admin&&$members)$part->whereOr('provider_member_id','in',$members);if($orders)$part->whereOr('coop_order_id','in',$orders);});
        }
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $row):array=>$this->presentReward($row,$admin),$page['data']);return $page;
    }
    private function presentReward(array $row,bool $admin): array
    {
        $payer=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['payer_member_id'])->find();
        $provider=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['provider_member_id'])->find();
        $row['payer_name']=$payer?self::summary($payer)['name']:'原会员';$row['provider_name']=$provider?self::summary($provider)['name']:'原服务商';
        $row['order_no']=(string)(Store::query('coop_order',$this->context->site)->where('id',$row['coop_order_id'])->value('order_no')??'');
        $row['source_label']=['publishing'=>'发单权益','certification'=>'靠谱认证','maintenance'=>'平台维护费'][$row['source']]??'待核对';
        $row['status_label']=!empty($row['review_required'])&&$row['status']==='settled'?'已入账·待人工追偿':(['pending'=>'待结算','settled'=>'已入框架佣金','held'=>'暂停待核对'][$row['status']]??'待核对');
        $row['review_required']=(bool)$row['review_required'];$row['rule']=Data::decode($row['rule_json']);unset($row['rule_json']);
        if (!$admin) unset($row['out_trade_no'],$row['account_log_id']);return $row;
    }
    public function settle(array $input=[]): array { $this->admin('settle');return self::settleSite($this->context->site,max(1,min(100,(int)($input['limit']??50)))); }
    /** Trusted schedule/manual service entry; all money is scoped to the supplied existing tenant. */
    public static function settleSite(int $site,int $limit=50): array
    {
        $ids=self::query($site)->where('status','pending')->where('available_at','<=',time())->order('id')->limit(max(1,min(100,$limit)))->column('id');
        $result=['processed'=>0,'settled'=>0,'held'=>0];
        foreach($ids as $id) {
            $state=Db::transaction(function()use($site,$id):string {
                self::lockSite($site);$row=self::query($site)->where('id',$id)->lock(true)->find();if (!$row||$row['status']!=='pending'||(int)$row['available_at']>time()) return 'skipped';
                $order=Store::row('coop_order',$site,(int)$row['coop_order_id'],true);
                $pay=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$order['id'])->where('out_trade_no',$row['out_trade_no'])->find();
                $reason='';
                if ($order['status']!=='paid'||(string)$order['out_trade_no']!==(string)$row['out_trade_no']||(int)$order['member_id']!==(int)$row['payer_member_id']||!$pay||(int)$pay['status']!==(int)PayDict::STATUS_FINISH||$pay['main_type']!==PayDict::MEMBER||Rules::cents((string)$pay['money'])!==Rules::cents((string)$order['amount'])||(int)$pay['main_id']!==(int)$row['payer_member_id']) $reason='原业务或支付凭据变化，暂停结算，请核对';
                elseif (self::hasRefund($site,(string)$row['out_trade_no'])) $reason='原支付存在退款申请或已退款，暂停结算，请人工核对';
                try {$member=self::member($site,(int)$row['provider_member_id'],true);}catch(CommonException $ignored){$reason=$reason?:'收益会员已停用或失效，请人工核对';$member=null;}
                $amount=Rules::cents((string)$row['amount']);
                if ($member&&($amount<=0||Rules::cents((string)$member['commission'])+$amount>self::MAX_CENTS||Rules::cents((string)$member['commission_get'])+$amount>self::MAX_CENTS)) $reason=$reason?:'会员佣金账户金额精度不足，暂停结算，请人工核对';
                if ($reason!=='') {self::query($site)->where('id',$id)->update(['status'=>'held','review_required'=>1,'reason'=>$reason,'update_time'=>time()]);return 'held';}
                $existing=Db::name('member_account_log')->where('site_id',$site)->where('account_type',MemberAccountTypeDict::COMMISSION)->where('from_type',self::INCOME_TYPE)->where('related_id',$id)->find();
                if ($existing) throw new CommonException('推广奖励与框架流水状态不一致，请人工核对，不会重复入账');
                $log=(new CoreMemberAccountService())->addLog($site,(int)$row['provider_member_id'],MemberAccountTypeDict::COMMISSION,(string)$row['amount'],self::INCOME_TYPE,'一级推广奖励 #'.$id,$id);
                self::query($site)->where('id',$id)->update(['status'=>'settled','account_log_id'=>$log,'settled_at'=>time(),'update_time'=>time()]);
                Data::audit($site,0,0,'promotion_reward',(int)$id,'settled',['member_id'=>(int)$row['provider_member_id'],'amount'=>$row['amount']]);return 'settled';
            });
            if ($state!=='skipped') {$result['processed']++;$result[$state]++;}
        }
        return $result;
    }
    /** Bounded scheduler entry. It discovers only sites that have real due promotion awards. */
    public static function tick(int $siteLimit=5): array
    {
        $sites=Db::name('hsx_lead_promotion_reward')->where('status','pending')->where('available_at','<=',time())->group('site_id')->limit(max(1,min(10,$siteLimit)))->column('site_id');
        $results=[];foreach($sites as $site) $results[(int)$site]=self::settleSite((int)$site,50);return $results;
    }
    private static function hasRefund(int $site,string $no): bool
    {
        return Db::name('pay_refund')->where('site_id',$site)->where('out_trade_no',$no)->whereIn('status',[RefundDict::WAIT,RefundDict::DEALING,RefundDict::SUCCESS])->count()>0;
    }
    public function hold(int $id,array $input): array
    {
        $this->admin('hold');$reason=Data::text($input,'reason',1000,true);
        Db::transaction(function()use($id,$reason):void {
            self::lockSite($this->context->site);$row=self::query($this->context->site)->where('id',$id)->lock(true)->find();if (!$row) throw new CommonException('推广奖励不存在或不属于本站');
            self::query($this->context->site)->where('id',$id)->update(['status'=>$row['status']==='settled'?'settled':'held','review_required'=>1,'reason'=>$reason,'update_time'=>time()]);
            $this->context->audit('promotion_reward',$id,'held',['reason'=>$reason,'already_credited'=>$row['status']==='settled']);
        });return $this->presentReward(self::query($this->context->site)->where('id',$id)->find(),true);
    }
    /** Explicit review recovery preserves the original award; it does not itself credit money. */
    public function resume(int $id,array $input): array
    {
        $this->admin('resume');$reason=Data::text($input,'reason',1000,true);$site=$this->context->site;
        Db::transaction(function()use($site,$id,$reason):void {
            self::lockSite($site);$row=self::query($site)->where('id',$id)->lock(true)->find();
            if (!$row||$row['status']!=='held') throw new CommonException('仅暂停且尚未入账的奖励可恢复，请刷新核对；已入账不能再次结算');
            $order=Store::row('coop_order',$site,(int)$row['coop_order_id'],true);
            $pay=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$order['id'])->where('out_trade_no',$row['out_trade_no'])->find();
            if ($order['status']!=='paid'||$order['out_trade_no']!==$row['out_trade_no']||!$pay||(int)$pay['status']!==(int)PayDict::STATUS_FINISH||$pay['main_type']!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$row['payer_member_id']||Rules::cents((string)$pay['money'])!==Rules::cents((string)$order['amount'])) throw new CommonException('原业务或付款凭据仍不一致，不能恢复结算');
            if (self::hasRefund($site,(string)$row['out_trade_no'])) throw new CommonException('原支付仍有待退款、退款中或已退款记录，不能恢复结算');
            $member=self::member($site,(int)$row['provider_member_id'],true);$amount=Rules::cents((string)$row['amount']);
            if ($amount<=0||Rules::cents((string)$member['commission'])+$amount>self::MAX_CENTS||Rules::cents((string)$member['commission_get'])+$amount>self::MAX_CENTS) throw new CommonException('会员佣金账户金额容量仍不足，不能恢复结算');
            if ((int)$row['account_log_id']>0||Db::name('member_account_log')->where('site_id',$site)->where('account_type',MemberAccountTypeDict::COMMISSION)->where('from_type',self::INCOME_TYPE)->where('related_id',$id)->count()>0) throw new CommonException('发现本笔奖励已有账本记录，不能恢复或重复入账，请人工核对');
            self::query($site)->where('id',$id)->update(['status'=>'pending','review_required'=>0,'reason'=>$reason,'update_time'=>time()]);
            $this->context->audit('promotion_reward',$id,'resumed',['reason'=>$reason]);
        });return $this->presentReward(self::query($site)->where('id',$id)->find(),true);
    }
    /** A real framework refund stops uncredited awards. Credited money is flagged, never pretended recovered. */
    public static function refund(array $event): bool
    {
        if (($event['trade_type']??'')!==PaymentService::TRADE_TYPE) return true;
        $site=(int)($event['site_id']??0);$no=(string)($event['refund_no']??'');if ($site<=0||$no==='') throw new CommonException('退款推广核对缺少凭据');
        return Db::transaction(function()use($site,$no):bool {
            self::lockSite($site);$refund=Db::name('pay_refund')->where('site_id',$site)->where('refund_no',$no)->where('status',RefundDict::SUCCESS)->find();if (!$refund) throw new CommonException('未找到成功退款凭据，未变更推广奖励');
            $rows=self::query($site)->where('out_trade_no',$refund['out_trade_no'])->lock(true)->select()->toArray();
            foreach($rows as $row) {
                $credited=$row['status']==='settled';
                self::query($site)->where('id',$row['id'])->update(['status'=>$credited?'settled':'held','review_required'=>1,
                    'reason'=>$credited?'原支付已退款；奖励此前已入框架佣金，需平台人工核对追偿，未自动扣回':'原支付已退款；奖励未入账，已暂停并等待平台核对','update_time'=>time()]);
                Data::audit($site,0,0,'promotion_reward',(int)$row['id'],'refund_review',['refund_no'=>$no,'already_credited'=>$credited]);
            }
            return true;
        });
    }
}
