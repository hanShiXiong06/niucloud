<?php
declare(strict_types=1);

/**
 * Real ERP/MySQL lifecycle, fake signed Boyuan exchange, disposable database.
 * php -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,dns_get_record,gethostbyname,gethostbynamel addon/hsx_boyuan/tests/purchase-integration.php --isolated-local-mysql
 */
if(PHP_SAPI!=='cli' || PHP_VERSION_ID<80000 || !in_array('--isolated-local-mysql',$argv,true)) exit("Requires PHP 8+ and --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','dns_get_record','gethostbyname','gethostbynamel'] as $function) if(function_exists($function)) exit("Disable HTTP, socket and DNS functions before testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit("Set allow_url_fopen=0 before testing\n");
require __DIR__.'/bootstrap.php';

use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService,PurchaseSyncService,PurchaseClosureService,PurchaseInventoryService,PaymentSyncService,TaskService,BatchService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_erp\app\service\admin\{ErpIntegrationService,ErpPurchaseService,ErpFinanceService};
use think\facade\Db;

$checks=0; $failed=false; $pdo=null; $schema=''; $fakeCalls=0; $creates=0; $queries=0; $stockQueries=0; $paymentCreates=0; $paymentTimeout=false;
function piCheck(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException('ASSERT '.$label); $checks++; }
function piRejected(callable $callback,string $reason): void
{
    try { $callback(); } catch(Problem $error) { piCheck($error->reason===$reason,'expected '.$reason.', received '.$error->reason); return; }
    throw new RuntimeException('ASSERT expected '.$reason);
}
function piInput(array $view,?array $selection=null): array
{
    $input=array_intersect_key($view['task'],array_flip(['revision','mode','connection_key']));
    if($selection!==null) $input+=['selection'=>$selection,'remember'=>false];
    return $input;
}
function piPatch(ConfigService $service,int $site,array $patch): array
{
    $current=$service->get($site,false);
    return $service->save($site,1,array_replace(['revision'=>(int)$current['revision'],'confirm_account'=>'ISOLATED_TEST_ACCOUNT'],$patch));
}
function piTask(int $site,int $id): array { return Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->find(); }
function piWorkbenchIds(\think\App $app,string $status): array
{
    $original=$app->request;
    try {
        $app->instance('request',(new \app\Request())->withGet(['status'=>$status,'limit'=>100]));
        $data=(new \addon\hsx_boyuan\app\adminapi\controller\Workbench($app))->tasks()->getData()['data']['data'];
        return array_map('intval',array_column($data,'id'));
    } finally { $app->instance('request',$original); }
}
function piReady(int $site,int $id): void { Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['next_at'=>0]); }
function piDispatch(int $site): void
{
    $service=ErpIntegrationService::forSite($site,1,'隔离采购测试员');
    foreach(Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.confirmed.v1')->order('id')->column('id') as $id) {
        $reply=$service->dispatchDomainEvent((int)$id);
        piCheck(!empty($reply['ok']),'ERP confirmed event dispatched to actual addon listener');
    }
}
function piErpHash(int $site): string
{
    $rows=[]; foreach(['erp_purchase_order','erp_purchase_item','erp_asset','erp_payable','erp_settlement','erp_settlement_link','erp_money_ledger','erp_account_ledger','erp_asset_ledger'] as $table) $rows[$table]=Db::name($table)->where('site_id',$site)->order('id')->select()->toArray();
    return hash('sha256',Contract::json($rows));
}
// Two independent PHP/PDO processes share only the explicitly named random test schema.
$paymentWorker=array_search('--payment-capture-worker',$argv,true);
if(($workerAt=array_search('--inventory-queue-worker',$argv,true))!==false || $paymentWorker!==false) {
    try {
        if($paymentWorker!==false) $workerAt=$paymentWorker;
        $workerSchema=(string)($argv[$workerAt+1]??''); $parentId=(int)($argv[$workerAt+2]??0);
        if(!preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$workerSchema) || $parentId<=0) throw new RuntimeException('ASSERT unsafe inventory queue worker scope');
        [$app,$workerPdo,$workerSchema,$site]=boyuanBoot($workerSchema); $app->env->set('queue.state',false); $app->env->set('app.auth_key','ISOLATED_BOYUAN_PURCHASE_TEST_KEY_2026');
        $parent=piTask($site,$parentId); $version=['revision'=>(int)$parent['revision'],'mode'=>$parent['mode'],'connection_key'=>$parent['connection_key']];
        if($paymentWorker!==false) { $reply=(new PaymentSyncService())->capture($site,$parentId,1,$version+['settlement_id'=>(int)($argv[$workerAt+3]??0)]); $taskId=$reply['task']['id']; }
        else { $reply=(new PurchaseInventoryService())->queue($site,$parentId,1,$version); $taskId=$reply['inventory']['task_id']; }
        echo json_encode(['passed'=>true,'task_id'=>$taskId])."\n"; exit(0);
    } catch(Throwable $error) { fwrite(STDERR,'FAIL isolated inventory queue worker '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine()."\n"); exit(1); }
}

