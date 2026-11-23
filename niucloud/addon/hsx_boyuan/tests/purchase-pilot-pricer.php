<?php
declare(strict_types=1);
// Test transports only: PHP never boots the application and never obtains live credentials.
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\support\{Contract,Problem,ProductionPurchasePilot as Pilot};
require __DIR__.'/purchase-pilot.php';
$r['lines']=[['imei'=>Pilot::NEXT_IMEI]]; $r['selection']=['buyer_id'=>Pilot::BUYER,'company_id'=>Pilot::COMPANY];
$r['pilot_operation_id']=Pilot::NEXT_OP; $r['pilot_pricer_ref']='staff:1';
$r['biz']['head']+=['sales_id'=>Pilot::BUYER,'sales_name'=>Pilot::BUYER_NAME,'warehouse_id'=>Pilot::WAREHOUSE,'supplier_id'=>Pilot::SUPPLIER];
$r['biz']['product_details']['add_details'][0]=array_replace($r['biz']['product_details']['add_details'][0],['imei'=>Pilot::NEXT_IMEI,'product_id'=>Pilot::PRODUCT]);
$u=['id'=>Pilot::BUYER,'real_name'=>Pilot::BUYER_NAME,'company_id'=>Pilot::COMPANY,'is_effective'=>1,'is_buyer'=>1];
$reply=['total_results'=>1,'result_list'=>[$u]];
$test=static function($req,$reply,$expect,$code=null)use($p,$check){
    $seen=[]; $fake=new OpenApi($p,'TEST',static function($path,$form)use(&$seen,$reply,$check){
        $seen[]=$form['service'];
        if($form['service']==='boyuan.users.get'){
            $check($form['id']===Pilot::BUYER && $form['page_no']===1 && $form['page_size']===1 && $form['big_gsdm']===Pilot::ACCOUNT);
            if($reply===false) throw new RuntimeException('simulated lookup failure');
            return ['return_code'=>'SUCCESS','api_response'=>$reply];
        }
        if($form['service']!=='boyuan.biz.bill.purchase.generate') throw new RuntimeException('unexpected wire service');
        $biz=Contract::decode($form['biz']);
        $check($biz['head']['sales_id']===Pilot::BUYER && $biz['head']['sales_name']===Pilot::BUYER_NAME && $biz['head']['cash_term']===0 &&
            count($biz['product_details']['add_details'])===1 && $biz['product_details']['add_details'][0]['imei']===Pilot::NEXT_IMEI);
        return ['return_code'=>'SUCCESS','api_response'=>['result'=>['ok'=>true]]];
    },'production');
    try { $fake->purchasePilotCreate($req); $check($code===null); }
    catch(Problem $e) { $check($code!==null && $e->reason===$code && $e->effect==='none'); }
    $check($seen===$expect);
};
$read=['boyuan.users.get']; $test($r,$reply,[...$read,'boyuan.biz.bill.purchase.generate']);
foreach(['is_buyer'=>0,'is_effective'=>0,'real_name'=>'other','id'=>'other','company_id'=>'other'] as $k=>$v)
    $test($r,['total_results'=>1,'result_list'=>[array_replace($u,[$k=>$v])]],$read,'PILOT_BUYER_NOT_ELIGIBLE');
foreach([['total_results'=>2,'result_list'=>[$u,$u]],['result_list'=>[$u]],['total_results'=>1,'result_list'=>'bad']] as $bad)
    $test($r,$bad,$read,'PILOT_BUYER_NOT_ELIGIBLE');
$test($r,false,$read,'PILOT_BUYER_READ_UNAVAILABLE');
foreach(['pilot_operation_id'=>Pilot::OP,'pilot_pricer_ref'=>'staff:2','lines'=>[['imei'=>Pilot::IMEI]],'selection'=>['buyer_id'=>'other','company_id'=>Pilot::COMPANY]] as $k=>$v)
    $test(array_replace($r,[$k=>$v]),$reply,[],'PILOT_SCOPE');
foreach(['sales_id','sales_name','warehouse_id','supplier_id'] as $k){$b=$r;$b['biz']['head'][$k]='other';$test($b,$reply,[],'PILOT_SELECTION_CHANGED');}
$b=$r; $b['biz']['product_details']['add_details'][0]['product_id']='other'; $test($b,$reply,[],'PILOT_SELECTION_CHANGED');
$b=$r; $b['biz']['head']['cash_term']=1; $test($b,$reply,[],'PILOT_SCOPE');
echo "$n cumulative scoped assertions passed; no live network or credentials.\n";
