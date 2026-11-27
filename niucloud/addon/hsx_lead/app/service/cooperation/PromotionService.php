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
use think\facade\{Cache,Db};

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
    public static function configuration(int $site): array { return PromotionRules::readConfiguration(self::value($site,self::CONFIG_KEY)); }
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
        $owned=Data::query('merchant',$site)->where('member_id',$id)->find();
        if ($owned&&(int)$owned['status']!==1) $allowed=false;
        $merchant=TeamService::resolve(new Context($site,0,$id),false);
        $level=['code'=>'','level_id'=>0,'name'=>'普通会员','active'=>false];
        if ($merchant) {
            $partner=Store::query('partner',$site)->where('merchant_id',$merchant['id'])->find();
            if ((int)$merchant['status']!==1||!$partner||(int)$partner['blocked_at']>0) $allowed=false;
            if ($allowed&&$partner) {
                $state=EntityMembershipService::state($site,(int)$merchant['id']);
                if (!empty($state['active'])) $level=['code'=>(string)$state['level_code'],'level_id'=>(int)$state['level_id'],'name'=>(string)$state['level_name'],'active'=>true];
            }
        }
        return ['role'=>!empty($provider['enabled'])?'provider':'normal','promotion_allowed'=>$allowed,'provider'=>$provider,'level'=>$level];
    }
    public static function rateFor(array $config,string $source,string $role,string $levelCode='',int $commissionLevel=1): string { return PromotionRules::rateFor($config,$source,$role,$levelCode,$commissionLevel); }
    /** New orders freeze policy; receipts freeze identities and actual earning facts. */
    public static function policyForNewOrder(int $site):array
    {
        return ['schema_version'=>'hsx_lead.promotion.policy.v2','site_id'=>$site,'created_at'=>time(),'configuration'=>PromotionRules::config(self::configuration($site))];
    }
    private static function configView(int $site,array $config): array
    {
        $levels=[];foreach(MembershipCatalogService::levels($site) as $level) $levels[]=['code'=>$level['code'],'level_id'=>$level['level_id'],'name'=>$level['name'],'display_rank'=>$level['display_rank']];
        return $config+['member_levels'=>$levels];
    }
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
        $rates=[];$secondRates=[];foreach($config['sources'] as $source=>$definition){$rates[$source]=self::rateFor($config,$source,$role['role'],$role['level']['code']);$secondRates[$source]=self::rateFor($config,$source,$role['role'],$role['level']['code'],2);}
        return ['enabled'=>$config['enabled'],'member'=>self::summary($member)+['member_no'=>(string)($member['member_no']??'')],'provider'=>$this->publicProvider($role['provider']),
            'level'=>$role['level'],'current_rates'=>$rates,'second_level_rates'=>$secondRates,
            'role'=>$role['role'],'promotion_allowed'=>$config['enabled']&&$role['promotion_allowed'],'application'=>$this->applicationView($id,$role['provider']),
            'upstream'=>$this->upstream((int)$member['pid']),
            'stats'=>['team_count'=>(int)$this->teamQuery($id)->count(),'indirect_team_count'=>(int)$this->teamQuery($id,2)->count(),'pending'=>(string)(clone $q)->where('status','pending')->sum('amount'),
                'settled'=>(string)(clone $q)->where('status','settled')->sum('amount'),'held'=>(string)(clone $q)->where('status','held')->sum('amount')],
            'account'=>PaymentService::accountSummary($this->context->site,$id),'rules'=>self::configView($this->context->site,$config),
            'share'=>['mid'=>$id,'path'=>'/addon/hsx_lead/pages/invite/index?mid='.$id.'&promotion_invite=1']];
    }
    private function publicProvider(?array $provider): ?array { if ($provider) unset($provider['remark']);return $provider; }
    private function teamQuery(int $id,int $commissionLevel=1)
    {
        $q=Db::name('member')->where('site_id',$this->context->site)->where('is_del',0)->where('delete_time',0)->where('member_id','<>',$id);
        if($commissionLevel===1)return$q->where('pid',$id);
        if($commissionLevel!==2)throw new CommonException('请选择一级或二级邀请');
        return$q->whereIn('pid',function($sub)use($id):void{$sub->name('member')->where('site_id',$this->context->site)->where('pid',$id)->where('member_id','<>',$id)->where('status',1)->where('is_del',0)->where('delete_time',0)->field('member_id');});
    }
    private static function memberPage($query,array $params): array
    {
        $page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??20)));
        return ['data'=>(clone $query)->order('member_id desc')->page($page,$size)->select()->toArray(),'total'=>(int)(clone $query)->count(),'per_page'=>$size,'current_page'=>$page];
    }
    public function team(array $params=[]): array
    {
        $member=$this->own();$level=$params['commission_level']??1;if(!in_array($level,[1,2,'1','2'],true))throw new CommonException('请选择一级或二级邀请');$q=$this->teamQuery((int)$member['member_id'],(int)$level);$keyword=Data::text($params,'keyword',80);
        if ($keyword!=='') {
            $like='%'.addcslashes($keyword,'\\%_').'%';
            $ids=Data::query('merchant',$this->context->site)->whereLike('name|contact_name',$like)->column('member_id');
            $q->where(function($part)use($like,$ids):void {$part->whereLike('nickname|username',$like);if($ids)$part->whereOr('member_id','in',$ids);});
        }
        $page=self::memberPage($q,$params);$rows=[];$subjects=[];$subjectErrors=[];$states=[];$merchantIds=[];
        // Resolve only this already-filtered page. Reuse the same owner/staff
        // subject rules as the member centre, not an unscoped merchant scan.
        foreach($page['data'] as $row){
            $id=(int)$row['member_id'];
            try{$shop=TeamService::resolve(new Context($this->context->site,0,$id),false);$subjects[$id]=$shop;if($shop)$merchantIds[(int)$shop['id']]=(int)$shop['id'];}
            catch(CommonException$error){$subjects[$id]=null;$subjectErrors[$id]=true;}
        }
        $paidSubjects=$merchantIds?Store::query('coop_order',$this->context->site)->whereIn('merchant_id',array_values($merchantIds))->where('purpose','membership')->where('status','paid')->distinct(true)->column('merchant_id'):[];
        $paidSubjects=array_fill_keys(array_map('intval',$paidSubjects),true);
        foreach($page['data'] as $row) {
            $id=(int)$row['member_id'];$shop=$subjects[$id]??null;$shared=$shop&&!$shop['is_owner'];
            $region=$shop?Data::regionNames($shop):[];
            $membership=['status'=>'not_opened','status_label'=>'未开通','level_name'=>'','shared'=>(bool)$shared];
            if(isset($subjectErrors[$id]))$membership['status']='review';
            elseif($shop){
                $mid=(int)$shop['id'];
                if(!array_key_exists($mid,$states))try{$states[$mid]=EntityMembershipService::state($this->context->site,$mid);}catch(CommonException$error){$states[$mid]=null;}
                $state=$states[$mid];
                if($state===null)$membership['status']='review';
                elseif(!empty($state['active'])){$membership['status']='active';$membership['level_name']=(string)$state['level_name'];}
                elseif((int)($state['expires_at']??0)>0&&(int)$state['expires_at']<=time()){$membership['status']='expired';$membership['level_name']=(string)$state['level_name'];}
                elseif(isset($paidSubjects[$mid]))$membership['status']='inactive';
            }
            $membership['status_label']=['not_opened'=>'未开通','active'=>'生效中','expired'=>'已到期','inactive'=>'当前未生效','review'=>'待核对'][$membership['status']];
            $stage=$shop?($shared?'已加入门店':'已登记门店'):'待完善门店';
            $rows[]=self::summary($row)+['merchant_name'=>(string)($shop['name']??''),'region_text'=>(string)($region['region_text']??''),
                'stage'=>$stage,'stage_key'=>$shop?'onboarded':'registered','stage_label'=>$stage,'membership'=>$membership,'create_time'=>(int)$row['create_time']];
        }
        $page['data']=$rows;$page['commission_level']=(int)$level;return $page;
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
            $changed=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$this->context->memberId)->where('pid',0)->update(['pid'=>$id]);
            if($changed!==1)throw new CommonException('邀请关系已发生变化，请重新查看；不会覆盖已确认的邀请人');
            (new CoreConfigService())->setConfig($this->context->site,self::RELATION_PREFIX.$this->context->memberId,['pid'=>$id,'bound_at'=>time()]);
            $this->context->audit('promotion_relation',$this->context->memberId,'bound',['inviter_member_id'=>$id]);
            return ['upstream'=>$this->upstream($id),'already_bound'=>false];
        });
    }
    public function rules(): array { $this->own();return self::configView($this->context->site,self::configuration($this->context->site)); }
    public function adminConfig(): array { $this->admin('config');return self::configView($this->context->site,self::configuration($this->context->site)); }
    public function saveConfig(array $input): array
    {
        $this->admin('config_save');$next=PromotionRules::config($input);
        if($next['enabled']&&$next['max_level']===2){
            $table=(string)config('database.connections.mysql.prefix').'hsx_lead_promotion_reward';
            $column=Db::query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,'commission_level']);
            $indexes=Db::query('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table]);$map=[];foreach($indexes as$index)if((int)$index['NON_UNIQUE']===0)$map[$index['INDEX_NAME']][]=$index['COLUMN_NAME'];
            if(!$column||($map['uk_site_order_level']??[])!==['site_id','coop_order_id','commission_level']||isset($map['uk_site_order']))throw new CommonException('二级推广结构尚未升级，请管理员先执行 php addon/hsx_lead/scripts/upgrade-promotion-levels.php --check，备份并执行 --apply 后重试');
        }
        Db::transaction(function()use($next):void {self::lockSite($this->context->site);(new CoreConfigService())->setConfig($this->context->site,self::CONFIG_KEY,$next);$this->context->audit('promotion_config',0,'saved',['enabled'=>$next['enabled']]);});
        return self::configView($this->context->site,self::configuration($this->context->site));
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
        $policy=Data::decode($order['rule_json']??'')['promotion_policy']??null;
        if(is_array($policy))return self::snapshotV2($site,$order,$policy);
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
            'promoter_level_code'=>$resolved['level']['code'],'promoter_level_name'=>$resolved['level']['name'],
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
        $rate=self::rateFor($config,$source,$resolved['role'],$resolved['level']['code']);$amount=PromotionRules::reward($base,$rate);
        return array_replace($snapshot,['eligible'=>$amount>0,'reason'=>$amount>0?'eligible':'zero_reward','base_amount'=>Rules::amount($base),'rate'=>$rate,'amount'=>Rules::amount($amount)]);
    }
    private static function snapshotV2(int $site,array $order,array $policy):array
    {
        if(($policy['schema_version']??'')!=='hsx_lead.promotion.policy.v2'||(int)($policy['site_id']??0)!==$site||!is_array($policy['configuration']??null)||(int)($policy['created_at']??0)<=0)throw new CommonException('原订单推广规则快照不完整，不能按当前配置替代');
        $config=PromotionRules::config($policy['configuration']);$source=PromotionRules::source((string)$order['purpose']);$paid=(int)$order['pay_time'];
        $snapshot=['schema_version'=>'hsx_lead.promotion.v2','site_id'=>$site,'order_id'=>(int)$order['id'],'out_trade_no'=>(string)$order['out_trade_no'],'payer_member_id'=>(int)$order['member_id'],'paid_at'=>$paid,'gross_amount'=>(string)$order['amount'],'source'=>$source,'eligible'=>false,'reason'=>'promotion_disabled','max_level'=>$config['max_level'],'rewards'=>[]];
        if(!$config['enabled'])return$snapshot;if($source===''||empty($config['sources'][$source]['enabled']))return array_replace($snapshot,['reason'=>'source_disabled']);
        $payer=self::member($site,(int)$order['member_id'],true);$previous=Store::query('coop_order',$site)->where('purpose',$order['purpose'])->where('status','paid')->where('id','<>',$order['id']);
        if($order['purpose']==='membership')$previous->where('merchant_id',$order['merchant_id']);else$previous->where('member_id',$payer['member_id']);
        $renewal=$source!=='maintenance'&&$previous->count()>0;$base=$source==='membership'?MembershipCostService::promotionBase($order):Rules::cents((string)$order['fee_amount']);
        if($base>Rules::cents((string)$order['amount']))throw new CommonException('推广计佣基数超过实收');
        $child=$payer;$seen=[(int)$payer['member_id']=>true];$total=0;
        for($level=1;$level<=$config['max_level'];$level++){
            $id=(int)$child['pid'];if($id<=0){$snapshot['reason']='no_inviter';break;}if(isset($seen[$id])){$snapshot['reason']='invalid_relation';break;}$seen[$id]=true;
            try{$parent=self::member($site,$id);$resolved=self::resolveRole($site,$id);}catch(CommonException$ignored){$snapshot['reason']='inviter_unavailable';break;}
            // An invalid or blocked intermediate node never promotes its ancestor.
            if(!$resolved['promotion_allowed']){$snapshot['reason']='promotion_blocked';break;}
            $relation=self::value($site,self::RELATION_PREFIX.$child['member_id']);$bound=(int)($relation['pid']??0)===$id?(int)($relation['bound_at']??0):(int)$child['create_time'];$provider=$resolved['provider'];
            $rate=self::rateFor($config,$source,$resolved['role'],$resolved['level']['code'],$level);$allowed=PromotionRules::eligible($config,$source,$renewal,$bound,$paid);$amount=$allowed?PromotionRules::reward($base,$rate):0;$total+=$amount;
            $entry=['commission_level'=>$level,'inviter_member_id'=>$id,'promoter_role'=>$resolved['role'],'promoter_level_code'=>$resolved['level']['code'],'promoter_level_name'=>$resolved['level']['name'],'provider_status'=>$provider['status']??'none','provider_version'=>(int)($provider['version']??0),'provider_city'=>(string)($provider['city']??''),'base_amount'=>Rules::amount($base),'rate'=>$rate,'amount'=>Rules::amount($amount),'eligible'=>$amount>0,'reason'=>!$allowed?'outside_eligibility':($amount>0?'eligible':'zero_reward'),'renewal'=>$renewal,'settlement_days'=>$config['settlement_days'],'eligibility_months'=>$config['eligibility_months'],'bound_at'=>$bound,'available_at'=>$paid+$config['settlement_days']*86400];
            $snapshot['rewards'][]=$entry;$child=$parent;
            if(!$allowed)break;
        }
        if($total>$base)throw new CommonException('各级推广奖励超过原计佣基数');$snapshot['eligible']=$total>0;if($total>0)$snapshot['reason']='eligible';return$snapshot;
    }
    private static function assertSnapshot(int $site,array $order,array $snapshot): void
    {
        if(($snapshot['schema_version']??'')==='hsx_lead.promotion.v2'){
            if(!in_array($snapshot['max_level']??null,[1,2],true)||!is_array($snapshot['rewards']??null)||count($snapshot['rewards'])>$snapshot['max_level'])throw new CommonException('原多级推广快照不完整');
            $ids=[];$total=0;$eligible=false;
            foreach($snapshot['rewards']as$position=>$entry){if(!is_array($entry)||(int)($entry['commission_level']??0)!==$position+1||isset($ids[(int)($entry['inviter_member_id']??0)]))throw new CommonException('原推广层级或受益人重复，不能生成奖励');$ids[(int)$entry['inviter_member_id']]=true;
                $single=array_replace($snapshot,$entry,['schema_version'=>'hsx_lead.promotion.v1']);unset($single['rewards']);self::assertSnapshot($site,$order,$single);if($entry['eligible']){$total+=Rules::cents($entry['amount']);$eligible=true;}}
            $header=array_replace($snapshot,['schema_version'=>'hsx_lead.promotion.v1','eligible'=>false]);self::assertSnapshot($site,$order,$header);
            $base=$snapshot['source']==='membership'?MembershipCostService::promotionBase($order):Rules::cents((string)$order['fee_amount']);if($total>$base||$snapshot['eligible']!==$eligible)throw new CommonException('多级奖励合计与原快照不一致');return;
        }
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
    /** Settlement and refund reserve consume the same immutable order decision. */
    public static function assertRewardSnapshot(int$site,array$order,array$row):void
    {
        $snapshot=Data::decode($order['rule_json'])['promotion_snapshot']??null;if(!is_array($snapshot))throw new CommonException('原推广付款快照缺失，请平台核对');self::assertSnapshot($site,$order,$snapshot);
        $level=(int)($row['commission_level']??1);$entry=($snapshot['schema_version']??'')==='hsx_lead.promotion.v2'?($snapshot['rewards'][$level-1]??null):($level===1?$snapshot:null);
        if(!is_array($entry)||empty($entry['eligible'])||(int)$row['site_id']!==$site||(int)$row['coop_order_id']!==(int)$order['id']||(int)$row['provider_member_id']!==(int)$entry['inviter_member_id']||(int)$row['payer_member_id']!==(int)$order['member_id']||(string)$row['out_trade_no']!==(string)$order['out_trade_no']||$row['source']!==$snapshot['source']||(int)$row['available_at']!==(int)$entry['available_at'])throw new CommonException('原奖励层级或收款归属与冻结快照不符');
        foreach(['base_amount','rate','amount']as$key)if(Rules::cents((string)$row[$key])!==Rules::cents((string)$entry[$key]))throw new CommonException('原奖励金额与冻结快照不符');
    }
    /** Invoked within the receipt transaction; a later retry uses this same frozen decision. */
    public static function recordPaid(int $site,array $order): void
    {
        if (($order['status']??'')!=='paid'||empty($order['out_trade_no'])) throw new CommonException('推广奖励缺少已支付凭据');
        $saved=Store::row('coop_order',$site,(int)$order['id'],true);
        $receipt=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$saved['id'])->where('out_trade_no',$saved['out_trade_no'])->find();
        if ($saved['status']!=='paid'||$saved['out_trade_no']!==$order['out_trade_no']||!PaymentService::receiptMatches($receipt,$saved)) throw new CommonException('未找到可核对的已支付事实，不会生成推广奖励');
        $rule=Data::decode($saved['rule_json']);if (!empty($rule['requires_manual_review'])) return;
        $snapshot=$rule['promotion_snapshot']??null;
        if (!is_array($snapshot)) {
            $snapshot=self::snapshotForOrder($site,$saved);$rule['promotion_snapshot']=$snapshot;
            Store::query('coop_order',$site)->where('id',$saved['id'])->update(['rule_json'=>Data::json($rule)]);
        }
        self::assertSnapshot($site,$saved,$snapshot);$entries=($snapshot['schema_version']??'')==='hsx_lead.promotion.v2'?$snapshot['rewards']:[array_replace($snapshot,['commission_level'=>1])];$expected=[];
        foreach($entries as$entry)if($entry['eligible'])$expected[(int)$entry['commission_level']]=$entry;
        $existing=self::query($site)->where('coop_order_id',$saved['id'])->lock(true)->select()->toArray();
        foreach($existing as$row){$level=(int)($row['commission_level']??1);$entry=$expected[$level]??null;if(!$entry||(int)$row['provider_member_id']!==(int)$entry['inviter_member_id']||(int)$row['payer_member_id']!==(int)$saved['member_id']||(string)$row['out_trade_no']!==(string)$saved['out_trade_no']||$row['source']!==$snapshot['source']||Rules::cents((string)$row['base_amount'])!==Rules::cents($entry['base_amount'])||Rules::cents((string)$row['rate'])!==Rules::cents($entry['rate'])||Rules::cents((string)$row['amount'])!==Rules::cents($entry['amount']))throw new CommonException('原推广奖励与冻结层级不符，不会重复发放');unset($expected[$level]);}
        foreach($expected as$level=>$entry)self::query($site)->insert(['site_id'=>$site,'coop_order_id'=>$saved['id'],'commission_level'=>$level,'out_trade_no'=>$saved['out_trade_no'],'source'=>$snapshot['source'],
            'payer_member_id'=>$saved['member_id'],'provider_member_id'=>$entry['inviter_member_id'],'base_amount'=>$entry['base_amount'],'rate'=>$entry['rate'],
            'amount'=>$entry['amount'],'status'=>'pending','rule_json'=>Data::json(array_replace($snapshot,$entry)),'available_at'=>$entry['available_at'],'create_time'=>$snapshot['paid_at'],'update_time'=>time()]);
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
            $rewards=self::query($site)->where('coop_order_id',$id)->order('commission_level')->select()->toArray();$rewards=$this->presentRewards($rewards,true);
            $this->context->audit('promotion_reward',$id,'payment_reward_retried',['reward_ids'=>array_column($rewards,'id')]);
            try{Cache::delete(self::paymentReviewsCacheKey($site));}catch(\Throwable $ignored){}
            return ['order_id'=>$id,'status'=>'paid','promotion_review_required'=>false,'reward_created'=>(bool)$rewards,'reward'=>$rewards[0]??null,'rewards'=>$rewards];
        });
    }
    public function rewards(array $params=[],bool $admin=false): array
    {
        if ($admin) $this->admin('rewards');else $this->own();$q=self::query($this->context->site);
        if (!$admin) $q->where('provider_member_id',$this->context->memberId);
        elseif (($params['provider_member_id']??'')!=='') $q->where('provider_member_id',\addon\hsx_lead\app\service\OwnershipScope::ownerId($params['provider_member_id']));
        if (($params['status']??'')!=='') {if (!in_array($params['status'],['pending','settled','held'],true)) throw new CommonException('推广奖励状态无效');$q->where('status',$params['status']);}
        if (($params['source']??'')!=='') {if (!in_array($params['source'],['membership','maintenance'],true)) throw new CommonException('推广奖励来源无效');$q->where('source',$params['source']);}
        if(($params['commission_level']??'')!==''){if(!in_array($params['commission_level'],[1,2,'1','2'],true))throw new CommonException('请选择一级或二级奖励');$q->where('commission_level',(int)$params['commission_level']);}
        foreach(['start_time'=>'>=','end_time'=>'<='] as $key=>$op) if ((int)($params[$key]??0)>0) $q->where('create_time',$op,(int)$params[$key]);
        $keyword=Data::text($params,'keyword',80);
        if ($keyword!=='') {
            // Bound the IN lists; a keyword this broad should be narrowed by the operator.
            $members=Db::name('member')->where('site_id',$this->context->site)->whereLike('nickname|username','%'.addcslashes($keyword,'\\%_').'%')->limit(1000)->column('member_id');
            $orders=Store::query('coop_order',$this->context->site)->whereLike('order_no','%'.addcslashes($keyword,'\\%_').'%')->limit(1000)->column('id');
            $q->where(function($part)use($members,$orders,$admin):void {$part->whereIn('payer_member_id',$members?:[0]);if($admin&&$members)$part->whereOr('provider_member_id','in',$members);if($orders)$part->whereOr('coop_order_id','in',$orders);});
        }
        $page=Data::page($q,$params);$page['data']=$this->presentRewards($page['data'],$admin);
        if ($admin) $page['payment_reviews']=$this->paymentReviews();return $page;
    }
    /** One read-only list over existing cooperation receipts and promotion facts.
     * A member needs no paid plan or merchant record to view their own promotion.
     * Publisher income is included only if the framework ledger credited this
     * exact member; joining another store never grants access to its finances.
     */
    public function income(array $params=[]): array
    {
        $this->own();$site=$this->context->site;$id=$this->context->memberId;
        $type=(string)($params['income_type']??'all');
        if (!in_array($type,['all','publisher','membership','maintenance'],true)) throw new CommonException('请选择有效的收益类型');
        $status=(string)($params['status']??'');
        if (!in_array($status,['','pending','settled','held'],true)) throw new CommonException('请选择有效的收益状态');
        $prefix=(string)config('database.connections.mysql.prefix');
        $rewards=Db::name('hsx_lead_promotion_reward')->alias('r')->where('r.site_id',$site)->where('r.provider_member_id',$id)
            ->leftJoin([$prefix.'member'=>'m'],'m.site_id=r.site_id AND m.member_id=r.payer_member_id')
            ->leftJoin([$prefix.'hsx_lead_coop_order'=>'o'],'o.site_id=r.site_id AND o.id=r.coop_order_id')
            ->fieldRaw("r.id,CONCAT('promotion:',r.id) AS record_key,r.source AS income_type,r.amount,r.status,r.review_required,r.reason,r.create_time,r.available_at,o.order_no,COALESCE(NULLIF(m.nickname,''),NULLIF(m.username,''),'原会员') AS payer_name,'' AS customer_name,'' AS lead_no,r.commission_level");
        $published=Db::name('hsx_lead_coop_order')->alias('o')
            ->join([$prefix.'hsx_lead_collaboration'=>'c'],'c.payment_order_id=o.id AND c.site_id=o.site_id')
            ->join([$prefix.'member_account_log'=>'a'],"a.related_id=o.id AND a.site_id=o.site_id AND a.account_type='commission' AND a.from_type='".PaymentService::INCOME_TYPE."'")
            ->where('o.site_id',$site)->where('a.member_id',$id)->where('a.account_data','>',0)
            ->where('c.status','paid')->where('o.purpose','commission')->where('o.status','paid')
            ->fieldRaw("o.id,CONCAT('publisher:',o.id) AS record_key,'publisher' AS income_type,o.net_amount AS amount,'settled' AS status,0 AS review_required,'' AS reason,o.pay_time AS create_time,o.pay_time AS available_at,o.order_no,'' AS payer_name,c.customer_name,c.lead_no,NULL AS commission_level");
        if(($params['commission_level']??'')!==''){if(!in_array($params['commission_level'],[1,2,'1','2'],true))throw new CommonException('请选择一级或二级奖励');$rewards->where('r.commission_level',(int)$params['commission_level']);$published->whereRaw('1=0');}
        if ($type==='publisher') $query=Db::table($published->buildSql().' income');
        elseif ($type!=='all') {$rewards->where('r.source',$type);$query=Db::table($rewards->buildSql().' income');}
        else $query=Db::table($rewards->unionAll($published->buildSql())->buildSql().' income');
        if ($status!=='') $query->where('status',$status);
        foreach(['start_time'=>'>=','end_time'=>'<='] as $key=>$operator) if ((int)($params[$key]??0)>0) $query->where('create_time',$operator,(int)$params[$key]);
        $page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??$params['page_size']??20)));
        $total=(int)(clone $query)->count();$rows=$query->order('create_time desc,record_key desc')->page($page,$size)->select()->toArray();
        foreach($rows as &$row) {
            $row['review_required']=(bool)$row['review_required'];$row['source']=$row['income_type'];
            $row['title']=['publisher'=>'线索交付收益','membership'=>'会员服务费推广佣金','maintenance'=>'平台维护费推广佣金'][$row['income_type']]??'推广收益';
            $row['status_label']=$row['review_required']&&$row['status']==='settled'?'已入账·待平台核对':(['pending'=>'待结算','settled'=>'已入账','held'=>'暂停待核对'][$row['status']]??'待核对');
        }unset($row);
        return ['account'=>PaymentService::accountSummary($site,$id),'entries'=>['data'=>$rows,'total'=>$total,'current_page'=>$page,'per_page'=>$size],
            'types'=>[['value'=>'all','label'=>'全部'],['value'=>'publisher','label'=>'交付收益'],['value'=>'membership','label'=>'会员服务费'],['value'=>'maintenance','label'=>'平台维护费']]];
    }
    private static function paymentReviewsCacheKey(int $site): string { return 'hsx_lead_promotion_payment_reviews_'.$site; }
    /** The rule_json LIKE scan covers every paid order, so the admin list reuses it briefly; retry clears it.
     * Display only: retryPromotion re-validates everything server-side. */
    private function paymentReviews(): array
    {
        $key=self::paymentReviewsCacheKey($this->context->site);
        try{$cached=Cache::get($key);}catch(\Throwable $ignored){$cached=null;}
        if(is_array($cached))return $cached;
        $result=$this->scanPaymentReviews();
        try{Cache::set($key,$result,60);}catch(\Throwable $ignored){}
        return $result;
    }
    private function scanPaymentReviews(): array
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
    /** Batch the member and source-order reads for a page instead of three queries per reward row. */
    private function presentRewards(array $rows,bool $admin): array
    {
        if(!$rows)return[];$site=$this->context->site;
        $memberIds=array_values(array_unique(array_map('intval',array_merge(array_column($rows,'payer_member_id'),array_column($rows,'provider_member_id')))));
        $members=array_column(Db::name('member')->where('site_id',$site)->whereIn('member_id',$memberIds)->select()->toArray(),null,'member_id');
        $orderIds=array_values(array_unique(array_map('intval',array_column($rows,'coop_order_id'))));
        $orders=array_column(Store::query('coop_order',$site)->whereIn('id',$orderIds)->field('id,order_no,rule_json')->select()->toArray(),null,'id');
        return array_map(fn(array $row):array=>$this->presentReward($row,$admin,$members,$orders),$rows);
    }
    private function presentReward(array $row,bool $admin,?array $members=null,?array $orders=null): array
    {
        if($members===null){
            $payer=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['payer_member_id'])->find();
            $provider=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['provider_member_id'])->find();
        }else{$payer=$members[(int)$row['payer_member_id']]??null;$provider=$members[(int)$row['provider_member_id']]??null;}
        $row['payer_name']=$payer?self::summary($payer)['name']:'原会员';$row['provider_name']=$provider?self::summary($provider)['name']:'原推广会员';
        $sourceOrder=$orders===null?Store::query('coop_order',$this->context->site)->where('id',$row['coop_order_id'])->field('order_no,rule_json')->find():($orders[(int)$row['coop_order_id']]??null);
        $row['order_no']=(string)($sourceOrder['order_no']??'');
        $row['source_label']=['membership'=>'会员服务费','maintenance'=>'平台维护费'][$row['source']]??'待核对';
        $row['status_label']=!empty($row['review_required'])&&$row['status']==='settled'?'已入账·待人工追偿':(['pending'=>'待结算','settled'=>'已入框架佣金','held'=>'暂停待核对'][$row['status']]??'待核对');
        $sourceRefund=$sourceOrder?Data::decode($sourceOrder['rule_json'])[PaymentRefundService::KEY]??[]:[];
        if(($sourceRefund['status']??'')==='refunded'&&$row['status']==='held'&&empty($row['review_required']))$row['status_label']=(int)$row['account_log_id']>0?'原推广奖励已退回':'原推广奖励已取消';
        $row['commission_level']=(int)($row['commission_level']??1);$row['commission_level_label']=$admin?($row['commission_level']===2?'二级奖励':'一级奖励'):($row['commission_level']===1?'直接邀请奖励':'推广奖励');$row['review_required']=(bool)$row['review_required'];$row['rule']=Data::decode($row['rule_json']);unset($row['rule_json']);
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
                if ($order['status']!=='paid'||(string)$order['out_trade_no']!==(string)$row['out_trade_no']||(int)$order['member_id']!==(int)$row['payer_member_id']||!PaymentService::receiptMatches($pay,$order)) $reason='原业务或支付凭据变化，暂停结算，请核对';
                elseif (!empty(Data::decode($order['rule_json'])['requires_manual_review'])) $reason='原付款业务存在待核对记录，暂停结算，请平台核对';
                elseif (!empty(Data::decode($order['rule_json'])['promotion_review_required'])) $reason='原付款推广层级尚待核对，请先按原快照恢复完整奖励';
                elseif (self::hasRefund($site,(string)$row['out_trade_no'])) $reason='原支付存在退款申请或已退款，暂停结算，请人工核对';
                try{self::assertRewardSnapshot($site,$order,$row);}catch(CommonException$ignored){$reason=$reason?:'原奖励与付款快照不一致，暂停入账，请平台核对';}
                try {$member=self::member($site,(int)$row['provider_member_id'],true);}catch(CommonException $ignored){$reason=$reason?:'收益会员已停用或失效，请人工核对';$member=null;}
                $amount=Rules::cents((string)$row['amount']);
                if ($member&&($amount<=0||Rules::cents((string)$member['commission'])+$amount>self::MAX_CENTS||Rules::cents((string)$member['commission_get'])+$amount>self::MAX_CENTS)) $reason=$reason?:'会员佣金账户金额精度不足，暂停结算，请人工核对';
                if ($reason!=='') {self::query($site)->where('id',$id)->update(['status'=>'held','review_required'=>1,'reason'=>$reason,'update_time'=>time()]);return 'held';}
                $existing=Db::name('member_account_log')->where('site_id',$site)->where('account_type',MemberAccountTypeDict::COMMISSION)->where('from_type',self::INCOME_TYPE)->where('related_id',$id)->find();
                if ($existing) throw new CommonException('推广奖励与框架流水状态不一致，请人工核对，不会重复入账');
                $log=(new CoreMemberAccountService())->addLog($site,(int)$row['provider_member_id'],MemberAccountTypeDict::COMMISSION,(string)$row['amount'],self::INCOME_TYPE,((int)($row['commission_level']??1)===1?'直接邀请奖励':'推广奖励').' #'.$id,$id);
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
            self::assertRewardSnapshot($site,$order,$row);
            if (!empty(Data::decode($order['rule_json'])['requires_manual_review'])) throw new CommonException('原付款业务仍待核对，不能恢复结算');
            $pay=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$order['id'])->where('out_trade_no',$row['out_trade_no'])->find();
            if ($order['status']!=='paid'||$order['out_trade_no']!==$row['out_trade_no']||(int)$order['member_id']!==(int)$row['payer_member_id']||!PaymentService::receiptMatches($pay,$order)) throw new CommonException('原业务或付款凭据仍不一致，不能恢复结算');
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
            if($refund['type']===PayDict::BALANCEPAY){
                $balanceSource=Store::query('coop_order',$site)->where('id',(int)$refund['trade_id'])->where('out_trade_no',$refund['out_trade_no'])->lock(true)->find();
                if($balanceSource){$balancePay=OrderPaymentReadService::payment($balanceSource,true);if($balancePay)OrderPaymentReadService::assertManualSource($balanceSource,$balancePay);}
            }
            if($refund['type']===PayDict::OFFLINEPAY){
                $manualSource=Store::query('coop_order',$site)->where('id',(int)$refund['trade_id'])->where('out_trade_no',$refund['out_trade_no'])->lock(true)->find();
                if(!$manualSource)throw new CommonException('原线下退款业务单无法核对，未变更推广奖励');
                ManualRefundService::assertRefundReceipt($site,$manualSource,$refund);
            }
            $rows=self::query($site)->where('out_trade_no',$refund['out_trade_no'])->lock(true)->select()->toArray();
            $managedOrder=Store::query('coop_order',$site)->where('out_trade_no',$refund['out_trade_no'])->find();
            $managed=$managedOrder?Data::decode($managedOrder['rule_json'])[PaymentRefundService::KEY]??[]:[];
            foreach($rows as $row) {
                if(!self::hasRefund($site,(string)$row['out_trade_no']))continue;
                if(($managed['refund_no']??'')===$no&&($managed['status']??'')==='refunded'){
                    // The reviewed order refund already reclaimed every credited reward atomically before dispatch.
                    self::query($site)->where('id',$row['id'])->update(['status'=>'held','review_required'=>0,'reason'=>(int)$row['account_log_id']>0?'原支付已退款，原推广奖励已从可用佣金退回':'原支付已退款，未发放的推广奖励已取消','update_time'=>time()]);continue;
                }
                $credited=$row['status']==='settled';
                self::query($site)->where('id',$row['id'])->update(['status'=>$credited?'settled':'held','review_required'=>1,
                    'reason'=>$credited?'原支付已退款；奖励此前已入框架佣金，需平台人工核对追偿，未自动扣回':'原支付已退款；奖励未入账，已暂停并等待平台核对','update_time'=>time()]);
                Data::audit($site,0,0,'promotion_reward',(int)$row['id'],'refund_review',['refund_no'=>$no,'already_credited'=>$credited]);
            }
            return true;
        });
    }
}
