<?php
declare(strict_types=1);

use addon\hsx_boyuan\app\service\{PurchaseSyncService,RecyclePurchaseSku};
use addon\hsx_boyuan\app\support\Contract;
use think\facade\Db;

// Same real purchase service and isolated in-memory store as the procurement regression suite.
require __DIR__.'/../app/service/RecyclePurchaseSku.php';
require __DIR__.'/purchase-sync.php';
function event($name,$payload) {
    if ($name!=='HsxErpSourceCatalogRequested') throw new LogicException('Unexpected event');
    $provider=new \addon\hsx_recycle\app\listener\erp\ErpSourceCatalogRequested();
    return [$provider->handle($payload)];
}
require __DIR__.'/../../hsx_recycle/app/service/core/recycle_order/RecycleErpSourceCatalog.php';
require __DIR__.'/../../hsx_recycle/app/listener/erp/ErpSourceCatalogRequested.php';

$baseline=$assertions; [$event,$snapshot]=seed(); $key=str_repeat('a',32);
$service=new PurchaseSyncService();
$extra=['name'=>'iPhone 14 Pro','brand_id'=>'APPLE','memory'=>'256GB','color'=>'黑色','version_type_id'=>'CN','version_type_name'=>'国行','imei_count'=>1,'imei_len'=>15];
$setProduct=fn($id,$attrs)=>\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id',$id)->update(['extra_json'=>Contract::json($attrs)]);
$setProduct('P1',$extra); $setProduct('P2',array_replace($extra,['name'=>'iPhone 14 Pro 学校版']));
$catalog=['source_plugin'=>'hsx_recycle','owner_site_id'=>0,'namespace'=>'recycle_device_model_dict:0','model_id'=>55,'model_name'=>'苹果/iPhone 14 Pro','brand_name'=>'苹果'];
$head=['source_plugin'=>'hsx_recycle','origin_plugin'=>'hsx_recycle','origin_type'=>'hsx_recycle.recycle_purchase'];
Db::name('erp_purchase_order')->where('id',1)->update($head);
foreach ([55,56] as $leaf) Db::name('recycle_device_model_dict')->insert(['id'=>$leaf,'site_id'=>0,'pid'=>0,'status'=>1,'node_name'=>'iPhone 14 Pro','model_full_name'=>'苹果/iPhone 14 Pro']);
$actual=[];
foreach ([1,2] as $id) {
    $device=10+$id;
    Db::name('recycle_device')->insert(['site_id'=>1,'id'=>$device,'category_id'=>55]);
    $meta=['capacity'=>'256G','color'=>'黑','source_plugin'=>'hsx_recycle','source_catalog'=>$catalog,'source_device_id'=>$device];
    $qc=['source_plugin'=>'hsx_recycle','source_device_id'=>$device,'report'=>['result_items'=>[['field_name'=>'销售版本','labels'=>['国行']]]]];
    $variant=['model'=>'iPhone 14 Pro','spec'=>'256G 黑','color'=>'黑'];
    $r=['spec_json'=>Contract::json($meta),'qc_report'=>Contract::json($qc)]+$variant;
    Db::name('erp_asset')->where('id',$id)->update($r+['source_plugin'=>'hsx_recycle','purchase_order_id'=>1,'purchase_item_id'=>$id]);
    Db::name('erp_purchase_item')->where('id',$id)->update($r);
    $part=$event; $part['event_id']='recycle-device-'.$id;
    $line=$snapshot['lines'][$id-1]; $line['product_ref']='model:'.hash('sha256',json_encode($variant,JSON_UNESCAPED_UNICODE));
    $part['payload']['lines']=[$line]; $part['payload']['batch_ref']=hash('sha256',$line['source_line_id']); $actual[$id]=[$part,$meta,$qc];
}
$get=fn()=>($service->view(1,2)['selection']['lines'][0]['product_id']);
$first=$actual[1][0]; $second=$actual[2][0]; $meta=$actual[2][1]; $qc=$actual[2][2];
$setMeta=function($val) {foreach (['erp_purchase_item','erp_asset'] as $table) Db::name($table)->where('id',2)->update(['spec_json'=>Contract::json($val)]);};
$setQc=fn($val)=>Db::name('erp_purchase_item')->where('id',2)->update(['qc_report'=>Contract::json($val)]);
$map=fn()=>Db::name('hsx_boyuan_mapping')->where('kind','product')->where('connection_key',$key)->where('local_ref','like','recycle-sku:%')->find();
Db::name('hsx_boyuan_mapping')->insert(['kind'=>'product','site_id'=>1,'connection_key'=>$key,'local_ref'=>$first['payload']['lines'][0]['product_ref'],'external_id'=>'P2']);
$v=newTask($service,$first); check($v['selection']['lines'][0]['product_id']==='','first recycle row ignores even an existing ERP name hash');
$chosen=selection($first['payload']);
$v=$service->saveDraft(1,1,7,input($v,$chosen));
check($map()===null,'one-off choice never teaches another order');
$v=$service->submit(1,1,7,array_replace(input($v,$chosen),['remember'=>true]));
check($map()['external_id']==='P1','explicit confirmation stores new source SKU mapping');
$frozen=Db::name('hsx_boyuan_task')->where('id',1)->value('request_json');
$service->capture(1,$second); check($get()==='P1','second serial, same public leaf and exact variant, reuses the confirmed SKU');
$setProduct('P2',$extra); check($get()==='','equally plausible current product family with the same version label needs manual choice');
$setProduct('P2',array_replace($extra,['color'=>'蓝色'])); check($get()==='P1','clearly different official SKU does not block a known exact variant');
$setProduct('P2',array_replace($extra,['name'=>'iPhone 14 Pro 学校版']));
foreach ([['color','蓝'],['capacity','512GB'],['color','123'],['capacity','256G 或 512G']] as [$field,$val]) {
    $m=$meta; $m[$field]=$val; $setMeta($m); check($get()==='','different or ambiguous '.$field.' is never guessed');
}
$setMeta($meta); $q=$qc; $q['report']['result_items'][0]['labels']=['美版']; $setQc($q);
check($get()==='','different sales version never defaults despite same model, memory and color');
$q['report']['result_items']=[['field_name'=>'系统版本','labels'=>['iOS 18']]]; $setQc($q);
check($get()==='','OS version is not SKU identity');
$m=$meta; $m['source_catalog']['model_id']=56; $setMeta($m); $setQc($qc);
Db::name('recycle_device')->where('id',12)->update(['category_id'=>56]); check($get()==='','different leaf with identical display name does not inherit');
Db::name('recycle_device')->where('id',12)->update(['category_id'=>55]); $setMeta($meta);
Db::name('recycle_device')->where('id',12)->update(['site_id'=>2]); check($get()==='','other site source device cannot resolve');
Db::name('recycle_device')->where('id',12)->update(['site_id'=>1]);
Db::name('recycle_device_model_dict')->where('id',56)->update(['pid'=>55]); check($get()==='','former leaf now containing children no longer defaults');
Db::name('recycle_device_model_dict')->where('id',56)->update(['pid'=>0]);
$old=$map(); Db::name('hsx_boyuan_mapping')->where('id',$old['id'])->update(['connection_key'=>'other']);
check($get()==='','no reuse across different Boyuan accounts'); Db::name('hsx_boyuan_mapping')->where('id',$old['id'])->update(['connection_key'=>$key]);
\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->update(['extra_json'=>Contract::json(array_replace($extra,['version_type_id'=>'ANOTHER']))]);
check($get()==='','same external id with changed SKU is not preselected');
\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->update(['extra_json'=>Contract::json($extra),'enabled'=>0]);
check($get()==='','disabled product requires manual confirmation');
$chosen2=selection($second['payload']); $chosen2['lines'][0]['product_id']='P2';
$v2=$service->saveDraft(1,2,7,input($service->view(1,2),$chosen2));
check($map()['external_id']==='P1','one-off replacement does not change remembered SKU');
$service->saveDraft(1,2,7,array_replace(input($v2,$chosen2),['remember'=>true]));
check($map()['external_id']==='P2' && $map()['revision']===2,'explicitly remember replacement for disabled SKU');
check(Db::name('hsx_boyuan_task')->where('id',1)->value('request_json')===$frozen,'frozen purchase remains unchanged by replacement');
\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->update(['enabled'=>1]);
rejects(fn()=>$service->saveDraft(1,2,7,array_replace(input($service->view(1,2),$chosen),['remember'=>true])),'PURCHASE_SELECTION_INVALID');
$conflict=$chosen2; $conflict['lines'][0]['product_id']='P1';
rejects(fn()=>$service->saveDraft(1,2,7,array_replace(input($service->view(1,2),$conflict),['remember'=>true])),'MAPPING_CONFLICT');
$sourceHead=Db::name('erp_purchase_order')->where('id',1)->find(); $sourceLine=$second['payload']['lines'][0];
$keyFor=fn($line)=>RecyclePurchaseSku::identities(1,$sourceHead,[$line])[$line['source_line_id']]['ref']??null;
$original=$keyFor($sourceLine); check($original===$map()['local_ref'],'actual variant matches the stored approval');
foreach ([['512G','黑'],['256G','蓝']] as [$cap,$colour]) {
    $v=['model'=>'iPhone 14 Pro','spec'=>$cap.' '.$colour,'color'=>$colour];
    $setMeta(array_replace($meta,['capacity'=>$cap,'color'=>$colour]));
    foreach (['erp_purchase_item','erp_asset'] as $table) Db::name($table)->where('id',2)->update($v);
    $line=array_replace($sourceLine,['product_ref'=>'model:'.hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE))]);
    check($keyFor($line)!==null && $keyFor($line)!==$original,'another internally consistent physical variant obtains a distinct identity');
}
$setMeta($meta);
foreach (['erp_purchase_item','erp_asset'] as $table) Db::name($table)->where('id',2)->update(['spec'=>'256G 黑','color'=>'黑']);
$q=$qc; $q['report']['result_items']=[]; $q['device_readings']['local']['raw']=['schema_version'=>'hsx.device.snapshot.v1','platform'=>'ios',
    'identity'=>['imei'=>$sourceLine['imei']],'hardware'=>['model_number'=>'MQ123','region_code'=>'CH/A','model_number_full'=>'MQ123CH/A']];
$setQc($q); $ch=$keyFor($sourceLine); check(is_string($ch) && $ch!==$original,'a verified complete physical market SKU is independent from an inspection version label');
$q['device_readings']['local']['raw']['hardware']=['model_number'=>'MQ123','region_code'=>'LL/A','model_number_full'=>'MQ123LL/A'];
$setQc($q); check($keyFor($sourceLine)!==null && $keyFor($sourceLine)!==$ch,'different physical market suffix cannot alias');
$q['device_readings']['local']['raw']['identity']['imei']='000000000000099'; $setQc($q);
check($keyFor($sourceLine)===null,'a different serial cannot provide hardware version evidence');
echo 'PASS '.($assertions-$baseline)." recycle SKU checks; in-memory only; no network or account changes.\n";
