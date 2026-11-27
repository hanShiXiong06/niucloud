<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,OwnershipScope};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Existing member.pid graph. Lazy pages and grouped aggregates, never a full recursive load. */
final class PromotionTreeReadService
{
    public function __construct(private Context $context){}
    private function members(){return Db::name('member')->where('site_id',$this->context->site)->where('is_del',0)->where('delete_time',0);}
    public function lists(array $params=[]):array
    {
        $this->context->permission('hsx_lead_promotion_relations');if(!$this->context->ownership()->scope()['all_data'])throw new CommonException('推广关系需要全站业务查看权限');
        $parent=in_array($params['parent_member_id']??'',['',0,'0'],true)?0:OwnershipScope::ownerId($params['parent_member_id']);
        if($parent&&!(clone $this->members())->where('member_id',$parent)->find())throw new CommonException('上级会员不存在或不属于本站');
        $keyword=Data::text($params,'keyword',100);$page=max(1,(int)($params['page']??1));$size=max(1,min(100,(int)($params['limit']??30)));$days=(int)($params['activity_days']??30);if(!in_array($days,[7,30],true))throw new CommonException('请选择近7天或30天活跃情况');
        $q=$this->members();if($keyword==='')$q->where('pid',$parent);else{
            $like='%'.addcslashes($keyword,'\\%_').'%';$shops=Data::query('merchant',$this->context->site)->whereLike('name|contact_name|mobile',$like)->field('id,member_id')->select()->toArray();$owners=array_map('intval',array_column($shops,'member_id'));$shopIds=array_map('intval',array_column($shops,'id'));
            if($shopIds)$owners=array_merge($owners,Db::name('hsx_lead_merchant_member')->where('site_id',$this->context->site)->whereIn('merchant_id',$shopIds)->where('status','active')->column('member_id'));
            $q->where(function($part)use($like,$keyword,$owners):void{$part->whereLike('nickname|username|mobile|member_no',$like);if(ctype_digit($keyword))$part->whereOr('member_id',(int)$keyword);if($owners)$part->whereOr('member_id','in',$owners);});
        }
        $total=(int)(clone$q)->count();$members=$q->field('member_id,member_no,pid,nickname,username,mobile,headimg,status,last_visit_time,login_time,login_count')->order('member_id desc')->page($page,$size)->select()->toArray();$ids=array_map('intval',array_column($members,'member_id'));
        $facts=(new PromotionRelationsReadService($this->context))->memberFacts($ids);$counts=$this->counts($ids);$rewards=$this->rewardTotals($ids);$activity=$this->activity($ids,$days);$paths=$keyword!==''?$this->paths($members):[];$rows=[];
        foreach($members as$member){$id=(int)$member['member_id'];$direct=(int)($counts[$id]['direct']??0);$indirect=(int)($counts[$id]['indirect']??0);$phone=(string)$member['mobile'];$path=$paths[$id]??['path'=>[],'truncated'=>false];
            $rows[]=['member_id'=>$id,'parent_member_id'=>(int)$member['pid'],'name'=>self::name($member),'member_no'=>(string)$member['member_no'],'mobile_masked'=>strlen($phone)>=7?substr($phone,0,3).'****'.substr($phone,-4):'','avatar'=>(string)$member['headimg'],'status'=>(int)$member['status'],'has_children'=>$direct>0,'children_count'=>$direct,'direct_invite_count'=>$direct,'indirect_invite_count'=>$indirect,'subtree_count'=>$direct+$indirect,'subtree_depth'=>2,'path'=>$path['path'],'path_truncated'=>$path['truncated'],
                'merchant'=>$facts[$id]['merchant'],'membership'=>$facts[$id]['membership'],'payment'=>$facts[$id]['payment'],'rewards'=>$rewards[$id]??PromotionRelationsReadService::emptyRewards(),
                'activity'=>array_replace(['activity_days'=>$days,'last_visit_time'=>(int)$member['last_visit_time'],'login_time'=>(int)$member['login_time'],'login_count'=>(int)$member['login_count'],'active_30d'=>max((int)$member['last_visit_time'],(int)$member['login_time'])>=time()-30*86400,'active'=>max((int)$member['last_visit_time'],(int)$member['login_time'])>=time()-$days*86400,'published_count'=>0,'accepted_count'=>0,'deal_count'=>0,'last_business_at'=>0],$activity[$id]??[])];
        }
        return['data'=>$rows,'total'=>$total,'current_page'=>$page,'per_page'=>$size,'parent_member_id'=>$parent,'searching'=>$keyword!=='','subtree_depth'=>2,'tree_depth_limit'=>20,'activity_days'=>$days];
    }
    private static function name(array$row):string{return trim((string)($row['nickname']??''))?:trim((string)($row['username']??''))?:'本站会员';}
    private function counts(array$ids):array
    {
        if(!$ids)return[];$out=[];$site=$this->context->site;$prefix=(string)config('database.connections.mysql.prefix');
        foreach($this->members()->whereIn('pid',$ids)->whereRaw('member_id<>pid')->field('pid,COUNT(*) AS n')->group('pid')->select()->toArray()as$row)$out[(int)$row['pid']]['direct']=(int)$row['n'];
        $q=Db::name('member')->alias('c')->join([$prefix.'member'=>'p'],'p.site_id=c.site_id AND p.member_id=c.pid')->where('c.site_id',$site)->whereIn('p.pid',$ids)->where('c.is_del',0)->where('c.delete_time',0)->where('p.is_del',0)->where('p.delete_time',0)->whereRaw('c.member_id<>p.pid AND p.member_id<>p.pid AND c.member_id<>c.pid');
        foreach($q->field('p.pid,COUNT(*) AS n')->group('p.pid')->select()->toArray()as$row)$out[(int)$row['pid']]['indirect']=(int)$row['n'];return$out;
    }
    public function rewardTotals(array$ids):array
    {
        $this->context->permission('hsx_lead_promotion_relations');if(!$this->context->ownership()->scope()['all_data']||count($ids)>500)throw new CommonException('请在授权范围内分批读取推广汇总');
        if(!$ids)return[];$prefix=(string)config('database.connections.mysql.prefix');$out=[];
        $cancelled="r.status='held' AND r.review_required=0 AND JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(o.rule_json),o.rule_json,'{}'),'$.order_refund.status'))='refunded'";
        $q=Db::name('hsx_lead_promotion_reward')->alias('r')->leftJoin([$prefix.'hsx_lead_coop_order'=>'o'],'o.site_id=r.site_id AND o.id=r.coop_order_id')->where('r.site_id',$this->context->site)->whereIn('r.provider_member_id',$ids);
        $fields="r.provider_member_id,SUM(CASE WHEN $cancelled THEN r.amount ELSE 0 END) AS cancelled,MAX(r.review_required) AS review_required";
        foreach(['pending','settled','held']as$status)$fields.=",SUM(CASE WHEN r.status='$status' AND NOT(COALESCE(($cancelled),0)) THEN r.amount ELSE 0 END) AS $status";
        foreach($q->fieldRaw($fields)->group('r.provider_member_id')->select()->toArray()as$row){$id=(int)$row['provider_member_id'];unset($row['provider_member_id']);$row['total']=Rules::amount(Rules::cents($row['pending'])+Rules::cents($row['settled'])+Rules::cents($row['held']));$row['review_required']=(bool)$row['review_required'];$out[$id]=$row;}return$out;
    }
    private function activity(array$ids,int$days):array
    {
        if(!$ids)return[];$out=[];$q=Db::name('hsx_lead_audit')->where('site_id',$this->context->site)->whereIn('operator_member_id',$ids)->where('entity_type','collaboration')->where('create_time','>=',time()-$days*86400)->whereIn('action',['published','accept','deal']);
        $fields="operator_member_id,MAX(create_time) AS last_business_at,COUNT(DISTINCT CASE WHEN action='published' THEN entity_id END) AS published_count,COUNT(DISTINCT CASE WHEN action='accept' THEN entity_id END) AS accepted_count,COUNT(DISTINCT CASE WHEN action='deal' THEN entity_id END) AS deal_count";
        foreach($q->fieldRaw($fields)->group('operator_member_id')->select()->toArray()as$row){$id=(int)$row['operator_member_id'];unset($row['operator_member_id']);$out[$id]=array_map('intval',$row);}return$out;
    }
    /** At most 20 bounded ancestor batches for the current <=100-node page. */
    private function paths(array$members):array
    {
        $known=[];$next=[];foreach($members as$row){$known[(int)$row['member_id']]=$row;if((int)$row['pid']>0)$next[(int)$row['pid']]=(int)$row['pid'];}
        for($depth=0;$depth<20&&$next;$depth++){$wanted=array_values(array_diff($next,array_keys($known)));if(!$wanted)break;$next=[];foreach($this->members()->whereIn('member_id',$wanted)->field('member_id,member_no,pid,nickname,username')->select()->toArray()as$row){$known[(int)$row['member_id']]=$row;if((int)$row['pid']>0)$next[(int)$row['pid']]=(int)$row['pid'];}}
        $out=[];foreach($members as$row){$path=[];$seen=[];$id=(int)$row['member_id'];for($depth=0;$depth<20&&$id>0&&!isset($seen[$id])&&isset($known[$id]);$depth++){$seen[$id]=true;$member=$known[$id];array_unshift($path,['member_id'=>$id,'member_no'=>(string)$member['member_no'],'name'=>self::name($member)]);$id=(int)$member['pid'];}$out[(int)$row['member_id']]=['path'=>$path,'truncated'=>$id>0];}return$out;
    }
}
