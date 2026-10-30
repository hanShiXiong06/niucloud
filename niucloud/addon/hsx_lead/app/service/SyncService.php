<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\provider\{ProviderRegistry,ProviderException};
use addon\hsx_lead\app\support\{Data,Cipher};
use core\exception\CommonException;
use think\facade\Db;

/** Persistent one-page jobs: HTTP happens outside the DB transaction; only complete generations publish. */
final class SyncService
{
    private ProviderRegistry $providers;
    public function __construct(?ProviderRegistry $providers=null) { $this->providers=$providers??new ProviderRegistry(); }

    public function start(int $site,int $merchantId,int $uid=0,string $trigger='manual'): array
    {
        $runId=Db::transaction(function()use($site,$merchantId,$uid,$trigger){
            $merchant=Data::row('merchant',$site,$merchantId);
            if ((int)$merchant['status']!==1) throw new CommonException('商户已停用，不能同步');
            $conn=Data::query('connection',$site)->where('merchant_id',$merchantId)->lock(true)->find();
            if (!$conn || empty($conn['credentials_cipher'])) throw new CommonException('请先保存该商户的ERP账号配置');
            if ($trigger==='scheduled' && !(int)$conn['enabled']) throw new CommonException('自动同步已关闭，本轮不创建计划任务');
            if ((int)$conn['active_run_id']>0) {
                $active=Data::query('sync_run',$site)->where('id',$conn['active_run_id'])->find();
                if ($active && in_array($active['status'],['pending','running'],true) && (int)$active['config_version']===(int)$conn['config_version']) return (int)$active['id'];
            }
            $id=(int)Data::query('sync_run',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$merchantId,
                'connection_id'=>$conn['id'],'config_version'=>$conn['config_version'],'trigger_type'=>$trigger,
                'status'=>'pending','cursor_json'=>Data::json(['page'=>1]),'summary_json'=>Data::json(['stores'=>[],'tenants'=>[]]),
                'create_time'=>time(),'update_time'=>time()]);
            Data::query('connection',$site)->where('id',$conn['id'])->update(['active_run_id'=>$id,'last_sync_status'=>'pending',
                'last_sync_message'=>'等待读取第一页','next_sync_at'=>time()+(int)$conn['sync_interval_minutes']*60,
                'lease_token'=>'','lease_until'=>0,'update_time'=>time()]);
            Data::audit($site,$uid,0,'sync_run',$id,'started',['merchant_id'=>$merchantId,'trigger'=>$trigger]);
            return $id;
        });
        return $this->detail($site,$runId);
    }

    public function detail(int $site,int $runId): array
    {
        $run=Data::row('sync_run',$site,$runId);
        $run['run_id']=$run['id'];
        $run['message']=$run['error_message']?:match($run['status']){
            'completed'=>'完整库存已发布，实际可售仍需门店确认',
            'pending'=>'任务已创建，等待分批读取',
            'running'=>'已读取'.$run['page_count'].'页、'.$run['record_count'].'条，分页尚未结束',
            'cancelled'=>'配置已变更，本轮未发布，请重新同步',
            default=>'本轮未发布，保留上一批完整库存',
        };
        $run['progress_known']=false;
        $run['scheduler_required']=$run['status']!=='completed';
        unset($run['cursor_json'],$run['summary_json']);
        return $run;
    }

    public function advance(int $site,int $runId): array
    {
        $lease=bin2hex(random_bytes(16));
        $claim=Db::transaction(function()use($site,$runId,$lease){
            $run=Data::row('sync_run',$site,$runId);
            $conn=Data::row('connection',$site,(int)$run['connection_id'],true);
            $run=Data::row('sync_run',$site,$runId,true);
            if (!in_array($run['status'],['pending','running'],true)) return null;
            if ((int)$conn['active_run_id']!==$runId || (int)$conn['config_version']!==(int)$run['config_version']) {
                Data::query('sync_run',$site)->where('id',$runId)->update(['status'=>'cancelled','finished_at'=>time(),'update_time'=>time()]);
                return null;
            }
            if ((int)$conn['lease_until']>time() && $conn['lease_token']!=='') return null;
            if ((int)Data::row('merchant',$site,(int)$run['merchant_id'])['status']!==1) {
                Data::query('sync_run',$site)->where('id',$runId)->update(['status'=>'cancelled','error_message'=>'商户已暂停，本轮未发布','finished_at'=>time(),'update_time'=>time()]);
                Data::query('connection',$site)->where('id',$conn['id'])->update(['active_run_id'=>0,'lease_token'=>'','lease_until'=>0,
                    'last_sync_status'=>'cancelled','last_sync_message'=>'商户已暂停，本轮未发布','update_time'=>time()]);
                return null;
            }
            Data::query('connection',$site)->where('id',$conn['id'])->update(['lease_token'=>$lease,'lease_until'=>time()+180,'last_sync_status'=>'running','update_time'=>time()]);
            Data::query('sync_run',$site)->where('id',$runId)->update(['status'=>'running','started_at'=>$run['started_at']?:time(),'update_time'=>time()]);
            return [$conn,$run];
        });
        if (!$claim) return $this->detail($site,$runId);
        [$conn,$run]=$claim;
        try {
            $credentials=Cipher::decrypt((string)$conn['credentials_cipher'],$site,(int)$conn['id']);
            $provider=$this->providers->get((string)$conn['provider']);
            $definition=$this->providers->definition((string)$conn['provider']);
            $scope=['tenant_id'=>$conn['expected_tenant_id']?:null,'store_id'=>$conn['store_id']?:null,'page_size'=>100];
            $cursor=Data::decode($run['cursor_json']);
            if ((int)($cursor['page']??1)>100) throw new CommonException('超过100页安全上限，本轮未发布；请联系管理员缩小范围');
            $tenantHint=null;
            if ($definition->loginRequired($credentials,(int)$run['page_count'])) {
                $login=$provider->login($credentials);
                $credentials['access_token']=$login['access_token'];
                $credentials['expires_at']=$login['expires_at']??0;
                $tenantHint=$login['identity']['tenant_id']??null;
            }
            if ($tenantHint!==null) {
                if ($scope['tenant_id']!==null && (string)$scope['tenant_id']!==(string)$tenantHint) throw new CommonException('登录账号与已绑定租户不一致，请核对ERP账号');
                $scope['tenant_id']=$scope['tenant_id']??(string)$tenantHint;
            }
            try { $page=$provider->fetchPage($credentials,$cursor,$scope); }
            catch(ProviderException $error) {
                if (!$definition->renewable() || $error->reason()!=='auth_required') throw $error;
                // At most one renewal for an expired token; never loop failed passwords.
                $login=$provider->login($credentials);
                $credentials['access_token']=$login['access_token']; $credentials['expires_at']=$login['expires_at']??0;
                $renewedTenant=$login['identity']['tenant_id']??null;
                if ($renewedTenant!==null) {
                    if ($scope['tenant_id']!==null && (string)$scope['tenant_id']!==(string)$renewedTenant) {
                        throw new CommonException('刷新授权后的账号与绑定租户不一致，本轮未发布');
                    }
                    $scope['tenant_id']=$scope['tenant_id']??(string)$renewedTenant;
                }
                $page=$provider->fetchPage($credentials,$cursor,$scope);
            }
            $summary=Data::decode($run['summary_json']);
            $stores=$summary['stores']??[]; $tenants=$summary['tenants']??[]; $rows=[]; $keys=[];
            foreach($page['rows'] as $source) {
                $item=$provider->normalize($source,['connection_id'=>(string)$conn['id'],'tenant_id'=>$scope['tenant_id'],
                    'store_id'=>$scope['store_id'],'fetched_at'=>gmdate('Y-m-d\TH:i:s\Z')]);
                $stores[]=(string)$item['source_store_id'];
                if ($item['tenant_id']!==null && $item['tenant_id']!=='') $tenants[]=(string)$item['tenant_id'];
                $stores=array_values(array_unique($stores)); $tenants=array_values(array_unique($tenants));
                if (count($tenants)>1) throw new CommonException('库存响应跨租户，本轮未发布');
                if ($conn['store_id']==='' && count($stores)>1) throw new CommonException('账号包含多个来源门店，请在高级设置填写来源门店ID后重新同步');
                $key=hash('sha256',$item['source_key']);
                if (isset($keys[$key]) || Data::query('inventory',$site)->where('connection_id',$conn['id'])->where('sync_run_id',$runId)->where('source_key',$key)->count()) {
                    throw new CommonException('分页出现重复设备，本轮未发布，请稍后重新同步');
                }
                $keys[$key]=true;
                $raw=$item['safe_snapshot'];
                $grade=$definition->grade($raw);
                $rows[]=['site_id'=>$site,'merchant_id'=>$conn['merchant_id'],'connection_id'=>$conn['id'],'sync_run_id'=>$runId,
                    'source_key'=>$key,'source_id'=>$item['source_id'],'source_store_id'=>$item['source_store_id'],'tenant_id'=>$item['tenant_id']??'',
                    'model'=>$item['title']??'','brand'=>$item['brand']??'','memory'=>$item['capacity']??'','color'=>$item['color']??'',
                    'grade'=>is_scalar($grade)?mb_substr((string)$grade,0,100):'','price'=>$item['retail_price'],'trade_price'=>$item['trade_price'],
                    'imei'=>$item['imei']??'','cover'=>$item['cover']??'','images_json'=>Data::json($item['images']),
                    'source_status_json'=>Data::json($item['source_status']),'snapshot_json'=>Data::json($raw),
                    'availability'=>'unknown','fetched_at'=>time(),'create_time'=>time(),'update_time'=>time()];
            }
            Db::transaction(function()use($site,$runId,$lease,$conn,$run,$page,$rows,$credentials,$stores,$tenants){
                $current=Data::row('connection',$site,(int)$conn['id'],true);
                if ($current['lease_token']!==$lease || (int)$current['active_run_id']!==$runId || (int)$current['config_version']!==(int)$run['config_version']) {
                    throw new CommonException('配置或执行权已变更，本轮数据未发布');
                }
                if ($rows) Data::query('inventory',$site)->insertAll($rows);
                $done=!$page['has_more'];
                Data::query('sync_run',$site)->where('id',$runId)->update(['status'=>$done?'completed':'running',
                    'cursor_json'=>Data::json($page['cursor']),'summary_json'=>Data::json(['stores'=>$stores,'tenants'=>$tenants]),
                    'page_count'=>(int)$run['page_count']+1,'record_count'=>(int)$run['record_count']+count($rows),
                    'finished_at'=>$done?time():0,'update_time'=>time()]);
                $changes=['lease_token'=>'','lease_until'=>0,'credentials_cipher'=>Cipher::encrypt($credentials,$site,(int)$conn['id']),
                    'last_sync_status'=>$done?'completed':'running','last_sync_message'=>$done?'库存完整同步成功':'已读取部分分页，继续处理中','update_time'=>time()];
                if ($done) {
                    $changes+=['published_run_id'=>$runId,'active_run_id'=>0,'last_sync_at'=>time(),
                        'next_sync_at'=>time()+(int)$current['sync_interval_minutes']*60];
                    if ($current['expected_tenant_id']==='' && count($tenants)===1) $changes['expected_tenant_id']=$tenants[0];
                    if ($current['store_id']==='' && count($stores)===1) $changes['store_id']=$stores[0];
                    Data::audit($site,0,0,'sync_run',$runId,'published',['record_count'=>(int)$run['record_count']+count($rows)]);
                }
                Data::query('connection',$site)->where('id',$conn['id'])->update($changes);
            });
        } catch(\Throwable $error) {
            $reason=$error instanceof ProviderException?$error->reason():'sync_failed';
            $message=$error instanceof ProviderException || $error instanceof CommonException ? $error->getMessage() : '同步执行失败，本轮未发布；请管理员核对服务日志与配置';
            $this->fail($site,$runId,(int)$conn['id'],$lease,$reason,$message);
        }
        return $this->detail($site,$runId);
    }

    private function fail(int $site,int $runId,int $connection,string $lease,string $code,string $message): void
    {
        Db::transaction(function()use($site,$runId,$connection,$lease,$code,$message){
            $conn=Data::row('connection',$site,$connection,true);
            if ($conn['lease_token']!==$lease || (int)$conn['active_run_id']!==$runId) return;
            Data::query('sync_run',$site)->where('id',$runId)->update(['status'=>'failed','error_code'=>mb_substr($code,0,80),
                'error_message'=>mb_substr($message,0,1000),'finished_at'=>time(),'update_time'=>time()]);
            Data::query('connection',$site)->where('id',$connection)->update(['active_run_id'=>0,'lease_token'=>'','lease_until'=>0,
                'last_sync_status'=>'failed','last_sync_message'=>mb_substr($message,0,1000),
                'next_sync_at'=>time()+(int)$conn['sync_interval_minutes']*60,'update_time'=>time()]);
            Data::audit($site,0,0,'sync_run',$runId,'failed',['reason'=>$code]);
        });
    }

    public function tick(int $limit=5): array
    {
        $began=microtime(true); $results=[];
        $connections=Db::name('hsx_lead_connection')->where(function($q){
            $q->where('active_run_id','>',0)->whereOr(function($q){$q->where('enabled',1)->where('next_sync_at','<=',time());});
        })->where('lease_until','<=',time())->order('next_sync_at')->limit(max(1,min(20,$limit)))->select()->toArray();
        foreach($connections as $conn) {
            if (microtime(true)-$began>25) break;
            try {
                $site=(int)$conn['site_id'];
                $run=(int)$conn['active_run_id']>0?$this->detail($site,(int)$conn['active_run_id']):$this->start($site,(int)$conn['merchant_id'],0,'scheduled');
                $results[]=$this->advance($site,(int)$run['run_id']);
                WorkflowService::expireAssigned($site);
            } catch(\Throwable $error) {
                // Only safe IDs are returned. A broken connection must not expose credentials or stop other sites.
                $results[]=['connection_id'=>(int)$conn['id'],'status'=>'failed','message'=>'配置或任务不可执行，请管理员核对'];
            }
        }
        $sites=Db::name('hsx_lead_task')->where('status','assigned')->where('response_deadline','<',time())->group('site_id')->limit(20)->column('site_id');
        foreach($sites as $site) WorkflowService::expireAssigned((int)$site);
        return $results;
    }
}
