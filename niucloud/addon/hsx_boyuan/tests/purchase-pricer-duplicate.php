<?php
declare(strict_types=1);
/** Pre-send duplicate reconciliation. Fake transport only; no framework boot, writes, database or live credentials. */
use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway};
use addon\hsx_boyuan\app\support\{Problem,ProductionPurchasePilot as Pilot,ProductionVoucher};
foreach(['support/Contract','support/Problem','support/ReceiptFacts','support/ProductionVoucher','support/ProductionPurchasePilot','gateway/Transport','gateway/OpenApi','gateway/PurchaseGateway'] as $f)
    require __DIR__.'/../app/'.$f.'.php';
$date=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
$no='FIXTURE-PUR-9'; $lineId='aa000000-0000-4000-8000-000000000009';
$line=['source_line_id'=>'purchase_line:99','device_ref'=>'asset:99','imei'=>Pilot::NEXT_IMEI,'product_id'=>Pilot::PRODUCT,
    'warehouse_id'=>Pilot::WAREHOUSE,'supplier_id'=>Pilot::SUPPLIER,'quantity'=>'1','amount'=>'1.00'];
$row=['imei'=>Pilot::NEXT_IMEI,'product_id'=>Pilot::PRODUCT,'qty'=>1,'unit_price'=>'1.00','amounts'=>'1.00'];
$head=['third_no'=>'BP-DUP-FIXTURE','warehouse_id'=>Pilot::WAREHOUSE,'supplier_id'=>Pilot::SUPPLIER,'cash_term'=>0,
    'is_auto_generate_supplier'=>0,'business_date'=>$date,'sales_id'=>Pilot::BUYER,'sales_name'=>Pilot::BUYER_NAME,'remark'=>Pilot::REMARK];
$request=['schema'=>'boyuan.purchase.v154','stage'=>'frozen','third_no'=>$head['third_no'],'original_event_id'=>'TEST-EVENT-99',
    'pilot_bridge_id'=>7,'pilot_authorized_uid'=>1,'pilot_operation_id'=>Pilot::NEXT_OP,'pilot_pricer_ref'=>'staff:1',
    'selection'=>['company_id'=>Pilot::COMPANY,'buyer_id'=>Pilot::BUYER],'biz'=>['head'=>$head,'product_details'=>['add_details'=>[$row]]],'lines'=>[$line]];
$identity=['bill_type'=>'PUR','company_id'=>Pilot::COMPANY,'warehouse_id'=>Pilot::WAREHOUSE,'product_id'=>Pilot::PRODUCT,
    'supplier_id'=>Pilot::SUPPLIER,'big_gsdm'=>Pilot::ACCOUNT,'is_effective'=>1,'seq_no'=>1,'qty'=>1,'unit_price'=>1];
// These are rows ALREADY IN BOYUAN, for a different IMEI than the requested purchase.
$serial=$identity+['id'=>'aa000000-0000-4000-8000-000000000019','detail_id'=>$lineId,'company_code'=>'SHOP1',
    'imei'=>'990000000033301','imei1'=>'990000000033301','amount'=>1];
$detail=$identity+['id'=>$lineId,'bill_id'=>$no,'business_date'=>$date,'trans_type'=>'购','amounts'=>1];
$base=['day'=>[$serial],'details'=>[$detail],'target'=>[$serial],'inventory'=>[]];
$profile=['endpoint'=>ProductionVoucher::ENDPOINT,'gateway_path'=>'/gateway.do','account_id'=>Pilot::ACCOUNT,'app_id'=>'FAKE'];
$allow=['day','details','target','inventory']; $checked=0; $fakeCalls=0; $failures=[];
/**
 * For a refusal, assert any extra services were never visited (especially not inventory after ambiguous data).
 * HTTP mocks must independently check filter values, not just hand over the expected result by call index.
 */
