<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit("Disable network functions before testing\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,OpeningMasterService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_erp\app\service\admin\{ErpGoodsCatalogService,ErpWarehouseService};
use think\facade\Db;
$pdo=null; $schema=''; $failed=false; $checks=0;
function checkMaster(bool $ok,string $label):void { global $checks; if(!$ok) throw new RuntimeException($label); ++$checks; }
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install();
    $key=str_repeat('a',32); $run=str_repeat('b',32); $service=new OpeningMasterService();
    $cfg=new ConfigService();$profile=$cfg->get($site,false);$profile['mode']='test';
    $profile['profiles']['test']=array_replace($profile['profiles']['test'],['connection_key'=>$key,'account_id'=>'ISOLATED','protocol'=>'openapi_v153','probe_at'=>time()]);
    $cfg->persist($site,$profile);
    Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'mode'=>'test','connection_key'=>$key,'resource'=>'warehouses','run_key'=>$run,'scope_json'=>'{}','status'=>'completed']);
    $seed=function($resource,$id,$name,$extra=[],$enabled=1,$station=null,$connection=null) use($site,$key,$run) {
        return \addon\hsx_boyuan\app\support\CatalogStore::query($resource)->insertGetId(['site_id'=>$station??$site,'connection_key'=>$connection??$key,'external_id'=>$id,'name'=>$name,'code'=>$id,
            'extra_json'=>Contract::json($extra),'enabled'=>$enabled,'seen_run'=>$run,'update_at'=>time()]);
    };
    $seed('products','P1','隔离手机 256G',['category_name'=>'手机/二手','brand_name'=>'测试品牌','memory'=>'256G','color'=>'黑','imei_count'=>1,'imei_len'=>15]);
    $seed('products','P2','缺分类');
    $seed('products','P3','停用手机',['category_name'=>'手机'],0);
    $seed('products','P4','重名手机',['category_name'=>'手机']);$seed('products','P5','重名手机',['category_name'=>'手机']);
    $seed('warehouses','W1','授权仓');
    $seed('parties','A1','省内供应渠道',['is_cust'=>1,'is_supplier'=>1]);
    $seed('parties','A2','缺角色',[]); $seed('parties','A3','本站重名客户',['is_cust'=>1]);
    Db::name('erp_party')->insert(['site_id'=>$site,'party_name'=>'本站重名客户','party_no'=>'BASE','role_flags'=>'sale_customer']);
    $seed('warehouses','WOTHER','其他站点仓',[],1,$site+1);
    $seed('warehouses','WD','其他连接仓',[],1,$site,str_repeat('c',32));
    $read=fn($r)=>$service->page($site,1,$key,$r,$run,0,false);
    $write=fn($r)=>Db::transaction(fn()=>$service->page($site,1,$key,$r,$run,0,true));
    $products=$read('products'); $parties=$read('parties'); $warehouses=$read('warehouses');
    checkMaster(array_column($products['rows'],'action')===['create','conflict','skip','conflict','conflict'],'category required, disabled excluded and duplicate source names not merged');
    checkMaster(array_column($parties['rows'],'action')===['create','skip','conflict'],'only authoritative roles imported, same-name target never merged');
    checkMaster(array_column($warehouses['rows'],'name')===['授权仓'],'tenant and connection isolation on preview');
    checkMaster($service->page($site,22222,$key,'warehouses',$run,0,false)['rows'][0]['action']==='conflict','a nonemployee cannot silently become site warehouse manager');
    checkMaster(str_contains($warehouses['rows'][0]['reason'],'本站默认库位'),'preview describes that this is a local default location');
    checkMaster(Db::name('erp_site_catalog_product')->count()===0 && Db::name('erp_warehouse')->count()===0 && Db::name('erp_warehouse_location')->count()===0 && Db::name('erp_party')->count()===1,'preview does not create masters or locations');
    $stopped=false;try {$service->page($site,1,$key,'warehouses',$run,0,true);} catch(Problem $err){$stopped=$err->reason==='OPENING_TRANSACTION';}
    checkMaster($stopped,'no writes outside guarded caller transaction');
    try {Db::transaction(function() use($service,$site,$key,$run){$service->page($site,1,$key,'warehouses',$run,0,true);throw new RuntimeException('ROLLBACK_FIXTURE');});}
    catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK_FIXTURE') throw $e;}
    checkMaster(Db::name('erp_warehouse')->count()===0 && Db::name('erp_warehouse_location')->count()===0 && Db::name('hsx_boyuan_mapping')->count()===0,'rollback includes warehouse, its default location and the binding');
    // Real database rejection between the two inserts. This is a random disposable DB from boyuanBoot(), not a site DB.
    $locTable=Db::name('erp_warehouse_location')->getTable();checkMaster(is_string($locTable)&&preg_match('/^[A-Za-z0-9_]+$/D',$locTable)===1 && preg_match('/^hsx_boyuan_test_[0-9a-f]{16}$/D',$schema)===1,'safe test-only fixture');
    Db::execute("CREATE TRIGGER boyuan_test_reject_default BEFORE INSERT ON `".$locTable."` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ISOLATED_LOCATION_REFUSED'");
    $refused=false;
    try{$write('warehouses');}catch(Throwable $err){$refused=str_contains($err->getMessage(),'ISOLATED_LOCATION_REFUSED');if(!$refused)throw $err;}
    finally{Db::execute('DROP TRIGGER boyuan_test_reject_default');}
    checkMaster($refused && Db::name('erp_warehouse')->count()===0 && Db::name('erp_warehouse_location')->count()===0 && Db::name('hsx_boyuan_mapping')->count()===0,'location rejection rolls back the parent warehouse and does not leave a remote binding');
    foreach(['products','warehouses','parties'] as $r) {
        $p=$read($r);checkMaster($write($r)===$p,'confirmed decisions equal preview: '.$r);
        checkMaster($read($r)['rows'][0]['action']==='existing','repeat recognizes durable identity: '.$r);
        $write($r);
    }
    $snapshot=(new ErpGoodsCatalogService())->productSnapshot((int)Db::name('erp_site_catalog_product')->value('site_product_id'));
    checkMaster($snapshot['product_name']==='隔离手机 256G' && $snapshot['category_path']==='手机/二手' && Db::name('erp_catalog_product_master')->count()===1,'ERP consumer resolves enabled site product');
    $catalogId=(int)$snapshot['catalog_product_id'];
    request()->siteId($site+1);
    $catalog=new ErpGoodsCatalogService(); $denied=false;
    try { $catalog->productSnapshot($catalogId); } catch(\core\exception\AdminException $e) { $denied=true; }
    checkMaster($denied && $catalog->productSnapshot($catalogId,$site)['catalog_product_id']===$catalogId,'background business uses the explicit site, no cross-site request fallback');
    request()->siteId($site);
    $line=function(int $id,string $model='隔离手机 256G',string $spec='256G',string $color='黑'):array{
        return ['source_line_id'=>'purchase_line:1','device_ref'=>'asset:1','imei'=>'990000000000001','catalog_product_id'=>$id,'warehouse_ref'=>'warehouse:1',
            'product_ref'=>'model:'.hash('sha256',json_encode(compact('model','spec','color'),JSON_UNESCAPED_UNICODE)),
            'attributes'=>[['key'=>'spec','value_text'=>$spec],['key'=>'color','value_text'=>$color]]];
    };
    $suggest=fn($l,$s=null,$k=null)=>OpeningMasterService::purchaseProductDefaults($s??$site,$k??$key,[$l]);
    checkMaster($suggest($line($catalogId))===[],'no prefill until official product reference completed');
    Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'mode'=>'test','connection_key'=>$key,'resource'=>'products','scope_json'=>'{}','run_key'=>$run,'status'=>'completed']);
    checkMaster($suggest($line($catalogId))===['purchase_line:1'=>'P1'],'exact local product identity recovers original archived product');
    checkMaster(!$suggest($line(0)) && !$suggest($line($catalogId),$site+1) && !$suggest($line($catalogId),$site,str_repeat('c',32)),'no text matching, cross-site or changed account');
    checkMaster(!$suggest($line($catalogId,'自定义机型')) && !$suggest($line($catalogId,'隔离手机 256G','512G')) && !$suggest($line($catalogId,'隔离手机 256G','256G','紫')),'custom model, memory or color remains for manual selection');
    $meta=['specs'=>['capacity'=>['value'=>'256GB','title_part'=>true]],'grade'=>['value'=>'99新'],'color'=>'黑色','battery'=>'95','warranty'=>0,'title_rule'=>['spec_in_title'=>1,'grade_in_title'=>1,'separator'=>' ']];
    $put=function($meta,$model,$spec,$color='黑色') use($site,$catalogId,$line) {
        $row=['site_id'=>$site,'asset_id'=>1,'id'=>1,'imei'=>'990000000000001','catalog_product_id'=>$catalogId,'model'=>$model,'spec'=>$spec,'color'=>$color,'spec_json'=>Contract::json($meta)];
        $exists=Db::name('erp_purchase_item')->where(['site_id'=>$site,'id'=>1]);
        if ($exists->count()) $exists->update($row); else Db::name('erp_purchase_item')->insert($row);
        return $line($catalogId,$model,$spec,$color);
    };
    $model='隔离手机 256G 256GB 99新';
    $pc=$put($meta,$model,'256GB 99新 黑色 电池95%');
    checkMaster($suggest($pc)===['purchase_line:1'=>'P1'],'a PC purchase with matching structured storage and display-only grade, color, battery still uses its opened SKU');
    checkMaster(!$suggest(array_replace($pc,['device_ref'=>'asset:2'])) && !$suggest(array_replace($pc,['imei'=>'990000000000002'])),'another asset or serial cannot lend its structured data');
    checkMaster($suggest($put($meta,$model,'256GB 99新'))===['purchase_line:1'=>'P1'],'mobile native title and compact spec also match');
    $gradeOnly=$meta;$gradeOnly['specs']=[];$gradeOnly['battery']='';$gradeOnly['title_rule']['grade_in_title']=0;
    checkMaster($suggest($put($gradeOnly,'隔离手机 256G','99新 黑色'))===['purchase_line:1'=>'P1'],'catalog ID attests SKU without inventing a separately unselected storage');
    checkMaster(!$suggest($put($gradeOnly,'隔离手机 256G','99新 黑色 512G')),'manual spec tail is never discarded as operational data');
    $wrong=$meta;$wrong['specs']['capacity']['value']='512G';
    checkMaster(!$suggest($put($wrong,'隔离手机 256G 512G 99新','512G 99新 黑色 电池95%')),'real storage contradiction remains manual');
    checkMaster(!$suggest($put($meta,'用户手改机型','256GB 99新')),'manual model cannot borrow the catalog identity');
    $wrong=$gradeOnly;$wrong['color']='紫色';
    checkMaster(!$suggest($put($wrong,'隔离手机 256G','99新 紫色','紫色')),'contradictory structured color remains manual');
    $wrong=$meta;$wrong['specs']['channel']=['value'=>'运营商定制版','title_part'=>false];
    checkMaster(!$suggest($put($wrong,$model,'256GB 运营商定制版 99新 黑色 电池95%')),'an additional unmapped SKU dimension remains manual');
    $defaults=new ReflectionMethod(addon\hsx_boyuan\app\service\PurchaseSyncService::class,'defaults'); $defaults->setAccessible(true);
    $selection=fn()=>$defaults->invoke(new addon\hsx_boyuan\app\service\PurchaseSyncService(),$site,$key,['source_document_id'=>'purchase:1','lines'=>[$line($catalogId)]]);
    checkMaster($selection()['lines'][0]['product_id']==='P1','purchase draft actually consumes imported identity');
    $seed('products','MANUAL','手工选择 SKU',['imei_count'=>1,'imei_len'=>15]);
    Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$site,'connection_key'=>$key,'kind'=>'product','local_ref'=>$line($catalogId)['product_ref'],'external_id'=>'MANUAL','extra_json'=>'{}','update_at'=>time()]);
    checkMaster($selection()['lines'][0]['product_id']==='MANUAL','previous specific manual product selection wins');
    \addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->update(['enabled'=>0]);
    checkMaster(!$suggest($line($catalogId)),'disabled archive cannot become a default');
    \addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->update(['enabled'=>1]);
    $warehouse=Db::name('erp_warehouse')->where('site_id',$site)->find();
    checkMaster((int)$warehouse['manager_uid']===1 && $warehouse['warehouse_type']==='exception' && (int)$warehouse['allow_transfer']===0 && (int)$warehouse['allow_direct_sale']===0 && (int)$warehouse['is_default']===0,'new warehouse has assigned manager and restricted operations');
    $options=ErpWarehouseService::forSite($site,1)->getOptions();$locations=$options[0]['locations'];
    checkMaster(count($options)===1 && count($locations)===1 && Db::name('erp_warehouse_location')->count()===1 && $locations[0]['location_code']==='DEFAULT' && $locations[0]['location_name']==='默认库位'
        && (int)$locations[0]['is_default']===1 && (int)$locations[0]['warehouse_id']===(int)$warehouse['id'],'ERP selects the one active local default location, also after replay');
    checkMaster(Db::name('hsx_boyuan_mapping')->where('kind','location')->count()===0,'local default is not a forged remote location');
    // Model old warehouses in this disposable schema only. Subsequent confirmed opening never updates or repairs them.
    $existingLoc=fn()=>Db::name('erp_warehouse_location')->where('site_id',$site)->where('id',$locations[0]['id']);
    $existingLoc()->update(['location_name'=>'原有命名']);
    checkMaster($write('warehouses')['rows'][0]['action']==='existing' && $existingLoc()->value('location_name')==='原有命名','repeat preserves existing custom location');
    $existingLoc()->delete();
    checkMaster($write('warehouses')['rows'][0]['action']==='existing' && Db::name('erp_warehouse_location')->count()===0,'no automatic backfill for a preexisting warehouse with no location');
    $seed('warehouses','W2','新仓甲');$seed('warehouses','W3','新仓乙');
    $write('warehouses');$write('warehouses');
    $locationsByWarehouse=Db::name('erp_warehouse_location')->where(['site_id'=>$site,'is_default'=>1,'status'=>1,'location_code'=>'DEFAULT'])->column('warehouse_id');
    checkMaster(count($locationsByWarehouse)===2 && count(array_unique($locationsByWarehouse))===2 && !in_array((int)$warehouse['id'],array_map('intval',$locationsByWarehouse),true),'each newly synced warehouse has exactly its own default; replay neither duplicates nor backfills');
    $ids=Db::name('hsx_boyuan_mapping')->where('external_id','A1')->column('local_ref');
    checkMaster(count($ids)===2 && count(array_unique($ids))===1 && Db::name('erp_party')->count()===2,'one ERP party, both authoritative roles and exact mappings');
    checkMaster(Db::name('erp_party_member')->count()===0 && Db::name('member')->count()===0 && Db::name('erp_asset')->count()===0 && Db::name('erp_payable')->count()===0 && Db::name('erp_receivable')->count()===0,'no login, member, inventory or finance rows');
    Db::name('erp_site_catalog_product')->where('site_id',$site)->update(['product_name'=>'本站自定义型号']);
    checkMaster(!$suggest($line($catalogId,'本站自定义型号')),'renamed site catalog requires review');
    checkMaster($write('products')['rows'][0]['action']==='conflict' && Db::name('erp_site_catalog_product')->value('product_name')==='本站自定义型号','relabelled local product is reported as a conflict and not overwritten');
    Db::name('erp_site_catalog_product')->where('site_id',$site)->update(['product_name'=>'隔离手机 256G','category_path'=>'其他']);
    checkMaster($read('products')['rows'][0]['action']==='conflict','category changes are reported even when product name is unchanged');
    Db::name('erp_warehouse')->where('site_id',$site)->where('id',$warehouse['id'])->update(['warehouse_name'=>'本站自定义仓']);
    Db::name('erp_party')->where('site_id',$site)->where('party_name','省内供应渠道')->update(['party_name'=>'本地名称']);
    checkMaster($write('warehouses')['rows'][0]['action']==='conflict' && $write('parties')['rows'][0]['action']==='conflict','renamed local warehouse and party not reported as aligned and not overwritten');
    $seed('products','PL','仅提供品类编号',['category_id'=>'CAT1']);$seed('categories','CAT1','手机');
    $history=['site_id'=>$site,'mode'=>'test','connection_key'=>$key,'resource'=>'categories','scope_json'=>'{}'];
    Db::name('hsx_boyuan_reference_sync')->insert($history+['run_key'=>$run,'status'=>'completed']);
    $pos=static fn($rows)=>array_column($rows,'action','external_id')['PL'];
    checkMaster($pos($read('products')['rows'])==='create','explicit category identity resolved in latest complete run');
    Db::name('hsx_boyuan_reference_sync')->insert($history+['run_key'=>str_repeat('c',32),'status'=>'needs_review']);
    checkMaster($pos($read('products')['rows'])==='conflict','incomplete replacement category run cannot fall back to historical names');
    for($i=0;$i<55;$i++) $seed('warehouses','X'.$i,'分页仓'.$i);
    $first=$read('warehouses'); $second=$service->page($site,1,$key,'warehouses',$run,$first['next_cursor'],false);
    checkMaster(count($first['rows'])===50 && count($second['rows'])===8 && $second['next_cursor']===0 && !array_intersect(array_column($first['rows'],'id'),array_column($second['rows'],'id')),'bounded non-overlapping pages');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'external_calls'=>0,'business_database_modified'=>false])."\n";
}catch(Throwable $e){$failed=true; fwrite(STDERR,'FAIL '.get_class($e).': '.($e instanceof Problem?$e->reason:$e->getMessage()).' at '.basename($e->getFile()).':'.$e->getLine()."\n");}
finally{if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