try {
    [$app,$pdo,$schema,$site]=boyuanBoot();
    // Bootstrap intentionally registers only domain facts; exercise the actual optional ERP entry too.
    $app->event->listen('HsxErpPurchaseActions',addon\hsx_boyuan\app\listener\PurchaseActions::class);
    $purchaseActionEvents=[];
    $app->event->listen('HsxErpPurchaseActions',static function(array $event) use(&$purchaseActionEvents): array {
        $purchaseActionEvents[]=array_intersect_key($event,array_flip(['site_id','purchase_id','status'])); return [];
    });
    // Do not reuse the host's real encryption identity for synthetic credentials.
    $app->env->set('app.auth_key','ISOLATED_BOYUAN_PURCHASE_TEST_KEY_2026');
    $app->env->set('queue.state',false); // Never dispatch a real queue or use synchronous queue fallback in tests.
    piCheck(Db::query('SELECT DATABASE() AS db')[0]['db']===$schema,'database connection is random isolated schema');
    $addon=new addon\hsx_boyuan\Addon(); piCheck($addon->install() && $addon->upgrade(),'current addon install and upgrade run only in isolated schema');
    $hubAddon=new addon\hsx_erp_integration\Addon(); piCheck($hubAddon->upgrade(),'actual manual bridge schema upgrades on isolated MySQL');
    Db::name('addon')->insert(['key'=>'hsx_boyuan','status'=>1,'title'=>'Fixture','type'=>'addon']);
    Db::name('site')->where('site_id',$site)->update(['addons'=>Contract::json(['hsx_erp','hsx_boyuan','hsx_erp_integration'])]);
    $prefix=(string)Db::connect()->getConfig('prefix');
    $logCount=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $logCount->execute([$schema,$prefix.'hsx_boyuan_log']); piCheck((int)$logCount->fetchColumn()===0,'fresh schema has no database technical-log table');

    $secret=str_repeat('I',32); $configService=new ConfigService();
    $saved=$configService->save($site,1,['revision'=>0,'mode'=>'test','enabled'=>false,'paused'=>true,'purchase_enabled'=>false,'finance_enabled'=>false,'profile'=>[
        'protocol'=>'openapi_v153','endpoint'=>PaymentSyncService::UAT_ENDPOINT,'gateway_path'=>'/gateway.do','app_id'=>'1234567890',
        'app_secret'=>$secret,'account_id'=>'ISOLATED_TEST_ACCOUNT','contract_confirmed'=>true,
    ]]);
    piCheck(!str_contains(Contract::json($saved),$secret),'synthetic app secret is not returned by config API');
    $profile=$configService->get($site,false)['profiles']['test']; $created=[]; $stockMissing=false;
    $exchange=function(string $path,array $params) use(&$fakeCalls,&$creates,&$queries,&$created,&$stockQueries,&$stockMissing,&$paymentCreates,&$paymentTimeout,$site): array {
        $fakeCalls++;
        piCheck($path==='/gateway.do' && ($params['big_gsdm']??'')==='ISOLATED_TEST_ACCOUNT','fake exchange is bound to synthetic account and official gateway');
        $service=$params['service']??'';
        if($service==='boyuan.biz.bill.payment.generate') {
            $paymentCreates++; $processing=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind',PaymentSyncService::KIND)->where('status','processing')->find();
            piCheck($processing && $processing['side_effect']==='unknown' && (int)$processing['attempts']===1,'payment intent and unknown side effect are durable before fake HTTP exchange');
            if($paymentTimeout) throw new RuntimeException('Synthetic timeout after possible commit');
            $biz=Contract::decode((string)$params['biz']); $head=$biz['head'];
            piCheck(($head['creater_id']??'')==='FINANCE_TEST' && ($head['creater_name']??'')==='隔离财务员工','voucher is authored by the original settlement finance employee, not the buyer or the synchronizing admin');
            piCheck($head['scope_flag']===3 && $head['is_auto_generate_supplier']===0 && $head['is_auto_generate_subject']===0,'payment uses same-company official scope without automatic party or subject creation');
            $head+=['id'=>'PAY_HEAD_'.$paymentCreates,'bill_id'=>'PAY_ISOLATED_'.$paymentCreates,'bill_type'=>'PAYMENT','big_gsdm'=>'ISOLATED_TEST_ACCOUNT'];
            $detail=$biz['payment_details']['add_details'][0]+['id'=>'PAY_LINE_'.$paymentCreates]; $head['bank_account']='MUST_NOT_LEAK'; $detail['voucher_url']='MUST_NOT_LEAK';
            return ['return_code'=>'SUCCESS','api_response'=>['result'=>['head'=>$head,'payment_details'=>['add_details'=>[$detail]]]]];
        }
        if($service==='boyuan.companys.get') return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>1,'result_list'=>[
            ['id'=>'COMPANY_TEST','gsdm'=>'ISOLATED_TEST_ACCOUNT','name'=>'隔离公司','is_effective'=>1],
        ]]];
        if($service==='boyuan.biz.bill.purchase.generate') {
            $creates++; $biz=Contract::decode((string)($params['biz']??'')); $head=$biz['head'];
            piCheck(($head['cash_term']??null)===0 && !isset($biz['cash_details']),'actual adapter sends inventory-only purchase without cash nodes');
            piCheck(($head['creater_id']??'')==='BUYER_TEST' && ($head['creater_name']??'')==='隔离staff','actual service uses selected external creator ID and archived name');
            $no='PUR_ISOLATED_'.$creates; $headId='ISOLATED_HEAD_'.$creates; $rows=[]; $details=[]; $serials=[];
            foreach($biz['product_details']['add_details'] as $index=>$line) {
                $seq=$index+1; $identity=['bill_id'=>$no,'bill_type'=>'PUR','business_date'=>$head['business_date'],'supplier_id'=>$head['supplier_id'],
                    'warehouse_id'=>$head['warehouse_id'],'company_id'=>'COMPANY_TEST','trans_type'=>'购','is_effective'=>1,'seq_no'=>$seq];
                $money=['product_id'=>$line['product_id'],'qty'=>1,'unit_price'=>$line['unit_price'],'amounts'=>$line['amounts']];
                $rows[]=$line+['id'=>'CREATE_DETAIL_'.$creates.'_'.$seq,'seq_no'=>$seq];
                $details[]=$identity+$money+['id'=>'PUR_DETAIL_'.$creates.'_'.$seq,'head_id'=>$headId];
                $serials[]=$identity+$money+['detail_id'=>'IMEI_DETAIL_'.$creates.'_'.$seq,'gsdm'=>'TEST_SHOP','imei'=>$line['imei'],'imei1'=>$line['imei'],'imei2'=>null,'imei3'=>null];
            }
            $reply=['head'=>$head+['id'=>$headId,'big_gsdm'=>'ISOLATED_TEST_ACCOUNT','bill_id'=>$no,'bill_type'=>'PUR','audit_status'=>0,'un_settle_amounts'=>'0.00','private_note'=>'NOT_FOR_FINANCE_UI'],'product_details'=>['add_details'=>$rows]];
            $created[$no]=['reply'=>$reply,'details'=>$details,'imei'=>$serials];
            return ['return_code'=>'SUCCESS','api_response'=>['result'=>$reply]];
        }
        if(in_array($service,['boyuan.biz.purchases.query','boyuan.biz.purchase.imeidetails.query'],true)) {
            $queries++; $rows=[];
            if($service==='boyuan.biz.purchases.query') foreach($created as $document) $rows=array_merge($rows,$document['details']);
            else $rows=$created[(string)($params['bill_id']??'')]['imei']??[];
            $page=(int)($params['page_no']??1); $size=(int)($params['page_size']??100);
            return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>count($rows),'result_list'=>array_slice($rows,($page-1)*$size,$size)]];
        }
        if($service==='boyuan.biz.inventory.imeidetails.query') {
            $stockQueries++; $rows=[];
            if(!$stockMissing) foreach($created as $document) foreach($document['imei'] as $line) if($line['imei1']===($params['imei']??'')) {
                $rows[]=['gsdm'=>'COMPANY_TEST_CODE','company_id'=>'COMPANY_TEST','imei'=>$line['imei1'],'imei1'=>$line['imei1'],'imei2'=>null,'imei3'=>null,
                    'product_id'=>$line['product_id'],'warehouse_id'=>$line['warehouse_id'],'supplier_id'=>$line['supplier_id'],'qty'=>1,'imei_in_stock_status'=>1];
            }
            return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>count($rows),'result_list'=>$rows]];
        }
        throw new RuntimeException('ASSERT unexpected fake service; no external transport is allowed');
    };
    // Exercise the documented purchase contract explicitly; PAYMENT below uses a separate exact-UAT adapter.
    $api=new OpenApi($profile,$secret,$exchange);
    piCheck($configService->probe($site,1,$api)['verified']===true,'real ConfigService accepts fake read-only company probe');
    piRejected(fn()=>piPatch($configService,$site,['enabled'=>true,'paused'=>false,'purchase_enabled'=>true,'confirm_account'=>'WRONG']),'ACCOUNT_CONFIRM_REQUIRED');
    $config=piPatch($configService,$site,['enabled'=>true,'paused'=>false,'purchase_enabled'=>true,'purchase_mode'=>'manual']);
    piCheck($config['mode']==='test' && $config['purchase_enabled'] && !$config['finance_enabled'] && $config['purchase_mode']==='manual','purchase-only test connection explicitly enabled');
    $key=$config['profiles']['test']['connection_key'];

    foreach(['companies','parties','products','warehouses','staff','capital_accounts'] as $resource) {
        $run=bin2hex(random_bytes(16));
        Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'connection_key'=>$key,'mode'=>'test','resource'=>$resource,'run_key'=>$run,'status'=>'completed',
            'scope_json'=>'{}','processed'=>1,'pages'=>1,'reported_total'=>1,'operator_uid'=>1,'create_at'=>time(),'update_at'=>time()]);
        $id=['companies'=>'COMPANY_TEST','parties'=>'SUPPLIER_TEST','products'=>'PRODUCT_TEST','warehouses'=>'WAREHOUSE_TEST','staff'=>'BUYER_TEST','capital_accounts'=>'PAY_ACCOUNT_TEST'][$resource];
        $extra=['companies'=>['gsdm'=>'COMPANY_TEST_CODE'],'parties'=>['is_supplier'=>1],'products'=>['imei_count'=>1,'imei_len'=>15,'memory'=>'256GB','color'=>'黑色'],'warehouses'=>['company_id'=>'COMPANY_TEST'],'staff'=>['is_buyer'=>1,'gsdm'=>'COMPANY_TEST_CODE'],'capital_accounts'=>['gsdm'=>'COMPANY_TEST_CODE']][$resource];
        if ($resource==='staff') $extra['company_id']='COMPANY_TEST';
        \addon\hsx_boyuan\app\support\CatalogStore::query($resource)->insert(['site_id'=>$site,'connection_key'=>$key,'external_id'=>$id,'code'=>$id,'name'=>'隔离'.$resource,'enabled'=>1,'extra_json'=>Contract::json($extra),'seen_run'=>$run,'update_at'=>time()]);
        if($resource==='staff') \addon\hsx_boyuan\app\support\CatalogStore::query('staff')->insert(['site_id'=>$site,'connection_key'=>$key,'external_id'=>'FINANCE_TEST','code'=>'FINANCE_TEST','name'=>'隔离财务员工','enabled'=>1,'extra_json'=>Contract::json($extra),'seen_run'=>$run,'update_at'=>time()]);
    }
    $supplier=(int)Db::name('erp_party')->insertGetId(['site_id'=>$site,'party_no'=>'ISOLATED_SUPPLIER','party_name'=>'隔离供应商','party_type'=>'supplier','status'=>1]);
    $warehouse=(int)Db::name('erp_warehouse')->insertGetId(['site_id'=>$site,'warehouse_name'=>'隔离设备仓','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
    $location=(int)Db::name('erp_warehouse_location')->insertGetId(['site_id'=>$site,'warehouse_id'=>$warehouse,'location_name'=>'隔离库位','status'=>1]);
    $purchase=ErpPurchaseService::forSite($site,1,'隔离采购测试员');
    $makePurchase=function(string $suffix) use($purchase,$supplier,$warehouse,$location): int {
        return (int)$purchase->create(['request_id'=>'BOYUAN_ISOLATED_'.$suffix,'party_id'=>$supplier,'party_name'=>'隔离供应商','purchaser_uid'=>1,'settle_method'=>'挂账',
            'origin_plugin'=>'hsx_erp','origin_type'=>'hsx_erp.manual_purchase','origin_no'=>'CLOSURE_SOURCE_'.$suffix,'items'=>[
            ['imei'=>'00000000000'.$suffix.'01','model'=>'隔离测试手机','spec'=>'256GB','color'=>'黑色','purchase_cost'=>1,'retail_price'=>2,'warehouse_id'=>$warehouse,'location_id'=>$location,'image_urls'=>'["/static/fixture.png"]'],
            ['imei'=>'00000000000'.$suffix.'02','model'=>'隔离测试手机','spec'=>'256GB','color'=>'黑色','purchase_cost'=>1,'retail_price'=>2,'warehouse_id'=>$warehouse,'location_id'=>$location,'image_urls'=>'["/static/fixture.png"]'],
        ]]);
    };
    $initialCalls=$fakeCalls; $po=$makePurchase('01');
    $bridge=new \addon\hsx_boyuan\app\adapter\BoyuanErpAdapter();
    $bridgeContext=['site_id'=>$site,'connection_id'=>1,'task_id'=>7,'operator_uid'=>1,'mode'=>'test','api_url'=>$profile['endpoint'],
        'gateway_path'=>$profile['gateway_path'],'app_id'=>$profile['app_id'],'account_code'=>$profile['account_id'],'vendor_task_id'=>0];
    $public=$bridge->originalProfile($site);
    piCheck(array_keys($public)===['api_url','gateway_path','app_id','account_code','mode','checked'] && $public['checked']===true
        && $public['account_code']===$profile['account_id'] && !str_contains(Contract::json($public),$secret) && $fakeCalls===$initialCalls,'profile binds only verified non-secret identity, without another probe');
    Db::startTrans();
    try {
        $withoutProbe=$configService->get($site,false); $withoutProbe['profiles']['test']['probe_at']=0; $configService->persist($site,$withoutProbe);
        $unverified=$bridge->originalProfile($site);
        piCheck(!$unverified['checked'] && $unverified['reason']!=='' && $fakeCalls===$initialCalls,'absent native probe does not become verified through bridge');
    } finally { Db::rollback(); }
    $bridgeEvent=static function(string $name,int $source) use($site):array {
        foreach(Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name',$name)->column('payload_json') as $raw) {
            $event=Contract::decode($raw); if((int)$event['aggregate_id']!==$source) continue;
            unset($event['_delivery']); return $event;
        }
        throw new RuntimeException('ASSERT missing persisted fixture event');
    };
    $bridgePurchaseEvent=$bridgeEvent('erp.integration.document.confirmed.v1',$po);
    $hubId=(int)Db::name('erp_integration_connection')->insertGetId(['site_id'=>$site,'adapter_code'=>'boyuan','adapter_name'=>'Fixture',
        'sync_mode'=>'manual','status'=>'enabled','bridge_started_at'=>$bridgePurchaseEvent['occurred_at'],'config'=>Contract::json(array_diff_key($public,['checked'=>true])),
        'business_types'=>Contract::json(['purchase','payment']),'last_test_result'=>'success','last_test_at'=>time()]);
    $bus=new \addon\hsx_erp_integration\app\service\IntegrationBusService();
    $manual=new \addon\hsx_erp_integration\app\service\ManualTaskService();
    $hubCapture=static function(array $event) use($site,$hubId,$bus):int {
        $first=$bus->acceptDomainEvent($event); $again=$bus->acceptDomainEvent($event);
        if(($first['status']??'')!=='processed' || ($again['status']??'')!=='duplicate' || count($first['task_ids']??[])!==1 || $first['task_ids']!==$again['task_ids'])
            throw new RuntimeException('ASSERT actual bridge bus captures this fact exactly once');
        $id=(int)$first['task_ids'][0]; $row=Db::name('erp_integration_sync_task')->where(['id'=>$id,'site_id'=>$site,'connection_id'=>$hubId])->find();
        if(!$row || $row['status']!=='awaiting_confirmation') throw new RuntimeException('ASSERT bridge task persisted in expected scope');
        return $id;
    };
    $hubRow=static fn(int $id)=>Db::name('erp_integration_sync_task')->where(['id'=>$id,'site_id'=>$site,'connection_id'=>$hubId])->find();
    $hubPurchaseTask=$hubCapture($bridgePurchaseEvent);
    piCheck($bridge->originalDocument($bridgePurchaseEvent,$bridgeContext,true)['status']==='waiting' && !Db::name('hsx_boyuan_task')->where('site_id',$site)->count(),'bridge query on an uncaptured source has no side effects');
    foreach(['site_id'=>$site+1,'account_code'=>'ANOTHER','api_url'=>'https://invalid.invalid','app_id'=>'ANOTHER','mode'=>'production','operator_uid'=>2,'vendor_task_id'=>1] as $field=>$bad) {
        piCheck($bridge->originalDocument($bridgePurchaseEvent,array_replace($bridgeContext,[$field=>$bad]),false)['status']==='blocked','bridge rejects conflicting '.$field.' before capture');
    }
    $changed=$bridgePurchaseEvent; $changed['payload']['lines'][0]['amount']='99.00';
    piCheck($bridge->originalDocument($changed,$bridgeContext,false)['status']==='blocked','source amounts cannot be forged in bridge');
    piCheck(($bridge->syncPurchase([],[])['success']??true)===false && ($bridge->syncPayment([],[])['success']??true)===false,'no legacy aggregate quick-write for either capability');
    $hubLinked=$manual->run($site,$hubPurchaseTask,1,false);
    $linked=$bridge->originalDocument($bridgePurchaseEvent,$bridgeContext,false);
    piCheck($hubLinked['vendor_task_id']===$linked['vendor_task_id'] && $hubRow($hubPurchaseTask)['status']==='linked' && $linked['external_no']==='',
        'actual bridge operator prepares a single native draft and persists its vendor task id');
    piCheck($linked['status']==='linked' && $linked['vendor_task_id']>0 && $fakeCalls===$initialCalls,'bridge prepares original manual task without remote exchange');
    piDispatch($site);
    piCheck((int)Db::name('hsx_boyuan_task')->where('site_id',$site)->count()===1 && $bridge->originalDocument($bridgePurchaseEvent,$bridgeContext,false)===$linked,'legacy listener and bridge hand off to one identical task');
    $detail=$purchase->info($po); $actions=$detail['integration_results']??[]; $entry=$actions[0]??[]; $observed=end($purchaseActionEvents);
    piCheck((int)$detail['id']===$po && (int)($observed['purchase_id']??0)===$po && (int)($observed['site_id']??0)===$site,'actual ERP info emits the correct tenant-scoped purchase source ID');
    piCheck(count($actions)===1 && ($entry['task_id']??0)>0 && $entry['query']['task_id']===(string)$entry['task_id']
        && array_column($detail['integration_actions'],'key')===['boyuan_exchange'] && ($detail['integration_actions'][0]['query']['purchase_no']??null)===$detail['purchase_no'],
        'existing purchase has its task and a scoped read-only exchange link, never another capture action');
    piCheck(($entry['pc_route']??'')==='/hsx_boyuan/purchase' && ($entry['mobile_path']??'')==='/addon/hsx_boyuan/pages/workbench/index'
        && preg_match('#^/[A-Za-z0-9_/-]+$#D',$entry['pc_route'])===1 && preg_match('#^/addon/[A-Za-z0-9_/-]+$#D',$entry['mobile_path'])===1,'ERP purchase entry returns only safe internal PC and mobile paths');
    piCheck(!str_contains(Contract::json($actions),$secret) && $fakeCalls===$initialCalls,'rendering actual purchase actions discloses no credential and performs no vendor request');
    Db::name('sys_user')->insert(['uid'=>2,'username'=>'isolated-no-role','real_name'=>'无博远权限测试员','status'=>1]);
    request()->uid(2);
    try { piCheck(($purchase->info($po)['integration_results']??[])===[],'active user without a site role receives no Boyuan purchase result'); }
    finally { request()->uid(1); }
    Db::startTrans();
    try {
        $temporary=$configService->get($site,false); $temporary['profiles']['test']['protocol']='bridge_v1'; $configService->persist($site,$temporary);
        piCheck(($purchase->info($po)['integration_results']??[])===[],'non-OpenAPI connection receives no Boyuan purchase entry');
    } finally { Db::rollback(); }
    Db::startTrans();
    try {
        Db::name('erp_purchase_order')->where('site_id',$site)->where('id',$po)->update(['status'=>'void']);
        piCheck(array_column($purchase->info($po)['integration_actions']??[],'key')===['boyuan_exchange'],'void purchase retains only read-only exchanges, no submit or capture action');
    } finally { Db::rollback(); }
    piCheck(count($purchase->info($po)['integration_results']??[])===1 && $fakeCalls===$initialCalls,'rollback restores permitted purchase entry without any vendor request');
    $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','purchase')->order('id')->find(); $id=(int)$task['id']; $immutable=$task['snapshot_json'];
    $snapshot=Contract::decode($immutable); $sync=new PurchaseSyncService(); $tasks=new TaskService();
    piCheck($task['status']==='awaiting_confirmation' && Contract::decode($task['request_json'])['stage']==='draft','real ERP transaction and outbox create a draft task');
    piCheck($snapshot['lines'][0]['quantity']==='1.000' && $snapshot['lines'][0]['amount']==='1.00','actual MySQL decimals are represented by immutable ERP snapshot');
    piCheck($fakeCalls===$initialCalls && $creates===0,'ERP create and fact capture perform no vendor exchange');
    piCheck(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','<>','purchase')->count()===0,'no party-resolution or financial child task created');
    piCheck(!$tasks->process($site,$id,$api) && $creates===0,'actual TaskService worker does not send an unsubmitted draft');

    $selection=['company_id'=>'COMPANY_TEST','supplier_id'=>'SUPPLIER_TEST','creator_id'=>'BUYER_TEST','buyer_id'=>'BUYER_TEST','lines'=>array_map(static fn($line)=>[
        'source_line_id'=>$line['source_line_id'],'product_id'=>'PRODUCT_TEST','warehouse_id'=>'WAREHOUSE_TEST'], $snapshot['lines'])];
    $view=$sync->view($site,$id); $view=$sync->saveDraft($site,$id,1,piInput($view,$selection));
    piCheck($view['editable'] && $view['can_submit'] && $creates===0,'complete external choices save without sending');
    piPatch($configService,$site,['paused'=>true]);
    piRejected(fn()=>$sync->submit($site,$id,1,piInput($view,$selection)),'PURCHASE_PAUSED');
    piCheck(!$tasks->process($site,$id,$api) && $creates===0,'global pause blocks draft submit and worker');
    piPatch($configService,$site,['paused'=>false]); $view=$sync->submit($site,$id,1,piInput($view,$selection));
    $frozen=piTask($site,$id)['request_json']; $request=Contract::decode($frozen);
    piCheck($view['task']['status']==='pending' && $request['stage']==='frozen' && $request['lines'][0]['quantity']==='1','actual submit freezes canonical one-device manifest');
    piCheck(Db::name('hsx_boyuan_mapping')->where('site_id',$site)->count()===0,'default draft and submit do not create global mappings');
    piPatch($configService,$site,['enabled'=>false,'paused'=>true]); piReady($site,$id);
    piCheck(!$tasks->process($site,$id,$api) && $creates===0,'disabled global connection holds a frozen task without vendor exchange');
    piPatch($configService,$site,['enabled'=>true,'paused'=>false]); piReady($site,$id);
    piCheck($tasks->process($site,$id,$api),'actual worker routes frozen purchase into the official adapter');
    $task=piTask($site,$id); piCheck($task['status']==='created_review' && $task['external_no']==='PUR_ISOLATED_1' && $creates===1,'create persists vendor number but does not claim verified success');
    piCheck($bridge->originalDocument($bridgePurchaseEvent,$bridgeContext,true)['status']==='linked','create-only receipt is not verified purchase on bridge');
    piCheck($task['snapshot_json']===$immutable && $task['request_json']===$frozen,'source and final request remain byte-for-byte immutable');
    $view=$sync->view($site,$id); $sync->queueVerify($site,$id,1,piInput($view)+['external_no'=>$task['external_no']]); piReady($site,$id);
    piCheck($tasks->process($site,$id,$api),'known number enters read-only purchase verification');
    $task=piTask($site,$id); $result=Contract::decode($task['result_json']);
    $facts=$result['provider_result']??[];
    piCheck(($facts['bill_id']??null)==='PUR_ISOLATED_1' && ($facts['audit_status']??null)===0 && ($facts['un_settle_amounts']??null)==='0.00' && !array_intersect(['amounts','private_note','remark'],array_keys($facts)),'verified purchase retains only returned business facts; zero preserved, missing not invented');
    piCheck($task['status']==='succeeded' && $creates===1 && $queries===2,'purchase and serial queries complete without repeat create');
    $bridgePurchase=$bridge->originalDocument($bridgePurchaseEvent,array_replace($bridgeContext,['vendor_task_id'=>$id]),true);
    piCheck($bridgePurchase['status']==='confirmed' && $bridgePurchase['external_no']===$task['external_no'],'verified lines and vendor receipt project as a confirmed purchase');
    Db::startTrans();
    try {
        Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['status'=>'confirming','external_no'=>'MANUAL-CANDIDATE','result_json'=>'{}']);
        $candidate=$bridge->originalDocument($bridgePurchaseEvent,array_replace($bridgeContext,['vendor_task_id'=>$id]),true);
        piCheck($candidate['status']==='unknown' && $candidate['external_no']==='','manually supplied lookup number is not returned as a native purchase receipt');
    } finally {Db::rollback();}
    $hubPurchase=$manual->run($site,$hubPurchaseTask,1,true); $persistedPurchase=$hubRow($hubPurchaseTask);
    piCheck($hubPurchase['status']==='confirmed' && $persistedPurchase['external_doc_no']===$task['external_no'] && $persistedPurchase['status']==='succeeded'
        && Contract::decode($persistedPurchase['receipt_json'])['vendor_task_id']===$id,'real bridge persists only verified original purchase receipt after signed fake-gateway lifecycle');
    piCheck(($result['verification_level']??'')==='purchase_imei_records_matched' && $result['stock_balance_verified']===false && $result['financial_posting_verified']===false,'success records explicit verification limits');
    piCheck(($result['evidence']['response_contract']??'')===OpenApi::PURCHASE_DIALECT_DOCUMENTED && ($result['evidence']['document_head_id_verified']??null)===true
        && ($result['evidence']['external_line_id_source']??'')==='purchase_detail.id','documented query contract and head-ID evidence persist without UAT fallback');
    piCheck(Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('kind','document')->count()===1 && Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('kind','line')->count()===2,'real transaction persists one document and two verified line bindings');
    piCheck(Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('kind','line')->where('local_ref',$snapshot['lines'][0]['source_line_id'])->value('external_id')==='PUR_DETAIL_1_1','line binding uses authoritative purchase-detail ID rather than a serial-row ID');
    piCheck(Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('kind','financial')->count()===0 && Db::name('erp_settlement')->where('site_id',$site)->count()===0,'no vendor finance binding or local payment created');
    piCheck((float)Db::name('erp_payable')->where('site_id',$site)->sum('settled_amount')===0.0,'ERP purchase payables remain unpaid');
    piCheck(!$tasks->process($site,$id,$api) && $creates===1,'completed actual task cannot be resent');

    $closureService=new PurchaseClosureService(); $beforeClosure=piTask($site,$id); $closure=$sync->view($site,$id)['closure'];
    piCheck($closure['available'] && $closure['scope']==='purchase_batch' && !$closure['complete'] && $closure['totals']===['currency'=>'CNY','amount'=>'2.00','paid_amount'=>'0.00','unpaid_amount'=>'2.00'],'actual view exposes exact unpaid batch closure without claiming a full cycle');
    piCheck($closure['stages'][1]['status']==='verified' && $closure['stages'][2]['status']==='unverified' && $closure['stages'][5]['status']==='blocked','successful purchase records remain separate from unknown current stock and blocked remote finance');
    piCheck(piTask($site,$id)===$beforeClosure,'closure read does not change task revision, status, receipt or immutable request');
    $cross=$closureService->view($site+1,$beforeClosure); piCheck(!$cross['available'] && $cross['source']['source_line_ids']===[] && $cross['totals']['amount']===null,'cross-tenant closure returns neither source identities nor amounts');
    request()->uid(2);
    try { $denied=$closureService->view($site,$beforeClosure); piCheck($denied['stages'][3]['status']==='permission_denied' && $denied['stages'][4]['status']==='permission_denied' && $denied['totals']['amount']===null,'real payable permission gates financial closure independently of task read data'); }
    finally { request()->uid(1); }
    $capital=(int)Db::name('erp_capital_account')->insertGetId(['site_id'=>$site,'account_name'=>'PRIVATE_CLOSURE_ACCOUNT','account_type'=>'bank','balance'=>1000,'status'=>1]);
    $payables=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->whereIn('source_id',array_map(static fn($line)=>(int)substr($line['device_ref'],6),$snapshot['lines']))->order('id')->select()->toArray();
    Db::name('sys_user')->insert(['uid'=>3,'username'=>'isolated-finance','real_name'=>'隔离财务测试员','status'=>1]);
    Db::name('sys_user_role')->insert(['uid'=>3,'site_id'=>$site,'status'=>1,'is_admin'=>0]);
    $finance=ErpFinanceService::forSite($site,3,'隔离财务测试员');
    $finance->confirmPayableItemsPayment($supplier,[['payable_id'=>(int)$payables[0]['id'],'amount'=>0.40]],['request_id'=>'CLOSURE_PARTIAL','capital_account_id'=>$capital,'voucher_urls'=>['PRIVATE_CLOSURE_VOUCHER']]);
    $closure=$closureService->view($site,piTask($site,$id));
    piCheck($closure['available'] && $closure['totals']['paid_amount']==='0.40' && $closure['totals']['unpaid_amount']==='1.60' && $closure['stages'][4]['status']==='partial','actual ERP partial settlement is reflected exactly without full-paid inference');
    piCheck(!str_contains(Contract::json($closure),'PRIVATE_CLOSURE_'),'closure discloses neither capital account nor voucher data');
    $finance->confirmPayableItemsPayment($supplier,[['payable_id'=>(int)$payables[0]['id'],'amount'=>0.60],['payable_id'=>(int)$payables[1]['id'],'amount'=>1.00]],['request_id'=>'CLOSURE_FULL','capital_account_id'=>$capital]);
    $closure=$closureService->view($site,piTask($site,$id));
    piCheck($closure['totals']['paid_amount']==='2.00' && $closure['totals']['unpaid_amount']==='0.00' && $closure['stages'][4]['status']==='verified' && !$closure['complete'],'actual local full payment never implies remote payment or full procurement closure');
    Db::startTrans();
    try {
        Db::name('erp_payable')->where('site_id',$site)->where('id',$payables[1]['id'])->update(['status'=>'void']);
        $voided=$closureService->view($site,piTask($site,$id));
        piCheck($voided['available'] && $voided['totals']['amount']==='1.00' && $voided['totals']['paid_amount']==='1.00' && $voided['stages'][3]['count']===1,'voided payable and its confirmed payment allocations are excluded from effective batch totals');
    } finally { Db::rollback(); }
    Db::startTrans();
    try {
        Db::name('erp_purchase_item')->where('site_id',$site)->where('id',(int)substr($snapshot['lines'][0]['source_line_id'],14))->update(['purchase_cost'=>'0.99']);
        $changed=$closureService->view($site,piTask($site,$id));
        piCheck(!$changed['available'] && $changed['totals']['amount']===null && $changed['stages'][0]['status']==='unavailable','changed original purchase returns unavailable rather than silently changing closure totals');
    } finally { Db::rollback(); }
    // Force one genuine SQL read failure only inside this disposable random schema.
    if(!preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema) || !preg_match('/^[a-zA-Z0-9_]+$/D',$prefix)) throw new RuntimeException('ASSERT unsafe failure-fixture schema');
    $from='`'.$schema.'`.`'.$prefix.'erp_settlement_link`'; $hold='`'.$schema.'`.`'.$prefix.'erp_settlement_link_closure_hold`';
    $pdo->exec('RENAME TABLE '.$from.' TO '.$hold);
    try {
        $unavailable=$closureService->view($site,piTask($site,$id));
        piCheck(!$unavailable['available'] && $unavailable['totals']['paid_amount']===null && $unavailable['totals']['unpaid_amount']===null && $unavailable['stages'][4]['status']==='unavailable','genuine local SQL failure returns unavailable and never fabricates zero payment amounts');
    } finally { $pdo->exec('RENAME TABLE '.$hold.' TO '.$from); }
    piCheck($creates===1 && $queries===2,'all local financial closure checks perform no external purchase, payment or query exchange');

    $secondPo=$makePurchase('02'); piDispatch($site); $draft=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','purchase')->order('id desc')->find(); $draftId=(int)$draft['id'];
    piCheck($draftId!==$id && $draft['status']==='awaiting_confirmation','second real purchase is independently awaiting confirmation');
    $draftView=$sync->view($site,$draftId); $draftSelection=$selection; $draftSelection['lines']=array_map(static fn($line)=>['source_line_id'=>$line['source_line_id'],'product_id'=>'PRODUCT_TEST','warehouse_id'=>'WAREHOUSE_TEST'],$draftView['source']['lines']);
    $draftInput=piInput($draftView,$draftSelection); $draftInput['remember']=true; $sync->saveDraft($site,$draftId,1,$draftInput);
    piCheck(Db::name('hsx_boyuan_mapping')->where('site_id',$site)->count()===4,'explicit remember writes only supplier, buyer, product, warehouse identities');
    piPatch($configService,$site,['purchase_mode'=>'automatic']); piDispatch($site);
    piCheck(piTask($site,$draftId)['status']==='awaiting_confirmation' && $creates===1,'event replay after switching automatic never submits an existing manual draft');
    piCheck(piTask($site,$id)['status']==='succeeded','event replay does not alter completed purchase');
    $automaticPo=$makePurchase('03'); piDispatch($site);
    $automatic=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','purchase')->order('id desc')->find(); $automaticId=(int)$automatic['id'];
    piCheck($automaticId!==$draftId && $automatic['status']==='pending' && Contract::decode($automatic['request_json'])['auto_verify']===true,'new automatic ERP purchase freezes complete mappings and read-verification intent');
    piReady($site,$automaticId); $tasks->process($site,$automaticId,$api); $automatic=piTask($site,$automaticId);
    piCheck($automatic['status']==='confirming' && (int)$automatic['query_only']===1 && $creates===2,'automatic create schedules only read-only verification without operator action');
    piReady($site,$automaticId); $tasks->process($site,$automaticId,$api); $automatic=piTask($site,$automaticId);
    piCheck($automatic['status']==='succeeded' && $creates===2 && $queries===4,'automatic actual service completes with exactly one create plus two record queries');
    piCheck(Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('kind','line')->count()===4,'automatic verification binds only its additional two source lines');
    piCheck(!$tasks->process($site,$automaticId,$api) && $creates===2,'completed automatic purchase never repeats create');
    piPatch($configService,$site,['purchase_mode'=>'manual']);
    $appendPo=(int)$purchase->create(['append_order_id'=>$secondPo,'request_id'=>'CLOSURE_APPEND_BATCH','party_id'=>$supplier,'party_name'=>'隔离供应商','purchaser_uid'=>1,'settle_method'=>'挂账',
        'origin_plugin'=>'hsx_erp','origin_type'=>'hsx_erp.manual_purchase','origin_no'=>'CLOSURE_SOURCE_02','items'=>[
        ['imei'=>'000000000009901','model'=>'隔离测试手机','spec'=>'256GB','color'=>'黑色','purchase_cost'=>1,'retail_price'=>2,'warehouse_id'=>$warehouse,'location_id'=>$location,'image_urls'=>'["/static/fixture.png"]'],
    ]]);
    piDispatch($site); $appendTask=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','purchase')->order('id desc')->find();
    piCheck($appendPo===$secondPo && (int)$appendTask['id']!==$draftId,'real ERP append creates an independent source batch under the same purchase');
    piCheck(array_column($purchase->info($secondPo)['integration_results'],'task_id')===[$draftId,(int)$appendTask['id']],'same-source append shows both and only its own batches, not a similarly dated purchase');
    $secondAssets=Db::name('erp_asset')->where('site_id',$site)->where('purchase_order_id',$secondPo)->column('id');
    $batchPayables=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->whereIn('source_id',$secondAssets)->select()->toArray();
    $finance->confirmPayableItemsPayment($supplier,array_map(static fn($payable)=>['payable_id'=>(int)$payable['id'],'amount'=>(float)$payable['amount']],$batchPayables),['request_id'=>'CLOSURE_SHARED_BATCH_PAYMENT','capital_account_id'=>$capital]);
    $firstBatch=$closureService->view($site,piTask($site,$draftId)); $lastBatch=$closureService->view($site,$appendTask);
    piCheck($firstBatch['available'] && $firstBatch['totals']['amount']==='2.00' && $firstBatch['totals']['paid_amount']==='2.00' && $lastBatch['available'] && $lastBatch['totals']['amount']==='1.00' && $lastBatch['totals']['paid_amount']==='1.00','shared confirmed payment is allocated only to each task batch lines, not the entire purchase or payment');
    piCheck($firstBatch['source']['device_count']===2 && $lastBatch['source']['device_count']===1 && $firstBatch['stages'][4]['count']===1 && $lastBatch['stages'][4]['count']===1,'batch identity and shared-payment count remain accurate without exposing payment details');
    $logCount->execute([$schema,$prefix.'hsx_boyuan_log']); piCheck((int)$logCount->fetchColumn()===0,'full lifecycle never recreates a database log table');
    piCheck(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','<>','purchase')->count()===0,'all actual addon tasks stay purchase-only after replay');

    $inventoryService=new PurchaseInventoryService(); $parentBefore=piTask($site,$id); $erpHash=piErpHash($site); $beforeCreates=$creates;
    piRejected(fn()=>$inventoryService->queue($site,$draftId,1,piInput($sync->view($site,$draftId))),'INVENTORY_DEPENDENCY_REQUIRED');
    $workers=[];
    for($worker=0;$worker<2;$worker++) {
        $pipes=[]; $process=proc_open([PHP_BINARY,'-d','allow_url_fopen=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,dns_get_record,gethostbyname,gethostbynamel',__FILE__,'--isolated-local-mysql','--inventory-queue-worker',$schema,(string)$id],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('ASSERT cannot start isolated inventory queue worker');
        fclose($pipes[0]); $workers[]=[$process,$pipes];
    }
    $queuedIds=[];
    foreach($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
        $workerResult=json_decode(trim($output),true); piCheck($code===0 && !empty($workerResult['passed']) && $stderr==='','parallel isolated inventory queue worker succeeds without external transport');
        $queuedIds[]=(int)$workerResult['task_id'];
    }
    $inventoryId=$queuedIds[0];
    piCheck($inventoryId>0 && $queuedIds[0]===$queuedIds[1] && Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind',PurchaseInventoryService::KIND)->count()===1,'two concurrent PHP/PDO enqueue transactions reuse exactly one read-only inventory child');
    $child=piTask($site,$inventoryId); $frozenInventory=$child['request_json'];
    piCheck((int)$child['query_only']===1 && $child['snapshot_json']===$frozenInventory && Contract::decode($frozenInventory)['source_task_id']===$id,'inventory task freezes its own exact parent, date and source identity without touching original purchase');
    piCheck(piTask($site,$id)===$parentBefore && piErpHash($site)===$erpHash && $stockQueries===0,'enqueue performs no stock lookup or local purchase, stock, money or source-task write');
    piRejected(fn()=>$tasks->action($site,$inventoryId,1,'retry'),'INVENTORY_READ_ONLY_REQUIRED');
    piCheck(BatchService::actionFor($child)==='','generic task batch never offers a bridge retry or write action for an inventory child');
    $crossStock=$inventoryService->summary($site+1,$parentBefore); piCheck($crossStock['status']==='unavailable' && $crossStock['in_stock_count']===null,'inventory summary rejects cross-site source without a false zero');
    piPatch($configService,$site,['paused'=>true]); piReady($site,$inventoryId); piCheck(!$tasks->process($site,$inventoryId,$api) && $stockQueries===0,'paused connection holds inventory child without HTTP');
    piPatch($configService,$site,['paused'=>false]); piReady($site,$inventoryId); piCheck($tasks->process($site,$inventoryId,$api),'real TaskService routes inventory child into bounded read-only worker');
    $stock=$sync->view($site,$id); $stockChild=piTask($site,$inventoryId); $stockResult=Contract::decode($stockChild['result_json']);
    piCheck($stockChild['status']==='succeeded' && $stock['inventory']['status']==='succeeded' && $stock['inventory']['total_count']===2 && $stock['inventory']['in_stock_count']===2 && $stockQueries===2,'two actual gateway reads persist complete current batch evidence');
    piCheck($stock['closure']['stages'][2]['label']==='本批次当前在库' && $stock['closure']['stages'][2]['status']==='verified' && !$stock['closure']['complete'] && $stock['closure']['stages'][5]['status']==='blocked','closure accepts only batch stock evidence, never whole-warehouse or finance completion');
    piCheck(($stockResult['evidence']['source_task_id']??null)===$id && $stockResult['evidence']['source_snapshot_hash']===hash('sha256',$parentBefore['snapshot_json'])
        && $stockResult['evidence']['whole_warehouse_reconciled']===false && $stockResult['evidence']['financial_posting_verified']===false && isset($stockResult['evidence']['scope_hash']),'minimal persisted inventory evidence retains exact source identity and negative verification limits');
    $sameInventory=$inventoryService->queue($site,$id,1,piInput($stock));
    piCheck($sameInventory['inventory']['task_id']===$inventoryId && $stockQueries===2 && !$tasks->process($site,$inventoryId,$api),'duplicate fresh-success request neither creates another task nor another stock query');
    $oldStock=$stockResult; $oldStock['checked_at']=time()-301; Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$inventoryId)->update(['result_json'=>Contract::json($oldStock)]);
    $expired=$sync->view($site,$id); piCheck($expired['inventory']['status']==='stale' && $expired['inventory']['in_stock_count']===null && $expired['can_check_inventory'],'expired result is explicitly stale with no current count and permits a manual refresh');
    $inventoryService->queue($site,$id,1,piInput($expired)); $stockMissing=true; piReady($site,$inventoryId); $tasks->process($site,$inventoryId,$api);
    $missingStock=$sync->view($site,$id); piCheck($missingStock['inventory']['status']==='failed' && $missingStock['inventory']['in_stock_count']===null && $missingStock['closure']['stages'][2]['status']!=='verified','empty official inventory response is failed evidence, not zero stock or successful closure');
    piCheck(piTask($site,$id)===$parentBefore && piErpHash($site)===$erpHash && $creates===$beforeCreates && piTask($site,$inventoryId)['request_json']===$frozenInventory,'all inventory worker outcomes leave local business tables, original frozen purchase and purchase create count unchanged');
    // Registration consumes only actual ErpFinanceService-confirmed payment facts; it must never debit them again.
    $payments=new PaymentSyncService(); $settlements=Db::name('erp_settlement')->where('site_id',$site)->where('status','confirmed')->order('id')->select()->toArray();
    $firstSettlement=(int)$settlements[0]['id']; $secondSettlement=(int)$settlements[1]['id'];
    piRejected(fn()=>$payments->capture($site,$id,1,piInput($sync->view($site,$id))+['settlement_id'=>$firstSettlement]),'PAYMENT_UAT_ONLY');
    piRejected(fn()=>piPatch($configService,$site,['uat_payment_enabled'=>true,'confirm_account'=>'WRONG_ACCOUNT']),'PAYMENT_UAT_ACCOUNT_CONFIRM_REQUIRED');
    piPatch($configService,$site,['uat_payment_enabled'=>true]);
    $candidatePage=$payments->candidates($site,$id); piCheck($candidatePage['total']===2 && count($candidatePage['items'])===2 && !$candidatePage['has_more'],'actual same-batch confirmed partial and remaining payments are safe paged candidates');
    piCheck(array_column($payments->candidates($site,$id,['settlement_id'=>(string)$firstSettlement])['items'],'settlement_id')===[$firstSettlement]
        && $payments->candidates($site,$id,['settlement_id'=>PHP_INT_MAX])['total']===0,'opening a specific ERP voucher never offers a different confirmed payment');
    $workers=[];
    for($worker=0;$worker<2;$worker++) {
        $pipes=[]; $process=proc_open([PHP_BINARY,'-d','allow_url_fopen=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,dns_get_record,gethostbyname,gethostbynamel',__FILE__,'--isolated-local-mysql','--payment-capture-worker',$schema,(string)$id,(string)$firstSettlement],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('ASSERT cannot start isolated payment capture worker'); fclose($pipes[0]); $workers[]=[$process,$pipes];
    }
    $paymentIds=[];
    foreach($workers as [$process,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process); $out=json_decode(trim($out),true); piCheck($exit===0 && !empty($out['passed']) && $err==='','parallel actual payment capture succeeds without gateway'); $paymentIds[]=(int)$out['task_id']; }
    $paymentId=$paymentIds[0]; piCheck($paymentIds[0]===$paymentIds[1] && Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind',PaymentSyncService::KIND)->count()===1,'concurrent confirmed-payment capture reuses a single settlement task');
    $paymentEvent=$bridgeEvent('erp.settlement.completed.v1',$firstSettlement); $fundsBeforeHub=piErpHash($site);
    $hubPaymentTask=$hubCapture($paymentEvent); $hubVoucherDraft=$manual->run($site,$hubPaymentTask,1,false);
    piCheck($hubVoucherDraft['status']==='linked' && $hubVoucherDraft['vendor_task_id']===$paymentId && $hubPaymentTask!==$hubPurchaseTask
        && $paymentEvent['payload']['targets'][0]['source_line_ref']===$bridgePurchaseEvent['payload']['lines'][0]['source_line_id']
        && $paymentEvent['payload']['targets'][0]['source_document_ref']===$bridgePurchaseEvent['payload']['source_document_id']
        && $fundsBeforeHub===piErpHash($site),'real bridge finds the exact same purchased device, reuses native voucher and never changes the original ERP accounting');
    $payment=$payments->view($site,$paymentId); $choose=['creator_id'=>'FINANCE_TEST','account_id'=>'PAY_ACCOUNT_TEST'];
    piCheck($payment['source']['operator_uid']===3 && $payment['source']['operator_name']==='隔离财务测试员' && $payment['selection']['creator_id']===''
        && !$payment['mapped_fields']['creator_id'] && $payment['can_remember'],'unmapped finance source does not inherit buyer or syncing admin; no mapping is written on read');
    piRejected(fn()=>$payments->saveDraft($site,$paymentId,1,piInput($payment)+['selection'=>array_replace($choose,['creator_id'=>'BUYER_TEST']),'remember'=>true]),'MAPPING_CONFLICT');
    piCheck(in_array($paymentId,piWorkbenchIds($app,'action_required'),true) && !in_array($paymentId,piWorkbenchIds($app,'completed'),true),'an unsent draft requiring user confirmation remains in the workbench action filter');
    piCheck($payment['source']['amount']==='0.40' && $payment['source']['company_code']==='COMPANY_TEST_CODE' && $payment['task']['status']==='awaiting_confirmation' && $paymentCreates===0,'actual payment draft freezes exact confirmed decimal and company scope, not frontend amount');
    $payment=$payments->saveDraft($site,$paymentId,1,piInput($payment)+['selection'=>$choose,'remember'=>true]);
    piCheck($payment['mapped_fields']===['creator_id'=>true,'account_id'=>true] && $payment['selection']===$choose
        && Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>'staff','local_ref'=>'staff:3'])->value('external_id')==='FINANCE_TEST',
        'explicit remember binds actual payer and original account in the current connection only');
    piRejected(fn()=>$payments->saveDraft($site,$paymentId,1,piInput($payment)+['selection'=>array_replace($choose,['creator_id'=>'BUYER_TEST'])]),'PAYMENT_MAPPING_CHANGED');
    piRejected(fn()=>$payments->submit($site,$paymentId,1,piInput($payment)+['selection'=>$choose,'confirm_registration'=>true]),'PAYMENT_AP_EVIDENCE_REQUIRED');
    piCheck($payment['can_submit_experiment'] && !$payment['can_submit'] && !$payment['can_verify'] && $payment['ap_evidence']['status']==='rule_confirmed','UAT experiment separates official AP formation rule from unavailable balance and allocation proof');
    $beforePayment=piErpHash($site); $parentPayment=piTask($site,$id); $paymentApi=new OpenApi($profile,$secret,$exchange,'test');
    $payment=$payments->submit($site,$paymentId,1,piInput($payment)+['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true]);
    $frozenPayment=piTask($site,$paymentId)['request_json'];
    piRejected(fn()=>$tasks->action($site,$paymentId,1,'repair'),'PAYMENT_SINGLE_ATTEMPT_REQUIRED');
    piCheck(BatchService::actionFor(piTask($site,$paymentId))==='' && $paymentCreates===0,'generic action and batch cannot unfreeze or invoke payment creation');
    piPatch($configService,$site,['paused'=>true]); piReady($site,$paymentId); piCheck(!$tasks->process($site,$paymentId,$paymentApi) && $paymentCreates===0,'pause holds actual pending registration before gateway'); piPatch($configService,$site,['paused'=>false]);
    piReady($site,$paymentId); $pendingVoucher=$payments->view($site,$paymentId);
    $current=piInput($pendingVoucher)+['account_code'=>$pendingVoucher['current_account_code'],'purchase_task_id'=>$pendingVoucher['parent_purchase_task_id'],'settlement_id'=>$pendingVoucher['source']['settlement_id']];
    piCheck($pendingVoucher['can_process_current'],'authorized UAT finance can process this frozen voucher, not submit a new one');
    piRejected(fn()=>$payments->processCurrent($site,$paymentId,1,array_replace($current,['settlement_id'=>0]),$paymentApi),'PAYMENT_CURRENT_UNAVAILABLE');
    piRejected(fn()=>$payments->processCurrent($site,$paymentId,1,array_replace($current,['account_code'=>'other']),$paymentApi),'PAYMENT_CURRENT_UNAVAILABLE');
    $payments->processCurrent($site,$paymentId,1,$current,$paymentApi);
    piCheck($paymentCreates===1 && !(new addon\hsx_boyuan\app\job\PaymentProcess())->doJob($site,$paymentId,$paymentApi) && $paymentCreates===1,'UI and subsequent queue delivery share one lease and one creation');
    $registered=$payments->view($site,$paymentId); piCheck($registered['task']['status']==='registered_review' && $registered['task']['external_no']==='PAY_ISOLATED_1' && $registered['result']['registered_amount']==='0.40' && !$registered['result']['allocation_applied_verified'],'matched receipt is persisted, not specific purchase allocation or full financial closure');
    $fundsBefore=piErpHash($site);
    $bridgePaid=$bridge->originalDocument($paymentEvent,$bridgeContext,true);
    piCheck($bridgePaid['status']==='confirmed' && $bridgePaid['vendor_task_id']===$paymentId && $bridgePaid['external_no']===$registered['task']['external_no']
        && $bridgePaid['allocation']['verified']===false && $bridgePaid['allocation']['remaining_amount']===null,'registered original voucher is not a bank transfer or provider allocation');
    $hubVoucher=$manual->run($site,$hubPaymentTask,1,true); $persistedVoucher=$hubRow($hubPaymentTask);
    $storedVoucher=Contract::decode($persistedVoucher['receipt_json']);
    piCheck($persistedVoucher['status']==='succeeded' && $hubVoucher['status']==='confirmed' && $persistedVoucher['external_doc_no']===$registered['task']['external_no']
        && $storedVoucher['allocation']===['status'=>'pending','verified'=>false,'applied_amount'=>null,'remaining_amount'=>null]
        && $storedVoucher['vendor_task_id']===$paymentId && $fundsBeforeHub===piErpHash($site) && $paymentCreates===1 && $creates===2,
        'actual bridge persists registered receipt, leaves allocation unverified, preserves settled balances and makes no repeat gateway creates');
    piCheck($registered['operator_complete'] && $registered['issues']===[] && !$registered['can_submit'] && !$registered['can_submit_experiment'],'operator has no remaining action after a matched receipt');
    $notice=new \addon\hsx_boyuan\app\service\NoticeService();
    $beforeNotice=piTask($site,$paymentId); $notice->send($site,$paymentId);
    piCheck(piTask($site,$paymentId)===$beforeNotice,'even an explicit reminder call cannot enqueue or mark a completed registration');
    piCheck(!str_contains(Contract::json($registered),'MUST_NOT_LEAK') && !$registered['result']['bank_transfer_verified'],'actual gateway and service exclude bank and voucher data and never imply funds transfer');
    piCheck(piTask($site,$id)===$parentPayment && piErpHash($site)===$beforePayment && piTask($site,$paymentId)['request_json']===$frozenPayment,'registering remote accounting receipt does not change any original task or local purchase, stock, payable, payment, capital or ledger row');
    piCheck(!$tasks->process($site,$paymentId,$paymentApi) && $paymentCreates===1 && Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('kind','financial')->count()===0,'registered receipt cannot create twice or forge remote AP evidence');
    $parentPaymentView=$sync->view($site,$id); piCheck($parentPaymentView['payment']['registered_amount']==='0.40' && $parentPaymentView['closure']['stages'][5]['status']==='partial' && !$parentPaymentView['closure']['operator_complete'] && !$parentPaymentView['closure']['complete'],'parent with two local settlements is not done on receipt for only one payment');
    $otherEvent=$bridgeEvent('erp.settlement.completed.v1',$secondSettlement);
    Db::startTrans();
    try {
        Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('task_id',$id)->where('kind','line')->delete();
        piCheck($bridge->originalDocument($otherEvent,$bridgeContext,false)['status']==='waiting'
            && (int)Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind',PaymentSyncService::KIND)->count()===1,
            'out-of-order voucher waits on verified parent line receipts; no new voucher draft');
    } finally { Db::rollback(); }
    piCheck($bridge->originalDocument($otherEvent,$bridgeContext,true)['status']==='waiting','query alone cannot create second voucher');
    $linkedPayment=$bridge->originalDocument($otherEvent,$bridgeContext,false);
    $other=$payments->capture($site,$id,1,piInput($parentPaymentView)+['settlement_id'=>$secondSettlement]); $otherId=$other['task']['id'];
    piCheck($other['selection']===$choose && $other['mapped_fields']===['creator_id'=>true,'account_id'=>true],'subsequent draft defaults both mappings without prompting or inheriting procurement');
    piCheck($linkedPayment['status']==='linked' && $linkedPayment['vendor_task_id']===$otherId && piErpHash($site)===$fundsBefore && $paymentCreates===1,'bridge and native desk reuse same second voucher; no bank, ERP or remote write');
    $other=$payments->submit($site,$otherId,1,piInput($other)+['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true]); $paymentTimeout=true; piReady($site,$otherId); $tasks->process($site,$otherId,$paymentApi);
    piCheck(piTask($site,$otherId)['status']==='attention' && piTask($site,$otherId)['side_effect']==='unknown' && (int)piTask($site,$otherId)['attempts']===1,'unknown possible payment write is held for manual review');
    piReady($site,$otherId); piCheck(!$tasks->process($site,$otherId,$paymentApi) && $paymentCreates===2,'unknown payment create is never retried');
    $unknown=$bridge->originalDocument($otherEvent,array_replace($bridgeContext,['vendor_task_id'=>$otherId]),false);
    piCheck($unknown['status']==='unknown' && $unknown['vendor_task_id']===$otherId && $paymentCreates===2 && piErpHash($site)===$fundsBefore,'uncertain original voucher remains query-only on bridge; no second attempt or debit');
    piCheck($payments->capture($site,$id,1,piInput($parentPaymentView)+['settlement_id'=>$secondSettlement])['task']['id']===$otherId,'repeat capture cannot bypass unknown single-attempt settlement identity');
    piCheck(piTask($site,$id)===$parentPayment && piErpHash($site)===$beforePayment && $creates===$beforeCreates,'unknown remote registration leaves all local business and original purchase task unchanged');
    piCheck(!$payments->view($site,$otherId)['operator_complete'] && !$sync->view($site,$id)['closure']['operator_complete'],'uncertain second receipt cannot complete either its operator task or the full batch');
    $notice->send($site,$otherId);
    piCheck((int)piTask($site,$otherId)['notice_at']>0,'registration with an unknown result remains eligible for staff notification');
    $doneIds=piWorkbenchIds($app,'completed'); $todoIds=piWorkbenchIds($app,'action_required');
    piCheck(in_array($paymentId,$doneIds,true) && !in_array($paymentId,$todoIds,true),'actual controller lists matched receipt as completed, never as outstanding');
    piCheck(!in_array($otherId,$doneIds,true) && in_array($otherId,$todoIds,true),'actual controller lists uncertain result only as outstanding, never completed');
    $logCount->execute([$schema,$prefix.'hsx_boyuan_log']); piCheck((int)$logCount->fetchColumn()===0,'payment lifecycle writes no database technical-log table');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'fake_exchange_calls'=>$fakeCalls,'fake_creates'=>$creates,'fake_queries'=>$queries,'fake_stock_queries'=>$stockQueries,'fake_payment_creates'=>$paymentCreates,'external_calls'=>0,'business_database_modified'=>false,'random_schema_dropped_on_exit'=>true])."\n";
} catch(Throwable $error) {
    $failed=true; $detail=$error instanceof Problem?$error->reason:(str_starts_with($error->getMessage(),'ASSERT ')?$error->getMessage():'see exception location');
    fwrite(STDERR,'FAIL '.get_class($error).': '.$detail.' at '.basename($error->getFile()).':'.$error->getLine()."\n");
} finally {
    if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');
}
exit($failed?1:0);
