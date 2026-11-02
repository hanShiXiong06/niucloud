<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\provider\ProviderRegistry;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\{Data,InventoryAvailability,MerchantRegion};
use core\exception\CommonException;
use think\facade\Db;

final class PartnerService
{
    private array $inventoryCache=[];
    public function __construct(private Context $context) {}

    public function own(bool $writable=false): array
    {
        $m=$this->context->merchant();$p=Store::partner($this->context->site,(int)$m['id']);
        if ($writable && (int)$p['blocked_at']>0) throw new CommonException('该商户已永久停止合作；历史记录与合法余额仍保留，可联系平台处理');
        return $m+$p;
    }

    public function bootstrap(): array
    {
        $config=(new ConfigService($this->context))->publicConfig();$memberId=$this->context->memberId;
        $member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$memberId)->where('status',1)->where('is_del',0)->where('delete_time',0)->find();
        if (!$member) throw new CommonException('请先登录本站有效会员');
        $m=Data::query('merchant',$this->context->site)->where('member_id',$memberId)->find();
        if (!$m) return ['profile'=>null,'config'=>$config,'stats'=>[],'wallet'=>null,'providers'=>ProviderRegistry::supported(),'needs_onboarding'=>true];
        $profile=$this->present($m,true);$id=(int)$m['id'];
        $out=Store::query('collaboration',$this->context->site)->where('publisher_id',$id);
        $in=Store::query('collaboration',$this->context->site)->where('receiver_id',$id);
        $stats=['outgoing'=>(int)(clone $out)->count(),'incoming'=>(int)(clone $in)->count(),'selecting'=>(int)(clone $out)->where('status','selecting')->count(),
            'assigned'=>(int)(clone $in)->where('status','assigned')->count(),'following'=>(int)(clone $in)->where('status','following')->count(),'unpaid'=>(int)(clone $in)->where('status','unpaid')->count()];
        return ['profile'=>$profile,'config'=>$config,'stats'=>$stats,'wallet'=>['available'=>$profile['available_balance'],'frozen'=>$profile['frozen_balance'],'total_income'=>$profile['total_income'],'withdrawn'=>$profile['withdrawn_amount']],
            'providers'=>ProviderRegistry::supported(),'needs_onboarding'=>false];
    }

    public function saveProfile(array $input): array
    {
        $memberId=$this->context->memberId;if ($memberId<=0) throw new CommonException('请先使用会员身份登录');
        $region=Data::region($input);$phone=self::phone($input['mobile']??'');
        $provider=Data::text($input,'provider',60,true);(new ProviderRegistry())->definition($provider);
        $values=$region+['name'=>Data::text($input,'name',120,true),'contact_name'=>Data::text($input,'contact_name',60,true),'mobile'=>$phone,'address'=>Data::text($input,'address',500,true)];
        $id=Db::transaction(function()use($memberId,$values,$provider,$phone):int {
            $member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$memberId)->lock(true)->find();
            if (!$member||(int)$member['status']!==1||(int)$member['is_del']!==0||(int)$member['delete_time']>0) throw new CommonException('会员已停用，请重新登录');
            $old=Data::query('merchant',$this->context->site)->where('member_id',$memberId)->lock(true)->find();
            if ($old) {
                $id=(int)$old['id'];$p=Store::partner($this->context->site,$id,true);
                if ((int)$p['blocked_at']>0||(int)$old['status']!==1) throw new CommonException('当前商户已停止合作，不能通过重填资料重新入驻');
            } else {
                $site=Db::name('site')->where('site_id',$this->context->site)->lock(true)->find();if (!$site) throw new CommonException('站点不存在');
                $blocked=Db::name('hsx_lead_merchant')->alias('m')->join('hsx_lead_partner p','p.site_id=m.site_id AND p.merchant_id=m.id')
                    ->where('m.site_id',$this->context->site)->where('p.blocked_at','>',0)->field('m.mobile,m.member_id')->select()->toArray();
                foreach($blocked as $row) if ((int)$row['member_id']===$memberId||self::phone($row['mobile'])===$phone) throw new CommonException('该会员或登记手机号已停止合作，无法重新入驻');
                $id=(int)Data::query('merchant',$this->context->site)->insertGetId($values+['site_id'=>$this->context->site,'member_id'=>$memberId,'status'=>1,'create_time'=>time(),'update_time'=>time()]);
                $p=Store::partner($this->context->site,$id,true);
            }
            Data::query('merchant',$this->context->site)->where('id',$id)->update($values+['update_time'=>time()]);
            Store::query('partner',$this->context->site)->where('id',$p['id'])->update(['provider_requested'=>$provider,'update_time'=>time()]);
            $this->context->audit('partner',$id,'profile_saved',['provider'=>$provider]);return $id;
        });
        return $this->present(Data::row('merchant',$this->context->site,$id),true);
    }

    public function present(array $merchant,bool $private=false,?array $inventory=null): array
    {
        $id=(int)$merchant['id'];$p=Store::partner($this->context->site,$id);$config=(new ConfigService($this->context))->get();
        $inventory=$inventory??$this->inventory($id,$config);$count=count($inventory);$grades=array_values(array_filter($config['grades'],static fn(array $g):bool=>(int)$g['status']===1));
        usort($grades,static fn(array $a,array $b):int=>(int)$b['rank']<=>(int)$a['rank']);$grade=null;
        if ($p['grade_mode']==='manual') foreach($grades as $g) if ($g['id']===$p['grade_id']) $grade=$g;
        if (!$grade) foreach($grades as $g) if ($count>=(int)$g['min_inventory']) {$grade=$g;break;}
        $grade=$grade??['id'=>'','name'=>'未分级','rank'=>0,'all_models'=>false];
        $row=Data::regionNames(array_intersect_key($merchant,array_flip(['id','name','contact_name','mobile','province_id','city_id','district_id','address','status','create_time','update_time'])));
        $conn=Data::query('connection',$this->context->site)->where('merchant_id',$id)->field('id,provider,enabled,last_sync_status,last_sync_at,last_sync_message,active_run_id,published_run_id,sync_interval_minutes')->find();
        $row+=['grade_id'=>$grade['id'],'grade_name'=>$grade['name'],'grade_rank'=>(int)$grade['rank'],'grade_mode'=>$p['grade_mode'],'all_models'=>(bool)$grade['all_models'],
            'certification_status'=>$p['certification_status'],'is_reliable'=>$p['certification_status']==='active'&&(int)$p['cert_expires_at']>time()&&(int)$p['blocked_at']===0,
            'cert_expires_at'=>(int)$p['cert_expires_at'],'publisher_until'=>(int)$p['publisher_until'],'blocked_at'=>(int)$p['blocked_at'],'blocked'=>(int)$p['blocked_at']>0,
            'inventory_count'=>$count,'provider_requested'=>$p['provider_requested'],'match_models'=>[],
            'connection'=>$conn?['connected'=>true,'connection_id'=>(int)$conn['id'],'provider'=>$conn['provider'],'enabled'=>(bool)$conn['enabled'],'last_sync_status'=>$conn['last_sync_status'],'last_sync_at'=>(int)$conn['last_sync_at'],'last_sync_message'=>$conn['last_sync_message'],'active_run_id'=>(int)$conn['active_run_id'],'published_run_id'=>(int)$conn['published_run_id'],'interval_minutes'=>(int)$conn['sync_interval_minutes']]:['connected'=>false,'last_sync_status'=>'idle','last_sync_message'=>'请完成ERP授权并同步库存']];
        if ($private) $row+=array_intersect_key($p,array_flip(['block_reason','available_balance','frozen_balance','total_income','withdrawn_amount','remark']));
        if ($this->context->uid>0) unset($row['available_balance'],$row['frozen_balance'],$row['total_income'],$row['withdrawn_amount']);
        return $row;
    }

    public function inventory(int $merchantId,array $config=[]): array
    {
        $config=$config?: (new ConfigService($this->context))->get();
        $key=$merchantId.':'.$config['inventory_fresh_hours'];if (array_key_exists($key,$this->inventoryCache)) return $this->inventoryCache[$key];
        $q=Db::name('hsx_lead_inventory')->alias('i')->join('hsx_lead_connection c','c.id=i.connection_id AND c.site_id=i.site_id AND c.merchant_id=i.merchant_id AND c.published_run_id=i.sync_run_id')
            ->join('hsx_lead_merchant m','m.id=i.merchant_id AND m.site_id=i.site_id')->where('i.site_id',$this->context->site)->where('i.merchant_id',$merchantId)->where('c.enabled',1);
        InventoryAvailability::apply($q,'sellable',true);
        return $this->inventoryCache[$key]=$q->where('i.fetched_at','>=',time()-(int)$config['inventory_fresh_hours']*3600)->field('i.id,i.brand,i.model')->select()->toArray();
    }

    public function lists(array $params=[]): array
    {
        Store::permission($this->context,'partners');$q=Data::query('merchant',$this->context->site);$keyword=Data::text($params,'keyword',100);
        if ($keyword!=='') $q->whereLike('name|contact_name|mobile','%'.addcslashes($keyword,'\\%_').'%');MerchantRegion::apply($q,$params);
        $rows=array_map(fn(array $m):array=>$this->present($m,true),$q->order('id desc')->select()->toArray());
        if (isset($params['grade_id'])&&$params['grade_id']!=='') $rows=array_values(array_filter($rows,static fn(array $m):bool=>$m['grade_id']===$params['grade_id']));
        if (isset($params['is_reliable'])&&$params['is_reliable']!=='') $rows=array_values(array_filter($rows,static fn(array $m):bool=>$m['is_reliable']===(bool)(int)$params['is_reliable']));
        if (isset($params['status'])&&$params['status']!=='') {
            if (!in_array($params['status'],['active','paused','blocked','1','0',1,0],true)) throw new CommonException('请选择有效商户状态');
            $status=$params['status']==='active'?1:($params['status']==='paused'?0:$params['status']);
            $rows=array_values(array_filter($rows,static fn(array $m):bool=>$status==='blocked'?$m['blocked_at']>0:((int)$m['status']===(int)$status&&$m['blocked_at']===0)));
        }
        return Store::pageArray($rows,$params)+['grades'=>(new ConfigService($this->context))->get()['grades']];
    }

    public function update(int $id,array $input): array
    {
        Store::permission($this->context,'partners');$config=(new ConfigService($this->context))->get();$mode=Data::text($input,'grade_mode',12,true);$grade=Data::text($input,'grade_id',40);
        if (!in_array($mode,['auto','manual'],true)||($mode==='manual'&&!in_array($grade,array_column(array_filter($config['grades'],static fn(array $g):bool=>(int)$g['status']===1),'id'),true))) throw new CommonException('请选择有效的商户等级');
        $status=Data::text($input,'certification_status',20,true);if (!in_array($status,['none','pending','active','revoked'],true)) throw new CommonException('认证状态无效');
        foreach(['cert_expires_at','publisher_until'] as $field) {$v=$input[$field]??0;if ((!is_int($v)&&!is_string($v))||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',(string)$v)||(float)$v>4294967295) throw new CommonException('有效期请填写合法的秒级时间');}
        $expires=(int)($input['cert_expires_at']??0);$until=(int)($input['publisher_until']??0);if ($status==='active'&&$expires<=time()) throw new CommonException('启用认证时请填写未来的到期时间');
        Db::transaction(function()use($id,$mode,$grade,$status,$expires,$until,$input):void {
            $p=Store::partner($this->context->site,$id,true);if ((int)$p['blocked_at']>0) throw new CommonException('已永久停止合作，不能通过资料修改重新启用');
            Store::query('partner',$this->context->site)->where('id',$p['id'])->update(['grade_mode'=>$mode,'grade_id'=>$grade,'certification_status'=>$status,'cert_expires_at'=>$expires,'publisher_until'=>$until,'remark'=>Data::text($input,'remark',2000),'update_time'=>time()]);
            $this->context->audit('partner',$id,'rights_changed',['grade_mode'=>$mode,'grade_id'=>$grade,'certification_status'=>$status,'publisher_until'=>$until]);
        });return $this->present(Data::row('merchant',$this->context->site,$id),true);
    }

    public function block(int $id,array $input): array
    {
        Store::permission($this->context,'partners');if (!in_array($input['confirm']??null,[true,1,'1'],true)) throw new CommonException('请再次明确确认永久停止合作');$reason=Data::text($input,'reason',1000,true);
        Db::transaction(function()use($id,$reason):void {$p=Store::partner($this->context->site,$id,true);if ((int)$p['blocked_at']>0) return;
            Store::query('partner',$this->context->site)->where('id',$p['id'])->update(['blocked_at'=>time(),'block_reason'=>$reason,'certification_status'=>'revoked','update_time'=>time()]);
            $this->context->audit('partner',$id,'permanently_blocked',['reason'=>$reason]);
        });return $this->present(Data::row('merchant',$this->context->site,$id),true);
    }

    public static function phone($value): string
    {
        if (!is_string($value)&&!is_numeric($value)) throw new CommonException('请填写有效联系电话');$phone=preg_replace('/[\s()-]+/','',(string)$value)??'';
        if (!preg_match('/^\+?[0-9]{6,20}$/D',$phone)) throw new CommonException('请填写有效联系电话');return $phone;
    }
}
