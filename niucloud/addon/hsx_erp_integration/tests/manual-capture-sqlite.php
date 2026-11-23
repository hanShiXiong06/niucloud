<?php
declare(strict_types=1);
// Isolated ThinkPHP SQLite in memory; no environment, account, client, worker or real business tables.
use addon\hsx_erp_integration\app\service\DomainCaptureService;
use addon\hsx_erp_integration\app\support\{BridgeFact,BridgeTarget};
use think\facade\Db;

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
require_once $root . '/vendor/topthink/framework/src/helper.php';
require_once $root . '/app/common.php';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR,"Requires pdo_sqlite; never falls back to a shared DB\n"); exit(2); }
$app = new think\App($root);
$app->config->set(['default'=>'isolated','connections'=>['isolated'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
$app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/erp-bridge-test-cache/'.getmypid().'/']]],'cache');
$app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/erp-bridge-test-log/']]],'log');
(new think\service\ModelService($app))->boot();
Db::execute('CREATE TABLE erp_integration_connection(id INTEGER PRIMARY KEY,site_id INTEGER,adapter_code TEXT,status TEXT,sync_mode TEXT,business_types TEXT,last_test_result TEXT,config TEXT,bridge_started_at INTEGER)');
Db::execute('CREATE TABLE erp_integration_sync_task(id INTEGER PRIMARY KEY,site_id INTEGER,connection_id INTEGER,business_type TEXT,source_type TEXT,source_id INTEGER,source_no TEXT,status TEXT,payload TEXT,origin_event_id TEXT,fact_key TEXT,target_key TEXT,receipt_json TEXT,external_doc_no TEXT,error_code TEXT,error_message TEXT,created_at INTEGER,updated_at INTEGER,UNIQUE(site_id,target_key,fact_key),UNIQUE(site_id,target_key,origin_event_id))');
$checks=0;
function ok($cond,string $label):void { global $checks; if (!$cond) throw new RuntimeException($label); ++$checks; }
function refuses(callable $fn,string $label):void { try {$fn();} catch (\DomainException|\InvalidArgumentException $e) {ok(true,$label);return;} throw new RuntimeException($label); }
function connection(int $site,string $mode='manual',string $account='A'):array {
    return ['site_id'=>$site,'adapter_code'=>'fixture','status'=>'enabled','sync_mode'=>$mode,'business_types'=>'["purchase","payment","sale"]',
        'last_test_result'=>'success','config'=>json_encode(['mode'=>'test','api_url'=>'https://fixture.invalid/api','gateway_path'=>'/gateway.do','account_code'=>$account]),
        'bridge_started_at'=>100];
}
function eventFixture(string $name,string $aggregate,int $id,array $p,string $event,int $site=11):array {
    return ['site_id'=>$site,'event_name'=>$name,'event_version'=>1,'event_id'=>$event,'aggregate_type'=>$aggregate,'aggregate_id'=>$id,'occurred_at'=>101,
        'operator'=>['id'=>12],'source'=>['plugin'=>'hsx_erp','type'=>$aggregate,'id'=>$id],'payload'=>$p];
}
$conn=connection(11); $cfg=json_decode($conn['config'],true);
$alias=$cfg; $alias['api_url']='https://FIXTURE.invalid:443/api/'; $alias['app_secret']='irrelevant-test-rotation';
ok(BridgeTarget::key($conn)===BridgeTarget::key(array_merge($conn,['config'=>$alias])),'normalized target without credentials or connection ID');
ok(BridgeTarget::key($conn)!==BridgeTarget::key(array_merge($conn,['config'=>array_merge($cfg,['mode'=>'production'])])),'environments isolated');
refuses(fn()=>BridgeTarget::key(array_merge($conn,['config'=>array_merge($cfg,['mode'=>'unexpected'])])),'unknown mode denied');
$capture=new DomainCaptureService(static fn(int $site,string $adapter)=>$adapter==='fixture');
$purchase=eventFixture(BridgeFact::PURCHASE,'purchase',8,['kind'=>'purchase','source_document_id'=>'purchase:8','source_document_no'=>'P8',
    'batch_ref'=>'first','lines'=>[['source_line_id'=>'purchase_line:4','imei'=>'000000000000001','amount'=>'3.20']]],'EV1');
$legacy=Db::name('erp_integration_connection')->insertGetId(connection(11,'async')); Db::name('erp_integration_connection')->insert(connection(12));
ok($capture->accept($purchase)===[],'legacy connection/new event and foreign tenant do not capture');
$cid=Db::name('erp_integration_connection')->insertGetId($conn);
refuses(fn()=>$capture->accept($purchase),'old/new enabled together cannot dispatch');
Db::name('erp_integration_connection')->where('id',$legacy)->update(['status'=>'disabled']);
$older=$purchase;$older['occurred_at']=99;
ok($capture->accept($older)===[],'never auto-backfill a fact predating opt-in');
$tagged=$purchase;$tagged['_delivery']=['internal'=>'test'];
$first=$capture->accept($tagged); $id=$first['task_ids'][0];
function row($id):array {return Db::name('erp_integration_sync_task')->where('id',$id)->find();}
$r=row($id);
ok($r['status']==='awaiting_confirmation' && $r['error_message']===null && (int)$r['connection_id']===$cid && $r['fact_key']==='purchase:8:first','only a local awaiting-confirmation task');
ok(json_decode($r['payload'],true)===$purchase,'original full envelope frozen and dispatch bookkeeping excluded');
ok($capture->accept($purchase)['status']==='duplicate','same original event returns same task');
$same=$purchase;$same['event_id']='DIFFERENT_DELIVERY';
ok($capture->accept($same)['task_ids']===[$id],'same business fact with alternate delivery id cannot add a task');
$changed=$same;$changed['payload']['lines'][0]['amount']='3.21';
refuses(fn()=>$capture->accept($changed),'changed content cannot overwrite original event');
Db::name('erp_integration_sync_task')->where('id',$id)->update(['status'=>'unverified','receipt_json'=>'{"vendor_task_id":72}']);
ok($capture->accept($purchase)['task_ids']===[$id] && row($id)['status']==='unverified','unknown stays frozen on replay');
$same['event_id']='EV2';$same['payload']['batch_ref']='second';
$overlap=row($capture->accept($same)['task_ids'][0]);
ok($overlap['status']==='failed' && $overlap['error_code']==='BRIDGE_SOURCE_REVIEW','overlapping lines never sent a second time');
$newBatch=$purchase;$newBatch['event_id']='EV3';$newBatch['payload']['batch_ref']='third';$newBatch['payload']['lines'][0]['source_line_id']='purchase_line:9';
$newId=$capture->accept($newBatch)['task_ids'][0];ok(row($newId)['status']==='awaiting_confirmation','disjoint batch is independently addressable');
$collision=$newBatch;$collision['event_id']='EV1';$collision['payload']['batch_ref']='collision';
refuses(fn()=>$capture->accept($collision),'same event cannot describe another fact');
$invalid=eventFixture(BridgeFact::INVALIDATED,'purchase',8,['original_document_ref'=>'purchase:8'],'VOID');
$capture->accept($invalid);
ok(row($newId)['status']==='failed' && row($id)['status']==='unverified' && row($id)['receipt_json']==='{"vendor_task_id":72}','void preserves previous receipt and stops pending task');
$part=['target_type'=>'payable','source_type'=>'purchase_asset','target_id'=>29,'source_document_ref'=>'purchase:8','source_line_ref'=>'purchase_line:4','applied_amount'=>'3.20'];
$payment=eventFixture(BridgeFact::PAYMENT,'settlement',55,['settlement_id'=>55,'settlement_no'=>'S55','settlement_type'=>'payment','cash_direction'=>'out','confirmed_at'=>100,'amount'=>3.20,'targets'=>[$part]],'EV-P');
$p=row($capture->accept($payment)['task_ids'][0]);
ok($p['fact_key']==='settlement:55' && $p['status']==='awaiting_confirmation' && $capture->accept($payment)['status']==='duplicate','registered original outgoing payment only, repeat is idempotent');
$receipt=$payment;$receipt['payload']['settlement_type']='receipt';
ok($capture->accept($receipt)===[],'incoming receipt never treated as outgoing payment');
$mixed=$payment;$mixed['source']['id']=$mixed['aggregate_id']=$mixed['payload']['settlement_id']=56;$mixed['event_id']='EV-P2';
$mixed['payload']['targets'][]=['target_id'=>30,'target_type'=>'receivable','applied_amount'=>'1.00'];
$p2=row($capture->accept($mixed)['task_ids'][0]);ok($p2['status']==='failed' && count(json_decode($p2['payload'],true)['payload']['targets'])===2,'reject whole mixed settlement with evidence, never trim rows');
$wrong=$payment;$wrong['source']['id']=$wrong['aggregate_id']=$wrong['payload']['settlement_id']=57;$wrong['event_id']='EV-P3';$wrong['payload']['amount']='3.21';
ok(row($capture->accept($wrong)['task_ids'][0])['error_code']==='BRIDGE_SOURCE_REVIEW','all allocations must reconcile exactly');
$other=$payment;$other['site_id']=12;
ok($capture->accept($other)['status']==='processed','tenant has its own fact and destination');
$saleLine=['source_line_id'=>'sale_line:14','device_ref'=>'asset:11','imei'=>'00000999','inbound_line_ref'=>'purchase_line:4','amount'=>'4.20','quantity'=>'1.000','ownership_type'=>'owned'];
$sale=eventFixture(BridgeFact::PURCHASE,'sale',60,['kind'=>'sale','sale_type'=>'wholesale','party'=>['ref'=>'party:6'],'payment_method_ref'=>'method:挂账',
    'source_document_id'=>'sale:60','source_document_no'=>'S-60','batch_ref'=>hash('sha256','sale_line:14'),'lines'=>[$saleLine]],'SALE');
$sId=$capture->accept($sale)['task_ids'][0];ok(row($sId)['status']==='awaiting_confirmation' && row($sId)['source_type']==='erp_sale','confirmed, pure device, credit wholesale source only');
$rconn=connection(11); $rconn['business_types']='["receipt"]'; $rconn['bridge_started_at']=110;
$rcid=Db::name('erp_integration_connection')->insertGetId($rconn);
$received=eventFixture(BridgeFact::PAYMENT,'settlement',70,['settlement_id'=>70,'settlement_no'=>'ST70','settlement_type'=>'receipt','cash_direction'=>'in','amount'=>2,'confirmed_at'=>101,'capital_account_id'=>8,'party_id'=>6,
    'targets'=>[['source_type'=>'sale','source_document_ref'=>'sale:60','source_id'=>60,'source_no'=>'S-60','target_type'=>'receivable','target_id'=>80,'applied_amount'=>2]]],'EV-RECEIPT');
ok($capture->accept($received)===[],'automatic capture never sweeps pre-enable receipts');
refuses(fn()=>$capture->acceptOriginalSaleReceipt($received,60),'original sale must have a verified external document');
Db::name('erp_integration_sync_task')->where('id',$sId)->update(['status'=>'succeeded','receipt_json'=>'{"status":"confirmed","vendor_task_id":4,"external_no":"S-REMOTE"}','external_doc_no'=>'S-REMOTE']);
refuses(fn()=>$capture->acceptOriginalSaleReceipt($received,61),'wrong source cannot claim the voucher');
$differentTarget=$rconn; $differentTarget['config']=connection(11,'manual','another')['config'];
Db::name('erp_integration_connection')->where('id',$rcid)->update(['config'=>$differentTarget['config']]);
refuses(fn()=>$capture->acceptOriginalSaleReceipt($received,60),'verified sale from another account is insufficient');
Db::name('erp_integration_connection')->where('id',$rcid)->update(['config'=>$rconn['config']]);
$rid=$capture->acceptOriginalSaleReceipt($received,60)['task_ids'][0];
ok(row($rid)['source_no']==='ST70' && row($rid)['status']==='awaiting_confirmation' && $capture->acceptOriginalSaleReceipt($received,60)['task_ids']===[$rid],'single explicit receipt recovery is idempotent, no send');
$split=$received; $split['payload']['targets'][]=$split['payload']['targets'][0];
refuses(fn()=>$capture->acceptOriginalSaleReceipt($split,60),'no repeated/cross-document allocations');
ok($capture->accept($sale)['task_ids']===[$sId],'sale replay never resubmits');
$retail=$sale;$retail['payload']['sale_type']='retail';ok($capture->accept($retail)===[],'retail not eligible');
$cash=$sale;$cash['payload']['payment_method_ref']='method:现结';ok($capture->accept($cash)===[],'cash sale not eligible');
$mixSale=$sale;$mixSale['event_id']='MIX-S';$mixSale['aggregate_id']=$mixSale['source']['id']=61;$mixSale['payload']['source_document_id']='sale:61';
$mixSale['payload']['lines'][]=['source_line_id'=>'sale_line:15','device_ref'=>'asset:0','quantity'=>'1','amount'=>'0.50'];
$mixSale['payload']['batch_ref']=hash('sha256','sale_line:14|sale_line:15');
$s2=row($capture->accept($mixSale)['task_ids'][0]);ok($s2['error_code']==='BRIDGE_SOURCE_REVIEW' && count(json_decode($s2['payload'],true)['payload']['lines'])===2,'mixed non-device line blocks entire sale');
$return=eventFixture(BridgeFact::PURCHASE,'sale_return',77,['kind'=>'sale_return','original_document_ref'=>'sale:60','source_document_id'=>'sale_return:77'],'RET-S');
$capture->accept($return);ok(row($sId)['error_code']==='BRIDGE_SOURCE_INVALIDATED','return blocks original sale; no new accounting task');
$unsupported=new DomainCaptureService(static fn(int $site,string $adapter,string $kind,string $mode)=>$kind!=='sale');
refuses(fn()=>$unsupported->accept($sale),'route cannot capture an unsupported vendor operation');
$double=Db::name('erp_integration_connection')->insertGetId($conn);
refuses(fn()=>$capture->accept($payment),'same destination configured twice is not silently broadcast');
Db::name('erp_integration_connection')->where('id',$double)->update(['status'=>'disabled']);
try { unset($r['id']); Db::name('erp_integration_sync_task')->insert($r); throw new RuntimeException('unique index unexpectedly allowed duplicate'); }
catch (think\db\exception\PDOException $expected) { ok(true,'DB enforces fact/event uniqueness'); }
if (class_exists(addon\hsx_boyuan\app\adapter\BoyuanErpAdapter::class)) {
    Db::execute('CREATE TABLE addon(id INTEGER PRIMARY KEY,key TEXT,title TEXT,icon TEXT,desc TEXT,status INTEGER,type TEXT,support_app TEXT)');
    Db::execute('CREATE TABLE site(id INTEGER PRIMARY KEY,site_id INTEGER,group_id INTEGER,app_type TEXT,addons TEXT)');
    Db::name('addon')->insert(['key'=>'hsx_boyuan','status'=>1,'title'=>'Fixture','type'=>'addon']);
    Db::name('site')->insert(['site_id'=>23,'group_id'=>0,'app_type'=>'site','addons'=>'["hsx_boyuan","hsx_erp_integration"]']);
    Db::name('erp_integration_connection')->insert(['adapter_code'=>'boyuan']+connection(23));
    $forWorker=$sale;$forWorker['site_id']=23;
    $registered=new addon\hsx_erp_integration\app\listener\OriginalDocumentRecorded();
    $out=$registered->handle($forWorker);
    ok(($out['status']??'')==='processed','actual plugin discovery from an outbox worker without request site or login: '.($out['message']??''));
    $hooks=require __DIR__.'/../app/event.php';
    $outboxClass=$hooks['listen']['HsxErpLocalOutboxStored'][0];
    $again=(new $outboxClass())->handle($forWorker);
    ok($again['status']==='duplicate' && $again['task_ids']===$out['task_ids'],'local outbox hook and worker share one original task');
}
echo "PASS $checks isolated local capture and identity checks (no ERP business writes / network).\n";
