<?php
declare(strict_types=1);
// Only SQLite in memory and a fixture adapter; absolutely no network driver.
namespace addon\hsx_erp_integration\app\service {
    final class AdapterRegistry {
        public static $profile=[];
        public static function forSite(int $site): self{return new self();}
        public function hasAdapter(string $code): bool{return $code==='fixture';}
        public function getAdapterList(): array{return [['code'=>'fixture']];}
        public function getAdapter(string $code){return new \FixtureScopeAdapter;}
    }
}
namespace app\service\core\site {
    final class CoreSiteService {public function getAddonKeysBySiteId(int $site):array{return $site===11?['hsx_erp_integration']:[];}}
}
namespace app\service\admin\auth {
    final class AuthService {public function getAuthApiList():array{return ['get'=>['erp/purchase/lists','integration/task/<id>'],'post'=>['integration/task/prepare/<id>']];}}
}
namespace {
    use addon\hsx_erp_integration\app\service\AdapterRegistry;
    use addon\hsx_erp_integration\app\adminapi\controller\Connection;
    use think\facade\Db;
    $root=dirname(__DIR__,3);require $root.'/vendor/autoload.php';require_once $root.'/vendor/topthink/framework/src/helper.php';require_once $root.'/app/common.php';
    class FixtureScopeAdapter implements \addon\hsx_erp_integration\app\contract\ManualDocumentAdapterInterface,\addon\hsx_erp_integration\app\contract\DisjointManualScopeInterface {
        public static $ambiguous=false;
        public function manualDisjointScopes(int $site):array{return $site===11?AdapterRegistry::$profile['pairs']??[]:[];}
        public function admitsManualFact(int $site,array $config,array $e):bool{return self::$ambiguous || $config['manual_scope']===($e['payload']['lines'][0]['imei']??null);}
        public function originalProfile(int $siteId): array{return $siteId===11?AdapterRegistry::$profile:[];}
        public function manualBusinessTypes(string $mode): array{return ['purchase','payment'];}
        public function getSupportedBusinessTypes(): array{return ['purchase','payment'];}
        public function getConfigFields(): array{return [];}
        public function originalDocument(array $event,array $context,bool $readOnly): array{throw new \Exception('must not dispatch');}
    }
    if(!extension_loaded('pdo_sqlite')) throw new \RuntimeException('Use standalone PHP with SQLite');
    $app=new \think\App($root); $app->config->set(['default'=>'sandbox','connections'=>['sandbox'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
    $app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/erp-scope-test-cache/']]],'cache');
    $app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/erp-scope-test-log/']]],'log');
    (new \think\service\ModelService($app))->boot();
    function ctx(array $args=[]):void{$r=new \app\Request();$r->withServer(['REQUEST_METHOD'=>'GET'])->withGet($args);$r->siteId(11);$r->uid(19);app()->instance('request',$r);}
    function eq($actual,$expected,string $why):void{if($actual!==$expected)throw new \RuntimeException($why.' '.json_encode($actual));}
    function resp($r,bool $positive=true){$v=$r->getData();eq((bool)$v['code'],$positive,$v['msg']);return $v['data'];}
    ctx();Db::execute('CREATE TABLE erp_integration_connection (id INTEGER PRIMARY KEY,site_id INTEGER,adapter_code TEXT,adapter_name TEXT,sync_mode TEXT,business_types TEXT,config TEXT,status TEXT,retry_times INTEGER,bridge_started_at INTEGER,last_test_at INTEGER,last_test_result TEXT,created_at INTEGER,updated_at INTEGER)');
    Db::execute('CREATE TABLE addon (id INTEGER PRIMARY KEY,key TEXT,status INTEGER)');
    $profile=['mode'=>'production','app_id'=>'demo','api_url'=>'https://fixture.invalid/api','account_code'=>'test-book','gateway_path'=>'/gateway','checked'=>true,
        'manual_scope'=>'legacy','manual_scope_types'=>['purchase'],'manual_scope_options'=>[['value'=>'fresh','label'=>'本次限定试单']]];
    AdapterRegistry::$profile=$profile; $c=new Connection();
    $base=['adapter_name'=>'A','adapter_code'=>'fixture','business_types'=>['purchase'],'sync_mode'=>'manual'];
    foreach([[] ,['manual_scope'=>''],['manual_scope'=>'legacy'],['manual_scope'=>'unknown'],['config'=>['manual_scope'=>'fresh']],['manual_scope'=>'fresh','business_types'=>['payment']]] as $extra){
        ctx(array_replace($base,$extra));resp($c->add(),false);
    }
    ctx();eq(resp($c->getAdapters())['list'][0]['manual_scope_options'],$profile['manual_scope_options'],'choices');
    ctx($base+['manual_scope'=>'fresh']);$new=(int)resp($c->add())['id'];
    ctx();eq(resp($c->info($new))['manual_scope'],'fresh','persisted scope');eq(resp($c->lists())['list'][0]['manual_scope_label'],'本次限定试单','shown scope');
    $cfg=$profile;unset($cfg['manual_scope_types'],$cfg['manual_scope_options'],$cfg['checked']);$legacy=Db::name('erp_integration_connection')->insertGetId(['site_id'=>11,'adapter_code'=>'fixture','adapter_name'=>'L','status'=>'enabled','last_test_result'=>'success','business_types'=>'["purchase"]','sync_mode'=>'manual','config'=>json_encode($cfg)]);
    ctx($base+['manual_scope'=>'fresh']);resp($c->edit((int)$legacy),false);ctx($base);resp($c->edit((int)$legacy));ctx();eq(resp($c->info((int)$legacy))['manual_scope'],'legacy','edit cannot upgrade');
    resp($c->testConnection($new));ctx(['status'=>'enabled']);resp($c->changeStatus($new),false);
    ctx(['status'=>'disabled']);resp($c->changeStatus((int)$legacy));ctx();eq(resp($c->info((int)$legacy))['manual_scope'],'legacy','pause preserves original target');
    ctx(['status'=>'enabled']);resp($c->changeStatus($new));
    ctx(['status'=>'disabled']);resp($c->changeStatus($new));ctx();AdapterRegistry::$profile['manual_scope_options']=[];
    resp($c->testConnection($new),false);ctx(['status'=>'enabled']);resp($c->changeStatus($new),false);
    // A generic adapter explicitly declares two disjoint single-document scopes. The bridge knows no IMEI or vendor literal.
    $profile['manual_scope_options'][]=['value'=>'later','label'=>'另一单']; $profile['pairs']=[['fresh','later']]; AdapterRegistry::$profile=$profile;
    ctx();resp($c->testConnection($new));ctx(['status'=>'enabled']);resp($c->changeStatus($new)); $before=Db::name('erp_integration_connection')->where('id',$new)->find();
    ctx($base+['manual_scope'=>'later']);$second=(int)resp($c->add())['id'];ctx();resp($c->testConnection($second));ctx(['status'=>'enabled']);resp($c->changeStatus($second));
    eq(Db::name('erp_integration_connection')->where('id',$new)->find(),$before,'first connection left untouched');
    $another=Db::name('erp_integration_connection')->where('id',$second)->find();
    $permits=fn($a,$b)=>\addon\hsx_erp_integration\app\support\DisjointManualRoutes::permits(11,$a,$b,new FixtureScopeAdapter());
    foreach(['mode','app_id','api_url','gateway_path','account_code','manual_scope'] as $k){
        $bad=$another;$cfg=json_decode($bad['config'],true);$cfg[$k]=$k==='manual_scope'?'fresh':'WRONG';$bad['config']=json_encode($cfg);
        eq($permits($before,$bad),false,'mismatched same-target pair '.$k);
    }
    foreach(['site_id'=>12,'business_types'=>'["purchase","payment"]','last_test_result'=>'failed','adapter_code'=>'other','sync_mode'=>'async'] as $k=>$v)
        eq($permits($before,array_replace($another,[$k=>$v])),false,'pair restrictions '.$k);
    ctx($base+['manual_scope'=>'later']);$dup=(int)resp($c->add())['id'];ctx();resp($c->testConnection($dup));ctx(['status'=>'enabled']);resp($c->changeStatus($dup),false);
    Db::execute('CREATE TABLE erp_integration_sync_task(id INTEGER PRIMARY KEY,site_id INTEGER,connection_id INTEGER,business_type TEXT,source_type TEXT,source_id INTEGER,source_no TEXT,status TEXT,payload TEXT,origin_event_id TEXT,fact_key TEXT,target_key TEXT,receipt_json TEXT,external_doc_no TEXT,error_code TEXT,error_message TEXT,created_at INTEGER,updated_at INTEGER,UNIQUE(site_id,target_key,fact_key),UNIQUE(site_id,target_key,origin_event_id))');
    $capture=new \addon\hsx_erp_integration\app\service\DomainCaptureService();
    $event=fn($serial,$id)=>['site_id'=>11,'event_name'=>\addon\hsx_erp_integration\app\support\BridgeFact::PURCHASE,'event_version'=>1,'event_id'=>'EV-'.$id,'aggregate_type'=>'purchase','aggregate_id'=>$id,'occurred_at'=>time()+1,'source'=>['plugin'=>'hsx_erp','type'=>'purchase','id'=>$id],
        'payload'=>['kind'=>'purchase','source_document_id'=>'purchase:'.$id,'source_document_no'=>'P'.$id,'batch_ref'=>'test'.$id,'lines'=>[['source_line_id'=>'purchase_line:'.$id,'imei'=>$serial,'amount'=>'1.00']]]];
    foreach([[$new,'fresh',4],[$second,'later',5]] as [$conn,$scope,$seq]){
        $e=$event($scope,$seq);$r=$capture->accept($e);$id=$r['task_ids'][0];$t=Db::name('erp_integration_sync_task')->where('id',$id)->find();
        eq([(int)$t['connection_id'],$t['status']],[$conn,'awaiting_confirmation'],'one matching manual task');
        eq($capture->accept($e)['task_ids'],[$id],'idempotence within active pair');
        eq(\addon\hsx_erp_integration\app\service\BoundManualMappings::connection(11,'fixture','purchase',$profile,$scope),$conn,'only its bound mappings');
    }
    eq(\addon\hsx_erp_integration\app\service\BoundManualMappings::connection(11,'fixture','purchase',$profile),0,'never choose arbitrary shared mapping');
    eq($capture->accept($event('unrelated',6)),[],'unrelated event ignored');
    $tooEarly=$event('later',7);$tooEarly['occurred_at']=1;eq($capture->accept($tooEarly),[],'no pre-enable capture');
    FixtureScopeAdapter::$ambiguous=true; try {$capture->accept($event('fresh',8));throw new \RuntimeException('ambiguous pair accepted');}catch(\DomainException $ok){}
    FixtureScopeAdapter::$ambiguous=false;AdapterRegistry::$profile['pairs']=[];try{$capture->accept($event('fresh',8));throw new \RuntimeException('withdrawn pair accepted');}catch(\DomainException $ok){}
    eq(Db::name('erp_integration_sync_task')->count(),2,'no extra tasks after ambiguous or withdrawn scopes');
    AdapterRegistry::$profile=$profile; ctx(); $recover=new \addon\hsx_erp_integration\app\service\OriginalCaptureRecovery();
    Db::execute('CREATE TABLE erp_purchase_order (id INTEGER PRIMARY KEY,site_id INTEGER,status TEXT,purchase_no TEXT)');
    Db::execute('CREATE TABLE erp_outbox_event (id INTEGER PRIMARY KEY,site_id INTEGER,event_name TEXT,event_id TEXT,occurred_at INTEGER,payload_json TEXT)');
    $pdo=Db::connect()->getPdo(); $pdo->sqliteCreateFunction('JSON_UNQUOTE',fn($value)=>(string)$value,1);
    foreach(['fresh','later','unrelated','later'] as $i=>$scope) {
        $e=$event($scope,60+$i);if($i===3)$e['occurred_at']=1;
        Db::name('erp_purchase_order')->insert(['id'=>60+$i,'site_id'=>11,'status'=>'completed','purchase_no'=>'P'.(60+$i)]);
        Db::name('erp_outbox_event')->insert(['site_id'=>11,'event_id'=>$e['event_id'],'event_name'=>$e['event_name'],'occurred_at'=>$e['occurred_at'],'payload_json'=>json_encode($e)]);
        eq($recover->available(11,60+$i),$i<2,'original document recovery respects enabled scope and cutoff');
    }
    eq(Db::name('erp_integration_sync_task')->count(),2,'checking original document action is read-only');
    echo "PASS: scoped manual connections, explicit choices, disjoint pair, original-document lookup, duplicate attempts, withdrawn scopes and paused history.\n";
}
