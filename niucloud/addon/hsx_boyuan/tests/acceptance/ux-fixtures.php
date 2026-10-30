<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit(1);
require dirname(__DIR__).'/bootstrap.php'; require dirname(__DIR__).'/Fixtures.php';
use think\facade\Db;
use addon\hsx_boyuan\app\service\{ConfigService,ReferenceService};
use addon\hsx_boyuan\app\support\Contract;
$options=getopt('', ['isolated-local-mysql','state-file:','reference-mode','mock-mode']);
if(empty($options['state-file'])) {
    [$app,$pdo,$schema,$site]=boyuanBoot();
    try {
        addon\hsx_boyuan\tests\Fixtures::seed($site);
        $path=sys_get_temp_dir().'/boyuan-acceptance-'.bin2hex(random_bytes(8)).'.json';
        file_put_contents($path,Contract::json(['schema'=>$schema,'key'=>bin2hex(random_bytes(32))])); chmod($path,0600); echo $path."\n";
    } catch(Throwable $e) {$pdo->exec('DROP DATABASE `'.$schema.'`'); throw $e;}
    exit;
}
$path=(string)$options['state-file'];
if(!preg_match('/^boyuan-acceptance-[a-f0-9]{16}\.json$/D',basename($path))) throw new RuntimeException('Not an isolated state');
$state=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR); [$app,$pdo,$schema,$site]=boyuanBoot($state['schema']);
$cs=new ConfigService(); $old=$cs->get($site);
if(isset($options['mock-mode'])) { $cs->save($site,1,['revision'=>$old['revision'],'mode'=>'mock','enabled'=>true,'paused'=>false,'profile'=>['scenario'=>'success']]); echo "Mock-only batch fixture selected.\n"; exit; }
if(!isset($options['reference-mode'])) exit;
$c=$cs->save($site,1,['revision'=>$old['revision'],'mode'=>'test','enabled'=>false,'paused'=>true,'profile'=>['protocol'=>'openapi_v153','endpoint'=>'https://open-api-uat.139erp.com/api','account_id'=>'UX_FAKE_ACCOUNT','app_id'=>'999999']]);
$key=$c['profiles']['test']['connection_key']; $run=str_repeat('a',32);
Db::name('sys_user')->where('uid',1)->update(['mobile'=>'13800000001','real_name'=>'虚构本站经办人']);
Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'connection_key'=>$key,'mode'=>'test','resource'=>'staff','run_key'=>$run,'status'=>'completed','scope_json'=>'{}','operator_uid'=>1]);
foreach(['UX_FAKE_USER_A'=>'虚构一店经办人','UX_FAKE_USER_B'=>'虚构二店经办人'] as $id=>$name) Db::name('hsx_boyuan_reference')->insert(['site_id'=>$site,'connection_key'=>$key,'resource'=>'staff','external_id'=>$id,'code'=>$id,'name'=>$name,'enabled'=>1,'seen_run'=>$run,'phone_hash'=>ReferenceService::phoneHash($site,'13800000001'),'phone_masked'=>'138****0001','extra_json'=>Contract::json(['company_name'=>'虚构门店'])]);
echo "Fictional same-phone candidates prepared; no vendor calls.\n";
