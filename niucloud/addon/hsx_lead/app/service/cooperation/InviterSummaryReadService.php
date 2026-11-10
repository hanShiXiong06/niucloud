<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,OwnershipScope};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Per-person current direct relationships and lifetime original-provider awards. No writes. */
final class InviterSummaryReadService
{
    private const CHUNK=500;
    public function __construct(private Context $context) {}

    public function lists(array $params=[]):array
    {
        $this->context->permission('hsx_lead_promotion_relations');
        if(!$this->context->ownership()->scope()['all_data'])throw new CommonException('推广人汇总需要全站业务查看权限');
        $role=Data::text($params,'role',20);if(!in_array($role,['','normal','provider'],true))throw new CommonException('请选择有效的推广身份');
        $site=$this->context->site;$query=Db::name('member')->where('site_id',$site)->where('is_del',0)->where('delete_time',0);
        $providerIds=$this->providerIds();
        if($role==='provider')$query->whereIn('member_id',$providerIds?:[0]);
        elseif($role==='normal'&&$providerIds)$query->whereNotIn('member_id',$providerIds);
        if(!in_array($params['provider_member_id']??'',['',0,'0'],true))$query->where('member_id',OwnershipScope::ownerId($params['provider_member_id']));
        $keyword=Data::text($params,'keyword',100);
        if($keyword!==''){
            $like='%'.addcslashes($keyword,'\\%_').'%';
            $owners=Data::query('merchant',$site)->whereLike('name|contact_name',$like)->column('member_id');
            $memberId=\addon\hsx_lead\app\support\AdminKeywordSearch::memberId($keyword);
            $query->where(function($part)use($like,$owners,$memberId):void{$part->whereLike('nickname|username|mobile|member_no',$like);if($memberId!==null)$part->whereOr('member_id',$memberId);if($owners)$part->whereOr('member_id','in',$owners);});
        }
        $page=max(1,(int)($params['page']??1));$limit=max(1,min(100,(int)($params['limit']??20)));$total=(int)(clone$query)->count();
        $members=$query->field('member_id,member_no,nickname,username,mobile')->order('member_id desc')->page($page,$limit)->select()->toArray();
        $ids=array_map('intval',array_column($members,'member_id'));
        $relationships=$this->relationshipTotals($ids);$rewards=$this->rewardTotals($ids);$rows=[];
        foreach($members as$member){$id=(int)$member['member_id'];$phone=(string)$member['mobile'];
            $rows[]=['member_id'=>$id,'member_no'=>(string)$member['member_no'],'name'=>trim((string)$member['nickname'])?:trim((string)$member['username'])?:'本站会员',
                'mobile_masked'=>strlen($phone)>=7?substr($phone,0,3).'****'.substr($phone,-4):'',
                'role'=>in_array($id,$providerIds,true)?'provider':'normal',
                'direct_invite_count'=>$relationships[$id]['total']??0,'paid_invitee_count'=>$relationships[$id]['paid']??0,'review_invitee_count'=>$relationships[$id]['review']??0,
                'promotion'=>$rewards[$id]??PromotionRelationsReadService::emptyRewards()];
        }
        return['data'=>$rows,'total'=>$total,'current_page'=>$page,'per_page'=>$limit,
            'scope'=>['relations'=>'current_direct','rewards'=>'lifetime_original_provider']];
    }

    /** Provider status is existing site configuration, not the account's personal member level. */
    private function providerIds():array
    {
        $ids=[];$prefix=PromotionService::PROVIDER_PREFIX;
        foreach(Db::name('sys_config')->where('site_id',$this->context->site)->whereLike('config_key',addcslashes($prefix,'\\%_').'%')->field('config_key,value')->select()->toArray()as$row){
            $id=substr($row['config_key'],strlen($prefix));if(!ctype_digit($id)||(int)$id<=0)continue;
            $value=Data::decode($row['value']);$status=(string)($value['status']??(!empty($value['enabled'])?'approved':'disabled'));
            if($status==='approved')$ids[(int)$id]=(int)$id;
        }return array_values($ids);
    }

    private function relationshipTotals(array $providerIds):array
    {
        if(!$providerIds)return[];$totals=[];$cursor=0;$facts=new PromotionRelationsReadService($this->context);
        $query=Db::name('member')->where('site_id',$this->context->site)->whereIn('pid',$providerIds)->where('is_del',0)->where('delete_time',0)->whereRaw('member_id<>pid');
        do{
            $children=(clone$query)->where('member_id','>',$cursor)->field('member_id,pid')->order('member_id')->limit(self::CHUNK)->select()->toArray();
            if(!$children)break;$cursor=(int)end($children)['member_id'];$payments=$facts->memberPaymentFacts(array_map('intval',array_column($children,'member_id')));
            foreach($children as$child){$parent=(int)$child['pid'];$totals[$parent]=$totals[$parent]??['total'=>0,'paid'=>0,'review'=>0];$totals[$parent]['total']++;
                $payment=$payments[(int)$child['member_id']];
                if($payment['status']==='review')$totals[$parent]['review']++;
                elseif($payment['status']==='paid'&&$payment['service_net']!==null&&Rules::cents($payment['service_net'])>0)$totals[$parent]['paid']++;
            }
        }while(count($children)===self::CHUNK);return$totals;
    }

    private function rewardTotals(array $providerIds):array
    {
        if(!$providerIds)return[];$out=[];$cursor=0;$prefix=(string)config('database.connections.mysql.prefix');
        $query=Db::name('hsx_lead_promotion_reward')->alias('r')->leftJoin([$prefix.'hsx_lead_coop_order'=>'o'],'o.site_id=r.site_id AND o.id=r.coop_order_id')
            ->where('r.site_id',$this->context->site)->whereIn('r.provider_member_id',$providerIds)
            ->field('r.id,r.provider_member_id,r.status,r.amount,r.review_required,o.rule_json');
        do{
            $rows=(clone$query)->where('r.id','>',$cursor)->order('r.id')->limit(self::CHUNK)->select()->toArray();
            if(!$rows)break;$cursor=(int)end($rows)['id'];
            foreach($rows as$row){$id=(int)$row['provider_member_id'];$out[$id]=PromotionRelationsReadService::accumulateReward($out[$id]??PromotionRelationsReadService::emptyRewards(),$row);}
        }while(count($rows)===self::CHUNK);return$out;
    }
}
