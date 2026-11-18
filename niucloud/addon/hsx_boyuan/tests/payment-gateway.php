<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit("CLI only\n");
// Pure fake exchange: no framework, database or real HTTP transport is loaded.
require __DIR__.'/../app/support/Problem.php';
require __DIR__.'/../app/support/Contract.php';
require __DIR__.'/../app/gateway/Transport.php';
require __DIR__.'/../app/gateway/OpenApi.php';
require __DIR__.'/../app/gateway/PaymentGateway.php';

use addon\hsx_boyuan\app\gateway\{OpenApi,PaymentGateway};
use addon\hsx_boyuan\app\support\{Contract,Problem};

$checks=0; $calls=0; $secret='FAKE_PAYMENT_SECRET_NOT_REAL';
function payRule(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; }
function payDenied(callable $fn,string $label,string $effect='none',?string $reason=null): void
{
    try {$fn();} catch(Problem $error) {payRule($error->effect===$effect && ($reason===null || $reason===$error->reason),$label.' ['.$error->reason.'/'.$error->effect.']'); return;}
    throw new RuntimeException($label.' accepted');
}
function payRequest(): array
{
    $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    return ['schema'=>PaymentGateway::SCHEMA,'stage'=>'frozen','mode'=>'test','source_settlement_id'=>1,'source_task_id'=>7,'source_snapshot_hash'=>str_repeat('a',64),
        'company_id'=>'COMPANY1','supplier_id'=>'SUPPLIER1','account_id'=>'ACCOUNT1','amount'=>'1.00',
        'biz'=>['head'=>['company_id'=>'COMPANY1','business_date'=>$today,'supplier_cust_id'=>'SUPPLIER1','scope_flag'=>3,
            'is_auto_generate_supplier'=>0,'is_auto_generate_subject'=>0,'creater_id'=>'BOYUAN_STAFF1','creater_name'=>'博远制单员 张三','remark'=>'UAT 本地结算#1 & 测试'],
            'payment_details'=>['add_details'=>[['account_id'=>'ACCOUNT1','account_name'=>'UAT测试账户 5525','amounts'=>'1.00','handling_fee'=>'0.00','seq_no'=>1]]]]];
}
function payResponse(array $request): array
{
    global $secret;
    return ['result'=>['head'=>$request['biz']['head']+['id'=>'PAYMENT_HEAD1','bill_id'=>'PM00001','bill_type'=>'PAYMENT','big_gsdm'=>'ACCOUNTROOT',
        'account_no'=>'PRIVATE_BANK_ACCOUNT','supplier_cust_account_name'=>'PRIVATE_PAYEE','app_secret'=>$secret],
        'payment_details'=>['add_details'=>[['id'=>'PAYMENT_LINE1','account_id'=>'ACCOUNT1','account_name'=>'PRIVATE_ACCOUNT_NAME','amounts'=>1,
            'handling_fee'=>0,'seq_no'=>1,'token'=>'PRIVATE_TOKEN']]],'private_trace'=>$secret]];
}
function payApi(array $response,array &$wire,string $mode='test',string $endpoint='https://open-api-uat.139erp.com/api',string $path='/gateway.do',?Closure $override=null): OpenApi
{
    global $secret;
    return new OpenApi(['app_id'=>'123','account_id'=>'ACCOUNTROOT','endpoint'=>$endpoint,'gateway_path'=>$path],$secret,
        function(string $path,array $params) use($response,&$wire,$override): array {
            global $calls; $calls++; $wire[]=['path'=>$path,'params'=>$params];
            if($override) return $override($params);
            if($params['service']!=='boyuan.biz.bill.payment.generate') throw new RuntimeException('Unexpected service');
            return ['return_code'=>'SUCCESS','api_response'=>$response];
        },$mode);
}

