<?php
declare(strict_types=1);
/** Production response shape, fake data: DB-free, no HTTP transport loaded, and only query services allowed. */
use addon\hsx_boyuan\app\support\{ProductionPurchasePilot as Pilot,ProductionVoucher,PurchasePilotReadback as Saved,Problem};
use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway};
foreach(['support/Problem','support/Contract','support/ReceiptFacts','support/ProductionVoucher','support/ProductionPurchasePilot','support/PurchasePilotReadback','gateway/Transport','gateway/OpenApi','gateway/PurchaseGateway'] as $f) require __DIR__.'/../app/'.$f.'.php';
$n=0;$calls=[];$check=static function(bool $ok)use(&$n){if(!$ok) throw new RuntimeException('check '.($n+1));++$n;};
$deny=static function(callable $f)use($check){try{$f();}catch(Problem $e){$check($e->effect==='unknown');return;}throw new RuntimeException('expected refusal');};
$hid='ba500000-0000-4000-8000-000000000001';$lid='ba500000-0000-4000-8000-000000000002';$no='FIXTURE-PUR-1';
$line=['source_line_id'=>'purchase_line:1','device_ref'=>'asset:1','imei'=>Pilot::IMEI,'quantity'=>'1','product_id'=>'P','supplier_id'=>'S','warehouse_id'=>'W','amount'=>'1.00'];
$row=['imei'=>Pilot::IMEI,'qty'=>1,'product_id'=>'P','unit_price'=>1,'amounts'=>1];
$head=['warehouse_id'=>'W','third_no'=>'BPfixture','business_date'=>'2026-11-21','cash_term'=>0,'is_auto_generate_supplier'=>0,'supplier_id'=>'S'];
$req=['schema'=>'boyuan.purchase.v154','original_event_id'=>'event','pilot_bridge_id'=>2,'external_id'=>$hid,'third_no'=>'BPfixture','selection'=>['company_id'=>'C'],
    'biz'=>['head'=>$head,'product_details'=>['add_details'=>[$row]]],'lines'=>[$line]];
$created=['head'=>$head+['company_id'=>'C','id'=>$hid,'bill_id'=>$no,'bill_type'=>'PUR','big_gsdm'=>'89096','amounts'=>1],
    'product_details'=>['add_details'=>[array_replace($row,['imei'=>'***1901','id'=>$lid,'head_id'=>$hid,'seq_no'=>1,'company_id'=>'C','warehouse_id'=>'W','big_gsdm'=>'89096','bill_id'=>$no])]]];
$prior=['created'=>true,'external_no'=>$no,'external_id'=>$hid,'provider_result'=>['id'=>$hid,'bill_id'=>$no,'bill_type'=>'PUR']];
$proof=Saved::receipt($req,$prior,$no,$created);
$d=['id'=>$lid,'bill_id'=>$no,'bill_type'=>'PUR','company_id'=>'C','warehouse_id'=>'W','supplier_id'=>'S','business_date'=>$head['business_date'],'trans_type'=>'购',
    'is_effective'=>1,'seq_no'=>1,'qty'=>1,'unit_price'=>1,'amounts'=>1,'product_id'=>'P'];
$serial=$d+['company_code'=>'SHOPCODE','imei'=>Pilot::IMEI,'imei1'=>Pilot::IMEI,'detail_id'=>$lid,'amount'=>1];unset($serial['seq_no'],$serial['amounts']);$serial['id']='serial-row-id';
$verify=static function($d,$s,$proof,$req,$account='89096')use(&$calls,$no){
    $fake=new OpenApi(['account_id'=>$account,'endpoint'=>ProductionVoucher::ENDPOINT,'gateway_path'=>'/gateway.do','app_id'=>'TEST'],'FIXTURE-SECRET',static function($path,$form)use($d,$s,&$calls){
        $calls[]=$form['service'];$r=match($form['service']){'boyuan.biz.purchases.query'=>$d,'boyuan.biz.purchase.imeidetails.query'=>$s,default=>throw new RuntimeException('non-read request')};
        return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>1,'result_list'=>[$r]]];
    },'production');
    return (new PurchaseGateway($fake))->verify($req,$no,$proof);
};
$v=$verify($d,$serial,$proof,$req);
$check($v['verified'] && $v['external_id']===$hid && $v['lines'][0]['external_line_id']===$lid && $v['lines'][0]['imei']===Pilot::IMEI && !$v['evidence']['document_head_id_verified'] && !$v['financial_posting_verified']);
$deny(fn()=>$verify($d,$serial,null,$req)); // No fallback from a handwritten bill number.
$deny(fn()=>$verify($d,$serial,$proof,$req,'OTHER'));
foreach(['id'=>'wrong','company_id'=>'wrong','bill_id'=>'wrong','big_gsdm'=>'OTHER','warehouse_id'=>'X','supplier_id'=>'X','product_id'=>'X','amounts'=>2,'seq_no'=>2,'head_id'=>'wrong'] as $k=>$val)
    $deny(fn()=>$verify(array_replace($d,[$k=>$val]),$serial,$proof,$req));
