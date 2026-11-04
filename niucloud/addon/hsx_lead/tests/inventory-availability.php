<?php
declare(strict_types=1);

/** Isolated, synthetic regression for saleability, quantities and publication boundaries. */
if (PHP_SAPI !== 'cli' || !in_array('--isolated-local-mysql', $argv, true)) exit("Requires --isolated-local-mysql\n");
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\ReadService;
use addon\hsx_lead\app\service\SyncService;
use addon\hsx_lead\app\service\cooperation\PartnerService;
use addon\hsx_lead\app\provider\ProviderRegistry;
use addon\hsx_lead\app\provider\TransportContract;
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\support\Cipher;
use core\exception\CommonException;
use think\facade\Db;

final class AvailabilityTransport implements TransportContract
{
    public int $calls=0;
    public function __construct(private array $responses){}
    public function post(string $endpoint,array $headers,array $body):array
    {
        $this->calls++;
        if($this->responses===[])throw new RuntimeException('Unexpected synthetic transport request');
        $response=array_shift($this->responses);
        if($response instanceof Throwable)throw $response;
        if(!is_array($response))throw new RuntimeException('Missing synthetic provider response');
        return $response;
    }
    public function remaining():int{return count($this->responses);}
}

$checks=0; $failed=false; $pdo=null; $schema='';
function availableCheck(bool $condition,string $label):void
{
    if(!$condition) throw new RuntimeException('Assertion failed: '.$label);
    $GLOBALS['checks']++;
}
function availableDenied(callable $callback,string $label):void
{
    try{$callback();}catch(CommonException $error){availableCheck(true,$label);return;}
    throw new RuntimeException('Expected rejection: '.$label);
}

