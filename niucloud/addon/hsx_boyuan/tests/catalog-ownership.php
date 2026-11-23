<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true) || ini_get('allow_url_fopen')) exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{CategoryCatalog,ConfigService,OpeningMasterService,ReferenceService};
use addon\hsx_boyuan\app\support\CatalogStore;
use addon\hsx_erp\app\service\admin\{ErpGoodsCatalogImportTaskService,ErpGoodsCatalogService,ErpQuantityInventoryService};
use core\exception\{AdminException,CommonException};
use think\facade\Db;

$pdo=null; $schema=''; $n=0; $fail=false;
function must(bool $ok,string $message):void {global $n;if(!$ok)throw new RuntimeException($message);$n++;}
function noEdit(callable $fn):void {
    try{$fn();}catch(AdminException|CommonException $e){must(str_contains($e->getMessage(),'同步最新分类'),'manual refusal explains the next action');return;}
    throw new RuntimeException('unintended local write');
}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot();(new addon\hsx_boyuan\Addon())->install();
    $erp=new ErpGoodsCatalogService(); $import=new ErpGoodsCatalogImportTaskService();
    $local=['category_path'=>'旧分类','product_name'=>'本站型号'];$localId=$erp->add($local);
    must(!$erp->summary()['master_policy']['managed'],'installation alone does not take ownership');
    $cs=new ConfigService();$cfg=$cs->get($site,false);$key=str_repeat('a',32);$cfg['mode']='test';
    $cfg['profiles']['test']=array_replace($cfg['profiles']['test'],['endpoint'=>'https://open-api-uat.139erp.com/api','account_id'=>'FIXTURE','app_id'=>'test','probe_at'=>time(),'connection_key'=>$key]);
    $cs->persist($site,$cfg);$ref=new ReferenceService();$rows=[];$total=0;
    $api=new OpenApi($cfg['profiles']['test'],str_repeat('a',32),function($path,$params)use(&$rows,&$total){
        must($params['service']==='boyuan.categorys.get','only the category read contract is used');
        return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>$rows,'total_results'=>$total]];
    });
    $pull=function($data,?int $reported=null,array $nextRows=[],?int $nextTotal=null)use(&$rows,&$total,$key,$site,$ref,$api) {
        $rows=$data;$total=$reported??count($data);$args=[$site,1,['categories'],'refresh',$key];
        $id=$ref->start(...$args)['ids'][0];must($ref->start(...$args)['ids']===[$id],'double click reuses queued read');
        $receipt=$ref->readJobPage($site,$id,'categories',$key,'test','1',$api);
        if($receipt['status']==='queued'){
            must($receipt['cursor']==='2','one bounded follow-up on short last page');$rows=$nextRows;$total=$nextTotal??$total;
            Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'id'=>$id])->update(['next_at'=>0]);
            $receipt=$ref->readJobPage($site,$id,'categories',$key,'test','2',$api);
        }
        return $receipt['status'];
    };
    must($erp->summary()['master_policy']['managed'] && !$cfg['enabled'] && $cfg['paused'],'verified site remains read-only even with delivery paused');
    noEdit(fn()=>$erp->add($local));noEdit(fn()=>$erp->edit($localId,$local));noEdit(fn()=>$erp->delete($localId));
    noEdit(fn()=>$erp->sortNode(['node_type'=>'category','category_path'=>'旧分类','sort'=>1]));
    noEdit(fn()=>$erp->importRowsForSite([$local+['from_sync'=>true]],'by_faked',$site,false));
    noEdit(fn()=>$import->upload(null));noEdit(fn()=>$import->retry(999)); must($erp->productSnapshot($localId)['product_name']==='本站型号','history intact');
    $row=fn($id,$name,$parent=null,$enabled=1)=>array_filter(['id'=>$id,'name'=>$name,'code'=>$id,'parent_id'=>$parent,'is_effective'=>$enabled],fn($v)=>$v!==null);
    $a=$row('A','数码');$b=$row('B','手机','A');$c=$row('C','苹果','B');$d=$row('D','旗舰','C');$old=$row('OLD','旧类');
    $dir=fn()=>array_column($ref->categoryDirectory($site,$key)['items'],'name','id');
    $selector=fn($path='')=>array_column($erp->hierarchy(['category_only'=>1,'category_path'=>$path])['list'],'category_path');
    must($pull([$a,$b,$c,$d],5,[$old])==='completed','short first page with a real remainder yields the complete catalogue');
    must($selector()===['数码','旧类'] && $selector('数码/手机/苹果')===['数码/手机/苹果/旗舰'],'ERP picker supports four levels and no local fallback');
    must(CategoryCatalog::path($site,$key,'D')==='数码/手机/苹果/旗舰','source parent IDs, not local model paths');
    $q=new ErpQuantityInventoryService();
    must($q->createProduct(['product_name'=>'标品','category_path'=>'数码/手机/苹果/旗舰'])['id']>0,'valid source leaf accepted');
    $bad=false;try{$q->createProduct(['product_name'=>'标品二','category_path'=>'数码/手机']);}catch(CommonException $e){$bad=true;}must($bad,'non-leaf refused');
    $b['name']='通信';$new=$row('NEW','平板');
    must($pull([$a,$b,$c,$d,$new])==='completed','confirmed rename addition and removal');
    $seen=$dir();must($seen['B']==='通信' && isset($seen['NEW']) && !isset($seen['OLD']),'no stale deleted ID in current directory');
    $paged=$ref->search($site,['resource'=>'categories','expected_connection_key'=>$key],true);
    must($paged['total']===5 && !in_array('OLD',array_column($paged['items'],'id'),true),'paged fallback also excludes deleted ID');
    must(CatalogStore::query('categories')->where('external_id','OLD')->count()===1,'audit archive is preserved');
    $productRun=str_repeat('f',32);
    CatalogStore::query('products')->insert(['site_id'=>$site,'connection_key'=>$key,'external_id'=>'P','code'=>'P','name'=>'来源型号','enabled'=>1,
        'extra_json'=>'{"category_id":"D","category_name":"过期别名"}','seen_run'=>$productRun]);
    $open=new OpeningMasterService();$write=Db::transaction(fn()=>$open->page($site,1,$key,'products',$productRun,0,true));
    must($write['rows'][0]['action']==='create','confirmed four-level source product can be built');
    $built=(int)Db::name('erp_site_catalog_product')->where('product_name','来源型号')->value('site_product_id');
    must($erp->productSnapshot($built)['category_path']==='数码/通信/苹果/旗舰','new product carries real four-level source path');
    $a['name']='电子';must($pull([$a,$b,$c,$d,$new])==='completed' && $erp->productSnapshot($built)['category_path']==='电子/通信/苹果/旗舰','after a source rename new operations follow stable category ID');
    must(Db::name('erp_site_catalog_product')->where('site_product_id',$built)->value('category_path')==='数码/通信/苹果/旗舰','stored history not rewritten');
    must($pull([$a,$new],7)==='needs_review','partial batch is not reported complete');
    $seen=$dir();must(count($seen)===5 && isset($seen['D']) && !isset($seen['OLD']),'partial does not remove unreturned rows or resurrect deleted ones');
    must($pull([$a,$new],7,[],0)==='needs_review' && count($dir())===5,'an empty follow-up claiming total zero cannot erase the earlier catalogue');
    must($pull([])==='completed' && $dir()===[] && $selector()===[],'confirmed empty source is empty, never falls back');
    $gone=false;try{$erp->productSnapshot($built);}catch(AdminException $e){$gone=str_contains($e->getMessage(),'分类已不可用');}must($gone,'new business cannot revive a removed mapped category');
    must($erp->productSnapshot($localId)['product_name']==='本站型号','source removal never deletes history');
    $cfg['mode']='mock';$cs->persist($site,$cfg);
    must(!$erp->summary()['master_policy']['managed'] && $erp->edit($localId,$local) && in_array('旧分类',$selector(),true),'independent site can still edit and select local data');
    echo "PASS: $n assertions, offline disposable database; no upstream writes.\n";
}catch(Throwable $e){$fail=true;fwrite(STDERR,'FAIL '.get_class($e).' '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine().PHP_EOL);}
finally{if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema))$pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($fail?1:0);
