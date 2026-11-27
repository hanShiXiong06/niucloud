<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true) || ini_get('allow_url_fopen')) exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\ConfigService;
use addon\hsx_erp\app\service\admin\ErpWarehouseService;
use think\facade\Db;
$pdo=null;$schema='';$checks=0;$failed=false;
function expectWarehouse(bool $ok,string $label): void { global $checks;if(!$ok) throw new RuntimeException($label); ++$checks; }
function refusedWarehouse(callable $fn,string $label): void {
    try {$fn();} catch(\core\exception\CommonException $e){expectWarehouse(true,$label);return;}
    throw new RuntimeException($label);
}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot();(new addon\hsx_boyuan\Addon())->install();
    $cfg=new ConfigService();$key=str_repeat('e',32);$s=ErpWarehouseService::forSite($site,1); $manual=['warehouse_name'=>'已有普通仓','manager_uid'=>1];
    expectWarehouse(!$s->masterPolicy()['managed'],'单纯安装不接管');
    $w=$s->saveWarehouse($manual);$l=$s->saveLocation($w,['location_name'=>'旧库位']);expectWarehouse($w>0 && $l>0,'默认或模拟站点可手动建仓和库位');
    $c=$cfg->get($site,false);$c['mode']='test';$c['profiles']['test']=array_replace($c['profiles']['test'],['connection_key'=>$key,'account_id'=>'UNIT','protocol'=>'openapi_v153','contract_confirmed'=>true,'probe_at'=>0]);$cfg->persist($site,$c);
    expectWarehouse(!$s->masterPolicy()['managed'],'尚未核验不能接管');
    $c['profiles']['test']['probe_at']=time();$c['enabled']=false;$c['paused']=true;$cfg->persist($site,$c);
    expectWarehouse($s->masterPolicy()['managed']&&!$s->masterPolicy()['allow_manual_location'],'暂停交易投递仍由博远维护');
    $inject=['provider'=>'hsx_boyuan','trusted'=>true,'from_sync'=>true,'connection_key'=>$key,'id'=>0];
    refusedWarehouse(fn()=>$s->saveWarehouse(['warehouse_name'=>'伪造仓','manager_uid'=>1]+$inject,0),'标识不能绕过');
    refusedWarehouse(fn()=>$s->saveLocation($w,['location_name'=>'伪造位置']+$inject,-1),'负ID也不能建库位');
    refusedWarehouse(fn()=>$s->saveWarehouse(['warehouse_name'=>'冒充编辑新增','manager_uid'=>1],999999),'绑定不存在的编辑ID不创建');
    refusedWarehouse(fn()=>$s->saveLocation($w,['location_name'=>'冒充编辑新增'],999999),'绑定不存在的库位编辑ID不创建');
    expectWarehouse($s->saveWarehouse($manual+['remark'=>'允许编辑原记录'],$w)===$w && $s->saveLocation($w,['location_name'=>'旧库位','remark'=>'允许编辑'],$l)===$l,'保留旧记录编辑');
    $s2=$site+1;Db::name('site')->insert(['site_id'=>$s2,'site_name'=>'仅ERP隔离站','app_type'=>'site','group_id'=>0,'addons'=>'["hsx_erp"]']);
    $cfg->persist($s2,$c);Db::name('sys_user_role')->insert(['site_id'=>$s2,'uid'=>1,'status'=>1,'is_admin'=>1]);$other=ErpWarehouseService::forSite($s2,1);
    $ow=$other->saveWarehouse(['warehouse_name'=>'只属隔离站','manager_uid'=>1]);expectWarehouse(!$other->masterPolicy()['managed'] && $other->saveLocation($ow,['location_name'=>'二站独立位'])>0,'未安装本站可建，即使有无效残余配置');
    refusedWarehouse(fn()=>$s->saveWarehouse(['warehouse_name'=>'跨站编辑','manager_uid'=>1],$ow),'不能编辑其他站点');
    $a=['warehouse_name'=>'同步仓','warehouse_code'=>'SRC','manager_uid'=>1];$run=str_repeat('a',32);
    Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'mode'=>'test','connection_key'=>$key,'resource'=>'warehouses','run_key'=>$run,'scope_json'=>'{}','status'=>'completed']);
    $base=['site_id'=>$site,'connection_key'=>$key,'external_id'=>'W01','name'=>'同步仓','code'=>'SRC','enabled'=>1,'seen_run'=>$run,'extra_json'=>'{"company_id":"company1"}'];
    \addon\hsx_boyuan\app\support\CatalogStore::query('warehouses')->insert($base);
    $create=fn($provider,$connection,$ext,$data)=>$s->createSyncedWarehouseWithDefaultLocation($provider,$connection,$ext,$data);
    refusedWarehouse(fn()=>$create('foo',$key,'W01',$a),'不接受伪造来源');
    refusedWarehouse(fn()=>$create('hsx_boyuan',str_repeat('d',32),'W01',$a),'连接不能串账套');
    refusedWarehouse(fn()=>$create('hsx_boyuan',$key,'W02',$a),'有效来源ID必需');
    refusedWarehouse(fn()=>$create('hsx_boyuan',$key,'W01',array_replace($a,['warehouse_name'=>'替换仓'])),'不得擅改主档');
    $dup=\addon\hsx_boyuan\app\support\CatalogStore::query('warehouses')->insertGetId(array_replace($base,['external_id'=>'W02','extra_json'=>'{"company_id":"company2"}']));
    refusedWarehouse(fn()=>$create('hsx_boyuan',$key,'W01',$a),'同站不同分公司重名不得猜测');
    \addon\hsx_boyuan\app\support\CatalogStore::query('warehouses')->where('site_id',$site)->where('id',$dup)->update(['enabled'=>0]);
    $iw=$create('hsx_boyuan',$key,'W01',$a);$il=Db::name('erp_warehouse_location')->where('site_id',$site)->where('warehouse_id',$iw)->select()->toArray();
    expectWarehouse(count($il)===1 && $il[0]['location_code']==='DEFAULT' && $il[0]['location_name']==='默认库位' && (int)$il[0]['is_default']===1,'真实主档与单个本地默认库位同时生成');
    $c['mode']='mock';$cfg->persist($site,$c);expectWarehouse(!$s->masterPolicy()['managed'] && $s->saveLocation($w,['location_name'=>'演练不接管'])>0,'模拟模式与实际主账套分离');
    $c['mode']='production';$c['profiles']['production']=$c['profiles']['test'];$cfg->persist($site,$c);
    expectWarehouse($s->masterPolicy()['managed'],'已验证正式主档也接管（无联网）');
    $app->event->listen('HsxErpWarehouseMasterPolicy',static function(){throw new RuntimeException('断开档案服务');});
    $err=false;try{$s->saveWarehouse(['warehouse_name'=>'异常绕过','manager_uid'=>1]);}catch(Throwable $e){$err=true;}
    expectWarehouse($err && !Db::name('erp_warehouse')->where(['site_id'=>$site,'warehouse_name'=>'异常绕过'])->count(),'归属判断异常不放行新增');
    echo json_encode(['passed'=>true,'assertions'=>$checks,'network_requests'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.get_class($e).' '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine().PHP_EOL);}
finally{if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
