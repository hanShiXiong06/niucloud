<?php
declare(strict_types=1);
// Explicit UAT-only runner. It retains one isolated schema for interactive acceptance.
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true) || !in_array('--read-only-uat',$argv,true)) exit("Requires --isolated-local-mysql --read-only-uat --credentials-file=private.json\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService,ReferenceService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;
$options=getopt('', ['isolated-local-mysql','read-only-uat','credentials-file:']);
$file=$options['credentials-file']??'';
if(!$file || !is_file($file) || (fileperms($file)&0077)!==0) exit("Private credentials file required (chmod 600)\n");
$credentials=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
if(($credentials['endpoint']??'')!=='https://open-api-uat.139erp.com/api') exit("Only the user-confirmed UAT endpoint is allowed\n");
$statePath=''; $report=['scope'=>'UAT reference reads into isolated local schema','remote_business_writes'=>0,'business_database_modified'=>false];
try {
    [$app,$pdo,$schema,$site]=boyuanBoot(); (new addon\hsx_boyuan\Addon())->install();
    $service=new ConfigService();
    $service->save($site,1,['revision'=>0,'mode'=>'test','enabled'=>false,'paused'=>true,'finance_enabled'=>false,'profile'=>[
        'protocol'=>'openapi_v153','endpoint'=>$credentials['endpoint'],'gateway_path'=>'/gateway.do','app_id'=>(string)$credentials['app_id'],
        'app_secret'=>(string)$credentials['app_secret'],'account_id'=>(string)$credentials['account_id'],
    ]]);
    unset($credentials);
    $statePath=sys_get_temp_dir().'/boyuan-uat-acceptance-'.bin2hex(random_bytes(8)).'.json';
    $old=umask(0077); file_put_contents($statePath,Contract::json(['schema'=>$schema,'key'=>bin2hex(random_bytes(32))])); umask($old);
    $report['state_file']=$statePath;
    $probe=$service->probe($site,1); $report['account_verified']=$probe['verified'];
    $sync=new ReferenceService(); $sync->start($site,1,array_keys(OpenApi::RESOURCES));
    $calls=0; $deadline=time()+900;
    while($calls<250 && time()<$deadline) {
        $rows=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->whereIn('status',['queued','running','retry'])->order('id')->select()->toArray();
        if(!$rows) break; $did=false;
        foreach($rows as $row) if((int)$row['next_at']<=time()) { $sync->page($site,(int)$row['id']); $calls++; $did=true; usleep(500000); if($calls>=250 || time()>=$deadline) break; }
        if(!$did) sleep(1);
    }
    $report['resources']=$sync->jobs($site); $report['pages_attempted']=$calls;
    $report['all_completed']=!array_filter($report['resources'],static fn($row)=>($row['job']['status']??'')!=='completed');
} catch(Throwable $error) {
    $report['all_completed']=false; $report['error']=$error instanceof Problem?$error->getMessage():'Local UAT verification did not complete: '.get_class($error);
}
echo Contract::json($report)."\n";
if($statePath!=='') { $old=umask(0077); file_put_contents($statePath.'.report.json',Contract::json($report)); umask($old); }
