<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $function)if(function_exists($function))exit("Disable network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\service\{Context,OwnershipScope};
use addon\hsx_lead\app\service\cooperation\{Store,CooperationService};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

$checks=0;$pdo=null;$schema='';$failed=false;
function coopOwnerCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException('Assertion failed: '.$message);$GLOBALS['checks']++;}
function coopOwnerDeny(callable $call,string $message):void{try{$call();}catch(CommonException $error){coopOwnerCheck(true,$message);return;}throw new RuntimeException('Expected denial: '.$message);}
try{
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);
    (new app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_lead');
    // A lead-only role must not silently inherit the overview page permission.
    $read=['hsx_lead_cooperation_leads','hsx_lead_cooperation_detail'];
    $overview=['hsx_lead_cooperation_manage'];
    $leadScope='hsx_lead_cooperation_leads_scope_read';
    $role=static function(string $name,array $keys)use($site):int{return(int)Db::name('sys_role')->insertGetId(['site_id'=>$site,'role_name'=>$name,'rules'=>Data::json($keys),'status'=>1]);};
    $staffRole=$role('Cooperation-only staff',array_merge($read,[$leadScope]));
    $managerRole=$role('Delegated cooperation manager',array_merge($read,$overview,[OwnershipScope::ALL_DATA,'hsx_lead_cooperation_owner','hsx_lead_cooperation_owners']));
    $allRole=$role('All data but no transfer',array_merge($read,$overview,[OwnershipScope::ALL_DATA]));
    $transferRole=$role('Transfer but no all data',array_merge($read,['hsx_lead_cooperation_owner']));
    $legacyRole=$role('Legacy-only staff',['hsx_lead_manage_leads','hsx_lead_manage_lead_info']);
    $legacyManagerRole=$role('Removed transfer key is not cooperation transfer',array_merge($read,[OwnershipScope::ALL_DATA,'hsx_lead_manage_lead_owner']));
    $listOnlyRole=$role('Lead list without detail',['hsx_lead_cooperation_leads',$leadScope]);
    $scopeOnlyRole=$role('Scope is not a business permission',[$leadScope]);
    $noRole=$role('No cooperation permissions',[]);
    $user=static function(int $uid,string $name,int $roleId,int $target=0,bool $admin=false)use($site):void{
        Db::name('sys_user')->insert(['uid'=>$uid,'username'=>'cooperation-owner-'.$uid,'real_name'=>$name,'status'=>1]);
        Db::name('sys_user_role')->insert(['uid'=>$uid,'site_id'=>$target?:$site,'is_admin'=>$admin?1:0,'status'=>1,'role_ids'=>Data::json($roleId?[$roleId]:[])]);
    };
    $user(11,'Coop Staff A',$staffRole);$user(12,'Coop Staff B',$staffRole);$user(21,'Coop Manager',$managerRole);
    $user(22,'All data only',$allRole);$user(23,'Transfer only',$transferRole);$user(24,'Legacy employee',$legacyRole);
    $user(25,'Other site employee',$staffRole,$site+1);$user(26,'Disabled employee',$staffRole);$user(27,'Disabled site role',$staffRole);
    $user(29,'Legacy manager',$legacyManagerRole);$user(28,'Platform-only administrator',0);Db::name('sys_user_role')->where('uid',28)->delete();Db::name('sys_user_role')->insert(['uid'=>28,'site_id'=>0,'is_admin'=>1,'status'=>1]);
    $user(30,'No grants',$noRole);$user(31,'List reader',$listOnlyRole);$user(32,'Scope reader',$scopeOnlyRole);
    Db::name('sys_user')->where('uid',26)->update(['status'=>0]);Db::name('sys_user_role')->where('uid',27)->update(['status'=>0]);
    $context=static fn(int $uid):Context=>new Context($site,$uid);$service=static fn(int $uid):CooperationService=>new CooperationService($context($uid));
    $admin=$service(1);$delegated=$service(21);
    coopOwnerCheck(Store::scope($context(1))['can_transfer'],'actual site administrator may transfer cooperation');
    coopOwnerCheck(Store::scope($context(21))['can_transfer'],'new delegated grants enable cooperation transfer without legacy transfer grant');
    coopOwnerCheck(!Store::scope($context(22))['can_transfer'],'all-data alone does not grant transfer');
    coopOwnerCheck(!Store::scope($context(23))['can_transfer'],'new transfer alone does not grant all-data access');
    coopOwnerCheck(!Store::scope($context(29))['can_transfer'],'legacy transfer grant does not silently grant new business mutation');
    coopOwnerCheck(!in_array('hsx_lead_cooperation_manage',$context(11)->ownership()->operator()['keys'],true),'lead-only staff fixture has no overview grant');
    coopOwnerCheck(Store::scope($context(11))['uid']===11&&!Store::scope($context(11))['all_data'],'page scope exposes only the current identity boundary');
    coopOwnerDeny(fn()=>Store::scope($context(30)),'no granted plugin permission cannot read current scope');
    coopOwnerDeny(fn()=>Store::scope($context(25)),'foreign-site identity cannot read this site scope');
    $menuService=new app\service\admin\sys\MenuService();
    $scopeKeys=Db::name('sys_menu')->where('app_type','site')->where('addon','hsx_lead')->where('api_url','hsx_lead/cooperation/scope')->where('methods','get')->column('menu_key');
    coopOwnerCheck(count($scopeKeys)>=2&&count($scopeKeys)===count(array_unique($scopeKeys)),'independent page scope buttons have unique keys sharing one endpoint');
    foreach($scopeKeys as $scopeKey){
        $api=$menuService->getApiListByMenuKeys([$scopeKey],'site');
        coopOwnerCheck(in_array('hsx_lead/cooperation/scope',$api['get']??[],true)&&!in_array('hsx_lead/cooperation/dashboard',$api['get']??[],true),'each scope key independently grants scope without overview: '.$scopeKey);
    }
    coopOwnerCheck(!in_array('hsx_lead/cooperation/scope',$menuService->getApiListByMenuKeys([],'site')['get']??[],true),'missing API grant does not expose scope endpoint');
    $savedRules=Db::name('sys_role')->where('role_id',$staffRole)->value('rules');
    (new app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_lead');
    coopOwnerCheck(Db::name('sys_role')->where('role_id',$staffRole)->value('rules')===$savedRules&&$context(11)->ownership()->allows('hsx_lead_cooperation_detail'),'menu refresh preserves existing role keys and active grants');
    $owners=$delegated->owners();$ids=array_map('intval',array_column($owners['data'],'uid'));
    coopOwnerCheck(in_array(11,$ids,true)&&in_array(12,$ids,true),'cooperation-only employees are eligible owner targets');
    coopOwnerCheck(!in_array(24,$ids,true),'legacy-only employees cannot receive a business they cannot read');
    coopOwnerCheck(!in_array(31,$ids,true)&&!in_array(32,$ids,true),'list-only and scope-only roles cannot receive unreadable details');
    coopOwnerCheck(!in_array(25,$ids,true)&&!in_array(26,$ids,true)&&!in_array(27,$ids,true),'foreign and disabled identities excluded');
    coopOwnerCheck(!in_array(28,$ids,true),'platform administrator without local membership is not a local owner target');
    $searched=$delegated->owners(['q'=>'Staff A']);coopOwnerCheck($searched['total']===1,'owner keyword search returns matching active target');
    coopOwnerCheck(count($delegated->owners(['limit'=>1])['data'])===1&&$delegated->owners(['limit'=>1])['total']>1,'owner pagination preserves total');
    coopOwnerDeny(fn()=>$service(11)->owners(),'ordinary employee cannot enumerate transfer targets');
    coopOwnerDeny(fn()=>$service(22)->owners(),'all-data without transfer cannot enumerate targets');
    coopOwnerDeny(fn()=>$service(23)->owners(),'transfer without all-data cannot enumerate targets');
    coopOwnerCheck(OwnershipScope::TRANSFER==='hsx_lead_cooperation_owner','current ownership grant points to cooperation transfer');
    coopOwnerDeny(fn()=>$context(24)->ownership()->operator(),'removed platform-only grants cannot authorize current staff identity');
    $merchant=(int)Data::query('merchant',$site)->insertGetId(['site_id'=>$site,'name'=>'Synthetic cooperation merchant','member_id'=>101]);
    $record=static function(int $owner,string $suffix,int $target=0)use($site,$merchant):int{return(int)Store::query('collaboration',$target?:$site)->insertGetId(['site_id'=>$target?:$site,'lead_no'=>'COOP_OWNER_'.$suffix,'publisher_id'=>$merchant,'owner_uid'=>$owner,'request_id'=>'owner-fixture-'.$suffix,'customer_name'=>'Synthetic '.$suffix,'models_json'=>Data::json([['id'=>'synthetic','title'=>'Synthetic model','brand'=>'Fixture']]),'create_time'=>time(),'update_time'=>time()]);};
    $a=$record(11,'A');$b=$record(12,'B');$unassigned=$record(0,'U');$foreign=$record(11,'X',$site+1);
    $aRows=$service(11)->lists();coopOwnerCheck($aRows['total']===1&&(int)$aRows['data'][0]['id']===$a,'new employee sees only current own records');
    coopOwnerCheck((int)$service(11)->detail($a)['id']===$a,'lead-only employee reads own details without overview permission');
    coopOwnerDeny(fn()=>$service(11)->dashboard(),'lead page grant does not grant the independent overview page');
    coopOwnerDeny(fn()=>$service(11)->detail($unassigned),'employee cannot read unassigned collaboration records');
    coopOwnerDeny(fn()=>$service(11)->detail($foreign),'own uid does not cross the tenant boundary');
    coopOwnerDeny(fn()=>$service(11)->lists(['owner_uid'=>0]),'employee cannot request unassigned records');
    coopOwnerCheck($service(31)->lists()['total']===0,'list-only employee can query own empty list without overview');
    coopOwnerDeny(fn()=>$service(31)->detail($a),'list page does not implicitly grant detail permission');
    coopOwnerDeny(fn()=>$service(32)->lists(),'scope-only role does not imply lead list permission');
    coopOwnerDeny(fn()=>$service(32)->detail($a),'scope-only role does not imply lead detail permission');
    coopOwnerDeny(fn()=>$service(30)->lists(),'role with no grants cannot query business records');
    $allRows=$service(22)->lists();
    coopOwnerCheck($allRows['total']===3&&!in_array($foreign,array_map('intval',array_column($allRows['data'],'id')),true),'all-data includes unassigned records but never another site');
    $filtered=$service(22)->lists(['owner_uid'=>12]);
    coopOwnerCheck($filtered['total']===1&&(int)$filtered['data'][0]['id']===$b,'all-data owner filter narrows rather than enlarges the site scope');
    coopOwnerDeny(fn()=>$service(11)->detail($b),'employee cannot read another owner business');
    coopOwnerDeny(fn()=>$service(11)->lists(['owner_uid'=>12]),'owner parameter cannot escalate an employee scope');
    coopOwnerDeny(fn()=>$service(23)->transferOwner($a,['owner_uid'=>12,'expected_owner_uid'=>11]),'transfer-only cannot mutate ownership');
    coopOwnerDeny(fn()=>$service(22)->transferOwner($a,['owner_uid'=>12,'expected_owner_uid'=>11]),'all-data-only cannot mutate ownership');
    coopOwnerDeny(fn()=>$delegated->transferOwner($a,['owner_uid'=>24,'expected_owner_uid'=>11]),'new business cannot be transferred to legacy-only staff');
    coopOwnerDeny(fn()=>$delegated->transferOwner($foreign,['owner_uid'=>12,'expected_owner_uid'=>11]),'transfer is tenant scoped');
    $moved=$delegated->transferOwner($a,['owner_uid'=>12,'expected_owner_uid'=>11]);
    coopOwnerCheck((int)$moved['owner_uid']===12&&$moved['can_transfer'],'new owner transfer returns updated scope-aware DTO');
    coopOwnerCheck($service(11)->lists()['total']===0&&$service(12)->lists()['total']===2,'old employee loses access and new employee gains it');
    coopOwnerDeny(fn()=>$delegated->transferOwner($a,['owner_uid'=>21,'expected_owner_uid'=>11]),'stale expected owner cannot overwrite another transfer');
    coopOwnerCheck((int)Store::row('collaboration',$site,$a)['owner_uid']===12,'failed stale transfer does not change current owner');
    $before=Data::query('audit',$site)->where('entity_type','collaboration')->where('entity_id',$a)->where('action','owner_transferred')->count();
    $delegated->transferOwner($a,['owner_uid'=>12,'expected_owner_uid'=>12]);
    coopOwnerCheck(Data::query('audit',$site)->where('entity_type','collaboration')->where('entity_id',$a)->where('action','owner_transferred')->count()===$before,'same-owner action is a harmless no-op');
    coopOwnerCheck($delegated->dashboard()['viewer_scope']['can_transfer'],'new dashboard exposes cooperation transfer semantics');
    coopOwnerCheck(!$service(22)->dashboard()['viewer_scope']['can_transfer'],'dashboard does not overstate all-data user permissions');
    coopOwnerCheck($service(12)->detail($a)['can_transfer']===false,'employee detail does not expose transfer capability');
    echo "PASS cooperation ownership: $checks checks; isolated local schema; current collaboration employee boundaries\n";
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL cooperation ownership: '.$error->getMessage()."\n".$error->getTraceAsString()."\n");}
finally{if($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema);}
exit($failed?1:0);
