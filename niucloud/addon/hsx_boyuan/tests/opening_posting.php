<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit(2);
foreach (['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{OpeningPostingService,ConfigService};
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;
$pdo=null;$schema='';$checks=0;$failed=false;
function verifyOpening(bool $ok,string $label):void {global $checks; if(!$ok) throw new RuntimeException($label); $checks++;}
function rejectOpening(callable $f,string $code):void {try {$f();} catch(Problem $e){verifyOpening($e->reason===$code,$code.': '.$e->reason);return;} throw new RuntimeException('未拦截 '.$code);}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install();
    $key=str_repeat('b',32);$run=str_repeat('e',32);
    $config=new ConfigService();$profile=$config->get($site,false);
    $profile['mode']='test';$profile['profiles']['test']=array_replace($profile['profiles']['test'],['connection_key'=>$key,'account_id'=>'DEMO','protocol'=>'openapi_v153','probe_at'=>time()]);
    $config->persist($site,$profile);
    $service=new OpeningPostingService();$request=['connection_key'=>$key,'resource'=>'parties','cursor'=>0];
    $preview=fn()=>$service->handle($site,1,$request,false);
    $apply=fn(string $token)=>$service->handle($site,1,$request+['token'=>$token,'confirm'=>true],true);
    $read=fn()=>$service->check($site,$key);
    rejectOpening(fn()=>$service->check($site,str_repeat('a',32)),'OPENING_CONNECTION');
    verifyOpening($read()['ready'] && !$read()['resources'][2]['ready'],'empty local finance does not imply ready source');
    rejectOpening($preview,'OPENING_SOURCE');
    $job=Db::name('hsx_boyuan_reference_sync')->insertGetId(['site_id'=>$site,'connection_key'=>$key,'mode'=>'test','resource'=>'parties','run_key'=>$run,'scope_json'=>'{}','status'=>'completed']);
    \addon\hsx_boyuan\app\support\CatalogStore::query('parties')->insert(['site_id'=>$site,'connection_key'=>$key,'external_id'=>'BY01','name'=>'期初隔离供应商','code'=>'BY1','extra_json'=>'{"is_supplier":1}','enabled'=>1,'seen_run'=>$run]);
    $p=$preview();verifyOpening($p['rows'][0]['action']==='create' && !$p['next_cursor'] && Db::name('erp_party')->count()===0,'preview does not write ERP');
    rejectOpening(fn()=>$service->handle($site,1,$request+['token'=>$p['token']],true),'OPENING_CONFIRM');
    rejectOpening(fn()=>$service->handle($site,2,$request+['token'=>$p['token'],'confirm'=>true],true),'OPENING_CHANGED');
    foreach(['receivable','payable'] as $type) {
        $table=Db::name('erp_'.$type);
        $id=$table->insertGetId(['site_id'=>$site,$type.'_no'=>'Z1','amount'=>'10.00','settled_amount'=>'9.99','status'=>'partial']);
        verifyOpening(!$read()['ready'] && $read()[$type.'_count']===1,'one cent of '.$type.' blocks opening');
        rejectOpening(fn()=>$apply($p['token']),'OPENING_OUTSTANDING');
        $table=Db::name('erp_'.$type);$table->where('id',$id)->update(['status'=>'settled']);
        verifyOpening(!$read()['ready'],'settled label cannot hide a difference');
        Db::name('erp_'.$type)->where('id',$id)->update(['status'=>'void']);
    }
    Db::name('erp_payable')->insert(['site_id'=>$site+1,'payable_no'=>'OTHER','amount'=>20,'status'=>'pending']);
    verifyOpening($read()['ready'],'void and other site do not block the site');
    \addon\hsx_boyuan\app\support\CatalogStore::query('parties')->where('external_id','BY01')->update(['name'=>'变更后的供应商']);
    rejectOpening(fn()=>$apply($p['token']),'OPENING_CHANGED');
    $p=$preview();
    $newJob=Db::name('hsx_boyuan_reference_sync')->insertGetId(['site_id'=>$site,'connection_key'=>$key,'mode'=>'test','resource'=>'parties','run_key'=>str_repeat('c',32),'scope_json'=>'{}','status'=>'needs_review']);
    rejectOpening(fn()=>$apply($p['token']),'OPENING_SOURCE');
    Db::name('hsx_boyuan_reference_sync')->where('id',$newJob)->delete(); // test-owned isolated fixture only
    $done=$apply($p['token']);verifyOpening($done['rows'][0]['action']==='create' && Db::name('erp_party')->where('site_id',$site)->count()===1,'confirmed local ERP master');
    rejectOpening(fn()=>$apply($p['token']),'OPENING_CHANGED');
    verifyOpening($preview()['rows'][0]['action']==='existing' && Db::name('erp_party')->where('site_id',$site)->count()===1,'verified replay never creates twice');
    // Prove empty-range protection against a normal ERP writer (which does not lock our site).
    $db=$app->config->get('database.connections.isolated');
    $other=new PDO('mysql:host=127.0.0.1;port='.$db['hostport'].';dbname='.$schema.';charset=utf8mb4',$db['username'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $other->exec('SET SESSION innodb_lock_wait_timeout=1');
    $fn=new ReflectionMethod(OpeningPostingService::class,'balances');$fn->setAccessible(true);
    $fresh=$site+20;
    Db::execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    Db::transaction(function() use($service,$fn,$other,$fresh,$db) {
        verifyOpening($fn->invoke($service,$fresh,true)['receivable_count']===0,'empty lock range');
        foreach(['receivable','payable'] as $type) {
            $blocked=false;
            try {$other->exec("INSERT INTO `".$db['prefix']."erp_".$type."` (site_id,".$type."_no,amount) VALUES (".$fresh.",'LOCK_TEST',1.00)");}
            catch(PDOException $e) {$blocked=(int)($e->errorInfo[1]??0)===1205;}
            verifyOpening($blocked,'new concurrent '.$type.' blocked until page commit');
        }
    });
    verifyOpening(Db::name('erp_asset')->count()===0 && Db::name('erp_money_ledger')->count()===0,'no inventory movements or funds');
    echo 'PASS '.$checks.' opening admission assertions, local disposable database, 0 external requests'.PHP_EOL;
} catch(Throwable $e) {$failed=true;fwrite(STDERR,'FAIL '.get_class($e).' '.($e instanceof PDOException?'PDO '.$e->getCode():$e->getMessage()).' @ '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);}
finally {if($pdo && preg_match('/^hsx_boyuan_test_[0-9a-f]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
