<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;
use addon\hsx_boyuan\app\support\CatalogStore;
use addon\hsx_boyuan\app\service\{ConfigService,TaskService,PermissionService,ReferenceService,BatchService,MappingCandidateService,PaymentSyncService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_boyuan\app\service\{DesktopConnectionService,PurchaseReleaseReadiness};
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\{Db,Log};
final class Workbench extends BaseAdminController
{
    private function run(string $method,string $api,callable $callback)
    {
        (new PermissionService())->assertApi($method,'boyuan/'.$api);
        try { return success($callback((int)$this->request->siteId(),(int)$this->request->uid())); }
        catch (Problem $e) { throw new CommonException('['.$e->reason.'] '.$e->getMessage()); }
        catch (CommonException $e) { throw $e; }
        catch (\Throwable $e) { $info=$e instanceof \think\db\exception\PDOException ? ($e->getData()['PDO Error Info']??[]) : []; $driver=(int)($info['Driver Error Code']??0); Log::error('hsx_boyuan command error; type='.get_class($e).'; driver_code='.$driver.'; location='.basename($e->getFile()).':'.$e->getLine().($driver===1146?'; missing_table='.(preg_match("/Table '([a-zA-Z0-9_.]+)'/",(string)($info['Driver Error Message']??''),$m)?$m[1]:'unknown'):'')); throw new CommonException('操作未完成，请按任务编号核查插件安装和服务状态'); }
    }
    public function config() { return $this->run('get','config',function($site) {
        $operation=$this->request->params([['operation','']])['operation']??'';
        if ($operation==='connection_setup') {
            $config=(new ConfigService())->get($site);
            $result=DesktopConnectionService::view($config);
            $result['exchange_views']=$this->exchangeAccess($site,$config)['views'];
            foreach (['can_edit'=>'config','can_probe'=>'probe'] as $key=>$api) {
                try { (new PermissionService())->assertApi('post','boyuan/'.$api); $result[$key]=true; }
                catch (CommonException $e) { $result[$key]=false; }
            }
            if ($config['mode']==='production' && $result['can_edit']) {
                try { (new PermissionService())->assertManualDeliveryManager(); $result['purchase_release']=(new PurchaseReleaseReadiness())->inspect($site,$config); }
                catch (CommonException $ignored) {}
                try {
                    $perm=new PermissionService(); $perm->assertManualDeliveryManager(); $perm->assertApi('post','erp/finance/payable/party/<party_id>/confirm_items_payment');
                    $live=(new ConfigService())->get($site,false); $p=$live['profiles']['production'];
                    $result['voucher_release']=['ready'=>\addon\hsx_boyuan\app\support\ProductionVoucher::ready($live,$p),'enabled'=>\addon\hsx_boyuan\app\support\ProductionVoucher::released($p)];
                } catch (CommonException $ignored) {}
            }
            return $result;
        }
        if($operation==='uat_manual_delivery') {
            (new PermissionService())->assertManualDeliveryManager();
            return (new ConfigService())->manualDeliveryState($site);
        }
        return (new ConfigService())->get($site);
    }); }
    public function saveConfig() {
        return $this->run('post','config',function($site,$uid) {
            $operation=$this->request->params([['operation','']])['operation']??'';
            if($operation==='production_vouchers') {
                $perm=new PermissionService(); $perm->assertManualDeliveryManager(); $perm->assertApi('post','erp/finance/payable/party/<party_id>/confirm_items_payment');
                return (new PurchaseReleaseReadiness())->vouchers($site,$uid,$this->request->params([['mode',''],['revision',-1],['connection_key',''],['confirm_account',''],['enable',null]]));
            }
            if ($operation==='production_manual_purchase') {
                (new PermissionService())->assertManualDeliveryManager();
                return (new PurchaseReleaseReadiness())->enable($site,$uid,$this->request->params([
                    ['mode',''],['revision',-1],['connection_key',''],['confirm_account',''],['candidate_task_id',-1]]));
            }
            if ($operation==='connection_trace') {
                (new PermissionService())->assertApi('get','boyuan/config');
                return (new ConfigService())->setTrace($site,$uid,$this->request->params([['revision',-1],['mode',''],['connection_key',''],['intent',''],['window_id','']]));
            }
            if ($operation==='connection_setup' || $operation==='connection_pause') {
                (new PermissionService())->assertApi('get','boyuan/config');
                $service=new ConfigService();
                $current=$service->get($site);
                if ($operation==='connection_pause') {
                    $in=$this->request->params([['revision',-1],['mode',''],['connection_key','']]);
                    if ($current['mode']!==$in['mode'] || $current['profiles'][$current['mode']]['connection_key']!==$in['connection_key'])
                        throw new Problem('CONFIG_STALE','账套已变化，请重新载入后再暂停');
                    $changes=['revision'=>$in['revision'],'paused'=>true];
                } else {
                    $in=$this->request->params([['revision',-1],['mode',''],['endpoint',''],['app_id',''],['account_id',''],['app_secret',''],['contract_confirmed',false],['confirm_target',''],['party_phone_field',null]]);
                    $changes=DesktopConnectionService::changes($current,$in);
                }
                return DesktopConnectionService::view($service->save($site,$uid,$changes));
            }
            if($operation==='uat_manual_delivery') {
                (new PermissionService())->assertManualDeliveryManager();
                return (new ConfigService())->setManualDelivery($site,$uid,$this->request->params([['revision',-1],['connection_key',''],['intent',''],['confirm_account','']]));
            }
            $old=(new ConfigService())->get($site,false);
            $fields=[['revision',-1],['profile',[]],['confirm_account','']];
            foreach(['mode','enabled','paused','finance_enabled','purchase_enabled','purchase_mode','uat_payment_enabled','assignee_uid'] as $key) $fields[]=[$key,$old[$key]];
            return (new ConfigService())->save($site,$uid,$this->request->params($fields));
        });
    }
    public function probe() { return $this->run('post','probe',function($site,$uid) {
        $in=$this->request->params([['operation',''],['revision',-1],['mode',''],['connection_key','']]);
        if ($in['operation']==='connection_setup') (new PermissionService())->assertApi('get','boyuan/config');
        return (new ConfigService())->probe($site,$uid,null,$in['operation']==='connection_setup'?$in:null);
    }); }
    public function staff() { return $this->run('get','staff',function($site) {
        $ids=Db::name('sys_user_role')->where('site_id',$site)->where('status',1)->column('uid');
        return $ids?Db::name('sys_user')->whereIn('uid',$ids)->where('status',1)->field('uid,username,real_name')->select()->toArray():[];
    }); }
    /** Optional narrowed view of the active connection. Never broaden the generic task permission/filter. */
    private static function wholesaleScope($query,string $type,array $config): void
    {
        if($type==='') return;
        if($type!=='wholesale_review') throw new Problem('TASK_TYPE_INVALID','不支持的任务视图');
        $mode=(string)$config['mode']; $key=(string)($config['profiles'][$mode]['connection_key']??'');
        if($key==='' || !in_array($mode,['test','mock'],true)) { $query->whereRaw('1=0'); return; }
        $query->where('mode',$mode)->where('connection_key',$key);
        if($mode==='test') { $query->where('kind','wholesale_outbound'); return; }
        $query->where('kind','sale');
        $doc="IF(JSON_VALID(snapshot_json),snapshot_json,'{}')";
        foreach(['kind'=>'sale','sale_type'=>'wholesale','payment_method_ref'=>'method:挂账'] as $k=>$value)
            $query->whereRaw("BINARY JSON_UNQUOTE(JSON_EXTRACT($doc,'$.$k')) = ?",[$value]);
    }
    public function tasks() {
        $input=$this->request->params([['page',1],['limit',15],['status',''],['keyword',''],['mode',''],['kind',''],['type','']]);
        return $this->run('get','tasks',function($site) use($input) {
            $config=(new ConfigService())->get($site); $active=$config['profiles'][$config['mode']];
            $query=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('connection_key',$active['connection_key']);
            try { (new PermissionService())->assertApi('get','erp/finance/payable/lists'); }
            catch(CommonException $ignored) { $query->where('kind','<>',\addon\hsx_boyuan\app\service\PaymentSyncService::KIND); }
            try { \addon\hsx_boyuan\app\service\SaleReceiptSyncService::authorize(false); }
            catch(CommonException $ignored){$query->where('kind','<>',\addon\hsx_boyuan\app\service\SaleReceiptSyncService::KIND);}
            if ($input['mode']!=='') $query->where('mode',$input['mode']);
            if (($input['kind']??'')!=='') $query->where('kind',(string)$input['kind']);
            self::wholesaleScope($query,(string)$input['type'],$config);
            $counts=clone $query;
            $groups=['ready'=>['awaiting_confirmation','pending','retry'],'working'=>['pending','retry','processing','waiting','confirming'],'action_required'=>['awaiting_confirmation','blocked','rejected','partial','attention','manual_pending','created_review','needs_review'],'completed'=>['simulated','succeeded',PaymentSyncService::REGISTERED]];
            if(isset($groups[$input['status']])) $query->whereIn('status',$groups[$input['status']]);
            elseif ($input['status']!=='') $query->where('status',$input['status']);
            if (trim($input['keyword'])!=='') $query->where('business_no','like','%'.addcslashes(mb_substr(trim($input['keyword']),0,100),'%_\\').'%');
            $rows=(clone $query)->field('id,mode,kind,business_no,status,reason,external_no,attempts,notice_status,side_effect,request_json,create_at,update_at,connection_key')->order('id desc')->page(max(1,(int)$input['page']),min(100,max(1,(int)$input['limit'])))->select()->toArray();
            foreach($rows as &$row) { $row['batch_action']=BatchService::actionFor($row); $request=Contract::decode($row['request_json']); if(in_array($request['schema']??'',[\addon\hsx_boyuan\app\service\PurchaseInventoryService::SCHEMA,PaymentSyncService::SCHEMA],true)) $row['parent_purchase_task_id']=(int)($request['source_task_id']??0); if(PaymentSyncService::isRegistered($row)) $row['reason']=PaymentSyncService::REGISTERED_REASON; unset($row['request_json']); } unset($row);
            if ($input['type']==='wholesale_review' && $config['mode']==='mock') foreach($rows as &$row) {
                $row['reason']='仅供核对本站模拟业务，未生成博远出库单。'; $row['external_no']=''; $row['batch_action']=null;
            } unset($row);
            return ['total'=>(clone $query)->count(),'data'=>$rows,'counts'=>$counts->field('status,COUNT(*) AS total')->group('status')->select()->toArray()]
                + ($input['type']==='wholesale_review'?['review_mode'=>$config['mode']]:[]);
        });
    }
    public function detail(int $id) { $type=(string)$this->request->params([['type','']])['type']; return $this->run('get','tasks/<id>',function($site) use($id,$type) {
        $config=(new ConfigService())->get($site); $query=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id);
        self::wholesaleScope($query,$type,$config);
        $row=$query->find(); if (!$row) throw new Problem('TASK_MISSING','任务不存在或不属于当前账套的查询范围');
        if($row['kind']===\addon\hsx_boyuan\app\service\PaymentSyncService::KIND || (Contract::decode($row['request_json'])['schema']??'')===\addon\hsx_boyuan\app\service\PaymentSyncService::SCHEMA) (new PermissionService())->assertApi('get','erp/finance/payable/lists');
        if($row['kind']===\addon\hsx_boyuan\app\service\SaleReceiptSyncService::KIND) \addon\hsx_boyuan\app\service\SaleReceiptSyncService::authorize(false);
        $row['can_process_current']=false;
        if($row['kind']==='wholesale_outbound' && \addon\hsx_boyuan\app\service\ManualDocumentCommandService::canProcess($config,$row)) {
            try { (new PermissionService())->assertApi('post','boyuan/wholesale/<id>/'.($row['query_only']?'verify':'submit'));$row['can_process_current']=true; }
            catch(CommonException $ignored) {}
        }
        foreach (['snapshot','request','result'] as $name) { $row[$name]=Contract::redact(Contract::decode($row[$name.'_json'])); unset($row[$name.'_json']); }
        unset($row['lease_token']);
        if(PaymentSyncService::isRegistered($row)) $row['reason']=PaymentSyncService::REGISTERED_REASON;
        if(in_array($row['request']['schema']??'',[\addon\hsx_boyuan\app\service\PurchaseInventoryService::SCHEMA,\addon\hsx_boyuan\app\service\PaymentSyncService::SCHEMA],true)) $row['parent_purchase_task_id']=(int)($row['request']['source_task_id']??0);
        $mockReview=$type==='wholesale_review' && $row['mode']==='mock';
        if ($mockReview) {
            $row['reason']='这笔单仅用于本地流程演练，未提交博远，也没有真实博远单号。';
            $row['external_no']=''; $row['request']=$row['result']=[];
        }
        $row['missing_mappings']=!$mockReview && $row['status']==='blocked'?TaskService::missingMappings($site,$row['connection_key'],$row['snapshot']):[];
        $row['logs']=[]; // Detailed operational logs are written only through the PHP Log channel.
        $row['active_connection']=$row['connection_key']===$config['profiles'][$config['mode']]['connection_key'] && $row['mode']===$config['mode'];
        if($type==='wholesale_review') $row['write_enabled']=$row['active_connection'] && \addon\hsx_boyuan\app\service\WholesaleSyncService::dispatchEnabled($config,$config['profiles'][$config['mode']]);
        if($type==='wholesale_review' && $row['active_connection'] && $row['kind']==='wholesale_outbound' && $row['side_effect']==='none' && in_array($row['status'],['awaiting_confirmation','blocked'],true))
            $row['creator_draft']=(new \addon\hsx_boyuan\app\service\WholesaleSyncService())->creatorDraft($site,$row['connection_key'],$row['snapshot']);
        return $row;
    }); }
    public function action(int $id) {
        $input=$this->request->params([['action',''],['note',''],['external_no','']]);
        return $this->run('post','tasks/<id>/action',function($site,$uid) use($id,$input) { (new TaskService())->action($site,$id,$uid,$input['action'],$input['note'],$input['external_no']); return ['queued'=>true,'message'=>'处理请求已记录，实际结果由后台异步核验']; });
    }
    public function batch() {
        $input=$this->request->params([['ids',[]],['mode',''],['connection_key',''],['request_id','']]);
        return $this->run('post','task-batch',fn($site,$uid)=>(new BatchService())->submit($site,$uid,$input));
    }
    public function batchStatus() {
        $input=$this->request->params([['ids',[]],['mode',''],['connection_key','']]);
        return $this->run('post','task-batch-status',fn($site)=>(new BatchService())->states($site,$input));
    }
    public function mappingCandidates() {
        $input=$this->request->params([['kind',''],['local_ref','']]);
        return $this->run('post','mapping-candidates',fn($site)=>(new MappingCandidateService())->search($site,$input['kind'],$input['local_ref']));
    }
    public function mappings() {
        $input=$this->request->params([['page',1],['limit',50],['kind',''],['local_ref',''],['keyword','']]);
        return $this->run('get','mappings',function($site) use($input) {
        $c=(new ConfigService())->get($site); $query=Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$c['profiles'][$c['mode']]['connection_key']);
        foreach(['kind','local_ref'] as $field) if($input[$field]!=='') $query->where($field,$input[$field]);
        if(trim($input['keyword'])!=='') $query->where(function($q) use($input){$pattern='%'.addcslashes(mb_substr(trim($input['keyword']),0,100),'%_\\').'%';$q->whereLike('local_name',$pattern)->whereOr('external_name','like',$pattern)->whereOr('external_code','like',$pattern);});
        return ['total'=>(clone $query)->count(),'data'=>$query->order('id desc')->page(max(1,(int)$input['page']),min(100,max(1,(int)$input['limit'])))->select()->toArray()];
    }); }
    public function saveMapping() {
        $input=$this->request->params([['kind',''],['local_ref',''],['local_name',''],['external_id',''],['external_code',''],['external_name',''],['extra',[]],['revision',0],['mode',''],['connection_key','']]);
        return $this->run('post','mappings',function($site,$uid) use($input) {
            if (!in_array($input['kind'],['product','warehouse','location','supplier','customer','staff','method','account'],true)) throw new Problem('MAPPING_INVALID','资料类型无效');
            if ($input['kind']==='product' && stripos((string)$input['local_ref'],'recycle-sku:')===0)
                throw new Problem('RECYCLE_SKU_ORIGIN_ONLY','回收规格对应请在 ERP 原采购单核对时维护；通用资料表不能覆盖该对应');
            foreach (['local_ref','external_id','external_code'] as $field) Contract::id($input[$field]);
            Db::transaction(function() use($site,$uid,$input) {
                Db::name('site')->where('site_id',$site)->lock(true)->find(); $c=(new ConfigService())->get($site); $key=$c['profiles'][$c['mode']]['connection_key'];
                if ($key==='') throw new Problem('CONFIG_REQUIRED','请先保存当前环境配置');
                if(($input['mode']!=='' && $input['mode']!==$c['mode']) || ($input['connection_key']!=='' && $input['connection_key']!==$key)) throw new Problem('CONNECTION_CHANGED','账套已改变，请刷新后重新选择资料');
                if($c['profiles'][$c['mode']]['protocol']==='openapi_v153' && $c['mode']!=='mock') {
                    $resources=['product'=>'products','warehouse'=>'warehouses','staff'=>'staff','customer'=>'parties','supplier'=>'parties','method'=>'payment_methods','account'=>'capital_accounts'];
                    $resource=$resources[$input['kind']]??'';
                    $archive=CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$key)->where('external_id',$input['external_id'])->where('enabled',1)->find();
                    if(!$archive) throw new Problem('ARCHIVE_REQUIRED','请先同步并选择当前连接的启用档案；库位接口尚未提供，不能猜测对应');
                    if($archive['seen_run']!==ReferenceService::latestRun($site,$key,$resource)) throw new Problem('ARCHIVE_STALE','此档案未在最近完整同步中确认，请先核对；旧缓存不会自动建立新对应');
                    \addon\hsx_boyuan\app\service\MappingSourceService::assertMatches($site,$input['kind'],$input['local_ref']);
                    $extra=Contract::decode($archive['extra_json']);
                    if($input['kind']==='customer' && empty($extra['is_cust']) || $input['kind']==='supplier' && empty($extra['is_supplier'])) throw new Problem('PARTY_ROLE_INVALID','博远往来单位不具备所选客户或供应商属性');
                    $input['external_code']=$archive['code']; $input['external_name']=$archive['name'];
                } elseif($input['kind']==='product') Contract::id($input['extra']['unit_code']??null);
                $where=[['site_id','=',$site],['connection_key','=',$key],['kind','=',$input['kind']],['local_ref','=',$input['local_ref']]];
                if($input['kind']==='staff' && Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$key)->where('kind','staff')->where('external_id',$input['external_id'])->where('local_ref','<>',$input['local_ref'])->count()) throw new Problem('STAFF_ALREADY_BOUND','该博远人员已绑定其他本站人员，请先核对原绑定，不会自动覆盖');
                $old=Db::name('hsx_boyuan_mapping')->where($where)->find();
                if ((int)($old['revision']??0)!==(int)$input['revision']) throw new Problem('MAPPING_STALE','对应关系已被修改，请刷新');
                $value=['external_id'=>$input['external_id'],'external_code'=>$input['external_code'],'external_name'=>mb_substr($input['external_name'],0,255),
                    'local_name'=>mb_substr($input['local_name'],0,255),'extra_json'=>Contract::json((array)$input['extra']),'revision'=>(int)$input['revision']+1,'operator_uid'=>$uid,'update_at'=>time()];
                if ($old) Db::name('hsx_boyuan_mapping')->where($where)->update($value);
                else Db::name('hsx_boyuan_mapping')->insert($value+['site_id'=>$site,'connection_key'=>$key,'kind'=>$input['kind'],'local_ref'=>$input['local_ref']]);
                TaskService::audit($site,0,$key,'mapping','saved','资料对应已保存；已发送请求不被修改',$uid,['before'=>$old?Contract::redact($old):[],'after'=>$value]);
            }); return [];
        });
    }
    public function references() {
        $operation=$this->request->params([['operation','']])['operation']??'';
        if($operation==='category_directory' && $this->request->isPost()) {
            return $this->run('post','reference-search',function($site) {
                (new PermissionService())->assertApi('get','boyuan/reference-sync');
                $in=$this->request->params([['expected_connection_key','']]);
                return (new ReferenceService())->categoryDirectory($site,(string)$in['expected_connection_key']);
            })->header(['Cache-Control'=>'private, no-store']);
        }
        if($operation==='archive_detail') {
            if(!$this->request->isPost())throw new CommonException('请从档案列表查看详情');
            return $this->run('post','reference-search',function($site){
                $in=$this->request->params([['resource',''],['id',''],['expected_connection_key',''],['check_source',false]]);
                // A selector alone grants a summary; browsing the archive needs both read permissions.
                try{(new PermissionService())->assertApi('get','boyuan/reference-sync');$full=true;}catch(CommonException $ignored){$full=false;}
                return (new ReferenceService())->detail($site,(string)$in['resource'],(string)$in['id'],(string)$in['expected_connection_key'],$full,null,in_array($in['check_source'],[true,1,'1','true'],true));
            })->header(['Cache-Control'=>'private, no-store']);
        }
        $archiveList=$operation==='archive_list' && $this->request->isPost();
        if($operation!=='' && !$archiveList) throw new CommonException('此查看方式已变更，请刷新页面后重试');
        $input=$this->request->params([['resource','warehouses'],['keyword',''],['cursor','1'],['limit',50],['role',''],['serial_length',null],['archive_status',''],['company_id',''],['cached',false],['enabled_only',false],['expected_connection_key',null]]);
        return $this->run($this->request->isPost()?'post':'get',$this->request->isPost()?'reference-search':'references',function($site) use($input,$archiveList) {
            if (!isset(\addon\hsx_boyuan\app\gateway\OpenApi::RESOURCES[$input['resource']])) throw new Problem('RESOURCE_INVALID','档案类型无效');
            if($archiveList) (new PermissionService())->assertApi('get','boyuan/reference-sync');
            $c=(new ConfigService())->get($site,false); $p=$c['profiles'][$c['mode']];
            if($archiveList || $c['mode']!=='mock' || $input['cached'] || isset($input['expected_connection_key'])) return ['code'=>'OK','data'=>(new ReferenceService())->search($site,$input+['page'=>(int)$input['cursor']],$archiveList)];
            return (new ConfigService())->transport($site,$p,$c['mode'])->send('GET',$p['paths']['references'],$input);
        })->header(['Cache-Control'=>'private, no-store']);
    }
    public function referenceJobs() {
        return $this->run('get','reference-sync',function($site) {
            $capabilities=[];
            foreach(['start'=>'reference-sync','retry'=>'reference-sync/<id>/retry','search'=>'reference-search'] as $key=>$api) {
                try {(new PermissionService())->assertApi('post','boyuan/'.$api); $capabilities[$key]=true;}
                catch(CommonException $ignored) {$capabilities[$key]=false;}
            }
            $capabilities['read_now']=$capabilities['start'];
            try {
                (new PermissionService())->assertApi('get','boyuan/opening-check');
                (new PermissionService())->assertApi('get','erp/opening/lists');
                $capabilities['can_check_opening']=true;
            } catch(CommonException $ignored) { $capabilities['can_check_opening']=false; }
            try { (new PermissionService())->assertManualDeliveryManager(); $capabilities['manual_delivery']=true; }
            catch(CommonException $ignored) { $capabilities['manual_delivery']=false; }
            return (new ReferenceService())->jobs($site,$capabilities);
        });
    }
    public function mappingSources() { $input=$this->request->params([['kind',''],['keyword',''],['page',1]]); return $this->run('get','mapping-sources',fn($site)=>(new \addon\hsx_boyuan\app\service\MappingSourceService())->search($site,$input)); }
    public function syncReferences() {
        $input=$this->request->params([['resources',[]],['intent','refresh'],['expected_connection_key',null],['id',0],['external_id',''],['resource',''],['mode',''],['cursor','']]);
        return $this->run('post','reference-sync',function($site,$uid) use($input) {
            $ref=new ReferenceService();
            if($input['intent']==='refresh_staff_one') {
                (new PermissionService())->assertApi('get','boyuan/reference-sync');
                return $ref->refreshStaff($site,$uid,(string)$input['external_id'],(string)$input['expected_connection_key'],(string)$input['mode']);
            }
            if($input['intent']==='read_job_page') {
                (new PermissionService())->assertApi('get','boyuan/reference-sync');
                return $ref->readJobPage($site,(int)$input['id'],(string)$input['resource'],(string)$input['expected_connection_key'],(string)$input['mode'],(string)$input['cursor']);
            }
            return $ref->start($site,$uid,(array)$input['resources'],(string)$input['intent'],$input['expected_connection_key']??null);
        });
    }
    public function retryReference(int $id) { return $this->run('post','reference-sync/<id>/retry',function($site,$uid) use($id) {(new ReferenceService())->retry($site,$uid,$id); return ['queued'=>true];}); }
    private function assertExchangeView(string $view): void
    {
        $acl=new PermissionService();
        foreach(['boyuan/logs','erp/finance/payable/lists'] as $path) $acl->assertApi('get',$path);
        if(in_array($view,['documents','purchases','payments','purchase_detail','payment_detail'],true)){
            $acl->assertApi('get','boyuan/tasks/<id>');$acl->assertApi('get','erp/purchase/lists');
        } else {
            // An all-business trace can also contain sales/receipt amounts.
            $acl->assertApi('get','erp/finance/receivable/lists');
        }
    }
    private function exchangeAccess(int $site,array $config): array
    {
        $available=\addon\hsx_boyuan\app\service\ExchangeCenterService::available($site,$config);
        $views=[];
        if($available) foreach(['documents','exchanges'] as $view) {
            try { $this->assertExchangeView($view);$views[]=$view;if($view==='documents') array_push($views,'purchases','payments'); }
            catch(CommonException $ignored) {}
        }
        return ['available'=>$available,'views'=>$views];
    }
    public function logs() { return $this->run('get','logs',function($site){
        $in=$this->request->params([['view',''],['day',date('Y-m-d')],['keyword',''],['page',1],['id',0],['cursor',''],['call_id',''],['side','']]);
        if($in['view']==='') return []; // Old mobile activity view never requested a wire log.
        if($in['view']==='access') return $this->exchangeAccess($site,(new ConfigService())->get($site));
        $this->assertExchangeView((string)$in['view']);
        return \addon\hsx_boyuan\app\service\ExchangeCenterService::read($site,$in);
    }); }
}
