<?php
declare(strict_types=1);
// Write the simulated receipt ONLY to an in-memory DB. No shared config, ERP account or network transport.
use addon\hsx_boyuan\app\adapter\BoyuanErpAdapter;
use addon\hsx_boyuan\app\service\PurchaseAllocationEvidence;
use addon\hsx_erp_integration\app\adminapi\controller\{Task,Connection};
use addon\hsx_erp_integration\app\service\ManualTaskService;
use addon\hsx_erp_integration\app\support\BridgeTarget;
use think\facade\Db;

$root=dirname(__DIR__,3);
require $root.'/vendor/autoload.php';
require_once $root.'/vendor/topthink/framework/src/helper.php';
require_once $root.'/app/common.php';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR,"Requires pdo_sqlite; no database fallback.\n"); exit(2); }
$app=new think\App($root);
$app->config->set(['default'=>'ram','connections'=>['ram'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
$app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/bridge-receipt-fixture-cache/']]],'cache');
$app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/bridge-receipt-fixture-log/']]],'log');
(new think\service\ModelService($app))->boot();
function ensure($ok,string $label): void { if(!$ok) throw new RuntimeException($label); }
$f=json_decode(file_get_contents(dirname(__DIR__,2).'/hsx_boyuan/tests/fixtures/purchase-allocation-evidence.json'),true,512,JSON_THROW_ON_ERROR);
$source=$f['expected']; $site=$source['site_id']; $uid=91; $request=new app\Request();$request->siteId($site);$request->uid($uid);$app->instance('request',$request);
foreach ([
    'addon'=>'id INTEGER PRIMARY KEY,key TEXT,title TEXT,icon TEXT,desc TEXT,status INTEGER,type TEXT,support_app TEXT',
    'site'=>'site_id INTEGER PRIMARY KEY,group_id INTEGER,app_type TEXT,addons TEXT',
    'erp_integration_connection'=>'id INTEGER PRIMARY KEY,site_id INTEGER,status TEXT,adapter_code TEXT,adapter_name TEXT,sync_mode TEXT,config TEXT,business_types TEXT,retry_times INTEGER,created_at INTEGER,updated_at INTEGER,last_test_result TEXT,last_test_at INTEGER,bridge_started_at INTEGER',
    'erp_integration_sync_task'=>'id INTEGER PRIMARY KEY,site_id INTEGER,connection_id INTEGER,business_type TEXT,status TEXT,source_no TEXT,source_type TEXT,source_id INTEGER,origin_event_id TEXT,payload TEXT,receipt_json TEXT,fact_key TEXT,target_key TEXT,external_doc_no TEXT,external_doc_id TEXT,updated_at INTEGER,succeeded_at INTEGER,error_message TEXT,error_code TEXT',
] as $table=>$fields) Db::execute("CREATE TABLE $table ($fields)");
Db::name('addon')->insert(['key'=>'hsx_boyuan','status'=>1,'type'=>'addon']);
Db::name('site')->insert(['site_id'=>$site,'group_id'=>0,'app_type'=>'site','addons'=>'["hsx_boyuan"]']);
$profile=['mode'=>'test','account_code'=>'fixture-book','api_url'=>'https://fixture.invalid/api','gateway_path'=>'/gateway.do','app_id'=>'fixture'];
$conn=['id'=>1,'site_id'=>$site,'status'=>'enabled','adapter_code'=>'boyuan','sync_mode'=>'manual','config'=>json_encode($profile)];
Db::name('erp_integration_connection')->insert($conn);
$event=['site_id'=>$site,'event_id'=>'FIXTURE-302','event_name'=>'erp.settlement.completed.v1','event_version'=>1,'occurred_at'=>1234,
    'aggregate_type'=>'settlement','aggregate_id'=>302,'source'=>['type'=>'settlement','id'=>302,'plugin'=>'hsx_erp'],
    'payload'=>['settlement_id'=>302,'settlement_no'=>'LOCAL-S302','settlement_type'=>'payment','cash_direction'=>'out','amount'=>30,
        'confirmed_at'=>1234,'targets'=>[['target_type'=>'payable','source_type'=>'purchase_asset','target_id'=>202,
            'source_document_ref'=>'purchase:20','source_line_ref'=>'purchase_line:102','applied_amount'=>30]]]];
$base=['id'=>7,'site_id'=>$site,'connection_id'=>1,'business_type'=>'payment','fact_key'=>'settlement:302','source_type'=>'erp_settlement','source_id'=>302,'origin_event_id'=>'FIXTURE-302',
    'target_key'=>BridgeTarget::key($conn),'status'=>'awaiting_confirmation','payload'=>json_encode($event),'source_no'=>'LOCAL-S302'];
Db::name('erp_integration_sync_task')->insert($base);

// Only this process overrides a container binding; the runtime has no fixture flag, URL, controller parameter or seed.
$fake=new class extends BoyuanErpAdapter {
    public array $facts=[],$profile=[]; public ?array $sample=null; public int $links=0,$manualCalls=0,$localReads=0; public bool $denied=false;
    public function localOriginalReceipt(array $event,array $context): array {
        $this->localReads++;
        if($this->denied) return ['persist'=>false];
        ensure($event['site_id']===$this->facts['site_id'] && $context['account_code']===$this->facts['account_id'],'read-through scope');
        return ['status'=>'confirmed','vendor_task_id'=>31,'external_no'=>'PAY-2','message'=>'原付款凭证已登记'];
    }
    public function originalProfile(int $siteId): array { return $this->profile; }
    public function originalDocument(array $event,array $context,bool $readOnly): array {
        ++$this->manualCalls;
        if($this->denied) return ['status'=>'blocked','persist'=>false,'message'=>'无权读取'];
        ensure($event['site_id']===$this->facts['site_id'] && $context['account_code']===$this->facts['account_id'],'pinned source and tenant');
        if(!$readOnly) $this->links++;
        $provider=$this->sample===null ? new PurchaseAllocationEvidence() : new PurchaseAllocationEvidence(fn()=>$this->sample);
        return ['status'=>'confirmed','vendor_task_id'=>31,'external_no'=>'PAY-2','allocation'=>$provider->lookup($this->facts)];
    }
};
$fake->facts=$source; $app->instance(BoyuanErpAdapter::class,$fake);
$service=new ManualTaskService();$ctrl=new Task();
$demo=[]; $read=static function() use($ctrl):array { return $ctrl->info(7)->getData()['data']['receipt']; };
$rendered=$ctrl->info(7)->getData()['data'];
ensure($rendered['status']==='succeeded' && $rendered['external_doc_no']==='PAY-2' && $rendered['receipt_adapter']==='boyuan'
    && $rendered['receipt']['vendor_task_id']===31 && $rendered['receipt']['allocation']['status']==='pending' &&
    !$rendered['can_prepare'] && $fake->manualCalls===0 && $fake->localReads===1 &&
    Db::name('erp_integration_sync_task')->where('id',7)->value('receipt_json')===null,'GET reads vendor evidence without a pre-linked task, SQL writes or commands');
$view=new \addon\hsx_erp_integration\app\service\LocalOriginalReceiptView();
foreach([['site_id'=>$site+1],['error_code'=>'BRIDGE_SOURCE_INVALIDATED'],['target_key'=>'wrong'],['source_id'=>666],
    ['source_no'=>'different'],['fact_key'=>'settlement:666'],['origin_event_id'=>'unrelated']] as $bad){
    $row=array_replace($base,$bad);ensure($view->read($site,$row)===$row,'never borrow a different tenant/destination/invalidated fact');
}
ensure($fake->localReads===1,'invalid identities do not reach even the local adapter');
$service->run($site,7,$uid,false); ensure($read()['allocation']['remaining_amount']===null,'no provider -> persist pending, not zero');
$demo['无结算接口']=$read()['allocation'];
$fake->sample=$f['evidence']; array_pop($fake->sample['allocations']); $fake->sample['allocations'][0]['amount']='59.99';
$fake->sample['applied_amount']='69.99'; $fake->sample['remaining_amount']='30.01';
$service->run($site,7,$uid,true);ensure($read()['allocation']['status']==='partial' && $read()['allocation']['remaining_amount']==='30.01','partial remains payable');
$demo['部分核销']=$read()['allocation'];
$fake->sample=$f['evidence']; $service->run($site,7,$uid,true);
ensure($read()['allocation']['status']==='settled' && $read()['allocation']['verified'] && $read()['allocation']['remaining_amount']==='0.00' && $read()['allocation']['applied_amount']==='100.00','verified fixture through service, DB and controller');
$demo['全额核销']=$read()['allocation'];
$fake->denied=true;try{$service->run($site,7,$uid,true);throw new LogicException('permission must deny');}catch(RuntimeException $expected){}
ensure($read()['allocation']['status']==='settled','permission error must not erase existing receipt');$fake->denied=false;
$fake->sample['allocations']=[]; $fake->sample['applied_amount']='0.00'; $fake->sample['remaining_amount']='100.00';
$service->run($site,7,$uid,true);ensure($read()['allocation']['status']==='unsettled','verified zero differs from unknown');
$fake->sample=$f['evidence'];$fake->sample['account_id']='different-book';
$service->run($site,7,$uid,true);ensure($read()['allocation']['status']==='pending','wrong book cannot close purchase');
Db::name('erp_integration_connection')->where('id',1)->update(['status'=>'disabled']);
$service->run($site,7,$uid,true); ensure($fake->links===1 && (int)Db::name('erp_integration_sync_task')->count()===1,'paused read never submits');
try { $service->run($site,7,$uid,false); throw new LogicException('Duplicate link accepted'); } catch(RuntimeException $expected) {}
$con=new Connection(); $fake->profile=$profile+['checked'=>true,'app_secret'=>'must-not-leave-fixture'];
$params=static function(array $args) use($app,$site,$uid) {
    $r=new app\Request();$r->withServer(['REQUEST_METHOD'=>'GET'])->withGet($args);$r->siteId($site);$r->uid($uid);$app->instance('request',$r);
};
$fields=['adapter_code'=>'boyuan','adapter_name'=>'测试','sync_mode'=>'manual','business_types'=>['purchase','payment'],'config'=>['api_url'=>'https://ignore.invalid','app_secret'=>'ignore']];
$params($fields);$saved=$con->add()->getData();ensure($saved['code']===1,'bind configured vendor without resubmitting secrets');
$id=(int)$saved['data']['id'];$savedCfg=Db::name('erp_integration_connection')->where('id',$id)->value('config');
ensure(strpos($savedCfg,'fixture.invalid')!==false && strpos($savedCfg,'secret')===false && strpos($savedCfg,'ignore')===false,'only server-selected destination stored, credentials not copied');
$params(['status'=>'enabled']);ensure(!$con->changeStatus($id)->getData()['code'],'unverified disabled');
ensure($con->testConnection($id)->getData()['code']===1 && $con->changeStatus($id)->getData()['code']===1,'verify identity then explicitly start capture');
$params($fields);$next=$con->add()->getData();$second=(int)$next['data']['id'];$params(['status'=>'enabled']);
ensure($con->testConnection($second)->getData()['code']===1 && !$con->changeStatus($second)->getData()['code'],'same business cannot acquire a second destination');
$fake->profile['mode']='production';$params(array_replace($fields,['business_types'=>['sale']]));
ensure(!$con->add()->getData()['code'],'production sale remains closed even if sent via HTTP');
if(in_array('--show',$argv,true)) echo json_encode(['样例'=>'模拟回执，仅内存测试库，非博远正式契约','回读'=>$demo], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
echo "PASS: simulated settlement -> isolated task DB -> detail API; no-contract, partial, verified close, verified zero, wrong account, pause and no duplicate. No live calls.\n";
