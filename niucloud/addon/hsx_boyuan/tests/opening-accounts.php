<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,OpeningPostingService,PaymentSyncService};
use addon\hsx_boyuan\app\support\{CatalogStore,Problem};
use think\facade\Db;
$pdo=null; $schema=''; $checks=0; $failed=false;
function accountTest(bool $yes,string $label):void {global $checks; if(!$yes)throw new RuntimeException($label); $checks++;}
function accountReject(callable $act,string $reason):void {try{$act();}catch(Problem $e){accountTest($e->reason===$reason,$reason.' but got '.$e->reason);return;}throw new RuntimeException('missing '.$reason);}
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install();
    $key=str_repeat('a',32); $run=str_repeat('b',32); $c=new ConfigService(); $p=$c->get($site,false);
    $p['mode']='test'; $p['profiles']['test']=array_replace($p['profiles']['test'],['account_id'=>'TEST','connection_key'=>$key,'protocol'=>'openapi_v153','probe_at'=>time()]); $c->persist($site,$p);
    $service=new OpeningPostingService();
    $request=['connection_key'=>$key,'resource'=>'capital_accounts'];
    $job=['site_id'=>$site,'connection_key'=>$key,'mode'=>'test','resource'=>'capital_accounts','run_key'=>$run,'scope_json'=>'{}','status'=>'completed'];
    $preview=fn($choices)=>$service->handle($site,1,$request+['choices'=>$choices],false);
    $apply=fn($choices,$token)=>$service->handle($site,1,$request+['choices'=>$choices,'token'=>$token,'confirm'=>true],true);
    $archive=fn($id,$name,$over=[])=>CatalogStore::query('capital_accounts')->insertGetId($over+['site_id'=>$site,'connection_key'=>$key,'external_id'=>$id,'code'=>$id,'name'=>$name,'enabled'=>1,'seen_run'=>$run,
        'extra_json'=>'{"gsdm":"C","company_name":"样例公司","account_type":"微信","balance":"888.88"}']);
    $local=fn($name,$over=[])=>Db::name('erp_capital_account')->insertGetId($over+['site_id'=>$site,'account_name'=>$name,'account_type'=>'bank','status'=>1]);
    $pick=fn($id,$localId,$type='')=>['id'=>$id,'local_id'=>$localId,'type'=>$type];
    $s1=$archive('A1','门店一号');$s2=$archive('A2','银行卡二号');$s3=$archive('A3','未选择的户头');
    $off=$archive('OFF','停用',['enabled'=>0]);$foreign=$archive('FOREIGN','其他站',['site_id'=>$site+1]);$archive('OLD','旧档',['seen_run'=>str_repeat('d',32)]);
    $keep=$local('本地原账户',['balance'=>123.45,'is_default'=>1]);$sys=$local('清算专户',['account_no'=>'system:phone_shop:wechat_clearing']);$foreignLocal=$local('另一站',['site_id'=>$site+1]);
    $act=[$pick($s1,0,'wechat'),$pick($s2,$keep)];
    accountTest(!$service->check($site,$key)['accounts_ready'],'no run, no accounts');
    accountReject(fn()=>$preview($act),'OPENING_SOURCE');
    Db::name('hsx_boyuan_reference_sync')->insert($job);
    $gate=$service->check($site,$key);accountTest($gate['ready'] && $gate['accounts_ready'] && !in_array('capital_accounts',array_column($gate['resources'],'resource'),true),'accounts never part of auto-selected generic opening');
    $opts=$service->accountOptions($site,$key,0);accountTest(count($opts['rows'])===3 && count($opts['locals'])===1 && (int)$opts['locals'][0]['id']===$keep,'current enabled archive and non-system site targets only');
    accountTest($opts['rows'][0]['suggested_type']==='wechat' && !str_contains(json_encode($opts),'balance'),'exact type hint, no balance exposed');
    accountReject(fn()=>$preview([]),'OPENING_ACCOUNT_CHOICE');accountReject(fn()=>$preview([$pick($s1,0,'invalid')]),'OPENING_ACCOUNT_TYPE');
    accountReject(fn()=>$preview([$pick($s1,0,'bank')+['balance'=>9999]]),'OPENING_ACCOUNT_CHOICE');
    accountReject(fn()=>$preview([$pick($off,0,'other')]),'OPENING_ACCOUNT_SOURCE');
    accountReject(fn()=>$preview([$pick($foreign,0,'bank')]),'OPENING_ACCOUNT_SOURCE');
    accountReject(fn()=>$preview([$pick($s2,$sys)]),'OPENING_ACCOUNT_TARGET');accountReject(fn()=>$preview([$pick($s2,$foreignLocal)]),'OPENING_ACCOUNT_TARGET');
    accountReject(fn()=>$preview([$pick($s1,$keep),$pick($s2,$keep)]),'OPENING_ACCOUNT_TAKEN');
    $same=$archive('SAME','本地原账户');accountReject(fn()=>$preview([$pick($same,0,'bank')]),'OPENING_ACCOUNT_NAME');
    $p=$preview($act); $before=Db::name('erp_capital_account')->where('id',$keep)->find();
    accountTest(array_column($p['rows'],'action')===['create','link'] && !Db::name('hsx_boyuan_mapping')->count(),'preview never creates/matches');
    accountReject(fn()=>$apply([$pick($s1,0,'cash'),$pick($s2,$keep)],$p['token']),'OPENING_CHANGED');
    $payable=Db::name('erp_payable')->insertGetId(['site_id'=>$site,'payable_no'=>'TEST-AP','amount'=>1,'settled_amount'=>0,'status'=>'pending']);
    accountReject(fn()=>$apply($act,$p['token']),'OPENING_OUTSTANDING'); Db::name('erp_payable')->where('id',$payable)->delete();
    $done=$apply($act,$p['token']);accountTest($done['rows']===$p['rows'] && Db::name('hsx_boyuan_mapping')->where('kind','account')->count()===2,'only selected writes & verifiable receipt');
    $created=Db::name('erp_capital_account')->where('account_name','门店一号')->find();
    accountTest($created['account_type']==='wechat' && $created['balance']==='0.00' && !(int)$created['is_default'],'zero opening balance and never new default');
    accountTest($before===Db::name('erp_capital_account')->where('id',$keep)->find(),'link preserves entire original account');
    accountTest(Db::name('erp_money_ledger')->count()===0 && Db::name('erp_settlement')->count()===0 && !Db::name('erp_capital_account')->where('account_name','未选择的户头')->count(),'no movement, settlement or unselected account');
    accountReject(fn()=>$apply($act,$p['token']),'OPENING_ACCOUNT_LINKED');
    accountTest(Db::name('hsx_boyuan_mapping')->where('kind','method')->count()===0,'no cashier method required or created');
    $payment=new PaymentSyncService();$fn=new ReflectionMethod($payment,'mappedVoucherField');$fn->setAccessible(true);
    $default=fn($company,$localId)=>$fn->invoke($payment,$site,$key,['capital_account_id'=>$localId,'company_id'=>'company','company_code'=>$company],'account');
    accountTest($default('C',$keep)==='A2' && $default('C',(int)$created['id'])==='A1','new draft uses exact local payment account mapping');
    accountTest($default('OTHER',$keep)==='' && $default('C',$sys)==='','wrong company or system account never defaults');
    Db::name('hsx_boyuan_reference_sync')->insert(array_replace($job,['run_key'=>str_repeat('f',32),'status'=>'queued']));
    accountTest(!$service->check($site,$key)['accounts_ready'] && $default('C',$keep)==='','incomplete replacement run suppresses account defaults');
    accountReject(fn()=>$service->accountOptions($site,$key,0),'OPENING_SOURCE');
    echo "PASS $checks isolated account-opening assertions; no external requests\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.get_class($e).' '.($e instanceof PDOException?'PDO '.$e->getCode():$e->getMessage()).' @ '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);}
finally{if($pdo && preg_match('/^hsx_boyuan_test_[0-9a-f]{16}$/D',$schema))$pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
