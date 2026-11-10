<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,CatalogService};
use addon\hsx_lead\app\support\{Data,MerchantRegion,InventoryAvailability};
use core\exception\CommonException;
use think\facade\{Db,Log};
use addon\hsx_lead\app\service\notice\CooperationResponseNotices;

final class CooperationService
{
    private ?PartnerService $partnerService=null;
    public function __construct(private Context $context) {}
    private function partners(): PartnerService { return $this->partnerService??=new PartnerService($this->context); }
    private function config(): array { return (new ConfigService($this->context))->get(); }
    private function enabled(): array { $c=$this->config();if (!$c['enabled']) throw new CommonException('商户协作尚未启用，请联系平台完成业务配置');return $c; }

    public function catalog(array $params=[]): array
    {
        if ($this->context->uid>0) $this->context->manager();else (new StorefrontService($this->context))->assertUploadAllowed();
        $business=(new BusinessService($this->context))->definition(Data::text($params,'business_type',16)?:'purchase');
        $normalize=static fn(string $value):string=>preg_replace('/\s+/u','',mb_strtolower($value))??'';
        $items=(new BusinessService($this->context))->options($business);$keyword=$normalize(Data::text($params,'keyword',100));
        if ($keyword!=='') $items=array_values(array_filter($items,static fn(array $item):bool=>str_contains($normalize(implode(' ',[$item['title'],$item['brand'],implode(' ',$item['path']??[]),implode(' ',$item['aliases']??[])])),$keyword)));
        $browse=$params['browse']??false;
        if (!in_array($browse,[false,true,0,1,'0','1'],true)) throw new CommonException('型号目录浏览参数无效');
        $limit=in_array($browse,[true,1,'1'],true)?5000:100;
        return array_slice($items,0,$limit);
    }

    public function create(array $input): array
    {
        $merchant=$this->partners()->own(true);$id=(int)$merchant['id'];$request=Store::requestId($input);
        if (!in_array($input['consent']??null,[true,1,'1'],true)) throw new CommonException('请先向客户说明联系方式用途并取得同意');
        $base=Data::region($input)+['customer_name'=>Data::text($input,'customer_name',80,true),'mobile'=>PartnerService::phone($input['mobile']??''),
            'attachments_json'=>Data::json(Store::attachments($input['attachments']??[],$this->context)),
            'remark'=>Data::text($input,'remark',2000)];
        $leadId=Db::transaction(function()use($id,$base,$request,$input):int {
            // Configuration saves lock this same site row: closure and fee changes cannot race publication.
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->field('site_id')->find();
            $partner=Store::partner($this->context->site,$id,true);if ((int)$partner['blocked_at']>0) throw new CommonException('商户已停止合作，不能新增线索');
            $old=Store::query('collaboration',$this->context->site)->where('publisher_id',$id)->where('request_id',$request)->find();
            if ($old) {
                foreach($base as $key=>$value) if ((string)$old[$key]!== (string)$value) throw new CommonException('该请求已用于另一份内容，请刷新核对原线索，不要重复发布');
                $ids=Rules::demandIds($input['demand_ids']??$input['model_ids']??null,0);$oldIds=array_column(Data::decode($old['models_json']),'id');sort($ids);sort($oldIds);
                $rule=Data::decode($old['rule_json']);$form=BusinessService::formValues($rule['business_snapshot']??['fields'=>[]],$input['form_values']??[]);
                if ($ids!==$oldIds||Data::text($input,'business_type',16,true)!==$old['business_type']||($rule['form_values']??[])!==$form) throw new CommonException('该请求已用于另一份业务需求，请核对原单');
                return (int)$old['id'];
            }
            $config=$this->enabled();Rules::assertCapability($partner,$config,'can_publish');
            $type=Data::text($input,'business_type',16,true);$business=(new BusinessService($this->context))->definition($type,true,$config);
            if (empty($config['fee_confirmed'])) throw new CommonException('平台尚未确认本业务收费规则，暂不能发布，请联系管理员');
            $models=(new BusinessService($this->context))->resolve($business,$input['demand_ids']??$input['model_ids']??null);
            $form=BusinessService::formValues($business,$input['form_values']??[]);$snapshot=BusinessService::snapshot($business,$config,$form);$snapshot['publisher_member_id']=$this->context->memberId;
            Rules::split(Rules::cents($business['commission_minimum']),$snapshot);
            $values=$base+['business_type'=>$type,'models_json'=>Data::json($models),'rule_json'=>Data::json($snapshot)];
            $new=(int)Store::query('collaboration',$this->context->site)->insertGetId($values+['site_id'=>$this->context->site,'lead_no'=>Data::number('LC'),'publisher_id'=>$id,'request_id'=>$request,
                'consent_at'=>time(),'status'=>'selecting','create_time'=>time(),'update_time'=>time()]);
            Store::audit($this->context,$new,'published',['publisher_id'=>$id,'model_count'=>count(Data::decode($values['models_json']))]);return $new;
        });return $this->detail($leadId);
    }

