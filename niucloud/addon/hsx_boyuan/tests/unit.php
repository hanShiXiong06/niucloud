<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/vendor/autoload.php';
use addon\hsx_boyuan\app\support\{Contract,Problem,Secret};
use addon\hsx_boyuan\app\support\Plan;
use addon\hsx_boyuan\app\gateway\HttpTransport;
$checks=0;
function assertRule(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; }
function rejects(callable $callback,string $label): void { try {$callback();}catch(Problem $e){assertRule(true,$label);return;}throw new RuntimeException($label); }
foreach(['0.00','1.23','9999999999.99'] as $amount) assertRule(Contract::amount(Contract::cents($amount))===$amount,'decimal round trip');
foreach([1,1.23,'1.2','-1.00','1e3','01.00','10000000000.00','0.001',null] as $amount) rejects(fn()=>Contract::cents($amount),'invalid amount rejected');
foreach(['http://vendor.example','https://127.0.0.1','https://user:pass@vendor.example','https://vendor.example:8443','https://vendor.example/?token=x','https://vendor.example/../a','https://vendor.example/%2fadmin','https://localhost'] as $url) rejects(fn()=>HttpTransport::endpoint($url),'unsafe endpoint');
foreach(['127.0.0.1','10.0.0.1','169.254.169.254','100.64.0.1','192.168.1.1','192.0.2.1','198.18.0.1','203.0.113.1','::1','::ffff:8.8.8.8','64:ff9b::808:808','2002:0808:0808::1'] as $ip) assertRule(!HttpTransport::publicIp($ip),'nonpublic or tunnel address blocked');
assertRule(HttpTransport::publicIp('8.8.8.8'),'public address accepted');
$cipher=Secret::seal('FAKE_TEST_TOKEN','fixture-key',1,'connection-a');
assertRule(Secret::open($cipher,'fixture-key',1,'connection-a')==='FAKE_TEST_TOKEN','secret round trip');
rejects(fn()=>Secret::open($cipher,'fixture-key',2,'connection-a'),'tenant separated');
rejects(fn()=>Secret::open($cipher,'fixture-key',1,'connection-b'),'connection separated');
$request=['schema_version'=>'1.0','operation_id'=>'SAMPLE_OP','operation_type'=>'purchase_inbound','data'=>[
    'source_document_id'=>'purchase:sample','total_amount'=>'2000.00','lines'=>[['source_line_id'=>'line:1','device_ref'=>'asset:1','external_product_id'=>'P1','product_code'=>'P1','warehouse_id'=>'W1','unit_code'=>'TAI','quantity'=>1,'line_amount'=>'2000.00','imei'=>'TEST_SERIAL','sn'=>null]],
]];
Contract::request($request); assertRule(true,'valid inventory request');
$copy=$request; $copy['data']['lines'][]=$copy['data']['lines'][0]; rejects(fn()=>Contract::request($copy),'duplicate device rejected');
$copy=$request; $copy['data']['total_amount']='1999.00'; rejects(fn()=>Contract::request($copy),'total mismatch rejected');
$reply=['code'=>'OK','data'=>['operation_id'=>'SAMPLE_OP','operation_type'=>'purchase_inbound','operation_status'=>'effective','documents'=>[
    ['external_document_id'=>'E1','external_document_no'=>'NO1','document_type'=>'purchase_inbound','source_document_id'=>'purchase:sample','document_status'=>'effective','inventory_applied'=>true,'lines'=>[
        ['source_line_id'=>'line:1','device_ref'=>'asset:1','external_line_id'=>'L1','warehouse_id'=>'W1','quantity'=>1,'line_amount'=>'2000.00','serial'=>'TEST_SERIAL','inventory_effect'=>'in','inventory_applied'=>true],
    ]],
]]];
assertRule(Contract::response($reply,$request)['operation_status']==='effective','full inventory response accepted');
foreach(['source_line_id'=>'wrong','device_ref'=>'wrong','warehouse_id'=>'wrong','line_amount'=>'1999.00','serial'=>'wrong','inventory_effect'=>'out','quantity'=>2,'inventory_applied'=>false,'external_line_id'=>''] as $field=>$value) {
    $bad=$reply; $bad['data']['documents'][0]['lines'][0][$field]=$value; rejects(fn()=>Contract::response($bad,$request),'bad response '.$field);
}
$bad=$reply; $bad['data']['documents'][0]['lines']=[]; rejects(fn()=>Contract::response($bad,$request),'missing lines rejected');
$bad=$reply; $bad['data']['operation_id']='WRONG'; rejects(fn()=>Contract::response($bad,$request),'wrong operation rejected');
$bad=$reply; $bad['data']['documents'][0]['document_status']='voided'; rejects(fn()=>Contract::response($bad,$request),'current void status not success');
$bad=$reply; $bad['data']['documents'][0]['inventory_applied']=false; rejects(fn()=>Contract::response($bad,$request),'save without stock effect not success');
$bad=$reply; $bad['code']='FAIL'; rejects(fn()=>Contract::response($bad,$request),'conflicting business code rejected');
$bad=$reply; $bad['data']['documents'][0]['document_type']='retail_sale'; rejects(fn()=>Contract::response($bad,$request),'wrong document type rejected');
$two=$request; $line=$two['data']['lines'][0]; $line['source_line_id']='line:2'; $line['device_ref']='asset:2'; $two['data']['lines'][]=$line;
$bad=$reply; $line=$bad['data']['documents'][0]['lines'][0]; $line['source_line_id']='line:2'; $line['device_ref']='asset:2'; $bad['data']['documents'][0]['lines'][]=$line;
rejects(fn()=>Contract::response($bad,$two),'two devices cannot share an external document line');
foreach(['accepted','processing','unknown','partial','rejected'] as $state) { $bad=$reply; $bad['data']['operation_status']=$state; assertRule(Contract::response($bad,$request)['operation_status']===$state,'nonfinal status preserved'); }
$redacted=Contract::redact(['authorization'=>'FAKE','nested'=>['token'=>'FAKE','mobile'=>'13800000000']]);
assertRule(!str_contains(Contract::json($redacted),'FAKE') && !str_contains(Contract::json($redacted),'13800000000'),'nested secrets and phone redacted');
$dateRequest=Plan::build(['kind'=>'party_resolve','occurred_at'=>strtotime('2026-10-27T16:30:00Z'),'data'=>[]],[],[],'SAMPLE_DATE');
assertRule($dateRequest['business_date']==='2026-10-28' && str_ends_with($dateRequest['business_time'],'+08:00'),'business date respects agreed Shanghai timezone');
rejects(fn()=>Plan::build(['outside_scope'=>true],[],[],'SAMPLE_OLD'),'historical device never becomes new stock automatically');
$balance=['external_financial_item_id'=>'FIN1','current_amount'=>'2000.00','settled_amount'=>'500.00','balance_amount'=>'1500.00','version'=>2];
Contract::financialBalance($balance); assertRule(true,'balanced current account accepted');
foreach (['version'=>0,'balance_amount'=>'1499.00','current_amount'=>'0.00'] as $field=>$value) { $bad=$balance; $bad[$field]=$value; rejects(fn()=>Contract::financialBalance($bad),'bad balance '.$field); }
$settlementRequest=['operation_id'=>'PAY1','operation_type'=>'settlement_register','data'=>['source_settlement_id'=>'settlement:1','direction'=>'payment','amount'=>'500.00','allocations'=>[
    ['source_financial_item_id'=>'payable:1','external_financial_item_id'=>'FIN1','amount'=>'500.00','expected_balance_amount'=>'1500.00','expected_version'=>2],
]]];
$settlementReply=['code'=>'OK','data'=>['operation_id'=>'PAY1','operation_type'=>'settlement_register','operation_status'=>'effective','settlement'=>[
    'source_settlement_id'=>'settlement:1','external_settlement_id'=>'EPAY1','external_settlement_no'=>'PAY_NO1','direction'=>'payment','amount'=>'500.00','funds_recorded'=>true,'allocations_applied'=>true,
    'allocations'=>[['source_financial_item_id'=>'payable:1','external_financial_item_id'=>'FIN1','external_allocation_id'=>'ALLOC1','applied_amount'=>'500.00','settled_amount'=>'1000.00','balance_amount'=>'1000.00','version'=>3]],
]]];
assertRule(Contract::response($settlementReply,$settlementRequest)['operation_status']==='effective','full settlement accepted');
foreach (['source_financial_item_id'=>'wrong','external_financial_item_id'=>'wrong','external_allocation_id'=>'','applied_amount'=>'499.00','balance_amount'=>'1500.00','version'=>2] as $field=>$value) {
    $bad=$settlementReply; $bad['data']['settlement']['allocations'][0][$field]=$value; rejects(fn()=>Contract::response($bad,$settlementRequest),'bad allocation '.$field);
}
$bad=$settlementReply; $bad['data']['settlement']['direction']='receipt'; rejects(fn()=>Contract::response($bad,$settlementRequest),'wrong cash direction rejected');
$partyRequest=['operation_id'=>'PARTY1','operation_type'=>'party_resolve','data'=>['role'=>'supplier','mode'=>'match_only']];
$partyReply=['code'=>'OK','data'=>['operation_id'=>'PARTY1','operation_type'=>'party_resolve','operation_status'=>'effective','match_status'=>'matched','party'=>['id'=>'SP1','code'=>'SP_CODE1','role'=>'supplier','enabled'=>true]]];
assertRule(Contract::response($partyReply,$partyRequest)['operation_status']==='effective','authorized supplier match accepted');
$bad=$partyReply; $bad['data']['party']['role']='customer'; rejects(fn()=>Contract::response($bad,$partyRequest),'counterparty role cannot be silently replaced');
$bad=$partyReply; $bad['data']['match_status']='created'; rejects(fn()=>Contract::response($bad,$partyRequest),'match only must not create a supplier');
echo Contract::json(['passed'=>true,'checks'=>$checks,'external_calls'=>0])."\n";
