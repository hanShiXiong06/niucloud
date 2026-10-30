<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\{Db,Event};

/** Transactions own state transitions; read-only ERP references never lock remote stock. */
final class WorkflowService
{
    public function __construct(private readonly Context $context) {}

    public function leads(array $params=[]): array
    {
        $this->context->manager();
        $q=Data::query('lead',$this->context->site);
        $key=Data::text($params,'keyword',100);
        if ($key!=='') $q->whereLike('customer_name|mobile|model|lead_no','%'.$key.'%');
        if (!empty($params['status'])) $q->where('status',Data::text($params,'status',24));
        $result=Data::page($q,$params);
        $result['data']=array_map(function($row){
            $row=Data::regionNames($row);
            $row['requirements']=Data::decode($row['requirements_json']??'');
            unset($row['requirements_json']);
            return $row;
        },$result['data']);
        return $result;
    }

    public function lead(int $id): array
    {
        $this->context->manager();
        $row=Data::regionNames(Data::row('lead',$this->context->site,$id));
        $row['requirements']=Data::decode($row['requirements_json']??'');
        unset($row['requirements_json']);
        return $row;
    }

    public function saveLead(int $id,array $input): array
    {
        $this->context->manager(); $site=$this->context->site;
        return Db::transaction(function () use ($id,$input,$site) {
            // Low-volume manual intake: serialize same-site creation so duplicate checks
            // remain true even when two operators submit the same phone simultaneously.
            if (!Db::name('site')->where('site_id',$site)->lock(true)->field('site_id')->find()) {
                throw new CommonException('当前站点不存在');
            }
            $old=$id?Data::row('lead',$site,$id,true):[];
            if ($old && !in_array($old['status'],['pending','closed'],true)) throw new CommonException('客资已派单，请先处理当前工单，不要覆盖正在跟进的需求');
            if (empty($input['consent']) && empty($input['consent_at']) && empty($old['consent_at'])) {
                throw new CommonException('请先确认客户同意由匹配门店联系，再录入和派单');
            }
            $mobile=Data::text($input,'mobile',32,true);
            if (!preg_match('/^[0-9+() -]{5,32}$/D',$mobile)) throw new CommonException('请填写有效客户联系电话');
            $duplicate=Data::query('lead',$site)->where('mobile',$mobile)->where('id','<>',$id)
                ->whereNotIn('status',['closed','completed'])->find();
            if ($duplicate) throw new CommonException('该电话已有未结束的客资，请先检索原记录，避免重复联系');
            $min=Data::amount($input['budget_min']??null,true);
            $max=Data::amount($input['budget_max']??null,true);
            if ($min!==null && $max!==null && bccomp($min,$max,2)>0) throw new CommonException('预算下限不能大于上限');
            $requirements=[];
            if (array_key_exists('requirements',$input)) {
                if (!is_array($input['requirements'])) throw new CommonException('请选择有效的需求设备');
                $requirements=(new CatalogService($this->context))->resolve($input['requirements'],Data::decode($old['requirements_json']??''));
            } elseif (!empty($old['requirements_json'])) $requirements=Data::decode($old['requirements_json']);
            $mode=Data::text($input,'demand_mode',8)?:($old['demand_mode']??'any');
            if (!in_array($mode,['any','all'],true)) throw new CommonException('请选择任选一款或分别采购多台');
            $summary=$requirements?implode(' / ',array_map(fn($r)=>$r['title'].' ×'.$r['quantity'],$requirements)):Data::text($input,'model',200);
            $row=Data::region($input)+['customer_name'=>Data::text($input,'customer_name',80,true),'mobile'=>$mobile,
                'model'=>mb_substr($summary,0,200),'requirements_json'=>Data::json($requirements),'demand_mode'=>$mode,'budget_min'=>$min,'budget_max'=>$max,
                'remark'=>Data::text($input,'remark',2000),'consent_at'=>$old['consent_at']??time(),'update_time'=>time()];
            if ($id) Data::query('lead',$site)->where('id',$id)->update($row);
            else $id=(int)Data::query('lead',$site)->insertGetId($row+['site_id'=>$site,'lead_no'=>Data::number('LD'),'create_time'=>time()]);
            $this->context->audit('lead',$id,$old?'updated':'created',['consent_recorded'=>true]);
            return $this->lead($id);
        });
    }

