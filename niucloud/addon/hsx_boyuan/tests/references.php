<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit("Disable network functions before testing\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService,ReferenceService,MappingSourceService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;
$checks=0; $failed=false; $pdo=null; $schema='';
function rule(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; }
function denied(callable $fn,string $label): void {try {$fn();}catch(Problem $e){rule(true,$label);return;}throw new RuntimeException($label);}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install(); (new addon\hsx_boyuan\Addon())->upgrade();
    rule(Db::name('hsx_boyuan_reference')->count()===0,'new reference schema idempotent');
    $secret=str_repeat('A',32); $configService=new ConfigService();
    $config=$configService->save($site,1,['revision'=>0,'mode'=>'test','enabled'=>false,'paused'=>true,'profile'=>[
        'protocol'=>'openapi_v153','endpoint'=>'https://open-api-uat.139erp.com/api','gateway_path'=>'/gateway.do','app_id'=>'1234567890','app_secret'=>$secret,'account_id'=>'TEST_ACCOUNT',
    ]]);
    rule(!str_contains(Contract::json($config),$secret) && !isset($config['profiles']['test']['app_secret_cipher']) && $config['profiles']['test']['app_secret_configured'],'secret never returned');
    $private=$configService->get($site,false); $profile=$private['profiles']['test'];
    rule(!str_contains($profile['app_secret_cipher'],$secret),'secret encrypted at rest');
    denied(fn()=>$configService->save($site,1,['revision'=>$config['revision'],'enabled'=>true,'paused'=>false]),'openapi business writes remain blocked');
    $sync=new ReferenceService(); denied(fn()=>$sync->start($site,1,['products']),'sync requires verified account');
    $wrong=true; $round=1; $badAccount=true; $emptyProducts=false; $wire=[];
    $api=new OpenApi($profile,$secret,function($path,$params) use(&$wire,&$wrong,&$round,&$badAccount,&$emptyProducts): array {
        $wire[]=['path'=>$path,'params'=>$params]; $rows=[]; $total=0;
        if($params['service']==='boyuan.companys.get') $rows=[['id'=>'COMP1','gsdm'=>$wrong?'WRONG':'TEST_ACCOUNT','name'=>'测试账套','is_effective'=>1]];
        if($params['service']==='boyuan.products.get' && !$emptyProducts) {
            $page=(int)$params['page_no']; $total=102;
            for($n=($page-1)*100+1;$n<=min($page*100,102);$n++) $rows[]=['id'=>'P'.$n,'code'=>'SKU'.$n,'full_name'=>'测试手机'.$n.($round===2?'更新':''),'memory'=>'256GB','color'=>'黑色','imei_count'=>1,'is_effective'=>$round===2 && $n===1?0:1];
        }
        if($params['service']==='boyuan.custsuppliers.get') $rows=[['id'=>'PARTY1','spell_code'=>'CUS1','name'=>'测试客户','is_effective'=>1,'is_cust'=>1,'is_supplier'=>0,'contact_phone'=>'13800000000','account_no'=>'DO_NOT_KEEP']];
        if($params['service']==='boyuan.warehouses.get') $rows=[['id'=>'W1','name'=>'测试仓','gsdm'=>'TEST_ACCOUNT','is_effective'=>1]];
        if($params['service']==='boyuan.account.get') $rows=[['name'=>'测试资金账户','id'=>$badAccount?'':'ACCOUNT1','is_effective'=>1,'account_no'=>'DO_NOT_KEEP']];
        return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>$rows,'total_results'=>$total?:count($rows)]];
    });
    denied(fn()=>$configService->probe($site,1,$api),'wrong company cannot pass probe'); $wrong=false;
    rule($configService->probe($site,1,$api)['verified'],'company identity verified read only');
    rule($wire[0]['path']==='/gateway.do' && $wire[0]['params']['big_gsdm']==='TEST_ACCOUNT','gateway and account use official parameters');
    rule($wire[0]['params']['version']==='1.0' && $wire[0]['params']['sign_type']==='MD5','document version not sent as API version');
    $signed=$wire[0]['params']; rule($signed['sign']===OpenApi::sign($signed,$secret) && !str_contains(Contract::json($signed),$secret),'signature has no raw secret');
    rule(OpenApi::sign(['z'=>0,'a'=>'A&B 中文','sign'=>'ignored','sign_type'=>'MD5','blank'=>'','nil'=>null],'fake')===md5('a=A&B 中文&z=0fake'),'raw values sorted before URL encoding; zero retained');
    denied(fn()=>$api->call('boyuan.trade.unified.pay',[]),'payment service cannot be called');
    denied(fn()=>$api->call('boyuan.products.get',['big_gsdm'=>'OTHER']),'business args cannot override company');
    denied(fn()=>$api->send('POST','/documents',[]),'document write adapter is not faked');
    $api->send('GET',$profile['paths']['references'],['resource'=>'members','since'=>'2001-01-01','until'=>'2026-10-30']);
    $last=end($wire)['params']; rule($last['register_date_start']==='2001-01-01' && $last['register_date_end']==='2026-10-30','member query sends explicit frozen registration range');
    $unnamed=OpenApi::normalize('brands',['id'=>'BRAND0','name'=>'','is_effective'=>0]);
    rule(!$unnamed['enabled'] && $unnamed['extra']['source_name_missing'],'unnamed inactive source preserved and not selectable');
    denied(fn()=>OpenApi::normalize('brands',['id'=>'BRAND1','name'=>'','is_effective'=>1]),'enabled name missing cannot silently become valid');
    $unknown=OpenApi::normalize('members',['id'=>'MEMBER0','name'=>'测试会员']); rule(!$unknown['enabled'] && !$unknown['extra']['status_known'],'missing source status is unknown rather than invented active');
    $product=OpenApi::normalize('products',['id'=>'STRUCTURED_PRODUCT','name'=>'测试型号','full_name'=>'测试商品展示名','memory'=>'256GB','color'=>'黑色','brand_id'=>'BRAND','version_type_id'=>'VERSION','version_type_name'=>'测试版本','is_effective'=>1]);
    rule($product['extra']['name']==='测试型号' && $product['extra']['memory']==='256GB' && $product['extra']['color']==='黑色' && $product['extra']['version_type_id']==='VERSION' && $product['extra']['brand_id']==='BRAND','model memory color version and brand retain separate fields');
    rule(ReferenceService::phoneHash($site,'13800000000')!==ReferenceService::phoneHash($site+1,'13800000000'),'phone hash tenant separated');
    $run=$sync->start($site,1,['products','parties','warehouses']); $same=$sync->start($site,1,['products']);
    rule($run['ids'][0]===$same['ids'][0],'repeat click reuses queued job');
    for($i=0;$i<4;$i++) {Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->update(['next_at'=>0]); $sync->tick($site,10,$api);}
    rule(Db::name('hsx_boyuan_reference_sync')->where('status','completed')->count()===3,'all reference pages completed');
    rule(Db::name('hsx_boyuan_reference')->where('resource','products')->count()===102,'two pages persisted');
    rule(!$sync->search($site,['resource'=>'products'])['items'][0]['stale'],'fully synced archives are confirmed current');
    $first=Db::name('hsx_boyuan_reference')->where('external_id','P1')->find(); $extra=Contract::decode($first['extra_json']);
    rule($extra['memory']==='256GB' && $extra['color']==='黑色','product attributes preserved');
    $party=Db::name('hsx_boyuan_reference')->where('external_id','PARTY1')->find();
    rule(!str_contains(Contract::json($party),'13800000000') && !str_contains(Contract::json($party),'DO_NOT_KEEP'),'phone and bank number not stored in archive plaintext');
    $found=$sync->search($site,['resource'=>'parties','keyword'=>'13800000000','role'=>'customer']);
    rule(count($found['items'])===1 && !str_contains(Contract::json($found),'phone_hash'),'exact phone search returns masked records only');
    rule($sync->search($site,['resource'=>'parties','role'=>'supplier'])['total']===0,'customer cannot be selected as supplier');
    $round=2; $sync->start($site,1,['products']);
    for($i=0;$i<3;$i++) {Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->update(['next_at'=>0]); $sync->tick($site,10,$api);}
    rule(Db::name('hsx_boyuan_reference')->where('resource','products')->count()===102,'refresh updates without duplicates');
    rule(Db::name('hsx_boyuan_reference')->where('external_id','P1')->value('enabled')===0,'remote disabled status preserved');
    rule($sync->search($site,['resource'=>'products','enabled_only'=>true])['total']===101,'disabled products excluded from selector');
    $emptyProducts=true; $sync->start($site,1,['products']); Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->update(['next_at'=>0]); $sync->tick($site,10,$api);
    rule(Db::name('hsx_boyuan_reference')->where('resource','products')->count()===102,'absence does not silently delete existing archives');
    rule($sync->search($site,['resource'=>'products','enabled_only'=>true])['total']===0,'old unseen records retained but unavailable for new automatic mapping');
    $bad=$sync->start($site,1,['capital_accounts'])['ids'][0];
    for($i=0;$i<3;$i++) {Db::name('hsx_boyuan_reference_sync')->where('id',$bad)->update(['next_at'=>0]); $sync->page($site,$bad,$api);}
    rule(Db::name('hsx_boyuan_reference_sync')->where('id',$bad)->value('status')==='failed','missing authoritative account id is visible failure');
    rule(Db::name('hsx_boyuan_reference')->where('resource','capital_accounts')->count()===0,'invalid page rolled back');
    $badAccount=false; $sync->retry($site,1,$bad); $sync->page($site,$bad,$api);
    rule(Db::name('hsx_boyuan_reference_sync')->where('id',$bad)->value('status')==='completed','manual resume completes same page');
    $mismatch=new class implements \addon\hsx_boyuan\app\gateway\Transport { public function send(string $method,string $path,array $body=[]): array {return ['code'=>'OK','data'=>['items'=>[['id'=>'W2','code'=>'W2','name'=>'数量差异测试仓','enabled'=>true,'extra'=>[]]],'has_more'=>false,'next_cursor'=>null,'total'=>2]];} };
    $review=$sync->start($site,1,['warehouses'])['ids'][0]; $sync->page($site,$review,$mismatch);
    rule(Db::name('hsx_boyuan_reference_sync')->where('id',$review)->value('status')==='needs_review','total mismatch never reports whole completion');
    rule(Db::name('hsx_boyuan_reference')->where('external_id','W2')->count()===1,'valid mismatched data retained for review');
    Db::name('erp_warehouse')->insert(['site_id'=>$site,'warehouse_name'=>'本地测试仓','warehouse_type'=>'peer','status'=>1]);
    $sources=(new MappingSourceService())->search($site,['kind'=>'warehouse']); rule(count($sources['items'])===1 && str_starts_with($sources['items'][0]['ref'],'warehouse:'),'local source selected without internal-id typing');
    MappingSourceService::assertMatches($site,'warehouse',$sources['items'][0]['ref']); rule(true,'local source identity belongs to site');
    denied(fn()=>MappingSourceService::assertMatches($site,'warehouse','warehouse:999999999'),'manual invalid local identity blocked');
    denied(fn()=>$sync->retry($site+1,1,$bad),'cross tenant sync action blocked');
    $fresh=$configService->get($site); $configService->save($site,1,['revision'=>$fresh['revision'],'profile'=>['app_id'=>'999999']]);
    rule($sync->search($site,['resource'=>'products'])['total']===0,'new app identity has separate archives');
    rule(!$sync->page($site,$bad,$api),'old connection job cannot run in new app');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'external_calls'=>0,'business_database_modified'=>false])."\n";
} catch(Throwable $e) { $failed=true; fwrite(STDERR,'FAIL '.get_class($e).': '.($e instanceof Problem?$e->reason:$e->getMessage()).' at '.basename($e->getFile()).':'.$e->getLine()."\n"); }
finally {if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