try {
    [$app,$pdo,$schema,$site]=leadBoot();
    leadAssertIsolated($schema);
    $now=time();
    $insert=static fn(string $table,array $row):int=>(int)Db::name('hsx_lead_'.$table)->insertGetId(
        ['site_id'=>$site,'create_time'=>$now,'update_time'=>$now]+$row
    );
    $merchant=$insert('merchant',['name'=>'Availability fixture merchant','member_id'=>101,
        'province_id'=>910000,'city_id'=>910100,'district_id'=>910101]);
    // enabled is only the automatic scheduler switch, not a ban on manual snapshots.
    $connection=$insert('connection',['merchant_id'=>$merchant,'provider'=>'wanji','enabled'=>0,
        'last_sync_at'=>$now,'last_sync_status'=>'completed','sync_interval_minutes'=>60]);
    $oldRun=$insert('sync_run',['merchant_id'=>$merchant,'connection_id'=>$connection,'config_version'=>1,
        'status'=>'completed','finished_at'=>$now]);
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['published_run_id'=>$oldRun]);
    $stock=static function(string $key,string $availability,?string $quantity,?string $saleable,string $listing='unknown',array $extra=[])use($insert,$merchant,$connection,$oldRun,$now):int{
        return $insert('inventory',array_replace([
            'merchant_id'=>$merchant,'connection_id'=>$connection,'sync_run_id'=>$oldRun,
            'source_key'=>$key,'source_id'=>$key,'source_store_id'=>'fixture-store',
            'model'=>'iPhone 16','memory'=>'256GB','color'=>'黑色','grade'=>'95新','price'=>'3000.00',
            'quantity'=>$quantity,'saleable_quantity'=>$saleable,'availability'=>$availability,
            'source_status_json'=>Data::json(['_inventory'=>['listing_status'=>$listing,
                'stock_status'=>$saleable!==null?(bccomp($saleable,'0',3)>0?'in_stock':'out_of_stock'):'unknown',
                'quantity_basis'=>'synthetic-source-field']]),
            'snapshot_json'=>'{"private":"PRIVATE_AVAILABILITY_SOURCE_MARKER"}',
            'fetched_at'=>$now,
        ],$extra));
    };
    $active=$stock('ACTIVE','available','3.000','2.000','listed');
    $off=$stock('OFF','unavailable','1.000','1.000','unlisted');
    $sold=$stock('SOLD','unavailable','0.000','0.000','listed');
    $zero=$stock('ZERO','available','0.000','0.000','listed');
    $unknown=$stock('UNKNOWN','unknown',null,null);
    $reserved=$stock('RESERVED','available','5.000','0.000','listed');
    $unknownListing=$stock('UNKNOWN_LISTING','unknown','5.000','5.000');
    $unknownStock=$stock('UNKNOWN_STOCK','available',null,null,'listed');
    $admin=new ReadService(new Context($site,1));
    $member=new ReadService(new Context($site,0,101));
    $otherMember=new ReadService(new Context($site,0,102));
    $all=$admin->inventory(['inventory_scope'=>'all','limit'=>100]);
    availableCheck($all['total']===8,'explicit administrative all view retains all current source states for checking');
    $rows=array_column($all['data'],null,'id');
    availableCheck($rows[$active]['availability']==='available','read projection preserves normalized available state');
    availableCheck($rows[$off]['availability']==='unavailable','read projection preserves source unavailable state');
    availableCheck($rows[$unknown]['availability']==='unknown','unknown availability is not silently converted to saleable');
    availableCheck($rows[$active]['quantity']==='3.000','provided stock quantity remains exact decimal data');
    availableCheck($rows[$unknown]['quantity']===null,'unprovided quantity stays null instead of fabricated zero or one');
    availableCheck(($rows[$active]['sale_status']??'')==='on_sale' && ($rows[$active]['is_sellable']??false)===true,
        'confirmed listed positive stock has an explicit saleable projection');
    availableCheck(($rows[$off]['sale_status']??'')==='off_shelf' && ($rows[$off]['is_sellable']??true)===false,
        'unlisted is distinct from a sold transaction');
    availableCheck(($rows[$sold]['sale_status']??'')==='sold_out' && ($rows[$sold]['is_sellable']??true)===false,
        'zero source stock is explicitly unavailable without claiming a verified sale');
    availableCheck(($rows[$unknown]['sale_status']??'')==='unknown' && ($rows[$unknown]['is_sellable']??true)===false,
        'source unknown state is explicitly awaiting verification');
    availableCheck(is_string($rows[$active]['sale_status_label']??null) && $rows[$active]['sale_status_label']!==''
        && is_string($rows[$active]['stock_status_label']??null) && $rows[$active]['stock_status_label']!=='',
        'readable listing and stock labels are provided rather than unlabelled source enum keys');
    availableCheck(!preg_match('/已成交|已售出/u',(string)($rows[$off]['sale_status_label']??'').(string)($rows[$sold]['sale_status_label']??'')),
        'unlisted or stock-out labels do not assert a financial transaction occurred');
    availableCheck(!str_contains(json_encode($all),'PRIVATE_AVAILABILITY_SOURCE_MARKER')
        && !str_contains(json_encode($all),'snapshot_json') && !str_contains(json_encode($all),'source_status_json'),
        'list projection never exposes raw source snapshots or internal source status JSON');
    $default=$admin->inventory(['limit'=>100]);
    availableCheck($default['total']===1 && array_column($default['data'],'id')===[$active],
        'default list keeps only confirmed listed available stock, not off/sold/zero/unknown/reserved');
    availableCheck($admin->inventory(['inventory_scope'=>'sellable','limit'=>100])['total']===1,
        'explicit saleable filter equals the safe default');
    availableCheck($admin->inventory(['keyword'=>'iPhone 16','limit'=>100])['total']===1,
        'keyword search cannot expand default scope to unavailable stock');
    availableCheck($admin->inventory(['merchant_id'=>$merchant,'limit'=>100])['total']===1,
        'merchant selection cannot expand default saleability scope');
    availableCheck(array_column($admin->inventory(['inventory_scope'=>'off_shelf'])['data'],'id')===[$off],
        'off-shelf scope reflects explicit unlisting, not every unavailable row');
    $stockOutIds=array_column($admin->inventory(['inventory_scope'=>'sold_out','limit'=>100])['data'],'id');
    sort($stockOutIds); $expectedStockOut=[$sold,$zero,$reserved];sort($expectedStockOut);
    availableCheck($stockOutIds===$expectedStockOut,'stock-out scope distinguishes listed records with zero effective stock');
    $unverifiedIds=array_column($admin->inventory(['inventory_scope'=>'unverified','limit'=>100])['data'],'id');
    sort($unverifiedIds);$expectedUnverified=[$unknown,$unknownListing,$unknownStock];sort($expectedUnverified);
    availableCheck($unverifiedIds===$expectedUnverified,'unverified scope does not mislabel unknown listings or quantities as saleable');
    availableCheck($member->inventory([],$merchant)['total']===1,
        'merchant member inventory shares the confirmed saleable default');
    availableCheck($member->inventory(['inventory_scope'=>'all'],$merchant)['total']===1,
        'member cannot expand own view to unavailable or unverified records with all scope');
    $partnerInventory=static fn(int $merchantId):array=>(new PartnerService(new Context($site,1)))->inventory($merchantId);
    availableCheck(count($partnerInventory($merchant))===0,'paused automatic connection does not promise cooperation delivery capability');
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['enabled'=>1]);
    availableCheck(count($partnerInventory($merchant))===1,'current cooperation merchant capability counts only saleable stock');
    availableCheck($admin->inventory(['inventory_scope'=>'all','page'=>1,'limit'=>3])['total']===8
        && count($admin->inventory(['inventory_scope'=>'all','page'=>1,'limit'=>3])['data'])===3,
        'administrative status filtering happens before pagination and totals');
    availableDenied(fn()=>$otherMember->inventory([],$merchant),'other member cannot select a foreign merchant');
    availableDenied(fn()=>$member->inventory(),'member cannot use the shared admin inventory entry');
    availableDenied(fn()=>$admin->inventory(['inventory_scope'=>'all OR 1=1']),'unknown status filter is rejected, not SQL-expanded');
    $candidate=static function()use($partnerInventory,$merchant):array{
        $items=$partnerInventory($merchant);
        return ['inventory_count'=>count($items),'items'=>$items];
    };
    $match=$candidate();
    availableCheck($match['inventory_count']===1 && array_column($match['items'],'id')===[$active],
        'matching cannot offer unlisted sold zero-stock reserved or unknown devices');

    // A staged generation is not an authoritative deletion/stock-out event.
    $nextRun=$insert('sync_run',['merchant_id'=>$merchant,'connection_id'=>$connection,'config_version'=>1,'status'=>'running']);
    $nextId=$stock('NEXT','available','1.000','1.000','listed',['sync_run_id'=>$nextRun]);
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['active_run_id'=>$nextRun,'last_sync_status'=>'running']);
    availableCheck(array_column($admin->inventory()['data'],'id')===[$active],
        'partial next page does not leak staged records or remove old complete-generation stock');
    availableCheck(!isset(array_column($admin->inventory(['inventory_scope'=>'all'])['data'],null,'id')[$nextId]),
        'even administrative all states cannot expose an incomplete generation');
    Db::name('hsx_lead_sync_run')->where('id',$nextRun)->update(['status'=>'failed']);
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['active_run_id'=>0,'last_sync_status'=>'failed']);
    $failedRows=$admin->inventory(['inventory_scope'=>'all']);
    availableCheck($failedRows['total']===8 && isset(array_column($failedRows['data'],null,'id')[$active]),
        'failed partial sync retains old complete-generation records without fabricating sold state');
    $failedActive=array_column($failedRows['data'],null,'id')[$active];
    availableCheck($failedActive['is_stale'] && $failedActive['availability']==='available',
        'failed sync marks its previous source state stale, not falsely sold or unavailable');
    availableCheck($admin->inventory()['total']===0 && $member->inventory([],$merchant)['total']===0,
        'failed previous generation is hidden by safe defaults on both admin and member lists');
    availableCheck($candidate()['inventory_count']===0,'failed previous generation is never normal matching stock');
    Db::name('hsx_lead_sync_run')->where('id',$nextRun)->update(['status'=>'completed','finished_at'=>$now]);
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['published_run_id'=>$nextRun,'last_sync_at'=>$now,'last_sync_status'=>'completed']);
    availableCheck(array_column($admin->inventory()['data'],'id')===[$nextId],
        'atomic complete-generation switch removes devices no longer returned by source');
    availableCheck($admin->inventory(['inventory_scope'=>'all'])['total']===1,
        'all-state view is still current generation only, not a union of old source history');
    availableCheck(Db::name('hsx_lead_inventory')->where('id',$active)->value('availability')==='available',
        'source disappearance does not rewrite an old record as sold without evidence');
    availableCheck(!in_array($active,array_column($candidate()['items'],'id'),true),
        'a disappeared source device cannot survive in matching recommendations');
    availableCheck(count($partnerInventory($merchant))===1,
        'current cooperation capability follows the published generation pointer');

    $unitStock=$stock('UNIT_SOURCE','available',null,'1.000','listed',['sync_run_id'=>$nextRun]);
    $contradictory=$stock('CONTRADICTORY','available','0.000','1.000','listed',['sync_run_id'=>$nextRun]);
    $currentIds=array_column($admin->inventory(['limit'=>100])['data'],'id');
    availableCheck(in_array($unitStock,$currentIds,true),
        'proven per-device positive saleable stock can be used without fabricating raw quantity');
    availableCheck(!in_array($contradictory,$currentIds,true),
        'zero raw stock contradicting positive saleable stock fails closed instead of being recommended');
    $unitProjection=array_column($admin->inventory(['limit'=>100])['data'],null,'id')[$unitStock];
    availableCheck($unitProjection['quantity']===null && ($unitProjection['is_sellable']??false),
        'per-device evidence and raw source quantity remain distinct concepts');
    availableCheck(!in_array($contradictory,array_column($candidate()['items'],'id'),true),
        'conflicting source stock fields cannot leak through the match endpoint');
    $badEvidenceIds=[];
    foreach([
        'missing'=>'{}',
        'malformed'=>'{not-json',
        'null_json'=>'null',
        'unknown_listing'=>Data::json(['_inventory'=>['listing_status'=>'unknown','stock_status'=>'in_stock']]),
        'unknown_stock'=>Data::json(['_inventory'=>['listing_status'=>'listed','stock_status'=>'unknown']]),
    ] as $key=>$rawStatus){
        $badEvidenceIds[]=$stock('INVALID_EVIDENCE_'.$key,'available','1.000','1.000','listed',
            ['sync_run_id'=>$nextRun,'source_status_json'=>$rawStatus]);
    }
    $safeCurrent=$admin->inventory(['limit'=>100]);
    $safeCurrentIds=array_column($safeCurrent['data'],'id');
    $diagnosticCurrent=array_column($admin->inventory(['inventory_scope'=>'all','limit'=>100])['data'],null,'id');
    $unverifiedCurrentIds=array_column($admin->inventory(['inventory_scope'=>'unverified','limit'=>100])['data'],'id');
    foreach($badEvidenceIds as $badId){
        availableCheck(!in_array($badId,$safeCurrentIds,true),'available flag and positive number cannot replace missing/invalid listing-stock proof');
        availableCheck(($diagnosticCurrent[$badId]['is_sellable']??true)===false,
            'all-state row projection also refuses incomplete listing-stock proof');
        availableCheck(in_array($badId,$unverifiedCurrentIds,true),'unverified scope retains invalid or incomplete proof for administrator review');
    }
    availableCheck($safeCurrent['total']===2,'defensive metadata validation leaves only the two positively verified current items');
    $currentDiagnosticTotal=3+count($badEvidenceIds);
    Db::name('hsx_lead_merchant')->where('id',$merchant)->update(['status'=>0]);
    availableCheck($admin->inventory()['total']===0 && count($partnerInventory($merchant))===0,
        'paused merchant stock is not shown as actively saleable or counted as cooperation capability');
    availableCheck($admin->inventory(['inventory_scope'=>'all'])['total']===$currentDiagnosticTotal,
        'administrator can still inspect a paused merchant current publication without declaring stock deleted');
    Db::name('hsx_lead_merchant')->where('id',$merchant)->update(['status'=>1]);
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['last_sync_at'=>$now-121*60]);
    Db::name('hsx_lead_inventory')->where('sync_run_id',$nextRun)->update(['fetched_at'=>$now-121*60]);
    availableCheck($admin->inventory()['total']===0 && $member->inventory([],$merchant)['total']===0,
        'expired complete publication is hidden by both safe defaults without a false sold status');
    $staleView=$admin->inventory(['inventory_scope'=>'all','limit'=>100]);
    availableCheck($staleView['total']===$currentDiagnosticTotal && count(array_filter($staleView['data'],static fn(array $r):bool=>$r['is_stale']))===$currentDiagnosticTotal,
        'administrator all-state scope retains explicit stale evidence for diagnosis');
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['last_sync_at'=>$now]);
    Db::name('hsx_lead_inventory')->where('sync_run_id',$nextRun)->update(['fetched_at'=>$now]);

    $emptyRun=$insert('sync_run',['merchant_id'=>$merchant,'connection_id'=>$connection,'config_version'=>1,'status'=>'completed','finished_at'=>$now]);
    Db::name('hsx_lead_connection')->where('id',$connection)->update(['published_run_id'=>$emptyRun]);
    availableCheck($admin->inventory()['total']===0 && $admin->inventory(['inventory_scope'=>'all'])['total']===0,
        'successful empty generation does not retain the last non-empty stock list');
    availableCheck($candidate()['inventory_count']===0 && $member->inventory([],$merchant)['total']===0,
        'empty current generation clears matching and own inventory statistics together');

    // An explicitly published other-site row must remain outside every filter.
    $foreignMerchant=(int)Db::name('hsx_lead_merchant')->insertGetId(['site_id'=>$site+1,'name'=>'Foreign fixture merchant','member_id'=>103]);
    $foreignConnection=(int)Db::name('hsx_lead_connection')->insertGetId(['site_id'=>$site+1,'merchant_id'=>$foreignMerchant,'provider'=>'wanji','last_sync_at'=>$now,'last_sync_status'=>'completed']);
    $foreignRun=(int)Db::name('hsx_lead_sync_run')->insertGetId(['site_id'=>$site+1,'merchant_id'=>$foreignMerchant,'connection_id'=>$foreignConnection,'config_version'=>1,'status'=>'completed']);
    Db::name('hsx_lead_connection')->where('id',$foreignConnection)->update(['published_run_id'=>$foreignRun]);
    Db::name('hsx_lead_inventory')->insert(['site_id'=>$site+1,'merchant_id'=>$foreignMerchant,'connection_id'=>$foreignConnection,'sync_run_id'=>$foreignRun,
        'source_key'=>'FOREIGN','model'=>'iPhone 16','availability'=>'available','quantity'=>'9.000','saleable_quantity'=>'9.000','fetched_at'=>$now]);
    availableCheck($admin->inventory(['inventory_scope'=>'all'])['total']===0 && $admin->inventory()['total']===0,
        'all-state and default filtering never remove site isolation');
    availableDenied(fn()=>$member->inventory(['inventory_scope'=>'all'],$foreignMerchant),'member all-state request cannot pivot tenant/merchant ownership');

    // Exercise real normalization -> persistence -> publication using a known
    // listed-per-device source, with a synthetic transport and no vendor calls.
    $syncMerchant=$insert('merchant',['name'=>'Synthetic sync source','member_id'=>102,
        'province_id'=>910000,'city_id'=>910100,'district_id'=>910101]);
    $syncConnection=$insert('connection',['merchant_id'=>$syncMerchant,'provider'=>'aiguanji',
        'expected_tenant_id'=>'42','store_id'=>'42','enabled'=>0]);
    Db::name('hsx_lead_connection')->where('id',$syncConnection)->update(['credentials_cipher'=>Cipher::encrypt(
        ['username'=>'19900000102','access_token'=>'synthetic-availability-token'],$site,$syncConnection)]);
    $profile=['code'=>0,'data'=>['user'=>['mobile'=>'19900000102','company'=>['id'=>42,'name'=>'Synthetic company']]]];
    $source=static fn(int $id):array=>['id'=>$id,'company_id'=>42,'warehouse_id'=>8,'name'=>'iPhone 16',
        'status'=>55,'status_text'=>'已上架','product_attr_memory'=>'256GB','product_attr_color'=>'黑色',
        'pre_sale_amount'=>'3000.00','industry_amount'=>'2900.00','imei'=>'SYNTHETIC_DEVICE_'.$id,
        'cost_price'=>'PRIVATE_SOURCE_COST','remark'=>'PRIVATE_SOURCE_REMARK'];
    $page=static fn(array $rows,int $total,int $number=1):array=>['code'=>0,'data'=>['total'=>$total,'per_page'=>20,
        'current_page'=>$number,'last_page'=>$total===0?0:(int)ceil($total/20),'data'=>$rows,'has_more'=>$number*20<$total]];
    $firstSources=array_map($source,range(1,20));
    $transport=new AvailabilityTransport([$profile,$page($firstSources,21),$page([$source(21)],21,2)]);
    $sync=new SyncService(new ProviderRegistry($transport));
    $run=$sync->start($site,$syncMerchant,1); $syncRun=(int)$run['run_id'];
    availableCheck($transport->calls===0 && $run['status']==='pending','creating a sync run does not make a hidden provider request');
    $partial=$sync->advance($site,$syncRun);
    availableCheck($partial['status']==='running' && $admin->inventory(['merchant_id'=>$syncMerchant])['total']===0,
        'real SyncService first page is staged and unavailable in the default list');
    availableCheck(Db::name('hsx_lead_inventory')->where('sync_run_id',$syncRun)->count()===20,
        'one advance persists only its bounded source page');
    $persisted=Db::name('hsx_lead_inventory')->where('sync_run_id',$syncRun)->order('id')->find();
    availableCheck($persisted['availability']==='available' && $persisted['quantity']===null
        && $persisted['saleable_quantity']==='1.000','real sync keeps normalized state and separate unit proof instead of hardcoding unknown');
    $evidence=Data::decode($persisted['source_status_json']);
    availableCheck(($evidence['_inventory']['listing_status']??'')==='listed'
        && ($evidence['_inventory']['stock_status']??'')==='in_stock',
        'sync persists verified source listing and stock evidence for later read projection');
    availableCheck(!str_contains($persisted['snapshot_json'],'PRIVATE_SOURCE_COST')
        && !str_contains($persisted['snapshot_json'],'PRIVATE_SOURCE_REMARK'),
        'source whitelist remains effective when adding availability metadata');
    $complete=$sync->advance($site,$syncRun);
    availableCheck($complete['status']==='completed' && $admin->inventory(['merchant_id'=>$syncMerchant,'limit'=>100])['total']===21,
        'real complete publication exposes every confirmed listed per-device record');
    availableCheck($transport->calls===3 && $transport->remaining()===0,'two-page sync uses one identity verification and two mocked stock requests');
    $syncMember=new ReadService(new Context($site,0,102));
    availableCheck(count($partnerInventory($syncMerchant))===0,'manual-only synchronized stock is not promoted to active merchant delivery capability');
    Db::name('hsx_lead_connection')->where('id',$syncConnection)->update(['enabled'=>1]);
    availableCheck($syncMember->inventory([],$syncMerchant)['total']===21
        && count($partnerInventory($syncMerchant))===21,
        'merchant own inventory and statistic see the same successful normalized publication');
    $goodPublication=(int)Db::name('hsx_lead_connection')->where('id',$syncConnection)->value('published_run_id');
    $failureTransport=new AvailabilityTransport([$profile,$page(array_map($source,range(101,120)),21),new RuntimeException('PRIVATE_NETWORK_FAILURE_MARKER')]);
    $failureSync=new SyncService(new ProviderRegistry($failureTransport));
    $failureRun=$failureSync->start($site,$syncMerchant,1);$failureId=(int)$failureRun['run_id'];
    $failureSync->advance($site,$failureId);
    availableCheck($admin->inventory(['merchant_id'=>$syncMerchant])['total']===21,
        'partial replacement keeps the previous complete publication current until the next page resolves');
    $failedSync=$failureSync->advance($site,$failureId);
    availableCheck($failedSync['status']==='failed' && (int)Db::name('hsx_lead_connection')->where('id',$syncConnection)->value('published_run_id')===$goodPublication,
        'failure cannot publish a partial replacement or infer that absent devices were sold');
    availableCheck($admin->inventory(['merchant_id'=>$syncMerchant])['total']===0
        && $admin->inventory(['merchant_id'=>$syncMerchant,'inventory_scope'=>'all','limit'=>100])['total']===21,
        'failed publication is not treated as currently available but remains inspectable in admin all states');
    availableCheck(Db::name('hsx_lead_inventory')->where('sync_run_id',$goodPublication)->where('availability','available')->count()===21,
        'failed next generation never rewrites previous source availability into false sold state');
    availableCheck(!str_contains(json_encode($failedSync),'PRIVATE_NETWORK_FAILURE_MARKER'),
        'unexpected transport diagnostics are redacted from the sync status DTO');
    $emptyTransport=new AvailabilityTransport([$profile,$page([],0)]);
    $emptySync=new SyncService(new ProviderRegistry($emptyTransport));
    $emptySyncRun=$emptySync->start($site,$syncMerchant,1);$emptySyncId=(int)$emptySyncRun['run_id'];
    $emptySyncResult=$emptySync->advance($site,$emptySyncId);
    availableCheck($emptySyncResult['status']==='completed' && (int)Db::name('hsx_lead_connection')->where('id',$syncConnection)->value('published_run_id')===$emptySyncId,
        'actual successful empty source response publishes an authoritative empty generation');
    availableCheck($admin->inventory(['merchant_id'=>$syncMerchant,'inventory_scope'=>'all'])['total']===0
        && $syncMember->inventory([],$syncMerchant)['total']===0,
        'real empty publication removes previously listed source devices from every current list');
    leadAssertIsolated($schema);
    echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'saleable inventory projection/defaults, stock exclusion, matching, statistics, member scope and complete-generation replacement',
        'database'=>'owned random localhost schema','remote_calls'=>0,'existing_business_rows_read'=>0,'existing_business_database_modified'=>false],JSON_UNESCAPED_UNICODE)."\n";
} catch(Throwable $error) {
    $failed=true;
    $message=$error instanceof PDOException?'isolated MySQL failed, SQLSTATE '.$error->getCode():$error->getMessage();
    fwrite(STDERR,'FAIL '.get_class($error).': '.$message.' at '.basename($error->getFile()).':'.$error->getLine()."\n");
} finally {
    if($pdo instanceof PDO && $schema!=='') {
        try{leadCleanup($pdo,$schema);}catch(Throwable){$failed=true;fwrite(STDERR,"FAIL owned test database cleanup requires attention\n");}
    }
}
exit($failed?1:0);