    public function lists(array $params=[]): array
    {
        if ($this->context->uid>0) {Store::permission($this->context,'leads');$q=Store::adminQuery($this->context,$params);}
        else {
            $merchant=$this->partners()->own();$direction=Data::text($params,'direction',16)?:'outgoing';if (!in_array($direction,['outgoing','incoming'],true)) throw new CommonException('请选择有效的交付方向');
            $q=Store::query('collaboration',$this->context->site)->where($direction==='incoming'?'receiver_id':'publisher_id',$merchant['id']);
            if ($direction==='incoming') TeamService::scopeIncoming($q,$this->context->memberId);
        }
        $status=Data::text($params,'status',24);if ($status!=='') {
            if ($status==='closed') $q->whereIn('status',['paid','no_deal','cancelled']);
            elseif (in_array($status,['selecting','assigned','following','unpaid','paid','no_deal','cancelled'],true)) $q->where('status',$status);
            else throw new CommonException('请选择有效状态');
        }
        $keyword=Data::text($params,'keyword',100);
        if($this->context->uid>0)\addon\hsx_lead\app\support\AdminKeywordSearch::leads($q,$this->context->site,$keyword);
        elseif($keyword!=='')$q->whereLike('customer_name|models_json|lead_no','%'.addcslashes($keyword,'\\%_').'%');
        foreach(['start_time'=>'>=','end_time'=>'<='] as $key=>$op) if (isset($params[$key])&&$params[$key]!==''&&(int)$params[$key]>0) $q->where('create_time',$op,(int)$params[$key]);
        $page=Data::page($q,$params);$page['data']=array_map(fn(array $row):array=>$this->present($row,false),$page['data']);return $page;
    }

    public function detail(int $id): array { return $this->present($this->authorized($id),true); }
    public function authorized(int $id,bool $lock=false): array
    {
        if ($this->context->uid>0) {Store::permission($this->context,'detail');return Store::adminLead($this->context,$id,$lock);}
        $m=$this->partners()->own();$q=Store::query('collaboration',$this->context->site)->where('id',$id)->where(function($q)use($m):void {$q->where('publisher_id',$m['id'])->whereOr('receiver_id',$m['id']);});if ($lock) $q->lock(true);
        $row=$q->find();if (!$row) throw new CommonException('线索不存在，或不属于当前门店');return $row;
    }