foreach(['detail_id'=>'serial-row-id','imei'=>'***1901','imei1'=>'123456789011901','amount'=>2,'amounts'=>2,'head_id'=>'X','company_id'=>'X','gsdm'=>'X','qty'=>2,'is_effective'=>0,'business_date'=>'2026-11-20','big_gsdm'=>'OTHER'] as $k=>$val)
    $deny(fn()=>$verify($d,array_replace($serial,[$k=>$val]),$proof,$req));
foreach(['id'=>'wrong','bill_id'=>'wrong','third_no'=>'wrong','supplier_id'=>'wrong','warehouse_id'=>'wrong','big_gsdm'=>'OTHER'] as $k=>$val) {
    $bad=$created;$bad['head'][$k]=$val;$deny(fn()=>Saved::receipt($req,$prior,$no,$bad));
}
foreach(['id'=>'not-uuid','head_id'=>'wrong','company_id'=>'wrong','product_id'=>'wrong','imei'=>'***0000'] as $k=>$val){
    $bad=$created;$bad['product_details']['add_details'][0][$k]=$val;$deny(fn()=>Saved::receipt($req,$prior,$no,$bad));
}
$req2=$req; $req2['lines'][0]['imei']=Pilot::NEXT_IMEI; $req2['biz']['product_details']['add_details'][0]['imei']=Pilot::NEXT_IMEI;
$req2['pilot_operation_id']=Pilot::NEXT_OP; $req2['biz']['head']+=['sales_id'=>Pilot::BUYER,'sales_name'=>Pilot::BUYER_NAME];
$body2=$created; $body2['product_details']['add_details'][0]['imei']='***1902';
$p2=Saved::receipt($req2,$prior,$no,$body2); $d2=$d+['sales_id'=>Pilot::BUYER,'sales_name'=>Pilot::BUYER_NAME];
$s2=array_replace($serial,['imei'=>Pilot::NEXT_IMEI,'imei1'=>Pilot::NEXT_IMEI]);
$check($verify($d2,$s2,$p2,$req2)['verified']===true);
$thin=$s2; unset($thin['bill_id'],$thin['business_date']);
$check($verify($d2,$thin,$p2,$req2)['verified']===true);
$oldThin=$serial; unset($oldThin['bill_id'],$oldThin['business_date']);
$deny(fn()=>$verify($d,$oldThin,$proof,$req));
foreach(['detail_id'=>'other','bill_id'=>'other','business_date'=>'2020-01-01','company_id'=>'other','product_id'=>'other','warehouse_id'=>'other','bill_type'=>'SAL','imei1'=>'***1902'] as $k=>$v)
    $deny(fn()=>$verify($d2,array_replace($thin,[$k=>$v]),$p2,$req2));
$deny(fn()=>$verify($d,$s2,$p2,$req2));
$deny(fn()=>$verify(array_replace($d2,['sales_id'=>'other']),$s2,$p2,$req2));
$deny(fn()=>$verify(array_replace($d2,['sales_name'=>'other']),$s2,$p2,$req2));
$deny(fn()=>$verify($d2,$serial,$p2,$req2)); $deny(fn()=>$verify($d2,$s2,$proof,$req2)); $deny(fn()=>$verify($d,$serial,$p2,$req));
$deny(fn()=>Saved::receipt(array_replace($req2,['pilot_operation_id'=>Pilot::OP]),$prior,$no,$body2));
$req3=$req2;$req3['pilot_operation_id']=Pilot::THIRD_OP;$req3['pilot_recycle_no']=Pilot::THIRD_RECYCLE_NO;
$req3['lines'][0]['imei']=$req3['biz']['product_details']['add_details'][0]['imei']=Pilot::THIRD_IMEI;
$body3=$body2;$body3['product_details']['add_details'][0]['imei']='***1903';$p3=Saved::receipt($req3,$prior,$no,$body3);
$s3=array_replace($thin,['imei'=>Pilot::THIRD_IMEI,'imei1'=>Pilot::THIRD_IMEI]);
$check($verify($d2,$s3,$p3,$req3)['verified']===true);
$deny(fn()=>Saved::receipt(array_replace($req3,['pilot_recycle_no'=>'other']),$prior,$no,$body3));
$deny(fn()=>$verify($d2,$s3,$p2,$req3));
foreach([[$d,'PURCHASE_BUYER_NOT_RETURNED'],[array_replace($d2,['sales_name'=>'other']),'PURCHASE_BUYER_MISMATCH']] as [$read,$expect]){
    try {$verify($read,$s3,$p3,$req3); throw new RuntimeException('unproved buyer');}
    catch(Problem $err){ $check($err->effect==='unknown' && $err->reason===$expect); }
}
$check(!array_diff($calls,['boyuan.biz.purchases.query','boyuan.biz.purchase.imeidetails.query']));
echo "$n pilot readback assertions passed; only fake purchase and serial queries, no writes.\n";
