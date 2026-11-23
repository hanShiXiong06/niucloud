<?php
declare(strict_types=1);
// Local, isolated SQLite :memory: only. No .env, API,queue or business data.
use addon\hsx_boyuan\app\service\{BridgeMasterSelections,DocumentResultService,FinanceResultService,PaymentSyncService,PurchaseSyncService};
use addon\hsx_erp_integration\app\service\BoundManualMappings;
use addon\hsx_erp\app\service\admin\ErpPurchaseExtensionService;
use think\facade\Db;

if(PHP_SAPI!=='cli' || !extension_loaded('pdo_sqlite')) exit(2);
$root=dirname(__DIR__,3);
require $root.'/vendor/autoload.php';
require_once $root.'/vendor/topthink/framework/src/helper.php';
$app=new think\App($root);
$app->config->set(['default'=>'isolated','connections'=>['isolated'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
$app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/erp-inline-fixture-cache/']]],'cache');
$app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/erp-inline-fixture-log/']]],'log');
final class FixtureEnabledAddons {public function getAddonKeysBySiteId($site):array{return $site===4?['hsx_erp_integration']:[];}}
class_alias(FixtureEnabledAddons::class,'app\service\core\site\CoreSiteService');
final class FixtureCurrentProfile {public function get($site,$public):array{return ['mode'=>'test','profiles'=>['test'=>['connection_key'=>'U','endpoint'=>'https://uat.invalid/api/','gateway_path'=>'/gateway.do','account_id'=>'U','app_id'=>'dummy','probe_at'=>10]]];}}
class_alias(FixtureCurrentProfile::class,'addon\hsx_boyuan\app\service\ConfigService');
$n=0; function expectInline(bool $ok,string $why):void { global $n; if(!$ok) throw new RuntimeException($why); ++$n; }
$app->event->listen('HsxErpPurchaseActions',static fn()=>['actions'=>[
    ['key'=>'one','pc_route'=>'/a/b','query'=>['purchase_source_id'=>7,'detail_extension'=>'supplier_test','task_id'=>88]],
    ['key'=>'wrongdoc','pc_route'=>'/a/b','query'=>['purchase_source_id'=>8,'detail_extension'=>'supplier_test']],
    ['key'=>'path','pc_route'=>'/a/b','query'=>['purchase_source_id'=>7,'detail_extension'=>'../bad']],
]]);
$action=ErpPurchaseExtensionService::detail(4,['id'=>7,'purchase_no'=>'P7'])['integration_actions'];
expectInline($action[0]['query']===['detail_extension'=>'supplier_test','purchase_source_id'=>'7'],'host only allows real source in declared extension');
expectInline(!isset($action[1]['query'],$action[2]['query']),'no cross-document or arbitrary component path');
$target=['mode'=>'test','api_url'=>'https://uat.invalid/api/','app_id'=>'dummy','gateway_path'=>'/gateway.do','account_code'=>'U'];
Db::execute('CREATE TABLE erp_integration_connection(id INTEGER,site_id INTEGER,adapter_code TEXT,sync_mode TEXT,status TEXT,business_types TEXT,config TEXT)');
Db::execute('CREATE TABLE erp_integration_mapping(site_id INTEGER,connection_id INTEGER,resource_type TEXT,auto_matched INTEGER,local_id INTEGER,external_id TEXT,external_code TEXT)');
foreach([[1,4,'test'],[2,5,'test'],[3,4,'production']] as [$id,$site,$mode]) Db::name('erp_integration_connection')->insert(['id'=>$id,'site_id'=>$site,'adapter_code'=>'boyuan','sync_mode'=>'manual','status'=>'enabled','business_types'=>'["purchase"]','config'=>json_encode(array_replace($target,['mode'=>$mode]))]);
expectInline(BoundManualMappings::connection(4,'boyuan','purchase',$target)===1 && BoundManualMappings::connection(4,'boyuan','payment',$target)===0,'same adapter, exact tenant/environment/account/business');
expectInline(BoundManualMappings::connection(4,'boyuan','purchase',array_replace($target,['app_id'=>'another']))===0,'other application cannot donate staff mappings');
foreach([[4,1,12,'','E12'],[4,1,18,'shadow','E18'],[4,3,18,'other','E12'],[5,1,44,'foreign','E12']] as [$site,$conn,$uid,$id,$code])
    Db::name('erp_integration_mapping')->insert(['site_id'=>$site,'connection_id'=>$conn,'resource_type'=>'staff','auto_matched'=>0,'local_id'=>$uid,'external_id'=>$id,'external_code'=>$code]);
$cat=[['external_id'=>'uid12','code'=>'E12'],['external_id'=>'shadow','code'=>'E18']];
$direct=BoundManualMappings::rows(4,1,'staff',[12]);
$owners=BoundManualMappings::staffOwners(4,1,['uid12','shadow'],['E12','E18']);
expectInline(count($direct)===1 && count($owners)===2,'id/code reverse ownership tenant and connection isolated');
$adapter=static fn($native,$d,$cat,$owners)=>BridgeMasterSelections::combine($native,'staff',[12=>'staff:12'],$d,$cat,$owners)['staff:staff:12'];
expectInline($adapter([],$direct,$cat,$owners)==='uid12','explicit bridge code resolves only to unique current external ID');
expectInline($adapter(['staff:staff:12'=>'different'],$direct,$cat,$owners)==='','conflicting native and bridge identities require selection');
expectInline($adapter([],$direct,array_merge($cat,[['external_id'=>'uid22','code'=>'E12']]),$owners)==='','ambiguous code is not guessed');
expectInline($adapter(['staff:staff:12'=>'shadow'],[],$cat,$owners)==='','cannot use an external employee already assigned to a different local person');
expectInline($adapter([],$direct,[],$owners)==='','disabled or out-of-date mapping omitted');
Db::execute('CREATE TABLE hsx_boyuan_reference_sync(id INTEGER,site_id INTEGER,connection_key TEXT,resource TEXT,status TEXT,run_key TEXT)');
Db::execute("INSERT INTO hsx_boyuan_reference_sync VALUES(1,4,'U','staff','completed','r1')");
Db::execute('CREATE TABLE hsx_boyuan_md_staff(site_id INTEGER,connection_key TEXT,external_id TEXT,code TEXT,enabled INTEGER,seen_run TEXT)');
Db::execute("INSERT INTO hsx_boyuan_md_staff VALUES(4,'U','uid12','E12',1,'r1'),(4,'U','retired','E12',1,'old'),(4,'U','shadow','E18',1,'r1')");
expectInline((BridgeMasterSelections::merge(4,'U','purchase',['staff:12'],[])['staff:staff:12']??null)==='uid12','adapter end-to-end selects only one current same-ledger staff code');
expectInline((BridgeMasterSelections::merge(4,'U','purchase',['staff:14'],['staff:staff:14'=>'shadow'])['staff:staff:14']??null)==='','native staff used by different bridge UID is withheld');
expectInline(BridgeMasterSelections::merge(4,'other','purchase',['staff:12'],[])===[] && BridgeMasterSelections::merge(5,'U','purchase',['staff:12'],[])===[],'foreign target and uninstalled site have no bridge candidates');
Db::execute("UPDATE erp_integration_connection SET status='disabled' WHERE id=1");
expectInline(BoundManualMappings::connection(4,'boyuan','purchase',$target)===0,'paused bridge does not supply defaults');

Db::execute('CREATE TABLE hsx_boyuan_task(id INTEGER,site_id INTEGER,mode TEXT,connection_key TEXT,kind TEXT,business_no TEXT,fact_key TEXT,operation_id TEXT,status TEXT,side_effect TEXT,resolution TEXT,external_no TEXT,snapshot_json TEXT,request_json TEXT,result_json TEXT,update_at INTEGER)');
$src=['kind'=>'purchase','source_document_id'=>'purchase:7','source_document_no'=>'PO7','batch_ref'=>'inbound','lines'=>[['source_line_id'=>'purchase_line:2','device_ref'=>'asset:3']]];
Db::name('hsx_boyuan_task')->insert(['id'=>43,'site_id'=>4,'mode'=>'test','connection_key'=>'U','kind'=>'purchase','business_no'=>'PO7','fact_key'=>'purchase:7:inbound',
    'status'=>'succeeded','side_effect'=>'applied','external_no'=>'PUR-T','snapshot_json'=>json_encode($src),'request_json'=>json_encode(['schema'=>PurchaseSyncService::SCHEMA]),
    'result_json'=>json_encode(['external_no'=>'PUR-T','verified'=>true,'verification_level'=>'purchase_imei_records_matched','external_id'=>'ph'])]);
$c=['mode'=>'test','enabled'=>true,'purchase_enabled'=>true,'paused'=>false,'profiles'=>['test'=>['connection_key'=>'U']]];
$f=[4=>['asset_id'=>3,'line_id'=>2,'document_id'=>7,'number'=>'PO7','settlements'=>[17]]];
$l=(new FinanceResultService())->links(4,'payable',$f,$c)[4];
expectInline($l[0]['query']['detail_extension']==='hsx_boyuan' && $l[0]['query']['purchase_task_id']===43,'purchase is accessible inside original');
expectInline($l[1]['query']===['detail_extension'=>'hsx_boyuan','kind'=>'payment','purchase_task_id'=>43,'settlement_id'=>17,'payment_task_id'=>0],'unregistered voucher points to exact device and settlement only');
$f[4]['line_id']=999;
expectInline(empty((new FinanceResultService())->links(4,'payable',$f,$c)[4][1]['query']),'another device cannot start a voucher from the wrong parent');
$paySrc=['source_task_id'=>43,'source_snapshot_hash'=>hash('sha256',json_encode($src)),'source_document_id'=>'purchase:7','source_document_no'=>'PO7',
    'settlement_id'=>17,'settlement_no'=>'Y17','lines'=>[['payable_id'=>4,'source_line_id'=>'purchase_line:2','device_ref'=>'asset:3']]];
$receipt=['created'=>true,'payment_registered'=>true,'verified'=>false,'external_no'=>'PAY-T','external_id'=>'yh','verification_level'=>'payment_create_receipt_matched'];
Db::name('hsx_boyuan_task')->insert(['id'=>64,'site_id'=>4,'mode'=>'test','connection_key'=>'U','kind'=>PaymentSyncService::KIND,'business_no'=>'Y17','fact_key'=>'purchase-payment:settlement:17',
    'status'=>PaymentSyncService::REGISTERED,'external_no'=>'PAY-T','snapshot_json'=>json_encode($paySrc),'request_json'=>json_encode(['schema'=>PaymentSyncService::SCHEMA]),'result_json'=>json_encode($receipt)]);
$f[4]['line_id']=2;
$both=(new FinanceResultService())->links(4,'payable',$f,$c)[4];
expectInline(array_column($both,'external_no')===['PUR-T','PAY-T'] && $both[1]['query']['purchase_task_id']===43 && $both[1]['query']['payment_task_id']===64,'current receipt opens the existing voucher, not another registration');
expectInline(str_contains($both[1]['next_step'],'不代表') && $both[1]['status']==='registered','a payment registration is not allocation proof');
$f[4]['line_id']=999;
expectInline(empty((new FinanceResultService())->links(4,'payable',$f,$c)[4][1]['query']),'mismatched payment does not advertise action');
$pilotSite=\addon\hsx_boyuan\app\support\ProductionPurchasePilot::SITE;
$pilotProfile=['protocol'=>'openapi_v153','endpoint'=>\addon\hsx_boyuan\app\support\ProductionVoucher::ENDPOINT,'gateway_path'=>'/gateway.do',
    'account_id'=>'89096','app_id'=>'fixture','connection_key'=>'P','app_secret_configured'=>true,'probe_at'=>10,'contract_confirmed'=>true];
$pilotCfg=['mode'=>'production','purchase_mode'=>'manual','profiles'=>['production'=>$pilotProfile]];
$read=new DocumentResultService();
$fixtureRows=$read->rows($pilotSite,'purchase',88,'P88',$pilotCfg);
$hint=$fixtureRows[0]; $original=static fn()=>ErpPurchaseExtensionService::detail($pilotSite,['id'=>88,'purchase_no'=>'P88'])['integration_results'];
$app->event->listen('HsxErpPurchaseActions',static function() use (&$fixtureRows){return ['results'=>$fixtureRows];});
expectInline(count($original())===1 && str_contains($original()[0]['next_step'],'开启同步'),'keep the administration instruction when no bridge task is visible');
$row=['key'=>'bridge_42','task_id'=>42,'status'=>'awaiting_confirmation','query'=>['kind'=>'purchase'],'purchase_target'=>$hint['fallback_target']];
$fixtureRows=[$hint,$row]; expectInline(array_column($original(),'key')===['bridge_42'],'exact original and ledger: show real bridge task, not contradictory empty hint');
$receipt=['key'=>'vendor_7','task_id'=>7,'status'=>'succeeded','external_no'=>'PUR-7'];
$fixtureRows=[$row,$receipt,$hint]; expectInline(array_column($original(),'key')===['bridge_42','vendor_7'] && $original()[1]['external_no']==='PUR-7','order-independent and keeps the real vendor receipt');
$fixtureRows=[$hint,array_replace($row,['purchase_target'=>str_repeat('f',64)])];expectInline(count($original())===2,'another ledger cannot hide a hint');
$fixtureRows=[$hint,array_replace($row,['query'=>['kind'=>'payment']])];expectInline(count($original())===2,'a payment is not evidence of a purchase task');
echo "PASS: $n inline original identity, scope and master-mapping checks (SQLite memory).\n";
