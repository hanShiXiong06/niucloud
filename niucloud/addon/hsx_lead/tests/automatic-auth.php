<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__,3) . '/vendor/autoload.php';

use addon\hsx_lead\app\auth\CaptchaRecognizerContract;
use addon\hsx_lead\app\provider\{AiguanjiProvider,ProviderException,ProviderRegistry,RequestTransportContract};
use addon\hsx_lead\app\service\{AutomaticAuthService,CaptchaConfigService,ConnectionService,Context};
use addon\hsx_lead\app\support\{Cipher,Data};
use core\exception\CommonException;
use think\facade\{Cache,Db};

final class AutoErpFixture implements RequestTransportContract
{
    public array $calls = [];
    public array $logins = [];
    public array $hooks = [];
    public function post(string $endpoint, array $headers, array $body): array { return $this->reply('POST', $endpoint, $headers, $body, 'json'); }
    public function request(string $method, string $endpoint, array $headers, array $data, string $encoding): array
    {
        $reply = $this->reply($method, $endpoint, $headers, $data, $encoding);
        return $endpoint === AiguanjiProvider::LOGIN_ENDPOINT
            ? ['status'=>200,'headers'=>['Content-Type'=>['application/json']],'body'=>Data::json($reply)] : $reply;
    }
    private function reply(string $method, string $endpoint, array $headers, array $data, string $encoding): array
    {
        autoCheck(!Db::connect()->getPdo()->inTransaction(), 'ERP HTTP is never inside a database transaction');
        $this->calls[] = compact('method','endpoint','headers','data','encoding');
        if (isset($this->hooks[$endpoint])) ($this->hooks[$endpoint])();
        if ($endpoint === AiguanjiProvider::CAPTCHA_ENDPOINT) return autoImage();
        if ($endpoint === AiguanjiProvider::LOGIN_ENDPOINT) {
            $reply = $this->logins ? array_shift($this->logins) : ['code'=>0,'data'=>['token'=>'POISON_NEW_ACCESS_TOKEN']];
            if ($reply instanceof Closure) $reply = $reply();
            if ($reply instanceof Throwable) throw $reply;
            return $reply;
        }
        if ($endpoint === AiguanjiProvider::PROFILE_ENDPOINT) return ['code'=>0,'data'=>['user'=>[
            'mobile'=>'18800000001','company'=>['id'=>42,'name'=>'合成公司'],'password'=>'POISON_PROFILE_PASSWORD']]];
        if ($endpoint === AiguanjiProvider::STOCK_ENDPOINT) return ['code'=>0,'data'=>[
            'total'=>0,'per_page'=>20,'current_page'=>1,'last_page'=>0,'has_more'=>false,'data'=>[]]];
        throw new RuntimeException('Unexpected synthetic endpoint');
    }
    public function count(string $endpoint): int { return count(array_filter($this->calls, static fn(array $call): bool => $call['endpoint'] === $endpoint)); }
}

