<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect']as$fn)if(function_exists($fn))exit("Disable HTTP functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';require_once dirname(__DIR__,3).'/vendor/autoload.php';
use addon\hsx_lead\app\auth\CaptchaRecognizerContract;
use addon\hsx_lead\app\provider\{AiguanjiProvider,ProviderException,ProviderRegistry,RequestTransportContract};
use addon\hsx_lead\app\service\{AuthChallengeService,AutomaticAuthService,CaptchaConfigService,Context,ProviderChannelService};
use addon\hsx_lead\app\support\{Cipher,Data};
use think\facade\{Cache,Db};

final class ChannelAuthErp implements RequestTransportContract
{
    public array $calls=[],$hooks=[];
    public function post(string $url,array $headers,array $data):array{return$this->reply($url,$data);}
    public function request(string $method,string $url,array $headers,array $data,string $encoding):array
    {
        $result=$this->reply($url,$data);
        return$url===AiguanjiProvider::LOGIN_ENDPOINT?['status'=>200,'headers'=>['Content-Type'=>['application/json']],'body'=>Data::json($result)]:$result;
    }
    private function reply(string $url,array $data):array
    {
        caCheck(!Db::connect()->getPdo()->inTransaction(),'ERP request is outside transaction');$this->calls[]=$url;
        if(isset($this->hooks[$url]))($this->hooks[$url])();
        if($url===AiguanjiProvider::CAPTCHA_ENDPOINT)return['status'=>200,'headers'=>['Content-Type'=>['image/png']],'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jp1sAAAAASUVORK5CYII=',true)];
        if($url===AiguanjiProvider::LOGIN_ENDPOINT)return['code'=>0,'data'=>['token'=>'POISON_SYNTHETIC_TOKEN']];
        if($url===AiguanjiProvider::PROFILE_ENDPOINT)return['code'=>0,'data'=>['user'=>['mobile'=>'18800000001','company'=>['id'=>42,'name'=>'Synthetic company']]]];
        if($url===AiguanjiProvider::STOCK_ENDPOINT)return['code'=>0,'data'=>['total'=>0,'per_page'=>20,'current_page'=>1,'last_page'=>0,'has_more'=>false,'data'=>[]]];
        throw new RuntimeException('Unexpected synthetic endpoint');
    }
    public function count(string $url):int{return count(array_filter($this->calls,static fn($item)=>$item===$url));}
}
final class ChannelAuthCaptcha implements CaptchaRecognizerContract
{
    public int $creates=0,$queries=0;public ?Closure $onCreate=null,$onResult=null;
    public function create(#[SensitiveParameter]string $key,#[SensitiveParameter]string $image,int $length=4):string
    {
        caCheck(!Db::connect()->getPdo()->inTransaction(),'recognition submission is outside transaction');$this->creates++;
        if($this->onCreate)($this->onCreate)();return'POISON_RECOGNITION_TASK';
    }
    public function result(#[SensitiveParameter]string $key,string $id,int $length=4):array
    {
        caCheck(!Db::connect()->getPdo()->inTransaction(),'recognition query is outside transaction');$this->queries++;
        if($this->onResult)($this->onResult)();return['status'=>'ready','text'=>'Ab19','cost'=>'0.00025'];
    }
    public function balance(#[SensitiveParameter]string $key):string{throw new RuntimeException('No balance request expected');}
}
$checks=0;$failed=false;$pdo=null;$schema='';$case='initial';
function caCheck(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('Assertion failed: '.$label);$GLOBALS['checks']++;}
function caDenied(callable $action):void
{
    try{$action();}catch(ProviderException $error){caCheck($error->reason()==='channel_disabled','disabled channel has stable safe classification');return;}
    throw new RuntimeException('Disabled authorization must be rejected');
}
function caSet(ProviderChannelService $policy,Context $context,bool $enabled):void
{
    $current=$policy->configuration();$items=array_map(static fn($row)=>['key'=>$row['key'],'enabled'=>$row['key']==='aiguanji'?$enabled:$row['enabled']],$current['items']);
    $policy->save($context,['revision'=>$current['revision'],'items'=>$items]);
}
function caFast(int $site,int $merchant,string $token):void
{
    $key='hsx_lead_auto_auth_'.hash('sha256',$token);$state=Cipher::decrypt(Cache::get($key),$site,-$merchant);$state['next_poll_at']=0;
    Cache::set($key,Cipher::encrypt($state,$site,-$merchant),420);
}
function caClean(int $site,int $merchant,string $token):void
{
    $state=Cipher::decrypt(Cache::get('hsx_lead_auto_auth_'.hash('sha256',$token)),$site,-$merchant);
    foreach(['password','answer','challenge_token','recognition_task']as$key)caCheck(!array_key_exists($key,$state),'cancelled state removes transient '.$key);
}
try{
    [$app,$pdo,$schema,$site]=leadBoot();$context=new Context($site,1);$policy=new ProviderChannelService($site);
    $make=static function()use($site,$context):array{
        $mid=(int)Data::query('merchant',$site)->insertGetId(['site_id'=>$site,'name'=>'Synthetic channel authorization','status'=>1]);
        $conn=(int)Data::query('connection',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$mid,'provider'=>'aiguanji','store_id'=>'42','expected_tenant_id'=>'42','config_version'=>1,'published_run_id'=>123]);
        Data::query('connection',$site)->where('id',$conn)->update(['credentials_cipher'=>Cipher::encrypt(['username'=>'18800000001','access_token'=>'POISON_OLD_TOKEN'],$site,$conn)]);
        $erp=new ChannelAuthErp();$recognizer=new ChannelAuthCaptcha();$registry=new ProviderRegistry($erp);$config=new CaptchaConfigService($context,$recognizer);
        $config->save(['api_key'=>str_repeat('d',32),'enabled'=>true,'consent'=>true,'daily_limit'=>1000]);
        return['mid'=>$mid,'conn'=>$conn,'old'=>Data::row('connection',$site,$conn),'erp'=>$erp,'recognizer'=>$recognizer,
            'challenge'=>new AuthChallengeService($context,$registry),'auto'=>new AutomaticAuthService($context,$registry,$recognizer,$config),
            'input'=>['provider'=>'aiguanji','credentials'=>['username'=>'18800000001'],'scope'=>[],'password'=>'POISON_TEST_PASSWORD','request_id'=>bin2hex(random_bytes(16))]];
    };
    caSet($policy,$context,false);$f=$make();
    caDenied(fn()=>$f['challenge']->begin($f['mid'],$f['input']));caDenied(fn()=>$f['auto']->start($f['mid'],$f['input']));
    caCheck($f['erp']->calls===[]&&$f['recognizer']->creates===0,'closed channel starts neither CAPTCHA nor paid recognition');

    caSet($policy,$context,true);$f=$make();$captcha=$f['challenge']->begin($f['mid'],$f['input']);caSet($policy,$context,false);
    caDenied(fn()=>$f['challenge']->authorize($f['mid'],'aiguanji',['username'=>'18800000001'],['challenge_token'=>$captcha['challenge_token'],'password'=>'POISON_TEST_PASSWORD','code'=>'Ab19']));
    caCheck($f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===0,'previously obtained challenge cannot bypass a newly closed channel');

    caSet($policy,$context,true);$f=$make();$f['erp']->hooks[AiguanjiProvider::CAPTCHA_ENDPOINT]=static fn()=>caSet($policy,$context,false);
    caDenied(fn()=>$f['challenge']->begin($f['mid'],$f['input']));
    caCheck(count($f['erp']->calls)===1,'closing during CAPTCHA request prevents committing a usable challenge');

    foreach(['before_create','before_query','before_login','during_create','during_query','during_login']as$case){
        caSet($policy,$context,true);$f=$make();$started=$f['auto']->start($f['mid'],$f['input']);$token=$started['task_token'];
        $stop=static fn()=>caSet($policy,$context,false);
        if($case==='before_query'||$case==='before_login'||$case==='during_query'||$case==='during_login')$f['auto']->advance($f['mid'],$token);
        if($case==='before_login'||$case==='during_login'){caFast($site,$f['mid'],$token);$f['auto']->advance($f['mid'],$token);}
        if(str_starts_with($case,'before_'))$stop();
        elseif($case==='during_create')$f['recognizer']->onCreate=$stop;
        elseif($case==='during_query')$f['recognizer']->onResult=$stop;
        else$f['erp']->hooks[AiguanjiProvider::LOGIN_ENDPOINT]=$stop;
        caFast($site,$f['mid'],$token);$result=$f['auto']->advance($f['mid'],$token);
        caCheck($result['status']==='cancelled','closed channel cancels automatic authorization at '.$case);
        caCheck(!str_contains(Data::json($result),'POISON_'),'cancelled public result is secret-free at '.$case);
        caClean($site,$f['mid'],$token);
        $before=[count($f['erp']->calls),$f['recognizer']->creates,$f['recognizer']->queries];
        $again=$f['auto']->advance($f['mid'],$token);
        caCheck($again['status']==='cancelled'&&$before===[count($f['erp']->calls),$f['recognizer']->creates,$f['recognizer']->queries],'cancelled task never performs another external step');
        caCheck($f['auto']->cancel($f['mid'],$token)['status']==='cancelled','cancel remains available while channel is closed');
        caCheck(Data::row('connection',$site,$f['conn'])===$f['old'],'policy cancellation never overwrites the permanent connection');
        if($case!=='during_login')caCheck($f['erp']->count(AiguanjiProvider::LOGIN_ENDPOINT)===0,'no later password login after policy closure');
        if($case==='before_create')caCheck($f['recognizer']->creates===0,'closure before submit never creates a recognition');
    }
    leadAssertIsolated($schema);echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'channel gating for manual CAPTCHA, cached challenges, automatic stages, in-flight close and cancellation','remote_calls'=>0,'paid_tasks'=>0,'existing_business_rows_read'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable$error){$failed=true;fwrite(STDERR,'FAIL after '.$checks.' checks (case '.$case.'): '.get_class($error).': '.($error instanceof PDOException?'isolated MySQL '.$error->getCode():$error->getMessage()).' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);}
finally{if($pdo instanceof PDO&&$schema!=='')try{leadCleanup($pdo,$schema);}catch(Throwable$error){$failed=true;fwrite(STDERR,"FAIL owned database cleanup\n");}}
exit($failed?1:0);
