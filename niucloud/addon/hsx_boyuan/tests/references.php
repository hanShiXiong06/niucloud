<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit("Disable network functions before testing\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService,ReferenceService,MappingSourceService,MappingCandidateService};
use addon\hsx_boyuan\app\support\{ArchiveView,CatalogStore,Contract,Problem,Secret};
use think\facade\Db;
$checks=0; $failed=false; $pdo=null; $schema='';
function rule(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; }
function denied(callable $fn,string $label): void {try {$fn();}catch(Problem $e){rule(true,$label);return;}throw new RuntimeException($label);}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install(); (new addon\hsx_boyuan\Addon())->upgrade();
    rule(array_sum(array_map(fn($k)=>\addon\hsx_boyuan\app\support\CatalogStore::query($k)->count(),\addon\hsx_boyuan\app\support\CatalogStore::kinds()))===0,'new reference schema idempotent');
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
            for($n=($page-1)*100+1;$n<=min($page*100,102);$n++) $rows[]=['id'=>'P'.$n,'code'=>'SKU'.$n,'full_name'=>'测试手机'.$n.($round===2?'更新':''),'memory'=>'256GB','color'=>'黑色','imei_count'=>$n===2?0:($n===3?2:1),'imei_len'=>$n===4?12:15,'is_effective'=>$round===2 && $n===1?0:1];
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
    $key=$profile['connection_key'];
    $jobs=$sync->jobs($site,['start'=>false,'retry'=>false,'search'=>true]);
    rule(count($jobs)===10 && $jobs[0]['connection_key']===$key && $jobs[0]['ready'] && !$jobs[0]['capabilities']['start'],'ten scope-labelled resource states');
    $opening=$sync->start($site,1,['products'],'opening',$key);
    rule(!$opening['queued'] && $opening['skipped']['products']==='completed','opening mode skips completed and creates no duplicate jobs');
    denied(fn()=>$sync->start($site,1,['products'],'opening','other'),'stale opening cannot switch accounts');
    denied(fn()=>$sync->search($site,['resource'=>'products','expected_connection_key'=>'other']),'stale archive preview is denied');
    rule(\addon\hsx_boyuan\app\support\CatalogStore::query('products')->count()===102,'two pages persisted');
    rule(!$sync->search($site,['resource'=>'products'])['items'][0]['stale'],'fully synced archives are confirmed current');
    $first=\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->find(); $extra=Contract::decode($first['extra_json']);
    rule($extra['memory']==='256GB' && $extra['color']==='黑色','product attributes preserved');
    $party=\addon\hsx_boyuan\app\support\CatalogStore::query('parties')->where('external_id','PARTY1')->find();
    rule(!str_contains(Contract::json($party),'13800000000') && !str_contains(Contract::json($party),'DO_NOT_KEEP'),'phone and bank number not stored in archive plaintext');
    $found=$sync->search($site,['resource'=>'parties','keyword'=>'13800000000','role'=>'customer']);
    rule(count($found['items'])===1 && !str_contains(Contract::json($found),'phone_hash'),'exact phone search returns masked records only');
    rule($sync->search($site,['resource'=>'parties','role'=>'supplier'])['total']===0,'customer cannot be selected as supplier');
    $round=2; $sync->start($site,1,['products']);
    for($i=0;$i<3;$i++) {Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->update(['next_at'=>0]); $sync->tick($site,10,$api);}
    rule(\addon\hsx_boyuan\app\support\CatalogStore::query('products')->count()===102,'refresh updates without duplicates');
    rule(\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P1')->value('enabled')===0,'remote disabled status preserved');
    rule($sync->search($site,['resource'=>'products','enabled_only'=>true])['total']===101,'disabled products excluded from selector');
    $serialFilter=['resource'=>'products','role'=>'purchase_product','serial_length'=>'15'];
    $filtered=$sync->search($site,$serialFilter+['limit'=>2]);
    rule($filtered['total']===98 && array_column($filtered['items'],'id')===['P5','P6'],'purchase single-serial filter is applied before pagination and excludes incompatible or disabled products');
    rule($sync->search($site,array_replace($serialFilter,['serial_length'=>12]))['total']===1 && $sync->search($site,['resource'=>'products'])['total']===102,'different lengths stay separate; browsing master data remains unfiltered');
    denied(fn()=>$sync->search($site,array_replace($serialFilter,['serial_length'=>'15x'])),'bad serial length rejected');
    rule($sync->search($site,['resource'=>'products','archive_status'=>'enabled'])['total']===101 && $sync->search($site,['resource'=>'products','archive_status'=>'disabled'])['total']===1,'desktop status filters use stored columns');
    $productRow=\addon\hsx_boyuan\app\support\CatalogStore::query('products')->where('external_id','P2'); $savedExtra=(clone $productRow)->value('extra_json');
    (clone $productRow)->update(['extra_json'=>Contract::json(array_replace(Contract::decode($savedExtra),['status_known'=>false]))]);
    rule($sync->search($site,['resource'=>'products','archive_status'=>'unknown'])['total']===1 && $sync->search($site,['resource'=>'products','archive_status'=>'enabled'])['total']===100,'unknown is not enabled');
    (clone $productRow)->update(['extra_json'=>$savedExtra]);
    denied(fn()=>$sync->search($site,['resource'=>'products','archive_status'=>'bogus']),'invalid filter rejected');
    $emptyProducts=true; $sync->start($site,1,['products']); Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->update(['next_at'=>0]); $sync->tick($site,10,$api);
    rule(\addon\hsx_boyuan\app\support\CatalogStore::query('products')->count()===102,'absence does not silently delete existing archives');
    rule($sync->search($site,['resource'=>'products','enabled_only'=>true])['total']===0,'old unseen records retained but unavailable for new automatic mapping');
    $bad=$sync->start($site,1,['capital_accounts'])['ids'][0];
    for($i=0;$i<3;$i++) {Db::name('hsx_boyuan_reference_sync')->where('id',$bad)->update(['next_at'=>0]); $sync->page($site,$bad,$api);}
    rule(Db::name('hsx_boyuan_reference_sync')->where('id',$bad)->value('status')==='failed','missing authoritative account id is visible failure');
    rule($sync->start($site,1,['capital_accounts'],'opening',$key)['skipped']['capital_accounts']==='failed','opening cannot bypass resume permission');
    rule(\addon\hsx_boyuan\app\support\CatalogStore::query('capital_accounts')->count()===0,'invalid page rolled back');
    $badAccount=false; $sync->retry($site,1,$bad); $sync->page($site,$bad,$api);
    rule(Db::name('hsx_boyuan_reference_sync')->where('id',$bad)->value('status')==='completed','manual resume completes same page');
    $mismatch=new class implements \addon\hsx_boyuan\app\gateway\Transport { public function send(string $method,string $path,array $body=[]): array {return ['code'=>'OK','data'=>['items'=>[['id'=>'W2','code'=>'W2','name'=>'数量差异测试仓','enabled'=>true,'extra'=>[]]],'has_more'=>false,'next_cursor'=>null,'total'=>2]];} };
    $review=$sync->start($site,1,['warehouses'])['ids'][0]; $sync->page($site,$review,$mismatch);
    rule(Db::name('hsx_boyuan_reference_sync')->where('id',$review)->value('status')==='needs_review','total mismatch never reports whole completion');
    rule($sync->start($site,1,['warehouses'],'opening',$key)['skipped']['warehouses']==='needs_review','mismatched total remains explicit until user chooses to refresh');
    rule(\addon\hsx_boyuan\app\support\CatalogStore::query('warehouses')->where('external_id','W2')->count()===1,'valid mismatched data retained for review');
    Db::name('erp_warehouse')->insert(['site_id'=>$site,'warehouse_name'=>'本地测试仓','warehouse_type'=>'peer','status'=>1]);
    $sources=(new MappingSourceService())->search($site,['kind'=>'warehouse']); rule(count($sources['items'])===1 && str_starts_with($sources['items'][0]['ref'],'warehouse:'),'local source selected without internal-id typing');
    MappingSourceService::assertMatches($site,'warehouse',$sources['items'][0]['ref']); rule(true,'local source identity belongs to site');
    denied(fn()=>MappingSourceService::assertMatches($site,'warehouse','warehouse:999999999'),'manual invalid local identity blocked');
    denied(fn()=>$sync->retry($site+1,1,$bad),'cross tenant sync action blocked');
    $old=$sync->start($site,1,['brands'],'opening',$key)['ids'][0];
    Db::name('hsx_boyuan_reference_sync')->where('id',$old)->update(['status'=>'failed']);
    $new=$sync->start($site,1,['brands'],'refresh',$key)['ids'][0];
    denied(fn()=>$sync->retry($site,1,$old),'obsolete failed sync cannot overwrite a newer run');
    rule($sync->start($site,1,['brands'],'opening',$key)['ids']===[$new],'idempotent opening reuses in-flight job');
    $varying=new class implements \addon\hsx_boyuan\app\gateway\Transport {
        public int $page=0;public bool $duplicate=false;
        public function send(string $method,string $path,array $body=[]):array {
            $first= ++$this->page===1; $id=$first||$this->duplicate?'B1':'B2';
            return ['code'=>'OK','data'=>['items'=>[['id'=>$id,'code'=>$id,'name'=>$id,'enabled'=>true,'extra'=>[]]],
                'total'=>$this->duplicate?null:($first?3:2),'has_more'=>$first,'next_cursor'=>$first?'2':null]];
        }
    };
    foreach([false,true] as $repeat) {
        $varying->page=0; $varying->duplicate=$repeat; $id=$sync->start($site,1,['brands'],'refresh',$key)['ids'][0];
        $sync->page($site,$id,$varying); Db::name('hsx_boyuan_reference_sync')->where('id',$id)->update(['next_at'=>0]); $sync->page($site,$id,$varying);
        rule(Db::name('hsx_boyuan_reference_sync')->where('id',$id)->value('status')==='needs_review',$repeat?'cross-page duplicate with no total cannot look complete':'changing upstream total cannot look complete');
    }
    // Simulate an account that selected the former agreed field and already indexed the wrong number.
    $cfgRow=Db::name('sys_config')->where(['site_id'=>$site,'config_key'=>ConfigService::KEY]);
    $cfg=Contract::decode((clone $cfgRow)->value('value')); $cfg['profiles']['test']['party_phone_field']='spell_code';
    (clone $cfgRow)->update(['value'=>Contract::json($cfg)]);
    rule($configService->get($site)['profiles']['test']['party_phone_field']==='help_code','old agreed-field preference corrected without a configuration save');
    denied(fn()=>$configService->save($site,1,['revision'=>$cfg['revision'],'profile'=>['party_phone_field'=>'spell_code']]),'old UI cannot reselect obsolete phone field');
    denied(fn()=>OpenApi::normalize('parties',['id'=>'X','name'=>'测试','spell_code'=>'13600000009'],'spell_code'),'cannot index the retired field');
    $rows=fn()=>CatalogStore::query('parties')->where(['site_id'=>$site,'external_id'=>'PARTY1','connection_key'=>$key]);
    $legacy=Contract::decode($party['extra_json']); $legacy['party_phone_field']='spell_code';
    $legacy[ArchiveView::SNAPSHOT]=Secret::seal(Contract::json(['help_code'=>'138-0000-0007','spell_code'=>'BY-OLD','contact_phone'=>'139****0008']),(string)env('app.auth_key',''),$site,ArchiveView::context($key,'parties','PARTY1'));
    $rows()->update(['phone_hash'=>ReferenceService::phoneHash($site,'13600000009'),'phone_masked'=>'136****0009','extra_json'=>Contract::json($legacy)]);
    $oldDetail=$sync->detail($site,'parties','PARTY1',$key,true);$oldSnapshot=$oldDetail['fields'];
    rule($oldSnapshot['help_code']===null && $oldDetail['phone_needs_sync'] && $oldSnapshot['spell_code']==='BY-OLD' && $oldSnapshot['contact_phone']==='139****0008','old snapshot from a different source cannot masquerade as the synced phone');
    $oldList=$sync->search($site,['resource'=>'parties'],true)['items'][0];
    rule($oldList['phone_number']===null && $oldList['phone_state']==='refresh' && !isset($oldList['phone_masked']),'old rows explicitly require rereading the class, not an extra reveal operation');
    $previous=$sync->search($site,['resource'=>'parties']);
    rule($previous['items'][0]['phone_state']==='refresh' && $previous['items'][0]['phone_masked']==='','old phone must not be relabelled as help_code');
    $localParty=(int)Db::name('erp_party')->insertGetId(['site_id'=>$site,'party_no'=>'PHONE_SOURCE_TEST','party_name'=>'测试往来单位','party_type'=>'both','role_flags'=>'sale_customer,purchase_supplier','contact_mobile'=>'13600000009','status'=>1]);
    $candidates=new MappingCandidateService();
    rule($sync->search($site,['resource'=>'parties','keyword'=>'13600000009'])['total']===0 && $candidates->search($site,'customer','party:'.$localParty)['match']==='not_found','old spell_code hash cannot be searched or suggested as a customer');
    $legacyScope=Contract::json(['party_phone_field'=>'spell_code']);
    $jobRow=fn($id)=>Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'id'=>$id]);
    $legacyJob=$sync->start($site,1,['parties'],'refresh',$key)['ids'][0];
    $jobRow($legacyJob)->update(['cursor'=>'2','pages'=>1,'scope_json'=>$legacyScope]);
    $calls=count($wire); $sync->page($site,$legacyJob,$api);
    rule($jobRow($legacyJob)->value('status')==='needs_review' && count($wire)===$calls,'obsolete page is retired before any source call');
    $jobRow($legacyJob)->update(['status'=>'queued','scope_json'=>Contract::json(['party_phone_field'=>'help_code'])]); // Partially indexed under the old masked-only help_code implementation.
    $visibleJob=array_column($sync->jobs($site),null,'resource')['parties']['job'];
    $replacement=$sync->start($site,1,['parties'],'refresh',$key)['ids'][0];
    rule($visibleJob['status']==='needs_review' && $replacement!==$legacyJob && (string)$jobRow($replacement)->value('cursor')==='1' && Contract::decode($jobRow($replacement)->value('scope_json'))['party_phone_field']==='help_code','one manual click replaces an old pending run from page one');
    $rawParty=['id'=>'PARTY1','code'=>'BUYER-7','name'=>'虚构往来单位','help_code'=>'138-0000-0007','spell_code'=>'13600000009','contact_phone'=>'13900000008','is_effective'=>1,'is_cust'=>1,'is_supplier'=>1,'account_no'=>'NEVER_RETURN'];
    $rawParty+=['credit_left'=>0,'empty_note'=>null,'new_payload'=>['active'=>false,'note'=>'扩展内容'],'privateKey'=>'DO_NOT_SHOW'];
    $ordinary=$rawParty;unset($ordinary['code']);$ordinary['spell_code']='CUS-007';
    $normal=OpenApi::normalize('parties',$ordinary,'help_code');
    rule($normal['code']==='CUS-007' && $normal['phone']==='13800000007','ordinary business code remains a code; only help_code is the telephone');
    $duplicate=ArchiveView::safe('parties',['help_code'=>'138-0000-0007','spell_code'=>'+13800000007','code'=>'138 0000 0007']);
    rule(count(array_unique($duplicate))===1 && $duplicate['help_code']==='138****0007','formatted copies of the agreed phone cannot bypass masking through other code fields');
    $rawList=[$rawParty]; $fieldCalls=0;
    $fieldApi=new OpenApi($configService->get($site,false)['profiles']['test'],$secret,function($path,$form) use(&$rawList,&$fieldCalls){
        rule($form['service']==='boyuan.custsuppliers.get' && $form['page_size']===100 && $form['page_no']===1,'only the scoped reference page may be read');$fieldCalls++;
        return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>$rawList,'total_results'=>count($rawList)]];
    });
    $legacyJson=$legacy;unset($legacyJson['source_page'],$legacyJson[ArchiveView::SNAPSHOT]);
    \addon\hsx_boyuan\app\support\CatalogStore::query('parties')->where('external_id','PARTY1')->update(['extra_json'=>Contract::json($legacyJson)]);
    denied(fn()=>$sync->detail($site,'parties','PARTY1','different',true,$fieldApi,true),'other account refused');
    denied(fn()=>$sync->detail($site,'parties','NO_LOCAL_ROW',$key,true,$fieldApi,true),'only an archived exact id can be inspected');
    rule($fieldCalls===0,'scope checks precede all remote reads');
    $callsBefore=$fieldCalls;
    $localDetail=$sync->detail($site,'parties','PARTY1',$key,true,$fieldApi);
    rule($localDetail['source']==='summary' && $localDetail['can_check_source'] && $fieldCalls===$callsBefore,'opening an old record never contacts the upstream');
    $oldDetail=$sync->detail($site,'parties','PARTY1',$key,true,$fieldApi,true);
    rule($oldDetail['source']==='boyuan' && $oldDetail['fields']['new_payload']['active']===false && array_key_exists('empty_note',$oldDetail['fields']) && $oldDetail['fields']['credit_left']===0 && $fieldCalls===$callsBefore+1,'old archive reads exact source row; returns every field including zero, null, false and unknown');
    rule(!str_contains(Contract::json($oldDetail),'DO_NOT_SHOW') && !str_contains(Contract::json($oldDetail),'NEVER_RETURN') && $oldDetail['fields']['help_code']===$rawParty['help_code'] && !$oldDetail['phone_needs_sync'],'an explicit exact-source read returns the agreed phone and still protects bank numbers and secrets');
    $summary=$sync->detail($site,'parties','PARTY1',$key,false,$fieldApi);
    rule($summary['source']==='summary' && $fieldCalls===$callsBefore+1,'selector-only role does not make source call');
    denied(fn()=>$sync->detail($site,'parties','PARTY1','OTHER',true,$fieldApi),'wrong connection cannot see stored detail');
    denied(fn()=>$sync->detail($site,'companies','PARTY1',$key,true,$fieldApi),'cannot use the same id in another resource');
    $rawList=[['id'=>'OTHER','name'=>'Never Show']];
    $moved=$sync->detail($site,'parties','PARTY1',$key,true,$fieldApi,true);
    rule($moved['source']==='summary' && $moved['notice']!=='' && !str_contains(Contract::json($moved),'Never Show'),'upstream page drift explicitly limited to own archive summary');
    $rawList=[$rawParty,$rawParty];$ambiguous=$sync->detail($site,'parties','PARTY1',$key,true,$fieldApi,true);
    rule($ambiguous['source']==='summary' && !str_contains(Contract::json($ambiguous),$rawParty['help_code']),'duplicate upstream identity never discloses a neighbour');
    $rawList=[$rawParty];$partyJob=$sync->start($site,1,['parties'],'refresh',$key)['ids'][0];
    $configService->save($site,1,['revision'=>$configService->get($site)['revision'],'profile'=>['party_phone_field'=>'contact_phone']]);
    $sync->page($site,$partyJob,$fieldApi);
    rule(!str_contains(Contract::json($sync->search($site,['resource'=>'parties'],true)),$rawParty['help_code']) && !str_contains(Contract::json($sync->detail($site,'parties','PARTY1',$key,true)),$rawParty['help_code']),'switching phone source does not expose another source in either local view');
    rule($sync->search($site,['resource'=>'parties'])['items'][0]['phone_state']==='refresh','ongoing jobs retain their selected field across config changes');
    $configService->save($site,1,['revision'=>$configService->get($site)['revision'],'profile'=>['party_phone_field'=>'help_code']]);
    $match=$sync->search($site,['resource'=>'parties','keyword'=>'13800000007']);
    rule($match['total']===1 && $match['items'][0]['phone_masked']==='138****0007' && $match['items'][0]['code']==='BUYER-7','exact phone and stable code are separate');
    rule($sync->search($site,['resource'=>'parties','keyword'=>'13900000008'])['total']===0,'no silent fallback to a different contact');
    rule($sync->search($site,['resource'=>'parties','keyword'=>'13600000009'])['total']===0 && $candidates->search($site,'supplier','party:'.$localParty)['match']==='not_found','ordinary spell_code is never a phone candidate');
    Db::name('erp_party')->where('id',$localParty)->update(['contact_mobile'=>'13800000007']);
    foreach(['customer','supplier'] as $role) rule($candidates->search($site,$role,'party:'.$localParty)['match']==='unique','exact help_code matches the correct '.$role);
    $stored=Contract::json($rows()->find());
    rule(Contract::decode($rows()->value('extra_json'))[ArchiveView::PARTY_PHONE]===$rawParty['help_code'] && !str_contains($stored,'13900000008'),'local archive preserves the exact agreed phone, not the unrelated contact');
    foreach(['customer','supplier'] as $role){
        $list=$sync->search($site,['resource'=>'parties','keyword'=>'13800000007','role'=>$role],true);
        rule($list['total']===1 && $list['items'][0]['phone_number']===$rawParty['help_code'] && !isset($list['items'][0]['phone_masked']) && !array_key_exists(ArchiveView::PARTY_PHONE,$list['items'][0]['extra']),'archive list returns original on the first read for '.$role);
    }
    $readApi=static function(array $input) use($app,$key):array {
        $old=$app->request;
        try {
            $app->instance('request',(new \app\Request())->withServer(['REQUEST_METHOD'=>'POST'])->withPost($input+['resource'=>'parties','expected_connection_key'=>$key]));
            $result=(new \addon\hsx_boyuan\app\adminapi\controller\Workbench($app))->references();
            return $result->getData()['data'];
        } finally {$app->instance('request',$old);}
    };
    rule($readApi(['operation'=>'archive_list'])['data']['items'][0]['phone_number']===$rawParty['help_code'] && $readApi(['operation'=>'archive_detail','id'=>'PARTY1'])['fields']['help_code']===$rawParty['help_code'],'both real PC endpoints return local full number on first read');
    // Only the site/connection's relationship projection may feed the PC tree. No sync or details on browse.
    $cat=static fn($id,$scope,$account,$parent,$known)=>['external_id'=>$id,'site_id'=>$scope,'connection_key'=>$account,'code'=>'0'.$id,
        'name'=>'分类'.$id,'enabled'=>1,'seen_run'=>'seed','extra_json'=>Contract::json(['parent_id'=>$parent,'status_known'=>$known,ArchiveView::SNAPSHOT=>'NOT_TREE_DATA'])];
    CatalogStore::query('categories')->insertAll([$cat('CHILD',$site,$key,'ROOT',false),$cat('ROOT',$site,$key,null,true),
        $cat('FOREIGN_SITE',$site+1,$key,null,true),$cat('FOREIGN_ACCOUNT',$site,'other',null,true)]);
    $tree=$readApi(['operation'=>'category_directory']); $treeById=array_column($tree['items'],null,'id');
    rule(!$tree['list_only'] && $tree['connection_key']===$key && $tree['resource']==='categories' && $tree['source']==='local_archive' && count($treeById)===2,'one complete read scoped to current site and connection');
    rule($treeById['CHILD']['parent_id']==='ROOT' && $treeById['CHILD']['status']==='unknown' && $treeById['ROOT']['parent_id']===null && $treeById['ROOT']['status']==='enabled','tree keeps true parent identity and unknown source status');
    rule(!str_contains(Contract::json($tree),'NOT_TREE_DATA') && !str_contains(Contract::json($tree),'FOREIGN_'),'no archive snapshot or other tenant returned');
    denied(fn()=>$sync->categoryDirectory($site,'other'),'tree refuses a changed account');
    denied(fn()=>$sync->categoryDirectory($site,''),'tree requires an explicit current account');
    $bulk=[];for($i=0;$i<1999;$i++)$bulk[]=$cat('CAT'.$i,$site,$key,null,true);
    foreach(array_chunk($bulk,250) as $chunk)CatalogStore::query('categories')->insertAll($chunk);
    $big=$readApi(['operation'=>'category_directory']);
    rule($big['list_only'] && $big['items']===[] && $sync->search($site,['resource'=>'categories','expected_connection_key'=>$key,'page'=>21,'limit'=>100])['total']===2001,'large archives keep a complete searchable list, never a partial tree');
    rule(!str_contains(Contract::json($readApi(['cached'=>true,'archiveReader'=>true])),$rawParty['help_code']),'a selector cannot promote its own projection via posted fields');
    rule(!str_contains(Contract::json(Contract::redact(['phone_number'=>$rawParty['help_code'],ArchiveView::PARTY_PHONE=>$rawParty['help_code']])),$rawParty['help_code']),'the diagnostic projection does not echo the archive phone');
    $limited=$sync->detail($site,'parties','PARTY1',$key,false,$fieldApi);
    rule(!str_contains(Contract::json([$limited,$match,$candidates->search($site,'customer','party:'.$localParty)]),$rawParty['help_code']),'selection-only requests and their generic extras do not return the archive-only raw field');
    foreach([['','empty'],['VIP-甲','check'],[null,'empty']] as [$val,$state]) {
        $row=array_replace($rawParty,['help_code'=>$val]);unset($row['code']);
        $v=OpenApi::normalize('parties',$row,'help_code');
        rule($v['id']==='PARTY1' && $v['code']==='PARTY1' && $v['phone']==='' && $v['extra']['party_phone_state']===$state,'keep identity and distinguish empty or unverified');
        rule(array_key_exists(ArchiveView::PARTY_PHONE,$v['extra']) && $v['extra'][ArchiveView::PARTY_PHONE]===$val,'retain original even if empty or non-numeric');
    }
    $missing=$rawParty;unset($missing['help_code']);
    rule(OpenApi::normalize('parties',$missing,'help_code')['extra'][ArchiveView::PARTY_PHONE]===null,'missing source must not fall back to another phone');
    $before=$fieldCalls;
    $storedView=$sync->detail($site,'parties','PARTY1',$key,true,$fieldApi);
    rule($storedView['source']==='snapshot' && !$storedView['phone_needs_sync'] && $storedView['fields']['help_code']===$rawParty['help_code'] && $storedView['fields']['new_payload']['note']==='扩展内容' && $fieldCalls===$before,'full detail shows the same original phone without any upstream lookup');
    $e=Contract::decode($rows()->value('extra_json'));unset($e[ArchiveView::SNAPSHOT]);$rows()->update(['extra_json'=>Contract::json($e)]);
    rule($sync->detail($site,'parties','PARTY1',$key,true,$fieldApi)['fields']['help_code']===$rawParty['help_code'] && $fieldCalls===$before,'telephone remains displayable without decryption, even with no snapshot');
    $literal=" 001-2345-6789 ";$rawList=[array_replace($rawParty,['help_code'=>$literal])];
    $run=$sync->start($site,1,['parties'],'refresh',$key)['ids'][0];$sync->page($site,$run,$fieldApi);
    rule($sync->search($site,['resource'=>'parties','keyword'=>'00123456789'],true)['items'][0]['phone_number']===$literal,'format, leading zeroes and spacing are retained on refresh and remain searchable');
    $rawList=[$missing];$run=$sync->start($site,1,['parties'],'refresh',$key)['ids'][0];$sync->page($site,$run,$fieldApi);
    $cleared=$sync->search($site,['resource'=>'parties'],true)['items'][0];
    rule($cleared['phone_number']===null && $cleared['phone_state']==='empty' && $sync->search($site,['resource'=>'parties','keyword'=>'00123456789'],true)['total']===0,'a removed phone clears the old original and matching index');
    rule(!str_contains(Contract::json($match),'_boyuan_view_cipher') && !str_contains($stored,'扩展内容'),'encrypted display snapshot never leaks into selectors or clear storage');
    foreach(OpenApi::RESOURCES as $resource=>[$title,$service]){
        $dummy=new OpenApi($profile,$secret,function($path,$form) use($resource,$service){
            rule($form['service']===$service,'each catalog uses its own official read service');
            rule($resource==='capital_accounts'?!isset($form['page_no']):(int)$form['page_no']===2,'preserve provider pagination policy');
            if($resource==='members')rule($form['register_date_start']==='2001-01-01' && $form['register_date_end']==='2026-09-01','member detail uses archived sync range');
            return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>[[$resource==='capital_accounts'?'account_id':'id'=>'X1','future'=>'keep']]]];
        });
        rule($dummy->archiveFieldsAt($resource,'X1',2,['since'=>'2001-01-01','until'=>'2026-09-01'])['future']==='keep','undocumented fields survive in '.$resource);
    }
    denied(fn()=>$fieldApi->archiveFieldsAt('purchase_inbound','PARTY1',1,[]),'no transaction API available to archive browser');
    $pause=$sync->start($site,1,['brands'],'refresh',$key)['ids'][0];
    $configService->save($site,1,['revision'=>$configService->get($site)['revision'],'profile'=>['app_secret'=>str_repeat('B',32)]]);
    $calls=count($wire);
    rule(!$sync->page($site,$pause,$api) && count($wire)===$calls && !$sync->jobs($site)[0]['ready'],'rotated credential suspends pending work until probed again');
    $fresh=$configService->get($site); $configService->save($site,1,['revision'=>$fresh['revision'],'profile'=>['app_id'=>'999999']]);
    rule($sync->search($site,['resource'=>'products'])['total']===0,'new app identity has separate archives');
    rule(!$sync->page($site,$bad,$api),'old connection job cannot run in new app');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'external_calls'=>0,'business_database_modified'=>false])."\n";
} catch(Throwable $e) { $failed=true; fwrite(STDERR,'FAIL '.get_class($e).': '.($e instanceof Problem?$e->reason:$e->getMessage()).' at '.basename($e->getFile()).':'.$e->getLine()."\n"); }
finally {if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
