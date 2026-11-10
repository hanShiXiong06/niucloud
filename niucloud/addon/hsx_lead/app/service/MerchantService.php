<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\support\ListTimeFilter;
use addon\hsx_lead\app\support\MerchantRegion;
use addon\hsx_lead\app\service\cooperation\PartnerService;
use addon\hsx_lead\app\service\cooperation\CapabilityService;
use addon\hsx_lead\app\service\cooperation\{ConfigService,BusinessService};
use core\exception\CommonException;
use think\facade\Db;

final class MerchantService
{
    public function __construct(private Context $context) {}

    public function members(array $params = []): array
    {
        $this->context->manager();
        $q = Db::name('member')->where('site_id',$this->context->site)->where('status',1)->where('is_del',0)->where('delete_time',0)->field('member_id,member_no,nickname,mobile');
        $key = Data::text($params,'keyword',100);
        \addon\hsx_lead\app\support\AdminKeywordSearch::members($q,$this->context->site,$key);
        return $q->limit(200)->select()->map(function ($r) { $r['id']=$r['member_id']; return $r; })->toArray();
    }

    public function lists(array $params = []): array
    {
        $this->context->manager();
        // The editor loads the large directory only when opened, under this
        // same existing merchant API permission. Ordinary pages stay summaries.
        if (in_array($params['definitions'] ?? null, [true, 1, '1'], true)) {
            return ['business_definitions'=>$this->businessDefinitions()];
        }
        $q=Data::query('merchant',$this->context->site);
        $key=Data::text($params,'keyword',100);
        \addon\hsx_lead\app\support\AdminKeywordSearch::merchants($q,$this->context->site,$key);
        if (isset($params['status']) && $params['status'] !== '') $q->where('status',(int)$params['status']);
        MerchantRegion::apply($q, $params);
        if (in_array($params['picker'] ?? null, [true, 1, '1'], true)) {
            // Search/paginate on the server; a first page is not the full merchant list.
            $result = Data::page($q->field('id,name,contact_name,mobile,province_id,city_id,district_id'), $params);
            $result['data'] = MerchantRegion::present($result['data']);
            return $result;
        }
        ListTimeFilter::apply($q,$params,['create_time'=>'create_time','update_time'=>'update_time'],'create_time');
        if (!empty($params['provider'])) {
            $provider=Data::text($params,'provider',60);
            $ids=Data::query('connection',$this->context->site)->where('provider',$provider)->column('merchant_id');
            $q->whereIn('id',$ids?:[0]);
        }
        $q->field('id,site_id,name,contact_name,mobile,province_id,city_id,district_id,address,member_id,status,create_time,update_time');
        $ready=Data::text($params,'ready_state',32);
        if ($ready!=='') {
            if (!in_array($ready,['member_required','membership_required','capability_required','connection_required','sync_required','ready','attention','paused'],true)) throw new CommonException('请选择有效的商户就绪状态');
            // Readiness spans merchant/member/auth/publication freshness. Filter the full
            // scoped set before pagination, never merely the currently requested page.
            $presenter=new MerchantListService($this->context);
            $page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??$params['page_size']??20)));
            $rows=[];$total=0;$start=($page-1)*$size;
            // Keep cross-table readiness filtering before pagination, while
            // bounding each batch's inventory/config/association read set.
            $beforeId=null;
            do {
                $batchQuery=clone $q;
                if($beforeId!==null) $batchQuery->where('id','<',$beforeId);
                $batch=$batchQuery->order('id desc')->limit(100)->select()->toArray();
                foreach($presenter->present($batch) as $row) if($row['ready_state']===$ready) {
                    if($total>=$start&&count($rows)<$size) $rows[]=$row;
                    $total++;
                }
                if($batch) $beforeId=(int)$batch[count($batch)-1]['id'];
            } while(count($batch)===100);
            return ['data'=>$rows,'total'=>$total,'per_page'=>$size,'current_page'=>$page];
        }
        $result=Data::page($q,$params);
        $result['data']=(new MerchantListService($this->context))->present($result['data']);
        return $result;
    }

    public function detail(int $id): array
    {
        $this->context->manager();
        return $this->present(Data::row('merchant',$this->context->site,$id),true);
    }

    private function present(array $row,bool $withDefinitions=false): array
    {
        $row=Data::regionNames($row);
        $conn=(new ConnectionService($this->context))->get((int)$row['id']);
        $row=array_replace($row,['provider'=>$conn['provider'],'provider_name'=>$conn['provider_name'],
            'connection_id'=>$conn['connection_id'],'sync_interval_minutes'=>$conn['interval_minutes'],'sync_enabled'=>$conn['enabled'],
            'auth_renewal'=>$conn['auth_renewal'],'next_sync_at'=>$conn['next_sync_at'],
            'channel_enabled'=>$conn['channel_enabled'],'channel_message'=>$conn['channel_message'],
            'last_sync_status'=>$conn['last_sync_status'],'last_sync_at'=>$conn['last_sync_at'],'last_sync_message'=>$conn['last_sync_message'],
            'last_run_id'=>$conn['active_run_id'],'published_run_id'=>$conn['published_run_id']]);
        $row['status']=(int)$row['status'];
        $member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['member_id'])->field('nickname,status,is_del,delete_time')->find();
        $row['member_name']=(string)($member['nickname']??'');
        $capability=(new PartnerService($this->context))->present($row);
        foreach(['manual_models','manual_model_count','business_capabilities','business_capability_count','capability_source','inventory_count','all_models',
            'membership_rank','display_rank','blocked_at','membership','membership_active','membership_tier','membership_expires_at',
            'can_collaborate','can_publish','can_accept','can_view_inventory','capabilities'] as $key) $row[$key]=$capability[$key];
        if ($withDefinitions) $row['business_definitions']=$this->businessDefinitions();
        [$row['ready_state'],$row['ready_reason']]=self::readiness($row,$conn,$member?:null);
        $row['service_areas']=Data::decode($row['service_areas_json']??'');
        unset($row['service_areas_json']);
        return $row;
    }

    private function businessDefinitions(): array
    {
        return array_map(function(array $business):array {return array_intersect_key($business,array_flip(['key','name','enabled','dimension']))+['options'=>(new BusinessService($this->context))->options($business)];},(new ConfigService($this->context))->get()['businesses']);
    }

    public function save(int $id, #[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->manager();
        $site=$this->context->site;
        return Db::transaction(function () use ($id,$input,$site) {
            Db::name('site')->where('site_id',$site)->lock(true)->field('site_id')->find();
            $old=$id ? Data::row('merchant',$site,$id,true) : [];
            $region=Data::region($input);
            $memberId=(int)($input['member_id']??0);
            if ($memberId>0) {
                $member=Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->lock(true)->find();
                if (!$member || (int)$member['status']!==1 || (int)$member['is_del']!==0 || (int)$member['delete_time']>0) throw new CommonException('请选择本站启用中的会员');
                if (Data::query('merchant',$site)->where('member_id',$memberId)->where('id','<>',$id)->lock(true)->find()) throw new CommonException('该会员已绑定其他商户');
                if (Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('member_id',$memberId)->whereIn('status',['active','pending'])->lock(true)->find()) throw new CommonException('该会员已有门店成员关系或待确认申请，请先处理原归属，不能直接绑定为另一主体负责人');
            }
            if ($old&&(int)$old['member_id']!==$memberId) {
                $hasTeam=Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$id)->whereIn('status',['active','pending'])->lock(true)->find();
                $tier=Db::name('hsx_lead_partner')->where('site_id',$site)->where('merchant_id',$id)->lock(true)->value('membership_level');
                if ($hasTeam||$tier) throw new CommonException('该主体已有成员或付费会员权益，不能通过编辑资料直接更换负责人，请先核对成员、账款和原权益');
            }
            $mobile=Data::text($input,'mobile',32,true);
            if (!preg_match('/^[0-9+() -]{5,32}$/D',$mobile)) throw new CommonException('请填写有效联系电话');
            $areas=$input['service_areas']??[];
            if (!is_array($areas) || count($areas)>100) throw new CommonException('服务地区格式错误或超过100项');
            $checkedAreas=[];
            foreach ($areas as $area) {
                if (!is_array($area)) throw new CommonException('服务地区需包含省、市、区县');
                $checkedAreas[]=Data::serviceArea($area);
            }
            $values=$region+['name'=>Data::text($input,'name',120,true),'contact_name'=>Data::text($input,'contact_name',60,true),
                'mobile'=>$mobile,'address'=>Data::text($input,'address',500),'member_id'=>$memberId,
                'status'=>(int)($input['status']??1)===1?1:0,'remark'=>Data::text($input,'remark',2000),
                'service_areas_json'=>Data::json($checkedAreas),'update_time'=>time()];
            if (!$id) $id=(int)Data::query('merchant',$site)->insertGetId($values+['site_id'=>$site,'create_time'=>time()]);
            else Data::query('merchant',$site)->where('id',$id)->update($values);
            (new CapabilityService($this->context))->save($id,$input,true);
            // Basic editing owns no ERP credentials, revision, lease or run state.
            // Pausing a merchant may stop future scheduled runs, not rewrite its connection.
            if ($values['status']===0) Data::query('connection',$site)->where('merchant_id',$id)->update(['enabled'=>0,'update_time'=>time()]);
            $this->context->audit('merchant',$id,$old?'updated':'created',['member_id'=>$memberId,'status'=>$values['status']]);
            return $this->detail($id);
        });
    }

    public static function readiness(array $merchant,array $conn,?array $member): array
    {
        if (!$member || (int)$member['status']!==1 || (int)$member['is_del']!==0 || (int)$member['delete_time']>0) return ['member_required','请先绑定本站启用中的会员，商户才可独立接单'];
        if ((int)$merchant['status']!==1 || (int)($merchant['blocked_at']??0)>0) return ['paused','商户已停止合作，不参与门店推荐；历史记录仍保留'];
        if (empty($merchant['can_accept'])) return ['membership_required',empty($merchant['membership_active'])?'门店会员未开通或已到期，暂不能接收新的客户线索；已有工单仍可处理':'当前门店会员未包含接收客户线索权益，请在会员页核对权益'];
        if (!empty($merchant['all_models'])) return ['ready','按平台授予的等级承接全部型号；不代表有现货，交付前需核对'];
        if ((int)($merchant['business_capability_count']??0)>0) return ['ready','已设置业务能力，可参与对应需求推荐；无需接入ERP，交付范围请向门店核对'];
        if ((int)($merchant['inventory_count']??0)>0) return ['ready','已有有效在售库存可参与需求推荐；接单前仍需核对现货'];
        if (!$conn['connected']) return ['capability_required','请商户在手机端选择可交付型号；也可选择连接ERP，发单不依赖ERP'];
        if (!$conn['channel_enabled']) return ['attention','ERP渠道已关闭；可手选可交付型号继续参与需求推荐'];
        if ($conn['authorization_attention']) return ['attention','ERP授权待核对；可重新授权，也可手选可交付型号'];
        if (in_array($conn['last_sync_status'],['pending','running'],true)) return ['sync_required','库存同步进行中；也可先手选可交付型号'];
        return ['capability_required','当前没有有效的可售库存，请手选可交付型号或重新同步；发单不受影响'];
    }
}
