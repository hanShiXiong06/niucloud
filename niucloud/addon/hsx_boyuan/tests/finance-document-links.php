<?php
declare(strict_types=1);
// Read projections and preservation of ledger state; disposable localhost schema, no remote transport.
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';

use addon\hsx_boyuan\app\gateway\WholesaleGateway;
use addon\hsx_boyuan\app\listener\FinanceDocumentLinks;
use addon\hsx_boyuan\app\service\{FinanceResultService,PaymentSyncService,PurchaseSyncService,WholesaleSyncService};
use addon\hsx_boyuan\app\support\{Contract,Problem,ReceiptFacts};
use addon\hsx_erp\app\service\admin\ErpPurchaseExtensionService;
use think\facade\Db;

$pdo=null;$schema='';$checks=0;$failed=false;
function assertFinance(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); ++$checks; }
try {
    [$app,$pdo,$schema,$site]=boyuanBoot();
    $connection='local-finance-only'; $c=['mode'=>'test','enabled'=>true,'purchase_enabled'=>false,'paused'=>true,'profiles'=>['test'=>['connection_key'=>$connection]]];
    $row=static function(string $kind,string $fact,string $number,array $source,string $schemaName,string $state,string $no,array $result) use($site,$connection): int {
        return (int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'mode'=>'test','connection_key'=>$connection,'kind'=>$kind,'fact_key'=>$fact,
            'event_id'=>'event:'.$fact,'operation_id'=>'op:'.$fact,'business_no'=>$number,'status'=>$state,'external_no'=>$no,'snapshot_json'=>Contract::json($source),
            'request_json'=>Contract::json(['schema'=>$schemaName]),'result_json'=>Contract::json($result)]);
    };
    $source=['kind'=>'purchase','source_document_id'=>'purchase:10','source_document_no'=>'PO1','batch_ref'=>'A','lines'=>[['source_line_id'=>'purchase_line:21','device_ref'=>'asset:301']]];
    $purchase=['verified'=>true,'external_id'=>'head-a','external_no'=>'PUR-A','verification_level'=>'purchase_imei_records_matched',
        'provider_result'=>ReceiptFacts::head(['id'=>'head-a','bill_id'=>'PUR-A','cash_term'=>0,'un_settle_amounts'=>'0.00','audit_status'=>0,'private_key'=>'never-expose'])];
    $parent=$row('purchase','purchase:10:A','PO1',$source,PurchaseSyncService::SCHEMA,'succeeded','PUR-A',$purchase);
    $second=array_replace($source,['batch_ref'=>'B','lines'=>[['source_line_id'=>'purchase_line:22','device_ref'=>'asset:302']]]);
    $row('purchase','purchase:10:B','PO1',$second,PurchaseSyncService::SCHEMA,'awaiting_confirmation','',[]);
    $paymentSource=['source_task_id'=>$parent,'source_snapshot_hash'=>hash('sha256',Contract::json($source)),'source_document_id'=>'purchase:10',
        'source_document_no'=>'PO1','external_purchase_no'=>'PUR-A','settlement_id'=>501,'settlement_no'=>'SET-1','lines'=>[['payable_id'=>401,'source_line_id'=>'purchase_line:21','device_ref'=>'asset:301']]];
    $paid=['created'=>true,'payment_registered'=>true,'verified'=>false,'allocation_applied_verified'=>false,'external_no'=>'PAY-A','external_id'=>'pay-a','verification_level'=>'payment_create_receipt_matched',
        'provider_result'=>['id'=>'pay-a','bill_id'=>'PAY-A','business_date'=>'2026-11-19']];
    $payId=$row(PaymentSyncService::KIND,'purchase-payment:settlement:501','SET-1',$paymentSource,PaymentSyncService::SCHEMA,PaymentSyncService::REGISTERED,'PAY-A',$paid);
    $facts=[401=>['document_id'=>10,'number'=>'PO1','asset_id'=>301,'line_id'=>21,'settlements'=>[501]],402=>['document_id'=>10,'number'=>'PO1','asset_id'=>302,'line_id'=>22,'settlements'=>[501]]];
    $service=new FinanceResultService();
    $ap=$service->links($site,'payable',$facts,$c);
    $gate=$c;$gate['profiles']['test']['probe_at']=time();$gate['enabled']=false;
    assertFinance(\addon\hsx_boyuan\app\service\ExchangeCenterService::available($site,$gate),'old current-account records stay readable when delivery disabled');
    assertFinance(\addon\hsx_boyuan\app\listener\CostMaintenanceHint::message($gate)==='','standalone or disabled ERP does not get cross-system cost prompts');
    $gate['profiles']['test']['connection_key']='never-used-account';
    assertFinance(!\addon\hsx_boyuan\app\service\ExchangeCenterService::available($site,$gate),'a probe or another account history alone cannot expose the center');
    $gate['enabled']=true;
    assertFinance(str_contains(\addon\hsx_boyuan\app\listener\CostMaintenanceHint::message($gate),'不会同步到博远'),'enabled but paused connection still warns before local cost adjustment');
    $mixed=$facts[401];$mixed['settlements'][]=502;
    $mi=$service->links($site,'payable',[401=>$mixed],$c)[401];
    assertFinance(array_column($mi,'external_no')===['PUR-A','PAY-A',''] && $mi[2]['status']==='not_dispatched' && str_contains($mi[2]['next_step'],'不要再向客户打款'),'partial registrations do not hide a second local settlement');
    $mixed['settlements']=[];
    $noCash=$service->links($site,'payable',[401=>$mixed],$c)[401];
    assertFinance($noCash[1]['status_label']==='未关联本站付款流水','purchase receipt is never proof of local payment');
    assertFinance(array_column($ap[401],'external_no')===['PUR-A','PAY-A'] && $ap[401][1]['status']==='registered','paused integration still projects purchase and matching registered payment');
    assertFinance(array_column($ap[402],'external_no')===['',''] && $ap[402][1]['status']==='needs_review','sibling cannot borrow the receipt even when given same settlement id');
    assertFinance(str_contains($ap[401][1]['pc_next_step'],'PUR-A') && str_contains($ap[401][1]['pc_next_step'],'核销：待核对') && !isset($ap[402][1]['pc_next_step']),'PC finance shows only the matching original purchase and never infers its allocation');
    assertFinance(in_array('0.00',array_column($ap[401][0]['receipt_details'],'value'),true) && !str_contains(Contract::json($ap),'never-expose'),'zero retained with no private values');
    $snap=Db::name('hsx_boyuan_task')->where('id',$parent)->value('result_json');
    Db::name('hsx_boyuan_task')->where('id',$payId)->update(['snapshot_json'=>Contract::json(array_replace($paymentSource,['source_snapshot_hash'=>'different']))]);
    assertFinance($service->links($site,'payable',$facts,$c)[401][1]['status']==='needs_review','mismatched source revision is not a linked payment');
    $inactive=array_replace($c,['enabled'=>false,'profiles'=>['test'=>['connection_key'=>'other-account']]]);
    assertFinance($service->links($site,'payable',$facts,$inactive)===[] && $service->links($site+1,'payable',$facts,array_replace($c,['enabled'=>false]))===[],'account and site isolation; no UI for unconnected site');
    assertFinance((new FinanceDocumentLinks())->handle(['site_id'=>$site+1,'direction'=>'receivable','facts'=>$facts])===[],'cross-site event rejected before lookup');
    assertFinance((new FinanceDocumentLinks())->handle(['site_id'=>$site,'direction'=>'receivable','facts'=>$facts])===[],'installed but never connected site has no finance extension');
    $sale=['kind'=>'sale','source_document_no'=>'SO1','source_document_id'=>'sale:31'];
    $saleId=$row(WholesaleSyncService::KIND,'sale:31:wholesale-credit','SO1',$sale,WholesaleGateway::SCHEMA,'succeeded','SAL-A',
        ['receipt'=>['external_no'=>'SAL-A','created'=>true,'external_id'=>'sale-a','provider_result'=>['id'=>'sale-a','bill_id'=>'SAL-A','business_date'=>'2026-11-19']],
         'verification'=>['verified'=>true,'external_no'=>'SAL-A','verification_level'=>'wholesale_records_matched']]);
    $ar=[701=>['document_id'=>31,'number'=>'SO1']]; $a=$service->links($site,'receivable',$ar,$c)[701];
    assertFinance($a[0]['external_no']==='SAL-A' && $a[0]['status']==='succeeded' && $a[1]['status']==='not_recorded' && $a[1]['external_no']==='','verified sale is not a confirmed receipt of funds');
    $unknown=ReceiptFacts::issue(new Problem('OPENAPI_BUSY','never-store-an-exception-body'),'verify');
    Db::name('hsx_boyuan_task')->where('id',$saleId)->update(['status'=>'attention','result_json'=>Contract::json(['last_issue'=>$unknown])]);
    $a=$service->links($site,'receivable',$ar,$c)[701];
    assertFinance($a[0]['external_no']==='' && $a[0]['issue_code']==='OPENAPI_BUSY' && !str_contains(Contract::json($a),'never-store'),'unknown result cannot report a candidate number or expose transport contents');
    $app->event->listen('HsxErpFinanceDocumentLinks',static fn($event)=>$service->links($site,$event['direction'],$event['facts'],$c));
    $items=[['id'=>701]]; ErpPurchaseExtensionService::attachFinance($site,'receivable',$items,$ar);
    assertFinance($items[0]['integration_results'][0]['status']==='attention' && $items[0]['integration_results'][0]['issue_code']==='OPENAPI_BUSY','frontend receives stable result state and code, not just display text');
    assertFinance(Db::name('hsx_boyuan_task')->where('id',$parent)->value('result_json')===$snap && Db::name('erp_settlement')->count()===0,'finance reads never edit receipts or register money');
    $view=new \addon\hsx_boyuan\app\service\OriginalPurchaseReceipts();
    // Original source, a subsequent cash payment and its actual allocation. A fresh connection has no Boyuan tasks.
    Db::name('erp_purchase_order')->insert(['id'=>10,'site_id'=>$site,'purchase_no'=>'PO1','party_id'=>50]);
    Db::name('erp_purchase_item')->insert(['id'=>21,'site_id'=>$site,'purchase_order_id'=>10,'asset_id'=>301,'imei'=>'EXAMPLE01','item_type'=>'device']);
    Db::name('erp_asset')->insert(['id'=>301,'site_id'=>$site,'purchase_order_id'=>10,'purchase_item_id'=>21,'asset_no'=>'AS1','imei'=>'EXAMPLE01','party_id'=>50]);
    Db::name('erp_payable')->insert(['id'=>401,'site_id'=>$site,'payable_no'=>'AP1','source_type'=>'purchase_asset','source_id'=>301,'asset_id'=>0,'party_id'=>50,'amount'=>'1.00','settled_amount'=>'1.00','status'=>'settled']);
    Db::name('erp_settlement')->insert(['id'=>501,'site_id'=>$site,'settlement_no'=>'SET-1','party_id'=>50,'cash_direction'=>'out','settlement_type'=>'payment','status'=>'confirmed','amount'=>'1.00','confirmed_at'=>time()]);
    Db::name('erp_settlement_link')->insert(['site_id'=>$site,'settlement_id'=>501,'target_type'=>'payable','target_id'=>401,'applied_amount'=>'1.00']);
    $pc=['mode'=>'production','enabled'=>false,'paused'=>true,'purchase_enabled'=>false,'profiles'=>['production'=>['connection_key'=>'unused-production','protocol'=>'openapi_v153','probe_at'=>time()]]];
    $beforeCount=Db::name('hsx_boyuan_task')->count();
    $preview=$view->paymentRows($site,10,'PO1',$pc);
    assertFinance($preview[1]['status_label']==='待核对' && $preview[1]['external_no']==='','zero locally or no vendor evidence must explicitly remain pending reconciliation');
    assertFinance(array_column($preview,'status')===['not_dispatched','unverified'] && str_contains($preview[0]['next_step'],'SET-1') && str_contains($preview[0]['next_step'],'1.00'),'paused production with real local payment: show original and no invented remote receipt or writeoff');
    assertFinance($view->paymentRows($site+1,10,'PO1',$pc)===[] && $view->paymentRows($site,10,'wrong-number',$pc)===[],'wrong site or original number cannot display another payment');
    $pc['profiles']['production']['probe_at']=0; assertFinance($view->paymentRows($site,10,'PO1',$pc)===[],'unconnected ERP has no external panel');
    $online=$c;$online['profiles']['test']+=['protocol'=>'openapi_v153','probe_at'=>time()];
    $paymentSource['amount']='1.00';$paymentSource['lines'][0]['amount']='1.00';
    Db::name('hsx_boyuan_task')->where('id',$payId)->update(['snapshot_json'=>Contract::json($paymentSource)]);
    $seen=$view->paymentRows($site,10,'PO1',$online);
    assertFinance(array_column($seen,'external_no')===['PAY-A',''] && $seen[1]['status']==='unverified','verified payment registration is not a confirmed writeoff');
    foreach ([null,true,'absent'] as $flag) {
        $legacy=$paid;
        if($flag==='absent') unset($legacy['allocation_applied_verified']); else $legacy['allocation_applied_verified']=$flag;
        Db::name('hsx_boyuan_task')->where('id',$payId)->update(['result_json'=>Contract::json($legacy)]);
        $result=$service->links($site,'payable',$facts,$online)[401][1];
        assertFinance($result['external_no']==='PAY-A' && str_contains($result['pc_next_step'],'核销：待核对'),'creation receipt alone cannot confirm writeoff, including an unknown flag');
    }
    Db::name('sys_config')->insert(['site_id'=>$site,'config_key'=>\addon\hsx_boyuan\app\service\ConfigService::KEY,'value'=>Contract::json($online)]);
    $devices=static fn($key)=>\addon\hsx_boyuan\app\service\ExchangeCenterService::read($site,['view'=>'documents','page'=>1,'keyword'=>$key]);
    foreach(['EXAMPLE01','PO1','SET-1','PUR-A','PAY-A'] as $key)
        assertFinance(array_column($devices($key)['rows'],'id')===[301],'business center matches serial, original or evidence-backed external number');
    assertFinance($devices('NO-SUCH-RECEIPT')['total']===0,'missing external bill is not simulated');
    $book=static fn($view,$key='',$id=0)=>\addon\hsx_boyuan\app\service\ExchangeCenterService::read($site,['view'=>$view,'page'=>1,'keyword'=>$key,'id'=>$id]);
    assertFinance($book('purchases')['total']===1 && $book('payments')['total']===1,'first visit lists original purchase and voucher without filters');
    foreach([['purchases','PUR-A',10],['payments','PAY-A',501],['purchases','EXAMPLE01',10],['payments','EXAMPLE01',501],['payments','PO1',501]] as [$v,$k,$n])
        assertFinance(array_map('intval',array_column($book($v,$k)['rows'],'id'))===[$n],'trusted number and serial search on '.$v.' '.$k);
    Db::name('erp_purchase_order')->insert(['id'=>11,'site_id'=>$site,'purchase_no'=>'PO2','party_id'=>50,'status'=>'completed']);
    Db::name('erp_purchase_item')->insert(['id'=>22,'site_id'=>$site,'purchase_order_id'=>11,'asset_id'=>302,'imei'=>'EXAMPLE02','item_type'=>'device']);
    Db::name('erp_asset')->insert(['id'=>302,'site_id'=>$site,'purchase_order_id'=>11,'purchase_item_id'=>22,'asset_no'=>'AS2','imei'=>'EXAMPLE02','party_id'=>50]);
    Db::name('erp_payable')->insert(['id'=>402,'site_id'=>$site,'payable_no'=>'AP2','source_type'=>'purchase_asset','source_id'=>302,'asset_id'=>302,'party_id'=>50,'amount'=>'1.00','settled_amount'=>'0.40','status'=>'partial']);
    $addVoucher=static function($id,$s,$no,$sum) {Db::name('erp_settlement')->insert(['id'=>$id,'site_id'=>$s,'settlement_no'=>$no,'party_id'=>50,'cash_direction'=>'out','settlement_type'=>'payment','status'=>'confirmed','amount'=>$sum,'confirmed_at'=>time()]);};
    $addVoucher(503,$site,'FIX-PART','1.40');$addVoucher(504,$site,'FIX-EMPTY','0.20');$addVoucher(505,$site+1,'OTHER-SITE','1.00');
    foreach([[401,'1.00'],[402,'0.40']] as [$apId,$sum])Db::name('erp_settlement_link')->insert(['site_id'=>$site,'settlement_id'=>503,'target_type'=>'payable','target_id'=>$apId,'applied_amount'=>$sum]);
    $shared=$book('payment_detail','',503);
    assertFinance($book('payments')['total']===3 && $shared['total']===2 && (int)$shared['device_count']===2 && $shared['amount']==='1.40','cross-purchase voucher counted once in list, not per allocation');
    foreach($shared['rows'] as $r)assertFinance(array_map('intval',array_column($r['settlements'],'id'))===[503] && !$r['payments'][0]['external_no'],'voucher shows only its own allocation and no other voucher receipt');
    assertFinance(!$book('payment_detail','',504)['rows'] && !$book('payments','OTHER-SITE')['rows'],'unallocated local still visible and foreign site excluded');
    try{$book('payment_detail','',505);throw new RuntimeException('foreign detail allowed');}catch(Problem $err){assertFinance($err->reason==='JOURNAL_ORIGINAL_MISSING','original id cannot cross tenant');}
    $events=[];
    $app->event->listen('HsxRecycleBusinessOperationRecorded',static function($e)use(&$events){$events[]=$e;});
    $audit=\addon\hsx_boyuan\app\support\BusinessOperation::class;
    $audit::audit($site,$parent,$connection,'purchase_submit','pending',77,['request'=>['secret'=>'SHOULD-NOT-LEAK']]);
    $audit::audit($site,$parent,$connection,'purchase_submit','pending',77,[]);
    $audit::audit($site,$parent,$connection,'purchase_open','succeeded',88,[]);
    $wire=['call_id'=>'adadadadadadadad','task_id'=>$parent,'stage'=>'request','sent_ms'=>1780000000000,'payload'=>['secret'=>'SHOULD-NOT-LEAK']];
    $audit::wire($site,$connection,$wire);
    $audit::wire($site,$connection,array_replace($wire,['stage'=>'result','finished_ms'=>1780000000222,'delivery'=>'no_confirmed_response']));
    $audit::wire($site+1,$connection,$wire);$book('purchases');$book('payment_detail','',501);
    assertFinance(count($events)===4 && $events[0]['operator_id']===77 && $events[0]['business_no']==='PO1' && $events[0]['event_id']===$events[1]['event_id'],'audited transitions keyed to persisted task; viewing the originals does not generate audit events');
    assertFinance($events[2]['occurred_at']===1780000000 && $events[3]['result']==='review' && $events[2]['event_id']!==$events[3]['event_id'] && !str_contains(json_encode($events),'SHOULD-NOT-LEAK'),'wire summaries distinct, safe, bounded and never imply timeout succeeded');
    $paymentSource['lines'][0]['amount']='2.00';
    Db::name('hsx_boyuan_task')->where('id',$payId)->update(['snapshot_json'=>Contract::json($paymentSource)]);
    $mismatch=$view->paymentRows($site,10,'PO1',$online);
    assertFinance($mismatch[0]['status']==='needs_review' && $mismatch[0]['external_no']==='','changed allocation must not inherit an old bill number');
    assertFinance(!$devices('PAY-A')['rows'],'stale payment allocation cannot match a device by external number');
    Db::name('erp_settlement')->whereIn('id',[501,503])->update(['status'=>'void']);
    assertFinance($view->paymentRows($site,10,'PO1',$online)===[] && Db::name('hsx_boyuan_task')->count()===$beforeCount,'void cash fact is excluded; opening and refreshing never creates tasks');
    // ERP deliberately passes settlements=[] for its AR detail. Find the receipt by the real voucher allocation.
    Db::name('erp_sale_order')->insert(['site_id'=>$site,'id'=>31,'sale_no'=>'SO1','party_id'=>75,'status'=>'completed']);
    Db::name('erp_receivable')->insert(['site_id'=>$site,'id'=>701,'receivable_no'=>'AR-701','source_type'=>'sale','source_id'=>31,'source_no'=>'SO1','party_id'=>75,'amount'=>'2.00','settled_amount'=>'2.00','status'=>'settled']);
    Db::name('erp_settlement')->insert(['site_id'=>$site,'id'=>720,'settlement_no'=>'ST-720','settlement_type'=>'receipt','cash_direction'=>'in','party_id'=>75,'amount'=>'2.00','status'=>'confirmed','confirmed_at'=>time()]);
    Db::name('erp_settlement_link')->insert(['site_id'=>$site,'settlement_id'=>720,'target_type'=>'receivable','target_id'=>701,'applied_amount'=>'2.00']);
    $cashView=\addon\hsx_boyuan\app\service\OriginalSaleCashReceipts::class;
    $cash=$cashView::rows($site,31,'SO1',$c,true);
    assertFinance(count($cash)===1 && $cash[0]['query']['original_source_id']===720 && $cash[0]['task_id']===0,'only one prepare action on the existing local voucher');
    $rSchema=\addon\hsx_boyuan\app\gateway\ReceiptGateway::SCHEMA;
    $src=['sale_id'=>31,'sale_no'=>'SO1','settlement_id'=>720,'settlement_no'=>'ST-720','receivable_id'=>701,'amount'=>'2.00','customer_id'=>'cust','company_id'=>'co'];
    $proof=['created'=>true,'receipt_registered'=>true,'verification_level'=>'receipt_create_receipt_matched','external_no'=>'RC-A','external_id'=>'r-A','amount'=>'2.00',
        'financial_posting_verified'=>false,'allocation_applied_verified'=>false,'response'=>['head'=>['bill_type'=>'RECEIPT','id'=>'r-A','bill_id'=>'RC-A','big_gsdm'=>'UAT','company_id'=>'co','supplier_cust_id'=>'cust'],'receipt_details'=>['add_details'=>[['account_id'=>'cash']]]]];
    $rid=$row('sale_receipt','sale-receipt:settlement:720','ST-720',$src,$rSchema,'registered_review','RC-A',$proof);
    Db::name('hsx_boyuan_task')->where('id',$rid)->update(['side_effect'=>'applied','attempts'=>1,'request_json'=>Contract::json(['schema'=>$rSchema,'stage'=>'frozen','connection_key'=>$connection,'account_code'=>'UAT','source_settlement_id'=>720,'customer_id'=>'cust','company_id'=>'co','account_id'=>'cash','amount'=>'2.00'])]);
    $rows=$service->links($site,'receivable',[701=>['number'=>'SO1','document_id'=>31,'settlements'=>[]]],$c)[701];
    assertFinance($rows[1]['status']==='registered' && $rows[1]['external_no']==='RC-A' && str_contains($rows[1]['next_step'],'核销待核对') && !isset($rows[1]['query']['original_source_id']),'paused, no supplied IDs: show original receipt, not a second registration or an allocation');
    assertFinance(!$cashView::rows($site+1,31,'SO1',$c,true) && !$cashView::rows($site,31,'OTHER',$c,true),'wrong site or original number cannot inherit receipt');
    $duplicate=false;try{$row('sale_receipt','sale-receipt:settlement:720','ST-720',$src,$rSchema,'attention','',[]);}catch(\think\db\exception\PDOException $e){$duplicate=true;}
    assertFinance($duplicate && Db::name('hsx_boyuan_task')->where('id',$rid)->value('external_no')==='RC-A','duplicate origin+connection fenced by existing schema; cannot replace original result');
    echo 'PASS '.$checks." finance linkage checks; isolated local database only.\n";
} catch(Throwable $e) { $failed=true; fwrite(STDERR,'FAIL '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine()."\n"); }
finally {if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
