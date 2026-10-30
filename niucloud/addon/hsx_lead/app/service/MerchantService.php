<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

final class MerchantService
{
    public function __construct(private readonly Context $context) {}

    public function members(array $params = []): array
    {
        $this->context->manager();
        $q = Db::name('member')->where('site_id',$this->context->site)->where('status',1)->where('is_del',0)->where('delete_time',0)->field('member_id,nickname,mobile');
        $key = Data::text($params,'keyword',100);
        if ($key !== '') $q->whereLike('nickname|mobile','%'.$key.'%');
        return $q->limit(200)->select()->map(function ($r) { $r['id']=$r['member_id']; return $r; })->toArray();
    }

    public function lists(array $params = []): array
    {
        $this->context->manager();
        $q=Data::query('merchant',$this->context->site);
        $key=Data::text($params,'keyword',100);
        if ($key !== '') $q->whereLike('name|mobile|contact_name','%'.$key.'%');
        if (isset($params['status']) && $params['status'] !== '') $q->where('status',(int)$params['status']);
        if (!empty($params['provider'])) {
            $provider=Data::text($params,'provider',60);
            $ids=Data::query('connection',$this->context->site)->where('provider',$provider)->column('merchant_id');
            $q->whereIn('id',$ids?:[0]);
        }
        $ready=Data::text($params,'ready_state',32);
        if ($ready!=='') {
            if (!in_array($ready,['member_required','connection_required','sync_required','ready','attention','paused'],true)) throw new CommonException('请选择有效的商户就绪状态');
            // Readiness spans merchant/member/auth/publication freshness. Filter the full
            // scoped set before pagination, never merely the currently requested page.
            $rows=array_values(array_filter(array_map(fn($r)=>$this->present($r),$q->order('id desc')->select()->toArray()),fn($r)=>$r['ready_state']===$ready));
            $page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??$params['page_size']??20)));
            return ['data'=>array_slice($rows,($page-1)*$size,$size),'total'=>count($rows),'per_page'=>$size,'current_page'=>$page];
        }
        $result=Data::page($q,$params);
        $result['data']=array_map(fn($r)=>$this->present($r),$result['data']);
        return $result;
    }

    public function detail(int $id): array
    {
        $this->context->manager();
        return $this->present(Data::row('merchant',$this->context->site,$id));
    }

    private function present(array $row): array
    {
        $row=Data::regionNames($row);
        $conn=(new ConnectionService($this->context))->get((int)$row['id']);
        $row=array_replace($row,['provider'=>$conn['provider'],'provider_name'=>$conn['provider_name'],
            'connection_id'=>$conn['connection_id'],'sync_interval_minutes'=>$conn['interval_minutes'],'sync_enabled'=>$conn['enabled'],
            'last_sync_status'=>$conn['last_sync_status'],'last_sync_at'=>$conn['last_sync_at'],'last_sync_message'=>$conn['last_sync_message'],
            'last_run_id'=>$conn['active_run_id'],'published_run_id'=>$conn['published_run_id']]);
        $row['status']=(int)$row['status'];
        $member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$row['member_id'])->field('nickname,status,is_del,delete_time')->find();
        $row['member_name']=(string)($member['nickname']??'');
        [$row['ready_state'],$row['ready_reason']]=$this->readiness($row,$conn,$member?:null);
        $row['service_areas']=Data::decode($row['service_areas_json']??'');
        unset($row['service_areas_json']);
        return $row;
    }

    public function save(int $id, #[\SensitiveParameter] array $input): array
    {
        $this->context->manager();
        $site=$this->context->site;
        return Db::transaction(function () use ($id,$input,$site) {
            $old=$id ? Data::row('merchant',$site,$id,true) : [];
            $region=Data::region($input);
            $memberId=(int)($input['member_id']??0);
            if ($memberId>0) {
                $member=Db::name('member')->where('site_id',$site)->where('member_id',$memberId)->lock(true)->find();
                if (!$member || (int)$member['status']!==1 || (int)$member['is_del']!==0 || (int)$member['delete_time']>0) throw new CommonException('请选择本站启用中的会员');
                if (Data::query('merchant',$site)->where('member_id',$memberId)->where('id','<>',$id)->count()) throw new CommonException('该会员已绑定其他商户');
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
            // Basic editing owns no ERP credentials, revision, lease or run state.
            // Pausing a merchant may stop future scheduled runs, not rewrite its connection.
            if ($values['status']===0) Data::query('connection',$site)->where('merchant_id',$id)->update(['enabled'=>0,'update_time'=>time()]);
            $this->context->audit('merchant',$id,$old?'updated':'created',['member_id'=>$memberId,'status'=>$values['status']]);
            return $this->detail($id);
        });
    }

    private function readiness(array $merchant,array $conn,?array $member): array
    {
        if (!$member || (int)$member['status']!==1 || (int)$member['is_del']!==0 || (int)$member['delete_time']>0) return ['member_required','请先绑定本站启用中的会员，商户才可独立接单'];
        if ((int)$merchant['status']!==1) return ['paused','商户已暂停，不参与地区匹配；自动同步已关闭'];
        if (!$conn['connected']) return ['connection_required','基本资料已保存，请独立探测并激活ERP连接'];
        if ($conn['authorization_attention']) return ['attention','ERP授权需重新验证，请检查授权状态后再同步'];
        if ($conn['last_sync_status']==='failed') return ['attention','最近同步失败，保留旧快照供人工参考，请核对后重试'];
        if (!$conn['published_run_id']) return ['sync_required','ERP已接入，请完成首次完整同步，旧范围库存不参与匹配'];
        $threshold=max(2*$conn['interval_minutes'],120)*60;
        if (!$conn['last_sync_at'] || $conn['last_sync_at']+$threshold<time()) return ['attention','库存快照已过期，请重新完整同步；不能据此承诺可售'];
        return ['ready','会员与接入正常，有新鲜完整库存资料；实物可售、数量与价格仍需门店确认'];
    }
}
