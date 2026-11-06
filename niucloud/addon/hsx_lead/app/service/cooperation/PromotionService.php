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
        $status=(string)($row['status']??(!empty($row['enabled'])?'approved':'disabled'));
        if (!in_array($status,['none','pending','approved','rejected','disabled'],true)) $status='none';
        return ['member_id'=>$id,'enabled'=>$status==='approved','status'=>$status,'version'=>max(0,(int)($row['version']??0)),
            'disabled_mode'=>($row['disabled_mode']??'normal')==='blocked'?'blocked':'normal',
            'city'=>(string)($row['city']??''),'remark'=>(string)($row['remark']??''),'reason'=>(string)($row['reason']??''),
            'merchant_id'=>(int)($row['merchant_id']??0),'merchant_name'=>(string)($row['merchant_name']??''),
            'contact_name'=>(string)($row['contact_name']??''),'mobile_masked'=>(string)($row['mobile_masked']??''),
            'submitted_at'=>(int)($row['submitted_at']??0),'reviewed_at'=>(int)($row['reviewed_at']??0),'update_time'=>(int)($row['update_time']??0)];
    }
    /** Public extension point: qualification changes only affect future paid facts. */
    public static function resolveRole(int $site,int $id): array
    {
        self::member($site,$id);$provider=self::provider($site,$id);
        $allowed=!(($provider['status']??'')==='disabled'&&($provider['disabled_mode']??'normal')==='blocked');
        $merchant=Data::query('merchant',$site)->where('member_id',$id)->find();
        if ($merchant) {
            $partner=Store::query('partner',$site)->where('merchant_id',$merchant['id'])->find();
            if ((int)$merchant['status']!==1||!$partner||(int)$partner['blocked_at']>0) $allowed=false;
        }
        return ['role'=>!empty($provider['enabled'])?'provider':'normal','promotion_allowed'=>$allowed,'provider'=>$provider];
    }
    public static function rateFor(array $config,string $source,string $role): string { return PromotionRules::rateFor($config,$source,$role); }
    private static function statusLabel(string $status): string
    {
        return ['none'=>'尚未申请','pending'=>'待审核','approved'=>'已通过','rejected'=>'未通过','disabled'=>'已停用'][$status]??'待核对';
    }
    /** Only server-owned merchant facts are copied into an application. */
    private static function applicant(int $site,int $id): array
    {
        $merchant=Data::query('merchant',$site)->where('member_id',$id)->find();
        if (!$merchant) throw new CommonException('请先完善门店资料后再申请服务商');
        $partner=Store::query('partner',$site)->where('merchant_id',$merchant['id'])->find();
        if ((int)$merchant['status']!==1||!$partner||(int)$partner['blocked_at']>0) throw new CommonException('当前门店已停用或停止合作，不能申请服务商');
        foreach(['name','contact_name','mobile','address'] as $key) if (trim((string)($merchant[$key]??''))==='') throw new CommonException('请先补齐门店、联系人、电话及地址后再申请');
        if (!preg_match('/^1[0-9]{10}$/D',(string)$merchant['mobile'])||(int)$merchant['province_id']<=0||(int)$merchant['city_id']<=0||(int)$merchant['district_id']<=0) throw new CommonException('请先核对门店电话及省市区后再申请');
        $region=Data::regionNames($merchant);$city=(string)($region['city_name']??'');
        if ($city==='') throw new CommonException('门店城市暂时无法核对，请先完善门店地区');
        return ['merchant_id'=>(int)$merchant['id'],'merchant_name'=>(string)$merchant['name'],'city'=>$city,
            'contact_name'=>(string)$merchant['contact_name'],'mobile_masked'=>substr($merchant['mobile'],0,3).'****'.substr($merchant['mobile'],-4)];
    }
    private function applicationView(int $id,?array $provider=null): array
    {
        $provider=$provider??self::provider($this->context->site,$id);$status=$provider['status']??'none';$facts=[];$reason='';
        try {$facts=self::applicant($this->context->site,$id);}catch(CommonException $error){$reason=$error->getMessage();}
        if (!self::configuration($this->context->site)['enabled']) $reason='本站暂未开启推广';
        elseif ($status==='approved') $reason='已审核通过，无需重复申请';
        elseif ($status==='pending') $reason='申请已提交，正在等待平台审核';
        elseif ($status==='disabled') $reason=($provider['disabled_mode']??'normal')==='blocked'?'推广资格已被禁止，请联系平台核对，不能重新申请绕过限制':'服务商资格已停用，仍可普通推广；如需恢复请联系平台';
        return ['status'=>$status,'status_label'=>self::statusLabel($status),'version'=>(int)($provider['version']??0),
            'submitted_at'=>(int)($provider['submitted_at']??0),'reviewed_at'=>(int)($provider['reviewed_at']??0),'reason'=>(string)($provider['reason']??''),
            'disabled_mode'=>(string)($provider['disabled_mode']??'normal'),'can_apply'=>$reason==='','apply_reason'=>$reason,
            'merchant_name'=>(string)(($provider['merchant_name']??'')?:($facts['merchant_name']??'')),'city'=>(string)(($provider['city']??'')?:($facts['city']??'')),
            'contact_name'=>(string)(($provider['contact_name']??'')?:($facts['contact_name']??'')),'mobile_masked'=>(string)(($provider['mobile_masked']??'')?:($facts['mobile_masked']??''))];
    }
    public function application(): array { $own=$this->own();return ['application'=>$this->applicationView((int)$own['member_id']),'already_submitted'=>false]; }
    public function apply(array $input): array
    {
        if (!in_array($input['confirm']??null,[true,1,'1'],true)) throw new CommonException('请确认提交服务商申请');
        return Db::transaction(function():array {
            $site=$this->context->site;$id=$this->context->memberId;self::lockSite($site);self::member($site,$id,true);
            $provider=self::provider($site,$id);
            if (in_array($provider['status']??'',['pending','approved'],true)) return ['application'=>$this->applicationView($id,$provider),'already_submitted'=>true];
            $view=$this->applicationView($id,$provider);if (!$view['can_apply']) throw new CommonException($view['apply_reason']);
            $facts=self::applicant($site,$id);$version=(int)($provider['version']??0)+1;
            if ($version>2147483647) throw new CommonException('申请版本已超出可处理范围，请联系平台核对');
            $next=array_replace($provider??[],$facts,['status'=>'pending','enabled'=>false,'version'=>$version,'reason'=>'','disabled_mode'=>'normal',
                'submitted_at'=>time(),'reviewed_at'=>0,'update_time'=>time()]);
            (new CoreConfigService())->setConfig($site,self::PROVIDER_PREFIX.$id,$next);
            $this->context->audit('promotion_provider',$id,'applied',['version'=>$version,'previous_status'=>$provider['status']??'none','merchant_id'=>$facts['merchant_id']]);
            return ['application'=>$this->applicationView($id,self::provider($site,$id)),'already_submitted'=>false];
        });
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
        return self::summary($member)+['provider_enabled'=>(bool)($provider['enabled']??false),'role'=>!empty($provider['enabled'])?'provider':'normal','city'=>(string)($provider['city']??'')];
    }
    public function overview(): array
    {
        $member=$this->own();$id=(int)$member['member_id'];$q=self::query($this->context->site)->where('provider_member_id',$id);$role=self::resolveRole($this->context->site,$id);$config=self::configuration($this->context->site);
        return ['enabled'=>$config['enabled'],'member'=>self::summary($member),'provider'=>$this->publicProvider($role['provider']),
            'role'=>$role['role'],'promotion_allowed'=>$config['enabled']&&$role['promotion_allowed'],'application'=>$this->applicationView($id,$role['provider']),
            'upstream'=>$this->upstream((int)$member['pid']),
            'stats'=>['team_count'=>(int)$this->teamQuery($id)->count(),'pending'=>(string)(clone $q)->where('status','pending')->sum('amount'),
                'settled'=>(string)(clone $q)->where('status','settled')->sum('amount'),'held'=>(string)(clone $q)->where('status','held')->sum('amount')],
            'account'=>PaymentService::accountSummary($this->context->site,$id),'rules'=>$config,
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
        $own=$this->own();$inviter=self::member($this->context->site,$id);$role=self::resolveRole($this->context->site,$id);$provider=$role['provider'];
        $reason='';
        if (!self::configuration($this->context->site)['enabled']) $reason='本站暂未开启推广';
        elseif ($id===(int)$own['member_id']) $reason='不能把自己设为邀请人';
        elseif (!$role['promotion_allowed']) $reason='该邀请人的推广资格已被禁止或门店已停止合作';
        elseif ((int)$own['pid']>0) $reason=(int)$own['pid']===$id?'邀请关系已存在，无需重复绑定':'已有邀请人，本次不会覆盖原关系';
        return ['inviter'=>self::summary($inviter)+['role'=>$role['role'],'city'=>(string)($provider['city']??'')],'current_upstream'=>$this->upstream((int)$own['pid']),'can_bind'=>$reason==='','reason'=>$reason];
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
        if (($params['enabled']??'')!==''||($params['application_status']??'')!==''||($params['role']??'')!=='') {
            $state=(string)($params['application_status']??'');$role=(string)($params['role']??'');
            if ($state!==''&&!in_array($state,['none','pending','approved','rejected','disabled'],true)) throw new CommonException('服务商申请状态无效');
            if ($role!==''&&!in_array($role,['normal','provider'],true)) throw new CommonException('推广身份无效');
            $approved=[];$specified=[];$nonEmpty=[];
            foreach(Db::name('sys_config')->where('site_id',$this->context->site)->whereLike('config_key',addcslashes(self::PROVIDER_PREFIX,'\\%_').'%')->field('config_key,value')->select()->toArray() as $saved) {
                $id=substr($saved['config_key'],strlen(self::PROVIDER_PREFIX));if (!ctype_digit($id)) continue;
                $provider=self::provider($this->context->site,(int)$id);$current=$provider['status']??'none';
                if ($current==='approved') $approved[]=(int)$id;
                if ($current!=='none') $nonEmpty[]=(int)$id;
                if ($state!==''&&$current===$state) $specified[]=(int)$id;
            }
            if ($state==='none') {if ($nonEmpty) $q->whereNotIn('member_id',$nonEmpty);}
            elseif ($state!=='') $q->whereIn('member_id',$specified?:[0]);
            if ($role==='provider') $q->whereIn('member_id',$approved?:[0]);elseif ($role==='normal'&&$approved) $q->whereNotIn('member_id',$approved);
            if (($params['enabled']??'')!=='') {if (PromotionRules::bool($params['enabled'])) $q->whereIn('member_id',$approved?:[0]);elseif ($approved) $q->whereNotIn('member_id',$approved);}
        }
        $page=self::memberPage($q,$params);$page['data']=array_map(fn(array $row):array=>$this->providerItem($row),$page['data']);return $page;
    }
    private function providerItem(array $member): array
    {
        $id=(int)$member['member_id'];$provider=self::provider($this->context->site,$id);
        $shop=Data::query('merchant',$this->context->site)->where('member_id',$id)->find();$region=$shop?Data::regionNames($shop):[];
        try {$role=self::resolveRole($this->context->site,$id);}catch(CommonException $ignored){$role=['role'=>!empty($provider['enabled'])?'provider':'normal','promotion_allowed'=>false];}
        $application=$this->applicationView($id,$provider);if (!$role['promotion_allowed']) {$application['can_apply']=false;$application['apply_reason']='会员、门店或推广资格已停用，请先核对';}
        return self::summary($member)+['status'=>(int)$member['status'],'provider'=>$provider,'application'=>$application,'role'=>$role['role'],'promotion_allowed'=>$role['promotion_allowed'],
            'enabled'=>(bool)($provider['enabled']??false),'city'=>(string)($provider['city']??''),'remark'=>(string)($provider['remark']??''),
            'merchant_name'=>(string)($shop['name']??''),'region_text'=>(string)($region['region_text']??''),'upstream_name'=>(string)($this->upstream((int)$member['pid'])['name']??'')];
    }
    private static function assertProviderVersion(?array $provider,array $input): int
    {
        $version=$input['version']??null;
        if ((!is_int($version)&&!is_string($version))||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',(string)$version)||(int)$version!==(int)($provider['version']??0)||(int)$version>=2147483647) throw new CommonException('服务商资料或申请已变化，请刷新后重新核对，不会覆盖其他人的处理');
        return (int)$version+1;
    }
    public function saveProvider(int $id,array $input): array
    {
        $this->admin('providers_save');$status=$input['status']??(PromotionRules::bool($input['enabled']??null)?'approved':'disabled');
        if (!in_array($status,['approved','disabled'],true)) throw new CommonException('请明确选择手工开通或停用服务商');
        $mode=$input['disabled_mode']??'normal';if (!in_array($mode,['normal','blocked'],true)) throw new CommonException('停用后请选择降为普通推广或禁止推广');
        $reason=Data::text($input,'reason',1000,true);$city=Data::text($input,'city',120);$remark=Data::text($input,'remark',1000);
        Db::transaction(function()use($id,$input,$status,$mode,$reason,$city,$remark):void {
            $site=$this->context->site;self::lockSite($site);self::member($site,$id,true);$old=self::provider($site,$id);$version=self::assertProviderVersion($old,$input);
            if (($old['status']??'')==='pending') throw new CommonException('该会员有待审核申请，请使用审核操作处理');
            $facts=[];$shop=Data::query('merchant',$site)->where('member_id',$id)->find();
            if ($status==='approved'&&$shop) $facts=self::applicant($site,$id);
            $effectiveCity=$facts['city']??($city!==''?$city:($old['city']??''));
            if ($status==='approved'&&$effectiveCity==='') throw new CommonException('请填写服务商负责的城市');
            $next=array_replace($old??[],$facts,['status'=>$status,'enabled'=>$status==='approved','version'=>$version,'disabled_mode'=>$mode,
                'city'=>$effectiveCity,'remark'=>$remark,'reason'=>$reason,'reviewed_at'=>time(),'update_time'=>time(),'operator_uid'=>$this->context->uid]);
            (new CoreConfigService())->setConfig($site,self::PROVIDER_PREFIX.$id,$next);
            $this->context->audit('promotion_provider',$id,$status==='approved'?'manually_approved':'disabled',['version'=>$version,'previous_status'=>$old['status']??'none','disabled_mode'=>$mode,'reason'=>$reason]);
        });return $this->providerItem(self::member($this->context->site,$id));
    }
    public function reviewProvider(int $id,array $input): array
    {
        $this->admin('provider_review');$decision=Data::text($input,'decision',20,true);
        if (!in_array($decision,['approve','reject'],true)) throw new CommonException('请选择通过或拒绝申请');
        $reason=Data::text($input,'reason',1000,$decision==='reject');
        Db::transaction(function()use($id,$input,$decision,$reason):void {
            $site=$this->context->site;self::lockSite($site);self::member($site,$id,true);$old=self::provider($site,$id);$version=self::assertProviderVersion($old,$input);
            if (($old['status']??'')!=='pending') throw new CommonException('该申请已经处理或不在待审状态，请刷新核对');
            if ($decision==='approve') self::applicant($site,$id);
            $next=array_replace($old,['status'=>$decision==='approve'?'approved':'rejected','enabled'=>$decision==='approve',
                'version'=>$version,'reason'=>$reason,'disabled_mode'=>'normal','reviewed_at'=>time(),'update_time'=>time(),'operator_uid'=>$this->context->uid]);
            (new CoreConfigService())->setConfig($site,self::PROVIDER_PREFIX.$id,$next);
            $this->context->audit('promotion_provider',$id,$decision==='approve'?'approved':'rejected',['version'=>$version,'reason'=>$reason]);
        });return $this->providerItem(self::member($this->context->site,$id));
    }
    /** Called only after a matching receipt. Even a non-award decision is frozen. */
    public static function snapshotForOrder(int $site,array $order): array
    {
        if (($order['status']??'')!=='paid'||empty($order['out_trade_no'])||(int)($order['id']??0)<=0||(int)($order['pay_time']??0)<=0) throw new CommonException('推广快照缺少已支付业务凭据');
        $source=PromotionRules::source((string)$order['purpose']);$paidAt=(int)$order['pay_time'];
        $snapshot=['schema_version'=>'hsx_lead.promotion.v1','site_id'=>$site,'order_id'=>(int)$order['id'],'out_trade_no'=>(string)$order['out_trade_no'],
            'payer_member_id'=>(int)$order['member_id'],'paid_at'=>$paidAt,'gross_amount'=>(string)$order['amount'],'source'=>$source,
            'eligible'=>false,'reason'=>'promotion_disabled','inviter_member_id'=>0,'promoter_role'=>'normal','provider_status'=>'none','provider_version'=>0,
            'base_amount'=>'0.00','rate'=>'0.00','amount'=>'0.00','renewal'=>false,'settlement_days'=>0,'eligibility_months'=>0,'bound_at'=>0,'provider_city'=>'','available_at'=>$paidAt];
        $config=self::configuration($site);if (!$config['enabled']) return $snapshot;
        if ($source===''||empty($config['sources'][$source]['enabled'])) return array_replace($snapshot,['reason'=>'source_disabled']);
        $payer=self::member($site,(int)$order['member_id'],true);$inviter=(int)$payer['pid'];
        if ($inviter<=0||$inviter===(int)$payer['member_id']) return array_replace($snapshot,['reason'=>'no_inviter']);
        try {$resolved=self::resolveRole($site,$inviter);}catch(CommonException $ignored){return array_replace($snapshot,['reason'=>'inviter_unavailable','inviter_member_id'=>$inviter]);}
        $provider=$resolved['provider'];$snapshot=array_replace($snapshot,['inviter_member_id'=>$inviter,'promoter_role'=>$resolved['role'],
            'provider_status'=>$provider['status']??'none','provider_version'=>(int)($provider['version']??0),'provider_city'=>(string)($provider['city']??'')]);
        if (!$resolved['promotion_allowed']) return array_replace($snapshot,['reason'=>'promotion_blocked']);
        $relation=self::value($site,self::RELATION_PREFIX.$payer['member_id']);
        $boundAt=(int)($relation['pid']??0)===$inviter?(int)($relation['bound_at']??0):(int)$payer['create_time'];
        $previous=Store::query('coop_order',$site)->where('purpose',$order['purpose'])->where('status','paid')->where('id','<>',$order['id']);
        if ($order['purpose']==='membership') $previous->where('merchant_id',$order['merchant_id']);
        else $previous->where('member_id',$payer['member_id']);
        $renewal=$source!=='maintenance'&&$previous->count()>0;
        $snapshot=array_replace($snapshot,['renewal'=>$renewal,'settlement_days'=>$config['settlement_days'],'eligibility_months'=>$config['eligibility_months'],'bound_at'=>$boundAt,'available_at'=>$paidAt+$config['settlement_days']*86400]);
        if (!PromotionRules::eligible($config,$source,$renewal,$boundAt,$paidAt)) return array_replace($snapshot,['reason'=>'outside_eligibility']);
        $gross=Rules::cents((string)$order['amount']);$base=$source==='membership'?MembershipCostService::promotionBase($order):Rules::cents((string)$order['fee_amount']);
        if ($base>$gross) throw new CommonException('推广计佣基数超过实收，需平台核对，不会减少发布方收益');
        $rate=self::rateFor($config,$source,$resolved['role']);$amount=PromotionRules::reward($base,$rate);
        return array_replace($snapshot,['eligible'=>$amount>0,'reason'=>$amount>0?'eligible':'zero_reward','base_amount'=>Rules::amount($base),'rate'=>$rate,'amount'=>Rules::amount($amount)]);
    }
    private static function assertSnapshot(int $site,array $order,array $snapshot): void
    {
        if (($snapshot['schema_version']??'')!=='hsx_lead.promotion.v1'||(int)($snapshot['site_id']??0)!==$site||(int)($snapshot['order_id']??0)!==(int)$order['id']
            ||($snapshot['out_trade_no']??'')!==$order['out_trade_no']||(int)($snapshot['payer_member_id']??0)!==(int)$order['member_id']||(int)($snapshot['paid_at']??0)!==(int)$order['pay_time']
            ||Rules::cents($snapshot['gross_amount']??'')!==Rules::cents((string)$order['amount'])||($snapshot['source']??'')!==PromotionRules::source((string)$order['purpose'])||!is_bool($snapshot['eligible']??null)) throw new CommonException('原付款推广快照不完整或归属不符，不能按当前规则补算');
        if (!$snapshot['eligible']) return;
        $base=Rules::cents($snapshot['base_amount']??'');$amount=Rules::cents($snapshot['amount']??'');
        $expectedBase=$snapshot['source']==='membership'?MembershipCostService::promotionBase($order):Rules::cents((string)$order['fee_amount']);
        if (!in_array($snapshot['promoter_role']??'',['normal','provider'],true)||(int)($snapshot['inviter_member_id']??0)<=0||(int)$snapshot['inviter_member_id']===(int)$order['member_id']
            ||$base!==$expectedBase||$base>Rules::cents((string)$order['amount'])||$amount<=0||$amount!==PromotionRules::reward($base,(string)($snapshot['rate']??''))
            ||(int)($snapshot['available_at']??0)<(int)$order['pay_time']) throw new CommonException('原推广分账快照无法核对，未生成奖励');
    }
    /** Invoked within the receipt transaction; a later retry uses this same frozen decision. */
    public static function recordPaid(int $site,array $order): void
    {
        if (($order['status']??'')!=='paid'||empty($order['out_trade_no'])) throw new CommonException('推广奖励缺少已支付凭据');
        $saved=Store::row('coop_order',$site,(int)$order['id'],true);
        $receipt=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$saved['id'])->where('out_trade_no',$saved['out_trade_no'])->find();
        if ($saved['status']!=='paid'||$saved['out_trade_no']!==$order['out_trade_no']||!$receipt||(int)$receipt['status']!==(int)PayDict::STATUS_FINISH||$receipt['main_type']!==PayDict::MEMBER||(int)$receipt['main_id']!==(int)$saved['member_id']||Rules::cents((string)$receipt['money'])!==Rules::cents((string)$saved['amount'])) throw new CommonException('未找到可核对的已支付事实，不会生成推广奖励');
        $rule=Data::decode($saved['rule_json']);if (!empty($rule['requires_manual_review'])) return;
        if (self::query($site)->where('coop_order_id',$saved['id'])->find()) return;
        $snapshot=$rule['promotion_snapshot']??null;
        if (!is_array($snapshot)) {
            $snapshot=self::snapshotForOrder($site,$saved);$rule['promotion_snapshot']=$snapshot;
            Store::query('coop_order',$site)->where('id',$saved['id'])->update(['rule_json'=>Data::json($rule)]);
        }
        self::assertSnapshot($site,$saved,$snapshot);if (!$snapshot['eligible']) return;
        self::query($site)->insert(['site_id'=>$site,'coop_order_id'=>$saved['id'],'out_trade_no'=>$saved['out_trade_no'],'source'=>$snapshot['source'],
            'payer_member_id'=>$saved['member_id'],'provider_member_id'=>$snapshot['inviter_member_id'],'base_amount'=>$snapshot['base_amount'],'rate'=>$snapshot['rate'],
            'amount'=>$snapshot['amount'],'status'=>'pending','rule_json'=>Data::json($snapshot),'available_at'=>$snapshot['available_at'],'create_time'=>$snapshot['paid_at'],'update_time'=>time()]);
    }
    public function retryPromotion(int $id): array
    {
        $this->admin('settle');$site=$this->context->site;
        return Db::transaction(function()use($site,$id):array {
            self::lockSite($site);$order=Store::row('coop_order',$site,$id,true);$rule=Data::decode($order['rule_json']);
            if ($order['status']!=='paid'||empty($rule['promotion_review_required'])) throw new CommonException('仅已付款且推广结果待核对的原订单可重试，请刷新核对');
            if (!empty($rule['requires_manual_review'])) throw new CommonException('原付款业务仍需人工核对，不能先补发推广奖励');
            if (self::hasRefund($site,(string)$order['out_trade_no'])) throw new CommonException('原付款存在退款申请、处理中或已退款，不能补发推广奖励');
            if (!is_array($rule['promotion_snapshot']??null)) throw new CommonException('缺少付款时推广快照，不能按当前比例或邀请人补算，请人工核对');
            self::assertSnapshot($site,$order,$rule['promotion_snapshot']);self::recordPaid($site,$order);
            unset($rule['promotion_review_required'],$rule['promotion_review_message']);
            Store::query('coop_order',$site)->where('id',$id)->update(['rule_json'=>Data::json($rule),'update_time'=>time()]);
            $this->context->audit('promotion_reward',$id,'payment_reward_retried',['inviter_member_id'=>$rule['promotion_snapshot']['inviter_member_id'],'role'=>$rule['promotion_snapshot']['promoter_role']]);
            $reward=self::query($site)->where('coop_order_id',$id)->find();
            return ['order_id'=>$id,'status'=>'paid','promotion_review_required'=>false,'reward_created'=>(bool)$reward,'reward'=>$reward?$this->presentReward($reward,true):null];
        });
    }
    public function rewards(array $params=[],bool $admin=false): array
    {
        if ($admin) $this->admin('rewards');else $this->own();$q=self::query($this->context->site);
        if (!$admin) $q->where('provider_member_id',$this->context->memberId);
        elseif (($params['provider_member_id']??'')!=='') $q->where('provider_member_id',\addon\hsx_lead\app\service\OwnershipScope::ownerId($params['provider_member_id']));
        if (($params['status']??'')!=='') {if (!in_array($params['status'],['pending','settled','held'],true)) throw new CommonException('推广奖励状态无效');$q->where('status',$params['status']);}
        if (($params['source']??'')!=='') {if (!in_array($params['source'],['membership','maintenance'],true)) throw new CommonException('推广奖励来源无效');$q->where('source',$params['source']);}
        foreach(['start_time'=>'>=','end_time'=>'<='] as $key=>$op) if ((int)($params[$key]??0)>0) $q->where('create_time',$op,(int)$params[$key]);
        $keyword=Data::text($params,'keyword',80);
        if ($keyword!=='') {
            $members=Db::name('member')->where('site_id',$this->context->site)->whereLike('nickname|username','%'.addcslashes($keyword,'\\%_').'%')->column('member_id');
            $orders=Store::query('coop_order',$this->context->site)->whereLike('order_no','%'.addcslashes($keyword,'\\%_').'%')->column('id');
            $q->where(function($part)use($members,$orders,$admin):void {$part->whereIn('payer_member_id',$members?:[0]);if($admin&&$members)$part->whereOr('provider_member_id','in',$members);if($orders)$part->whereOr('coop_order_id','in',$orders);});
        }
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $row):array=>$this->presentReward($row,$admin),$page['data']);
        if ($admin) $page['payment_reviews']=$this->paymentReviews();return $page;
    }
    private function paymentReviews(): array
    {
        $site=$this->context->site;$q=Store::query('coop_order',$site)->where('status','paid')->whereLike('rule_json','%"promotion_review_required":true%');
        $total=(int)(clone $q)->count();$rows=$q->order('id desc')->limit(10)->select()->toArray();$result=[];
        foreach($rows as $order) {
            $rule=Data::decode($order['rule_json']);$retry=is_array($rule['promotion_snapshot']??null)&&empty($rule['requires_manual_review']);
            $message='原付款已到账，推广奖励待平台核对，请勿重复支付';
            if ($retry) {try {self::assertSnapshot($site,$order,$rule['promotion_snapshot']);}catch(CommonException $ignored){$retry=false;}}
            if (!$retry) $message='原付款已到账，但付款时推广快照或原业务仍待核对，不能按当前规则自动补算';
            elseif (self::hasRefund($site,(string)$order['out_trade_no'])) {$retry=false;$message='原付款存在退款记录，暂不能补发推广奖励';}
            $result[]=['id'=>(int)$order['id'],'order_no'=>(string)$order['order_no'],'purpose'=>(string)$order['purpose'],'amount'=>(string)$order['amount'],
                'pay_time'=>(int)$order['pay_time'],'promotion_review_required'=>true,'message'=>$message,'can_retry'=>$retry];
        }
        return ['data'=>$result,'total'=>$total];
    }
    private function presentReward(array $row,bool $admin): array
    {
        $payer=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['payer_member_id'])->find();
        $provider=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['provider_member_id'])->find();
        $row['payer_name']=$payer?self::summary($payer)['name']:'原会员';$row['provider_name']=$provider?self::summary($provider)['name']:'原推广会员';
        $row['order_no']=(string)(Store::query('coop_order',$this->context->site)->where('id',$row['coop_order_id'])->value('order_no')??'');
        $row['source_label']=['membership'=>'主体会员费','maintenance'=>'平台维护费'][$row['source']]??'待核对';
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
        $refunds=Db::name('pay_refund')->where('site_id',$site)->where('out_trade_no',$no)->whereIn('status',[RefundDict::WAIT,RefundDict::DEALING,RefundDict::SUCCESS])->select()->toArray();
        if(!$refunds)return false;
        $order=Store::query('coop_order',$site)->where('out_trade_no',$no)->where('purpose','membership')->find();
        if($order){$rule=Data::decode($order['rule_json']);if(($rule['schema_version']??'')===EntityMembershipService::SNAPSHOT_SCHEMA){
            try{EntityMembershipService::assertSnapshot($order,$rule);$parts=MembershipCostService::refundedComponents($order,$rule,$refunds);
                // Only the actually refunded service fee affects its referral reward.
                if(!$parts['review']&&$parts['service']===0){
                    $depositReserved=$parts['deposit'];$known=true;$seen=[];
                    foreach($refunds as $refund)if(in_array($refund['status'],[RefundDict::WAIT,RefundDict::DEALING],true)){
                        $number=(string)($refund['refund_no']??'');$amount=Rules::cents((string)$refund['money']);
                        if(isset($seen[$number])){if($seen[$number]!==$amount)$known=false;continue;}$seen[$number]=$amount;
                        $allocation=$rule['membership_refund_allocations'][$number]??null;
                        if(!is_array($allocation)||$amount<=0||Rules::cents($allocation['deposit']??'')!==$amount||Rules::cents($allocation['service']??'')!==0||Rules::cents($allocation['experience']??'')!==0||Rules::cents($allocation['quantity']??'')!==0){$known=false;break;}
                        $depositReserved+=$amount;
                    }
                    // A reviewed deposit-only return cannot freeze an unrelated paid service reward.
                    if($known&&$depositReserved<=Rules::cents($rule['deposit_amount']))return false;
                }
            }catch(CommonException $ignored){}
        }}
        return true;
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
                if(!self::hasRefund($site,(string)$row['out_trade_no']))continue;
                $credited=$row['status']==='settled';
                self::query($site)->where('id',$row['id'])->update(['status'=>$credited?'settled':'held','review_required'=>1,
                    'reason'=>$credited?'原支付已退款；奖励此前已入框架佣金，需平台人工核对追偿，未自动扣回':'原支付已退款；奖励未入账，已暂停并等待平台核对','update_time'=>time()]);
                Data::audit($site,0,0,'promotion_reward',(int)$row['id'],'refund_review',['refund_no'=>$no,'already_credited'=>$credited]);
            }
            return true;
        });
    }
}
