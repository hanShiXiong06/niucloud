<?php
declare(strict_types=1);
// Real ThinkPHP models/controllers against transient SQLite only. No app bootstrap, no .env, no remote transport.
use think\facade\Db;
use addon\hsx_erp_integration\app\adminapi\controller\{Connection, Mapping, Task, Failure};
use addon\hsx_erp_integration\app\model\{ErpIntegrationConnection, ErpIntegrationSyncTask, ErpIntegrationFailure};
use addon\hsx_erp_integration\app\support\ConnectionFields;
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php'; require_once $root . '/vendor/topthink/framework/src/helper.php'; require_once $root . '/app/common.php';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "Requires pdo_sqlite; no DB fallback.\n"); exit(2); }
$app = new think\App($root);
$app->config->set(['default'=>'isolated','auto_timestamp'=>true,'connections'=>['isolated'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
$app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/erp-ui-test-cache/']]],'cache');
$app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/erp-ui-test-log/']]],'log');
$app->env->set('app.auth_key', bin2hex(random_bytes(32)));
(new think\service\ModelService($app))->boot();
function ok($condition, $name): void { if (!$condition) throw new RuntimeException($name); }
function scope(int $site, array $args=[]): void {
    $req = new app\Request(); $req->withServer(['REQUEST_METHOD'=>'GET'])->withGet($args);
    $req->siteId($site); $req->uid(91); app()->instance('request', $req);
}
function data($response) { $r=$response->getData(); ok($r['code']===1,'api: '.$r['msg']); return $r['data']; }
scope(11);
foreach ([
    'addon'=>'id,key,title,icon,desc,status,type,support_app',
    'site'=>'id,site_id,group_id,app_type,addons',
    'erp_integration_connection'=>'id,site_id,adapter_code,adapter_name,status,config,business_types,sync_mode,retry_times,last_test_at,last_test_result,created_at,updated_at',
    'erp_integration_mapping'=>'id,site_id,connection_id,resource_type,local_id,local_code,local_name,external_code,external_name,external_id,auto_matched,created_at,updated_at',
    'erp_integration_sync_task'=>'id,site_id,connection_id,business_type,source_no,status,payload,external_doc_no,external_doc_id,error_message,error_code,succeeded_at,created_at,updated_at',
    'erp_integration_failure'=>'id,site_id,task_id,connection_id,source_no,business_type,failure_type,error_detail,resolved,resolved_at,resolved_by,resolution_note,created_at',
    'erp_warehouse'=>'id,site_id,warehouse_code,warehouse_name,status'
] as $table=>$columns) {
    $cols=[]; foreach(explode(',',$columns) as $c) $cols[]='"'.$c.'" '.($c==='id'?'INTEGER PRIMARY KEY':(in_array($c,['site_id','resolved','created_at','updated_at','connection_id','task_id','local_id','resolved_by','resolved_at'])?'INTEGER':'TEXT'));
    Db::execute("CREATE TABLE $table (".join(',',$cols).')');
}
$fields=[['name'=>'app_secret','label'=>'密钥','type'=>'password']];
$c=ErpIntegrationConnection::create(['site_id'=>11,'adapter_name'=>'A','adapter_code'=>'fixture','config'=>ConnectionFields::seal(['app_secret'=>'dummy-only'],$fields,11,'fixture'),'business_types'=>['purchase'],'status'=>'disabled']);
$scopeId=(int)$c->id;
ErpIntegrationConnection::create(['site_id'=>12,'adapter_name'=>'OTHER','adapter_code'=>'fixture']);
$task=ErpIntegrationSyncTask::create(['site_id'=>11,'connection_id'=>$scopeId,'source_no'=>'ORIG','business_type'=>'purchase','status'=>'succeeded','created_at'=>time(),'payload'=>['secret'=>'demo']]);
ErpIntegrationSyncTask::create(['site_id'=>12,'source_no'=>'OTHER','status'=>'failed']);
ErpIntegrationSyncTask::create(['site_id'=>11,'source_no'=>'ORIG-2','status'=>'succeeded','external_doc_no'=>'EXT-2','created_at'=>time()]);
$fail=ErpIntegrationFailure::create(['site_id'=>11,'task_id'=>$task->id,'failure_type'=>'timeout','resolved'=>0]);
$other=ErpIntegrationFailure::create(['site_id'=>12,'resolved'=>1]);
Db::name('erp_warehouse')->insertAll([['site_id'=>11,'warehouse_code'=>'S11','warehouse_name'=>'本仓','status'=>1],['site_id'=>12,'warehouse_code'=>'S12','warehouse_name'=>'他仓','status'=>1]]);
$con=new Connection(); $r=data($con->lists()); ok($r['count']===1 && !array_key_exists('config',$r['list'][0]),'scoped connection list without config');
ok(data((new Task())->info((int)$task->id))['payload']['secret']==='******','snapshot must be redacted');
scope(11,['status'=>'unverified']); $r=data((new Task())->lists()); ok($r['count']===1 && $r['list'][0]['status']==='unverified','missing receipt not confirmed');
scope(11); $stats=data((new Task())->statistics())['today'];ok($stats['total']===2 && $stats['success']===1 && $stats['unverified']===1,'receipt statistics');
scope(11,['resource_type'=>'warehouse','keyword'=>'仓']);$r=data((new Mapping())->getLocalResources());ok(count($r['list'])===1 && $r['list'][0]['code']==='S11','local lookup is site scoped');
scope(11,['note'=>'已人工确认']); data((new Failure())->resolve((int)$fail->id));
ok((new Failure())->resolve((int)$fail->id)->getData()['code']===0, 'do not overwrite resolved record');
scope(11,['ids'=>[$fail->id,$other->id]]); ok(data((new Failure())->batchDel())['count']===1,'only own resolved record removed');
scope(12); ok((new Task())->info((int)$task->id)->getData()['code']===0,'foreign task unavailable'); ok(data((new Failure())->info((int)$other->id))['resolved']==1,'foreign record retained');
if (is_dir(dirname(__DIR__, 2) . '/hsx_boyuan/app/adapter')) {
    Db::name('addon')->insert(['key'=>'hsx_boyuan','status'=>1,'title'=>'Fixture','type'=>'addon']);
    Db::name('site')->insert(['site_id'=>11,'group_id'=>0,'app_type'=>'site','addons'=>'["hsx_boyuan"]']);
    scope(11);
    ok(data($con->getAdapters())['list'][0]['code']==='boyuan','only configured tenant adapter available');
    $settings=['api_url'=>'https://fixture.invalid/api','gateway_path'=>'/gateway.do','app_id'=>'demo','app_secret'=>'fixture-not-live','account_code'=>'fixture','mode'=>'test'];
    scope(11,['adapter_name'=>'隔离测试','adapter_code'=>'boyuan','business_types'=>['purchase'],'config'=>$settings]);
    $id=(int)data($con->add())['id'];
    $info=data($con->info($id));ok($info['saved_secrets']['app_secret'] && !isset($info['config']['app_secret']) && $info['status']==='disabled','create paused and redact saved credential');
    $settings['app_secret']='';
    scope(11,['adapter_name'=>'新名称','business_types'=>['purchase'],'config'=>$settings]);data($con->edit($id));
    $stored=ErpIntegrationConnection::find($id);ok(ConnectionFields::open(ConnectionFields::values($stored->config),11,'boyuan')['app_secret']==='fixture-not-live','blank edit retains encrypted secret');
    scope(11,['status'=>'enabled']);ok($con->changeStatus($id)->getData()['code']===0,'untested cannot be enabled');
    scope(11,['connection_id'=>$id,'resource_type'=>'warehouse','local_id'=>1,'external_code'=>'EXT-W','external_name'=>'对方仓库']);
    $map=new Mapping();data($map->add());ok($map->add()->getData()['code']===0,'cannot overwrite on create');
    $rid=(int)data($map->lists())['list'][0]['id'];
    scope(11,['external_code'=>'EXT-W2']);data($map->edit($rid));
    scope(12);ok($map->del($rid)->getData()['code']===0,'cannot remove a foreign mapping');
    scope(11);ok($con->del($id)->getData()['code']===0,'preserve used connection');data($map->del($rid));data($con->del($id));
}
echo "PASS: scoped real ORM lists, search, credential exclusion, receipt states and statistics, foreign record protection, resolve and deletion.\n";
