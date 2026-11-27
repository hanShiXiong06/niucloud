<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;
use addon\hsx_boyuan\app\gateway\{OpenApi,Transport};
use addon\hsx_boyuan\app\support\{ArchiveView,Contract,Problem,Secret};
use think\facade\Db;

final class ReferenceService
{
    private const RELOAD_PARTY_PHONE='请重新读取客户供应商，以取回博远的完整手机号。';
    private function context(int $site): array
    {
        $service=new ConfigService(); if(!$service->installed($site)) throw new Problem('ADDON_MISSING','请先安装ERP及博远插件');
        $config=$service->get($site,false); return [$config,$config['profiles'][$config['mode']]];
    }
    private static function needsProbe(array $config,array $p): bool
    {
        return $config['mode']!=='mock' && ($p['protocol']!=='openapi_v153' || !$p['probe_at']);
    }
    private static function obsoletePartyRun(string $resource,array $scope): bool
    {
        $field=$scope['party_phone_field']??null;
        return $resource==='parties' && ($field==='spell_code' || ($field==='help_code' && empty($scope['keep_help_code'])));
    }
    private static function stopObsoleteRun(array $where): void
    {
        Db::name('hsx_boyuan_reference_sync')->where($where)->update(['status'=>'needs_review','reason'=>self::RELOAD_PARTY_PHONE,'lease_token'=>'','lease_until'=>0,'next_at'=>0,'update_at'=>time()]);
    }
    public function start(int $site,int $uid,array $resources,string $intent='refresh',?string $expectedConnection=null): array
    {
        if(!$resources || count($resources)>count(OpenApi::RESOURCES)) throw new Problem('RESOURCE_INVALID','请选择需要同步的基础资料');
        foreach($resources as $resource) if(!is_string($resource) || !isset(OpenApi::RESOURCES[$resource])) throw new Problem('RESOURCE_INVALID','资料类型不支持');
        if(!in_array($intent,['refresh','opening'],true) || ($intent==='opening' && !$expectedConnection)) throw new Problem('OPENING_CONTEXT','请刷新后在当前账套开始同步');
        return Db::transaction(function() use($site,$uid,$resources,$intent,$expectedConnection): array {
            if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            [$config,$profile]=$this->context($site);
            if($profile['connection_key']==='') throw new Problem('CONFIG_REQUIRED','请先保存连接配置');
            $this->assertConnection($profile,$expectedConnection);
            if(self::needsProbe($config,$profile)) throw new Problem('PREFLIGHT_REQUIRED','请先保存开放平台授权并通过只读账套核验');
            $ids=[]; $skipped=[];
            foreach(array_unique($resources) as $resource) {
                $where=[['site_id','=',$site],['connection_key','=',$profile['connection_key']],['resource','=',$resource]];
                $running=Db::name('hsx_boyuan_reference_sync')->where($where)->whereIn('status',['queued','running','retry'])->order('id desc')->find();
                if($running) {
                    if(!self::obsoletePartyRun($resource,Contract::decode($running['scope_json']))) {$ids[]=(int)$running['id']; continue;}
                    self::stopObsoleteRun(['site_id'=>$site,'id'=>$running['id']]);
                }
                if($intent==='opening' && !$running) {
                    $latest=Db::name('hsx_boyuan_reference_sync')->where($where)->order('id desc')->field('id,status')->find();
                    if($latest && in_array($latest['status'],['completed','failed','needs_review'],true)) {
                        $skipped[$resource]=$latest['status']; continue;
                    }
                }
                $scope=['since'=>$profile['member_since'],'until'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d')];
                if($resource==='parties') {
                    $scope['party_phone_field']=$profile['party_phone_field']??'contact_phone';
                    if($scope['party_phone_field']==='help_code') $scope['keep_help_code']=true;
                }
                $ids[]=(int)Db::name('hsx_boyuan_reference_sync')->insertGetId(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'mode'=>$config['mode'],
                    'resource'=>$resource,'run_key'=>bin2hex(random_bytes(16)),'operator_uid'=>$uid,'scope_json'=>Contract::json($scope),'create_at'=>time(),'update_at'=>time()]);
                TaskService::audit($site,0,$profile['connection_key'],'reference_start','queued',OpenApi::RESOURCES[$resource][0].'同步已排队',$uid,['sync_id'=>end($ids)]);
            }
            return ['ids'=>$ids,'queued'=>!empty($ids),'skipped'=>$skipped];
        });
    }
    public function retry(int $site,int $uid,int $id): void
    {
        Db::transaction(function() use($site,$uid,$id): void {
            Db::name('site')->where('site_id',$site)->lock(true)->find();
            $row=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('id',$id)->lock(true)->find();
            [$config,$p]=$this->context($site);
            if(!$row || $row['connection_key']!==$p['connection_key'] || $row['mode']!==$config['mode']) throw new Problem('SYNC_MISSING','同步任务不属于当前连接');
            if(self::needsProbe($config,$p)) throw new Problem('PREFLIGHT_REQUIRED','请先核验当前博远账套，再继续原分页');
            if($row['status']!=='failed') throw new Problem('SYNC_STATE','仅失败任务可从原分页继续');
            if(Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('resource',$row['resource'])->where('id','>',$id)->count()) throw new Problem('SYNC_SUPERSEDED','此轮已被更新的同步替代，请刷新查看最新进度');
            Db::name('hsx_boyuan_reference_sync')->where('id',$id)->update(['status'=>'queued','attempts'=>0,'next_at'=>0,'reason'=>'','update_at'=>time()]);
            TaskService::audit($site,0,$p['connection_key'],'reference_retry','queued','人工请求从失败分页继续',$uid,['sync_id'=>$id,'cursor'=>$row['cursor']]);
        });
    }
    private function assertConnection(array $profile,?string $expected): void
    {
        if($expected!==null && ($expected==='' || !hash_equals((string)$profile['connection_key'],$expected))) throw new Problem('CONNECTION_CHANGED','账套已切换，请刷新后再继续');
    }
    public function jobs(int $site,array $capabilities=[]): array
    {
        [$config,$p]=$this->context($site); $result=[];
        foreach(OpenApi::RESOURCES as $resource=>[$name]) {
            $q=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('resource',$resource);
            $job=(clone $q)->field('id,status,cursor,processed,pages,reported_total,reason,warning,scope_json,update_at,next_at')->order('id desc')->find();
            if($job) {
                $job['scope']=Contract::decode($job['scope_json']); unset($job['scope_json']);
                if(self::obsoletePartyRun($resource,$job['scope'])) { $job['status']='needs_review'; $job['reason']=self::RELOAD_PARTY_PHONE; }
            }
            $last=(clone $q)->where('status','completed')->order('id desc')->value('update_at');
            $count=$resource==='categories'?CategoryCatalog::current($site,$p['connection_key'])->count()
                :CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$p['connection_key'])->count();
            $reason=$p['connection_key']===''?'请先保存博远连接':(self::needsProbe($config,$p)?'请先核验当前博远账套':'');
            $result[]=['resource'=>$resource,'name'=>$name,'count'=>$count,'last_success_at'=>(int)$last,'job'=>$job,
                'mode'=>$config['mode'],'connection_key'=>$p['connection_key'],'ready'=>$reason==='','blocking_reason'=>$reason,'capabilities'=>$capabilities];
        }
        return $result;
    }
    public static function phoneHash(int $site,string $phone): string
    {
        $phone=trim($phone); if(!preg_match('/^\+?[0-9 ()-]{6,24}$/D',$phone)) return '';
        $digits=preg_replace('/\D/','',$phone); if(strlen($digits)<6 || strlen($digits)>20) return '';
        $key=(string)env('app.auth_key',''); if($key==='') throw new Problem('ENCRYPTION_KEY_MISSING','系统身份密钥未配置');
        return hash_hmac('sha256',$site.':'.$digits,$key);
    }
    /** Explicit one-row refresh; never creates an employee, an entitlement, a sync run or a voucher. */
    public function refreshStaff(int $site,int $uid,string $id,string $key,string $mode,?OpenApi $testGateway=null,string $expectedName=''): array
    {
        Contract::id($id);
        if($site<1 || $uid<1) throw new Problem('STAFF_REFRESH_SCOPE','请使用本站有同步权限的账号');
        [$c,$p]=$this->context($site); $this->assertConnection($p,$key);
        if($mode!==$c['mode'] || !in_array($mode,['test','production'],true) || self::needsProbe($c,$p) || $p['gateway_path']!=='/gateway.do')
            throw new Problem('PREFLIGHT_REQUIRED','请先核验当前账套再刷新人员');
        DesktopConnectionService::endpoint($mode,$p['endpoint']);
        $where=['site_id'=>$site,'connection_key'=>$key,'external_id'=>$id];
        $q=fn()=>CatalogStore::query('staff')->where($where);
        $fields='id,external_id,name,seen_run,extra_json,update_at';
        $getRun=function() use($site,$key,$mode):string {
            $latest=Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'connection_key'=>$key,'mode'=>$mode,'resource'=>'staff'])
                ->order('id desc')->field('status,run_key')->find();
            if(!$latest || $latest['status']!=='completed' || $latest['run_key']==='')
                throw new Problem('STAFF_REFRESH_BATCH','请先完成或核对当前员工资料同步，再刷新此人');
            return (string)$latest['run_key'];
        };
        $run=$getRun(); $old=$q()->field($fields)->find();
        if(!$old || $old['external_id']!==$id || $old['seen_run']!==$run) throw new Problem('ARCHIVE_STALE','当前有效资料批次没有这位员工，不会新建或恢复旧员工');
        if($expectedName!=='' && $old['name']!==$expectedName) throw new Problem('STAFF_REFRESH_IDENTITY','当前选择的员工姓名不匹配，请核对原档案');
        $extra=Contract::decode($old['extra_json']); $company=Contract::id($extra['company_id']??null);
        $gw=$testGateway??(new ConfigService())->transport($site,$p,$mode,['kind'=>'staff_refresh','operator_uid'=>$uid,'resource'=>'staff']);
        if(!$gw instanceof OpenApi || $gw->accountId()!==$p['account_id']) throw new Problem('CONNECTION_CHANGED','账套不一致，请重新检查连接');
        $item=$gw->staffArchiveById($id,$company);
        if($expectedName!=='' && $item['name']!==$expectedName) throw new Problem('STAFF_REFRESH_IDENTITY','博远返回与指定姓名不一致，保留原档案');
        // Reject a projection that would silently erase other staff roles or turn a hidden employee into an enabled one.
        foreach(['is_engineer','is_sales'] as $role)
            if(array_key_exists($role,$extra) && !in_array($item['extra'][$role]??null,[0,'0',1,'1'],true))
                throw new Problem('STAFF_REFRESH_INCOMPLETE','博远未完整返回该员工原有的岗位信息，保留原档案');
        $values=$this->archiveValues($site,$key,'staff',$item,(int)($extra['source_page']??0),true,true);
        return Db::transaction(function() use($site,$uid,$c,$p,$key,$mode,$fields,$getRun,$run,$q,$old,$item,$values,$id) {
            if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            [$fresh,$profile]=$this->context($site); $this->assertConnection($profile,$key);
            if($fresh['mode']!==$mode || $fresh['revision']!==$c['revision'] || $profile['account_id']!==$p['account_id'] ||
                self::needsProbe($fresh,$profile) || $getRun()!==$run || $q()->field($fields)->lock(true)->find()!==$old)
                throw new Problem('STAFF_REFRESH_STALE','读取期间连接或员工档案已变化，请刷新后重试');
            // Deliberately no seen_run update or insertion; typed lookup columns derive from this same extra_json.
            $q()->where('id',$old['id'])->update($values);
            TaskService::audit($site,0,$key,'staff_refresh','completed','已按编号更新一位员工档案；未更改映射或单据',$uid,['rows'=>1]);
            return ['refreshed'=>true,'external_id'=>$id,'enabled'=>$item['enabled'],'is_buyer'=>(int)$item['extra']['is_buyer'],'updated_at'=>$values['update_at']];
        });
    }
    /** Shared projection for paged and individual refreshes; no raw credentials/PII in the public result. */
    private function archiveValues(int $site,string $key,string $kind,array $item,int $page,bool $archive,bool $requireSnapshot=false):array
    {
        $phone=(string)($item['phone']??''); $extra=(array)($item['extra']??[]);
        if($archive) {
            if($page>0) $extra['source_page']=$page;
            if(isset($item['archive_view']) && is_array($item['archive_view'])) {
                $plain=Contract::json($item['archive_view']);
                if(strlen($plain)<45000) {
                    $cipher=Secret::seal($plain,(string)env('app.auth_key',''),$site,ArchiveView::context($key,$kind,$item['id']));
                    if(strlen(Contract::json($extra))+strlen($cipher)<64000) $extra[ArchiveView::SNAPSHOT]=$cipher;
                }
            }
        }
        if($requireSnapshot && !isset($extra[ArchiveView::SNAPSHOT])) throw new Problem('STAFF_REFRESH_INCOMPLETE','未能保存完整的员工档案快照，保留原档案');
        return ['code'=>$item['code'],'name'=>$item['name'],'enabled'=>(int)$item['enabled'],'phone_hash'=>self::phoneHash($site,$phone),
            'phone_masked'=>$phone===''?'':(strlen($phone)<=7?'****'.substr($phone,-2):mb_substr($phone,0,3).'****'.mb_substr($phone,-4)),'extra_json'=>Contract::json($extra),'update_at'=>time()];
    }
    /** Browse an existing reference only. No synchronization, cache update, or ERP posting on read. */
    public function detail(int $site,string $kind,string $id,string $key,bool $full,?OpenApi $testGateway=null,bool $readUpstream=false): array
    {
        if(!isset(OpenApi::RESOURCES[$kind]))throw new Problem('RESOURCE_INVALID','未知的档案类型');
        Contract::id($id);[$c,$p]=$this->context($site);$this->assertConnection($p,$key);
        $row=CatalogStore::query($kind)->where(['site_id'=>$site,'connection_key'=>$key,'external_id'=>$id])->field('external_id,name,code,enabled,phone_masked,extra_json,update_at')->find();
        if(!$row || $row['external_id']!==$id)throw new Problem('ARCHIVE_MISSING','当前账套没有这条档案');
        $extra=Contract::decode($row['extra_json']);$snap=$extra[ArchiveView::SNAPSHOT]??'';
        $savedPhoneField=$extra['party_phone_field']??'contact_phone';
        $showPhone=$full && $kind==='parties' && $p['party_phone_field']==='help_code';
        $hasPhone=$savedPhoneField==='help_code' && array_key_exists(ArchiveView::PARTY_PHONE,$extra);
        $original=$hasPhone?$extra[ArchiveView::PARTY_PHONE]:null;
        $extra=ArchiveView::publicExtras($extra);
        unset($extra['source_page'],$extra['source_name_missing'],$extra['party_phone_field'],$extra['party_phone_state']);
        $known=$extra['status_known']??true;unset($extra['status_known']);
        $summary=['id'=>$id,'name'=>$row['name'],'code'=>$row['code'],'is_effective'=>$known?(int)$row['enabled']:null]+$extra;
        $agreedPhone=$kind==='parties' && $p['party_phone_field']==='help_code';
        if($agreedPhone && $savedPhoneField!=='help_code') unset($summary['code']);
        $base=['id'=>$id,'resource'=>$kind,'connection_key'=>$key,'archive_at'=>(int)$row['update_at']];
        if($showPhone) $base['phone_needs_sync']=!$hasPhone;
        $notice=$full?'此旧档案只保存了摘要。':'当前账号可查看已保存的档案摘要。';
        if($full && $snap!==''){
            try {
                $fields=Contract::decode(Secret::open($snap,(string)env('app.auth_key',''),$site,ArchiveView::context($key,$kind,$id)));
                if($kind==='parties') {
                    $fields=ArchiveView::safe($kind,$fields);
                    if($showPhone) $fields['help_code']=$original;
                }
                return $base+['source'=>'snapshot','fields'=>$fields,'notice'=>$showPhone && !$hasPhone?self::RELOAD_PARTY_PHONE:''];
            } catch(Problem | \JsonException $e) {
                $notice='这条档案已保存的完整字段暂时无法读取。';
            }
        }
        $mayReadSource=$full && $c['mode']!=='mock' && !self::needsProbe($c,$p);
        if($readUpstream && $mayReadSource) {
            try{return $this->readSource($site,$kind,$id,$key,$testGateway)+$base;}
            catch(Problem $e){if($e->reason==='CONNECTION_CHANGED')throw $e;$notice.=' '.(str_starts_with($e->reason,'OPENAPI_')?'暂时未能从博远核对完整字段，当前仅显示本站摘要。':$e->getMessage());}
        }
        $safe=ArchiveView::safe($kind,$summary);
        if($showPhone){
            $safe['help_code']=$original;
            if(!$hasPhone) $notice.=' '.self::RELOAD_PARTY_PHONE;
        }
        return $base+['source'=>'summary','fields'=>$safe,'notice'=>$notice,'can_check_source'=>$mayReadSource];
    }
    private function readSource(int $site,string $kind,string $id,string $key,?OpenApi $testGateway):array
    {
        Contract::id($id); [$c,$p]=$this->context($site); $this->assertConnection($p,$key);
        $showPhone=$kind==='parties' && $p['party_phone_field']==='help_code';
        if(!in_array($c['mode'],['test','production'],true) || self::needsProbe($c,$p) || $p['gateway_path']!=='/gateway.do')
            throw new Problem('PREFLIGHT_REQUIRED','请先核验当前博远开放平台账套');
        DesktopConnectionService::endpoint($c['mode'],$p['endpoint']);
        $run=self::latestRun($site,$key,$kind);
        $scope=['site_id'=>$site,'connection_key'=>$key,'resource'=>$kind];
        $base=fn()=>CatalogStore::query($kind)->where(['site_id'=>$site,'connection_key'=>$key]);
        $r=$base()->where('external_id',$id)->field('id,external_id,extra_json,seen_run')->find();
        $latest=Db::name('hsx_boyuan_reference_sync')->where($scope)->where('mode',$c['mode'])->order('id desc')->field('run_key,status')->find();
        $active=$latest && in_array($latest['status'],['queued','running','retry'],true)?$latest['run_key']:'';
        if(!$r || $r['external_id']!==$id || $r['seen_run']==='' || !in_array($r['seen_run'],[$run,$active],true)) throw new Problem('ARCHIVE_STALE','本账套最近的有效同步中没有这条档案，请重新读取后核对');
        $extra=Contract::decode($r['extra_json']); $page=(int)($extra['source_page']??0);
        if($page<1) $page=1+intdiv((int)$base()->where('id','<',$r['id'])->count(),100);
        $gateway=$testGateway??(new ConfigService())->transport($site,$p,$c['mode'],['kind'=>'archive_read','resource'=>$kind]);
        if(!$gateway instanceof OpenApi) throw new Problem('PREFLIGHT_REQUIRED','请先核验博远开放平台连接');
        $job=Db::name('hsx_boyuan_reference_sync')->where($scope)->where('run_key',$r['seen_run'])->value('scope_json');
        $result=['id'=>$id,'resource'=>$kind,'fields'=>$gateway->archiveFieldsAt($kind,$id,$page,$job?Contract::decode($job):[],$showPhone),'notice'=>''];
        if($showPhone) $result['phone_needs_sync']=false;
        [$now,$profile]=$this->context($site); $this->assertConnection($profile,$key);
        if($now['mode']!==$c['mode'] || ($showPhone && $profile['party_phone_field']!=='help_code') || self::needsProbe($now,$profile) || self::latestRun($site,$key,$kind)!==$run || $base()->where('external_id',$id)->value('seen_run')!==$r['seen_run'])
            throw new Problem('CONNECTION_CHANGED','账套或归档批次已变更，请刷新后再核对');
        return $result+['source'=>'boyuan','connection_key'=>$key,'page'=>$page];
    }
    /** A category branch may span several list pages. Read only the relationship columns, in one snapshot. */
    public function categoryDirectory(int $site,string $connectionKey): array
    {
        [, $profile]=$this->context($site);
        $this->assertConnection($profile,$connectionKey);
        $max=2000;
        $rows=CategoryCatalog::current($site,$connectionKey)
            ->field('external_id,name,code,parent_id,parent_name,status_known,enabled')->order('id')->limit($max+1)->select()->toArray();
        $result=['resource'=>'categories','connection_key'=>$connectionKey,'source'=>'local_archive','list_only'=>count($rows)>$max,'items'=>[]];
        // A partial tree would falsely turn absent parents into roots: offer the complete paged list instead.
        if($result['list_only']) return $result;
        foreach($rows as $r) $result['items'][]=[
            'id'=>(string)$r['external_id'],'name'=>(string)$r['name'],'code'=>(string)$r['code'],
            'parent_id'=>$r['parent_id'],'parent_name'=>$r['parent_name'],
            'status'=>$r['status_known']!==null && (int)$r['status_known']===0?'unknown':((int)$r['enabled']===1?'enabled':'disabled'),
        ];
        return $result;
    }
    public function search(int $site,array $input,bool $archiveReader=false): array
    {
        [, $p]=$this->context($site); $this->assertConnection($p,$input['expected_connection_key']??null); $resource=(string)($input['resource']??'warehouses');
        if(!isset(OpenApi::RESOURCES[$resource])) throw new Problem('RESOURCE_INVALID','资料类型不支持');
        $q=$resource==='categories'?CategoryCatalog::current($site,$p['connection_key'])
            :CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$p['connection_key']);
        $keyword=trim((string)($input['keyword']??''));
        $partyField=$resource==='parties'?($p['party_phone_field']??'contact_phone'):'';
        if($keyword!=='') {
            $hash=self::phoneHash($site,$keyword); $pattern='%'.addcslashes(mb_substr($keyword,0,100),'%_\\').'%';
            $q->where(function($where) use($hash,$pattern,$partyField): void {
                $where->whereLike('name',$pattern);
                if($partyField==='help_code') {
                    $where->whereOr('external_id','like',$pattern)->whereOr(function($term) use($hash,$pattern){
                        $term->where('party_phone_field','help_code')
                            ->where(function($v) use($hash,$pattern){$v->whereLike('code',$pattern);if($hash!=='')$v->whereOr('phone_hash',$hash);});
                    });
                } else {
                    $where->whereOr('code','like',$pattern)->whereOr('external_id','like',$pattern);
                    if($hash!=='') {
                        if($partyField==='contact_phone') $where->whereOr(function($term) use($hash){$term->where('phone_hash',$hash)->where('party_phone_field','contact_phone');});
                        else $where->whereOr('phone_hash',$hash);
                    }
                }
            });
        }
        $latest=self::latestRun($site,$p['connection_key'],$resource);
        if(!empty($input['enabled_only'])) $q->where('enabled',1)->where('seen_run',$latest);
        $role=$input['role']??'';
        if($role==='purchase_product') {
            $length=$input['serial_length']??null;
            if($resource!=='products' || !(is_int($length)||is_string($length)) || !preg_match('/^[1-9][0-9]?$/D',(string)$length) || (int)$length>64)
                throw new Problem('PRODUCT_SERIAL_LENGTH','没有可核对的设备串号长度，请返回原采购单核对串号。');
            // Filter before count and paging. Browsing the archive must still show all products.
            if($latest==='') $q->whereRaw('1=0');
            $q->where('enabled',1)->where('seen_run',$latest)
                ->whereRaw("BINARY JSON_UNQUOTE(JSON_EXTRACT(extra_json,'$.imei_count')) = '1' AND BINARY JSON_UNQUOTE(JSON_EXTRACT(extra_json,'$.imei_len')) = ?",[(string)$length]);
        }
        if(in_array($role,['customer','supplier'],true)) {
            if($resource!=='parties')$q->whereRaw('1=0');else $q->where($role==='customer'?'is_cust':'is_supplier',1);
        }
        if($resource==='staff' && $role==='buyer')$q->where('is_buyer',1);
        if(in_array($resource,['staff','warehouses'],true) && ($input['company_id']??'')!=='')$q->where('company_id',Contract::id($input['company_id']));
        $status=(string)($input['archive_status']??'');
        if(in_array($status,['enabled','disabled'],true)) $q->where('enabled',(int)($status==='enabled'))->whereRaw('COALESCE(status_known,1)=1');
        elseif($status==='unknown')$q->where('status_known',0);
        elseif($status!=='')throw new Problem('ARCHIVE_STATUS','未知的档案状态筛选');
        $page=max(1,(int)($input['page']??1)); $limit=min(100,max(1,(int)($input['limit']??25))); $total=(clone $q)->count();
        $rows=$q->field('external_id,code,name,enabled,phone_masked,seen_run,update_at')->fieldRaw("JSON_REMOVE(extra_json,'$._boyuan_view_cipher') AS extra_json")->order('id')->page($page,$limit)->select()->toArray(); $items=[];
        foreach($rows as $row) { $row['id']=$row['external_id']; unset($row['external_id']); $row['enabled']=(bool)$row['enabled'];
            $extra=Contract::decode($row['extra_json']); $row['extra']=ArchiveView::publicExtras($extra); unset($row['extra_json']);
            $row['stale']=$latest==='' || $row['seen_run']!==$latest; unset($row['seen_run']);
            if($resource==='parties') {
                $row['phone_field']=$partyField;
                if(($row['extra']['party_phone_field']??'contact_phone')!==$partyField) {
                    $row['phone_masked']=''; $row['phone_state']='refresh';
                    if($partyField==='help_code') $row['code']=$row['id'];
                } else $row['phone_state']=$row['extra']['party_phone_state']??($row['phone_masked']===''?'empty':'available');
                if($archiveReader && $partyField==='help_code') {
                    $current=($extra['party_phone_field']??'')==='help_code' && array_key_exists(ArchiveView::PARTY_PHONE,$extra);
                    $row['phone_number']=$current?$extra[ArchiveView::PARTY_PHONE]:null;
                    if(!$current) $row['phone_state']='refresh';
                    unset($row['phone_masked']);
                }
            }
            $items[]=$row;
        }
        if($resource==='warehouses' && $items) {
            $owners=array_values(array_unique(array_filter(array_column(array_column($items,'extra'),'company_id'),'is_string')));
            $run=self::latestRun($site,$p['connection_key'],'companies'); $companies=[];
            if($run!=='' && $owners) {
                foreach(CatalogStore::query('companies')->where('site_id',$site)->where('connection_key',$p['connection_key'])
                    ->where('seen_run',$run)->where('enabled',1)->whereIn('external_id',$owners)->field('external_id,code,name')->select()->toArray() as $co)
                    $companies[$co['external_id']]=['id'=>$co['external_id'],'code'=>$co['code'],'name'=>$co['name']];
            }
            foreach($items as &$item) $item['company']=$companies[$item['extra']['company_id']??'']??null;
            unset($item);
        }
        return ['items'=>$items,'total'=>$total,'has_more'=>$page*$limit<$total,'next_cursor'=>$page*$limit<$total?(string)($page+1):null,'source'=>'local_archive'];
    }
    public static function latestRun(int $site,string $connection,string $resource): string
    {
        return (string)Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$connection)->where('resource',$resource)->where('status','completed')->order('id desc')->value('run_key');
    }
    public function tick(int $site,int $limit=10,?Transport $transport=null): int
    {
        [$config,$p]=$this->context($site);
        $ids=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('mode',$config['mode'])->whereIn('status',['queued','running','retry'])->where('next_at','<=',time())->order('id')->limit(min(10,max(1,$limit)))->column('id');
        $done=0; foreach($ids as $id) if($this->page($site,(int)$id,$transport)) $done++; return $done;
    }
    /** One explicit foreground page of an existing archive job. Never ticks business tasks or other sites. */
    public function readJobPage(int $site,int $id,string $resource,string $key,string $mode,string $cursor,?Transport $transport=null): array
    {
        [$c,$p]=$this->context($site);
        $this->assertConnection($p,$key);
        if(!isset(OpenApi::RESOURCES[$resource]) || !in_array($mode,['test','production'],true) || $c['mode']!==$mode || self::needsProbe($c,$p))
            throw new Problem('PREFLIGHT_REQUIRED','请在已核验的当前账套读取此项资料');
        DesktopConnectionService::endpoint($mode,$p['endpoint']);
        if($id<=0 || !preg_match('/^[1-9][0-9]{0,5}$/D',$cursor)) throw new Problem('SYNC_MISSING','任务或分页无效，请刷新');
        $q=Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'mode'=>$mode,'connection_key'=>$key,'resource'=>$resource]);
        $read=static fn()=>(clone $q)->field('id,status,cursor,pages,processed,next_at')->order('id desc')->find();
        $job=$read();
        if(!$job || (int)$job['id']!==$id) throw new Problem('SYNC_SUPERSEDED','此项已换到新一轮任务，请刷新');
        if((string)$job['cursor']===$cursor && in_array($job['status'],['queued','running','retry'],true)) $this->page($site,$id,$transport,$cursor);
        [$fresh,$current]=$this->context($site); $this->assertConnection($current,$key);
        $job=$read();
        if($fresh['mode']!==$mode || self::needsProbe($fresh,$current) || !$job || (int)$job['id']!==$id)
            throw new Problem('CONNECTION_CHANGED','账套或任务已改变，请刷新');
        return ['id'=>$id,'resource'=>$resource,'mode'=>$mode,'connection_key'=>$key,'status'=>$job['status'],'cursor'=>(string)$job['cursor'],
            'pages'=>(int)$job['pages'],'processed'=>(int)$job['processed'],'wait_seconds'=>max(0,(int)$job['next_at']-time())];
    }

    public function page(int $site,int $id,?Transport $transport=null,?string $expectedCursor=null): bool
    {
        [$config,$p]=$this->context($site); $lease=bin2hex(random_bytes(16));
        if(self::needsProbe($config,$p)) return false; // A rotated credential pauses a queued job rather than making an unverified request.
        $base=[['site_id','=',$site],['id','=',$id],['connection_key','=',$p['connection_key']],['mode','=',$config['mode']]];
        $claim=Db::name('hsx_boyuan_reference_sync')->where($base)->where('next_at','<=',time());
        if($expectedCursor!==null) $claim->where('cursor',$expectedCursor);
        $claimed=$claim->where(function($q): void {
            $q->whereIn('status',['queued','retry'])->whereOr(function($stale): void {$stale->where('status','running')->where('lease_until','<',time());});
        })->update(['status'=>'running','lease_token'=>$lease,'lease_until'=>time()+90,'update_at'=>time()]);
        if($claimed!==1) return false;
        $owned=array_merge($base,[['lease_token','=',$lease]]); $row=Db::name('hsx_boyuan_reference_sync')->where($owned)->find();
        if(!$row) return false;
        try {
            $scope=Contract::decode($row['scope_json']);
            if(self::obsoletePartyRun($row['resource'],$scope)) { self::stopObsoleteRun($owned); return true; }
            if((int)$row['pages']>=1000) throw new Problem('SYNC_PAGE_LIMIT','已达到1000页安全上限，请核对接口分页');
            if($row['resource']==='parties') $scope+=['party_phone_field'=>'contact_phone']; // Already-running pre-upgrade jobs keep their original rules.
            $reply=($transport??(new ConfigService())->transport($site,$p,$config['mode'],['kind'=>'reference_sync','sync_id'=>$id,'resource'=>$row['resource']]))->send('GET',$p['paths']['references'],['resource'=>$row['resource'],'cursor'=>$row['cursor'],'limit'=>100,'include_archive_view'=>true]+$scope);
            $data=$reply['data']??null;
            if(($reply['code']??'')!=='OK' || !is_array($data) || !is_array($data['items']??null) || !array_is_list($data['items']) || !is_bool($data['has_more']??null)) throw new Problem('REFERENCE_RESULT_INVALID','基础资料分页回执不完整');
            if(isset($data['total']) && (!is_int($data['total']) || $data['total']<0 || $data['total']>2147483647)) throw new Problem('REFERENCE_RESULT_INVALID','博远返回的总数无效，不能核验本轮完整性');
            $next=(string)($data['next_cursor']??'');
            if($data['has_more'] && (!$data['items'] || !preg_match('/^[1-9][0-9]{0,5}$/D',$next) || (int)$next<=(int)$row['cursor'])) throw new Problem('REFERENCE_CURSOR_INVALID','基础资料分页未前进，已停止同步');
            // Commit only under the same site lock as connection edits, opening and new sync requests.
            // Never hold this lock during the upstream HTTP call.
            Db::transaction(function() use($site,$id,$owned,$row,$data,$next,$p,$config): void {
                if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
                $fresh=(new ConfigService())->get($site,false); $current=$fresh['profiles'][$fresh['mode']];
                if($fresh['mode']!==$config['mode'] || $current['connection_key']!==$p['connection_key'] || self::needsProbe($fresh,$current)) throw new Problem('CONNECTION_CHANGED','连接已改变或需要重新核验，旧任务不写入新环境');
                if(!Db::name('hsx_boyuan_reference_sync')->where($owned)->lock(true)->find()) return;
                $seen=[]; $warnings=$row['warning'];
                foreach($data['items'] as $item) {
                    Contract::id($item['id']??null); Contract::id($item['code']??null);
                    if(isset($seen[$item['id']]) || !is_string($item['name']??null) || $item['name']==='' || mb_strlen($item['name'])>255 || !is_bool($item['enabled']??null)) throw new Problem('REFERENCE_RESULT_INVALID','基础资料身份、名称或启用状态异常');
                    $seen[$item['id']]=true;
                    if(!empty($item['extra']['source_name_missing'])) $warnings='部分档案未提供名称，已保留原记录，仅供核对';
                    $where=[['site_id','=',$site],['connection_key','=',$p['connection_key']],['external_id','=',$item['id']]];
                    $value=$this->archiveValues($site,$p['connection_key'],$row['resource'],$item,(int)$row['cursor'],$config['mode']!=='mock' && $p['protocol']==='openapi_v153')+['seen_run'=>$row['run_key']];
                    $prior=CatalogStore::query($row['resource'])->where($where)->field('external_id')->find();
                    if($prior && $prior['external_id']!==$item['id']) throw new Problem('REFERENCE_ID_CONFLICT','博远返回仅大小写或字符排序规则不同的编号，不能按同一档案覆盖');
                    if($prior) CatalogStore::query($row['resource'])->where($where)->update($value);
                    else CatalogStore::query($row['resource'])->insert($value+['site_id'=>$site,'connection_key'=>$p['connection_key'],'external_id'=>$item['id']]);
                }
                $more=$data['has_more']; $state=$more?'queued':'completed';
                $reason='';
                $reported=$data['total']??(int)$row['reported_total']; $processed=(int)$row['processed']+count($data['items']);
                if(isset($data['total']) && (int)$row['reported_total']>=0 && $data['total']!==(int)$row['reported_total']) {
                    $state='needs_review'; $reason='博远分页总数发生变化，请重新同步本类后核对';
                    if($row['resource']==='categories') {
                        $received=CatalogStore::query('categories')->where(['site_id'=>$site,'connection_key'=>$p['connection_key'],'seen_run'=>$row['run_key']])->count();
                        $reported=(int)$row['reported_total'];
                        $reason='博远前一页报告 '.$reported.' 条，本页报告 '.$data['total'].' 条。本轮收到 '.$received.' 个不同分类，未移除原有分类。';
                    }
                } elseif(!$data['has_more']) {
                    $received=CatalogStore::query($row['resource'])->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('seen_run',$row['run_key'])->count();
                    // A short nominal last page is not proof of deletion. Check one further page in this same read-only job.
                    if($row['resource']==='categories' && $reported>$received && count($data['items'])>0 && $received===$processed
                        && (int)$row['cursor']===(int)ceil($reported/100)) {
                        $more=true; $next=(string)((int)$row['cursor']+1); $state='queued';
                    }
                    elseif($reported>=0 && $received!==$reported) {$state='needs_review'; $reason='博远声明总数'.$reported.'，本轮收到唯一档案'.$received.'；已保存有效资料，完整性待博远核对';}
                    elseif($processed!==$received) {$state='needs_review'; $reason='博远分页出现重复编号，本轮收到'.$processed.'行、唯一档案'.$received.'条，请重新同步本类后核对';}
                }
                Db::name('hsx_boyuan_reference_sync')->where($owned)->update(['status'=>$state,'cursor'=>$more?$next:$row['cursor'],'processed'=>$processed,
                    'pages'=>(int)$row['pages']+1,'reported_total'=>$reported,'attempts'=>0,'next_at'=>time()+1,'lease_token'=>'','lease_until'=>0,'reason'=>$reason,'warning'=>$warnings,'update_at'=>time()]);
                TaskService::audit($site,0,$p['connection_key'],'reference_page',$state,OpenApi::RESOURCES[$row['resource']][0].'：第'.$row['cursor'].'页已保存'.($state==='completed'?'，本轮拉取完成':''),0,['sync_id'=>$id,'cursor'=>$row['cursor'],'rows'=>count($data['items'])]);
            });
        } catch(\Throwable $e) {
            $attempt=(int)$row['attempts']+1; $state=$attempt>=3?'failed':'retry';
            $reason=$e instanceof Problem?$e->getMessage():'资料同步未完成，请检查插件服务日志';
            Db::name('hsx_boyuan_reference_sync')->where($owned)->update(['status'=>$state,'attempts'=>$attempt,'reason'=>mb_substr($reason,0,1000),'next_at'=>time()+30*$attempt,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
            TaskService::audit($site,0,$p['connection_key'],'reference_page',$state,$reason,0,['sync_id'=>$id,'cursor'=>$row['cursor'],'attempt'=>$attempt]);
        }
        return true;
    }
}