    public function assign(int $leadId,array $input): array
    {
        $this->context->manager(); $site=$this->context->site;
        $id=Db::transaction(function () use($leadId,$input,$site) {
            $lead=Data::row('lead',$site,$leadId,true);
            if (!$lead['consent_at']) throw new CommonException('尚未记录客户联系同意，不能派单');
            if (in_array($lead['status'],['completed','deal_pending','closed'],true)) throw new CommonException('该客资当前不能派单，请先核对已有成交或关闭记录');
            $merchant=Data::row('merchant',$site,(int)($input['merchant_id']??0),true);
            if ((int)$merchant['status']!==1 || !(int)$merchant['member_id']) throw new CommonException('请先启用商户并绑定用于接单的会员');
            if (!Db::name('member')->where('site_id',$site)->where('member_id',$merchant['member_id'])->where('status',1)->where('is_del',0)->where('delete_time',0)->count()) throw new CommonException('商户绑定会员已失效，请重新绑定可登录的本站会员后派单');
            $candidates=(new ReadService($this->context))->matches($leadId)['candidates'];
            $selected=array_values(array_filter($candidates,fn($r)=>(int)$r['merchant_id']===(int)$merchant['id']));
            if (!$selected) throw new CommonException('商户不在该客资的服务地区内，请先核对服务地区');
            if (!empty($selected[0]['needs_manual']) && empty($input['manual_stock_confirmed'])) throw new CommonException('没有可参考的新鲜库存，请先人工核实门店服务能力和实物，再勾选确认派单');
            $active=(int)$lead['active_task_id'];
            if ($active) {
                $old=Data::row('task',$site,$active,true);
                if ($old['status']==='assigned' && (int)$old['merchant_id']===(int)$merchant['id']) return (int)$old['id'];
                if (bccomp(DealProgress::view($old,$lead)['total_confirmed_amount'],'0',2)>0) throw new CommonException('本工单已有核验成交，不能转移已产生的财务责任；请先结束剩余需求，另建后续客资');
                if (!in_array($old['status'],['assigned','following','rejected','closed','reassigned'],true)) throw new CommonException('当前工单不能转派');
                Data::query('task',$site)->where('id',$active)->update(['status'=>'reassigned','close_reason'=>'管理员转派','update_time'=>time()]);
                $this->history($active,'reassigned','管理员转派，原商户不再拥有该客资的联系权限');
            }
            $minutes=(int)($input['response_minutes']??30);
            if ($minutes<5 || $minutes>1440) throw new CommonException('接单期限请设置为5—1440分钟');
            $inventoryId=(int)($input['inventory_id']??0);
            if ($inventoryId) {
                if (!array_filter($selected[0]['items'],fn($item)=>(int)$item['id']===$inventoryId)) {
                    throw new CommonException('关联设备不在本次新鲜匹配推荐中，请重新选择；需要人工派单时可不绑定具体设备');
                }
                $inventory=Data::row('inventory',$site,$inventoryId);
                $connection=Data::row('connection',$site,(int)$inventory['connection_id']);
                if ((int)$inventory['merchant_id']!==(int)$merchant['id'] || (int)$connection['published_run_id']!==(int)$inventory['sync_run_id']) {
                    throw new CommonException('关联商品不属于该商户的当前库存快照');
                }
            }
            $id=(int)Data::query('task',$site)->insertGetId(['site_id'=>$site,'task_no'=>Data::number('TK'),
                'lead_id'=>$leadId,'merchant_id'=>$merchant['id'],'inventory_id'=>$inventoryId,'status'=>'assigned',
                'response_deadline'=>time()+$minutes*60,'create_time'=>time(),'update_time'=>time()]);
            Data::query('lead',$site)->where('id',$leadId)->update(['active_task_id'=>$id,'status'=>'assigned','update_time'=>time()]);
            $remark=Data::text($input,'remark',2000);
            $this->history($id,'assigned',$remark!==''?$remark:'已派单，商户接单时需确认实物、售价和可售状态；派单不等于锁货');
            $this->context->audit('task',$id,'assigned',['lead_id'=>$leadId,'merchant_id'=>(int)$merchant['id'],'previous_task_id'=>$active]);
            return $id;
        });
        $this->emit('HsxLeadTaskAssigned',$id,'assigned');
        return $this->detail($id);
    }

