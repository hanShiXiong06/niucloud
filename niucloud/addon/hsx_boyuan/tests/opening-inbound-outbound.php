<?php
declare(strict_types=1);

/** Local acceptance: use the same site, catalog row, device and location end to end.
 * No network or customer data; only tables in a new, randomly named local schema are writable.
 */
if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit(2);
if (ini_get('allow_url_fopen')) exit(2);
foreach (['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client'] as $fn) if (function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';
require __DIR__.'/LocalCycleExchange.php';

use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService, OpeningPostingService, ReferenceService, PurchaseSyncService, PurchaseInventoryService, TaskService, WholesaleSyncService};
use addon\hsx_boyuan\tests\LocalCycleExchange;
use addon\hsx_erp\app\adminapi\controller\ErpCounterparty;
use addon\hsx_erp\app\service\admin\{ErpCapitalAccountService, ErpFinanceService, ErpGoodsCatalogService, ErpIntegrationService, ErpPurchaseService, ErpSaleService, ErpWarehouseService};
use think\facade\Db;

function cycleAssert(bool $condition, string $label): void {
    $GLOBALS['boyuan_cycle_assertions']=($GLOBALS['boyuan_cycle_assertions']??0)+1;
    if (!$condition) throw new RuntimeException('验收未通过：'.$label);
}
function cycleParty(think\App $app, string $role, string $name): array {
    $original = $app->request;
    try {
        $app->instance('request', (new app\Request())->withGet(['role_type'=>$role, 'keyword'=>$name]));
        $rows = (new ErpCounterparty($app))->options()->getData()['data'];
        cycleAssert(count($rows) === 1 && $rows[0]['party_name'] === $name, 'ERP 往来单位选择器 / '.$role);
        return $rows[0];
    } finally {
        $app->instance('request', $original);
    }
}
function cycleDispatch(int $site): array {
    $service = ErpIntegrationService::forSite($site, 1, '隔离验收');
    $rows = Db::name('erp_outbox_event')->where('site_id', $site)->order('id')->select()->toArray();
    foreach ($rows as $row) {
        $result = $service->dispatchDomainEvent((int)$row['id']);
        cycleAssert(!empty($result['ok']), 'ERP 事件投递：'.$row['event_name'].' '.($result['message'] ?? ''));
    }
    return Db::name('erp_outbox_event')->where('site_id', $site)->field('id,event_name,status')->order('id')->select()->toArray();
}
function cycleAmounts(array $row): array {
    return ['id'=>(int)$row['id'],'amount'=>$row['amount'],'settled'=>$row['settled_amount'],'status'=>$row['status']];
}

