<?php
declare(strict_types=1);

// Fresh, owned random local schema and synthetic business facts only.
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $fn)if(function_exists($fn))exit("Disable network functions before testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\service\{Context,MerchantService,ProviderChannelService};
use addon\hsx_lead\app\service\cooperation\{ConfigService,PartnerService,CooperationService,Store,CapabilityService};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

$checks=0;$pdo=null;$schema='';$failed=false;
function deliveryCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException('Assertion failed: '.$message);$GLOBALS['checks']++;}
function deliveryDenied(callable $call,string $message):void{try{$call();}catch(CommonException $error){deliveryCheck(true,$message);return;}throw new RuntimeException('Expected denial: '.$message);}
function externalDelivery(array $result):array{$count=count($result['data']);$result['data']=array_values(array_filter($result['data'],static fn(array $row):bool=>empty($row['is_internal'])));$result['total']-=$count-count($result['data']);return$result;}
try{
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);$now=time();$installedTables=Db::query('SHOW TABLES');
    Db::name('sys_area')->insertAll([
        ['id'=>910200,'pid'=>910000,'name'=>'Second City','level'=>2,'status'=>1],
        ['id'=>910201,'pid'=>910200,'name'=>'Second District','level'=>3,'status'=>1],
        ['id'=>920000,'pid'=>0,'name'=>'Other Province','level'=>1,'status'=>1],
        ['id'=>920100,'pid'=>920000,'name'=>'Other City','level'=>2,'status'=>1],
        ['id'=>920101,'pid'=>920100,'name'=>'Other District','level'=>3,'status'=>1],
    ]);
    foreach(range(104,109) as $mid)Db::name('member')->insert(['site_id'=>$site,'member_id'=>$mid,'nickname'=>'Synthetic '.$mid,'mobile'=>'19900000'.$mid]);
    $ctx=new Context($site,1);$configService=new ConfigService($ctx);$config=$configService->get();$config['enabled']=true;$config['publishing_required']=false;$config['fee_confirmed']=true;$configService->save($config);
    $pctx=new Context($site,0,101);$p=new CooperationService($pctx);
    $models=$p->catalog(['keyword'=>'iPhone']);deliveryCheck(count($models)>0,'valid unregistered member can search common delivery models');$model=$models[0];
    deliveryDenied(fn()=>(new CooperationService(new Context($site,0,103)))->catalog(),'foreign-site member cannot browse pre-registration catalog');
    $base=['name'=>'Publisher','contact_name'=>'Operator','mobile'=>'18800000001','province_id'=>910000,'city_id'=>910200,'district_id'=>910201,'address'=>'Synthetic road','provider'=>'','business_capabilities'=>[]];
    $profile=new PartnerService($pctx);$publisher=$profile->saveProfile($base);$pid=(int)$publisher['id'];
    deliveryCheck($publisher['connection']['connected']===false&&$publisher['provider_requested']==='','profile does not require an ERP provider or authorization');
    deliveryCheck($publisher['manual_models']===[]&&$publisher['capability_source']==='none','publisher-only merchant can register without pretending delivery capability');
    $merchants=new MerchantService($ctx);
    deliveryCheck($merchants->detail($pid)['ready_state']==='capability_required','platform asks an unconfigured delivery shop to choose models, not to force ERP');
    deliveryCheck(Data::query('connection',$site)->count()===0,'registering without ERP creates no connection row');
    $make=static function(int $mid,string $name,int $city,int $district)use($site,$base,$model):array{
        return(new PartnerService(new Context($site,0,$mid)))->saveProfile(array_replace($base,['name'=>$name,'mobile'=>'18800000'.$mid,'city_id'=>$city,'district_id'=>$district,'business_capabilities'=>[['business_type'=>'purchase','option_ids'=>[$model['id']],'items'=>[['title'=>'FORGED TITLE','brand'=>'FORGED BRAND']]]]]));
    };
    $county=$make(102,'County shop',910100,910101);$countyId=(int)$county['id'];
    $city=$make(104,'City shop',910100,910102);$cityId=(int)$city['id'];
    $province=$make(105,'Province shop',910200,910201);$provinceId=(int)$province['id'];
    deliveryCheck($county['manual_models'][0]===$model,'manual model labels are rebuilt from the current catalog, not client labels');
    deliveryCheck($county['manual_model_count']===1&&$county['inventory_count']===0&&$county['capability_source']==='manual','declared capabilities do not fabricate ERP inventory count or inventory-based grade');
    $ready=$merchants->detail($countyId);deliveryCheck($ready['ready_state']==='ready'&&$ready['inventory_count']===0&&!$ready['membership_active'],'platform classifies free manual delivery as ready without inventing stock or paid rank');
    deliveryCheck($merchants->lists(['ready_state'=>'ready'])['total']===3&&$merchants->lists(['ready_state'=>'capability_required'])['total']===1,'platform readiness filter includes manual providers and isolates shops lacking capability');
    deliveryCheck((new CapabilityService($ctx))->models($countyId)===$county['manual_models'],'framework config stores delivery declaration without new schema');
    deliveryDenied(fn()=>(new CapabilityService($pctx))->save($countyId,['business_capabilities'=>[]]),'capability helper cannot be reused to edit another member shop');
    $countyProfile=new PartnerService(new Context($site,0,102));
    deliveryDenied(fn()=>$countyProfile->saveProfile(array_replace($base,['business_capabilities'=>[['business_type'=>'purchase','option_ids'=>['fake-model']]]])),'unknown catalog model cannot become a declared capability');
    deliveryCheck((new CapabilityService($ctx))->models($countyId)===$county['manual_models'],'invalid declaration does not erase the prior profile or capability');
    $countyProfile->saveProfile(array_replace($base,['name'=>'County shop','city_id'=>910100,'district_id'=>910101]));
    deliveryCheck((new CapabilityService($ctx))->models($countyId)===[],'explicit empty model list clears declared capability');
    $county=$make(102,'County shop',910100,910101);
    $input=['request_id'=>'delivery-fixture-0001','customer_name'=>'Customer','mobile'=>'13800000000','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'business_type'=>'purchase','model_ids'=>[$model['id']],'attachments'=>[],'consent'=>true];
    $lead=$p->create($input);$id=(int)$lead['id'];
    deliveryCheck($lead['can_cancel']&&$lead['can_assign']&&$lead['status']==='selecting','new unassigned lead exposes cancel and choose-store actions');
    $found=externalDelivery($p->stores([],$id));
    deliveryCheck($found['scope']==='district'&&array_column($found['data'],'id')===[$countyId],'default search starts in the customer county, not publisher address');
    deliveryCheck($found['region']['district_id']===910101,'response identifies the actual customer search region');
    deliveryCheck($p->stores(['province_id'=>920000,'city_id'=>920100,'district_id'=>920101],$id)['scope']==='district','lead parameters cannot replace its customer location');
    deliveryCheck($found['data'][0]['inventory_count']===0&&count($found['data'][0]['match_models'])===1,'manual declaration qualifies model matching without claiming stock');
    Data::query('merchant',$site)->where('id',$countyId)->update(['status'=>0]);
    $found=externalDelivery($p->stores([],$id));deliveryCheck($found['scope']==='city'&&array_column($found['data'],'id')===[$cityId],'county without external candidates automatically expands to customer city');
    Data::query('merchant',$site)->where('id',$cityId)->update(['status'=>0]);
    $found=externalDelivery($p->stores([],$id));deliveryCheck($found['scope']==='province'&&array_column($found['data'],'id')===[$provinceId],'city without external candidates automatically expands to customer province');
    foreach([$countyId,$cityId] as $mid)Data::query('merchant',$site)->where('id',$mid)->update(['status'=>1]);
    deliveryCheck(externalDelivery($p->stores(['province_id'=>910000,'scope'=>'province']))['total']===3,'province alone can query every eligible external shop in that province');
    deliveryCheck(externalDelivery($p->stores(['province_id'=>910000,'city_id'=>910100,'scope'=>'city']))['total']===2,'province and city can query without selecting a county');
    deliveryCheck(externalDelivery($p->stores(['province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'scope'=>'district']))['total']===1,'county can query exact customer area');
    deliveryCheck($p->stores(['province_id'=>910000,'scope'=>'province','keyword'=>'City shop'])['total']===1,'store keyword search intersects the selected region');
    deliveryCheck($p->stores(['province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'scope'=>'district','keyword'=>'City shop'])['total']===0,'explicit county search does not silently widen its scope');
    deliveryCheck($p->stores(['keyword'=>'City shop'],$id)['scope']==='city','automatic fallback applies after the requested keyword and delivery capability');
    foreach([['city_id'=>910100],['province_id'=>910000,'city_id'=>920100],['province_id'=>910000,'city_id'=>910100,'district_id'=>910201],['province_id'=>[]],['province_id'=>'910000 OR 1=1'],['province_id'=>910000,'scope'=>'district']] as $bad)deliveryDenied(fn()=>$p->stores($bad),'invalid and mismatched search regions are rejected');
    $r=new CooperationService(new Context($site,0,102));
    deliveryDenied(fn()=>$r->action($id,['action'=>'cancel','assignment_version'=>0]),'unassigned other merchant cannot cancel a publisher lead');
    deliveryDenied(fn()=>$p->action($id,['action'=>'cancel','assignment_version'=>1]),'stale cancel version cannot mutate current lead');
    $cancelled=$p->action($id,['action'=>'cancel','assignment_version'=>0]);
    deliveryCheck($cancelled['status']==='cancelled'&&!$cancelled['can_assign']&&!$cancelled['can_cancel']&&(int)$cancelled['assignment_version']===1,'cancel closes an unselected lead and increments version once');
    $p->action($id,['action'=>'cancel','assignment_version'=>0]);
    deliveryCheck(Data::query('audit',$site)->where('entity_type','collaboration')->where('entity_id',$id)->where('action','cancel')->count()===1,'retrying cancellation is an idempotent acknowledgement, not another operation');
    deliveryDenied(fn()=>$p->assign($id,['merchant_id'=>$countyId,'assignment_version'=>1]),'cancelled lead cannot later be assigned');
    deliveryCheck($p->lists(['status'=>'closed'])['total']===1,'cancelled lead remains in history and is searchable as closed');
    $lead=$p->create(array_replace($input,['request_id'=>'delivery-fixture-0002']));$assignedId=(int)$lead['id'];$p->assign($assignedId,['merchant_id'=>$countyId,'assignment_version'=>0]);
    deliveryDenied(fn()=>$p->action($assignedId,['action'=>'cancel','assignment_version'=>1]),'assigned lead uses withdraw instead of direct cancellation');
    $p->action($assignedId,['action'=>'withdraw','assignment_version'=>1]);
    deliveryCheck($p->detail($assignedId)['can_cancel'],'withdraw restores selecting and permits a deliberate separate cancellation');
    $p->assign($assignedId,['merchant_id'=>$countyId,'assignment_version'=>2]);$r->action($assignedId,['action'=>'accept','assignment_version'=>3]);
    deliveryDenied(fn()=>$p->action($assignedId,['action'=>'cancel','assignment_version'=>3]),'accepted lead cannot be cancelled out from under the receiving merchant');
    $r->action($assignedId,['action'=>'no_deal','assignment_version'=>3]);
    deliveryCheck(Store::query('coop_order',$site)->count()===0,'manual-capability merchant completes no-deal feedback without financial entries or ERP');
    $empty=$p->storeInventory($countyId);deliveryCheck(!$empty['has_connection']&&$empty['data']===[]&&$empty['total']===0&&$empty['message']!=='','no-ERP shop offers an honest empty inventory reference and explanation');
    $connection=(int)Data::query('connection',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$countyId,'provider'=>'soujike','enabled'=>1,'last_sync_status'=>'completed','last_sync_at'=>$now]);
    $run=(int)Data::query('sync_run',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$countyId,'connection_id'=>$connection,'config_version'=>1,'status'=>'completed','finished_at'=>$now]);
    $old=(int)Data::query('sync_run',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$countyId,'connection_id'=>$connection,'config_version'=>1,'status'=>'completed','finished_at'=>$now-30]);
    Data::query('connection',$site)->where('id',$connection)->update(['published_run_id'=>$run]);
    $stock=static function(string $key,array $replace=[])use($site,$countyId,$connection,$run,$now,$model):int{return(int)Data::query('inventory',$site)->insertGetId(array_replace(['site_id'=>$site,'merchant_id'=>$countyId,'connection_id'=>$connection,'sync_run_id'=>$run,'source_key'=>$key,'model'=>$model['title'],'brand'=>$model['brand'],'memory'=>'256GB','color'=>'黑色','grade'=>'95新','price'=>'2345.00','quantity'=>'1.000','saleable_quantity'=>'1.000','availability'=>'available','fetched_at'=>$now,'imei'=>'PRIVATE_IMEI','trade_price'=>'1234.00','snapshot_json'=>'{"secret":"PRIVATE_SOURCE"}','source_status_json'=>Data::json(['_inventory'=>['listing_status'=>'listed','stock_status'=>'in_stock','sale_state'=>'unsold']])],$replace));};
    $one=$stock('active-1');$two=$stock('active-2',['color'=>'银色']);$stock('old',['sync_run_id'=>$old]);
    $stock('sold',['availability'=>'unavailable','source_status_json'=>Data::json(['_inventory'=>['listing_status'=>'listed','stock_status'=>'out_of_stock','sale_state'=>'sold']])]);
    $stock('zero',['quantity'=>'0.000']);$stock('unknown',['source_status_json'=>'{}']);$stock('off',['source_status_json'=>Data::json(['_inventory'=>['listing_status'=>'unlisted','stock_status'=>'in_stock']])]);$stock('stale',['fetched_at'=>$now-48*3600]);
    $snapshot=$p->storeInventory($countyId,['limit'=>1]);
    deliveryCheck($snapshot['has_connection']&&$snapshot['snapshot_at']===$now&&$snapshot['total']===2&&count($snapshot['data'])===1,'public inventory exposes only the complete published fresh sellable generation with pagination');
    $second=$p->storeInventory($countyId,['limit'=>1,'page'=>2]);deliveryCheck($snapshot['data'][0]['id']!==$second['data'][0]['id'],'inventory next page is stable and distinct');
    deliveryCheck($p->storeInventory($countyId,['keyword'=>'银色'])['total']===1,'public inventory searches safe product attributes');
    deliveryCheck($p->storeInventory($countyId,['keyword'=>"%' OR 1=1"])['total']===0,'inventory search treats SQL metacharacters as text');
    deliveryCheck(!preg_match('/PRIVATE_|imei|trade_price|source_status_json|snapshot_json|credentials|mobile/',Data::json($snapshot)),'public projection excludes device identifiers, costs, private source data, credentials and contacts');
    deliveryCheck($snapshot['data'][0]['is_sellable']===true&&$snapshot['data'][0]['sale_status_label']==='在售','visible items include safe explicit saleability labels');
    deliveryCheck($r->storeInventory($countyId)['total']===2,'merchant can browse the same safe current reference for its own shop');
    deliveryCheck($p->storeInventory($countyId,['inventory_scope'=>'all'])['total']===2,'client diagnostic scope cannot expand public inventory to sold or unknown devices');
    $make(102,'County shop',910100,910101);
    deliveryCheck(Data::query('connection',$site)->where('id',$connection)->count()===1,'saving no-provider/manual profile never deletes an existing ERP authorization');
    deliveryCheck((new PartnerService(new Context($site,0,102)))->bootstrap()['profile']['inventory_count']===2,'manual declaration and ERP count coexist without inflating real stock');
    Data::query('connection',$site)->where('id',$connection)->update(['last_sync_status'=>'failed']);
    deliveryCheck($merchants->detail($countyId)['ready_state']==='ready','ERP failure does not suppress explicitly declared non-ERP delivery capability');
    Data::query('connection',$site)->where('id',$connection)->update(['last_sync_status'=>'completed']);
    $policy=new ProviderChannelService($site);$channelConfig=$policy->configuration();$channelItems=$channelConfig['items'];
    foreach($channelItems as &$item)if($item['key']==='soujike')$item['enabled']=false;unset($item);
    $policy->save($ctx,['revision'=>$channelConfig['revision'],'items'=>$channelItems]);
    deliveryCheck($merchants->detail($countyId)['ready_state']==='ready','closed ERP channel does not suppress independent manual capability');
    deliveryCheck($p->storeInventory($countyId)['data']===[],'closed ERP channel hides its public inventory reference');
    $channelConfig=$policy->configuration();$channelItems=$channelConfig['items'];foreach($channelItems as &$item)if($item['key']==='soujike')$item['enabled']=true;unset($item);$policy->save($ctx,['revision'=>$channelConfig['revision'],'items'=>$channelItems]);
    Data::query('connection',$site)->where('id',$connection)->update(['enabled'=>0]);
    deliveryCheck($p->storeInventory($countyId)['total']===0,'paused inventory is not offered as a current public stock promise');
    Data::query('connection',$site)->where('id',$connection)->update(['enabled'=>1]);
    Store::query('partner',$site)->where('merchant_id',$countyId)->update(['blocked_at'=>$now]);
    deliveryCheck($merchants->detail($countyId)['ready_state']==='paused','blacklisted merchant cannot appear ready even with declared models');
    deliveryDenied(fn()=>$p->storeInventory($countyId),'blocked merchant inventory is not publicly browsable');
    deliveryDenied(fn()=>$r->storeInventory($provinceId),'blocked viewer cannot enumerate other merchant inventory');
    Store::query('partner',$site)->where('merchant_id',$countyId)->update(['blocked_at'=>0]);
    Data::query('merchant',$site)->where('id',$countyId)->update(['status'=>0]);
    deliveryCheck($merchants->detail($countyId)['ready_state']==='paused','disabled merchant cannot appear ready even with declared models');
    deliveryDenied(fn()=>$p->storeInventory($countyId),'paused merchant inventory is not publicly browsable');
    deliveryDenied(fn()=>$p->storeInventory(999999),'unknown or foreign target is not a valid stock reference');
    Data::query('merchant',$site)->where('id',$countyId)->update(['status'=>1]);Db::name('member')->where('site_id',$site)->where('member_id',102)->update(['status'=>0]);
    deliveryCheck($merchants->detail($countyId)['ready_state']==='member_required','invalid member binding cannot appear ready despite declaration');
    Db::name('member')->where('site_id',$site)->where('member_id',102)->update(['status'=>1]);
    $foreign=(int)Data::query('merchant',$site+1)->insertGetId(['site_id'=>$site+1,'name'=>'Foreign shop','member_id'=>103,'status'=>1]);
    deliveryDenied(fn()=>$p->storeInventory($foreign),'a real other-site merchant cannot be selected by numeric ID');
    deliveryCheck(Db::query('SHOW TABLES')===$installedTables,'manual capability and stock browsing add no tables beyond the installed schema');
    leadAssertIsolated($schema);echo "PASS cooperation delivery: {$checks} checks; owned random local schema, synthetic data, no ERP, notification or payment calls\n";
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL cooperation delivery: '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine()."\n");}
finally{if($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema);}
exit($failed?1:0);