    public function tasks(array $params=[]): array
    {
        $this->context->manager();
        $q=Data::query('task',$this->context->site);
        $todo=Data::text($params,'todo',24);
        if ($todo==='overdue') $q->where('status','assigned')->where('response_deadline','<',time());
        elseif ($todo==='partial') $q->where('status','following')->where('completion_status','partial');
        elseif ($todo==='verification') $q->where('status','deal_pending');
        if (!empty($params['status'])) $q->where('status',Data::text($params,'status',24));
        if (!empty($params['merchant_id'])) $q->where('merchant_id',(int)$params['merchant_id']);
        $key=Data::text($params,'keyword',100);
        if ($key!=='') {
            $leads=Data::query('lead',$this->context->site)->whereLike('customer_name|mobile|model|lead_no','%'.$key.'%')->column('id');
            $q->where(function($w)use($key,$leads){$w->whereLike('task_no','%'.$key.'%')->whereOr('lead_id','in',$leads?:[0]);});
        }
        $result=Data::page($q,$params);
        $result['data']=array_map(fn($r)=>$this->presentTask($r,false),$result['data']);
        return $result;
    }

    public function detail(int $id): array
    {
        return $this->presentTask($this->context->task($id),true);
    }

    private function presentTask(array $task,bool $details): array
    {
        $site=$this->context->site;
        $lead=Data::row('lead',$site,(int)$task['lead_id']);
        $merchant=Data::row('merchant',$site,(int)$task['merchant_id']);
        $redacted=$this->context->uid<=0 && (int)$lead['active_task_id']!==(int)$task['id'];
        if ($redacted) {
            $lead['mobile']=''; $lead['customer_name']='已转派客资'; $lead['remark']='';
            // Requirements are mutable on the shared lead after a rejection. Free-form
            // notes may contain contact details; a former merchant must not read new notes.
            $lead['model']='当前需求已交由新的承接商户处理';
            $lead['requirements_json']='[]';
        }
        $progress=DealProgress::view($task,$lead);
        if ($redacted) $progress['requirements']=[];
        $lead['requirements']=$progress['requirements'];
        unset($lead['requirements_json']);
        $task['lead']=Data::regionNames($lead);
        $task=array_replace($task,$progress);
        unset($task['deal_items_json'],$task['confirmed_items_json'],$task['deal_history_json']);
        $task['demand_mode']=$lead['demand_mode']??'any';
        $task['merchant']=['id'=>$merchant['id'],'name'=>$merchant['name']];
        $task['merchant_name']=$merchant['name'];
        $task['customer_name']=$lead['customer_name']; $task['mobile']=$lead['mobile'];
        $task['model']=$lead['model'];
        $task['is_overdue']=$task['status']==='assigned' && (int)$task['response_deadline']<time();
        $task['seconds_remaining']=$task['status']==='assigned'?max(0,(int)$task['response_deadline']-time()):0;
        $task['can_accept']=$task['status']==='assigned' && !$task['is_overdue'];
        $task['can_reassign']=in_array($task['status'],['assigned','following','rejected'],true) && bccomp($progress['total_confirmed_amount'],'0',2)===0;
        $task['reassign_reason']=$task['can_reassign']?'':'已有成交或当前状态不可转派，请先处理当前工单';
        $task['followups']=[];
        if ($details) {
            $task['followups']=Data::query('followup',$site)->where('task_id',$task['id'])->order('id desc')->limit(200)->select()->toArray();
            foreach($task['followups'] as &$follow) {
                $follow['operator_name']=(int)$follow['operator_member_id']>0?'商户会员':'平台工作人员';
            }
            unset($follow);
        }
        return $task;
    }