$pdo=null; $schema=''; $report=['passed'=>false,'external_requests'=>0,'remote_receipt'=>'无真实博远接口访问；所有上游响应均为本地合成样本','steps'=>[]];
$phase='创建隔离 MySQL';
try {
    $recycleOrigin=in_array('--recycle-origin',$argv,true);
    [$app,$pdo,$schema,$site] = boyuanBoot(null,$recycleOrigin);
    $app->event->listen('HsxErpPurchaseActions',addon\hsx_boyuan\app\listener\PurchaseActions::class);
    $app->event->listen('HsxErpSaleActions',addon\hsx_boyuan\app\listener\PurchaseActions::class);
    $app->env->set('queue.state',false);
    $app->env->set('app.auth_key','ISOLATED_BOYUAN_OPENING_CYCLE_KEY_2026');
    (new addon\hsx_boyuan\Addon())->install();
    $report['php']=PHP_VERSION;
    $report['database_engine']=Db::query('SELECT VERSION() AS v')[0]['v'];
    $rows=static fn(string $table)=>Db::name($table)->where('site_id',$site);
    $phase='同步源资料';
    $cfg = new ConfigService();
    $secret = str_repeat('Z',32);
    $account='LOCAL_TEST_COMPANY';
    $cfg->save($site,1,['revision'=>0,'mode'=>'test','enabled'=>false,'paused'=>true,'profile'=>[
        'protocol'=>'openapi_v153','endpoint'=>'https://open-api-uat.139erp.com/api','gateway_path'=>'/gateway.do',
        'app_id'=>'1234567890','app_secret'=>$secret,'account_id'=>$account,'contract_confirmed'=>true]]);
    $company=['id'=>'COMP01','gsdm'=>$account,'name'=>'隔离验收账套'];
    $examples=[
        'boyuan.companys.get'=>[$company],
        'boyuan.categorys.get'=>[['id'=>'DEVICE','name'=>'设备'],['id'=>'PHONES','name'=>'手机','parent_id'=>'DEVICE']],
        'boyuan.products.get'=>[['id'=>'PRO01','code'=>'TEST-P1','name'=>'验收手机','full_name'=>'验收手机 256GB 黑色',
            'category_id'=>'PHONES','category_name'=>'设备/手机','brand_name'=>'验收牌','color'=>'黑色','memory'=>'256GB','imei_count'=>1,'imei_len'=>15]],
        'boyuan.warehouses.get'=>[['id'=>'WH01','code'=>'CK01','name'=>'验收自营仓','company_id'=>'COMP01','gsdm'=>$account]],
        'boyuan.custsuppliers.get'=>[['id'=>'SUP01','name'=>'验收供货商','is_supplier'=>1,'is_cust'=>0],['id'=>'CUS01','name'=>'验收同行客户','is_supplier'=>0,'is_cust'=>1]],
        'boyuan.users.get'=>[['id'=>'EMP01','name'=>'验收操作员','login_name'=>'TEST01','is_buyer'=>1,'is_sales'=>1,'company_id'=>'COMP01','gsdm'=>$account],
            ['id'=>'SALEOP','name'=>'销售经办员工','login_name'=>'SALEOP','company_id'=>'COMP01','gsdm'=>$account],
            ['id'=>'FOREIGN','name'=>'其他分公司经办','login_name'=>'FOREIGN','company_id'=>'COMP02','gsdm'=>$account]],
    ];
    $private=$cfg->get($site,false); $key=$private['profiles']['test']['connection_key'];
    $wire=new LocalCycleExchange($examples,$account,$secret);
    $api=new OpenApi($private['profiles']['test'],$secret,\Closure::fromCallable([$wire,'exchange']),'test');
    cycleAssert((bool)$cfg->probe($site,1,$api)['verified'],'合成账套校验');
    // The browser's compact switch operates on the verified, current account and cannot edit a profile.
    Db::startTrans();
    try {
        $original=$cfg->get($site,false);
        $state=$cfg->manualDeliveryState($site);
        $beforeWire=$wire->calls;
        $command=['revision'=>$state['revision'],'connection_key'=>$state['connection_key'],'intent'=>'enable','confirm_account'=>$account];
        $reject=static function($callback,string $reason): void {
            try { $callback(); } catch(\addon\hsx_boyuan\app\support\Problem $e) { cycleAssert($e->reason===$reason,'预期拒绝：'.$reason);return; }
            throw new RuntimeException('意外放行：'.$reason);
        };
        cycleAssert($state['eligible'] && !$state['enabled'] && !$state['purchase_enabled'] && $state['paused'] && !isset($state['profiles']),'只返回当前开关和非敏感确认信息');
        $reject(fn()=>$cfg->setManualDelivery($site,1,array_replace($command,['confirm_account'=>'WRONG'])),'ACCOUNT_CONFIRM_REQUIRED');
        $reject(fn()=>$cfg->setManualDelivery($site,1,array_replace($command,['connection_key'=>'WRONG'])),'CONFIG_STALE');
        $reject(fn()=>$cfg->setManualDelivery($site,1,$command+['uat_payment_enabled'=>true]),'MANUAL_DELIVERY_FIELDS');
        $on=$cfg->setManualDelivery($site,1,$command);
        cycleAssert($on['enabled'] && $on['purchase_enabled'] && !$on['paused'] && $on['purchase_mode']==='manual','手动投递启用，不能切自动');
        $reject(fn()=>$cfg->setManualDelivery($site,1,$command),'CONFIG_STALE');
        $off=$cfg->setManualDelivery($site,1,array_replace($command,['revision'=>$on['revision'],'intent'=>'pause','confirm_account'=>'']));
        cycleAssert(!$off['enabled'] && !$off['purchase_enabled'] && $off['paused'],'停止恢复三个开关');
        $after=$cfg->get($site,false);
        // The native configuration records first enablement for reading earlier receipts after pause.
        cycleAssert(($after['profiles']['test']['exchange_enabled_once']??false)===true,'开启后保留可读旧回执标志');
        $expected=$original; $expected['profiles']['test']['exchange_enabled_once']=true;
        foreach(['profiles','mode','finance_enabled','uat_payment_enabled','assignee_uid'] as $unchanged)
            cycleAssert($after[$unchanged]===$expected[$unchanged],'不变更账套、凭据及财务等配置：'.$unchanged);
        $originalMode=$after; $after['uat_payment_enabled']=true; $cfg->persist($site,$after);
        $reject(fn()=>$cfg->setManualDelivery($site,1,$command),'MANUAL_DELIVERY_UNAVAILABLE');
        $after=$originalMode; $after['mode']='production'; $cfg->persist($site,$after);
        $reject(fn()=>$cfg->setManualDelivery($site,1,$command),'MANUAL_DELIVERY_UNAVAILABLE');
        $after=$originalMode; $after['profiles']['test']['probe_at']=0; $cfg->persist($site,$after);
        $reject(fn()=>$cfg->setManualDelivery($site,1,$command),'MANUAL_DELIVERY_UNAVAILABLE');
        cycleAssert($wire->calls===$beforeWire,'切换只写配置，没有发单或访问上游');
    } finally { Db::rollback(); }
    $cfg->save($site,1,['revision'=>$cfg->get($site)['revision'],'confirm_account'=>$account,'enabled'=>true,'paused'=>false,'purchase_enabled'=>true,'purchase_mode'=>'manual']);
    $sync=new ReferenceService(); $names=['categories','products','warehouses','parties','companies','staff'];
    $jobs=$sync->start($site,1,$names,'opening',$key);
    foreach ($jobs['ids'] as $job) $sync->page($site,(int)$job,$api);
    cycleAssert($rows('hsx_boyuan_reference_sync')->where('status','completed')->count()===count($names),'商品、分类和业务档案均已同步');
    $report['steps']['references']=['resources'=>$names,'archived_rows'=>array_sum(array_map(fn($k)=>$rows(\addon\hsx_boyuan\app\support\CatalogStore::table($k))->count(),\addon\hsx_boyuan\app\support\CatalogStore::kinds()))];

    $phase='期初建账';
    $opening=new OpeningPostingService();
    $check=$opening->check($site,$key);
    cycleAssert($check['ready'] && !$check['payable_count'] && !$check['receivable_count'],'期初未结往来必须为零');
    foreach (['products'=>1,'warehouses'=>1,'parties'=>2] as $resource=>$count) {
        $request=['connection_key'=>$key,'resource'=>$resource,'cursor'=>0];
        $preview=$opening->handle($site,1,$request,false);
        cycleAssert(count($preview['rows'])===$count && count(array_filter($preview['rows'],fn($r)=>$r['action']==='create'))===$count,'预览不冲突：'.$resource);
        $apply=$opening->handle($site,1,$request+['token'=>$preview['token'],'confirm'=>true],true);
        $again=$opening->handle($site,1,$request,false);
        cycleAssert(count(array_filter($again['rows'],fn($r)=>$r['action']==='existing'))===$count,'建档后复查无重复：'.$resource);
        $report['steps']['opening'][$resource]=count($apply['rows']);
    }
    $phase='ERP 选择器和库位';
    $catalog=new ErpGoodsCatalogService(); $products=$catalog->lists(['keyword'=>'验收手机','is_enabled'=>1]);
    cycleAssert($products['total']===1,'ERP 商品选择器唯一命中');
    $product=$catalog->productSnapshot((int)$products['list'][0]['site_product_id']); $pid=(int)$product['catalog_product_id'];
    $whService=ErpWarehouseService::forSite($site,1);
    $wh=$whService->getOptions()[0]; $wid=(int)$wh['id'];
    cycleAssert($wh['warehouse_type']==='exception' && !$wh['allow_direct_sale'] && !$wh['allow_transfer'] && count($wh['locations'])===1
        && $wh['locations'][0]['location_code']==='DEFAULT' && $wh['locations'][0]['location_name']==='默认库位' && (int)$wh['locations'][0]['is_default']===1,'新仓默认受限，已有可直接选用的本站默认库位');
    $lid=(int)$wh['locations'][0]['id'];
    $whService->saveWarehouse(array_replace($wh,['warehouse_type'=>'owned','allow_direct_sale'=>1,'allow_transfer'=>1]),$wid);
    $wh=$whService->getOptions()[0];
    cycleAssert((int)$wh['id']===$wid && (int)$wh['locations'][0]['id']===$lid && $wh['warehouse_type']==='owned' && $wh['allow_direct_sale'],'ERP 中重读仓、库位');
    $supplier=cycleParty($app,'supplier','验收供货商'); $customer=cycleParty($app,'customer','验收同行客户');
    $report['steps']['selection']=['catalog_id'=>$pid,'product'=>$product['product_name'],'warehouse'=>$wh['warehouse_name'],'warehouse_id'=>$wid,'location'=>$wh['locations'][0]['location_name'],'location_id'=>$lid,'supplier_id'=>$supplier['id'],'customer_id'=>$customer['id']];

    $phase='采购挂账入库';
    if ($recycleOrigin) {
        require __DIR__.'/recycle-origin-cycle.php';
        $phase='回收接受报价 → ERP 入库 → 同行挂账出库';
        $report['steps']['recycle_origin']=cycleRecycleOrigin($app,$site,$wid,$lid,$customer,$api,$wire,$cfg);
    } else {
    $imei='990000000000010'; // reserved synthetic identifier in disposable schema
    $purchase=ErpPurchaseService::forSite($site,1,'隔离验收');
    $purchaseInput=['request_id'=>'CYCLE_LOCAL_PURCHASE_01','party_id'=>$supplier['id'],'party_name'=>$supplier['party_name'],'settle_method'=>'挂账','items'=>[[
        'catalog_product_id'=>$pid,'model'=>$product['product_name'],'imei'=>$imei,'spec'=>'256GB','color'=>'黑色','purchase_cost'=>2000,'retail_price'=>2500,'warehouse_id'=>$wid,'location_id'=>$lid,
        'image_urls'=>'["/addon/hsx_erp/resource/icon.png"]',
    ]]];
    $in=$purchase->create($purchaseInput);
    $poNo=(string)$rows('erp_purchase_order')->where('id',$in)->value('purchase_no');
    cycleAssert($poNo!=='','原采购业务单号已生成');
    $sale=ErpSaleService::forSite($site,1,'隔离验收');
    $stockFilter=['imei'=>$imei,'warehouse_id'=>$wid,'location_id'=>$lid,'catalog_product_id'=>$pid];
    $stock=$sale->stockPage($stockFilter);
    cycleAssert((int)$stock['total']===1 && $stock['data'][0]['imei']===$imei,'销售出库选择器能按串号/商品/仓位找到原机');
    $asset=$stock['data'][0]; $aid=(int)$asset['id'];
    cycleAssert($asset['status']==='in_stock' && (int)$asset['party_id']===$supplier['id'] && (int)$asset['purchase_order_id']===$in,'在库资产和原采购关联');
    $pi=$rows('erp_purchase_item')->where('purchase_order_id',$in)->find();
    cycleAssert((int)$pi['catalog_product_id']===$pid,'采购行保留原目录 ID');
    $ap=$rows('erp_payable')->where('party_id',$supplier['id'])->where('source_type','purchase_asset')->where('source_id',$aid)->find();
    cycleAssert($ap && $ap['amount']==='2000.00' && $ap['settled_amount']==='0.00','应付形成并关联同一资产');
    cycleDispatch($site);
    $task=$rows('hsx_boyuan_task')->where('kind','purchase')->find();
    cycleAssert($task && $task['status']==='awaiting_confirmation','形成待人工核对的采购任务');
    $purchaseSync=new PurchaseSyncService();
    $tid=(int)$task['id'];
    $view=$purchaseSync->view($site,$tid);
    $report['steps']['purchase']=['order_id'=>$in,'purchase_no'=>$poNo,'asset_id'=>$aid,'catalog_id'=>$pid,'imei'=>$imei,'state'=>$asset['status'],'payable'=>cycleAmounts($ap),
        'manual_task'=>$task['status'],'defaults'=>['supplier'=>$view['selection']['supplier_id']??'','warehouse'=>$view['selection']['lines'][0]['warehouse_id']??'','product'=>$view['selection']['lines'][0]['product_id']??'']];
    $phase='同设备采购手动同步与合成对端核验';
    $v=static fn($r)=>array_intersect_key($r,array_flip(['revision','mode','connection_key']));
    $oneTask=static fn(int $id)=>$rows('hsx_boyuan_task')->where('id',$id)->find();
    $worker = new TaskService();
    $process=static function(int $id) use ($worker,$site,$rows,$api) {
        // Advance the one-second queue release time in our disposable schema; no business fields are mutated.
        $rows('hsx_boyuan_task')->where('id',$id)->update(['next_at'=>0]);
        cycleAssert($worker->process($site,$id,$api),'受控工作进程应处理本任务');
    };
    $selection=array_replace($view['selection'],['company_id'=>'COMP01','buyer_id'=>'EMP01','creator_id'=>'EMP01']);
    $manual=in_array('--manual-command-suite',$argv,true);
    $commands=new \addon\hsx_boyuan\app\service\ManualDocumentCommandService();
    $inCommand=$v($view['task'])+['selection'=>$selection,'remember'=>false];
    if($manual) {
        $submitted=$commands->run($site,$tid,1,'purchase',false,$inCommand,false,$api);
        cycleAssert($submitted['task']['status']==='created_review' && $submitted['task']['external_no']!=='' && $wire->calls['boyuan.biz.bill.purchase.generate']===1,'明确提交只执行本采购一次，并返回真实服务层回执');
        $reject(fn()=>$commands->run($site,$tid,1,'purchase',false,$inCommand,false,$api),'TASK_STALE');
    } else {
        $submitted=$purchaseSync->submit($site,$tid,1,$inCommand);
        cycleAssert($submitted['task']['status']==='pending','采购人工确认并排队');
        $process($tid);
    }
    $created=$oneTask($tid);
    cycleAssert($created['status']==='created_review','采购已建待核：'.$created['reason']);
    if($manual) {
        $verifiedView=$commands->run($site,$tid,1,'purchase',true,$v($created)+['external_no'=>$created['external_no']],false,$api);
        cycleAssert($verifiedView['task']['status']==='succeeded' && $wire->calls['boyuan.biz.bill.purchase.generate']===1,'核对仅查同一笔采购，无重发');
    } else { $purchaseSync->queueVerify($site,$tid,1,$v($created)); $process($tid); }
    $verified=$oneTask($tid);
    cycleAssert($verified['status']==='succeeded','采购单和串号已核：'.$verified['reason']);
    $invService=new PurchaseInventoryService();
    $inv=$invService->queue($site,$tid,1,$v($verified),$manual,$api); $invId=(int)$inv['inventory']['task_id'];
    if($manual) {
        cycleAssert($inv['inventory']['status']==='succeeded','本批库存只读查询同一POST返回结果，不需运行worker');
        $invCalls=$wire->calls; $invService->queue($site,$tid,1,$v($verified),true,$api);
        cycleAssert($invCalls===$wire->calls,'已取得有效在库证据不重复查询');
    } else $process($invId);
    $inv=$oneTask($invId);
    cycleAssert($inv['status']==='succeeded','合成库存核验：'.$inv['reason']);
    $report['steps']['simulated_vendor_purchase']=['bill'=>$verified['external_no'],'status_path'=>['awaiting_confirmation','pending',$created['status'],$verified['status']],
        'verification'=>json_decode($verified['result_json'],true)['verification_level'],'stock_before_sale'=>json_decode($inv['result_json'],true)['verification_level']];
    $phase='同行挂账销售出库';
    $saleInput=['request_id'=>'CYCLE_LOCAL_SALE_01','sale_channel_key'=>'erp_peer','party_id'=>$customer['id'],'party_name'=>$customer['party_name'],'settle_mode'=>'credit','items'=>[['asset_id'=>$aid,'sale_price'=>2500]]];
    $out=$sale->create($saleInput);
    $soNo=(string)$rows('erp_sale_order')->where('id',$out)->value('sale_no');
    cycleAssert($soNo!=='' && $soNo!==$poNo,'原销售业务单号已生成');
    $sold=$rows('erp_asset')->where('id',$aid)->find();
    $saleLine=$rows('erp_sale_item')->where('id',$sold['sale_item_id'])->find();
    cycleAssert((int)$saleLine['warehouse_id']===$wid && (int)$saleLine['location_id']===$lid,'出库明细必须保留出库当时仓库和库位快照');
    cycleAssert($sold['imei']===$imei && $sold['status']==='sold' && (int)$sold['sale_order_id']===$out && (int)$sold['catalog_product_id']===$pid && (int)$sold['warehouse_id']===$wid && (int)$sold['location_id']===$lid && (int)$sale->stockPage($stockFilter)['total']===0,'原机出库后不可再选');
    $ar=$rows('erp_receivable')->where('party_id',$customer['id'])->where('source_type','sale')->where('source_id',$out)->find();
    cycleAssert($ar && $ar['amount']==='2500.00' && $ar['settled_amount']==='0.00','应收形成并指向原销售');
    cycleAssert($sale->create($saleInput)===$out,'同一操作重复提交不重复出库');
    $denied=false;
    try { $sale->create(array_replace($saleInput,['request_id'=>'CYCLE_LOCAL_SALE_02'])); }
    catch (\core\exception\CommonException $e) { $denied=str_contains($e->getMessage(),'不可销售'); }
    cycleAssert($denied && $rows('erp_sale_order')->count()===1 && $rows('erp_receivable')->count()===1,'另起一单时拦截重复销售，不产生新的应收');
    $moves=$rows('erp_asset_ledger')->where('asset_id',$aid)->order('id')->field('action,before_status,after_status,source_id,source_no,source_type')->select()->toArray();
    cycleAssert(array_column($moves,'action')===['inbound','sold'] && (int)$moves[0]['source_id']===$in && (int)$moves[1]['source_id']===$out
        && array_column($moves,'source_no')===[$poNo,$soNo],'串号流水及原单号与实际采购/销售一致');
    $report['steps']['sale']=['order_id'=>$out,'sale_no'=>$soNo,'asset_id'=>$aid,'imei'=>$sold['imei'],'state'=>$sold['status'],'sellable_count'=>0,'receivable'=>cycleAmounts($ar),'duplicate_sale_rejected'=>$denied,'stock_movements'=>$moves,
        'stock_path'=>['from_supplier'=>$supplier['party_name'],'in_stock_warehouse'=>$asset['warehouse_name'],'in_stock_location'=>$asset['location_name'],
            'outbound_warehouse'=>$saleLine['warehouse_name'],'outbound_location'=>$saleLine['location_name'],'to_customer'=>$customer['party_name']]];
    $guard=$opening->check($site,$key);
    cycleAssert(!$guard['ready'] && $guard['payable_count']===1 && $guard['receivable_count']===1,'产生未结应收应付后不能重新建账');
    $report['steps']['opening_blocked_with_open_balances']=true;
    $earlyWholesale=new WholesaleSyncService();
    $sourceTasks=static fn()=>$rows('hsx_boyuan_task')->where('kind',WholesaleSyncService::KIND);
    $wireAtPrepare=$wire->calls;
    cycleAssert($sourceTasks()->count()===0 && $earlyWholesale->canPrepareSource($site,$out,$soNo) && !$sourceTasks()->count(),'事件已存在但尚无任务，GET准备能力只读');
    $sale->info($out);
    cycleAssert(!$sourceTasks()->count() && $wire->calls===$wireAtPrepare,'打开销售详情不补录任务、不外发');
    $rejectPrepare=static function(int $checkSite,string $number) use($earlyWholesale,$out,$sourceTasks,$wire,$wireAtPrepare):void {
        cycleAssert(!$earlyWholesale->canPrepareSource($checkSite,$out,$number),'不可操作的销售来源不显示准备按钮');
        $denied=false;
        try { $earlyWholesale->prepareSource($checkSite,1,$out,$number); }
        catch(\addon\hsx_boyuan\app\support\Problem $e) { $denied=true; }
        cycleAssert($denied && !$sourceTasks()->count() && $wire->calls===$wireAtPrepare,'拒绝补录时不新增任务、不对外建单');
    };
    $rejectPrepare($site+1,$soNo);
    $rejectPrepare($site,'WRONG-SALE-NO');
    foreach([
        '暂停'=>static fn()=>$cfg->persist($site,array_replace($cfg->get($site,false),['paused'=>true])),
        '事件早于启用时间'=>static fn()=>$cfg->persist($site,array_replace($cfg->get($site,false),['started_at'=>time()+86400,'purchase_started_at'=>time()+86400])),
        '没有已核采购'=>static fn()=>$rows('hsx_boyuan_task')->where('id',$tid)->update(['status'=>'created_review']),
        '串号与原始单据不符'=>static fn()=>$rows('erp_asset')->where('id',$aid)->update(['imei'=>'990000000000099']),
        '非挂账销售'=>static fn()=>$rows('erp_sale_order')->where('id',$out)->update(['settle_method'=>'现结']),
    ] as $change) {
        Db::startTrans();
        try { $change(); $rejectPrepare($site,$soNo); } finally {Db::rollback();}
    }
    Db::startTrans();
    try {
        $events=$rows('erp_outbox_event')->where('event_name','erp.integration.document.confirmed.v1')->select()->toArray();
        $source=array_values(array_filter($events,static fn($e)=>(json_decode($e['payload_json'],true)['payload']['source_document_id']??'')==='sale:'.$out));
        cycleAssert(count($source)===1,'本次销售信息来自唯一的原始事件');
        $copy=$source[0];unset($copy['id']);$copy['event_id']='EV_TEST_DUPLICATE_SOURCE';
        Db::name('erp_outbox_event')->insert($copy);
        $rejectPrepare($site,$soNo);
    } finally {Db::rollback();}
    (new addon\hsx_erp_integration\Addon())->upgrade();
    Db::name('addon')->insert(['key'=>'hsx_boyuan','status'=>1,'title'=>'Fixture','type'=>'addon']);
    $saleEvent=json_decode($source[0]['payload_json'],true,512,JSON_THROW_ON_ERROR); unset($saleEvent['_delivery']);
    $bridge=new \addon\hsx_boyuan\app\adapter\BoyuanErpAdapter(); $identity=$bridge->originalProfile($site);
    $hubConnection=Db::name('erp_integration_connection')->insertGetId(['site_id'=>$site,'adapter_code'=>'boyuan','adapter_name'=>'Fixture','sync_mode'=>'manual',
        'status'=>'enabled','bridge_started_at'=>$saleEvent['occurred_at'],'last_test_result'=>'success','config'=>json_encode(array_diff_key($identity,['checked'=>true]),JSON_THROW_ON_ERROR),
        'business_types'=>'["sale"]']);
    $hubBus=new \addon\hsx_erp_integration\app\service\IntegrationBusService();
    $hubManual=new \addon\hsx_erp_integration\app\service\ManualTaskService();
    $hubAccepted=$hubBus->acceptDomainEvent($saleEvent); $hubTask=(int)($hubAccepted['task_ids'][0]??0);
    cycleAssert($hubTask>0 && $hubBus->acceptDomainEvent($saleEvent)['status']==='duplicate'
        && !in_array('sale',$bridge->manualBusinessTypes('production'),true) && !$bridge->syncSale([],[])['success'],'仅测试同行原单接入且重放复用桥任务；生产及旧聚合直发关闭');
    $retail=$saleEvent; $retail['payload']['sale_type']='retail'; $cash=$saleEvent; $cash['payload']['payment_method_ref']='method:现结';
    cycleAssert(!$hubBus->acceptDomainEvent($retail) && !$hubBus->acceptDomainEvent($cash),'零售及现结不冒充同行挂账');
    Db::startTrans();
    try {
        $rows('hsx_boyuan_task')->where('id',$tid)->update(['status'=>'created_review']);
        cycleAssert($hubManual->run($site,$hubTask,1,false)['status']==='waiting' && !$sourceTasks()->count(),'逐台原采购回执未齐时，桥不创建博远销售任务');
    } finally {Db::rollback();}
    cycleAssert($hubManual->run($site,$hubTask,1,true)['status']==='waiting' && !$sourceTasks()->count(),'原单桥查看不会创建厂商出库');
    $hubLinked=$hubManual->run($site,$hubTask,1,false);
    $prepared=$earlyWholesale->prepareSource($site,1,$out,$soNo);
    $repeat=$earlyWholesale->prepareSource($site,1,$out,$soNo);
    cycleAssert($prepared['task_id']>0 && $prepared['sale_no']===$soNo && $repeat['task_id']===$prepared['task_id'] && $sourceTasks()->count()===1
        && $sourceTasks()->value('status')==='awaiting_confirmation' && $wire->calls===$wireAtPrepare,'首次显式准备只生成本站待核任务，再次准备复用，博远无写入');
    cycleAssert($hubLinked['status']==='linked' && $hubLinked['vendor_task_id']===$prepared['task_id'],'桥和原工作台复用同一台设备的同一个待核同行任务');
    $report['steps']['outbox']=cycleDispatch($site);
    cycleAssert($sourceTasks()->count()===1 && (int)$sourceTasks()->value('id')===(int)$prepared['task_id'] && $wire->calls===$wireAtPrepare,'后到的领域事件同样复用手动准备的原销售任务');
    cycleAssert(in_array('erp.asset.sold.v1',array_column($report['steps']['outbox'],'event_name'),true),'销售出库已发出本站事件');
    $phase='同设备批发出库同步与合成对端核验';
    $w=$rows('hsx_boyuan_task')->where('kind',WholesaleSyncService::KIND)->find();
    cycleAssert($w && $w['status']==='awaiting_confirmation','原 ERP 事件捕获同笔挂账批发销售、未自动外发');
    $widTask=(int)$w['id']; $wholesale=new WholesaleSyncService();
    $wrongRole=false;
    try { $wholesale->submit($site,$widTask,1,$v($w)+['customer_id'=>'SUP01']); }
    catch (\addon\hsx_boyuan\app\support\Problem $e) { $wrongRole=$e->reason==='SALE_CUSTOMER_INVALID'; }
    cycleAssert($wrongRole && !isset($wire->calls['boyuan.biz.bill.sale.generate']),'不得误把非客户的供货商作为销售客户');
    $snapshot=\addon\hsx_boyuan\app\support\Contract::decode($w['snapshot_json']); $operator=$snapshot['operator']['operator_ref'];
    $draft=$wholesale->creatorDraft($site,$key,$snapshot);
    cycleAssert($draft['company_id']==='COMP01' && $draft['mapped']===null && $draft['customer']['id']==='CUS01' && $draft['customer']['name']==='验收同行客户','实际已映射的原销售客户提供给核对页；没有员工对应不能冒用原采购制单员');
    $wrongCustomer=$snapshot; $wrongCustomer['party']['ref']='party:999999';
    cycleAssert($wholesale->creatorDraft($site,$key,$wrongCustomer)['customer']===null, '没有对应关系的客户不按名称猜测');
    foreach([['','SALE_CREATOR_REQUIRED'],['FOREIGN','SALE_CREATOR_INVALID'],['unknown','SALE_CREATOR_INVALID']] as [$creator,$code]) {
        $failed=false; try { $wholesale->submit($site,$widTask,1,$v($w)+['creator_id'=>$creator]); }
        catch(\addon\hsx_boyuan\app\support\Problem $e){ $failed=$e->reason===$code; }
        cycleAssert($failed && $oneTask($widTask)['side_effect']==='none','缺失、跨公司或未同步制单人不能外发');
    }
    $staffResults=$sync->search($site,['resource'=>'staff','company_id'=>'COMP01','enabled_only'=>true,'expected_connection_key'=>$key]);
    cycleAssert(!in_array('FOREIGN',array_column($staffResults['items'],'id'),true),'制单人选择器只列出出库公司员工');
    Db::startTrans();
    try {
        $wholesale->submit($site,$widTask,1,$v($w)+['creator_id'=>'SALEOP']);
        $h=\addon\hsx_boyuan\app\support\Contract::decode($oneTask($widTask)['request_json'])['biz']['head'];
        cycleAssert($h['creater_id']==='SALEOP' && $h['creater_name']==='销售经办员工','手选制单人时 ID 和名称均取自同公司有效档案');
    } finally { Db::rollback(); }
    Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$site,'connection_key'=>$key,'kind'=>'staff','local_ref'=>$operator,'external_id'=>'SALEOP','extra_json'=>'{}','update_at'=>time()]);
    cycleAssert($wholesale->creatorDraft($site,$key,$snapshot)['mapped']===['id'=>'SALEOP','name'=>'销售经办员工'],'原销售操作员映射优先，非采购或销售业务员代填');
    if (in_array('--guard-suite',$argv,true)) {
        require __DIR__.'/wholesale-business-guards.php';
        $report['steps']['wholesale_guards']=cycleWholesaleGuards($site,$widTask,$key,$api,$cfg,$account,$v,$oneTask);
    }
    $logStartCreates=boyuanLogCount($schema,'wholesale_create',$widTask);
    $logStartReads=boyuanLogCount($schema,'wholesale_readback',$widTask);
    if($manual && in_array('--manual-new-sale',$argv,true)) {
        $firstSale=$commands->run($site,$widTask,1,WholesaleSyncService::KIND,false,$v($w),false,$api);
        cycleAssert($firstSale['status']==='created_review' && $firstSale['external_no']!=='' && $wire->calls['boyuan.biz.bill.sale.generate']===1,'新销售单确认后无需另点处理或常驻队列，返回本单回执');
    } else {
    $pending=$wholesale->submit($site,$widTask,1,$v($w)); // Original customer's opening mapping supplies its Boyuan identity.
    cycleAssert(\addon\hsx_boyuan\app\support\Contract::decode($oneTask($widTask)['request_json'])['biz']['head']['creater_id']==='SALEOP','无人工覆盖时冻结原制单员对应');
    cycleAssert($pending['status']==='pending','销售核对确认并排队');
    if($manual) {
        $curr=$oneTask($widTask); $guardBase=$wire->calls;
        $reject(fn()=>$commands->run($site,$widTask,1,WholesaleSyncService::KIND,true,$v($curr),true,$api),'TASK_STALE');
        $reject(fn()=>$commands->run($site,$widTask,1,'purchase',false,$v($curr),true,$api),'TASK_STALE');
        $reject(fn()=>$commands->run($site,$widTask,1,WholesaleSyncService::KIND,false,array_replace($v($curr),['connection_key'=>str_repeat('f',32)]),true,$api),'TASK_STALE');
        foreach([['side_effect'=>'unknown'],['attempts'=>1],['status'=>'processing','lease_until'=>time()+60]] as $unsafe) {
            Db::startTrans();
            try {
                $rows('hsx_boyuan_task')->where('id',$widTask)->update($unsafe);
                $reject(fn()=>$commands->run($site,$widTask,1,WholesaleSyncService::KIND,false,$v($curr),true,$api),'TASK_STALE');
            } finally {Db::rollback();}
        }
        foreach([['paused'=>true],['mode'=>'production'],['purchase_mode'=>'automatic']] as $change) {
            Db::startTrans();
            try {
                $cfg->persist($site,array_replace($cfg->get($site,false),$change));
                $reject(fn()=>$commands->run($site,$widTask,1,WholesaleSyncService::KIND,false,$v($curr),true,$api),'MANUAL_TASK_UNAVAILABLE');
            } finally {Db::rollback();}
        }
        Db::startTrans();
        try {
            $rows('hsx_boyuan_task')->where('id',$widTask)->update(['next_at'=>time()+300]);
            cycleAssert($commands->run($site,$widTask,1,WholesaleSyncService::KIND,false,$v($curr),true,$api)['status']==='pending','不抢跑尚未到期的任务');
        } finally {Db::rollback();}
        cycleAssert($wire->calls===$guardBase,'不用错误意图、错误连接、未知结果、关闭或未到期任务调用博远');
        $completed=$commands->run($site,$widTask,1,WholesaleSyncService::KIND,false,$v($curr),true,$api);
        cycleAssert($completed['status']==='created_review' && $completed['external_no']!=='','已有明确确认待发送的同一销售任务直接读取新回执');
    } else $process($widTask);
    }
    $registered=$oneTask($widTask);
    cycleAssert($registered['status']==='created_review','批发单已建待核：'.$registered['reason']);
    cycleAssert($hubManual->run($site,$hubTask,1,true)['status']==='linked','只有原创建回执时，桥不提前确认出库');
    if($manual) $commands->run($site,$widTask,1,WholesaleSyncService::KIND,true,$v($registered),false,$api);
    else { $wholesale->verify($site,$widTask,1,$v($registered)); $process($widTask); }
    $matched=$oneTask($widTask);
    cycleAssert($matched['status']==='succeeded','逐台核对批发单：'.$matched['reason']);
    $hubSale=$hubManual->run($site,$hubTask,1,true);
    $hubRow=$rows('erp_integration_sync_task')->where('connection_id',$hubConnection)->where('id',$hubTask)->find();
    cycleAssert($hubSale['status']==='confirmed' && $hubRow['status']==='succeeded' && $hubRow['external_doc_no']===$matched['external_no']
        && $hubSale['vendor_task_id']===(int)$matched['id'] && $ar['settled_amount']==='0.00','原厂商签名测试回执验证同机出库后回写桥；不当作客户已付款');
    $wireBefore=$wire->calls;
    Db::startTrans();
    try {
        $stopped=$cfg->get($site,false); $stopped['enabled']=false; $stopped['purchase_enabled']=false; $stopped['paused']=true; $cfg->persist($site,$stopped);
        foreach ([[$purchase->info($in),$verified],[$sale->info($out),$matched]] as [$source,$taskRow]) {
            $r=$source['integration_results']??[]; $first=$r[0]??[];
            $permittedReadLinks=$taskRow['kind']==='purchase'?['boyuan_exchange']:[];
            cycleAssert(count($r)===1 && ($first['status']??'')==='succeeded' && $first['external_no']===$taskRow['external_no']
                && $first['task_id']===(int)$taskRow['id'] && array_column($source['integration_actions'],'key')===$permittedReadLinks,'暂停后可查看原单和往来，不展示写操作');
        }
        $rows('hsx_boyuan_task')->where('id',$tid)->update(['external_no'=>'MANUAL-CANDIDATE','result_json'=>'{}','status'=>'confirming']);
        cycleAssert($purchase->info($in)['integration_results'][0]['external_no']==='','人工输入的候选单号不作为博远返回单号显示');
        $cfg->persist($site,array_replace($stopped,['mode'=>'mock']));
        cycleAssert(!$purchase->info($in)['integration_results'] && !$sale->info($out)['integration_results'],'模拟账套不能看原测试账套的核验结果');
        $stopped['profiles']['test']['connection_key']=str_repeat('f',32); $cfg->persist($site,$stopped);
        $miss=$sale->info($out)['integration_results'];
        cycleAssert(count($miss)===1 && $miss[0]['status']==='not_recorded' && !$miss[0]['external_no'] && !$miss[0]['task_id'],'切换到不同连接不能看上一个账套的单号');
    } finally { Db::rollback(); }
    cycleAssert($wire->calls===$wireBefore,'读取原单结果不能产生外部读写或触发重发');
    $readback=json_decode($matched['result_json'],true,512,JSON_THROW_ON_ERROR)['verification'];
    cycleAssert(!$readback['financial_posting_verified'] && !$readback['stock_balance_verified'],'销售核验不能冒充财务或上游库存余额证明');
    $again=false;
    try { $wholesale->submit($site,$widTask,1,$v($matched)); }
    catch (\addon\hsx_boyuan\app\support\Problem $e) { $again=$e->reason==='SALE_ALREADY_SENT'; }
    cycleAssert($again && $wire->calls['boyuan.biz.bill.sale.generate']===1 && $wire->calls['boyuan.biz.sales.query']===1,'已确认出库禁止再建单');
    $report['steps']['simulated_vendor_sale']=['bill'=>$matched['external_no'],'status_path'=>['awaiting_confirmation','pending',$registered['status'],$matched['status']],
        'verification'=>$readback['verification_level'],'automatic_customer_match'=>'CUS01','duplicate_submit_rejected'=>$again,'vendor_accounts_receivable_verified'=>false,'vendor_stock_balance_verified_by_sale_query'=>false];
    $phase='ERP 本地收付款';
    $localAccount=(new ErpCapitalAccountService())->save(['account_name'=>'验收记账账户','account_type'=>'cash','balance'=>3000]);
    $finance=ErpFinanceService::forSite($site,1,'隔离验收');
    $payId=$finance->confirmPayment((int)$ap['id'],2000,['request_id'=>'CYCLE_LOCAL_PAYMENT_01','capital_account_id'=>$localAccount]);
    $receiptId=$finance->confirmReceipt((int)$ar['id'],2500,['request_id'=>'CYCLE_LOCAL_RECEIPT_01','capital_account_id'=>$localAccount,'items'=>[
        ['sale_item_id'=>(int)$sold['sale_item_id'],'sale_price'=>2500,'amount'=>2500]]]);
    $ap=$rows('erp_payable')->where('id',$ap['id'])->find(); $ar=$rows('erp_receivable')->where('id',$ar['id'])->find();
    $balance=$rows('erp_capital_account')->where('id',$localAccount)->value('balance');
    cycleAssert($ap['amount']===$ap['settled_amount'] && $ar['amount']===$ar['settled_amount'] && (float)$balance===3500.0,'本地核销两笔源单且资金账增减吻合');
    $report['steps']['local_settlement']=['payment_id'=>$payId,'receipt_id'=>$receiptId,'payable'=>cycleAmounts($ap),'receivable'=>cycleAmounts($ar),'local_account_balance'=>$balance,'bank_transfer'=>false];
    $report['steps']['outbox']=cycleDispatch($site);
    $defaults=$report['steps']['purchase']['defaults'];
    cycleAssert($defaults===['supplier'=>'SUP01','warehouse'=>'WH01','product'=>'PRO01'],'采购核对直接带出期初对应的供应商、仓库和唯一商品');
    $report['in_process_fixture_calls']=$wire->calls;
    $prefix=(string)Db::connect()->getConfig('prefix');
    $logTable=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $logTable->execute([$schema,$prefix.'hsx_boyuan_log']);
    $logWrites=boyuanLogCount($schema,'wholesale_create',$widTask)-$logStartCreates;
    $logReads=boyuanLogCount($schema,'wholesale_readback',$widTask)-$logStartReads;
    cycleAssert((int)$logTable->fetchColumn()===0 && $logWrites===1 && $logReads===1,'不建技术日志表，写入与回查各有一条文件 Log');
    $report['steps']['audit']=['vendor_log_table'=>false,'framework_log_sale_create'=>$logWrites,'framework_log_sale_readback'=>$logReads];
    }
    $report['passed']=true;
    $report['assertions']=$GLOBALS['boyuan_cycle_assertions']??0;
} catch (Throwable $e) {
    fwrite(STDERR,'FAIL '.$phase.' '.get_class($e).' '.($e instanceof PDOException?'PDO '.$e->getCode():$e->getMessage()).' @ '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);
} finally {
    if ($pdo && preg_match('/^hsx_boyuan_test_[0-9a-f]{16}$/D',$schema)) {
        $pdo->exec('DROP DATABASE `'.$schema.'`');
        $report['isolated_schema_removed']=true;
    }
}
$report['executed_at']=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format(DATE_ATOM);
$json=json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
if ($report['passed'] && !$recycleOrigin && in_array('--evidence',$argv,true)) {
    $dir=dirname(__DIR__,4).'/deliverables/boyuan-local-cycle-20261118';
    if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('无法创建验收目录');
    if(file_put_contents($dir.'/local-cycle.json',$json,LOCK_EX)===false) throw new RuntimeException('无法保存验收记录');
    require __DIR__.'/local-cycle-report.php';
    if(file_put_contents($dir.'/本地验收结果.html',cycleEvidenceHtml($report),LOCK_EX)===false) throw new RuntimeException('无法保存可读验收报告');
}
echo $json;
exit($report['passed']?0:1);
