<?php
declare(strict_types=1);
// Isolated read-model fixtures; no DB, credentials, ERP writes or vendor requests.
if(PHP_SAPI!=='cli') exit;
foreach(['support/Problem','support/Contract','service/PurchaseSyncService','service/WholesaleSyncService','service/OriginalSaleReceipts'] as $name)
    require __DIR__.'/../app/'.$name.'.php';
use addon\hsx_boyuan\app\service\{OriginalSaleReceipts as View,PurchaseSyncService};
$count=0;
function check($actual,$expected): void {global $count; if($actual!==$expected) throw new RuntimeException('Mismatch at case '.($count+1)); ++$count;}
$item=['id'=>2,'asset_id'=>3,'imei'=>'TEST-044','status'=>'sold','origin_purchase_item_id'=>12];
$line=['source_line_id'=>'sale_line:2','device_ref'=>'asset:3','imei'=>'TEST-044','external_line_id'=>'sale-row-1'];
$snapshot=['lines'=>[$line]]; $receipt=['created'=>true,'external_no'=>'S-42','external_id'=>'sale-uuid','lines'=>[$line]];
$verification=['verified'=>true,'verification_level'=>'wholesale_records_matched','external_no'=>'S-42','external_id'=>'sale-uuid','lines'=>[$line]];
$sale=View::confirmedLines('S-42',$snapshot,$receipt,$verification);
check(count($sale),1);
foreach([['verified'=>false],['external_id'=>'other'],['external_no'=>'other'],['verification_level'=>'candidate_only'],
    ['lines'=>[array_replace($line,['imei'=>'other'])]], ['lines'=>[array_replace($line,['external_line_id'=>'other'])]], ['lines'=>[$line,$line]]] as $changes)
    check(View::confirmedLines('S-42',$snapshot,$receipt,array_replace($verification,$changes)),[]);
check(View::confirmedLines('S-42',$snapshot,array_replace($receipt,['created'=>false]),$verification),[]);
$ref='purchase_line:12'; $in=array_replace($line,['source_line_id'=>$ref,'external_line_id'=>'purchase-row']);
$binding=['task_id'=>99,'external_no'=>'P-23','external_id'=>'purchase-row','detail_json'=>json_encode($in)];
$task=['external_no'=>'P-23','request_json'=>json_encode(['schema'=>PurchaseSyncService::SCHEMA,'stage'=>'frozen','lines'=>[$in]]),
    'result_json'=>json_encode(['verified'=>true,'verification_level'=>'purchase_imei_records_matched','external_no'=>'P-23','lines'=>[$in]])];
$run=static function($item,$sale,$can,$binding,$task)use($ref){$r=View::project([$item],$sale,$can,[$ref=>$binding],[99=>$task]);return [$r[0]['purchase_no'],$r[0]['sale_no']];};
check($run($item,$sale,true,$binding,$task),['P-23','S-42']);
check($run($item,$sale,false,$binding,$task),['','S-42']);
check($run($item,$sale,true,$binding,array_replace($task,['resolution'=>'source_changed'])),['','S-42']);
check($run($item,$sale,true,array_replace($binding,['external_id'=>'other']),$task),['','S-42']);
check($run(array_replace($item,['imei'=>'OTHER']),$sale,true,$binding,$task),['','']);
check($run(array_replace($item,['status'=>'returned']),$sale,true,$binding,$task),['P-23','']);
check($run($item,array_merge($sale,$sale),true,$binding,$task),['P-23','']);
check($run($item,[],true,$binding,$task),['P-23','']);
$row=['status'=>'succeeded','tone'=>'success','external_no'=>'S-42','task_id'=>99,'verified_device_receipts'=>$sale];
$devices=View::project([$item],$sale,true,[$ref=>$binding],[99=>$task]);
check(View::displayed($row,$devices),true);
foreach([['status'=>'needs_review'],['verified_device_receipts'=>[]],['external_no'=>'other']] as $change)
    check(View::displayed(array_replace($row,$change),$devices),false);
foreach([['item_id'=>200],['imei'=>'wrong'],['asset_id'=>300],['sale_no'=>'']] as $change)
    check(View::displayed($row,[array_replace($devices[0],$change)]),false);
echo "PASS $count original sale serial evidence checks\n";
