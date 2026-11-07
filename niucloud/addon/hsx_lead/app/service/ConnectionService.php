<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\provider\{ProviderRegistry,ProviderDefinition};
use addon\hsx_lead\app\support\{Data,Cipher,SyncStatus};
use core\exception\CommonException;
use think\facade\{Db,Cache};

/** One verified primary ERP connection per merchant. Probe never changes live configuration. */
final class ConnectionService
{
    private const PROBE_TTL=600;
    private const RECENT_RUN_LIMIT=10;
    private ProviderRegistry $providers;
    public function __construct(private Context $context,?ProviderRegistry $providers=null)
    {
        $this->providers=$providers??new ProviderRegistry();
    }

    public function get(int $merchantId): array
    {
        $this->merchant($merchantId);
        $conn=$this->connection($merchantId);
        $result=['merchant_id'=>$merchantId,'connected'=>false,'connection_id'=>0,'provider'=>'','provider_name'=>'',
            'credentials'=>[],'configured_fields'=>[],'scope'=>['tenant_id'=>'','store_id'=>''],'enabled'=>false,'interval_minutes'=>60,
            'config_revision'=>0,'last_sync_status'=>'idle','last_sync_at'=>0,'last_sync_message'=>'尚未接入ERP',
            'active_run_id'=>0,'published_run_id'=>0,'next_sync_at'=>0,'requires_initial_sync'=>false,'authorization_attention'=>false,
            'auth_renewal'=>['automatic'=>false,'ready'=>false,'mode'=>'','status'=>'credentials_required','message'=>'尚未接入ERP，请先保存并验证登录资料。'],
            'channel_enabled'=>true,'channel_message'=>'','sync_progress'=>null,'requires_sync_restart'=>false,
            'recent_runs'=>[],'recent_runs_total'=>0,'recent_runs_limit'=>self::RECENT_RUN_LIMIT,'recent_runs_has_more'=>false];
        if (!$conn) return $result;
        $definition=$this->providers->definition((string)$conn['provider']);
        $channelEnabled=(new ProviderChannelService($this->context->site))->enabled($definition->key());
        $result=array_replace($result,['connected'=>true,'connection_id'=>(int)$conn['id'],'provider'=>$definition->key(),
            'channel_enabled'=>$channelEnabled,'channel_message'=>$channelEnabled?'':'本站已关闭该ERP渠道，授权及采集暂停，账号与历史库存仍保留。',
            'provider_name'=>(new ProviderChannelService($this->context->site))->displayName($definition->key()),'scope'=>['tenant_id'=>(string)$conn['expected_tenant_id'],'store_id'=>(string)$conn['store_id']],
            'enabled'=>(bool)$conn['enabled'],'interval_minutes'=>(int)$conn['sync_interval_minutes'],'config_revision'=>(int)$conn['config_version'],
            'last_sync_status'=>(string)$conn['last_sync_status'],'last_sync_at'=>(int)$conn['last_sync_at'],'last_sync_message'=>'',
            'active_run_id'=>(int)$conn['active_run_id'],'published_run_id'=>(int)$conn['published_run_id'],'next_sync_at'=>(int)$conn['next_sync_at'],'requires_initial_sync'=>(int)$conn['published_run_id']===0]);
        $result=array_replace($result,SyncStatus::connection($this->context->site,$conn));
        $result=array_replace($result,$this->recentRuns($merchantId,$conn,$channelEnabled));
        try {
            $credentials=Cipher::decrypt((string)$conn['credentials_cipher'],$this->context->site,(int)$conn['id']);
            $result=array_replace($result,$definition->publicCredentials($credentials));
            $result['auth_renewal']=$definition->renewalStatus($credentials);
            $expires=(int)($credentials['expires_at']??0);
            // Renewable adapters handle routine token expiry with their already verified
            // credentials; only a non-renewable token needs manual reauthorization.
            $result['authorization_attention']=!$definition->renewable($credentials) && $expires>0 && $expires<=time();
        } catch(\Throwable $error) {
            $result['authorization_attention']=true;
            $result['credential_error']='凭据无法读取，请核对部署密钥后重新探测授权';
            $result['auth_renewal']=['automatic'=>false,'ready'=>false,'mode'=>'','status'=>'credential_error','message'=>'登录资料无法解密，未尝试自动登录；请核对部署密钥后重新验证。'];
        }
        return $result;
    }

