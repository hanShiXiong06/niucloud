<?php
declare(strict_types=1);
/** Read-only remote recovery. This runner has no call to purchaseCreate(). */
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true) || !in_array('--query-original-only',$argv,true)) exit("Requires --isolated-local-mysql --query-original-only --schema=<isolated> --task=1\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,PurchaseSyncService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_boyuan\app\gateway\OpenApi;
use think\facade\Db;
$options=getopt('', ['isolated-local-mysql','query-original-only','schema:','task:']);
$schema=(string)($options['schema']??''); $id=(int)($options['task']??0);
if(!preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema) || $id<=0) exit("Explicit isolated schema and task required\n");
$report=['scope'=>'recover original UAT purchase by exact serial; never recreate','remote_write_calls'=>0,'production_calls'=>0];
try {
    [$app,$pdo,$schema,$site]=boyuanBoot($schema); $configService=new ConfigService(); $c=$configService->get($site,false); $p=$c['profiles'][$c['mode']];
    if($c['mode']!=='test' || ($p['endpoint']??'')!=='https://open-api-uat.139erp.com/api' || $p['gateway_path']!=='/gateway.do') throw new RuntimeException('Only confirmed UAT allowed');
    $api=$configService->transport($site,$p,'test'); if(!$api instanceof OpenApi) throw new RuntimeException('Official UAT API required');
    $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->find();
    if(!$task || $task['mode']!=='test' || $task['connection_key']!==$p['connection_key']) throw new RuntimeException('Task account mismatch');
    $request=Contract::decode($task['request_json']);
    if(($request['schema']??'')!=='boyuan.purchase.v154' || ($request['stage']??'')!=='frozen' || empty($request['lines'])) throw new RuntimeException('Frozen original purchase required');
    $expected=array_column($request['lines'],null,'imei'); $date=$request['biz']['head']['business_date']; $matches=[]; $shape=[];
    for($page=1;$page<=20;$page++) {
        $data=$api->purchaseQuery('imei',['business_date_start'=>$date,'business_date_end'=>$date,'bill_type'=>'PUR','page_no'=>$page,'page_size'=>100]);
        $shape=array_keys($data); $rows=$data['result_list']??null; if(!is_array($rows)) throw new RuntimeException('UAT query returned no result_list; keys='.implode(',',$shape));
        foreach($rows as $row) {
            $serial=(string)($row['imei1']??'');
            if(!isset($expected[$serial])) continue;
            $matches[]=array_intersect_key($row,array_flip(['bill_id','bill_type','imei1','product_id','warehouse_id','supplier_id','qty','amounts','is_effective']));
        }
        if($page*100>=(int)($data['total_results']??count($rows))) break;
        if($page===20) throw new RuntimeException('Read-only recovery page limit');
    }
    $numbers=array_values(array_unique(array_column($matches,'bill_id'))); $report['matching_serial_records']=$matches; $report['query_shape']=$shape;
    if(count($numbers)!==1 || count($matches)!==count($expected)) throw new RuntimeException('Original serial was not uniquely resolved; no create attempted');
    $service=new PurchaseSyncService(); $view=$service->view($site,$id);
    if($view['task']['status']==='succeeded') { $report['already_verified']=true; }
    else {
        $service->queueVerify($site,$id,1,['revision'=>$view['task']['revision'],'mode'=>'test','connection_key'=>$p['connection_key'],'external_no'=>(string)$numbers[0]]);
        Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->update(['next_at'=>0]);
        $service->process($site,$id);
    }
    $view=$service->view($site,$id); $report['external_no']=$view['task']['external_no']; $report['final_status']=$view['task']['status']; $report['reason']=$view['task']['reason']; $report['result']=$view['result'];
} catch(Throwable $error) {
    $report['completed']=false; $report['error_type']=get_class($error); $report['reason']=$error instanceof Problem?$error->reason.': '.$error->getMessage():mb_substr($error->getMessage(),0,250);
}
echo Contract::json(Contract::redact($report))."\n";
