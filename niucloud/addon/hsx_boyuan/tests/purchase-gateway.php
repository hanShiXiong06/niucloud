<?php
declare(strict_types=1);
// No framework bootstrap, database adapter or real HTTP transport is loaded.
if(PHP_SAPI!=='cli') exit("CLI only\n");
require __DIR__.'/../app/support/Problem.php';
require __DIR__.'/../app/support/Contract.php';
require __DIR__.'/../app/support/ReceiptFacts.php';
require __DIR__.'/../app/support/ProductionPurchasePilot.php';
require __DIR__.'/../app/support/CatalogStore.php';
require __DIR__.'/../app/gateway/Transport.php';
require __DIR__.'/../app/gateway/OpenApi.php';
require __DIR__.'/../app/gateway/PurchaseGateway.php';

use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway};
use addon\hsx_boyuan\app\support\{Contract,Problem};

$checks=0; $fakeCalls=0; $secret='FAKE_PURCHASE_SECRET_NEVER_REAL';
function pgRule(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; }
function pgDenied(callable $fn,string $label,?string $reason=null,?string $effect=null): void
{
    try { $fn(); }
    catch(Problem $e) { pgRule(($reason===null || $reason===$e->reason) && ($effect===null || $effect===$e->effect),$label.' ['.$e->reason.'/'.$e->effect.']'); return; }
    throw new RuntimeException($label.' was accepted');
}
function pgRequest(): array
{
    $lines=[]; $products=[];
    foreach([['000000000000001','P1','0.00'],['SN中文0002','P2','123.45']] as $n=>$item) {
        [$imei,$product,$amount]=$item;
        $lines[]=['source_line_id'=>'purchase-line:'.($n+1),'device_ref'=>'device:'.($n+1),'imei'=>$imei,'product_id'=>$product,'warehouse_id'=>'W1','supplier_id'=>'S1','quantity'=>'1','amount'=>$amount];
        $products[]=['imei'=>$imei,'product_id'=>$product,'qty'=>1,'unit_price'=>$amount,'amounts'=>$amount,'remark'=>'采购明细：'.($n+1)];
    }
    return ['schema'=>'boyuan.purchase.v154','stage'=>'frozen','selection'=>['internal'=>'not sent'],
        'third_no'=>'NIU_PURCHASE_STABLE_1','biz'=>['head'=>['warehouse_id'=>'W1','third_no'=>'NIU_PURCHASE_STABLE_1','business_date'=>'2026-11-17','cash_term'=>0,'is_auto_generate_supplier'=>0,'supplier_id'=>'S1','creater_id'=>'BOYUAN_STAFF_CREATER_1','creater_name'=>'博远制单员 & 张三','remark'=>'中文 & /= 零元采购'],
        'product_details'=>['add_details'=>$products]],'lines'=>$lines];
}
function pgFixtures(array $request): array
{
    global $secret;
    $head=$request['biz']['head']+['id'=>'HEAD1','big_gsdm'=>'ACCOUNT1','bill_id'=>'PUR00001','bill_type'=>'PUR','customer_phone'=>'13800000000','app_secret'=>$secret];
    $create=[]; $details=[]; $imeis=[];
    foreach($request['biz']['product_details']['add_details'] as $n=>$row) {
        $seq=$n+1;
        $create[]=$row+['id'=>'PRODUCT_DETAIL_'.$seq,'seq_no'=>$seq,'internal_private'=>'must_not_keep'];
        $identity=['bill_id'=>'PUR00001','bill_type'=>'PUR','business_date'=>'2026-11-17','supplier_id'=>'S1','warehouse_id'=>'W1','company_id'=>'SHOP1','trans_type'=>'购','is_effective'=>1,'seq_no'=>$seq];
        $details[]=$identity+['id'=>'PURCHASE_DETAIL_'.$seq,'head_id'=>'HEAD1','product_id'=>$row['product_id'],'qty'=>1,'unit_price'=>$row['unit_price'],'amounts'=>$row['amounts']];
        $imeis[]=$identity+['detail_id'=>'IMEI_DETAIL_'.$seq,'gsdm'=>'80001','imei1'=>$row['imei'],'imei'=>$row['imei'],'imei2'=>null,'imei3'=>'','product_id'=>$row['product_id'],'qty'=>1,'unit_price'=>$row['unit_price'],'amounts'=>$row['amounts']];
    }
    return ['create'=>['result'=>['head'=>$head,'product_details'=>['add_details'=>$create],'secret_trace'=>$secret]],'details'=>$details,'imei'=>$imeis];
}
function pgApi(array $fixture,array &$wire,?Closure $override=null,string $mode='',string $endpoint='',string $path='/gateway.do'): OpenApi
{
    global $secret;
    return new OpenApi(['app_id'=>'APP1','account_id'=>'ACCOUNT1','gateway_path'=>$path,'endpoint'=>$endpoint],$secret,
        function(string $path,array $params) use($fixture,&$wire,$override): array {
            global $fakeCalls;
            $fakeCalls++; $wire[]=['path'=>$path,'params'=>$params];
            if($override) { $reply=$override($params,$fixture); if($reply!==null) return $reply; }
            if($params['service']==='boyuan.biz.bill.purchase.generate') return ['return_code'=>'SUCCESS','api_response'=>$fixture['create']];
            $kind=$params['service']==='boyuan.biz.purchases.query'?'details':($params['service']==='boyuan.biz.purchase.imeidetails.query'?'imei':null);
            if($kind===null) throw new RuntimeException('Unexpected service');
            $rows=$fixture[$kind]; $page=(int)$params['page_no']; $size=(int)$params['page_size'];
            return ['return_code'=>'SUCCESS','api_response'=>['total_results '=>count($rows),'result_list'=>array_slice($rows,($page-1)*$size,$size)]];
        },$mode);
}
function pgUatFixture(array $fixture): array
{
    foreach($fixture['details'] as &$row) unset($row['head_id']); unset($row);
    foreach($fixture['imei'] as $index=>&$row) {
        $row['id']='SERIAL_RECORD_'.($index+1); $row['detail_id']=$fixture['details'][$index]['id'];
        $row['company_code']=$row['gsdm']; $row['amount']=$row['amounts'];
        unset($row['gsdm'],$row['amounts'],$row['seq_no']);
    }
    unset($row); return $fixture;
}