    /** Existing persisted runs only. This read never logs in, advances a task or exposes provider payloads. */
    private function recentRuns(int $merchantId,array $connection,bool $channelEnabled): array
    {
        $query=Data::query('sync_run',$this->context->site)->where('merchant_id',$merchantId)->where('connection_id',(int)$connection['id']);
        $total=(int)(clone $query)->count();
        $rows=$query->field('id,config_version,status,trigger_type,page_count,record_count,started_at,finished_at,error_code,cursor_json,create_time,update_time')
            ->order('id desc')->limit(self::RECENT_RUN_LIMIT)->select()->toArray();
        $labels=['pending'=>'等待同步','running'=>'同步中','completed'=>'已完成','failed'=>'未完成','cancelled'=>'已取消',
            'interrupted'=>'已中断','paused'=>'已暂停','unknown'=>'状态待核对'];
        $triggers=['manual'=>'后台手动','merchant'=>'门店手动','scheduled'=>'自动同步','unknown'=>'来源未记录'];
        // Do not echo stored error_message: old provider text can contain account details.
        $errors=[
            'auth_required'=>'ERP 登录已失效，请重新验证账号后再同步',
            'invalid_credentials'=>'ERP 登录资料未通过校验，请核对账号与授权',
            'http_error'=>'ERP 暂时没有正常响应，请稍后重试',
            'schema_changed'=>'ERP 返回的数据暂时无法完整识别，本轮未更新库存，请联系平台核对',
            'scope_mismatch'=>'ERP 返回的门店与绑定范围不一致，本轮未更新库存，请重新核对授权',
            'invalid_scope'=>'本轮查询范围未通过校验，请核对门店范围后重新同步',
            'response_too_large'=>'ERP 单次返回资料超过处理上限，请联系平台核对',
            'generation_expired'=>'本轮同步间隔过久，暂存资料已失效，请重新完整同步',
            'unsupported_environment'=>'当前同步服务缺少必要能力，请联系平台处理',
            'forbidden_endpoint'=>'ERP 接口配置未通过校验，请联系平台处理',
            'sync_failed'=>'本轮同步未完成，未更新库存，请重试或联系平台核对',
        ];
        $runs=[];
        foreach($rows as $row) {
            $storedStatus=isset($labels[$row['status']])?(string)$row['status']:'unknown';
            $status=$storedStatus;
            $currentConfig=(int)$row['config_version']===(int)$connection['config_version'];
            if (in_array($status,['pending','running'],true)) {
                if ((int)$row['id']!==(int)$connection['active_run_id']||!$currentConfig) $status='interrupted';
                elseif (!$channelEnabled) $status='paused';
            }
            $trigger=isset($triggers[$row['trigger_type']])?(string)$row['trigger_type']:'unknown';
            $progress=SyncStatus::progress(array_replace($row,['status'=>$status]));
            $error='';$message='';
            if ($status==='failed'||$status==='cancelled') {
                $raw=(string)($row['error_code']??'');
                $error=isset($errors[$raw])?$raw:($status==='failed'?'sync_failed':'');
                $message=$error!==''?$errors[$error]:'本轮同步已取消，未更新库存';
            }
            elseif ($status==='interrupted') $message='本轮同步已中断，未更新库存；请重新完整同步';
            elseif ($status==='paused') $message='本站已关闭该 ERP 渠道，本轮同步暂停，已有资料保留';
            $runs[]=[
                'id'=>(int)$row['id'],'status'=>$status,'stored_status'=>$storedStatus,'status_label'=>$labels[$status],
                'config_revision'=>(int)$row['config_version'],'is_current_configuration'=>$currentConfig,
                'trigger_type'=>$trigger,'trigger_label'=>$triggers[$trigger],
                'page_count'=>max(0,(int)$row['page_count']),'record_count'=>$progress['record_count'],
                'total_records'=>$progress['total_records'],'progress_known'=>$progress['progress_known'],
                'progress_percent'=>$progress['progress_percent'],'complete'=>$progress['complete'],
                'is_current_snapshot'=>$status==='completed'&&(int)($connection['published_run_id']??0)===(int)$row['id'],
                'started_at'=>max(0,(int)$row['started_at']),'finished_at'=>max(0,(int)$row['finished_at']),
                'create_time'=>max(0,(int)$row['create_time']),'update_time'=>max(0,(int)$row['update_time']),
                'error_code'=>$error,'error_message'=>$message,'message'=>$message!==''?$message:$progress['message'],
            ];
        }
        return ['recent_runs'=>$runs,'recent_runs_total'=>$total,'recent_runs_limit'=>self::RECENT_RUN_LIMIT,
            'recent_runs_has_more'=>$total>count($runs)];
    }

