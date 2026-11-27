<?php
declare(strict_types=1);

/** Full pagination and authoritative completion, using only synthetic ERP pages. */
if (PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $function) {
    if (function_exists($function)) exit("Disable HTTP/network functions before isolated testing\n");
}
if (filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen before isolated testing\n");
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\{ProviderRegistry,SoujikeProvider,TransportContract};
use addon\hsx_lead\app\service\{ConnectionService,Context,SyncService};
use addon\hsx_lead\app\support\{Cipher,Data,SyncStatus};
use think\facade\Db;

$checks=0;$failed=false;$pdo=null;$schema='';
function syncStateCheck(bool $condition,string $message):void { if (!$condition) throw new RuntimeException($message);$GLOBALS['checks']++; }
final class SyncStateTransport implements TransportContract
{
    public array $calls=[];
    public function __construct(private array $responses) {}
    public function post(string $endpoint,array $headers,array $body):array
    {
        syncStateCheck(!Db::connect()->getPdo()->inTransaction(),'source HTTP never holds a database transaction');
        syncStateCheck($endpoint===SoujikeProvider::STOCK_ENDPOINT,'full sync reads the fixed source inventory endpoint');
        $this->calls[]=$body;
        if (!$this->responses) throw new RuntimeException('Unexpected synthetic source request');
        return array_shift($this->responses);
    }
}
function syncStatePage(int $page,int $total):array
{
    $count=min(100,max(0,$total-($page-1)*100));$records=[];
    for($index=0;$index<$count;$index++) {
        $id=(string)(($page-1)*100+$index+1);
        $records[]=['id'=>$id,'storeId'=>'fixture-store','productName'=>'Synthetic phone '.$id,'categoryName'=>'Synthetic phone '.$id,
            'brandName'=>'Fixture','shelfStatus'=>1,'shelfStatusName'=>'已上架','saleStatus'=>false,
            'retailPrice'=>'1000.00','firstResIsVideo'=>false];
    }
    return ['code'=>200,'data'=>['records'=>$records,'current'=>$page,'size'=>100,'total'=>$total,'pages'=>(int)ceil($total/100)]];
}
try {
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);$now=time();
    $merchant=(int)Data::query('merchant',$site)->insertGetId(['site_id'=>$site,'member_id'=>101,'name'=>'Synthetic sync state merchant']);
    $connection=(int)Data::query('connection',$site)->insertGetId(['site_id'=>$site,'merchant_id'=>$merchant,'provider'=>'soujike',
        'store_id'=>'fixture-store','enabled'=>1,'config_version'=>1,'sync_interval_minutes'=>60]);
    Data::query('connection',$site)->where('id',$connection)->update(['credentials_cipher'=>Cipher::encrypt(['auth_mode'=>'token','access_token'=>'synthetic-sync-state-token'],$site,$connection)]);
    $connections=new ConnectionService(new Context($site,1));
    $transport=new SyncStateTransport([syncStatePage(1,205),syncStatePage(2,205),syncStatePage(3,205)]);
    $service=new SyncService(new ProviderRegistry($transport));$first=$service->start($site,$merchant,1);$run=(int)$first['run_id'];
    syncStateCheck($first['status']==='pending'&&$first['can_advance']&&$first['total_records']===null,'new full sync is pending, not a fabricated complete first-page result');
    syncStateCheck($transport->calls===[],'starting a persistent job does not hide a source request');
    $one=$service->advance($site,$run);
    syncStateCheck($one['status']==='running'&&$one['record_count']===100&&$one['total_records']===205,'first page reports 100 of the true 205 source records');
    syncStateCheck($one['progress_known']&&$one['progress_percent']===48&&!$one['complete']&&!$one['published'],'first page is explicitly incomplete and unpublished');
    syncStateCheck($one['can_advance']&&$one['scheduler_required'],'unfinished page exposes continuation instead of pretending 100 rows is all stock');
    $connectionView=$connections->get($merchant);
    syncStateCheck($connectionView['last_sync_status']==='running'&&$connectionView['sync_progress']['record_count']===100,'connection reads current run rather than only a stale status string');
    $two=$service->advance($site,$run);
    syncStateCheck($two['status']==='running'&&$two['record_count']===200&&$two['progress_percent']===97,'second page also remains unfinished');
    syncStateCheck((int)Data::row('connection',$site,$connection)['published_run_id']===0,'two staged pages are not published early');
    $three=$service->advance($site,$run);
    syncStateCheck($three['status']==='completed'&&$three['record_count']===205&&$three['page_count']===3,'all three source pages finish before completion');
    syncStateCheck($three['progress_percent']===100&&$three['complete']&&$three['published']&&!$three['can_advance']&&!$three['scheduler_required'],'terminal completion cannot continue or demand a scheduler');
    syncStateCheck(array_column($transport->calls,'current')===[1,2,3],'each actual source page is fetched in order exactly once');
    foreach($transport->calls as $request) syncStateCheck($request['saleStatus']===null&&$request['isNew']===null&&$request['categoryIds']===[],'full sync does not add source sale, age or model filters');
    syncStateCheck((int)Data::query('inventory',$site)->where('sync_run_id',$run)->count()===205,'every normalized source record is persisted in the full generation');
    $current=$connections->get($merchant);
    syncStateCheck($current['last_sync_status']==='completed'&&$current['active_run_id']===0&&$current['sync_progress']['record_count']===205,'refreshing after background completion replaces the cached running state');
    syncStateCheck($current['sync_progress']['published']&&str_contains($current['last_sync_message'],'205'),'connection summary describes the complete total rather than a page-size cap');
    $calls=count($transport->calls);$again=$service->advance($site,$run);
    syncStateCheck($again['status']==='completed'&&count($transport->calls)===$calls,'a late running UI cannot make completed advance fetch again');

    // Read-only defensive projection of an inconsistent pointer is not a data migration.
    Data::query('connection',$site)->where('id',$connection)->update(['active_run_id'=>$run,'last_sync_status'=>'running']);
    $view=$connections->get($merchant);
    syncStateCheck($view['active_run_id']===0&&$view['last_sync_status']==='completed','a terminal run takes precedence over a stale cached running pointer');
    syncStateCheck(Data::row('connection',$site,$connection)['last_sync_status']==='running','a read never silently rewrites stored business state');
    Data::query('connection',$site)->where('id',$connection)->update(['active_run_id'=>0,'last_sync_status'=>'running']);
    syncStateCheck($connections->get($merchant)['last_sync_status']==='completed','missing active pointer uses the matching terminal current-configuration run');
    Data::query('connection',$site)->where('id',$connection)->update(['active_run_id'=>$run,'config_version'=>2]);
    $changed=$connections->get($merchant);
    syncStateCheck($changed['last_sync_status']==='interrupted'&&$changed['requires_sync_restart']&&$changed['active_run_id']===0,'another configuration cannot be presented as continuing the old run');
    Data::query('connection',$site)->where('id',$connection)->update(['active_run_id'=>0,'last_sync_status'=>'idle']);
    $emptyTransport=new SyncStateTransport([syncStatePage(1,0)]);$emptyService=new SyncService(new ProviderRegistry($emptyTransport));
    $empty=$emptyService->start($site,$merchant,1);$emptyDone=$emptyService->advance($site,(int)$empty['run_id']);
    syncStateCheck($emptyDone['status']==='completed'&&$emptyDone['record_count']===0&&$emptyDone['total_records']===0&&$emptyDone['progress_percent']===100,'a verified empty source is a completed zero, not stuck collecting');
    syncStateCheck($connections->get($merchant)['sync_progress']['record_count']===0,'completed empty generation replaces the former nonempty total');

    $badTransport=new SyncStateTransport([syncStatePage(1,205),['code'=>500,'msg'=>'Synthetic source failure']]);
    $badService=new SyncService(new ProviderRegistry($badTransport));$bad=$badService->start($site,$merchant,1);$badId=(int)$bad['run_id'];
    $badService->advance($site,$badId);$failedRun=$badService->advance($site,$badId);$failedConnection=$connections->get($merchant);
    syncStateCheck($failedRun['status']==='failed'&&!$failedRun['can_advance']&&!$failedRun['scheduler_required'],'failed final page is terminal and never called complete');
    syncStateCheck($failedConnection['last_sync_status']==='failed'&&$failedConnection['sync_progress']['record_count']===100&&!$failedConnection['sync_progress']['complete'],'prior completed publication cannot conceal a newer failed refresh');
    syncStateCheck((int)$failedConnection['published_run_id']===(int)$empty['run_id'],'failed partial generation does not replace the last complete source publication');
    $foreign=(int)Data::query('sync_run',$site+1)->insertGetId(['site_id'=>$site+1,'merchant_id'=>$merchant,'connection_id'=>$connection,'config_version'=>2,'status'=>'running','record_count'=>999]);
    Data::query('connection',$site)->where('id',$connection)->update(['active_run_id'=>$foreign,'last_sync_status'=>'running']);
    $isolated=$connections->get($merchant);
    syncStateCheck($isolated['sync_progress']['record_count']===100&&$isolated['last_sync_status']==='failed','foreign-site task IDs cannot enter this connection progress');
    $pure=SyncStatus::progress(['id'=>1,'status'=>'running','record_count'=>100,'cursor_json'=>'{"source_total":100}']);
    syncStateCheck($pure['progress_percent']===99&&!$pure['complete'],'record count alone never reports 100 percent before an actual completed task');
    leadAssertIsolated($schema);
    echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'full 205-row source pagination, authoritative sync completion, bounded continuation, empty/failure states and tenant isolation','database'=>'owned random localhost schema','remote_calls'=>0,'existing_business_rows_read'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL sync state: '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);}
finally{if($pdo instanceof PDO&&$schema!==''){try{leadCleanup($pdo,$schema);}catch(Throwable $error){$failed=true;fwrite(STDERR,"FAIL owned test schema cleanup\n");}}}
exit($failed?1:0);