try {
    $request=payRequest(); $response=payResponse($request); $wire=[]; $api=payApi($response,$wire); $gateway=new PaymentGateway($api);
    $result=$gateway->create($request);
    payRule($result['created'] && $result['payment_registered'] && $result['external_id']==='PAYMENT_HEAD1' && $result['external_no']==='PM00001' && $result['amount']==='1.00','a complete receipt identifies the created payment voucher and exact amount');
    payRule(!$result['verified'] && !$result['financial_posting_verified'] && !$result['allocation_applied_verified'] && !$result['bank_transfer_verified'],'creation does not claim audit, allocation, bank payment or full verification');
    payRule($result['verification_level']==='payment_create_receipt_matched' && $result['evidence']['scope_flag']===3,'receipt level is explicitly distinct from settlement application');
    payRule(!str_contains(Contract::json($result),'PRIVATE_') && !str_contains(Contract::json($result),$secret),'returned whitelist excludes bank data, account names, tokens and secret traces');
    $params=$wire[0]['params']; $json=Contract::json($request['biz']);
    payRule($params['biz']===$json && !isset($params['source_settlement_id']) && count($params)===9,'only official JSON, not internal settlement metadata, is transmitted');
    $source='_input_charset=utf-8&app_id=123&big_gsdm=ACCOUNTROOT&biz='.$json.'&service=boyuan.biz.bill.payment.generate&timestamp='.$params['timestamp'].'&version=1.0';
    payRule($params['sign']===md5($source.$secret) && str_contains($params['biz'],'"is_auto_generate_supplier":0') && str_contains($params['biz'],'"handling_fee":"0.00"'),'exact Chinese JSON and zero-valued safety fields participate in signing');
    payRule($wire[0]['path']==='/gateway.do' && count($wire)===1,'creation makes one signed UAT accounting call, never an automatic second call');
    payDenied(fn()=>$api->call('boyuan.biz.bill.payment.generate'),'generic calls cannot register payments','none','READ_ONLY_ONLY');
    payDenied(fn()=>$api->call('boyuan.trade.unified.pay'),'generic calls cannot trigger a real payment','none','READ_ONLY_ONLY');
    payDenied(fn()=>$api->purchaseQuery('payment',[]),'purchase query cannot query a payment bill','none','PURCHASE_QUERY_INVALID');
    payDenied(fn()=>$api->inventoryQuery('PAYMENT',[]),'inventory entry cannot escape to finance','none','INVENTORY_QUERY_INVALID');
    foreach([
        ['production','https://open-api-uat.139erp.com/api','/gateway.do'],['test','https://open-api.139erp.com/api','/gateway.do'],
        ['test','https://open-api-uat.139erp.com/api/','/gateway.do'],['test','https://open-api-uat.139erp.com/api','/gateway/do'],
        ['mock','https://open-api-uat.139erp.com/api','/gateway.do'],['','https://open-api-uat.139erp.com/api','/gateway.do'],
    ] as [$mode,$endpoint,$path]) {
        $w=[]; $a=payApi($response,$w,$mode,$endpoint,$path); $g=new PaymentGateway($a);
        payRule(!$a->uatPaymentEnabled(),'only explicit test mode and exact UAT endpoint/path enables payment registration');
        payDenied(fn()=>$g->create($request),'gateway refuses non-UAT payment locally','none','PAYMENT_UAT_ONLY');
        payDenied(fn()=>$a->paymentCreate($request['biz']),'direct adapter also refuses non-UAT payment locally','none','PAYMENT_UAT_ONLY');
        payRule(!$w,'environment rejection performs zero exchanges');
    }
    foreach(['schema','zero','negative','fraction','number','company','supplier','account','amount','scope','autosupplier','autosubject','creator','creatoruid','creatorname','date','cash','bank','multiline','fee','sequence','reserved'] as $case) {
        $bad=$request;
        if($case==='schema') $bad['schema']='untrusted';
        if($case==='zero') $bad['amount']=$bad['biz']['payment_details']['add_details'][0]['amounts']='0.00';
        if($case==='negative') $bad['amount']='-1.00';
        if($case==='fraction') $bad['amount']='1.001';
        if($case==='number') $bad['amount']=1;
        if($case==='company') $bad['biz']['head']['company_id']='OTHER';
        if($case==='supplier') $bad['biz']['head']['supplier_cust_id']='OTHER';
        if($case==='account') $bad['biz']['payment_details']['add_details'][0]['account_id']='OTHER';
        if($case==='amount') $bad['biz']['payment_details']['add_details'][0]['amounts']='1.01';
        if($case==='scope') $bad['biz']['head']['scope_flag']=1;
        if($case==='autosupplier') $bad['biz']['head']['is_auto_generate_supplier']=1;
        if($case==='autosubject') $bad['biz']['head']['is_auto_generate_subject']=1;
        if($case==='creator') unset($bad['biz']['head']['creater_id']);
        if($case==='creatoruid') $bad['biz']['head']['creater_id']=123;
        if($case==='creatorname') $bad['biz']['head']['creater_name']='';
        if($case==='date') $bad['biz']['head']['business_date']='2026-02-30';
        if($case==='cash') $bad['biz']['cash_details']=['add_details'=>[]];
        if($case==='bank') $bad['biz']['head']['account_no']='REAL_TRANSFER_TARGET';
        if($case==='multiline') $bad['biz']['payment_details']['add_details'][]=$bad['biz']['payment_details']['add_details'][0];
        if($case==='fee') $bad['biz']['payment_details']['add_details'][0]['handling_fee']='0.01';
        if($case==='sequence') $bad['biz']['payment_details']['add_details'][0]['seq_no']=2;
        if($case==='reserved') $bad['biz']['head']['service']='boyuan.trade.unified.pay';
        $before=count($wire); payDenied(fn()=>$gateway->create($bad),'invalid frozen payment '.$case);
        payRule(count($wire)===$before,'invalid frozen payment '.$case.' never reaches exchange');
    }
    foreach(['app_id','big_gsdm','service','sign','timestamp'] as $field) {
        $bad=$request['biz']; $bad['head'][$field]='OVERRIDE';
        payDenied(fn()=>$api->paymentCreate($bad),'adapter rejects nested identity override '.$field,'none','PARAM_INVALID');
    }
    foreach(['empty','resulttype','tophead','mixedhead','accountroot','type','company','supplier','date','scope','missingscope','creator','id','bill','missingline','multiline','account','amount','head_amount','fraction','fee','lineid','sequence','duplicatekey'] as $case) {
        $bad=$response;
        if($case==='empty') $bad=[];
        if($case==='resulttype') $bad['result']='bad';
        if($case==='tophead') $bad=$response['result'];
        if($case==='mixedhead') $bad['head']=$response['result']['head'];
        foreach(['accountroot'=>'big_gsdm','type'=>'bill_type','company'=>'company_id','supplier'=>'supplier_cust_id','date'=>'business_date','creator'=>'creater_id'] as $name=>$field) if($case===$name) $bad['result']['head'][$field]='OTHER';
        if($case==='scope') $bad['result']['head']['scope_flag']=1;
        if($case==='missingscope') unset($bad['result']['head']['scope_flag']);
        if($case==='id') unset($bad['result']['head']['id']);
        if($case==='bill') unset($bad['result']['head']['bill_id']);
        if($case==='missingline') $bad['result']['payment_details']['add_details']=[];
        if($case==='multiline') $bad['result']['payment_details']['add_details'][]=$bad['result']['payment_details']['add_details'][0];
        if($case==='account') $bad['result']['payment_details']['add_details'][0]['account_id']='OTHER';
        if($case==='amount') $bad['result']['payment_details']['add_details'][0]['amounts']='1.01';
        if($case==='head_amount') $bad['result']['head']['amounts']='1.01';
        if($case==='fraction') $bad['result']['payment_details']['add_details'][0]['amounts']='1.001';
        if($case==='fee') $bad['result']['payment_details']['add_details'][0]['handling_fee']='0.01';
        if($case==='lineid') unset($bad['result']['payment_details']['add_details'][0]['id']);
        if($case==='sequence') $bad['result']['payment_details']['add_details'][0]['seq_no']=2;
        if($case==='duplicatekey') $bad['result']['head']['company_id ']='OTHER';
        $w=[]; $g=new PaymentGateway(payApi($bad,$w)); payDenied(fn()=>$g->create($request),'unsafe receipt '.$case,'unknown');
        payRule(count($w)===1,'unsafe receipt '.$case.' is never automatically retried');
    }
    $w=[]; $g=new PaymentGateway(payApi($response,$w,'test','https://open-api-uat.139erp.com/api','/gateway.do',static function(){throw new RuntimeException('PRIVATE_EXCEPTION');}));
    payDenied(fn()=>$g->create($request),'lost response remains unknown','unknown','OPENAPI_EXCHANGE_UNKNOWN');
    payRule(count($w)===1,'lost response causes no automatic retry');
    $w=[]; $g=new PaymentGateway(payApi($response,$w,'test','https://open-api-uat.139erp.com/api','/gateway.do',static fn()=>['return_code'=>'ERROR','return_msg'=>'bad '.$secret]));
    try {$g->create($request); throw new RuntimeException('remote rejection accepted');}
    catch(Problem $error) {payRule($error->effect==='unknown' && !str_contains($error->getMessage(),$secret),'remote error stays unknown and strips actual secret');}
    payRule(!class_exists('think\\facade\\Db',false) && !class_exists('addon\\hsx_boyuan\\app\\gateway\\HttpTransport',false),'test never loads database or real network transport');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'fake_exchanges'=>$calls,'external_calls'=>0,'database_access'=>false])."\n";
} catch(Throwable $error) {
    fwrite(STDERR,'FAIL '.get_class($error).': '.($error instanceof Problem?$error->reason:$error->getMessage()).' at '.basename($error->getFile()).':'.$error->getLine()."\n"); exit(1);
}
