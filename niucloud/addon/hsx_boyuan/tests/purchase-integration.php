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
use addon\hsx_boyuan\app\service\{ConfigService,PurchaseSyncService,PurchaseClosureService,TaskService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_erp\app\service\admin\{ErpIntegrationService,ErpPurchaseService,ErpFinanceService};
use think\facade\Db;

$checks=0; $failed=false; $pdo=null; $schema=''; $fakeCalls=0; $creates=0; $queries=0;
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
function piReady(int $site,int $id): void { Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['next_at'=>0]); }
function piDispatch(int $site): void
{
    $service=ErpIntegrationService::forSite($site,1,'隔离采购测试员');
    foreach(Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.confirmed.v1')->order('id')->column('id') as $id) {
        $reply=$service->dispatchDomainEvent((int)$id);
        piCheck(!empty($reply['ok']),'ERP confirmed event dispatched to actual addon listener');
    }
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
    piCheck(Db::query('SELECT DATABASE() AS db')[0]['db']===$schema,'database connection is random isolated schema');
    $addon=new addon\hsx_boyuan\Addon(); piCheck($addon->install() && $addon->upgrade(),'current addon install and upgrade run only in isolated schema');
    $prefix=(string)Db::connect()->getConfig('prefix');
    $logCount=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $logCount->execute([$schema,$prefix.'hsx_boyuan_log']); piCheck((int)$logCount->fetchColumn()===0,'fresh schema has no database technical-log table');

    $secret=str_repeat('I',32); $configService=new ConfigService();
    $saved=$configService->save($site,1,['revision'=>0,'mode'=>'test','enabled'=>false,'paused'=>true,'purchase_enabled'=>false,'finance_enabled'=>false,'profile'=>[
        'protocol'=>'openapi_v153','endpoint'=>'https://isolated-buyer.example.invalid/api','gateway_path'=>'/gateway.do','app_id'=>'1234567890',
        'app_secret'=>$secret,'account_id'=>'ISOLATED_TEST_ACCOUNT','contract_confirmed'=>true,
    ]]);
    piCheck(!str_contains(Contract::json($saved),$secret),'synthetic app secret is not returned by config API');
    $profile=$configService->get($site,false)['profiles']['test']; $created=[];
    $exchange=function(string $path,array $params) use(&$fakeCalls,&$creates,&$queries,&$created): array {
        $fakeCalls++;
        piCheck($path==='/gateway.do' && ($params['big_gsdm']??'')==='ISOLATED_TEST_ACCOUNT','fake exchange is bound to synthetic account and official gateway');
        $service=$params['service']??'';
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
            $reply=['head'=>$head+['id'=>$headId,'big_gsdm'=>'ISOLATED_TEST_ACCOUNT','bill_id'=>$no,'bill_type'=>'PUR'],'product_details'=>['add_details'=>$rows]];
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
        throw new RuntimeException('ASSERT unexpected fake service; no external transport is allowed');
    };
    $api=new OpenApi($profile,$secret,$exchange,'test');
    piCheck($configService->probe($site,1,$api)['verified']===true,'real ConfigService accepts fake read-only company probe');
    piRejected(fn()=>piPatch($configService,$site,['enabled'=>true,'paused'=>false,'purchase_enabled'=>true,'confirm_account'=>'WRONG']),'ACCOUNT_CONFIRM_REQUIRED');
    $config=piPatch($configService,$site,['enabled'=>true,'paused'=>false,'purchase_enabled'=>true,'purchase_mode'=>'manual']);
    piCheck($config['mode']==='test' && $config['purchase_enabled'] && !$config['finance_enabled'] && $config['purchase_mode']==='manual','purchase-only test connection explicitly enabled');
    $key=$config['profiles']['test']['connection_key'];

    foreach(['companies','parties','products','warehouses','staff'] as $resource) {
        $run=bin2hex(random_bytes(16));
        Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'connection_key'=>$key,'mode'=>'test','resource'=>$resource,'run_key'=>$run,'status'=>'completed',
            'scope_json'=>'{}','processed'=>1,'pages'=>1,'reported_total'=>1,'operator_uid'=>1,'create_at'=>time(),'update_at'=>time()]);
        $id=['companies'=>'COMPANY_TEST','parties'=>'SUPPLIER_TEST','products'=>'PRODUCT_TEST','warehouses'=>'WAREHOUSE_TEST','staff'=>'BUYER_TEST'][$resource];
        $extra=['companies'=>[],'parties'=>['is_supplier'=>1],'products'=>['imei_count'=>1,'imei_len'=>15,'memory'=>'256GB','color'=>'黑色'],'warehouses'=>['company_id'=>'COMPANY_TEST'],'staff'=>['is_buyer'=>1]][$resource];
        Db::name('hsx_boyuan_reference')->insert(['site_id'=>$site,'connection_key'=>$key,'resource'=>$resource,'external_id'=>$id,'code'=>$id,'name'=>'隔离'.$resource,'enabled'=>1,'extra_json'=>Contract::json($extra),'seen_run'=>$run,'update_at'=>time()]);
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
    $initialCalls=$fakeCalls; $po=$makePurchase('01'); piDispatch($site);
    $detail=$purchase->info($po); $actions=$detail['integration_actions']??[]; $entry=$actions[0]??[]; $observed=end($purchaseActionEvents);
    piCheck((int)$detail['id']===$po && (int)($observed['purchase_id']??0)===$po && (int)($observed['site_id']??0)===$site,'actual ERP info emits the correct tenant-scoped purchase source ID');
    piCheck(count($actions)===1 && ($entry['key']??'')==='boyuan_purchase','actual registered addon listener supplies exactly one purchase entry');
    piCheck(($entry['pc_route']??'')==='/hsx_boyuan/purchase' && ($entry['mobile_path']??'')==='/addon/hsx_boyuan/pages/workbench/index'
        && preg_match('#^/[A-Za-z0-9_/-]+$#D',$entry['pc_route'])===1 && preg_match('#^/addon/[A-Za-z0-9_/-]+$#D',$entry['mobile_path'])===1,'ERP purchase entry returns only safe internal PC and mobile paths');
    piCheck(!str_contains(Contract::json($actions),$secret) && $fakeCalls===$initialCalls,'rendering actual purchase actions discloses no credential and performs no vendor request');
    Db::name('sys_user')->insert(['uid'=>2,'username'=>'isolated-no-role','real_name'=>'无博远权限测试员','status'=>1]);
    request()->uid(2);
    try { piCheck(($purchase->info($po)['integration_actions']??[])===[],'active user without a site role receives no Boyuan purchase entry'); }
    finally { request()->uid(1); }
    Db::startTrans();
    try {
        $temporary=$configService->get($site,false); $temporary['profiles']['test']['protocol']='bridge_v1'; $configService->persist($site,$temporary);
        piCheck(($purchase->info($po)['integration_actions']??[])===[],'non-OpenAPI connection receives no Boyuan purchase entry');
    } finally { Db::rollback(); }
    Db::startTrans();
    try {
        Db::name('erp_purchase_order')->where('site_id',$site)->where('id',$po)->update(['status'=>'void']);
        piCheck(($purchase->info($po)['integration_actions']??[])===[],'void purchase receives no Boyuan purchase entry');
    } finally { Db::rollback(); }
    piCheck(count($purchase->info($po)['integration_actions']??[])===1 && $fakeCalls===$initialCalls,'rollback restores permitted purchase entry without any vendor request');
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
    piCheck($task['snapshot_json']===$immutable && $task['request_json']===$frozen,'source and final request remain byte-for-byte immutable');
    $view=$sync->view($site,$id); $sync->queueVerify($site,$id,1,piInput($view)+['external_no'=>$task['external_no']]); piReady($site,$id);
    piCheck($tasks->process($site,$id,$api),'known number enters read-only purchase verification');
    $task=piTask($site,$id); $result=Contract::decode($task['result_json']);
    piCheck($task['status']==='succeeded' && $creates===1 && $queries===2,'purchase and serial queries complete without repeat create');
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
    $finance=ErpFinanceService::forSite($site,1,'隔离财务测试员');
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
    $secondAssets=Db::name('erp_asset')->where('site_id',$site)->where('purchase_order_id',$secondPo)->column('id');
    $batchPayables=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->whereIn('source_id',$secondAssets)->select()->toArray();
    $finance->confirmPayableItemsPayment($supplier,array_map(static fn($payable)=>['payable_id'=>(int)$payable['id'],'amount'=>(float)$payable['amount']],$batchPayables),['request_id'=>'CLOSURE_SHARED_BATCH_PAYMENT','capital_account_id'=>$capital]);
    $firstBatch=$closureService->view($site,piTask($site,$draftId)); $lastBatch=$closureService->view($site,$appendTask);
    piCheck($firstBatch['available'] && $firstBatch['totals']['amount']==='2.00' && $firstBatch['totals']['paid_amount']==='2.00' && $lastBatch['available'] && $lastBatch['totals']['amount']==='1.00' && $lastBatch['totals']['paid_amount']==='1.00','shared confirmed payment is allocated only to each task batch lines, not the entire purchase or payment');
    piCheck($firstBatch['source']['device_count']===2 && $lastBatch['source']['device_count']===1 && $firstBatch['stages'][4]['count']===1 && $lastBatch['stages'][4]['count']===1,'batch identity and shared-payment count remain accurate without exposing payment details');
    $logCount->execute([$schema,$prefix.'hsx_boyuan_log']); piCheck((int)$logCount->fetchColumn()===0,'full lifecycle never recreates a database log table');
    piCheck(Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','<>','purchase')->count()===0,'all actual addon tasks stay purchase-only after replay');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'fake_exchange_calls'=>$fakeCalls,'fake_creates'=>$creates,'fake_queries'=>$queries,'external_calls'=>0,'business_database_modified'=>false,'random_schema_dropped_on_exit'=>true])."\n";
} catch(Throwable $error) {
    $failed=true; $detail=$error instanceof Problem?$error->reason:(str_starts_with($error->getMessage(),'ASSERT ')?$error->getMessage():'see exception location');
    fwrite(STDERR,'FAIL '.get_class($error).': '.$detail.' at '.basename($error->getFile()).':'.$error->getLine()."\n");
} finally {
    if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');
}
exit($failed?1:0);