    public function act(int $id,string $action,array $input=[]): array
    {
        if (in_array($action,['verify','cancel'],true)) $this->context->manager();
        $site=$this->context->site;
        Db::transaction(function()use($id,$action,$input,$site){
            $initial=$this->context->task($id);
            $lead=Data::row('lead',$site,(int)$initial['lead_id'],true);
            $task=$this->context->task($id,true);
            if ((int)$lead['active_task_id']!==$id) throw new CommonException('该工单已退回或转派，请刷新，不再允许处理原客资');
            $update=['update_time'=>time()]; $leadStatus=$lead['status']; $content='';
            switch($action) {
                case 'accept':
                    if ($task['status']==='following') return;
                    $this->requireState($task,['assigned']);
                    if ((int)$task['response_deadline']<time()) throw new CommonException('接单期限已过，请联系管理员重新派单');
                    $update+=['status'=>'following','accepted_at'=>time()]; $leadStatus='following'; $content='已接单；商户负责核实实物、价格并联系客户';
                    break;
                case 'reject':
                    $this->requireState($task,['assigned','following']);
                    if (bccomp(DealProgress::view($task,$lead)['total_confirmed_amount'],'0',2)>0) throw new CommonException('已有核验成交，请使用结束剩余需求，不可退回已成交工单');
                    $content=Data::text($input,'reason',1000,true);
                    $update+=['status'=>'rejected','close_reason'=>$content]; $leadStatus='pending';
                    Data::query('lead',$site)->where('id',$lead['id'])->update(['active_task_id'=>0]);
                    break;
                case 'follow':
                    $this->requireState($task,['following']);
                    $content=Data::text($input,'content',4000,true);
                    break;
                case 'deal':
                    $this->requireState($task,['following','deal_pending']);
                    $update+=DealProgress::prepare($task,$lead,$input);
                    $update+=['status'=>'deal_pending','deal_no'=>Data::text($input,'deal_no',100,true),
                        'evidence'=>Data::text($input,'evidence',6000,true),'reported_at'=>time()];
                    $leadStatus='deal_pending'; $content='商户已申报本批实际成交，等待平台逐项核验；不代表全部需求已完成';
                    break;
                case 'verify':
                    $revision=$input['deal_revision']??null;
                    if ((!is_int($revision) && !is_string($revision)) || !preg_match('/^[1-9][0-9]*$/D',(string)$revision) || (string)$revision!==(string)$task['deal_revision']) throw new CommonException('本批成交已变化，请刷新后核验对应批次');
                    if ((int)$task['deal_revision']>0 && (int)$task['deal_revision']<=(int)$task['last_verified_revision']) return;
                    $this->requireState($task,['deal_pending']);
                    if (!in_array($input['approved']??null,[true,false,1,0,'1','0'],true)) throw new CommonException('请明确选择本批核验通过或退回');
                    if (!(bool)$input['approved']) {
                        $content=Data::text($input,'remark',1000,true);
                        $update+=['status'=>'following']; $leadStatus='following';
                        break;
                    }
                    $commission=Data::amount($input['commission_amount']??'');
                    if (bccomp($commission,(string)$task['deal_amount'],2)>0) throw new CommonException('佣金不能高于成交金额');
                    $update+=DealProgress::approve($task,$lead,$commission,$this->context->uid);
                    $leadStatus=$update['status'];
                    $content=$leadStatus==='following'?'本批已核验并生成独立佣金台账；剩余设备继续跟进':'本批已核验并生成独立佣金台账；工单已结束，不代表佣金已经收款';
                    if (!Data::query('commission',$site)->where('task_id',$id)->where('deal_revision',$task['deal_revision'])->count()) {
                        Data::query('commission',$site)->insert(['site_id'=>$site,'task_id'=>$id,'merchant_id'=>$task['merchant_id'],
                            'deal_revision'=>$task['deal_revision'],
                            'amount'=>$commission,'status'=>bccomp($commission,'0',2)===0?'void':'unpaid',
                            'remark'=>bccomp($commission,'0',2)===0?'零佣金，无需收款':'核验成交后登记应收；实际收到后再确认',
                            'create_time'=>time(),'update_time'=>time()]);
                    }
                    break;
                case 'close':
                    $this->requireState($task,['assigned','following']);
                    $content=Data::text($input,'reason',1000,true);
                    $partial=bccomp(DealProgress::view($task,$lead)['total_confirmed_amount'],'0',2)>0;
                    $update+=['status'=>$partial?'completed':'closed','close_reason'=>$content,
                        'completion_status'=>$partial?'partial_closed':'none','close_remaining'=>$partial?1:0,'remaining_reason'=>$partial?$content:''];
                    $leadStatus=$update['status'];
                    break;
                case 'cancel':
                    if ($task['status']==='cancelled') return;
                    $this->requireState($task,['assigned','following','deal_pending','completed']);
                    $content=Data::text($input,'reason',1000,true);
                    $update+=['status'=>'cancelled','completion_status'=>'cancelled','close_reason'=>$content]; $leadStatus='closed';
                    $commissions=Data::query('commission',$site)->where('task_id',$id)->lock(true)->select()->toArray();
                    foreach($commissions as $commission) Data::query('commission',$site)->where('id',$commission['id'])->update([
                        'status'=>$commission['status']==='paid'?'refund_pending':(in_array($commission['status'],['refunded','refund_pending'],true)?$commission['status']:'void'),
                        'update_time'=>time()]);
                    break;
                default: throw new CommonException('不支持的工单操作');
            }
            Data::query('task',$site)->where('id',$id)->update($update);
            Data::query('lead',$site)->where('id',$lead['id'])->update(['status'=>$leadStatus,'update_time'=>time()]);
            $this->history($id,$action,$content);
            $this->context->audit('task',$id,$action,['from'=>$task['status'],'to'=>$update['status']??$task['status']]);
        });
        $this->emit('HsxLeadTaskChanged',$id,$action);
        // Rejection revokes contact access; detail remains available with contact redacted.
        return $this->detail($id);
    }