try {
    $request=pgRequest(); $fixture=pgFixtures($request); $wire=[]; $api=pgApi($fixture,$wire); $gateway=new PurchaseGateway($api);
    $orig=$request; $orig['original_event_id']='EV-OLD'; $orig['lines']=[$orig['lines'][0]];
    $orig['biz']['product_details']['add_details']=[$orig['biz']['product_details']['add_details'][0]];
    $sight=function(array $pur,array $inv)use($fixture) {
        $w=[];return new PurchaseGateway(pgApi($fixture,$w,static function($p)use($pur,$inv) {
            $service=$p['service']; if(!in_array($service,['boyuan.biz.purchase.imeidetails.query','boyuan.biz.inventory.imeidetails.query'],true))
                throw new RuntimeException('This check may never create a document');
            if($service==='boyuan.biz.inventory.imeidetails.query' && $p['imei']!=='000000000000001')throw new RuntimeException('Wrong serial');
            return ['return_code'=>'SUCCESS','api_response'=>$service==='boyuan.biz.inventory.imeidetails.query'?$inv:$pur];
        }));
    };
    $zero=['total_results'=>0,'result_list'=>[]];$sight($zero,$zero)->checkExistingSerial($orig);
    pgDenied(fn()=>$sight(['total_results'=>1,'result_list'=>[$fixture['imei'][0]]],$zero)->checkExistingSerial($orig),'old PO duplicate must stop before issuing any purchase','ORIGINAL_PURCHASE_REMOTE_EXISTS','none');
    pgDenied(fn()=>$sight($zero,['total_results'=>1,'result_list'=>[['imei'=>'000000000000001']]])->checkExistingSerial($orig),'serial found in current stock must block','ORIGINAL_PURCHASE_REMOTE_EXISTS','none');
    pgDenied(fn()=>$sight(['total_results'=>2,'result_list'=>[]],$zero)->checkExistingSerial($orig),'unread pages cannot clear a purchase','PURCHASE_PAGE_INCOMPLETE','none');
    pgDenied(fn()=>$sight($zero,[])->checkExistingSerial($orig),'missing inventory response cannot clear a purchase','ORIGINAL_PURCHASE_REMOTE_UNKNOWN','none');
    $created=$gateway->create($request);
    pgRule($created['created'] && $created['external_no']==='PUR00001' && $created['external_id']==='HEAD1','create returns known vendor identity only');
    pgRule(!isset($created['verified']) && !isset($created['inventory_applied']),'create never invents inventory or audit completion');
    $params=$wire[0]['params']; $json=Contract::json($request['biz']);
    pgRule($params['biz']===$json && is_string($params['biz']),'one exact official JSON string is transmitted');
    $signSource='_input_charset=utf-8&app_id=APP1&big_gsdm=ACCOUNT1&biz='.$json.'&service=boyuan.biz.bill.purchase.generate&timestamp='.$params['timestamp'].'&version=1.0';
    pgRule($params['sign']===md5($signSource.$secret),'exact JSON, Chinese, zero and raw special characters participate in signature');
    $sentBiz=Contract::decode($params['biz']);
    pgRule($sentBiz['head']['creater_id']==='BOYUAN_STAFF_CREATER_1' && $sentBiz['head']['creater_name']==='博远制单员 & 张三','external Boyuan creator identity and name remain in the signed biz JSON');
    pgRule($wire[0]['path']==='/gateway.do' && $params['version']==='1.0' && count($params)===9 && !isset($params['head']),'signed form has only common identity fields plus biz');
    pgRule(!str_contains(Contract::json($created),$secret) && !str_contains(Contract::json($created),'13800000000') && !str_contains(Contract::json($created),'must_not_keep'),'create persistence whitelist excludes secrets, contact and arbitrary data');
    pgRule($created['response']['product_details']['add_details'][0]['imei']==='000000000000001','leading zeros survive create response');
    pgRule(OpenApi::sign(['biz'=>'{"amounts":0,"remark":"中文 &"}','z'=>0,'empty'=>'','nil'=>null],'fake')===md5('biz={"amounts":0,"remark":"中文 &"}&z=0fake'),'zero and exact JSON are retained before URL encoding');
    $result=$gateway->verify($request+['external_id'=>'HEAD1'],'PUR00001');
    pgRule($result['verified'] && count($result['lines'])===2 && $result['lines'][1]['source_line_id']==='purchase-line:2','complete per-device manifest is verified');
    pgRule($result['lines'][0]['external_line_id']==='PURCHASE_DETAIL_1' && $result['evidence']['external_line_id_source']==='purchase_detail.id','documented mode consistently binds the real purchase line, not the auxiliary serial record');
    pgRule($result['evidence']['document_head_id_verified'] && $result['evidence']['created_head_id_matched'] && $result['evidence']['response_contract']===OpenApi::PURCHASE_DIALECT_DOCUMENTED,'documented mode verifies saved head identity and labels its contract');
    pgRule($result['lines'][0]['imei']==='000000000000001' && $result['lines'][1]['imei']==='SN中文0002','IMEI and SN strings remain exact');
    pgRule($result['verification_level']==='purchase_imei_records_matched' && !$result['stock_balance_verified'] && !$result['financial_posting_verified'],'record matching does not claim stock balance or financial posting');
    pgRule($result['evidence']['gsdm']==='80001' && $result['evidence']['company_id']==='SHOP1','shop code is not incorrectly equated with root account code');
    pgRule($wire[1]['params']['business_date_start']==='2026-11-17' && $wire[1]['params']['business_date_end']==='2026-11-17' && !isset($wire[1]['params']['bill_id']),'purchase-detail lookup uses documented date filters only');
    pgRule($wire[2]['params']['bill_id']==='PUR00001' && $wire[2]['params']['bill_type']==='PUR','IMEI lookup is restricted to the known purchase bill');
    $priced=$request; $priced['biz']['head']+=['sales_id'=>'STAFF1','sales_name'=>'定价人'];
    foreach([
        [[['STAFF1','定价人'],['STAFF1','定价人']],'matched','STAFF1','定价人'],
        [[['STAFF1',null],['STAFF1',null]],'matched','STAFF1',''],
        [[[null,'定价人'],[null,'定价人']],'not_returned','','定价人'],
        [[['STAFF1','定价人'],[null,null]],'not_returned','STAFF1','定价人'],
        [[['STAFF1','定价人'],['OTHER','其他']],'mismatch','',''],
        [[['OTHER','定价人'],['OTHER','定价人']],'mismatch','OTHER','定价人'],
        [[['STAFF1','其他'],['STAFF1','其他']],'mismatch','STAFF1','其他'],
        [[[null,null],[null,null]],'not_returned','',''],
    ] as [$returned,$status,$id,$name]) {
        $sample=$fixture;
        foreach($returned as $index=>[$salesId,$salesName]) $sample['details'][$index]+=['sales_id'=>$salesId,'sales_name'=>$salesName,'private'=>'discard'];
        $sample['details'][]=array_replace($sample['details'][0],['id'=>'FOREIGN_LINE','bill_id'=>'OTHER_PUR','sales_id'=>'unrelated','sales_name'=>'无关人员']);
        $writes=[]; $read=(new PurchaseGateway(pgApi($sample,$writes)))->verify($priced+['external_id'=>'HEAD1'],'PUR00001');
        pgRule($read['evidence']['pricing_buyer']===['source'=>'purchase_details','status'=>$status,'sales_id'=>$id,'sales_name'=>$name],'aggregate buyer only on exact, cross-checked purchase lines: '.$status.' '.$id);
        pgRule(count($writes)===2 && in_array($writes[0]['params']['service'],['boyuan.biz.purchases.query'],true),'buyer readback is read-only');
    }
    pgDenied(fn()=>$api->call('boyuan.biz.bill.purchase.generate'),'generic call cannot create','READ_ONLY_ONLY');
    pgDenied(fn()=>$api->call('boyuan.trade.unified.pay'),'generic call cannot pay','READ_ONLY_ONLY');
    pgDenied(fn()=>$api->purchaseQuery('payment',[]),'query kind cannot escape to another service','PURCHASE_QUERY_INVALID');
    pgDenied(fn()=>$api->purchaseQuery('details',['service'=>'boyuan.trade.unified.pay']),'purchase query rejects service override','PARAM_INVALID');
    pgDenied(fn()=>$api->purchaseQuery('details',['bill_id'=>'undocumented']),'detail query rejects undocumented bill filter','PARAM_INVALID');
    $cash=$request['biz']; $cash['head']['cash_term']=1;
    pgDenied(fn()=>$api->purchaseCreate($cash),'direct purchase adapter cannot register cash','PURCHASE_SCOPE_DENIED');
    $cash=$request['biz']; $cash['cash_details']=['add_details'=>[]];
    pgDenied(fn()=>$api->purchaseCreate($cash),'direct purchase adapter cannot carry cash nodes','PURCHASE_SCOPE_DENIED');
    foreach(['app_id','big_gsdm','sign','timestamp','service'] as $key) {
        $bad=$request['biz']; $bad['head'][$key]='OVERRIDE';
        pgDenied(fn()=>$api->purchaseCreate($bad),'biz cannot override reserved '.$key,'PARAM_INVALID');
    }

    foreach(['cash','autocreate','cashnode','wrongtotal','duplicate','date','missing','productoverride','multiserial','localcreator','creatorname'] as $case) {
        $bad=$request;
        if($case==='cash') $bad['biz']['head']['cash_term']=1;
        if($case==='autocreate') $bad['biz']['head']['is_auto_generate_supplier']=1;
        if($case==='cashnode') $bad['biz']['cash_details']=['add_details'=>[]];
        if($case==='wrongtotal') $bad['biz']['product_details']['add_details'][0]['amounts']='0.01';
        if($case==='duplicate') $bad['lines'][1]['imei']=$bad['lines'][0]['imei'];
        if($case==='date') $bad['biz']['head']['business_date']='2026-02-30';
        if($case==='missing') unset($bad['biz']['head']['supplier_id']);
        if($case==='productoverride') $bad['biz']['product_details']['add_details'][0]['product_id']='P_OTHER';
        if($case==='multiserial') $bad['biz']['product_details']['add_details'][0]['imei2']='SECONDARY';
        if($case==='localcreator') $bad['biz']['head']['creater_id']=123;
        if($case==='creatorname') $bad['biz']['head']['creater_name']=['invalid'];
        $before=count($wire); pgDenied(fn()=>$gateway->create($bad),'invalid frozen request '.$case,null,'none');
        pgRule(count($wire)===$before,'invalid request '.$case.' makes zero fake exchanges');
    }

    foreach(['account','third','type','no','id','missingline','dupline','lineid','amount','serial','sequence','empty','format','duplicatekey','emptyresult','nullresult','stringresult','tophead','mixedtophead','emptyresult_tophead'] as $case) {
        $bad=$fixture;
        if($case==='account') $bad['create']['result']['head']['big_gsdm']='OTHER';
        if($case==='third') $bad['create']['result']['head']['third_no']='OTHER';
        if($case==='type') $bad['create']['result']['head']['bill_type']='PURR';
        if($case==='no') unset($bad['create']['result']['head']['bill_id']);
        if($case==='id') unset($bad['create']['result']['head']['id']);
        if($case==='missingline') array_pop($bad['create']['result']['product_details']['add_details']);
        if($case==='dupline') $bad['create']['result']['product_details']['add_details'][1]=$bad['create']['result']['product_details']['add_details'][0];
        if($case==='lineid') $bad['create']['result']['product_details']['add_details'][1]['id']=$bad['create']['result']['product_details']['add_details'][0]['id'];
        if($case==='amount') $bad['create']['result']['product_details']['add_details'][1]['amounts']='123.451';
        if($case==='serial') $bad['create']['result']['product_details']['add_details'][0]['imei']='0001';
        if($case==='sequence') $bad['create']['result']['product_details']['add_details'][1]['seq_no']=1;
        if($case==='empty') $bad['create']=[];
        if($case==='format') $bad['create']['result']['product_details']['add_details']='bad';
        if($case==='duplicatekey') $bad['create']['result']['head']['bill_id ']='PUR00001';
        if($case==='emptyresult') $bad['create']['result']=[];
        if($case==='nullresult') $bad['create']['result']=null;
        if($case==='stringresult') $bad['create']['result']='bad';
        if($case==='tophead') $bad['create']=$fixture['create']['result'];
        if($case==='mixedtophead') $bad['create']['head']=$fixture['create']['result']['head'];
        if($case==='emptyresult_tophead') {$bad['create']['result']=[]; $bad['create']['head']=$fixture['create']['result']['head'];}
        $w=[]; $g=new PurchaseGateway(pgApi($bad,$w));
        pgDenied(fn()=>$g->create($request),'unsafe create response '.$case,null,'unknown');
        pgRule(count($w)===1,'unsafe create response '.$case.' is never automatically retried');
    }
    $w=[]; $g=new PurchaseGateway(pgApi($fixture,$w,static fn()=>['return_code'=>'SUCCESS','api_response'=>'not object']));
    pgDenied(fn()=>$g->create($request),'SUCCESS envelope without object is uncertain','OPENAPI_RESPONSE_INVALID','unknown');
    $w=[]; $g=new PurchaseGateway(pgApi($fixture,$w,static function() {throw new RuntimeException('do not disclose');}));
    pgDenied(fn()=>$g->create($request),'lost response remains unknown','OPENAPI_EXCHANGE_UNKNOWN','unknown');
    $w=[]; $g=new PurchaseGateway(pgApi($fixture,$w,static fn()=>['return_code'=>'VENDOR_ERROR','return_msg'=>'bad '.$secret,'api_response'=>[]]));
    try { $g->create($request); throw new RuntimeException('vendor error accepted'); }
    catch(Problem $e) { pgRule($e->effect==='unknown' && !str_contains($e->getMessage(),$secret),'vendor error message never discloses signing secret'); }

    foreach(['unknown','partial','extra','supplier','warehouse','company','deleted','unknownstatus','product','quantity','amount','imei','dupimei','seq','head','duplicateid','secondimei'] as $case) {
        $bad=$fixture;
        if($case==='unknown') foreach($bad['details'] as &$row) $row['bill_id']='OTHER'; unset($row);
        if($case==='partial') array_pop($bad['imei']);
        if($case==='extra') {$row=$bad['imei'][0]; $row['detail_id']='EXTRA'; $row['imei1']=$row['imei']='EXTRA_IMEI'; $bad['imei'][]=$row;}
        if($case==='supplier') $bad['imei'][0]['supplier_id']='S_OTHER';
        if($case==='warehouse') $bad['imei'][0]['warehouse_id']='W_OTHER';
        if($case==='company') $bad['imei'][0]['company_id']='SHOP_OTHER';
        if($case==='deleted') $bad['details'][0]['is_effective']=0;
        if($case==='unknownstatus') unset($bad['imei'][0]['is_effective']);
        if($case==='product') $bad['imei'][0]['product_id']='P_OTHER';
        if($case==='quantity') $bad['details'][0]['qty']=2;
        if($case==='amount') $bad['details'][0]['amounts']='0.01';
        if($case==='imei') $bad['imei'][0]['imei1']='123';
        if($case==='dupimei') {$bad['imei'][1]['imei1']=$bad['imei'][1]['imei']=$bad['imei'][0]['imei1'];}
        if($case==='seq') $bad['imei'][1]['seq_no']=1;
        if($case==='head') $bad['details'][1]['head_id']='HEAD_OTHER';
        if($case==='duplicateid') $bad['imei'][1]['detail_id']=$bad['imei'][0]['detail_id'];
        if($case==='secondimei') $bad['imei'][0]['imei2']='EXTRA';
        $w=[]; $g=new PurchaseGateway(pgApi($bad,$w));
        pgDenied(fn()=>$g->verify($request+['external_id'=>'HEAD1'],'PUR00001'),'verification rejects '.$case,null,'unknown');
        pgRule(!in_array('boyuan.biz.bill.purchase.generate',array_column(array_column($w,'params'),'service'),true),'verification failure '.$case.' never creates');
    }
    pgDenied(fn()=>$gateway->verify($request+['external_id'=>'WRONG'],'PUR00001'),'saved create identity must match query head','PURCHASE_HEAD_CONFLICT','unknown');
    foreach([
        ['details','id',null,'details.page1.result_list.0.id'],
        ['details','head_id','','details.head_id'],
        ['details','company_id',str_repeat('PRIVATE',8),'details.company_id'],
        ['imei','detail_id',123456789,'imei.page1.result_list.0.detail_id'],
        ['imei','gsdm',[],'imei.gsdm'],
        ['imei','imei1',str_repeat('SENSITIVE',8),'imei.imei1'],
    ] as [$kind,$field,$value,$path]) {
        $bad=$fixture; $bad[$kind][0][$field]=$value; $w=[]; $g=new PurchaseGateway(pgApi($bad,$w));
        try { $g->verify($request,'PUR00001'); throw new RuntimeException('invalid identity accepted: '.$path); }
        catch(Problem $e) {
            $message=$e->getMessage(); $empty=$value===null || $value===''?'true':'false'; $length=is_string($value)?(string)strlen($value):'n/a';
            pgRule($e->reason==='PURCHASE_IDENTITY_INVALID' && $e->effect==='unknown'
                && str_contains($message,'path='.$path.'; type='.get_debug_type($value).'; empty='.$empty.'; length='.$length)
                && (!is_string($value) || $value==='' || !str_contains($message,$value)),
                'identity diagnostics expose only field path and shape: '.$path);
        }
    }
    $uatEndpoint='https://open-api-uat.139erp.com/api'; $uat=pgUatFixture($fixture); $w=[];
    $uatApi=pgApi($uat,$w,null,'test',$uatEndpoint); $uatGateway=new PurchaseGateway($uatApi);
    pgRule($uatApi->purchaseDialect()===OpenApi::PURCHASE_DIALECT_UAT,'UAT response adaptation requires explicit test mode and exact endpoint/path');
    $uatResult=$uatGateway->verify($request+['external_id'=>'HEAD1'],'PUR00001');
    pgRule($uatResult['verified'] && $uatResult['external_id']==='HEAD1' && $uatResult['lines'][0]['external_line_id']==='PURCHASE_DETAIL_1','UAT foreign key links serial.detail_id to real purchase.id, never serial.id');
    pgRule(!$uatResult['evidence']['document_head_id_verified'] && !$uatResult['evidence']['created_head_id_matched'] && $uatResult['evidence']['response_contract']===OpenApi::PURCHASE_DIALECT_UAT,'missing UAT head id is explicitly unverified while retaining the saved create id');
    pgRule(!$uatResult['stock_balance_verified'] && !$uatResult['financial_posting_verified'] && $uatResult['evidence']['gsdm']==='80001','UAT aliases do not upgrade stock or financial evidence');
    pgDenied(fn()=>$uatGateway->verify($request,'PUR00001'),'UAT recovery without a saved create head never invents one','PURCHASE_HEAD_ID_UNVERIFIABLE','unknown');
    $reordered=$uat; $reordered['imei']=array_reverse($reordered['imei']); $w=[];
    $g=new PurchaseGateway(pgApi($reordered,$w,null,'test',$uatEndpoint));
    pgRule($g->verify($request+['external_id'=>'HEAD1'],'PUR00001')['lines']===$uatResult['lines'],'UAT joins by foreign key regardless of response order or absent serial seq_no');
    $dual=$uat;
    foreach($dual['imei'] as &$row) {$row['gsdm']=$row['company_code']; $row['amounts']=$row['amount'];} unset($row);
    $w=[]; $g=new PurchaseGateway(pgApi($dual,$w,null,'test',$uatEndpoint));
    pgRule($g->verify($request+['external_id'=>'HEAD1'],'PUR00001')['verified'],'matching UAT dual aliases are accepted only when consistent');
    foreach(['foreignkey','serialid','missingforeignkey','amount','amountconflict','companyconflict','missingamount','quantity','seqconflict','providedheadconflict','emptyhead'] as $case) {
        $bad=$uat;
        if($case==='foreignkey') $bad['imei'][0]['detail_id']='UNKNOWN_PURCHASE_LINE';
        if($case==='serialid') {$bad['imei'][0]['id']=$bad['details'][0]['id']; $bad['imei'][0]['detail_id']='UNKNOWN_PURCHASE_LINE';}
        if($case==='missingforeignkey') unset($bad['imei'][0]['detail_id']);
        if($case==='amount') $bad['imei'][0]['amount']='0.01';
        if($case==='amountconflict') $bad['imei'][0]['amounts']='0.01';
        if($case==='companyconflict') $bad['imei'][0]['gsdm']='DIFFERENT_CODE';
        if($case==='missingamount') unset($bad['imei'][0]['amount']);
        if($case==='quantity') $bad['imei'][0]['qty']=2;
        if($case==='seqconflict') $bad['imei'][0]['seq_no']=2;
        if($case==='providedheadconflict') $bad['details'][0]['head_id']='OTHER_HEAD';
        if($case==='emptyhead') $bad['details'][0]['head_id']='';
        $w=[]; $g=new PurchaseGateway(pgApi($bad,$w,null,'test',$uatEndpoint));
        pgDenied(fn()=>$g->verify($request+['external_id'=>'HEAD1'],'PUR00001'),'UAT contract rejects '.$case,null,'unknown');
        pgRule(!in_array('boyuan.biz.bill.purchase.generate',array_column(array_column($w,'params'),'service'),true),'UAT verification failure '.$case.' never recreates');
    }
    foreach([
        ['production',$uatEndpoint,'/gateway.do'],['',$uatEndpoint,'/gateway.do'],['test','https://open-api.139erp.com/api','/gateway.do'],
        ['test',$uatEndpoint.'/','/gateway.do'],['test',$uatEndpoint,'/gateway/do'],['mock',$uatEndpoint,'/gateway.do'],
    ] as [$mode,$endpoint,$path]) {
        $w=[]; $apiStrict=pgApi($uat,$w,null,$mode,$endpoint,$path); $g=new PurchaseGateway($apiStrict);
        pgRule($apiStrict->purchaseDialect()===OpenApi::PURCHASE_DIALECT_DOCUMENTED,'non-exact environment cannot activate UAT response aliases');
        pgDenied(fn()=>$g->verify($request+['external_id'=>'HEAD1'],'PUR00001'),'non-UAT mode refuses missing documented query head_id','PURCHASE_IDENTITY_INVALID','unknown');
    }

    // Date queries legitimately include other bills: scan every page before selecting ours.
    $many=$fixture;
    for($i=0;$i<101;$i++) {$row=$fixture['details'][0]; $row['id']='OTHER_DETAIL_'.$i; $row['head_id']='OTHER_HEAD_'.$i; $row['bill_id']='OTHER_'.$i; array_unshift($many['details'],$row);}
    $w=[]; $g=new PurchaseGateway(pgApi($many,$w)); $verified=$g->verify($request+['external_id'=>'HEAD1'],'PUR00001');
    pgRule($verified['verified'] && count($w)===3 && $w[1]['params']['page_no']===2,'all date-query pages are read before complete verification');
    foreach(['empty','totaldrift','duplicate','missingtotal','truncated'] as $case) {
        $w=[]; $g=new PurchaseGateway(pgApi($many,$w,static function(array $params,array $f) use($case): ?array {
            if($params['service']!=='boyuan.biz.purchases.query') return null;
            if($case==='missingtotal') return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>$f['details']]];
            if($case==='truncated' && $params['page_no']===1) return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>103,'result_list'=>array_slice($f['details'],0,99)]];
            if($params['page_no']!==2) return null;
            $rows=array_slice($f['details'],100);
            if($case==='empty') $rows=[];
            if($case==='duplicate') $rows[0]=$f['details'][0];
            return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>$case==='totaldrift'?104:103,'result_list'=>$rows]];
        }));
        pgDenied(fn()=>$g->verify($request,'PUR00001'),'pagination rejects '.$case,null,'unknown');
    }
    $many=$fixture;
    for($i=0;$i<101;$i++) {$row=$fixture['imei'][0]; $row['detail_id']='UNEXPECTED_IMEI_DETAIL_'.$i; $row['imei1']=$row['imei']='EXTRA_SERIAL_'.$i; $many['imei'][]=$row;}
    $w=[]; $g=new PurchaseGateway(pgApi($many,$w));
    pgDenied(fn()=>$g->verify($request,'PUR00001'),'unexpected IMEI results cannot be truncated into success','PURCHASE_IMEI_INCOMPLETE','unknown');
    pgRule(count($w)===3 && $w[2]['params']['page_no']===2,'IMEI verification also exhausts all pages');
    // Vendor JSON number variants compare by exact cents, including zero.
    $numeric=$fixture;
    foreach(['details','imei'] as $kind) { $numeric[$kind][0]['unit_price']=0.0; $numeric[$kind][0]['amounts']=0; $numeric[$kind][1]['unit_price']=123.45; $numeric[$kind][1]['amounts']='123.4500'; }
    $w=[]; $g=new PurchaseGateway(pgApi($numeric,$w)); pgRule($g->verify($request,'PUR00001')['verified'],'exact vendor numeric amounts normalize without lossy rounding');
    pgRule(!class_exists('think\\facade\\Db',false) && !class_exists('addon\\hsx_boyuan\\app\\gateway\\HttpTransport',false),'test never loads database or real network transport');
    $referenceApi=new OpenApi(['app_id'=>'APP1','account_id'=>'ACCOUNT1'],$secret,static fn()=>['return_code'=>'SUCCESS','api_response'=>['result_list'=>[
        ['id'=>'S1','name'=>'测试供应商','is_effective'=>1,'contact_phone'=>'13800000000','account_no'=>'DO_NOT_PERSIST','token'=>'CREDENTIAL','note'=>$secret],
    ]]]);
    $raw=$referenceApi->call('boyuan.custsuppliers.get'); $normalized=OpenApi::normalize('parties',$raw['result_list'][0]);
    pgRule($normalized['phone']==='13800000000' && $raw['result_list'][0]['token']==='[已隐藏]' && $raw['result_list'][0]['note']==='[已隐藏]','internal reference normalization retains phone for hashing but strips credentials');
    pgRule(!str_contains(Contract::json($normalized),'DO_NOT_PERSIST') && !str_contains(Contract::json($normalized),'CREDENTIAL'),'reference normalizer never persists bank numbers or credentials');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'fake_exchanges'=>$fakeCalls,'external_calls'=>0,'database_access'=>false])."\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL '.get_class($e).': '.($e instanceof Problem?$e->reason:$e->getMessage()).' at '.basename($e->getFile()).':'.$e->getLine()."\n");
    exit(1);
}
