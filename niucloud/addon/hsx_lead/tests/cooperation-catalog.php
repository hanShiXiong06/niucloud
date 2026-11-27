<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $f) if (function_exists($f)) exit("Disable HTTP functions before testing\n");
if (filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\service\{Context,CatalogService};
use addon\hsx_lead\app\service\cooperation\{CooperationService,ConfigService,PartnerService,CapabilityService,Store};
use core\exception\CommonException;
$pdo=null;$schema='';$checks=0;
function catalogCheck(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label);$GLOBALS['checks']++; }
function catalogDeny(callable $work): void { try {$work();} catch (CommonException $e) {$GLOBALS['checks']++;return;}throw new RuntimeException('Expected safe rejection'); }
try {
    [$app,$pdo,$schema,$site]=leadBoot();
    $admin=new Context($site,1);$settings=new ConfigService($admin);$config=$settings->get();$config['enabled']=true;$config['publishing_required']=false;$config['fee_confirmed']=true;$config['businesses'][0]['selection_limit']=3;$settings->save($config);
    $source=[];for($i=0;$i<150;$i++)$source[]=['title'=>'Fixture model '.$i,'brand'=>$i<75?'Fixture A':'Fixture B','category_name'=>$i<75?'手机':'平板'];
    (new CatalogService($admin))->save(['format'=>'json','content'=>json_encode($source,JSON_UNESCAPED_UNICODE)]);
    $context=new Context($site,0,101);$service=new CooperationService($context);
    $quick=$service->catalog();$all=$service->catalog(['browse'=>1]);
    catalogCheck(count($quick)===100&&count($all)===150,'search keeps its bound while explicit browse returns complete directory');
    catalogCheck($all[149]['category_name']==='平板'&&$all[0]['category_name']==='手机','category labels survive public projection');
    catalogCheck(is_array($all[0]['aliases'])&&!isset($all[0]['credentials']),'public catalog exposes safe aliases but no private fields');
    catalogDeny(fn()=>$service->catalog(['browse'=>'all']));
    catalogCheck(count($service->catalog(['browse'=>1,'keyword'=>'Fixture model 149']))===1,'browse retains server keyword filtering');
    (new PartnerService($context))->saveProfile(['name'=>'Catalog fixture','contact_name'=>'Fixture','mobile'=>'18800000001','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'address'=>'Fixture']);
    $base=['request_id'=>'catalog-demand-0001','customer_name'=>'Fixture customer','mobile'=>'13800000000','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'business_type'=>'purchase','model_ids'=>array_column(array_slice($all,0,4),'id'),'consent'=>true,'attachments'=>[]];
    catalogDeny(fn()=>$service->create($base));
    catalogCheck(Store::query('collaboration',$site)->count()===0,'over-limit requests do not create partial leads');
    $base['model_ids']=array_column(array_slice($all,0,3),'id');$lead=$service->create($base);
    catalogCheck(count($lead['models'])===3,'configured demand limit accepted at boundary');
    $bad=$config;$bad['businesses'][0]['selection_limit']='3.2';catalogDeny(fn()=>$settings->save($bad));
    catalogCheck($settings->get()['businesses'][0]['selection_limit']===3,'invalid settings preserve saved business limit');
    $config['businesses'][0]['selection_limit']=0;$settings->save($config);$base['request_id']='catalog-demand-0002';$base['model_ids']=array_column(array_slice($all,0,25),'id');
    catalogCheck(count($service->create($base)['models'])===25,'zero removes former hardcoded twenty-model business limit');
    (new CapabilityService($context))->save((int)(new PartnerService($context))->own()['id'],['business_capabilities'=>[['business_type'=>'purchase','option_ids'=>array_column($all,'id')]]]);
    catalogCheck(count((new CapabilityService($context))->models((int)(new PartnerService($context))->own()['id']))===150,'delivery capability is independent of demand count');
    catalogDeny(fn()=>(new CooperationService(new Context($site+1,0,101)))->catalog(['browse'=>1]));
    echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'owned random localhost schema only; no existing business data or remote calls']),PHP_EOL;
} finally { if ($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema); }
