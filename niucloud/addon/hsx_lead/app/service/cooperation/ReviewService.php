<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Sampling is evidence, never an automatic fraud score or automatic forfeiture. */
final class ReviewService
{
    public function __construct(private Context $context) {}
    public function lists(array $params=[]): array
    {
        Store::permission($this->context,'review');$q=Store::query('review',$this->context->site)->whereIn('lead_id',function($sub):void {$sub->name('hsx_lead_collaboration')->field('id')->where('site_id',$this->context->site);$this->context->ownership()->leads($sub);});
        $status=Data::text($params,'status',20);if ($status!=='') $q->where('status',$status);
        if (!empty($params['lead_id'])) $q->where('lead_id',(int)$params['lead_id']);
        $keyword=Data::text($params,'keyword',100);if ($keyword!=='') {$needle='%'.addcslashes($keyword,'\\%_').'%';$leadIds=Store::adminQuery($this->context)->whereLike('customer_name|lead_no|mobile',$needle)->column('id');$q->where(function($sub)use($needle,$leadIds):void {$sub->whereLike('note|reason',$needle)->whereOr('lead_id','in',$leadIds?:[0]);});}
        foreach(['start_time'=>'>=','end_time'=>'<='] as $key=>$op) if (isset($params[$key])&&$params[$key]!==''&&(int)$params[$key]>0) $q->where('create_time',$op,(int)$params[$key]);
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $r):array=>$this->present($r),$page['data']);return $page;
    }
    public function create(array $input): array
    {
        Store::permission($this->context,'review');$id=(int)($input['lead_id']??0);$lead=Store::adminLead($this->context,$id);
        if (!(int)$lead['receiver_id']) throw new CommonException('尚未交付给门店，暂无可抽检接收方');
        $outcome=Data::text($input,'customer_outcome',16,true);if (!in_array($outcome,['unknown','deal','no_deal'],true)) throw new CommonException('请选择有效客户回访结果');
        $values=['site_id'=>$this->context->site,'lead_id'=>$id,'receiver_id'=>$lead['receiver_id'],'customer_outcome'=>$outcome,'merchant_outcome'=>$lead['status'],
            'note'=>Data::text($input,'note',4000,true),'evidence_json'=>Data::json(Store::attachments($input['evidence']??[],$this->context)),'operator_uid'=>$this->context->uid,'status'=>'pending','create_time'=>time(),'update_time'=>time()];
        $reviewId=Db::transaction(function()use($id,$values):int {$current=Store::adminLead($this->context,$id,true);if ((int)$current['receiver_id']!==(int)$values['receiver_id']) throw new CommonException('接收门店已变化，请刷新后重新抽检');$values['merchant_outcome']=$current['status'];$review=(int)Store::query('review',$this->context->site)->insertGetId($values);Store::audit($this->context,$id,'sampled',['review_id'=>$review,'customer_outcome'=>$values['customer_outcome']]);return $review;});
        return $this->present(Store::row('review',$this->context->site,$reviewId));
    }
    public function resolve(int $id,array $input): array
    {
        Store::permission($this->context,'review');$decision=Data::text($input,'decision',32,true);$reason=Data::text($input,'reason',2000,true);
        if (!in_array($decision,['clear','followup','confirmed_violation'],true)) throw new CommonException('请选择有效复核结论');
        if (!in_array($input['confirm']??null,[true,1,'1'],true)) throw new CommonException('请明确确认本次复核结论');
        if ($decision==='confirmed_violation') Store::permission($this->context,'partners');
        Db::transaction(function()use($id,$decision,$reason):void {
            $initial=Store::row('review',$this->context->site,$id);if ($decision==='confirmed_violation') Store::partner($this->context->site,(int)$initial['receiver_id'],true);$lead=Store::adminLead($this->context,(int)$initial['lead_id'],true);$review=Store::row('review',$this->context->site,$id,true);
            if ($review['status']==='resolved') {
                if ($review['decision']===$decision) return;throw new CommonException('该复核已结束，不能重复改写结论');
            }
            if ($decision==='confirmed_violation') {
                if ($review['customer_outcome']!=='deal') throw new CommonException('客户反馈尚未证实成交，不能直接认定隐瞒成交');
                if (!in_array($lead['status'],['no_deal','unpaid'],true)) throw new CommonException('当前成交/付佣状态已有变化，请重新核对后复核');
                if ((int)$lead['receiver_id']!==(int)$review['receiver_id']) throw new CommonException('接收门店已变化，请重新抽检当前交付');
                (new PartnerService($this->context))->block((int)$review['receiver_id'],['confirm'=>true,'reason'=>$reason]);
            }
            Store::query('review',$this->context->site)->where('id',$id)->update(['decision'=>$decision,'reason'=>$reason,'status'=>$decision==='followup'?'followup':'resolved',
                'resolved_uid'=>$this->context->uid,'resolved_at'=>$decision==='followup'?0:time(),'update_time'=>time()]);
            Store::audit($this->context,(int)$review['lead_id'],'review_resolved',['review_id'=>$id,'decision'=>$decision,'reason'=>$reason]);
        });return $this->present(Store::row('review',$this->context->site,$id));
    }
    private function present(array $row): array
    {
        $lead=Store::adminLead($this->context,(int)$row['lead_id']);$row['lead_no']=$lead['lead_no'];$row['customer_name']=$lead['customer_name'];$row['evidence']=Data::decode($row['evidence_json']);unset($row['evidence_json']);
        $row['receiver_name']=(string)(Data::query('merchant',$this->context->site)->where('id',$row['receiver_id'])->value('name')??'原接收门店');$row['merchant_outcome_label']=Rules::status($row['merchant_outcome']);
        return $row;
    }
}
