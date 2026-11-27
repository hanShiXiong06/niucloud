<?php
declare(strict_types=1);
/** Explicitly authorized UAT purchase acceptance. Never sends payment or production calls. */
if (PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true) || !in_array('--write-one-uat-purchase',$argv,true)) {
    exit("Requires --isolated-local-mysql --write-one-uat-purchase --source-schema=hsx_boyuan_test_<16hex>\n");
}
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,ReferenceService,PurchaseSyncService};
use addon\hsx_boyuan\app\support\{Contract,Problem,Secret};
use addon\hsx_erp\app\service\admin\{ErpPurchaseService,ErpIntegrationService};
use think\facade\Db;

$options=getopt('', ['isolated-local-mysql','write-one-uat-purchase','source-schema:']);
$source=(string)($options['source-schema']??'');
if (!preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$source)) exit("Explicit isolated credential source required\n");
$root=dirname(__DIR__,3); require_once $root.'/vendor/autoload.php';
$probeApp=new think\App($root); $probeApp->loadEnv(); require_once $probeApp->getThinkPath().'helper.php';
$db=require $root.'/config/database.php'; $original=$db['connections'][$db['default']];
if (!in_array($original['hostname']??'', ['localhost','127.0.0.1'],true) || !preg_match('/^[A-Za-z0-9_]+$/D',$original['prefix'])) exit("Local database required\n");
$reader=new PDO('mysql:host=127.0.0.1;port='.$original['hostport'].';dbname='.$source.';charset=utf8mb4',$original['username'],$original['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$q=$reader->prepare('SELECT site_id,value FROM `'.$original['prefix'].'sys_config` WHERE config_key=?'); $q->execute([ConfigService::KEY]);
$saved=$q->fetch(PDO::FETCH_ASSOC); $config=json_decode($saved['value']??'{}',true); $profile=$config['profiles']['test']??[];
if (($profile['endpoint']??'')!=='https://open-api-uat.139erp.com/api' || ($profile['gateway_path']??'')!=='/gateway.do' || empty($profile['app_secret_cipher'])) exit("Confirmed UAT profile required\n");
$report=['scope'=>'one authorized UAT purchase, credit only','production_calls'=>0,'payment_calls'=>0,'business_database_modified'=>false];
$schema=''; $statePath='';
try {
    $secret=Secret::open($profile['app_secret_cipher'],(string)env('app.auth_key',''),(int)$saved['site_id'],$profile['connection_key'].':app_secret');
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install();
    $cs=new ConfigService(); $c=$cs->save($site,1,['revision'=>0,'mode'=>'test','enabled'=>false,'paused'=>true,'finance_enabled'=>false,
        'purchase_enabled'=>false,'purchase_mode'=>'manual','profile'=>['protocol'=>'openapi_v153','endpoint'=>$profile['endpoint'],
            'gateway_path'=>$profile['gateway_path'],'account_id'=>$profile['account_id'],'app_id'=>$profile['app_id'],'app_secret'=>$secret]]);
    unset($secret,$reader,$saved,$config);
    $cs->probe($site,1); $report['account_verified']=true;
    $reference=new ReferenceService(); $resources=['companies','warehouses','products','parties','staff']; $reference->start($site,1,$resources);
    for($round=0;$round<100;$round++) {
        $jobs=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->whereIn('status',['queued','retry','running'])->column('id');
        if (!$jobs) break;
        foreach($jobs as $job) {
            Db::name('hsx_boyuan_reference_sync')->where('id',$job)->update(['next_at'=>0]);
            $reference->page($site,(int)$job);
        }
    }
    foreach($reference->jobs($site) as $job) if(in_array($job['resource'],$resources,true) && ($job['job']['status']??'')!=='completed') throw new RuntimeException('Required UAT references incomplete: '.$job['resource']);
    $key=$cs->get($site)['profiles']['test']['connection_key'];
    $archives=[]; foreach(\addon\hsx_boyuan\app\support\CatalogStore::kinds() as $kind)foreach(\addon\hsx_boyuan\app\support\CatalogStore::query($kind)->where('site_id',$site)->where('connection_key',$key)->where('enabled',1)->select()->toArray() as $a)$archives[]=$a+['resource'=>$kind];
    $companies=[]; $remoteWarehouse=null; $remoteSupplier=null; $remoteProduct=null; $remoteCreator=null;
    foreach($archives as $archive) if($archive['resource']==='companies') $companies[$archive['external_id']]=$archive;
    foreach($archives as $archive) {
        $extra=Contract::decode($archive['extra_json']);
        if(!$remoteWarehouse && $archive['resource']==='warehouses' && isset($companies[$extra['company_id']??''])) $remoteWarehouse=$archive;
        if(!$remoteSupplier && $archive['resource']==='parties' && in_array($extra['is_supplier']??null,[1,'1',true],true)) $remoteSupplier=$archive;
        if(!$remoteProduct && $archive['resource']==='products' && in_array($extra['imei_count']??null,[1,'1'],true) && (string)($extra['imei_len']??'')==='15') $remoteProduct=$archive;
    }
    if(!$remoteWarehouse || !$remoteSupplier || !$remoteProduct) throw new RuntimeException('No suitable enabled UAT archive combination');
    $warehouseExtra=Contract::decode($remoteWarehouse['extra_json']); $productExtra=Contract::decode($remoteProduct['extra_json']);
    foreach($archives as $archive) {
        $extra=Contract::decode($archive['extra_json']);
        if($archive['resource']==='staff' && ($extra['company_id']??'')===$warehouseExtra['company_id']) { $remoteCreator=$archive; break; }
    }
    if(!$remoteCreator) throw new RuntimeException('No enabled UAT creator in the selected company');
    $c=$cs->get($site); $cs->save($site,1,['revision'=>$c['revision'],'mode'=>'test','enabled'=>true,'paused'=>false,'finance_enabled'=>false,
        'purchase_enabled'=>true,'purchase_mode'=>'manual','confirm_account'=>$profile['account_id'],'profile'=>['contract_confirmed'=>true]]);
    $token=strtoupper(bin2hex(random_bytes(4)));
    $supplier=(int)Db::name('erp_party')->insertGetId(['site_id'=>$site,'party_no'=>'UAT_SUP_'.$token,'party_name'=>'UAT采购验收供应商','party_type'=>'supplier','status'=>1]);
    $warehouse=(int)Db::name('erp_warehouse')->insertGetId(['site_id'=>$site,'warehouse_name'=>'UAT采购验收仓','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
    $location=(int)Db::name('erp_warehouse_location')->insertGetId(['site_id'=>$site,'warehouse_id'=>$warehouse,'location_name'=>'UAT测试位','status'=>1]);
    $base='9900'.str_pad((string)random_int(0,9999999999),10,'0',STR_PAD_LEFT); $sum=0;
    for($i=0;$i<14;$i++) { $digit=(int)$base[$i]; if($i%2===1) {$digit*=2; if($digit>9) $digit-=9;} $sum+=$digit; }
    $imei=$base.(string)((10-$sum%10)%10);
    $purchaseId=ErpPurchaseService::forSite($site,1,'UAT采购联调')->create(['request_id'=>'UAT_BETA_'.$token,'party_id'=>$supplier,'party_name'=>'UAT采购验收供应商',
        'settle_method'=>'挂账','items'=>[['imei'=>$imei,'model'=>$productExtra['name']??$remoteProduct['name'],'spec'=>$productExtra['memory']??'',
            'color'=>$productExtra['color']??'','purchase_cost'=>1,'retail_price'=>2,'warehouse_id'=>$warehouse,'location_id'=>$location,'image_urls'=>'["/static/fixture.png"]']]]);
    $integration=ErpIntegrationService::forSite($site,1,'UAT采购联调');
    foreach(Db::name('erp_outbox_event')->where('site_id',$site)->whereIn('status',['pending','failed'])->column('id') as $id) $integration->dispatchDomainEvent((int)$id);
    $service=new PurchaseSyncService(); $entry=$service->capturePurchaseId($site,1,$purchaseId); $taskId=(int)$entry['task_id']; $view=$service->view($site,$taskId);
    if($view['task']['status']!=='awaiting_confirmation') throw new RuntimeException('Manual mode did not await confirmation');
    $selection=['company_id'=>$warehouseExtra['company_id'],'supplier_id'=>$remoteSupplier['external_id'],'creator_id'=>$remoteCreator['external_id'],'buyer_id'=>'','lines'=>[]];
    foreach($view['source']['lines'] as $line) $selection['lines'][]=['source_line_id'=>$line['source_line_id'],'product_id'=>$remoteProduct['external_id'],'warehouse_id'=>$remoteWarehouse['external_id']];
    $input=['revision'=>$view['task']['revision'],'mode'=>'test','connection_key'=>$view['task']['connection_key'],'selection'=>$selection,'remember'=>false];
    $view=$service->saveDraft($site,$taskId,1,$input); $report['draft_saved_without_sending']=$view['task']['status']==='awaiting_confirmation';
    $input['revision']=$view['task']['revision']; $view=$service->submit($site,$taskId,1,$input);
    $old=umask(0077); $statePath=sys_get_temp_dir().'/boyuan-purchase-uat-'.strtolower($token).'.json';
    file_put_contents($statePath,Contract::json(['schema'=>$schema,'site_id'=>$site,'task_id'=>$taskId,'purchase_id'=>$purchaseId])); umask($old);
    $report+=['state_file'=>$statePath,'isolated_schema'=>$schema,'task_id'=>$taskId,'purchase_id'=>$purchaseId,'imei'=>$imei,'amount'=>'1.00'];
    Db::name('hsx_boyuan_task')->where('id',$taskId)->update(['next_at'=>0]); $service->process($site,$taskId);
    $view=$service->view($site,$taskId); $report['create_status']=$view['task']['status']; $report['reason']=$view['task']['reason']; $report['external_no']=$view['task']['external_no'];
    if($view['task']['external_no']!=='' && $view['task']['status']==='created_review') {
        $service->queueVerify($site,$taskId,1,['revision'=>$view['task']['revision'],'mode'=>'test','connection_key'=>$view['task']['connection_key'],'external_no'=>$view['task']['external_no']]);
        Db::name('hsx_boyuan_task')->where('id',$taskId)->update(['next_at'=>0]); $service->process($site,$taskId);
        $view=$service->view($site,$taskId);
    }
    $report['final_status']=$view['task']['status']; $report['reason']=$view['task']['reason']; $report['result']=$view['result'];
    $report['remote_cleanup']='No remote delete/return was attempted. Retain this UAT bill for review.';
} catch(Throwable $error) {
    $report['completed']=false; $report['error_type']=get_class($error);
    $report['reason']=$error instanceof Problem?$error->reason.': '.$error->getMessage():mb_substr($error->getMessage(),0,250);
    if($schema!=='') $report['isolated_schema']=$schema;
    $report['remote_state']='If a create might have been sent, inspect the retained task; do not rerun this script to retry.';
}
echo Contract::json(Contract::redact($report))."\n";
if($statePath!=='') { $old=umask(0077); file_put_contents($statePath.'.report.json',Contract::json(Contract::redact($report))); umask($old); }
