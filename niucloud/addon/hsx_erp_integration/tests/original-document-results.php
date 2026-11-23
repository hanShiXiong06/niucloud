<?php
declare(strict_types=1);
use addon\hsx_erp_integration\app\service\OriginalDocumentResults as Results;
use addon\hsx_erp_integration\app\listener\{OriginalPurchaseActions,OriginalFinanceLinks};
use addon\hsx_erp_integration\app\support\BridgeFact;
use addon\hsx_erp\app\service\admin\ErpPurchaseExtensionService as Host;
use think\facade\Db;
if (PHP_SAPI!=='cli' || !extension_loaded('pdo_sqlite')) exit(2);
$root=dirname(__DIR__,3);
require $root.'/vendor/autoload.php';
require_once $root.'/vendor/topthink/framework/src/helper.php';
final class FixtureAddon { public static array $keys=['hsx_erp_integration']; public function getAddonKeysBySiteId($id) { return self::$keys; } }
final class FixtureRole { public static array $get=['integration/task/<id>','erp/finance/payable/lists']; public static array $post=[]; public function getAuthApiList() { return ['get'=>self::$get,'post'=>self::$post]; } }
class_alias(FixtureAddon::class,'app\service\core\site\CoreSiteService');
class_alias(FixtureRole::class,'app\service\admin\auth\AuthService');
$app=new think\App($root);
$app->config->set(['default'=>'isolated','connections'=>['isolated'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
$app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/erp-document-fixture-cache/']]],'cache');
$app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/erp-document-fixture-log/']]],'log');
$app->instance('request',new app\Request()); request()->siteId(9); request()->uid(42);
$app->event->listen('HsxErpPurchaseActions',OriginalPurchaseActions::class);
$app->event->listen('HsxErpSaleActions',OriginalPurchaseActions::class);
$app->event->listen('HsxErpFinanceDocumentLinks',OriginalFinanceLinks::class);
$checks=0;
function check($condition,$msg) { global $checks; if (!$condition) throw new RuntimeException($msg); ++$checks; }
foreach ([
    'erp_integration_sync_task'=>'id INTEGER PRIMARY KEY,site_id INTEGER,source_type TEXT,source_id INTEGER,source_no TEXT,business_type TEXT,origin_event_id TEXT,fact_key TEXT,target_key TEXT,status TEXT,payload TEXT,receipt_json TEXT,external_doc_no TEXT,connection_id INTEGER,error_code TEXT',
    'erp_purchase_order'=>'id INTEGER,site_id INTEGER,purchase_no TEXT,party_id INTEGER',
    'erp_purchase_item'=>'id INTEGER,site_id INTEGER,purchase_order_id INTEGER,asset_id INTEGER,item_type TEXT',
    'erp_asset'=>'id INTEGER,site_id INTEGER,purchase_item_id INTEGER,purchase_order_id INTEGER',
    'erp_payable'=>'id INTEGER,site_id INTEGER,source_type TEXT,source_id INTEGER,asset_id INTEGER,party_id INTEGER,status TEXT',
    'erp_settlement_link'=>'site_id INTEGER,target_type TEXT,target_id INTEGER,settlement_id INTEGER,applied_amount TEXT',
    'erp_settlement'=>'id INTEGER,site_id INTEGER,settlement_no TEXT,amount TEXT,party_id INTEGER,status TEXT,cash_direction TEXT,settlement_type TEXT,confirmed_at INTEGER',
    'erp_integration_connection'=>'id INTEGER PRIMARY KEY,site_id INTEGER,status TEXT,sync_mode TEXT,business_types TEXT,last_test_result TEXT,bridge_started_at INTEGER,adapter_code TEXT,config TEXT',
] as $t=>$cols) Db::execute("CREATE TABLE $t($cols)");
Db::execute("INSERT INTO erp_purchase_order VALUES(8,9,'PO8',20)");
foreach ([[10,40,70],[11,41,71]] as [$line,$asset,$pid]) {
    Db::execute("INSERT INTO erp_purchase_item VALUES($line,9,8,$asset,'device')");
    Db::execute("INSERT INTO erp_asset VALUES($asset,9,$line,8)");
    Db::execute("INSERT INTO erp_payable VALUES($pid,9,'purchase_asset',$asset,$asset,20,'partial')");
}
Db::execute("INSERT INTO erp_settlement VALUES(3,9,'PAY3','15.00',20,'confirmed','out','payment',111)");
Db::execute("INSERT INTO erp_settlement_link VALUES(9,'payable',71,3,'15.00')");
function addTask($tid,$site,$kind,$id,$p,$receipt=[]) {
    $event=['site_id'=>$site,'event_name'=>$kind==='payment'?BridgeFact::PAYMENT:BridgeFact::PURCHASE,'event_version'=>1,'event_id'=>'E-'.$tid,'occurred_at'=>111,
        'aggregate_type'=>$kind==='payment'?'settlement':$kind,'aggregate_id'=>$id,
        'source'=>['plugin'=>'hsx_erp','type'=>$kind==='payment'?'settlement':$kind,'id'=>$id],'payload'=>$p];
    $fact=BridgeFact::project($event,$kind); unset($fact['error_message']);
    Db::name('erp_integration_sync_task')->insert($fact+['id'=>$tid,'site_id'=>$site,'origin_event_id'=>$event['event_id'],'target_key'=>'KEY','status'=>$receipt?'succeeded':'awaiting_confirmation',
        'payload'=>json_encode($event),'receipt_json'=>json_encode($receipt),'external_doc_no'=>$receipt['external_no']??'','connection_id'=>1]);
}
$p=['kind'=>'purchase','source_document_id'=>'purchase:8','source_document_no'=>'PO8','batch_ref'=>'A','lines'=>[['source_line_id'=>'purchase_line:10','device_ref'=>'asset:40']]];
$v=['settlement_type'=>'payment','settlement_id'=>3,'settlement_no'=>'PAY3','cash_direction'=>'out','confirmed_at'=>111,'amount'=>15,
    'targets'=>[['target_type'=>'payable','source_type'=>'purchase_asset','source_id'=>41,'source_document_ref'=>'purchase:8','source_line_ref'=>'purchase_line:11','target_id'=>71,'applied_amount'=>15]]];
addTask(1,9,'purchase',8,$p); addTask(2,10,'purchase',8,$p);
addTask(3,9,'payment',3,$v,['status'=>'confirmed','external_no'=>'V3','vendor_task_id'=>19,'allocation'=>['verified'=>false]]);
$sale=['kind'=>'sale','sale_type'=>'wholesale','payment_method_ref'=>'method:挂账','source_document_id'=>'sale:8','source_document_no'=>'S8',
    'batch_ref'=>hash('sha256','sale_line:9'),'party'=>['ref'=>'party:5'],'lines'=>[['source_line_id'=>'sale_line:9','device_ref'=>'asset:40',
    'quantity'=>'1','ownership_type'=>'owned','imei'=>'000000000000001','inbound_line_ref'=>'purchase_line:10','amount'=>'15.00']]];
addTask(4,9,'sale',8,$sale); addTask(5,10,'sale',8,$sale);
$facts=[70=>['document_id'=>8,'number'=>'PO8','asset_id'=>40,'line_id'=>10,'settlements'=>[3]],71=>['document_id'=>8,'number'=>'PO8','asset_id'=>41,'line_id'=>11,'settlements'=>[3]]];
$svc=new Results();
$rows=$svc->purchase(9,8,'PO8',true);
check(array_column($rows,'task_id')===[1,3],'Original purchase only; foreign site excluded, its payment is included');
check($rows[1]['external_no']==='V3' && str_contains($rows[1]['next_step'],'尚无核销'),'registration without allocation proof');
check($svc->purchase(9,8,'OTHER',true)===[],'wrong original number');
check(array_column($svc->purchase(9,8,'PO8',false),'task_id')===[1],'purchase-only staff do not see payment');
$out=$svc->payables(9,$facts);
check(array_column($out[70],'task_id')===[1] && array_column($out[71],'task_id')===[3],'same purchase and even stale settlement ID cannot attach a sibling voucher');
$wrong=$facts; $wrong[71]['line_id']=10; check(!isset($svc->payables(9,$wrong)[71]),'wrong line');
$wrong=$facts; $wrong[71]['asset_id']=40; check(!isset($svc->payables(9,$wrong)[71]),'wrong device');
$wrong=$facts; $wrong[71]['settlements']=[]; check(!isset($svc->payables(9,$wrong)[71]),'missing confirmed local link');
$orig=['id'=>8,'purchase_no'=>'PO8']; $host=Host::detail(9,$orig);
check(array_column(Host::detail(9,['id'=>8,'sale_no'=>'S8'],true)['integration_results'],'task_id')===[4],'same site+origin sale only; never show other site, purchase or payment');
check(Host::detail(9,['id'=>8,'sale_no'=>'PO8'],true)['integration_results']===[],'wrong sale number');
check($host['integration_results'][1]['query']['detail_extension']==='hsx_erp_integration' && $host['integration_results'][1]['pc_route']==='','sanitized optional inline panel survives original purchase');
$items=[['payable_id'=>70],['payable_id'=>71]];
Host::attachFinance(9,'payable',$items,$facts);
check($items[1]['integration_results'][0]['query']['detail_extension']==='hsx_erp_integration','same contract on finance; native safe extension');
check(($items[1]['integration_results'][0]['query']['kind']??'')==='payment' && ($items[1]['integration_results'][0]['query']['settlement_id']??'')==='3',
    'finance card receives the verified original settlement id, not a sibling task id or amount');
check(Host::detail(10,$orig)['integration_results']===[],'cannot read another site using forged event context');
FixtureRole::$get=['erp/finance/payable/lists']; check(Host::detail(9,$orig)['integration_results']===[],'no task-detail GET permission');
FixtureRole::$get=['integration/task/<id>']; check(count(Host::detail(9,$orig)['integration_results'])===1,'no finance GET permission');
FixtureAddon::$keys=[]; check(Host::detail(9,$orig)['integration_results']===[],'uninstalled bridge remains invisible');
FixtureAddon::$keys=['hsx_erp_integration']; FixtureRole::$get[]='erp/finance/payable/lists';
Db::execute("UPDATE erp_settlement_link SET applied_amount='14.99'");
check(array_column($svc->purchase(9,8,'PO8',true),'task_id')===[1],'source allocation drift not treated as a matched payment');
Db::execute("UPDATE erp_integration_sync_task SET receipt_json='{}' WHERE id=3");
check($svc->payables(9,$facts)[71][0]['external_no']==='' && $svc->payables(9,$facts)[71][0]['status']==='unverified','task succeeded alone is not financial proof');
// Reuse actual persisted source payloads and expose ONLY the single local original captured after the connection started.
Db::execute('ALTER TABLE erp_purchase_order ADD COLUMN status TEXT');
Db::execute("UPDATE erp_purchase_order SET status='completed'");
Db::execute('CREATE TABLE erp_outbox_event(id INTEGER PRIMARY KEY,site_id INTEGER,event_id TEXT,event_name TEXT,occurred_at INTEGER,payload_json TEXT)');
Db::name('erp_integration_connection')->insert(['site_id'=>9,'status'=>'enabled','sync_mode'=>'manual','business_types'=>'["purchase","payment"]','last_test_result'=>'success','bridge_started_at'=>1]);
$memory=Db::connect()->getPdo();
if(method_exists($memory,'createFunction')) $memory->createFunction('JSON_UNQUOTE',fn($v)=>(string)$v,1);
else $memory->sqliteCreateFunction('JSON_UNQUOTE',fn($v)=>(string)$v,1);
foreach([1,3] as $fixture) {
    $e=json_decode(Db::name('erp_integration_sync_task')->where('id',$fixture)->value('payload'),true);
    Db::name('erp_outbox_event')->insert(['site_id'=>9,'event_id'=>$e['event_id'],'occurred_at'=>$e['occurred_at'],'event_name'=>$e['event_name'],'payload_json'=>json_encode($e)]);
}
$recover=new \addon\hsx_erp_integration\app\service\OriginalCaptureRecovery();
check(!$recover->available(9,8),'read-only staff get no recover action');
FixtureRole::$get[]='erp/purchase/lists'; FixtureRole::$post=['integration/task/prepare/<id>'];
check($recover->available(9,8) && $recover->available(9,3,'payment',8),'eligible purchase and exact confirmed original payment can each recover');
check(!$recover->available(9,3,'payment',77) && !$recover->available(10,8),'wrong purchase or tenant never offered');
Db::execute("UPDATE erp_integration_connection SET bridge_started_at=2000000000");
check(!$recover->available(9,8),'does not adopt pre-enablement history');
Db::execute("UPDATE erp_integration_connection SET bridge_started_at=1");
Db::execute("INSERT INTO erp_outbox_event(site_id,event_id,event_name,occurred_at,payload_json) SELECT site_id,event_id,event_name,occurred_at,payload_json FROM erp_outbox_event WHERE id=1");
check(!$recover->available(9,8),'does not pick between two purchase batches');
Db::execute('DROP TABLE erp_integration_sync_task');
$app->event->listen('HsxErpPurchaseActions',static fn()=>['results'=>[['key'=>'another','provider_label'=>'其他扩展','status_label'=>'可用']]]);
check(Host::detail(9,$orig)['integration_results'][0]['key']==='another','bridge failure does not block sibling provider');
echo "PASS: $checks original purchase/payable identity, isolation and extension checks (SQLite memory only).\n";