    private function present(array $row,bool $detail): array
    {
        $row=Data::regionNames($row);$row['models']=Data::decode($row['models_json']);$row['attachments']=Data::decode($row['attachments_json']);$row['status_label']=Rules::status($row['status']);
        $rule=Data::decode($row['rule_json']);$row['requires_manual_review']=!empty($rule['requires_manual_review']);$row['review_message']=PaymentService::reviewMessage($rule);
        $row['is_internal']=(int)$row['receiver_id']>0&&(int)$row['publisher_id']===(int)$row['receiver_id'];
        if ($row['is_internal']&&$row['status']==='paid') $row['status_label']='已完成 · 本店交付';
        if($row['status']==='cancelled'&&isset($rule['commission_refund'])){$row['status_label']='已退款关闭';$row['commission_refund_amount']=(string)($rule['commission_refund']['amount']??'');}
        $row['business_name']=$rule['business_name']??(['purchase'=>'二手机','rent'=>'租机','exchange'=>'置换'][$row['business_type']]??$row['business_type']);
        $row['demands']=$row['models'];$row['form_values']=$rule['form_values']??[];$row['business_snapshot']=$rule['business_snapshot']??null;
        $row['fields']=$rule['business_snapshot']['fields']??[];
        $row['commission_minimum']=$rule['commission_minimum']??'50.00';$row['fee_percent']=$rule['fee_percent']??'10.00';$row['fee_minimum']=$rule['fee_minimum']??'9.90';
        $row['fee_confirmed']=!empty($rule['fee_confirmed']);
        $row['dimension']=$rule['dimension']??'model';$row['selection_label']=['model'=>'型号','brand'=>'品牌','operator'=>'运营商'][$row['dimension']]??'需求';
        $row['publisher']=$this->summary((int)$row['publisher_id']);$row['receiver']=(int)$row['receiver_id']>0?$this->summary((int)$row['receiver_id']):null;
        $admin=$this->context->uid>0;$actor=$admin?0:(int)$this->partners()->own()['id'];$publisher=$actor===(int)$row['publisher_id'];$receiver=$actor===(int)$row['receiver_id'];
        $assignment=TeamService::internalAssignment($row);
        if ($assignment!==null) {
            $valid=$assignment['publisher_member_id']>0&&$assignment['assignee_member_id']>0&&$assignment['publisher_member_id']!==$assignment['assignee_member_id'];
            $members=$valid?TeamService::assignmentMembers($this->context->site,Data::row('merchant',$this->context->site,(int)$row['receiver_id']),$assignment['publisher_member_id'],false,$assignment['assignee_member_id']):[];
            $row['internal_assignee']=null;
            foreach($members as $member) if ($member['member_id']===$assignment['assignee_member_id']) $row['internal_assignee']=$member;
            $row['internal_assignee_name']=is_string($rule['internal_assignee_name']??null)?$rule['internal_assignee_name']:($row['internal_assignee']['name']??'');
            $receiver=$receiver&&$row['internal_assignee']!==null&&$assignment['assignee_member_id']===$this->context->memberId;
        }
        $blocked=!$admin&&(int)Store::partner($this->context->site,$actor)['blocked_at']>0;
        if (!$admin&&!$publisher&&(!(int)$row['accepted_at']||$row['status']==='assigned')) {$row['mobile']='';$row['attachments']=[];$row['remark']='';$row['form_values']=[];}
        $config=$this->config();
        $publisherRights=Rules::capabilities(Store::partner($this->context->site,(int)$row['publisher_id']),$config);
        $actorRights=$admin?[]:Rules::capabilities(Store::partner($this->context->site,$actor),$config);
        // Existing publication remains manageable after the publishing membership expires; create() still requires can_publish.
        $row['can_assign']=!$blocked&&!empty($config['enabled'])&&($publisher||$admin)&&$row['status']==='selecting';$row['can_accept']=!$blocked&&!empty($config['enabled'])&&(!empty($actorRights['can_accept'])||$row['is_internal'])&&$receiver&&$row['status']==='assigned';
        // Expiry must not trap a recipient in an assignment or prevent settlement of accepted work.
        $row['can_reject']=!$blocked&&$receiver&&$row['status']==='assigned';$row['can_report']=!$blocked&&$receiver&&$row['status']==='following';$row['can_pay']=$receiver&&$row['status']==='unpaid'&&!$row['is_internal'];
        $needsPermission=$receiver&&!$row['is_internal']&&$row['status']==='assigned'&&empty($actorRights['can_accept']);
        $row['membership_required']=!$admin&&$needsPermission;$row['membership_message']=$row['membership_required']?'当前门店会员未包含本次新操作所需权益，或会员已到期。请查看会员权益；已有工单与账款仍可处理':'';
        $row['can_withdraw']=!$blocked&&$publisher&&$row['status']==='assigned';
        $row['can_reassign']=!$blocked&&!empty($config['enabled'])&&$publisher&&$row['status']==='assigned';
        $row['can_cancel']=!$blocked&&$publisher&&$row['status']==='selecting'&&(int)$row['receiver_id']===0;
        $row['can_review']=$admin;$row['history']=[];
        $now=time();$row['response_overdue']=$row['status']==='assigned'&&(int)$row['response_deadline']>0&&(int)$row['response_deadline']<$now;
        $row['server_time']=$now;$row['response_remaining_seconds']=$row['status']==='assigned'&&((int)$row['response_deadline']>0)?max(0,(int)$row['response_deadline']-$now):0;
        $row['contact_overdue']=$row['status']==='following'&&(int)$config['contact_hours']>0&&(int)$row['accepted_at']+(int)$config['contact_hours']*3600<$now;
        $row['review_overdue']=$row['status']==='unpaid'&&(int)$config['review_hours']>0&&(int)$row['outcome_at']+(int)$config['review_hours']*3600<$now;
        $row['attention_reasons']=array_values(array_filter([$row['review_message'],$row['response_overdue']?'接单提醒时间已到，原门店仍可接单；发布方可继续等待或重新选店':null,$row['contact_overdue']?'跟进时间较长，建议联系门店核对进展':null,$row['review_overdue']?'成交后尚未付佣，建议平台抽检核对':null]));
        $row['needs_attention']=(bool)$row['attention_reasons'];
        if ($detail) {
            $history=Data::query('audit',$this->context->site)->where('entity_type','collaboration')->where('entity_id',$row['id'])->order('id asc')->field('id,action,detail_json,create_time')->select()->toArray();
            $row['history']=array_map(static function(array $a):array {$a['detail']=Data::decode($a['detail_json']);if($a['action']==='commission_refunded')$a['action_label']='协作费用已原路退还';unset($a['detail_json']);return $a;},$history);
            // Admin recipient diagnostics have a dedicated permission and
            // paginated endpoint; ordinary lead detail must not bypass it.
            $row['notice']=$admin?[]:CooperationNoticeService::statusForLead($this->context->site,(int)$row['id']);
        }
        unset($row['models_json'],$row['attachments_json'],$row['rule_json'],$row['request_id']);
        if ($admin) {$row+=$this->context->ownership()->present($row);$row['can_transfer']=Store::scope($this->context)['can_transfer'];if ((int)$row['owner_uid']===0) $row['owner_name']='未分配客服';}else unset($row['owner_uid']);
        return $row;
    }

    private function summary(int $id): array
    {
        $m=Data::row('merchant',$this->context->site,$id);return array_intersect_key($this->partners()->present($m),array_flip(['id','name','storefront_image','contact_name','mobile','region_text','membership','membership_rank','display_rank']));
    }

