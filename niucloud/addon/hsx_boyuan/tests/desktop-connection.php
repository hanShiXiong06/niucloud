<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,DesktopConnectionService as Desk};
use addon\hsx_boyuan\app\gateway\Transport;
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;

[$app,$pdo,$schema,$site]=boyuanBoot();
function checkDesk(bool $ok,string $what): void { if(!$ok) throw new RuntimeException($what); }
function refused(string $reason,callable $fn): void {
    try {$fn();} catch(Problem $e){checkDesk($e->reason===$reason,'Unexpected refusal: '.$e->reason);return;}
    throw new RuntimeException('Expected refusal: '.$reason);
}
try {
    (new \addon\hsx_boyuan\Addon())->install();
    $service=new ConfigService(); $empty=$service->get($site);
    $in=['mode'=>'test','revision'=>0,'account_id'=>'00007','endpoint'=>'https://open-api-uat.139erp.com/api/gateway.do','app_id'=>'00042','app_secret'=>bin2hex(random_bytes(16)),'contract_confirmed'=>false,
        'enabled'=>true,'finance_enabled'=>true,'purchase_enabled'=>true,'paused'=>false];
    $changes=Desk::changes($empty,$in);
    checkDesk(array_keys($changes)===['revision','mode','profile'],'Editor must not control posting');
    refused('APP_SECRET_REQUIRED',fn()=>Desk::changes($empty,array_merge($in,['app_secret'=>''])));
    refused('ENDPOINT_ENVIRONMENT',fn()=>Desk::changes($empty,array_merge($in,['endpoint'=>'https://open-api.139erp.com/api'])));
    refused('TARGET_CONFIRM_REQUIRED',fn()=>Desk::changes($empty,array_merge($in,['mode'=>'production','endpoint'=>'https://open-api.139erp.com/api'])));
    $saved=$service->save($site,1,$changes);$p=$saved['profiles']['test'];$public=Desk::view($saved);
    checkDesk(!$public['dispatching'] && !$saved['purchase_enabled'] && !$saved['finance_enabled'],'Saving must not start delivery');
    checkDesk($p['endpoint']==='https://open-api-uat.139erp.com/api' && !$p['probe_at'] && $p['app_secret_configured'] && $p['app_id']==='00042' && !$p['contract_confirmed'],'Persisted setup with string IDs');
    checkDesk(strpos(json_encode($public),'cipher')===false && strpos(json_encode($public),$in['app_secret'])===false,'Credential disclosure');
    $expected=['revision'=>$saved['revision'],'mode'=>'test','connection_key'=>$p['connection_key']];
    $start=$expected+['intent'=>'start','window_id'=>'']; $before=$service->get($site,false);
    checkDesk(Desk::view($saved)['interface_trace']['window_id']==='','Trace off by default');
    refused('CONFIG_STALE',fn()=>$service->setTrace($site,1,array_replace($start,['mode'=>'production'])));
    $w=$service->setTrace($site,1,$start);
    checkDesk($w['window_id']!=='' && in_array($w['expires_at']-$w['server_time'],[899,900],true),'Trace TTL and ID');
    $after=$service->get($site,false); unset($after['interface_trace']); checkDesk($before===$after,'Starting diagnostics does not change business configuration/revision');
    checkDesk(ConfigService::traceState($service->get($site+1,false))['window_id']==='','Isolated by site');
    $expiry=$service->get($site,false); $expiry['interface_trace']['expires_at']=time()-1;
    checkDesk(ConfigService::traceState($expiry)['window_id']==='' && ConfigService::traceState($expiry)['last_window_id']===$w['window_id'],'Expires without cron, search still works');
    refused('TRACE_STALE',fn()=>$service->setTrace($site,1,array_replace($start,['intent'=>'stop','window_id'=>'stale'])));
    $stopped=$service->setTrace($site,1,array_replace($start,['intent'=>'stop','window_id'=>$w['window_id']]));
    checkDesk($stopped['window_id']==='' && $stopped['last_window_id']===$w['window_id'],'Stopped window still searchable');
    $w=$service->setTrace($site,1,$start);
    $gateway=new class implements Transport {public int $calls=0;public string $account='wrong';public function send(string $method,string $path,array $body=[]):array{checkDesk($method==='GET','Read-only fake');$this->calls++;return ['code'=>'OK','data'=>['account_id'=>$this->account]];}};
    $tasks=new \addon\hsx_boyuan\app\service\TaskService();
    $ignored=$tasks->accept(['site_id'=>$site,'event_id'=>'EV-FIXTURE-1','event_name'=>'erp.integration.document.confirmed.v1','payload'=>[
        'kind'=>'purchase','source_document_no'=>'CG-FIXTURE-1','party'=>['name'=>'PRIVATE-FIXTURE-NAME','help_code'=>'13900009999']]]);
    checkDesk($ignored===[],'Diagnostics do not turn on event intake or dispatch');
    refused('PURCHASE_UNCONFIRMED',fn()=>ConfigService::localPreparation($site,['kind'=>'purchase','source_id'=>99999],fn()=>
        (new \addon\hsx_boyuan\app\service\PurchaseSyncService())->capturePurchaseId($site,1,99999)));
    $tasks::audit($site,908,$p['connection_key'],'purchase_created','created_review','Test fixture business result',1,['external_no'=>'PUR-fixture-99']);
    $records=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(sys_get_temp_dir().'/'.$schema.'/log',FilesystemIterator::SKIP_DOTS)) as $file) {
        if(!$file->isFile() || $file->isLink()) continue;
        foreach(file($file->getPathname(),FILE_IGNORE_NEW_LINES) as $line) {
            $at=strpos($line,'hsx_boyuan trace '); if($at===false || !str_contains($line,$w['window_id']))continue;
            $records[]=json_decode(substr($line,$at+strlen('hsx_boyuan trace ')),true,512,JSON_THROW_ON_ERROR);
        }
    }
    checkDesk(count($records)===3 && $records[0]['event_id']==='EV-FIXTURE-1' && $records[0]['outcome']==='erp_event_skipped'
        && $records[0]['code']==='SYNC_DISABLED' && $records[0]['event_input']['source_document_no']==='CG-FIXTURE-1' && $records[0]['listener_result']===[],'ERP handoff input/response recorded in PHP file log');
    checkDesk($records[1]['outcome']==='blocked_before_send' && $records[1]['code']==='PURCHASE_UNCONFIRMED'
        && $records[2]['trace_id']==='T'.$site.'-908' && $records[2]['result']['external_no']==='PUR-fixture-99','Original ERP ticket and local refusal captured');
    checkDesk(!preg_match('/PRIVATE-FIXTURE-NAME|13900009999/',json_encode($records)),'No personal archive fields in trace');
    refused('CONFIG_STALE',fn()=>$service->probe($site,1,$gateway,array_merge($expected,['revision'=>0])));
    checkDesk($gateway->calls===0,'Stale probe must not make a request');
    refused('ACCOUNT_UNVERIFIED',fn()=>$service->probe($site,1,$gateway,$expected));
    checkDesk(!$service->get($site)['profiles']['test']['probe_at'],'Wrong account cannot verify');
    $gateway->account='00007';$receipt=$service->probe($site,1,$gateway,$expected);
    checkDesk($receipt['verified'] && !$receipt['write_performed'] && $service->get($site)['profiles']['test']['probe_at']>0,'Readback verified');
    $ref=new \addon\hsx_boyuan\app\service\ReferenceService();
    [$one,$two]=$ref->start($site,1,['companies','warehouses'],'opening',$p['connection_key'])['ids'];
    $pages=new class implements Transport {public int $calls=0;
        public function send(string $method,string $path,array $body=[]):array {
            checkDesk($method==='GET' && $body['resource']==='companies','No other resource or write');$this->calls++;$n=(int)$body['cursor'];
            return ['code'=>'OK','data'=>['items'=>[['id'=>(string)$n,'code'=>'C'.$n,'name'=>'测试门店'.$n,'enabled'=>true]],'total'=>2,'has_more'=>$n===1,'next_cursor'=>$n===1?'2':'']];
        }
    };
    $advance=fn($id,$resource,$key,$mode,$cursor)=>$ref->readJobPage($site,$id,$resource,$key,$mode,$cursor,$pages);
    refused('CONNECTION_CHANGED',fn()=>$advance($one,'companies','different','test','1'));
    refused('PREFLIGHT_REQUIRED',fn()=>$advance($one,'companies',$p['connection_key'],'production','1'));
    refused('SYNC_SUPERSEDED',fn()=>$advance($two,'companies',$p['connection_key'],'test','1'));
    checkDesk($pages->calls===0,'No request before scope checks');
    $r=$advance($one,'companies',$p['connection_key'],'test','1');checkDesk($r['status']==='queued' && $r['processed']===1 && $r['cursor']==='2','Page persisted');
    Db::name('hsx_boyuan_reference_sync')->where('id',$one)->update(['next_at'=>0]);
    $advance($one,'companies',$p['connection_key'],'test','1');checkDesk($pages->calls===1,'A repeated cursor cannot advance the job');
    $r=$advance($one,'companies',$p['connection_key'],'test','2');checkDesk($r['status']==='completed' && $r['processed']===2,'Selected job completed');
    $advance($one,'companies',$p['connection_key'],'test','2');checkDesk($pages->calls===2 && (int)Db::name('hsx_boyuan_reference_sync')->where('id',$two)->value('pages')===0,'No rerun or other job');
    $same=array_merge($in,['revision'=>1,'app_secret'=>'','contract_confirmed'=>true]);
    $unchanged=$service->save($site,1,Desk::changes($service->get($site),$same));
    checkDesk($unchanged['profiles']['test']['connection_key']===$p['connection_key'] && $unchanged['profiles']['test']['app_secret_configured'],'Retain saved secret');
    $new=array_merge($in,['revision'=>2,'account_id'=>'00008']);refused('TARGET_CONFIRM_REQUIRED',fn()=>Desk::changes($unchanged,$new));
    refused('PAUSE_REQUIRED',fn()=>Desk::changes(array_merge($unchanged,['enabled'=>true,'paused'=>false]),$new));
    $changed=$service->save($site,1,Desk::changes($unchanged,array_merge($new,['confirm_target'=>'00008'])));
    checkDesk(!$changed['profiles']['test']['probe_at'] && $changed['profiles']['test']['connection_key']!==$p['connection_key'],'New identity invalidates verification');
    checkDesk(Desk::view($changed)['interface_trace']['last_window_id']==='','New account does not inherit logging');
    checkDesk(Db::name('hsx_boyuan_task')->count()===0,'No business tasks');
    echo "PASS: isolated PHP 8 connection setup, redaction, permissions boundary, exact environment, matched/stale verification, scoped archive paging and duplicate protection, same-identity retention and account replacement; no network or business documents.\n";
} finally {
    if (preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');
}
