<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $function) if (function_exists($function)) exit("Disable network functions before testing\n");
require __DIR__.'/bootstrap.php';
use think\facade\Db;
use addon\hsx_boyuan\app\service\{ConfigService,TaskService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_erp\app\service\admin\{ErpPurchaseService,ErpSaleService,ErpIntegrationService,ErpSaleReturnService,ErpPurchaseReturnService,ErpFinanceService};
$pdo=null; $schema=''; $checks=0; $failed=false;
function check(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function drain(int $site): void {
    $integration=ErpIntegrationService::forSite($site,1,'测试经办人');
    foreach (Db::name('erp_outbox_event')->where('site_id',$site)->column('id') as $id) $integration->dispatchDomainEvent((int)$id);
    for($round=0;$round<5;$round++) {
        Db::name('hsx_boyuan_task')->where('site_id',$site)->update(['next_at'=>0]); (new TaskService())->tick($site,100);
    }
}
function mapTask(int $site,array $task): void {
    $snapshot=Contract::decode($task['snapshot_json']); $key=$task['connection_key'];
    $pairs=[];
    if (isset($snapshot['party'])) $pairs[]=[in_array($snapshot['kind'],['purchase','purchase_return'],true)?'supplier':'customer',$snapshot['party']['ref']];
    foreach($snapshot['lines']??[] as $line) { $pairs[]=['product',$line['product_ref']]; $pairs[]=['warehouse',$line['warehouse_ref']]; }
    if (isset($snapshot['salesperson_ref'])) $pairs[]=['staff',$snapshot['salesperson_ref']];
    if (isset($snapshot['payment_method_ref'])) $pairs[]=['method',$snapshot['payment_method_ref']];
    if (isset($snapshot['account_ref'])) $pairs[]=['account',$snapshot['account_ref']];
    if (isset($snapshot['method_ref'])) $pairs[]=['method',$snapshot['method_ref']];
    foreach($pairs as [$kind,$ref]) if (!Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$key)->where('kind',$kind)->where('local_ref',$ref)->count()) Db::name('hsx_boyuan_mapping')->insert([
        'site_id'=>$site,'connection_key'=>$key,'kind'=>$kind,'local_ref'=>$ref,'local_name'=>'测试对应','external_id'=>'MOCK_'.strtoupper($kind).'_'.substr(hash('sha256',$ref),0,10),
        'external_code'=>'CODE_'.substr(hash('sha256',$ref),0,10),'external_name'=>'模拟档案','extra_json'=>Contract::json(['unit_code'=>'TAI']),'update_at'=>time()]);
}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot();
    $addon=new addon\hsx_boyuan\Addon(); check($addon->install(),'install'); check($addon->upgrade(),'upgrade');
    check(Db::name('sys_schedule')->where('addon','hsx_boyuan')->count()===1,'one schedule after upgrade');
    $configService=new ConfigService();
    $config=$configService->save($site,1,['revision'=>0,'mode'=>'mock','enabled'=>true,'paused'=>false,'finance_enabled'=>true]);
    check($config['mode']==='mock' && !isset($config['profiles']['mock']['token_cipher']),'configuration public');
    $add=fn($table,$row)=>(int)Db::name($table)->insertGetId(['site_id'=>$site]+$row);
    $supplier=$add('erp_party',['party_no'=>'SAMPLE_SUP','party_name'=>'测试供应商','party_type'=>'supplier','status'=>1]);
    $customer=$add('erp_party',['party_no'=>'SAMPLE_CUS','party_name'=>'测试客户','party_type'=>'customer','status'=>1]);
    $warehouse=$add('erp_warehouse',['warehouse_name'=>'测试仓','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
    $location=$add('erp_warehouse_location',['warehouse_id'=>$warehouse,'location_name'=>'测试库位','status'=>1]);
    $account=$add('erp_capital_account',['account_name'=>'测试账户','account_type'=>'bank','balance'=>20000,'status'=>1]);
    $purchase=ErpPurchaseService::forSite($site,1,'测试经办人'); $sale=ErpSaleService::forSite($site,1,'测试经办人');
    $input=['party_id'=>$supplier,'party_name'=>'测试供应商','request_id'=>'BOYUAN_PURCHASE_01','settle_method'=>'挂账','items'=>[
        ['imei'=>'BOYUAN_TEST_SERIAL_01','model'=>'测试手机','spec'=>'256GB','purchase_cost'=>2000,'retail_price'=>2600,'warehouse_id'=>$warehouse,'location_id'=>$location,'image_urls'=>'["/static/fixture.png"]'],
        ['imei'=>'BOYUAN_TEST_SERIAL_02','model'=>'测试手机','spec'=>'256GB','purchase_cost'=>1800,'retail_price'=>2400,'warehouse_id'=>$warehouse,'location_id'=>$location,'image_urls'=>'["/static/fixture.png"]'],
    ]];
    $po=$purchase->create($input); check($purchase->create($input)===$po,'ERP purchase idempotent');
    drain($site); $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','purchase')->find();
    check((bool)$task,'real ERP transaction produced bridge task'); check($task['status']==='blocked','missing mappings blocked');
    mapTask($site,$task); (new TaskService())->action($site,(int)$task['id'],1,'retry'); drain($site);
    $task=Db::name('hsx_boyuan_task')->where('id',$task['id'])->find(); check($task['status']==='simulated','purchase asynchronous mock complete');
    check(Db::name('hsx_boyuan_binding')->where('kind','financial')->count()===2,'one payable per device bound');
    $asset=Db::name('erp_asset')->where('site_id',$site)->where('purchase_order_id',$po)->order('id')->find();
    $so=$sale->create(['party_id'=>$customer,'party_name'=>'测试客户','request_id'=>'BOYUAN_SALE_01','sale_channel_key'=>'erp_peer','settle_method'=>'挂账','items'=>[['asset_id'=>$asset['id'],'sale_price'=>2600]]]);
    drain($site); $saleTask=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','sale')->find();
    mapTask($site,$saleTask); (new TaskService())->action($site,(int)$saleTask['id'],1,'retry'); drain($site);
    $saleTask=Db::name('hsx_boyuan_task')->where('id',$saleTask['id'])->find(); check($saleTask['status']==='simulated','wholesale sale complete');
    check(Contract::decode($saleTask['request_json'])['operation_type']==='wholesale_sale','correct channel type');
    check(Db::name('hsx_boyuan_binding')->where('kind','financial')->count()===3,'one receivable per sale bound');
    $secondAsset=Db::name('erp_asset')->where('site_id',$site)->where('purchase_order_id',$po)->where('id','<>',$asset['id'])->find();
    $retailOrder=$sale->create(['party_id'=>$customer,'party_name'=>'测试客户','request_id'=>'BOYUAN_RETAIL_01','sale_channel_key'=>'erp_store','settle_method'=>'挂账','items'=>[['asset_id'=>$secondAsset['id'],'sale_price'=>2400]]]);
    drain($site); $retailTask=Db::name('hsx_boyuan_task')->where('kind','sale')->order('id desc')->find();
    check($retailTask['status']==='simulated','retail sale complete');
    check(Contract::decode($retailTask['request_json'])['operation_type']==='retail_sale','retail type preserved');
    $finance=ErpFinanceService::forSite($site,1,'测试财务');
    $payables=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->order('id')->select()->toArray();
    $payId=$finance->confirmPayableItemsInTransaction($supplier,array_map(fn($row)=>['payable_id'=>$row['id'],'amount'=>(float)$row['amount']],$payables),['capital_account_id'=>$account,'request_id'=>'BOYUAN_MERGED_PAY']);
    $finance->flushPendingSettlementDomainEvents(); drain($site);
    $payment=Db::name('hsx_boyuan_task')->where('kind','settlement_register')->order('id desc')->find();
    check((bool)$payment,'merged payment fact received'); mapTask($site,$payment); (new TaskService())->action($site,(int)$payment['id'],1,'retry'); drain($site);
    $payment=Db::name('hsx_boyuan_task')->where('id',$payment['id'])->find();
    check($payment['status']==='simulated','merged payment complete: '.$payment['reason']);
    $paymentRequest=Contract::decode($payment['request_json']); check(count($paymentRequest['data']['allocations'])===2 && $paymentRequest['data']['amount']==='3800.00','one real payment allocated to two independent payables');
    $receivable=Db::name('erp_receivable')->where('site_id',$site)->where('source_type','sale')->where('source_id',$so)->find();
    $retailReceivable=Db::name('erp_receivable')->where('site_id',$site)->where('source_type','sale')->where('source_id',$retailOrder)->find();
    $finance->confirmReceivableItemsInTransaction($customer,[['receivable_id'=>$receivable['id'],'amount'=>2600],['receivable_id'=>$retailReceivable['id'],'amount'=>2400]],['capital_account_id'=>$account,'request_id'=>'BOYUAN_RECEIPT']);
    $finance->flushPendingSettlementDomainEvents(); drain($site);
    $receipt=Db::name('hsx_boyuan_task')->where('kind','settlement_register')->order('id desc')->find();
    check($receipt['status']==='simulated','sales receipt complete: '.$receipt['reason']);
    check(count(Contract::decode($receipt['request_json'])['data']['allocations'])===2,'merged receipt allocated across two sales');
    $ret=(new ErpSaleReturnService())->create(['sale_order_id'=>$so,'request_id'=>'BOYUAN_RETURN_01','refund_mode'=>'payable','items'=>[['asset_id'=>$asset['id'],'return_price'=>2600,'reason'=>'测试退货']]]);
    (new ErpSaleReturnService())->confirm($ret); drain($site);
    $returnTask=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','sale_return')->find();
    check($returnTask && $returnTask['status']==='simulated','sale return references original line: '.($returnTask['reason']??'missing task'));
    $retailReturn=(new ErpSaleReturnService())->create(['sale_order_id'=>$retailOrder,'request_id'=>'BOYUAN_RETAIL_RET_01','refund_mode'=>'payable','items'=>[['asset_id'=>$secondAsset['id'],'return_price'=>2400,'reason'=>'测试零售退货']]]);
    (new ErpSaleReturnService())->confirm($retailReturn); drain($site);
    $retailReturnTask=Db::name('hsx_boyuan_task')->where('kind','sale_return')->order('id desc')->find();
    check($retailReturnTask['status']==='simulated' && Contract::decode($retailReturnTask['request_json'])['operation_type']==='retail_return','retail return uses original retail type');
    $pret=(new ErpPurchaseReturnService())->create(['purchase_order_id'=>$po,'request_id'=>'BOYUAN_PRETURN_01','refund_mode'=>'receivable','items'=>[['asset_id'=>$asset['id'],'return_cost'=>2000,'reason'=>'测试采购退货']]]);
    drain($site); $purchaseReturnTask=Db::name('hsx_boyuan_task')->where('kind','purchase_return')->find(); check(($purchaseReturnTask['status']??'')==='simulated','purchase return complete: '.($purchaseReturnTask['reason']??'missing'));
    $before=Db::name('erp_outbox_event')->count(); Db::startTrans(); $copy=$input; $copy['request_id']='BOYUAN_ROLLBACK'; $copy['items'][0]['imei']='BOYUAN_ROLLBACK_A'; $copy['items'][1]['imei']='BOYUAN_ROLLBACK_B'; $purchase->create($copy); Db::rollback();
    check(Db::name('erp_outbox_event')->count()===$before,'transaction rollback has no bridge fact');
    $rows=Db::name('hsx_boyuan_task')->select()->toArray(); $service=new TaskService();
    foreach($rows as $row) check(!$service->process($site,(int)$row['id']),'completed tasks not resent');
    $before=Db::name('hsx_boyuan_task')->count(); drain($site); check(Db::name('hsx_boyuan_task')->count()===$before,'event redelivery creates no duplicate tasks');
    $base=Contract::decode($task['snapshot_json']); $connection=$task['connection_key'];
    $make=function(string $scenario,string $suffix) use($base,$service,$site,$connection): int {
        $copy=$base; $copy['source_document_id']='purchase:scenario:'.$suffix; $copy['source_document_no']='SCENARIO_'.$suffix; $copy['_mock_scenario']=$scenario;
        foreach($copy['lines'] as $index=>&$line) { $line['source_line_id']='scenario_line:'.$suffix.':'.$index; $line['device_ref']='scenario_device:'.$suffix.':'.$index; $line['imei']='SCENARIO_SERIAL_'.$suffix.'_'.$index; }
        return $service->enqueue($site,'mock',$connection,$copy,'SCENARIO_'.$suffix);
    };
    $timeout=$make('timeout_after_commit','TIMEOUT'); drain($site);
    $timeoutTask=Db::name('hsx_boyuan_task')->where('id',$timeout)->find(); check($timeoutTask['status']==='simulated','lost response recovered by original operation query');
    check(Db::name('hsx_boyuan_log')->where('task_id',$timeout)->where('action','send')->count()===1,'timeout did not repeat POST');
    $partial=$make('partial','PARTIAL'); drain($site); check(Db::name('hsx_boyuan_task')->where('id',$partial)->value('status')==='partial','partial is not whole success');
    try {$service->action($site,$partial,1,'retry'); check(false,'partial retry rejected');} catch(Problem $e){check($e->reason==='QUERY_REQUIRED','partial cannot be blindly retried');}
    $service->action($site,$partial,1,'record_manual','已在演示账套人工核对，尚未获得完整回执','SAMPLE_MANUAL_NO');
    check(Db::name('hsx_boyuan_task')->where('id',$partial)->value('status')==='manual_pending','manual note not automatic success');
    $notFound=new class implements \addon\hsx_boyuan\app\gateway\Transport {
        public int $writes=0;
        public function send(string $method,string $path,array $body=[]): array { if($method==='POST') $this->writes++; return ['code'=>'OPERATION_NOT_FOUND','data'=>[]]; }
    };
    $service->action($site,$partial,1,'query'); $service->process($site,$partial,$notFound);
    check($notFound->writes===0 && Db::name('hsx_boyuan_task')->where('id',$partial)->value('status')==='confirming','manual query never posts when original operation is not found');
    try {$service->action($site+1,$partial,1,'record_manual','禁止跨站点修改隔离任务');check(false,'cross tenant action');}catch(Problem $e){check($e->reason==='TASK_MISSING','task action is tenant scoped');}
    $rejected=$make('rejected','REJECT'); drain($site); $oldOperation=Db::name('hsx_boyuan_task')->where('id',$rejected)->value('operation_id');
    $fresh=$configService->get($site); $configService->save($site,1,['revision'=>$fresh['revision'],'profile'=>['scenario'=>'success']]);
    $service->action($site,$rejected,1,'retry'); drain($site); $repaired=Db::name('hsx_boyuan_task')->where('id',$rejected)->find();
    check($repaired['status']==='simulated' && $repaired['operation_id']!==$oldOperation,'rejected no-effect request repaired with linked new operation');
    check(Contract::decode($repaired['request_json'])['supersedes_operation_id']===$oldOperation,'repair retains predecessor identity');
    $financialSnapshot=Contract::decode(Db::name('hsx_boyuan_task')->where('kind','financial_item_ensure')->value('snapshot_json'));
    $financialSnapshot['source_document_id']='finance:paused-check';
    $pausedFinance=$service->enqueue($site,'mock',$connection,$financialSnapshot,'FINANCE_PAUSE_CHECK');
    $fresh=$configService->get($site); $configService->save($site,1,['revision'=>$fresh['revision'],'finance_enabled'=>false]);
    Db::name('hsx_boyuan_task')->where('id',$pausedFinance)->update(['next_at'=>0]);
    check(!$service->process($site,$pausedFinance) && Db::name('hsx_boyuan_task')->where('id',$pausedFinance)->value('status')==='pending','disabled finance holds queued tasks without sending');
    $fresh=$configService->get($site);
    $testConfig=$configService->save($site,1,['revision'=>$fresh['revision'],'mode'=>'test','enabled'=>false,'paused'=>true,'profile'=>['protocol'=>'bridge_v1','endpoint'=>'https://api.vendor.example','account_id'=>'SAMPLE_TEST_ACCOUNT','token'=>'FAKE_SAMPLE_TOKEN','contract_confirmed'=>true]]);
    check(!str_contains(Contract::json($testConfig),'FAKE_SAMPLE_TOKEN'),'credentials not exposed');
    check($testConfig['profiles']['test']['connection_key']!==$connection,'environment connections isolated');
    check(!$service->process($site,$partial),'mock task cannot run in test connection');
    try {$configService->save($site,1,['revision'=>$testConfig['revision'],'enabled'=>true,'paused'=>false,'confirm_account'=>'SAMPLE_TEST_ACCOUNT']); check(false,'real mode gate');}catch(Problem $e){check($e->reason==='PREFLIGHT_REQUIRED','test writes require account probe');}
    check(Db::name('hsx_boyuan_mapping')->where('connection_key',$testConfig['profiles']['test']['connection_key'])->count()===0,'mock mappings not promoted to test');
    $probe=new class implements \addon\hsx_boyuan\app\gateway\Transport {
        public string $account='WRONG_ACCOUNT'; public int $writes=0;
        public function send(string $method,string $path,array $body=[]): array { if($method!=='GET') $this->writes++; return ['code'=>'OK','data'=>['account_id'=>$this->account,'items'=>[]]]; }
    };
    try {$configService->probe($site,1,$probe);check(false,'wrong account probe');}catch(Problem $e){check($e->reason==='ACCOUNT_UNVERIFIED','wrong authorized account cannot pass probe');}
    $probe->account='SAMPLE_TEST_ACCOUNT'; $verified=$configService->probe($site,1,$probe);
    check($verified['verified'] && $probe->writes===0,'account preflight only performs GET');
    $fresh=$configService->get($site);
    try {$configService->save($site,1,['revision'=>$fresh['revision'],'enabled'=>true,'paused'=>false,'confirm_account'=>'WRONG_ACCOUNT']);check(false,'typed account confirmation');}catch(Problem $e){check($e->reason==='ACCOUNT_CONFIRM_REQUIRED','real mode needs exact account confirmation');}
    $prod=$configService->save($site,1,['revision'=>$fresh['revision'],'mode'=>'production','enabled'=>false,'paused'=>true,'profile'=>['protocol'=>'bridge_v1','endpoint'=>'https://api.vendor.example','account_id'=>'SAMPLE_PRODUCTION_ACCOUNT','token'=>'FAKE_SAMPLE_TOKEN','contract_confirmed'=>true]]);
    $probe->account='SAMPLE_PRODUCTION_ACCOUNT'; $configService->probe($site,1,$probe);
    try {$configService->save($site,1,['revision'=>$prod['revision'],'enabled'=>true,'paused'=>false,'confirm_account'=>'SAMPLE_PRODUCTION_ACCOUNT']);check(false,'production acceptance gate');}catch(Problem $e){check($e->reason==='ACCEPTANCE_REQUIRED','production needs real test acceptance reference');}
    echo Contract::json(['passed'=>true,'checks'=>$checks,'database'=>'isolated random local schema','remote_calls'=>0,'production_modified'=>false])."\n";
} catch(Throwable $e) {
    $failed=true; fwrite(STDERR,'FAIL '.get_class($e).' '.($e instanceof Problem?$e->reason:'').': '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine()."\n");
} finally { if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`'); }
exit($failed?1:0);