    private function requireState(array $task,array $states): void
    {
        if (!in_array($task['status'],$states,true)) throw new CommonException('当前工单状态不支持该操作，请刷新后确认');
    }

    private function history(int $task,string $action,string $content): void
    {
        Data::query('followup',$this->context->site)->insert(['site_id'=>$this->context->site,'task_id'=>$task,
            'uid'=>$this->context->uid,'operator_member_id'=>$this->context->memberId,'action'=>$action,'content'=>$content,
            'create_time'=>time(),'update_time'=>time()]);
    }

    public function commissions(array $params=[]): array
    {
        $this->context->manager();
        $q=Data::query('commission',$this->context->site);
        if (!empty($params['status'])) $q->where('status',Data::text($params,'status',24));
        $this->filterCommissions($q,$params);
        $result=Data::page($q,$params);
        $result['data']=array_map(fn($r)=>$this->presentCommission($r),$result['data']);
        return $result;
    }

    private function presentCommission(array $row): array
    {
        $row['merchant_name']=(string)Data::query('merchant',$this->context->site)->where('id',$row['merchant_id'])->value('name');
        $row['workorder_id']=$row['task_id'];
        $row['task_no']=(string)Data::query('task',$this->context->site)->where('id',$row['task_id'])->value('task_no');
        return $row;
    }

    public function commissionAction(int $id,string $action,array $input): array
    {
        $this->context->manager(); $site=$this->context->site;
        return Db::transaction(function()use($site,$id,$action,$input){
            // Lock task before ledger, same ordering as cancellation.
            $initial=Data::row('commission',$site,$id);
            $task=Data::row('task',$site,(int)$initial['task_id'],true);
            $row=Data::row('commission',$site,$id,true);
            $amount=Data::amount($input['amount']??'');
            if (bccomp($amount,(string)$row['amount'],2)!==0) throw new CommonException('本期按整笔登记，请核对实际金额与台账金额一致');
            $receipt=Data::text($input,'receipt_no',100,true);
            $remark=Data::text($input,'remark',2000);
            if ($action==='receive') {
                if ($row['status']==='paid') return $this->presentCommission($row);
                if ($row['status']!=='unpaid' || !in_array($task['status'],['following','deal_pending','completed'],true) || (int)$row['deal_revision']>(int)$task['last_verified_revision']) throw new CommonException('该账目尚未核验或已取消，当前不能登记收款');
                $changes=['status'=>'paid','received_at'=>time(),'received_uid'=>$this->context->uid,'receipt_no'=>$receipt];
            } elseif ($action==='refund') {
                if ($row['status']==='refunded') return $this->presentCommission($row);
                if ($row['status']!=='refund_pending') throw new CommonException('只有已取消成交并待退款的佣金可登记退款');
                $changes=['status'=>'refunded','refunded_at'=>time(),'refund_no'=>$receipt];
            } else throw new CommonException('不支持的佣金操作');
            Data::query('commission',$site)->where('id',$id)->update($changes+['remark'=>$remark,'update_time'=>time()]);
            $this->context->audit('commission',$id,$action,['amount'=>$amount,'receipt_no'=>$receipt]);
            return $this->presentCommission(Data::row('commission',$site,$id));
        });
    }

