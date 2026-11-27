<?php
declare(strict_types=1);
// Pure origin, single-attempt and stored-evidence fixtures; neither a database nor an HTTP client is initialized.
if(PHP_SAPI!=='cli') exit;
foreach(['support/Problem','support/Contract','gateway/ReceiptGateway','service/SaleReceiptSource','service/SaleReceiptSyncService',
    'service/OriginalSaleCashReceipts','service/PaymentSyncService','service/PurchaseInventoryService','service/WholesaleSyncService','service/BatchService'] as $f)require __DIR__.'/../app/'.$f.'.php';
use addon\hsx_boyuan\app\service\{BatchService,OriginalSaleCashReceipts,SaleReceiptSource,SaleReceiptSyncService};
use addon\hsx_boyuan\app\gateway\ReceiptGateway;
use addon\hsx_boyuan\app\support\{Problem,Contract};

$count=0;
function okOrigin(bool $ok,string $msg):void{global $count;if(!$ok)throw new RuntimeException($msg);$count++;}
$event=['event_name'=>'erp.settlement.completed.v1','aggregate_type'=>'settlement','aggregate_id'=>7,'payload'=>[
    'settlement_id'=>7,'settlement_type'=>'receipt','cash_direction'=>'in','amount'=>2,
    'targets'=>[['target_type'=>'receivable','target_id'=>8,'source_type'=>'sale','source_id'=>3,'source_document_ref'=>'sale:3','applied_amount'=>'2.00']]]];
okOrigin(SaleReceiptSource::refs($event)===[7,3,8],'confirmed receipt refs');
foreach([['aggregate_id'=>8],['payload'=>['settlement_type'=>'payment']],['payload'=>['cash_direction'=>'out']],
    ['payload'=>['targets'=>[1=>$event['payload']['targets'][0]]]],['payload'=>['targets'=>[['source_type'=>'purchase_asset']]]],
    ['payload'=>['targets'=>[['source_document_ref'=>'sale:4']]]],['payload'=>['targets'=>[['applied_amount'=>1]]]],
    ['payload'=>['amount'=>0]],['payload'=>['amount'=>1.001]],['payload'=>['targets'=>[['target_type'=>'payable']]]]] as $delta){
    try{SaleReceiptSource::refs(array_replace_recursive($event,$delta));okOrigin(false,'invalid receipt origin accepted');}
    catch(Problem $e){okOrigin(true,'invalid fact blocked');}
}
$draft=['kind'=>SaleReceiptSyncService::KIND,'mode'=>'test','status'=>'awaiting_confirmation','side_effect'=>'none','attempts'=>0];
okOrigin(SaleReceiptSyncService::draft($draft),'manual fresh draft');
foreach([['mode'=>'production'],['status'=>'attention'],['status'=>'registered_review'],['status'=>'processing'],['attempts'=>1],['side_effect'=>'unknown'],['resolution'=>'changed']] as $d)
    okOrigin(!SaleReceiptSyncService::draft(array_replace($draft,$d)),'uneditable once not pristine');
okOrigin(BatchService::actionFor($draft)==='','generic batch must never send or recover receipt');
$s=['customer_id'=>'cust','company_id'=>'co','settlement_id'=>7,'amount'=>'2.00'];
$req=$s+['schema'=>ReceiptGateway::SCHEMA,'stage'=>'frozen','source_settlement_id'=>7,'account_id'=>'bank','account_code'=>'UAT','connection_key'=>'key'];
$r=['external_no'=>'R01','external_id'=>'R1','created'=>true,'receipt_registered'=>true,'verification_level'=>'receipt_create_receipt_matched','amount'=>'2.00',
    'financial_posting_verified'=>false,'allocation_applied_verified'=>false,'response'=>['head'=>['bill_type'=>'RECEIPT','bill_id'=>'R01','id'=>'R1','company_id'=>'co','supplier_cust_id'=>'cust','big_gsdm'=>'UAT'],
    'receipt_details'=>['add_details'=>[['account_id'=>'bank']]]]];
$row=['kind'=>SaleReceiptSyncService::KIND,'mode'=>'test','status'=>'registered_review','side_effect'=>'applied','external_no'=>'R01','connection_key'=>'key','result_json'=>Contract::json($r),'snapshot_json'=>Contract::json($s),'request_json'=>Contract::json($req)];
okOrigin(SaleReceiptSyncService::number($row)==='R01','documented registered receipt only');
foreach([['connection_key'=>'wrong'],['side_effect'=>'unknown'],['status'=>'attention'],['mode'=>'production'],['external_no'=>'candidate'],
    ['result_json'=>Contract::json(array_replace($r,['financial_posting_verified'=>true]))],['result_json'=>Contract::json(array_replace($r,['allocation_applied_verified'=>true]))],
    ['result_json'=>Contract::json(array_replace_recursive($r,['response'=>['head'=>['bill_type'=>'PAYMENT']]]))],
    ['result_json'=>Contract::json(array_replace_recursive($r,['response'=>['head'=>['big_gsdm'=>'different-uat']]]))],
    ['request_json'=>Contract::json(array_replace($req,['account_id'=>'other-fund']))],['request_json'=>Contract::json(array_replace($req,['amount'=>'1.00']))],
    ['snapshot_json'=>Contract::json(array_replace($s,['settlement_id'=>4]))]] as $d)
    okOrigin(SaleReceiptSyncService::number(array_replace($row,$d))==='','mismatched proof does not produce a receipt number');
okOrigin(OriginalSaleCashReceipts::label([['status'=>'registered'],['status'=>'not_dispatched']])==='部分已登记','partial receipts are not all registered');
okOrigin(OriginalSaleCashReceipts::label([['status'=>'registered'],['status'=>'needs_review']])==='待核对','unverified is not never sent');
echo "OK: $count sale receipt source and projection checks\n";
