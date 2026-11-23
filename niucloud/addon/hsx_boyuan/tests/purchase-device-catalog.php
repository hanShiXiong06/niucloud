<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,OpeningMasterService};
use addon\hsx_boyuan\app\support\CatalogStore;
use addon\hsx_erp\app\service\admin\{ErpGoodsCatalogService,ErpPurchaseService};
use think\facade\Db;

$pdo=null; $schema=''; $n=0; $failed=false;
function checkDevice(bool $ok,string $why):void {global $n;if(!$ok) throw new RuntimeException($why);++$n;}
function refusedDevice(callable $f,string $text):void {
    try {$f();} catch(Throwable $e) {checkDevice(str_contains($e->getMessage(),$text),'expected '.$text.' / got '.$e->getMessage());return;}
    throw new RuntimeException('expected refusal: '.$text);
}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install();
    $erp=new ErpGoodsCatalogService(); $local=$erp->add(['category_path'=>'本机验收/演示','product_name'=>'历史型号']);
    $browse=fn($args=[])=>$erp->hierarchy(['scene'=>'purchase_device']+$args);
    checkDevice($browse()===['managed'=>false],'a standalone site uses the original device picker');
    $cfg=new ConfigService();$c=$cfg->get($site,false);$key=str_repeat('a',32);$run=str_repeat('b',32);
    $c['mode']='test';$c['profiles']['test']=array_replace($c['profiles']['test'],['connection_key'=>$key,'account_id'=>'ISOLATED','protocol'=>'openapi_v153','probe_at'=>time()]);
    $cfg->persist($site,$c);
    checkDevice($browse()['managed'] && $browse()['list']===[],'empty managed source never exposes old local categories');
    foreach (['products','categories'] as $kind) Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'mode'=>'test','connection_key'=>$key,'resource'=>$kind,'run_key'=>$run,'scope_json'=>'{}','status'=>'completed']);
    $seed=function($kind,$id,$name,$extra=[],$enabled=1,$station=null,$connection=null)use($site,$key,$run){
        CatalogStore::query($kind)->insert(['site_id'=>$station??$site,'connection_key'=>$connection??$key,'external_id'=>$id,'name'=>$name,'code'=>$id,
            'extra_json'=>json_encode($extra,JSON_UNESCAPED_UNICODE),'enabled'=>$enabled,'seen_run'=>$run]);
    };
    $seed('categories','C','手机'); $seed('categories','D','配件',['parent_id'=>'C']);
    $seed('categories','E','屏幕',['parent_id'=>'D']); $seed('categories','F','旗舰',['parent_id'=>'E']);
    $seed('categories','D2','配件',['parent_id'=>'C']); $seed('categories','G','停用',[],0);
    $seed('categories','O','孤儿',['parent_id'=>'missing']);$seed('categories','L','历史测试',[],1,$site+1);
    $seed('products','ONLY-CODE-123','来源设备',['category_id'=>'F','category_name'=>'旧别名','memory'=>'256GB']);
    $seed('products','P2','另一个设备',['category_id'=>'D2']); $seed('products','P3','停用商品',['category_id'=>'F'],0);
    $seed('products','P4','其他账套商品',['category_id'=>'F'],1,$site,str_repeat('c',32));
    $seed('products','P5','停用分类商品',['category_id'=>'G']);
    $root=$browse();$scope=$root['scope'];$query=fn($args)=>$browse($args+['expected_scope'=>$scope]);
    checkDevice(count($root['list'])===5 && array_column($root['list'],'category_path','id')['F']==='手机/配件/屏幕/旗舰','source parent IDs, four levels, not the paths of local products');
    $options=fn($cat,$word='')=>$query(['node_type'=>'products','category_id'=>$cat,'keyword'=>$word])['list'];
    checkDevice(count($options('C'))===2 && array_column($options('D'),'id')===['ONLY-CODE-123'],'browse includes descendants, excludes duplicate-name sibling by id, inactive and other tenant/account goods');
    checkDevice(count($options('','ONLY-CODE-123'))===1 && !$options('','历史型号'),'global SKU code search has no local fallback');
    $only=fn($id='ONLY-CODE-123',$cat='F')=>$query(['node_type'=>'selection','category_id'=>$cat,'product_id'=>$id])['choice'];
    $picked=$only();$ref=$picked['catalog_reference'];
    checkDevice($picked['site_product_id']===0 && $picked['label']==='来源设备' && Db::name('erp_site_catalog_product')->count()===1,'selection is read-only, external ID is not cast to ERP ID');
    refusedDevice(fn()=>$only('ONLY-CODE-123','D2'),'分类已变更');
    refusedDevice(fn()=>$browse(['node_type'=>'products','expected_scope'=>'another account']),'目录来源已切换');
    $prepare=fn($item)=>Db::transaction(fn()=>$erp->resolvePurchaseDevices($site,[$item])[0]);
    refusedDevice(fn()=>$prepare(['catalog_product_id'=>$local]),'重新选择博远型号');
    refusedDevice(fn()=>$prepare(['category_path'=>'手机/配件']),'不在当前博远目录');
    refusedDevice(fn()=>$prepare(['catalog_reference'=>array_replace($ref,['revision'=>'stale'])]),'资料已变化');
    $catOnly=$prepare(['catalog_reference'=>['scope'=>$scope,'category_id'=>'D'],'category_path'=>'客户端旧路径']);
    checkDevice($catOnly['catalog_product_id']===0 && $catOnly['category_name']==='配件' && $catOnly['category_path']==='手机/配件','server resolves explicit category ID without inventing a model');
    $supplier=(int)Db::name('erp_party')->insertGetId(['site_id'=>$site,'party_no'=>'SUPPLIER','party_name'=>'隔离供应商','party_type'=>'supplier','status'=>1]);
    $w=(int)Db::name('erp_warehouse')->insertGetId(['site_id'=>$site,'warehouse_name'=>'隔离仓','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
    $loc=(int)Db::name('erp_warehouse_location')->insertGetId(['site_id'=>$site,'warehouse_id'=>$w,'location_name'=>'隔离库位','status'=>1]);
    $purchase=ErpPurchaseService::forSite($site,1,'隔离验收');
    $make=fn($req,$overrides=[])=>$purchase->create(['request_id'=>$req,'purchaser_uid'=>1,'party_id'=>$supplier,'party_name'=>'隔离供应商','settle_mode'=>'credit',
        'items'=>[array_replace(['imei'=>'990000000000002','model'=>'来源设备','catalog_reference'=>$ref,'category_path'=>'不能使用这个旧路径','spec'=>'256GB',
            'purchase_cost'=>1,'retail_price'=>2,'image_urls'=>'["/static/fixture.png"]','warehouse_id'=>$w,'location_id'=>$loc],$overrides)]]);
    refusedDevice(fn()=>$make('CATALOG_FAIL',['warehouse_id'=>0]),'仓库');
    checkDevice(Db::name('erp_site_catalog_product')->count()===1 && Db::name('erp_purchase_order')->count()===0,'failed purchase rolls back the new catalog identity too');
    $po=$make('CATALOG_BUY'); $item=Db::name('erp_purchase_item')->where('purchase_order_id',$po)->find(); $asset=Db::name('erp_asset')->where('id',$item['asset_id'])->find();
    $id=(int)$item['catalog_product_id'];
    checkDevice($id>0 && $id!==$local && $id===(int)$asset['catalog_product_id'] && $asset['category_path']==='手机/配件/屏幕/旗舰','actual local purchase and asset use resolved native ID and current category snapshot');
    checkDevice($only()['site_product_id']===$id && $prepare(['catalog_product_id'=>$id])['catalog_product_id']===$id && $po===$make('CATALOG_BUY'),'reselection, trusted local ID and replay reuse the same product/order');
    $variant=['model'=>'来源设备','spec'=>'256GB','color'=>''];
    $line=['catalog_product_id'=>$id,'source_line_id'=>'purchase_line:'.$item['id'],'product_ref'=>'model:'.hash('sha256',json_encode($variant,JSON_UNESCAPED_UNICODE)),
        'attributes'=>[['key'=>'spec','value_text'=>'256GB']]];
    checkDevice(OpeningMasterService::purchaseProductDefaults($site,$key,[$line])===[$line['source_line_id']=>'ONLY-CODE-123'],'downstream Boyuan purchase prefill uses the same external SKU ID');
    $seed('products','DUP1','同名商品',['category_id'=>'F']);$seed('products','DUP2','同名商品',['category_id'=>'F']);
    refusedDevice(fn()=>$only('DUP1'),'同名');
    CatalogStore::query('products')->where('external_id',$ref['product_id'])->update(['enabled'=>0]);
    refusedDevice(fn()=>$prepare(['catalog_reference'=>$ref]),'已停用');
    $c['mode']='mock'; $cfg->persist($site,$c);
    refusedDevice(fn()=>$prepare(['catalog_reference'=>$ref]),'来源已变更');
    checkDevice(!$browse()['managed'] && $erp->productSnapshot($local)['product_name']==='历史型号','disconnect returns to local picker; historical records untouched');
    echo "PASS: $n purchase-device catalog assertions; disposable local DB only.\n";
} catch(Throwable $e) {$failed=true;fwrite(STDERR,'FAIL '.get_class($e).': '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine().PHP_EOL);}
finally {if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