$case=static function(string $label,array $data,array $req,?string $expectReason,array $lastAllowed,array $profile,string $mode='production')
    use($date,$no,&$checked,&$fakeCalls,&$failures):void {
    $ops=[]; $transportErrors=[]; $reason=null; $effect=null;
    $api=new OpenApi($profile,'FAKE-SECRET',static function(string $path,array $form)use($data,$date,$no,$req,&$ops,&$transportErrors,&$fakeCalls):array {
        ++$fakeCalls; $service=$form['service'];
        $kind=match($service) {
            'boyuan.biz.purchase.imeidetails.query'=>isset($form['bill_id'])?'target':'day',
            'boyuan.biz.purchases.query'=>'details',
            'boyuan.biz.inventory.imeidetails.query'=>'inventory',
            default=>'FORBIDDEN:'.$service,
        };
        $ops[]=$kind;
        $valid=match($kind) {
            'day'=>($form['bill_type']??null)==='PUR' && ($form['business_date_start']??null)===$date && ($form['business_date_end']??null)===$date,
            'details'=>($form['trans_type']??null)==='购' && ($form['business_date_start']??null)===$date && ($form['business_date_end']??null)===$date,
            'target'=>($form['bill_id']??null)===$no && ($form['bill_type']??null)==='PUR',
            'inventory'=>($form['imei']??null)===$req['lines'][0]['imei'] && ($form['query_date']??null)===$date,
            default=>false,
        };
        if(!$valid || $path!=='/gateway.do' || !isset($data[$kind])) {
            $transportErrors[]=$kind;
            throw new RuntimeException('forbidden or incorrectly scoped fake request');
        }
        $rows=$data[$kind]; return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>count($rows),'result_list'=>$rows]];
    },$mode);
    try {(new PurchaseGateway($api))->checkExistingSerial($req);} catch(Problem $e) {$reason=$e->reason; $effect=$e->effect;}
    $prefix=array_slice($lastAllowed,0,count($ops));
    if($transportErrors || (!$ops && $lastAllowed!==[]) || $ops!==$prefix || ($expectReason===null && $ops!==$lastAllowed) ||
        ($expectReason===null ? $reason!==null : ($reason===null || $effect!=='none' || ($expectReason!=='reject' && $reason!==$expectReason)))) {
        $failures[]=$label.' reason='.($reason??'none').' effect='.($effect??'none').' reads='.implode(',',$ops).' invalid='.implode(',',$transportErrors);
        return;
    }
    ++$checked;
};
$case('date and bill omitted; foreign key plus independent targeted read complete the identity',$base,$request,null,$allow,$profile);
$dateOnly=$base; foreach(['day','target'] as $k) $dateOnly[$k][0]['bill_id']=$no;
$case('only date omitted; matching optional bill is allowed',$dateOnly,$request,null,$allow,$profile);
$billOnly=$base; foreach(['day','target'] as $k) $billOnly[$k][0]['business_date']=$date;
$case('only bill omitted; supplied date is still respected',$billOnly,$request,null,$allow,$profile);
$reconcile=['day','details','target'];
foreach([
    ['day','business_date','1990-01-01'],['details','business_date','1990-01-01'],['target','business_date','1990-01-01'],
    ['day','bill_id','WRONG-PUR'],['target','bill_id','WRONG-PUR'],['target','bill_type','PURR'],
    ['day','company_id','wrong-company'],['day','warehouse_id','wrong-warehouse'],['details','company_id','wrong-company'],
    ['details','warehouse_id','wrong-warehouse'],['target','company_id','wrong-company'],['target','warehouse_id','wrong-warehouse'],
    ['target','product_id','wrong-product'],['target','detail_id','cross-bill-line'],['target','imei','masked-3301'],
] as [$kind,$field,$value]) {
    $d=$base; $d[$kind][0][$field]=$value;
    $case($kind.'.'.$field.' conflict',$d,$request,'reject',$reconcile,$profile);
}
$d=$base; unset($d['details'][0]['business_date']);
$case('without an independent date there is no fallback',$d,$request,'reject',$reconcile,$profile);
$d=$base; $d['target'][0]['imei']=$d['target'][0]['imei1']='990000000033302';
$case('different IMEI for same line in targeted reply',$d,$request,'reject',$reconcile,$profile);
$d=$base; $d['target']=[];
$case('targeted bill missing line',$d,$request,'reject',$reconcile,$profile);
$d=$base; $d['target'][]=array_replace($serial,['detail_id'=>'cross-bill-line']);
$case('unexpected extra serial in bill-specific lookup',$d,$request,'reject',$reconcile,$profile);
$d=$base; $d['details'][]=array_replace($detail,['id'=>'different-purchase-line']);
$case('dated detail absent from day serial read',$d,$request,'reject',$reconcile,$profile);
$empty=$base; $empty['day']=[]; $empty['target']=[];
$case('empty daily serials but independent daily purchases are not empty',$empty,$request,'reject',['day','details'],$profile);
$empty['details']=[];
$case('both independent daily feeds and inventory empty',$empty,$request,null,['day','details','inventory'],$profile);
$d=$base; $d['day'][0]['imei']=$d['day'][0]['imei1']=Pilot::NEXT_IMEI;
$case('positive on same-day query blocks without inventory',$d,$request,'ORIGINAL_PURCHASE_REMOTE_EXISTS',['day'],$profile);
$d=$base; $d['day'][0]['imei2']=Pilot::NEXT_IMEI;
$case('target also forbidden as secondary IMEI on another purchase',$d,$request,'ORIGINAL_PURCHASE_REMOTE_EXISTS',['day'],$profile);
$d=$base; $d['inventory'][]=['imei'=>Pilot::NEXT_IMEI];
$case('reconciliation cannot waive positive inventory',$d,$request,'ORIGINAL_PURCHASE_REMOTE_EXISTS',$allow,$profile);
$r3=$request;$r3['pilot_operation_id']=Pilot::THIRD_OP;$r3['pilot_recycle_no']=Pilot::THIRD_RECYCLE_NO;
$r3['lines'][0]['imei']=$r3['biz']['product_details']['add_details'][0]['imei']=Pilot::THIRD_IMEI;
$case('903 original keeps all evidence checks',$base,$r3,null,$allow,$profile);
$case('903 rejects different original before any reads',$base,array_replace($r3,['pilot_recycle_no'=>'OTHER']),'PILOT_SCOPE',[],$profile);
$case('903 cannot inherit 902 authority',$base,array_replace($r3,['pilot_operation_id'=>Pilot::NEXT_OP]),'PILOT_SCOPE',[],$profile);
$bad3=$base;$bad3['day'][0]['imei']=$bad3['day'][0]['imei1']=Pilot::THIRD_IMEI;
$case('903 duplicate refusal',$bad3,$r3,'ORIGINAL_PURCHASE_REMOTE_EXISTS',['day'],$profile);
$bad3=$base;$bad3['details']=[];$case('903 incomplete manifests never clear preflight',$bad3,$r3,'reject',['day','details'],$profile);
$old=$request; $old['pilot_operation_id']=Pilot::OP; unset($old['pilot_pricer_ref']);
$old['lines'][0]['imei']=$old['biz']['product_details']['add_details'][0]['imei']=Pilot::IMEI;
$case('901 legacy route refuses missing date without extra queries',$base,$old,'reject',['day'],$profile);
$oldData=$base; $oldData['day'][0]['business_date']=$date;
$case('901 complete original shape retains legacy route',$oldData,$old,null,['day','inventory'],$profile);
$ordinary=$request; unset($ordinary['pilot_operation_id'],$ordinary['pilot_bridge_id'],$ordinary['pilot_authorized_uid'],$ordinary['pilot_pricer_ref']);
$case('ordinary production route cannot inherit 902 fallback',$base,$ordinary,'reject',['day'],$profile);
foreach(['stage'=>'draft','pilot_authorized_uid'=>0,'pilot_bridge_id'=>'7','pilot_pricer_ref'=>'staff:999','pilot_operation_id'=>Pilot::OP] as $k=>$v)
    $case('new fallback unavailable to incorrect '.$k,$base,array_replace($request,[$k=>$v]),'reject',['day'],$profile);
$case('UAT cannot inherit formal account fallback',$base,$request,'reject',['day'],array_replace($profile,['endpoint'=>'https://open-api-uat.139erp.com/api','account_id'=>'80004']),'test');
if(class_exists('think\\facade\\Db',false) || class_exists('addon\\hsx_boyuan\\app\\gateway\\HttpTransport',false)) throw new RuntimeException('unexpected live adapter loaded');
if($failures) { fwrite(STDERR,implode("\n",$failures)."\n$checked passed; ".count($failures)." failed\n"); exit(1); }
echo "$checked duplicate-preflight scenarios passed; $fakeCalls fake reads; 0 writes, live calls or database connections.\n";