final class AutoCaptchaFixture implements CaptchaRecognizerContract
{
    public array $calls = [];
    public array $creates = [];
    public array $results = [];
    public ?Closure $onCreate = null;
    public ?Closure $onResult = null;
    public function create(#[SensitiveParameter] string $apiKey, #[SensitiveParameter] string $dataUri, int $length = 4): string
    {
        autoCheck(!Db::connect()->getPdo()->inTransaction(), 'paid create is never inside a database transaction');
        autoCheck($apiKey === str_repeat('c',32) && str_starts_with($dataUri,'data:image/png;base64,') && $length === 4,
            'recognizer receives only the configured API key and bounded image, not ERP credentials');
        $this->calls[] = ['method'=>'create','api_key'=>$apiKey,'image'=>$dataUri,'length'=>$length];
        if ($this->onCreate) ($this->onCreate)();
        $response = $this->creates ? array_shift($this->creates) : 'POISON_UPSTREAM_TASK_' . $this->count('create');
        if ($response instanceof Throwable) throw $response;
        return $response;
    }
    public function result(#[SensitiveParameter] string $apiKey, string $taskId, int $length = 4): array
    {
        autoCheck(!Db::connect()->getPdo()->inTransaction(), 'recognition query is never inside a database transaction');
        autoCheck($apiKey === str_repeat('c',32) && str_starts_with($taskId,'POISON_UPSTREAM_TASK_') && $length === 4,
            'queries reuse the original recognition task and key');
        $this->calls[] = ['method'=>'result','task_id'=>$taskId,'length'=>$length];
        if ($this->onResult) ($this->onResult)();
        $response = $this->results ? array_shift($this->results) : ['status'=>'ready','text'=>'Ab19','cost'=>'0.00025'];
        if ($response instanceof Throwable) throw $response;
        return $response;
    }
    public function balance(#[SensitiveParameter] string $apiKey): string { throw new RuntimeException('Automatic login must not query balance'); }
    public function count(string $method): int { return count(array_filter($this->calls, static fn(array $call): bool => $call['method'] === $method)); }
}

$checks=0; $failed=false; $pdo=null; $schema='';
function autoCheck(bool $condition,string $description): void
{
    if (!$condition) throw new RuntimeException('FAIL ' . $description);
    $GLOBALS['checks']++;
}
function autoDenied(callable $action,string $description): void
{
    try { $action(); }
    catch (CommonException|ProviderException $error) {
        autoCheck($error->getPrevious()===null && !str_contains($error->getMessage(),'POISON_'), $description . ' has a safe local error');
        return;
    }
    throw new RuntimeException('Expected denial: ' . $description);
}
function autoSafe(array $public): void
{
    $json=Data::json($public);
    autoCheck(!str_contains($json,'POISON_') && !str_contains($json,str_repeat('c',32)), 'public task response contains no upstream token, password, task ID or service key');
    autoCheck(!preg_match('/"(?:password|api_key|recognition_task|answer|device_id|clientKey|taskId)"/', $json), 'public response has no private state fields');
}
function autoImage(): array
{
    return ['status'=>200,'headers'=>['Content-Type'=>['image/png']],
        'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jp1sAAAAASUVORK5CYII=',true)];
}
function autoKey(string $token): string { return 'hsx_lead_auto_auth_' . hash('sha256',$token); }
function autoState(int $mid,string $token): array
{
    $cipher=Cache::get(autoKey($token));
    autoCheck(is_string($cipher) && !str_contains($cipher,'POISON_'), 'task cache exists only as an encrypted envelope');
    return Cipher::decrypt($cipher,$GLOBALS['auto_site'],-$mid);
}
function autoEdit(int $mid,string $token,callable $edit): void
{
    $state=autoState($mid,$token); $edit($state);
    Cache::set(autoKey($token),Cipher::encrypt($state,$GLOBALS['auto_site'],-$mid),420);
}
function autoFast(int $mid,string $token): void { autoEdit($mid,$token,static function(array &$state): void { $state['next_poll_at']=0; }); }
function autoClean(int $mid,string $token): void
{
    $state=autoState($mid,$token);
    foreach (['password','answer','challenge_token','recognition_task'] as $field) autoCheck(!array_key_exists($field,$state), 'terminal state strips ' . $field);
}
function autoFixture(?Context $context=null): array
{
    $site=$GLOBALS['auto_site']; $context??=new Context($site,1);
    $erp=new AutoErpFixture(); $captcha=new AutoCaptchaFixture(); $providers=new ProviderRegistry($erp);
    $config=new CaptchaConfigService($context,$captcha);
    $config->save(['api_key'=>str_repeat('c',32),'enabled'=>true,'consent'=>true,'daily_limit'=>1000]);
    $mid=(int)Data::query('merchant',$site)->insertGetId(['site_id'=>$site,'name'=>'Synthetic automatic auth merchant','status'=>1]);
    $conn=(int)Data::query('connection',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$mid,'provider'=>'aiguanji',
        'config_version'=>1,'expected_tenant_id'=>'42','store_id'=>'42','published_run_id'=>123]);
    Data::query('connection',$site)->where('id',$conn)->update(['credentials_cipher'=>Cipher::encrypt(
        ['username'=>'18800000001','access_token'=>'POISON_OLD_ACCESS_TOKEN'],$site,$conn)]);
    $input=['provider'=>'aiguanji','credentials'=>['username'=>'18800000001'],'scope'=>[],
        'password'=>' POISON_NORMAL_PASSWORD ','request_id'=>bin2hex(random_bytes(16))];
    return ['mid'=>$mid,'conn'=>$conn,'before'=>Data::row('connection',$site,$conn),'input'=>$input,'erp'=>$erp,'captcha'=>$captcha,
        'providers'=>$providers,'config'=>$config,'service'=>new AutomaticAuthService($context,$providers,$captcha,$config)];
}
function autoBegin(array $f): array
{
    $public=$f['service']->start($f['mid'],$f['input']); autoSafe($public);
    autoCheck($public['status']==='processing' && $public['stage']==='preparing' && preg_match('/^[a-f0-9]{64}$/D',$public['task_token'])===1, 'start creates a local opaque preparing task');
    autoCheck($f['erp']->calls===[] && $f['captcha']->calls===[], 'start creates no upstream request or billable task');
    return $public;
}
function autoAdvance(array $f,string $token,bool $fast=false): array
{
    if ($fast) autoFast($f['mid'],$token);
    $public=$f['service']->advance($f['mid'],$token); autoSafe($public); return $public;
}
function autoLogging(array $f): array
{
    $public=autoBegin($f); $token=$public['task_token'];
    $public=autoAdvance($f,$token);
    autoCheck($public['stage']==='recognizing' && $public['status']==='processing', 'create transitions to recognizing without login');
    $public=autoAdvance($f,$token,true);
    autoCheck($public['stage']==='logging_in' && $public['status']==='processing', 'ready text transitions to logging_in, not immediate password submission');
    return $public;
}

try {
    [$app,$pdo,$schema,$site]=leadBoot(); $GLOBALS['auto_site']=$site;

    $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token'];
    $again=$f['service']->start($f['mid'],$f['input']);
    autoCheck($again['task_token']===$token && $f['captcha']->calls===[], 'same request ID and fingerprint returns the same task without new recognition');
    $resume=$f['input']; unset($resume['password']); $resume['resume_only']=true;
    autoCheck($f['service']->start($f['mid'],$resume)['task_token']===$token, 'lost start response may resume without supplying or retaining password in the browser');
    foreach (['credentials','password','scope'] as $field) {
        $bad=$f['input'];
        if ($field==='credentials') $bad[$field]['username']='18800000002';
        if ($field==='password') $bad[$field]='another-password';
        if ($field==='scope') $bad[$field]=['tenant_id'=>'43','store_id'=>'43'];
        autoDenied(fn()=>$f['service']->start($f['mid'],$bad), 'idempotent request cannot change ' . $field);
    }
    $otherInput=$f['input']; $otherInput['request_id']=bin2hex(random_bytes(16));
    autoDenied(fn()=>$f['service']->start($f['mid'],$otherInput), 'parallel new request for same merchant');
    $public=autoAdvance($f,$token);
    autoCheck($public['stage']==='recognizing' && $public['retry_after']>=5, 'first recognition schedules at least five seconds before polling');
    $cached=autoState($f['mid'],$token);
    autoCheck($cached['password']===$f['input']['password'] && $cached['attempts']===1, 'password remains literal only in encrypted short-lived state');
    autoCheck($f['config']->get()['used_today']===1, 'paid attempt budget is reserved exactly once before create');
    $beforeCalls=count($f['erp']->calls)+count($f['captcha']->calls);
    for ($i=0;$i<3;$i++) autoAdvance($f,$token);
    autoCheck(count($f['erp']->calls)+count($f['captcha']->calls)===$beforeCalls, 'rapid advance does not poll before the minimum interval or recreate');
    $public=autoAdvance($f,$token,true);
    autoCheck($public['stage']==='logging_in' && $f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===0 && $public['retry_after']>=5,
        'recognized answer waits another bounded interval before login');
    autoAdvance($f,$token);
    autoCheck($f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===0, 'rapid logging-stage advance cannot send the password early');
    $public=autoAdvance($f,$token,true);
    autoCheck($public['status']==='completed' && !empty($public['result']['probe_token']) && $public['result']['preview']['can_activate'], 'normal automatic authorization returns the real ConnectionService verification preview');
    autoCheck(Data::row('connection',$site,$f['conn'])===$f['before'], 'completed automatic authorization never activates or changes existing connection by itself');
    autoCheck($f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===1 && $f['erp']->count(AiguanjiProvider::PROFILE_ENDPOINT)===2 && $f['captcha']->count('create')===1,
        'one success performs one password submission, identity checks and one recognition');
    autoClean($f['mid'],$token);
    $calls=count($f['erp']->calls)+count($f['captcha']->calls);
    autoCheck(autoAdvance($f,$token)['status']==='completed' && count($f['erp']->calls)+count($f['captcha']->calls)===$calls, 'completed-task replay sends no new request');
    autoDenied(fn()=>(new ConnectionService(new Context($site,1),$f['providers']))->activate($f['mid'],['probe_token'=>$public['result']['probe_token']]), 'activation requires explicit confirmation');
    (new ConnectionService(new Context($site,1),$f['providers']))->activate($f['mid'],['probe_token'=>$public['result']['probe_token'],'confirm_switch'=>true]);
    $saved=Data::row('connection',$site,$f['conn']); $permanent=Cipher::decrypt($saved['credentials_cipher'],$site,$f['conn']);
    autoCheck($permanent['username']==='18800000001' && $permanent['access_token']==='POISON_NEW_ACCESS_TOKEN', 'explicit activation retains the verified identity and encrypted access token');
    foreach (['password','code','device_id','answer','recognition_task','api_key'] as $field) autoCheck(!array_key_exists($field,$permanent), 'permanent connection excludes ' . $field);

    // Scope and caller identity failures must not consume the rightful task.
    $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token'];
    $other=(int)Data::query('merchant',$site)->insertGetId(['site_id'=>$site,'name'=>'Other merchant','status'=>1]);
    $cross=(int)Data::query('merchant',$site+1)->insertGetId(['site_id'=>$site+1,'name'=>'Cross site merchant','status'=>1]);
    $wrongUid=new AutomaticAuthService(new Context($site,2),$f['providers'],$f['captcha']);
    $wrongSite=new AutomaticAuthService(new Context($site+1,1),$f['providers'],$f['captcha']);
    autoDenied(fn()=>$wrongUid->advance($f['mid'],$token), 'other operator advance');
    autoDenied(fn()=>$wrongUid->cancel($f['mid'],$token), 'other operator cancel');
    autoDenied(fn()=>$f['service']->advance($other,$token), 'other merchant');
    autoDenied(fn()=>$wrongSite->advance($cross,$token), 'other site');
    autoDenied(fn()=>$wrongUid->start($f['mid'],array_replace($resume,['request_id'=>$f['input']['request_id']])), 'other operator cannot resume');
    $badResume=$f['input']; unset($badResume['password']); $badResume['resume_only']=true; $badResume['credentials']['username']='18800000002';
    autoDenied(fn()=>$f['service']->start($f['mid'],$badResume), 'resume with different account');
    $missing=$f['input']; unset($missing['password']); $missing['resume_only']=true; $missing['request_id']=bin2hex(random_bytes(16));
    autoDenied(fn()=>$f['service']->start($other,$missing), 'resume-only never creates a missing task');
    autoDenied(fn()=>(new AutomaticAuthService(new Context($site,0,101),$f['providers'],$f['captcha']))->start($f['mid'],$f['input']), 'member cannot start operator login');
    autoCheck($f['captcha']->calls===[] && $f['erp']->calls===[], 'all ownership/resume failures happen before external requests');
    $f['service']->cancel($f['mid'],$token); autoClean($f['mid'],$token);

    foreach (['cancel','expired','cache_missing','cipher_bad','purpose_bad'] as $case) {
        $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token'];
        if ($case==='cancel') {
            autoCheck($f['service']->cancel($f['mid'],$token)['status']==='cancelled', 'explicit cancel returns a terminal state');
            autoClean($f['mid'],$token);
            autoCheck(autoAdvance($f,$token)['status']==='cancelled', 'cancelled task cannot continue');
        } elseif ($case==='expired') {
            autoEdit($f['mid'],$token,static function(array &$state): void { $state['expires_at']=time()-1; });
            autoCheck(autoAdvance($f,$token)['status']==='expired', 'TTL expiration stops before recognition or login'); autoClean($f['mid'],$token);
        } else {
            if ($case==='cache_missing') Cache::delete(autoKey($token));
            if ($case==='cipher_bad') Cache::set(autoKey($token),'POISON_INVALID_CIPHER',420);
            if ($case==='purpose_bad') autoEdit($f['mid'],$token,static function(array &$state): void { $state['purpose']='wrong-purpose'; });
            autoDenied(fn()=>$f['service']->advance($f['mid'],$token), $case);
        }
        autoCheck($f['captcha']->calls===[] && $f['erp']->calls===[], 'unavailable or terminal tasks cause no paid call');
    }

    // Cancellation while create is in flight must discard the returned task, not continue login.
    $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token'];
    $f['captcha']->onCreate=static function()use($f,$token): void { $f['service']->cancel($f['mid'],$token); };
    autoCheck(autoAdvance($f,$token)['status']==='cancelled', 'in-flight create cannot overwrite cancellation');
    autoClean($f['mid'],$token);
    autoCheck($f['captcha']->count('create')===1 && $f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===0, 'one already submitted task may incur cost, but cancellation never logs in');
    autoAdvance($f,$token,true);
    autoCheck($f['captcha']->count('result')===0, 'cancelled create result never becomes an automatic query');

    $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token'];
    $f['erp']->hooks[AiguanjiProvider::CAPTCHA_ENDPOINT]=static function()use($f,$token): void { $f['service']->cancel($f['mid'],$token); };
    autoCheck(autoAdvance($f,$token)['status']==='cancelled' && $f['captcha']->count('create')===0,
        'cancellation while obtaining the ERP image is rechecked before paid submission');
    autoClean($f['mid'],$token);

    // Challenge and multi-company continuation cannot extend the absolute password deadline.
    $f=autoFixture(); $f['erp']->logins=[['code'=>7,'data'=>['token'=>'POISON_COMPANY_TEMP_TOKEN','teams'=>[
        ['id'=>42,'name'=>'合成公司甲'],['id'=>43,'name'=>'合成公司乙']]]]];
    $public=autoBegin($f); $token=$public['task_token']; $deadline=time()+45;
    autoEdit($f['mid'],$token,static function(array &$state)use($deadline): void { $state['expires_at']=$deadline; });
    autoAdvance($f,$token);
    $state=autoState($f['mid'],$token); $challengeToken=$state['challenge_token'];
    $challengeCipher=Cache::get('hsx_lead.auth.'.hash('sha256',$challengeToken));
    $challenge=Cipher::decrypt($challengeCipher,$site,-$f['mid']);
    autoCheck($state['expires_at']===$deadline && $challenge['expires_at']===$deadline, 'new image challenge is clamped to original automatic-task deadline, not a fresh 180 seconds');
    autoAdvance($f,$token,true); $public=autoAdvance($f,$token,true);
    autoCheck($public['status']==='completed' && !empty($public['result']['authorization_required']) && $public['result']['status']==='choose_company',
        'explicit company selection is handed back to the normal manual continuation, not silently auto-chosen');
    autoCheck($public['expires_at']===$deadline && $public['result']['expires_at']===$deadline, 'company handoff preserves the absolute password deadline');
    $companyToken=$public['result']['challenge_token'];
    $companyState=Cipher::decrypt(Cache::get('hsx_lead.auth.'.hash('sha256',$companyToken)),$site,-$f['mid']);
    autoCheck($companyState['expires_at']===$deadline && $companyState['private_state']['password']===$f['input']['password'],
        'server-only company continuation holds password no later than the original 45-second remainder');
    autoCheck(Cache::get('hsx_lead.auth.'.hash('sha256',$challengeToken))===null, 'old captcha challenge is consumed when the company token rotates');
    autoClean($f['mid'],$token);
    $continued=(new ConnectionService(new Context($site,1),$f['providers']))->probe($f['mid'],['provider'=>'aiguanji',
        'credentials'=>$f['input']['credentials'],'scope'=>[], 'authorization'=>['challenge_token'=>$companyToken,'company_id'=>'42']]);
    autoSafe($continued);
    autoCheck(!empty($continued['probe_token']) && Cache::get('hsx_lead.auth.'.hash('sha256',$companyToken))===null,
        'normal explicit company selection completes and consumes the password-bearing continuation');
    autoCheck(Data::row('connection',$site,$f['conn'])===$f['before'], 'company continuation is still a probe, never automatic activation');

    // A retained idempotency pointer must not rebuild a missing paid task; a new explicit
    // request after the expired active window may start locally, without any paid call.
    $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token']; Cache::delete(autoKey($token));
    autoDenied(fn()=>$f['service']->start($f['mid'],$f['input']), 'same request with lost task cache never recreates');
    $activeKey='hsx_lead_auto_auth_active_'.$site.'_'.$f['mid'];
    $active=Cipher::decrypt(Cache::get($activeKey),$site,-$f['mid']); $active['expires_at']=time()-1;
    Cache::set($activeKey,Cipher::encrypt($active,$site,-$f['mid']),420);
    $new=$f['input']; $new['request_id']=bin2hex(random_bytes(16));
    $replacement=$f['service']->start($f['mid'],$new);
    autoCheck($replacement['task_token']!==$token && $replacement['stage']==='preparing' && $f['captcha']->calls===[],
        'explicit new request can recover after expired active pointer without stale pointer causing a paid submission');
    $f['service']->cancel($f['mid'],$replacement['task_token']);

    // Simulated concurrent calls see the committed lease, not a second paid submission.
    $f=autoFixture(); $public=autoBegin($f); $token=$public['task_token'];
    $f['captcha']->onCreate=static function()use($f,$token): void {
        autoCheck($f['service']->advance($f['mid'],$token)['status']==='processing', 'concurrent advance sees live create lease');
    };
    autoAdvance($f,$token);
    autoCheck($f['captcha']->count('create')===1, 'a live create lease prevents duplicate recognition');
    $f['captcha']->onResult=static function()use($f,$token): void { $f['service']->advance($f['mid'],$token); };
    autoAdvance($f,$token,true);
    autoCheck($f['captcha']->count('result')===1, 'a live query lease prevents overlapping result calls');
    $f['service']->cancel($f['mid'],$token);

    foreach (['create','login','query'] as $operation) {
        $f=autoFixture(); $public=$operation==='login'?autoLogging($f):autoBegin($f); $token=$public['task_token'];
        if ($operation==='query') autoAdvance($f,$token);
        autoEdit($f['mid'],$token,static function(array &$state)use($operation): void {
            $state['lease']='expired-inflight'; $state['lease_until']=time()-1; $state['operation']=$operation; $state['next_poll_at']=0;
        });
        $public=autoAdvance($f,$token);
        if ($operation==='query') {
            autoCheck($public['stage']==='logging_in' && $f['captcha']->count('create')===1 && $f['captcha']->count('result')===1,
                'expired query lease safely resumes the original task without creating another');
            $f['service']->cancel($f['mid'],$token);
        } else {
            autoCheck($public['status']==='failed' && $f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===0, 'lost create/login result is not sent again after lease expiry');
            autoClean($f['mid'],$token);
        }
    }

    foreach (['query_temporary','processing'] as $case) {
        $f=autoFixture(); $f['captcha']->results=[$case==='query_temporary'
            ? new ProviderException('captcha_temporary','识别服务暂未响应') : ['status'=>'processing','cost'=>null], ['status'=>'ready','text'=>'Ab19','cost'=>null]];
        $public=autoBegin($f); $token=$public['task_token']; autoAdvance($f,$token);
        autoCheck(autoAdvance($f,$token,true)['stage']==='recognizing', 'temporary or processing result stays with the original recognition task');
        autoCheck($f['captcha']->count('create')===1 && $f['captcha']->count('result')===1, 'query failure never repeats the paid create');
        autoCheck(autoAdvance($f,$token,true)['stage']==='logging_in', 'safe result query may resume later');
        autoCheck(autoAdvance($f,$token,true)['status']==='completed' && $f['captcha']->count('create')===1, 'recovered original query may complete normal authorization');
    }
    foreach (['unknown_create','unexpected_create','wrong_password','bad_result'] as $case) {
        $f=autoFixture();
        if ($case==='unknown_create') $f['captcha']->creates=[new ProviderException('captcha_submit_unknown','识别提交结果待核实，请勿重复申请')];
        if ($case==='unexpected_create') $f['captcha']->creates=[new RuntimeException('POISON_PRIVATE_NETWORK_EXCEPTION')];
        if ($case==='wrong_password') $f['erp']->logins=[['code'=>1,'msg'=>'密码不正确 POISON_UPSTREAM_PASSWORD']];
        if ($case==='bad_result') $f['captcha']->results=[['status'=>'ready','text'=>'TOO-LONG']];
        $public=autoBegin($f); $token=$public['task_token'];
        for ($i=0;$i<4 && $public['status']==='processing';$i++) $public=autoAdvance($f,$token,true);
        autoCheck($public['status']==='failed' && $f['captcha']->count('create')===1, 'unknown create/password/invalid answer stops without a new recognition');
        autoClean($f['mid'],$token);
        $calls=count($f['erp']->calls)+count($f['captcha']->calls); autoAdvance($f,$token);
        autoCheck(count($f['erp']->calls)+count($f['captcha']->calls)===$calls, 'failed task advance cannot retry a paid request or password');
    }
    foreach (['second_success','second_failure'] as $case) {
        $f=autoFixture(); $bad=['code'=>1,'msg'=>'验证码错误 POISON_REMOTE_MESSAGE'];
        $f['erp']->logins=$case==='second_success'?[$bad]:[$bad,$bad];
        $public=autoBegin($f); $token=$public['task_token'];
        for ($i=0;$i<8 && $public['status']==='processing';$i++) $public=autoAdvance($f,$token,true);
        autoCheck($public['status']===($case==='second_success'?'completed':'failed'), 'explicit invalid_captcha has a controlled second attempt outcome');
        autoCheck($f['captcha']->count('create')===2 && $f['erp']->count(AiguanjiProvider::CAPTCHA_ENDPOINT)===2 && $f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===2,
            'only explicit ERP captcha rejection permits one fresh image and at most two recognitions/logins');
        autoClean($f['mid'],$token);
    }

    foreach (['revision_before','revision_during_create','revision_during_login','config_before','config_during_create','config_during_query','config_during_login'] as $case) {
        $f=autoFixture(); $public=str_ends_with($case,'login')?autoLogging($f):autoBegin($f); $token=$public['task_token'];
        if ($case==='config_during_query') autoAdvance($f,$token);
        $change=static function()use($f,$case,$site): void {
            if (str_starts_with($case,'revision_')) Data::query('connection',$site)->where('id',$f['conn'])->update(['config_version'=>2]);
            else $f['config']->save(['enabled'=>false]);
        };
        if (str_ends_with($case,'before')) $change();
        elseif (str_ends_with($case,'create')) $f['captcha']->onCreate=$change;
        elseif (str_ends_with($case,'query')) $f['captcha']->onResult=$change;
        else $f['erp']->hooks[AiguanjiProvider::LOGIN_ENDPOINT]=$change;
        $public=autoAdvance($f,$token,true);
        autoCheck(in_array($public['status'],['cancelled','failed'],true), 'configuration change before or during operation prevents accepting stale result: ' . $case);
        autoCheck(Data::row('connection',$site,$f['conn'])['credentials_cipher']===$f['before']['credentials_cipher'], 'configuration change never replaces original permanent credentials');
        autoClean($f['mid'],$token);
    }

    $menu=require dirname(__DIR__).'/app/dict/menu/site.php';
    $nodes=[]; $walk=static function(array $rows)use(&$walk,&$nodes): void { foreach ($rows as $row) { $nodes[$row['menu_key']]=$row; $walk($row['children']??[]); } }; $walk($menu);
    foreach (['connection_auth_automatic','connection_auth_automatic_advance','connection_auth_automatic_cancel'] as $permission) {
        $node=$nodes['hsx_lead_manage_'.$permission]??[];
        autoCheck(($node['is_show']??null)==='0' && ($node['methods']??null)==='post', 'each automatic authorization action is an explicit hidden POST permission');
    }
    autoCheck(Db::name('hsx_lead_collaboration')->count()===0 && Db::name('hsx_lead_coop_order')->count()===0, 'login verification creates no collaboration or financial rows');
    leadAssertIsolated($schema);
    echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'automatic authorization, encrypted temporary state, idempotency, retry/cancel/lease safety and explicit activation',
        'remote_calls'=>0,'paid_tasks'=>0,'database'=>'owned random localhost schema','existing_business_rows_read'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;
} catch (Throwable $error) {
    $failed=true; $message=$error instanceof PDOException?'isolated MySQL SQLSTATE '.$error->getCode():$error->getMessage();
    fwrite(STDERR,'FAIL '.get_class($error).': '.$message.' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
} finally {
    if ($pdo instanceof PDO && $schema!=='') try { leadCleanup($pdo,$schema); }
    catch (Throwable $error) { $failed=true; fwrite(STDERR,"FAIL owned test database cleanup requires attention\n"); }
}
exit($failed?1:0);