    public function probe(int $merchantId,#[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $merchant=$this->merchant($merchantId);
        if ((int)$merchant['status']!==1) throw new CommonException('请先启用商户再探测ERP连接');
        $conn=$this->connection($merchantId);
        $revision=$this->revision($conn);
        $definition=$this->providers->definition(Data::text($input,'provider',60,true));
        (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
        $saved=[];
        if ($conn && $conn['provider']===$definition->key()) {
            try { $saved=Cipher::decrypt((string)$conn['credentials_cipher'],$this->context->site,(int)$conn['id']); }
            catch(\Throwable $ignored) { /* A complete fresh authorization can replace an unreadable old cipher. */ }
        }
        if (!is_array($input['credentials']??null) || !is_array($input['scope']??[])) throw new CommonException('ERP字段与范围格式不正确');
        if (isset($input['authorization'])) {
            if (!is_array($input['authorization'])) throw new CommonException('登录验证资料格式不正确');
            $authorized=(new AuthChallengeService($this->context,$this->providers))->authorize(
                $merchantId,$definition->key(),$input['credentials'],$input['authorization']);
            if (($authorized['status']??null)==='choose_company') {
                $public=is_array($authorized['public']??null)?$authorized['public']:$authorized;
                return ['authorization_required'=>true,'status'=>'choose_company']
                    + array_intersect_key($public,array_flip(['challenge_token','expires_at','companies']));
            }
            if (($authorized['status']??null)!=='authorized' || !is_array($authorized['credentials']??null)) {
                throw new CommonException('ERP登录未完成，请重新获取验证码');
            }
            // Interactive secrets are transient; only explicitly declared permanent fields survive.
            $credentials=$definition->credentials($authorized['credentials']);
        } else {
            $credentials=$definition->credentials($input['credentials'],$saved);
        }
        $scope=$definition->scope($input['scope']??[],$credentials);
        $provider=$this->providers->get($definition->key());
        $login=$provider->login($credentials);
        $credentials['access_token']=$login['access_token'];
        $credentials['expires_at']=$login['expires_at']??0;
        $hint=$login['identity']['tenant_id']??null;
        if ($hint!==null) {
            if ($scope['tenant_id']!=='' && (string)$hint!==$scope['tenant_id']) throw new CommonException('登录账号与填写的租户不一致，请核对后再探测');
            if ($scope['tenant_id']==='') $scope['tenant_id']=(string)$hint;
        }
        $verified=null;
        if ($definition->verifiesIdentity() && ($login['identity']['verified']??false)===true) {
            $identity=$login['identity'];
            $verified=['tenant_id'=>Data::text($identity,'tenant_id',100,$definition->verifiedTenantRequired()),
                'store_id'=>Data::text($identity,'store_id',100,true),'name'=>Data::text($identity,'store_name',120)];
            if ($scope['store_id']!=='' && $scope['store_id']!==$verified['store_id']) {
                throw new CommonException('填写的来源范围与当前认证账号不一致，未保存连接');
            }
            $scope['store_id']=$verified['store_id'];
        }
        (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
        $page=$provider->fetchPage($credentials,['page'=>1],['tenant_id'=>$scope['tenant_id']?:null,'store_id'=>$scope['store_id']?:null,'page_size'=>100]);
        $stores=[]; $tenants=[]; $items=[];
        if ($verified!==null) {
            $stores[$verified['store_id']]=['store_id'=>$verified['store_id'],'name'=>$verified['name']];
            if ($verified['tenant_id']!=='') $tenants[$verified['tenant_id']]=true;
        }
        foreach($page['rows'] as $source) {
            $item=$provider->normalize($source,['connection_id'=>'probe-'.$merchantId,'tenant_id'=>$scope['tenant_id']?:null,
                'store_id'=>$scope['store_id']?:null,'fetched_at'=>gmdate('Y-m-d\TH:i:s\Z')]);
            $id=(string)$item['source_store_id'];
            $stores[$id]=['store_id'=>$id,'name'=>$stores[$id]['name']??$definition->storeName($item['safe_snapshot'])];
            if ($item['tenant_id']!==null && $item['tenant_id']!=='') $tenants[(string)$item['tenant_id']]=true;
            if (count($items)<5) $items[]=['title'=>$item['title'],'brand'=>$item['brand'],'price'=>$item['retail_price'],'store_id'=>$id];
        }
        if (count($tenants)>1) throw new CommonException('探测库存跨租户，未保存连接');
        if (count($tenants)===1) $scope['tenant_id']=(string)array_key_first($tenants);
        if ($scope['store_id']==='' && count($stores)===1) $scope['store_id']=(string)array_key_first($stores);
        // Only opt-in authenticated profile adapters can prove an empty source. JWT claims remain hints.
        $canActivate=$stores!==[];
        $preview=['identity'=>['tenant_id'=>$scope['tenant_id']?:null,'store_id'=>$scope['store_id']?:null,
                'name'=>$verified['name']??null,
                'basis'=>$verified!==null?'authenticated_profile_and_first_page':'first_page_read_only_list','scope_checked'=>$canActivate],
            'stores'=>array_values($stores),'first_page_count'=>count($page['rows']),'has_more'=>(bool)$page['has_more'],
            'items'=>$items,'requires_store_selection'=>count($stores)>1 && $scope['store_id']==='',
            'empty_inventory'=>$page['rows']===[] && !$page['has_more'],'can_activate'=>$canActivate,
            'note'=>isset($page['note']) && is_string($page['note']) ? mb_substr($page['note'],0,500) : ($verified!==null && $page['rows']===[] && !$page['has_more']
                ? '来源身份已核验，当前筛选范围暂无库存；可启用接入，完整同步为空不会生成虚假商品。'
                : '只预览第一页，不是完整门店或库存清单；未核验地区、可售数量，也没有锁货')];
        if ($this->revision($this->connection($merchantId))!==$revision) throw new CommonException('探测期间连接配置已变化，请重新探测');
        $token=bin2hex(random_bytes(32)); $expires=time()+self::PROBE_TTL;
        $payload=['site'=>$this->context->site,'merchant_id'=>$merchantId,'uid'=>$this->context->uid,'member_id'=>$this->context->memberId,'revision'=>$revision,
            'provider'=>$definition->key(),'credentials'=>$credentials,'credentials_fingerprint'=>$this->fingerprint($credentials),
            'identity_fingerprint'=>$definition->identityFingerprint($credentials),'scope'=>$scope,'stores'=>array_keys($stores),
            'expires_at'=>$expires,'can_activate'=>$canActivate];
        (new ProviderChannelService($this->context->site))->assertEnabled($definition->key());
        if (!Cache::set($this->cacheKey($token),Cipher::encrypt($payload,$this->context->site,-$merchantId),self::PROBE_TTL)) {
            throw new CommonException('无法保存短期校验凭证，请检查缓存配置后重试');
        }
        return ['probe_token'=>$token,'expires_at'=>$expires,'preview'=>$preview,'impact'=>$this->impact($conn,$definition,$credentials,$scope)];
    }

    public function activate(int $merchantId,#[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->merchant($merchantId);
        if (($input['confirm_switch']??null)!==true && ($input['confirm_switch']??null)!==1 && ($input['confirm_switch']??null)!=='1') {
            throw new CommonException('请先阅读接入影响并明确确认激活');
        }
        $token=Data::secret($input,'probe_token',64);
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new CommonException('校验凭证无效，请重新探测');
        $cipher=Cache::get($this->cacheKey($token));
        if (!is_string($cipher) || $cipher==='') throw new CommonException('校验凭证已过期或已使用，请重新探测');
        $proof=Cipher::decrypt($cipher,$this->context->site,-$merchantId);
        if (($proof['site']??0)!==$this->context->site || ($proof['merchant_id']??0)!==$merchantId || ($proof['uid']??0)!==$this->context->uid || (int)($proof['member_id']??0)!==$this->context->memberId
            || (int)($proof['expires_at']??0)<=time() || !is_array($proof['credentials']??null)
            || !hash_equals((string)($proof['credentials_fingerprint']??''),$this->fingerprint($proof['credentials']))) {
            throw new CommonException('校验凭证不属于当前操作或已过期，请重新探测');
        }
        if (empty($proof['can_activate'])) throw new CommonException('没有可核验的门店库存，暂不激活；旧连接保持不变，请有库存后重新探测');
        $scope=$proof['scope'];
        $selected=Data::text($input,'store_id',100);
        if ($scope['store_id']==='' || ($selected!=='' && $selected!==$scope['store_id'])) {
            throw new CommonException('请选择来源门店后重新探测该范围，再使用新凭证激活；激活不能临时更换范围');
        }
        if ($scope['store_id']==='' || !in_array((string)$scope['store_id'],array_map('strval',$proof['stores']),true)) {
            throw new CommonException('请从本次探测观察到的来源门店中明确选择一个');
        }
        $definition=$this->providers->definition((string)$proof['provider']);
        $impact=Db::transaction(function()use($merchantId,$proof,$scope,$definition){
            (new ProviderChannelService($this->context->site))->assertEnabled($definition->key(),true);
            $merchant=$this->context->connectionMerchant($merchantId,true);
            if ((int)$merchant['status']!==1) throw new CommonException('商户已停用，不能激活连接');
            $conn=Data::query('connection',$this->context->site)->where('merchant_id',$merchantId)->lock(true)->find();
            if ($this->revision($conn?:null)!==$proof['revision']) throw new CommonException('当前连接已变化，校验凭证失效，请重新探测');
            $impact=$this->impact($conn?:null,$definition,$proof['credentials'],$scope);
            if (!$conn) {
                $id=(int)Data::query('connection',$this->context->site)->insertGetId(['site_id'=>$this->context->site,
                    'merchant_id'=>$merchantId,'provider'=>$definition->key(),'name'=>'商户库存连接','create_time'=>time(),'update_time'=>time()]);
                $conn=Data::row('connection',$this->context->site,$id,true);
            }
            if ((int)$conn['active_run_id']>0) Data::query('sync_run',$this->context->site)->where('id',$conn['active_run_id'])
                ->whereIn('status',['pending','running'])->update(['status'=>'cancelled','error_message'=>'授权已重新验证并激活，请使用新配置同步','finished_at'=>time(),'update_time'=>time()]);
            Data::query('connection',$this->context->site)->where('id',$conn['id'])->update([
                'provider'=>$definition->key(),'credentials_cipher'=>Cipher::encrypt($proof['credentials'],$this->context->site,(int)$conn['id']),
                'expected_tenant_id'=>$scope['tenant_id'],'store_id'=>$scope['store_id'],'config_version'=>(int)$conn['config_version']+1,
                'active_run_id'=>0,'lease_token'=>'','lease_until'=>0,'next_sync_at'=>time(),
                'published_run_id'=>$impact['switching']?0:(int)$conn['published_run_id'],
                'last_sync_at'=>$impact['switching']?0:(int)$conn['last_sync_at'],'last_sync_status'=>'idle',
                'last_sync_message'=>$impact['switching']?'接入账号或范围已切换，旧快照暂不参与匹配，请完成首次同步':'授权已验证，请继续完整同步；保留上一批完整库存',
                'update_time'=>time()]);
            $this->context->audit('connection',(int)$conn['id'],'activated',['provider'=>$definition->key(),'switched'=>$impact['switching']]);
            return $impact;
        });
        try { Cache::delete($this->cacheKey($token)); } catch(\Throwable $ignored) { /* Revision checking still prevents replay after a committed activation. */ }
        return $this->get($merchantId)+['impact'=>$impact];
    }

    public function syncSettings(int $merchantId,#[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->merchant($merchantId);
        $enabled=$input['enabled']??null; $interval=$input['interval_minutes']??null;
        if (!in_array($enabled,[true,false,1,0,'1','0'],true) || (!is_int($interval) && !is_string($interval))
            || !preg_match('/^[1-9][0-9]{1,3}$/D',(string)$interval) || (int)$interval<15 || (int)$interval>1440) {
            throw new CommonException('请明确选择自动同步开关，间隔需为15—1440分钟');
        }
        Db::transaction(function()use($merchantId,$enabled,$interval){
            $policy=new ProviderChannelService($this->context->site);$policy->lockSite();
            $merchant=Data::row('merchant',$this->context->site,$merchantId,true);
            if ((bool)$enabled && (int)$merchant['status']!==1) throw new CommonException('商户已停用，不能开启自动同步');
            $conn=Data::query('connection',$this->context->site)->where('merchant_id',$merchantId)->lock(true)->find();
            if (!$conn) throw new CommonException('请先探测并激活ERP连接');
            if (in_array($enabled,[true,1,'1'],true) && !(int)$conn['enabled']) $policy->assertEnabled((string)$conn['provider']);
            // Settings do not alter config_version, lease, cursor, or active/manual run.
            Data::query('connection',$this->context->site)->where('id',$conn['id'])->update(['enabled'=>(bool)$enabled?1:0,
                'sync_interval_minutes'=>(int)$interval,'next_sync_at'=>time()+(int)$interval*60,'update_time'=>time()]);
            $this->context->audit('connection',(int)$conn['id'],'sync_settings',['enabled'=>(bool)$enabled,'interval_minutes'=>(int)$interval]);
        });
        return $this->get($merchantId);
    }

    private function merchant(int $id): array { return $this->context->connectionMerchant($id); }
    private function connection(int $merchant): ?array { return Data::query('connection',$this->context->site)->where('merchant_id',$merchant)->find()?:null; }
    private function revision(?array $conn): string
    {
        return hash('sha256',Data::json($conn?array_intersect_key($conn,array_flip(['id','config_version','provider','expected_tenant_id','store_id'])):[]));
    }
    private function cacheKey(#[\SensitiveParameter] string $token): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        return 'hsx_lead.probe.'.hash('sha256',$token);
    }
    private function fingerprint(#[\SensitiveParameter] array $credentials): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        ksort($credentials);
        return hash('sha256',Data::json($credentials));
    }
    private function impact(?array $conn,ProviderDefinition $definition,#[\SensitiveParameter] array $credentials,array $scope): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $switched=$conn===null || $conn['provider']!==$definition->key() || (string)$conn['expected_tenant_id']!==$scope['tenant_id'] || (string)$conn['store_id']!==$scope['store_id'];
        if ($conn && !$switched) {
            try {
                $old=Cipher::decrypt((string)$conn['credentials_cipher'],$this->context->site,(int)$conn['id']);
                $switched=!hash_equals($definition->identityFingerprint($old),$definition->identityFingerprint($credentials));
            } catch(\Throwable $ignored) { $switched=true; }
        }
        return ['switching'=>$conn!==null && $switched,'previous_provider'=>(string)($conn['provider']??''),
            'published_snapshot_will_be_hidden'=>$switched && (int)($conn['published_run_id']??0)>0,
            'active_sync_will_cancel'=>(int)($conn['active_run_id']??0)>0,
            'message'=>$conn===null?'首次接入只保存已验证连接；完成首次完整同步后才有可用于匹配的库存资料':($switched?'激活会切换已验证账号或门店范围，终止旧读取任务；旧快照暂不参与匹配，完成首次完整同步后恢复':'激活会替换已验证凭据并终止当前读取，保留上一批完整快照；不会自动承诺可售或锁货')];
    }
}