    public function stores(array $params=[],int $leadId=0): array
    {
        $config=$this->config();
        if ($this->context->uid>0) Store::permission($this->context,'leads');else { $config=$this->enabled();$own=$this->partners()->own(true);if(!$leadId)Rules::assertCapability($own,$config,'can_publish'); }
        if ($leadId) {$lead=$this->authorized($leadId);$publisherId=(int)$lead['publisher_id'];if ($this->context->uid<=0&&$publisherId!==(int)$this->partners()->own()['id']) throw new CommonException('只有发布门店可以选择交付门店');$region=$lead;$models=Data::decode($lead['models_json']);}
        else {$publisherId=$this->context->uid>0?0:(int)$this->partners()->own()['id'];$region=$params;$models=[];}
        $businessKey=$leadId?(string)$lead['business_type']:(Data::text($params,'business_type',16)?:'purchase');
        $leadRule=$leadId?Data::decode($lead['rule_json']):[];
        $business=$leadRule['business_snapshot']??(new BusinessService($this->context))->definition($businessKey,!$leadId);
        $dimension=$business['dimension'];
        if (!$leadId&&array_key_exists('demand_ids',$params)&&$params['demand_ids']!==[]) $models=(new BusinessService($this->context))->resolve($business,$params['demand_ids']);
        // Validate the whole hierarchy first. Only explicit ranges narrow SQL;
        // auto still needs the full province for county -> city -> province fallback.
        MerchantRegion::apply(Data::query('merchant',$this->context->site),$region);
        $region=array_intersect_key($region,array_flip(['province_id','city_id','district_id']));
        foreach(['province_id','city_id','district_id'] as $key) $region[$key]=(int)($region[$key]??0);
        $scope=Data::text($params,'scope',16)?:'auto';if (!in_array($scope,['auto','district','city','province'],true)) throw new CommonException('地区范围无效');
        if ($scope==='district'&&$region['district_id']<=0) throw new CommonException('请先选择客户所在区县');
        if ($scope==='city'&&$region['city_id']<=0) throw new CommonException('请先选择客户所在城市');
        $keyword=mb_strtolower(Data::text($params,'keyword',100));$q=Data::query('merchant',$this->context->site)->where('status',1)->where('id','<>',$publisherId);
        if ((int)($params['_target_merchant_id']??0)>0) $q->where('id',(int)$params['_target_merchant_id']);
        if ($region['province_id']<=0) throw new CommonException('请先选择客户所在省份');
        $q->where('province_id',$region['province_id']);
        if ($scope==='district') $q->where('city_id',$region['city_id'])->where('district_id',$region['district_id']);
        elseif ($scope==='city') $q->where('city_id',$region['city_id']);
        if ($keyword!=='') {
            // Literal substring semantics match the final PHP check, including
            // a query spanning the name/contact separator. An explicit escape
            // keeps %, _ and ! literal, independent of SQL backslash mode.
            $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$keyword).'%';
            $q->whereRaw("LOWER(CONCAT(name,' ',contact_name)) LIKE BINARY ? ESCAPE '!'",[$pattern]);
        }
        $candidates=[];
        foreach($q->select()->toArray() as $m) {
            if (!Rules::allows(Store::partner($this->context->site,(int)$m['id']),$config,'can_accept')) continue;
            $inventory=$this->partners()->inventory((int)$m['id']);$p=$this->partners()->present($m,false,$inventory);if ($p['blocked_at']>0) continue;
            if ($keyword!==''&&!str_contains(mb_strtolower($p['name'].' '.$p['contact_name']),$keyword)) continue;
            $matched=[];
            $declared=[];foreach($p['business_capabilities'] as $capability) if ($capability['business_type']===$businessKey) $declared=CapabilityService::itemsForDimension($capability,$dimension);
            // Sellable ERP stock proves used-phone supply only, not new phones, rental or broadband.
            $inventory=$businessKey==='purchase'&&$dimension==='model'?$inventory:[];
            $allModels=$dimension==='model'&&in_array($businessKey,['purchase','rent','exchange'],true)&&$p['all_models'];
            foreach($models as $model) {
                foreach($inventory as $stock) if (self::matches($model['title'],$stock['model'])) {$matched[$model['id']]=$model;break;}
                foreach($declared as $ability) if ($ability['id']===$model['id']||($dimension==='model'&&self::matches($model['title'],$ability['title']))) {$matched[$model['id']]=$model;break;}
            }
            if ($models&&!$allModels&&!$matched) continue;
            if (!$models&&!$allModels&&!$inventory&&!$declared) continue;
            $p['all_models']=$allModels;$p['match_models']=$allModels?$models:array_values($matched);$p['match_demands']=$p['match_models'];
            $p['business_type']=$businessKey;$p['business_name']=$business['name'];$p['capability_dimension']=$dimension;
            if ($dimension!=='model') $p['capability_source']=$declared?'manual':'none';
            $candidates[]=$p;
        }
        $choose=static function(array $rows,string $range)use($region):array {
            if ($range==='district') return array_values(array_filter($rows,static fn(array $m):bool=>(int)$m['district_id']===$region['district_id']&&$region['district_id']>0));
            if ($range==='city') return array_values(array_filter($rows,static fn(array $m):bool=>(int)$m['city_id']===$region['city_id']&&$region['city_id']>0));return $rows;
        };
        $actual=$scope;$items=[];
        if ($scope==='auto') {
            foreach(['district','city','province'] as $level) {$items=$choose($candidates,$level);$actual=$level;if ($items) break;}
        } else $items=$choose($candidates,$scope);
        usort($items,static fn(array $a,array $b):int=>[(int)$b['membership_rank'],count($b['match_models']),(int)$b['inventory_count'],-(int)$b['id']]<=>[(int)$a['membership_rank'],count($a['match_models']),(int)$a['inventory_count'],-(int)$a['id']]);
        $reason=$scope==='auto'&&$actual!=='district'?($actual==='city'?'本区县暂无匹配门店，已查找全市':'本市暂无匹配门店，已查找全省'):'按客户所在地区查找';
        if (!$items) $reason='所选地区暂时没有可承接该需求的门店';
        // Only real colleagues can receive an internal handoff; paid seat limits do not prove membership.
        if ($publisherId>0 && $this->context->memberId>0 && ((int)($params['_target_merchant_id']??0)===0||(int)$params['_target_merchant_id']===$publisherId)) {
            $own=Data::row('merchant',$this->context->site,$publisherId);
            $ownPartner=Store::partner($this->context->site,$publisherId);
            $members=TeamService::assignmentMembers($this->context->site,$own,$this->context->memberId);
            if ($members&&(int)$own['status']===1&&(int)$ownPartner['blocked_at']===0&&($keyword===''||str_contains(mb_strtolower($own['name'].' '.$own['contact_name']),$keyword))) {
                $internal=$this->partners()->present($own,false,[]);
                $internal=array_replace($internal,['is_internal'=>true,'internal_assignees'=>$members,'match_models'=>$models,'match_demands'=>$models,'business_type'=>$businessKey,'business_name'=>$business['name'],'capability_dimension'=>$dimension]);
                array_unshift($items,$internal);
                if (count($items)===1) $reason='本店成员可直接承接；暂无匹配的外部门店';
            }
        }
        return Store::pageArray($items,$params)+['scope'=>$actual,'region'=>$region,'reason'=>$reason];
    }

    /** Public, safe stock reference for registered merchants, never raw ERP data. */
    public function storeInventory(int $merchantId,array $params=[]): array
    {
        if ($this->context->uid>0) Store::permission($this->context,'partners');else { $own=$this->partners()->own(true);if ((int)$own['id']!==$merchantId) Rules::assertCapability($own,$this->enabled(),'can_view_inventory'); }
        $merchant=Data::row('merchant',$this->context->site,$merchantId);
        $partner=Store::partner($this->context->site,$merchantId);
        if ((int)$merchant['status']!==1||(int)$partner['blocked_at']>0) throw new CommonException('该门店暂时不能提供库存参考');
        $conn=Data::query('connection',$this->context->site)->where('merchant_id',$merchantId)->find();
        $page=max(1,(int)($params['page']??1));$limit=max(1,min(100,(int)($params['limit']??20)));
        $out=['data'=>[],'total'=>0,'per_page'=>$limit,'current_page'=>$page,'has_connection'=>(bool)$conn,'snapshot_at'=>0,
            'store'=>array_intersect_key(Data::regionNames($merchant),array_flip(['id','name','region_text'])),
            'message'=>'该门店暂未接入ERP，可联系门店核对其声明的可交付型号'];
        if (!$conn) return $out;
        if (!(int)$conn['enabled']||!(new \addon\hsx_lead\app\service\ProviderChannelService($this->context->site))->enabled((string)$conn['provider'])) {
            $out['message']='该门店库存同步已暂停，请联系门店核对可交付情况';return $out;
        }
        $run=Data::query('sync_run',$this->context->site)->where('id',$conn['published_run_id'])->where('connection_id',$conn['id'])->where('merchant_id',$merchantId)->find();
        if (!$run||!in_array($run['status'],['completed','success'],true)) {$out['message']='该门店尚未完成首次库存同步，请直接向门店核对';return $out;}
        $out['snapshot_at']=(int)$run['finished_at'];
        $config=$this->config();
        $q=Db::name('hsx_lead_inventory')->alias('i')->join('hsx_lead_connection c','c.id=i.connection_id AND c.site_id=i.site_id AND c.merchant_id=i.merchant_id AND c.published_run_id=i.sync_run_id')
            ->join('hsx_lead_merchant m','m.id=i.merchant_id AND m.site_id=i.site_id')->where('i.site_id',$this->context->site)->where('i.merchant_id',$merchantId)->where('c.id',$conn['id']);
        InventoryAvailability::apply($q,'sellable',true);
        $q->where('i.fetched_at','>=',time()-(int)$config['inventory_fresh_hours']*3600);
        $keyword=Data::text($params,'keyword',100);
        if ($keyword!=='') $q->whereLike('i.brand|i.model|i.memory|i.color|i.grade','%'.addcslashes($keyword,'\\%_').'%');
        $out['total']=(int)(clone $q)->count();
        $rows=$q->field('i.id,i.brand,i.model,i.memory,i.color,i.grade,i.price,i.fetched_at,i.quantity,i.saleable_quantity,i.availability,i.source_status_json,c.last_sync_at,c.last_sync_status,c.sync_interval_minutes,m.status AS merchant_status')
            ->order('i.id desc')->page($page,$limit)->select()->toArray();
        foreach($rows as $row) {
            $safe=array_intersect_key($row,array_flip(['id','brand','model','memory','color','grade','price','fetched_at']));
            $projection=InventoryAvailability::present($row);
            $out['data'][]=$safe+array_intersect_key($projection,array_flip(['sale_status_label','stock_status_label','is_sellable']));
        }
        $out['message']=$out['total']>0?'库存快照仅供选店参考，实际现货和交付时间请与门店确认':'暂无新鲜的在售库存快照，请直接向门店核对可交付情况';
        return $out;
    }

    private static function matches(string $wanted,string $stock): bool
    {
        $normalize=static function(string $s):string {$s=mb_strtolower($s);$s=str_replace(['苹果','apple','华为','huawei','小米','xiaomi','三星','samsung','手机'],'',$s);return preg_replace('/[\s\p{P}]+/u','',$s)??$s;};
        return $normalize($wanted)===$normalize($stock);
    }

    public function assign(int $id,array $input): array
    {
        $this->enabled();$merchantId=(int)($input['merchant_id']??0);if ($merchantId<=0) throw new CommonException('请先选择一家交付门店');
        $replace=$input['replace']??false;if(!is_bool($replace))throw new CommonException('重新选店参数不正确');
        $lead=$this->authorized($id);$publisher=$this->context->uid>0?null:$this->partners()->own(true);
        if ($publisher&&(int)$lead['publisher_id']!==(int)$publisher['id']) throw new CommonException('只有发布门店可以交付该线索');
        $config=$this->config();
        Db::transaction(function()use($id,$input,$merchantId,$config,$replace):void {
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->field('site_id')->find();
            $config=$this->enabled();
            $row=$this->authorized($id,true);Rules::version($row,$input);
            if($replace){if($this->context->memberId<=0||(int)$row['publisher_id']!==(int)$this->partners()->own(true)['id']||$row['status']!=='assigned')throw new CommonException('交付已处理，只有发布方可以在接单前重新选择门店');}
            elseif($row['status']!=='selecting')throw new CommonException('该线索已交付，请刷新状态后操作');
            $publisher=Store::partner($this->context->site,(int)$row['publisher_id'],true);if ((int)$publisher['blocked_at']>0) throw new CommonException('发布门店已停止合作，不能新增交付');
            $internal=$merchantId===(int)$row['publisher_id'];
            $assigneeId=0;$assigneeName='';
            if ($internal) {
                if ($this->context->memberId<=0) throw new CommonException('请由发布门店的成员选择本店接收人');
                $value=$input['assignee_member_id']??0;
                if (!(is_int($value)||(is_string($value)&&ctype_digit($value)))||(int)$value<=0) throw new CommonException('请选择一位本店接收成员');
                $assigneeId=(int)$value;
                $members=TeamService::assignmentMembers($this->context->site,Data::row('merchant',$this->context->site,$merchantId,true),$this->context->memberId,true,$assigneeId);
                if (!in_array($assigneeId,array_column($members,'member_id'),true)) throw new CommonException('不能交给自己；请选择本店已确认加入且可用的其他成员');
                $assigneeName=$members[0]['name'];
            }
            if($replace&&(int)$row['receiver_id']===$merchantId){$oldInternal=TeamService::internalAssignment($row);if(!$internal||($oldInternal!==null&&(int)$oldInternal['assignee_member_id']===$assigneeId))throw new CommonException('请选择其他交付门店或其他本店成员');}
            $result=$this->stores(['scope'=>'province','_target_merchant_id'=>$merchantId],$id);if (!in_array($merchantId,array_map('intval',array_column($result['data'],'id')),true)) throw new CommonException('门店已不可承接该需求，请重新选择');
            $receiver=Store::partner($this->context->site,$merchantId,true);$m=Data::row('merchant',$this->context->site,$merchantId,true);
            if ((int)$receiver['blocked_at']>0||(int)$m['status']!==1) throw new CommonException('该门店已停止合作');
            if (!$internal) Rules::assertCapability($receiver,$config,'can_accept');
            $rule=Data::decode($row['rule_json']);$rule['internal_delivery']=$internal;
            unset($rule['internal_assignee_member_id'],$rule['internal_publisher_member_id'],$rule['internal_assignee_name']);
            if ($internal) {$rule['internal_assignee_member_id']=$assigneeId;$rule['internal_publisher_member_id']=$this->context->memberId;$rule['internal_assignee_name']=$assigneeName;}
            $assignedAt=time();$deadline=(int)$config['response_minutes']>0?$assignedAt+(int)$config['response_minutes']*60:0;
            $publisherMember=(int)($rule['publisher_member_id']??0);if($publisherMember<=0)$publisherMember=(int)Data::row('merchant',$this->context->site,(int)$row['publisher_id'])['member_id'];
            $rule['response_notice_plan']=CooperationResponseNotices::plan($this->context->site,(int)$row['assignment_version']+1,$assignedAt,$deadline,$publisherMember);
            Store::query('collaboration',$this->context->site)->where('id',$id)->update(['receiver_id'=>$merchantId,'status'=>'assigned','assignment_version'=>(int)$row['assignment_version']+1,
                'rule_json'=>Data::json($rule),'accepted_at'=>0,'response_deadline'=>$deadline,'update_time'=>$assignedAt]);
            $this->responseNotices($id,$replace);
            Store::audit($this->context,$id,'assigned',['receiver_id'=>$merchantId,'assignment_version'=>(int)$row['assignment_version']+1]+($internal?['assignee_member_id'=>$assigneeId,'assignee_name'=>$assigneeName]:[]));
            CooperationNoticeOutbox::record($this->context->site,$id,'assigned');
        });$this->notify($id,'assigned');return $this->detail($id);
    }

    public function action(int $id,array $input): array
    {
        $action=Data::text($input,'action',20,true);if (!in_array($action,['accept','reject','withdraw','cancel','deal','no_deal'],true)) throw new CommonException('操作无效');
        $m=$this->partners()->own(true);$mid=(int)$m['id'];$remark=Data::text($input,'remark',1000);
        $changed=Db::transaction(function()use($id,$input,$action,$mid,$remark):bool {
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->field('site_id')->find();
            $partner=Store::partner($this->context->site,$mid,true);if ((int)$partner['blocked_at']>0) throw new CommonException('当前门店已停止合作，不能继续接单或回填');
            $row=$this->authorized($id,true);
            if ($action==='cancel'&&(int)$row['publisher_id']===$mid&&$row['status']==='cancelled') return false;
            Rules::version($row,$input);
            if ($action==='cancel') {
                if ((int)$row['publisher_id']!==$mid||$row['status']!=='selecting'||(int)$row['receiver_id']!==0) throw new CommonException('只能取消尚未选择交付门店的线索；已接单或已成交线索不能取消');
                $values=['status'=>'cancelled','outcome_at'=>time(),'response_deadline'=>0,'assignment_version'=>(int)$row['assignment_version']+1];
            } elseif ($action==='withdraw') {
                if ((int)$row['publisher_id']!==$mid||$row['status']!=='assigned') throw new CommonException('只能撤回尚未接单的交付');
                $values=['status'=>'selecting','receiver_id'=>0,'response_deadline'=>0,'assignment_version'=>(int)$row['assignment_version']+1];
            } else {
                if ((int)$row['receiver_id']!==$mid) throw new CommonException('当前门店不是本次接收方');
                $assignment=TeamService::internalAssignment($row);
                if ($assignment!==null) {
                    $members=TeamService::assignmentMembers($this->context->site,Data::row('merchant',$this->context->site,$mid,true),$assignment['publisher_member_id'],true,$assignment['assignee_member_id']);
                    if ($assignment['publisher_member_id']<=0||$assignment['assignee_member_id']!==$this->context->memberId||!in_array($this->context->memberId,array_column($members,'member_id'),true)) throw new CommonException('这条线索已交给指定成员，仅接收人可以接单或反馈结果');
                }
                if (in_array($action,['accept','reject'],true)) {
                    if ($row['status']!=='assigned') throw new CommonException('该交付已处理，请刷新');
                    if ($action==='accept') { $activeConfig=$this->enabled();if ((int)$row['publisher_id']!==$mid) Rules::assertCapability($partner,$activeConfig,'can_accept'); }
                    $values=$action==='accept'?['status'=>'following','accepted_at'=>time()]:['status'=>'selecting','receiver_id'=>0,'response_deadline'=>0,'assignment_version'=>(int)$row['assignment_version']+1];
                } else {
                    if ($row['status']!=='following') throw new CommonException('只有接单后且尚未反馈的线索可以回填');
                    $internal=(int)$row['publisher_id']===(int)$row['receiver_id'];
                    $values=['status'=>$action==='deal'?($internal?'paid':'unpaid'):'no_deal','outcome_at'=>time()];
                    if ($internal&&$action==='deal') $values+=['commission_amount'=>'0.00','fee_amount'=>'0.00','net_amount'=>'0.00','payment_order_id'=>0,'paid_at'=>0];
                }
            }
            $typedAccept=false;
            if($action==='accept'){
                $rule=Data::decode($row['rule_json']??'');$plan=$rule['response_notice_plan']??[];
                $rule['response_notice_plan']=CooperationResponseNotices::acceptedFact($this->context->site,$plan,(int)$values['accepted_at'],$row);
                $typedAccept=!empty($rule['response_notice_plan']['acceptance']['typed']);$values['rule_json']=Data::json($rule);
            }
            Store::query('collaboration',$this->context->site)->where('id',$id)->update($values+['update_time'=>time()]);Store::audit($this->context,$id,$action,['merchant_id'=>$mid,'remark'=>$remark]);
            $this->responseNotices($id,in_array($action,['accept','reject','withdraw','cancel'],true));
            if($action!=='accept'||!$typedAccept)CooperationNoticeOutbox::record($this->context->site,$id,$action);
            return true;
        });if ($changed) $this->notify($id,$action);
        if ($action==='reject') return ['id'=>$id,'status'=>'selecting','message'=>'已退回发布门店，可由对方重新选择'];
        return $this->detail($id);
    }

    public function dashboard(): array
    {
        Store::permission($this->context,'manage');$q=Store::adminQuery($this->context);$out=['leads_total'=>(int)(clone $q)->count()];
        foreach(['selecting','assigned','following','unpaid','paid','no_deal'] as $s) $out[$s]=(int)(clone $q)->where('status',$s)->count();
        $ids=(clone $q)->column('id');$out['pending_reviews']=(int)Store::query('review',$this->context->site)->whereIn('lead_id',$ids?:[0])->whereIn('status',['pending','followup'])->count();
        foreach(['paid_amount'=>'commission_amount','fee_amount'=>'fee_amount','net_amount'=>'net_amount'] as $key=>$field) $out[$key]=(string)(clone $q)->where('status','paid')->sum($field);
        return $out+['viewer_scope'=>Store::scope($this->context)];
    }

    /** Only active, same-site staff with the new cooperation read contract are assignable. */
    public function owners(array $params=[]): array
    {
        Store::requireTransfer($this->context);$keyword=Data::text($params,'q',80);
        $q=Db::name('sys_user')->alias('u')->join('sys_user_role ur','ur.uid=u.uid')
            ->where('ur.site_id',$this->context->site)->where('ur.status',1)->where('ur.delete_time',0)
            ->where('u.status',1)->where('u.is_del',0)->where('u.delete_time',0);
        if ($keyword!=='') $q->whereLike('u.username|u.real_name','%'.addcslashes($keyword,'\\%_').'%');
        $rows=[];
        foreach($q->distinct(true)->field('u.uid')->order('u.uid')->select()->toArray() as $user) {
            try {$target=$this->targetOwner((int)$user['uid']);}catch(CommonException $ignored){continue;}
            $rows[]=['uid'=>$target['uid'],'name'=>$target['name']];
        }
        return Store::pageArray($rows,$params);
    }

    private function targetOwner(int $uid): array
    {
        $actor=$this->context->ownership()->operator($uid);
        $keys=['hsx_lead_cooperation_leads','hsx_lead_cooperation_detail'];
        if (!$actor['site_role']||(!$actor['admin']&&array_diff($keys,$actor['keys']))) throw new CommonException('归属人员必须是本站启用且有协作线索列表及详情权限的工作人员');
        return $actor;
    }

    public function transferOwner(int $id,array $input): array
    {
        Store::requireTransfer($this->context);Store::permission($this->context,'detail');$uid=\addon\hsx_lead\app\service\OwnershipScope::ownerId($input['owner_uid']??null);$this->targetOwner($uid);
        $expected=\addon\hsx_lead\app\service\OwnershipScope::ownerId($input['expected_owner_uid']??null,true);
        Db::transaction(function()use($id,$uid,$expected):void {Store::requireTransfer($this->context);$row=Store::adminLead($this->context,$id,true);$this->targetOwner($uid);if ((int)$row['owner_uid']!==$expected) throw new CommonException('该线索已被其他管理员转交，请刷新后重新核对归属');if ((int)$row['owner_uid']===$uid) return;Store::query('collaboration',$this->context->site)->where('id',$id)->update(['owner_uid'=>$uid,'update_time'=>time()]);Store::audit($this->context,$id,'owner_transferred',['previous_uid'=>$expected,'owner_uid'=>$uid]);});return $this->detail($id);
    }

    private function notify(int $id,string $action): void
    {
        CooperationResponseNotices::enqueue($this->context->site,$id);
        if($action==='accept'&&CooperationResponseNotices::typedAccept(Store::row('collaboration',$this->context->site,$id)))return;
        try {event('HsxLeadCollaborationChanged',['site_id'=>$this->context->site,'lead_id'=>$id,'action'=>$action]);}
        catch(\Throwable $error) {Log::warning('协作业务已保存，通知待核对',['site_id'=>$this->context->site,'lead_id'=>$id,'action'=>$action]);}
    }
    private function responseNotices(int $id,bool $invalidate=false): void
    {
        try{if($invalidate)CooperationResponseNotices::invalidatePending($this->context->site,$id);CooperationResponseNotices::recordCurrent($this->context->site,$id);}
        catch(\Throwable $error){Log::warning('接单提醒计划待补偿，业务已保存',['site_id'=>$this->context->site,'lead_id'=>$id,'error_type'=>get_class($error)]);}
    }
}
