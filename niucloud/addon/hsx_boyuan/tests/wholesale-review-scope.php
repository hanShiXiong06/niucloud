<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit(2);
foreach (['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn)) exit(2);
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\adminapi\controller\Workbench;
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;
$pdo=null;$schema='';$failed=false;
try {
    [, $pdo,$schema,$site]=boyuanBoot();
    $key='scope'; $snap=['kind'=>'sale','sale_type'=>'wholesale','payment_method_ref'=>'method:挂账'];
    $seed=function(string $no,string $mode,string $kind,$snapshot,int $siteId,string $conn){
        return Db::name('hsx_boyuan_task')->insertGetId(['site_id'=>$siteId,'connection_key'=>$conn,'mode'=>$mode,'kind'=>$kind,'fact_key'=>$no,'event_id'=>$no,'operation_id'=>$no,'business_no'=>$no,
            'snapshot_json'=>is_array($snapshot)?json_encode($snapshot,JSON_UNESCAPED_UNICODE):$snapshot,'request_json'=>'{}','result_json'=>'{}']);
    };
    $seed('correct','mock','sale',$snap,$site,$key);
    $retail=$seed('retail','mock','sale',array_replace($snap,['sale_type'=>'retail']),$site,$key);
    $seed('cash','mock','sale',array_replace($snap,['payment_method_ref'=>'method:现结']),$site,$key);
    $seed('suffix','mock','sale',array_replace($snap,['payment_method_ref'=>'method:挂账备注']),$site,$key);
    $seed('wrong_fact','mock','sale',array_replace($snap,['kind'=>'purchase']),$site,$key);
    $seed('malformed','mock','sale','{',$site,$key);$seed('another-site','mock','sale',$snap,$site+1,$key);
    $seed('old-conn','mock','sale',$snap,$site,'old');$seed('uat','test','wholesale_outbound',$snap,$site,$key);
    $seed('uat-other','test','sale',$snap,$site,$key);$seed('prod','production','wholesale_outbound',$snap,$site,$key);
    $apply=new ReflectionMethod(Workbench::class,'wholesaleScope'); $apply->setAccessible(true);
    $query=function(string $mode,string $type='wholesale_review',string $k='scope')use($site,$apply){
        $q=Db::name('hsx_boyuan_task')->where('site_id',$site);
        $apply->invoke(null,$q,$type,['mode'=>$mode,'profiles'=>[$mode=>['connection_key'=>$k]]]);return $q;
    };
    $checks=0;$assert=function($b,$m)use(&$checks){if(!$b)throw new RuntimeException($m);$checks++;};
    $assert($query('mock')->column('business_no')===['correct'],'mock classification, valid JSON and connection');
    $assert(!$query('mock')->where('id',$retail)->find(),'same scope applied to id/detail');
    $assert($query('test')->column('business_no')===['uat'],'UAT dedicated kinds only');
    $assert(!$query('production')->count() && !$query('mock','wholesale_review','')->count(),'production and unknown connection fail closed');
    $assert($query('mock','')->count()===10,'no type retains normal filters');
    $no=false;try{$query('mock','unsupported');}catch(Problem $e){$no=$e->reason==='TASK_TYPE_INVALID';}$assert($no,'reject unsupported types');
    echo "PASS $checks isolated MySQL wholesale list/detail scope checks; 0 external calls\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,get_class($e).': '.($e instanceof PDOException?$e->getCode():$e->getMessage()).' at '.basename($e->getFile()).':'.$e->getLine()."\n");}
finally{if($pdo && preg_match('/^hsx_boyuan_test_[0-9a-f]{16}$/D',$schema))$pdo->exec('DROP DATABASE `'.$schema.'`');}
exit($failed?1:0);