    public function portal(array $params=[]): array
    {
        $merchant=$this->context->merchant(); $site=$this->context->site; $id=(int)$merchant['id'];
        $q=Data::query('task',$site)->where('merchant_id',$id);
        if (!empty($params['status'])) $q->where('status',Data::text($params,'status',24));
        $keyword=Data::text($params,'keyword',100);
        if ($keyword!=='') {
            $ownTaskIds=Data::query('task',$site)->where('merchant_id',$id)->column('id');
            $leadIds=Data::query('lead',$site)->whereIn('active_task_id',$ownTaskIds?:[0])->whereLike('customer_name|mobile|model|lead_no','%'.$keyword.'%')->column('id');
            $q->where(function($w)use($keyword,$leadIds){$w->whereLike('task_no|deal_no','%'.$keyword.'%')->whereOr('lead_id','in',$leadIds?:[0]);});
        }
        $tasks=Data::page($q,$params);
        $tasks['data']=array_map(fn($r)=>$this->presentTask($r,false),$tasks['data']);
        $stats=[];
        foreach(['assigned','following','deal_pending','completed'] as $status) $stats[$status]=Data::query('task',$site)->where('merchant_id',$id)->where('status',$status)->count();
        foreach(['commission_unpaid'=>'unpaid','commission_received'=>'paid'] as $field=>$state) {
            $sum=Data::query('commission',$site)->where('merchant_id',$id)->where('status',$state)->fieldRaw('COALESCE(SUM(amount),0) AS amount')->find();
            $stats[$field]=bcadd((string)($sum['amount']??'0'),'0',2);
        }
        $commissionQuery=Data::query('commission',$site)->where('merchant_id',$id);
        $this->filterCommissions($commissionQuery,$params,$id);
        $commissions=Data::page($commissionQuery,$params);
        $commissions['data']=array_map(fn($r)=>$this->presentCommission($r),$commissions['data']);
        // Minimal merchant profile, never ERP login data or a list of all customer leads.
        return ['merchant'=>['id'=>$id,'name'=>$merchant['name'],'region_text'=>Data::regionNames($merchant)['region_text']],
            'stats'=>$stats,'workorders'=>$tasks,'inventory'=>(new ReadService($this->context))->inventory($params,$id),'commissions'=>$commissions];
    }

    private function filterCommissions($query,array $params,?int $merchantId=null): void
    {
        $key=Data::text($params,'keyword',100);
        if ($key==='') return;
        $tasks=Data::query('task',$this->context->site);
        if ($merchantId!==null) $tasks->where('merchant_id',$merchantId);
        $ids=$tasks->whereLike('task_no|deal_no','%'.$key.'%')->column('id');
        $query->where(function($w)use($key,$ids){$w->whereLike('receipt_no|refund_no','%'.$key.'%')->whereOr('task_id','in',$ids?:[0]);});
    }

    /** A scheduler timeout does not contact the consumer; it returns the lead for re-dispatch. */
    public static function expireAssigned(int $site,int $limit=100): int
    {
        $ids=Data::query('task',$site)->where('status','assigned')->where('response_deadline','<',time())->limit($limit)->column('id');
        $count=0;
        foreach($ids as $id) Db::transaction(function()use($site,$id,&$count){
            $initial=Data::row('task',$site,(int)$id);
            $lead=Data::row('lead',$site,(int)$initial['lead_id'],true);
            $task=Data::row('task',$site,(int)$id,true);
            if ($task['status']!=='assigned' || (int)$task['response_deadline']>=time()) return;
            Data::query('task',$site)->where('id',$id)->update(['status'=>'rejected','close_reason'=>'超过接单期限，系统退回待分配','update_time'=>time()]);
            if ((int)$lead['active_task_id']===(int)$id) Data::query('lead',$site)->where('id',$lead['id'])->update(['active_task_id'=>0,'status'=>'pending','update_time'=>time()]);
            Data::audit($site,0,0,'task',(int)$id,'expired');
            $count++;
        });
        return $count;
    }

    private function emit(string $name,int $id,string $action): void
    {
        try {
            $row=Data::row('task',$this->context->site,$id);
            Event::trigger($name,['site_id'=>$this->context->site,'task_id'=>$id,'action'=>$action,
                'revision'=>(int)($row['deal_revision']??0),'status'=>$row['status'],
                'event_key'=>$id.':'.$action.':'.($row['deal_revision']??0).':'.$row['status']]);
        } catch(\Throwable $e) {
            // Business is committed. Keep a safe actionable trace, never a raw exception/token/customer payload.
            try { $this->context->audit('task',$id,'extension_failed',['event'=>$name,'action'=>$action]); } catch(\Throwable $ignored) {}
        }
    }
}
