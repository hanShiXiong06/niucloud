<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn)if(function_exists($fn))exit(2);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,TaskService};
use addon\hsx_boyuan\app\support\{Contract,InterfaceTrace};
use addon\hsx_recycle\app\listener\audit\BusinessOperationRecorded;
use addon\hsx_recycle\app\service\core\audit\{OperationAuditSchema,OperationAuditService};
use think\facade\Db;
$pdo=null;$schema='';$failed=false;
try{
    [$app,$pdo,$schema,$site]=boyuanBoot(null,true);
    OperationAuditSchema::apply();
    $app->event->listen('HsxRecycleBusinessOperationRecorded',BusinessOperationRecorded::class);
    Db::name('recycle_order')->insert(['id'=>7,'site_id'=>$site,'order_no'=>'FIX-REC']);
    Db::name('recycle_device')->insert(['id'=>8,'site_id'=>$site,'order_id'=>7,'imei'=>'FIX-SERIAL']);
    Db::name('erp_asset')->insert(['id'=>9,'site_id'=>$site,'asset_no'=>'AS9','source_plugin'=>'hsx_recycle','spec_json'=>'{"source_device_id":8}']);
    $id=(int)Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$site,'kind'=>'purchase','mode'=>'test','connection_key'=>'fixture','event_id'=>'EV9','fact_key'=>'fixture:9',
        'operation_id'=>'OP9','business_no'=>'PO-FIX','snapshot_json'=>'{"lines":[{"device_ref":"asset:9"}]}','request_json'=>'{}','result_json'=>'{}','status'=>'pending']);
    foreach([1,2] as $_)TaskService::audit($site,$id,'fixture','purchase_submit','pending','submitted',1,['request'=>['sign'=>'NO_TOKEN_IN_DB']]);
    $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->find();
    // Make a transport journal pair without performing any HTTP call or writing to the real wire log.
    $trace=new InterfaceTrace($site,'test',InterfaceTrace::task($task),static fn()=>'',
        static fn($r)=>\addon\hsx_boyuan\app\support\BusinessOperation::wire($site,'fixture',$r));
    $pair=$trace->begin('POST','purchase_create',['service'=>'boyuan.biz.bill.purchase.generate','sign'=>'NO_TOKEN_IN_DB']);
    $trace->end($pair,'response_received',200,0,['return_code'=>'SUCCESS','api_response'=>['account_id'=>'NO_TOKEN_IN_DB']],'json','');
    $q=Db::name('recycle_order_log')->where(['site_id'=>$site,'order_id'=>7,'action'=>'audit_boyuan']);
    $list=(new OperationAuditService)->page($site,['source'=>'boyuan','order_id'=>7]);
    if($q->count()!==3 || count($list['rows'])!==3)throw new RuntimeException('原单时间线应恰好三条，重复投影只一次');
    if(str_contains(Contract::json($list),'NO_TOKEN_IN_DB'))throw new RuntimeException('请求原文不得进入业务日志');
    $names=$q->order('id')->column('operator_name');
    if($names!==['测试经办人','系统','系统'])throw new RuntimeException('制单人、系统请求和系统响应必须区分');
    if(!empty((new OperationAuditService)->page($site+1,['source'=>'boyuan','order_id'=>7])['rows']))throw new RuntimeException('不可跨站读取');
    echo "PASS cross-addon transaction: real task audit + file-safe request/result → plugin event → single original recycle order; dedupe, operator, no payload, tenant isolation. No remote calls.\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine()."\n");}
finally{if($pdo&&preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema))$pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
