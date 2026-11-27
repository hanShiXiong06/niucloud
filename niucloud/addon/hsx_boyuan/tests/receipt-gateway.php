<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
// Isolated documented vendor fixtures. No DB, network client, configuration file or user data is loaded.
foreach (['support/Problem','support/Contract','support/ReceiptFacts','gateway/Transport','gateway/OpenApi','gateway/ReceiptGateway'] as $file) {
    require __DIR__.'/../app/'.$file.'.php';
}
use addon\hsx_boyuan\app\gateway\{OpenApi,ReceiptGateway};
use addon\hsx_boyuan\app\support\{Contract,Problem};

$checks=0;
function checkReceipt(bool $ok,string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
function receiptReject(callable $fn,string $effect,string $label): void {
    try { $fn(); } catch (Problem $e) {
        checkReceipt($e->effect===$effect,$label.' has unexpected effect '.$e->effect);
        return;
    }
    throw new RuntimeException('unexpected success: '.$label);
}
$request=['schema'=>ReceiptGateway::SCHEMA,'mode'=>'test','company_id'=>'COMPANY','customer_id'=>'CUSTOMER',
    'account_id'=>'FUND','amount'=>'2.00','biz'=>[
        'head'=>['company_id'=>'COMPANY','supplier_cust_id'=>'CUSTOMER','business_date'=>'2026-11-21','scope_flag'=>3,
            'is_auto_generate_cust'=>0,'is_auto_generate_subject'=>0,'creater_id'=>'FINANCE','creater_name'=>'财务测试'],
        'receipt_details'=>['add_details'=>[['account_id'=>'FUND','amounts'=>'2.00','handling_fee'=>'0.00','seq_no'=>1]]],
    ]];
$result=['head'=>['id'=>'RECEIPT-ID','bill_id'=>'TEST-RECEIPT','bill_type'=>'RECEIPT','big_gsdm'=>'UAT-FIXTURE',
    'company_id'=>'COMPANY','supplier_cust_id'=>'CUSTOMER','business_date'=>'2026-11-21','scope_flag'=>'3',
    'creater_id'=>'FINANCE','cust_account_no'=>'PRIVATE-FIXTURE'],
    'receipt_details'=>['add_details'=>[['id'=>'LINE','account_id'=>'FUND','amounts'=>2,'handling_fee'=>0,'seq_no'=>1]]]];
$profile=['endpoint'=>'https://open-api-uat.139erp.com/api','gateway_path'=>'/gateway.do','account_id'=>'UAT-FIXTURE','app_id'=>'fixture-app'];
$wire=[];$reply=$result;
$exchange=static function(string $path,array $form) use (&$wire,&$reply):array {
    $wire[]=[$path,$form];
    if ($reply===null) throw new RuntimeException('fake timeout');
    return ['return_code'=>'SUCCESS','api_response'=>['result'=>$reply]];
};
$api=new OpenApi($profile,'test-only-secret',$exchange,'test');
$gateway=new ReceiptGateway($api);
$ok=$gateway->create($request);
checkReceipt(count($wire)===1 && $wire[0][0]==='/gateway.do' && $wire[0][1]['service']==='boyuan.biz.bill.receipt.generate','only the receipt service was called');
checkReceipt($wire[0][1]['sign']===OpenApi::sign($wire[0][1],'test-only-secret') && Contract::decode($wire[0][1]['biz'])===$request['biz'],'exact signed biz without outer task fields');
checkReceipt($ok['receipt_registered'] && $ok['external_no']==='TEST-RECEIPT' && $ok['external_id']==='RECEIPT-ID' && $ok['amount']==='2.00','actual matched number and amount');
checkReceipt(!$ok['verified'] && !$ok['allocation_applied_verified'] && !$ok['financial_posting_verified'] && !isset($ok['payment_registered']),'a receipt is never a sale write-off or payment');
checkReceipt(strpos(Contract::json($ok),'PRIVATE-FIXTURE')===false,'exclude private bank details from stored evidence');

$before=count($wire);
foreach ([
    fn()=>(new ReceiptGateway(new OpenApi($profile,'x',$exchange,'production')))->create($request),
    fn()=>(new OpenApi(array_replace($profile,['endpoint'=>'https://open-api.139erp.com/api']),'x',$exchange,'test'))->receiptCreate($request),
    fn()=>(new OpenApi(array_replace($profile,['gateway_path'=>'/other']),'x',$exchange,'test'))->receiptCreate($request),
    fn()=>$api->call(ReceiptGateway::SERVICE,[]),
    fn()=>$api->receiptCreate(array_replace($request,['mode'=>'production'])),
    fn()=>$gateway->create(array_replace($request,['schema'=>'boyuan.purchase.payment.v1'])),
    fn()=>$gateway->create(array_replace($request,['customer_id'=>'OTHER'])),
    fn()=>$gateway->create(array_replace($request,['amount'=>'1.00'])),
    fn()=>$gateway->create(array_replace_recursive($request,['biz'=>['head'=>['is_auto_generate_cust'=>1]]])),
    fn()=>$gateway->create(array_replace_recursive($request,['biz'=>['head'=>['is_auto_generate_subject'=>1]]])),
    fn()=>$gateway->create(array_replace_recursive($request,['biz'=>['head'=>['business_date'=>'2026-02-31']]])),
    fn()=>$gateway->create(array_replace_recursive($request,['biz'=>['head'=>['remark'=>['service'=>'not-allowed']]]])),
    fn()=>$gateway->create(array_replace_recursive($request,['biz'=>['receipt_details'=>['add_details'=>[1=>['account_id'=>'FUND','amounts'=>2]]]]])),
    fn()=>$gateway->create(array_replace_recursive($request,['biz'=>['payment_details'=>['add_details'=>[]]]])),
] as $i=>$fn) receiptReject($fn,'none','invalid preflight '.$i);
checkReceipt(count($wire)===$before,'no request leaves on invalid local input');

$objections=[
    ['head'=>['bill_type'=>'PAYMENT']], ['head'=>['big_gsdm'=>'OTHER-LEDGER']], ['head'=>['company_id'=>'OTHER']],
    ['head'=>['supplier_cust_id'=>'OTHER']], ['head'=>['scope_flag'=>1]], ['head'=>['creater_id'=>'OTHER']],
    ['head'=>['bill_id'=>null]], ['head'=>['bill_id '=>'conflict']], ['head'=>['amounts'=>'1.99']],
    ['receipt_details'=>['add_details'=>[['account_id'=>'OTHER']]]], ['receipt_details'=>['add_details'=>[['amounts'=>1.999]]]],
    ['receipt_details'=>['add_details'=>[['handling_fee'=>1]]]], ['receipt_details'=>['add_details'=>[['seq_no'=>2]]]],
    ['receipt_details'=>['add_details'=>[1=>['id'=>'EXTRA']]]], ['payment_details'=>['add_details'=>[['id'=>'WRONG']]]],
];
foreach ($objections as $i=>$delta) {
    $before=count($wire); $reply=array_replace_recursive($result,$delta);
    receiptReject(fn()=>$gateway->create($request),'unknown','contradictory vendor success '.$i);
    checkReceipt(count($wire)===$before+1,'never retry an ambiguous vendor receipt');
}
$reply=null;$before=count($wire);
receiptReject(fn()=>$gateway->create($request),'unknown','lost receipt response');
checkReceipt(count($wire)===$before+1,'no second request after timeout');
echo "PASS: $checks receipt gateway checks; fixture exchange only\n";
