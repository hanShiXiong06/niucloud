<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $f)if(function_exists($f))exit("Disable external HTTP/network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{PartnerService,StorefrontService,Store,UploadService};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

$checks=0;$pdo=null;$schema='';$failed=false;
function storefrontCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function storefrontReject(callable $call,string $label):void{try{$call();}catch(CommonException $e){storefrontCheck(true,$label);return;}throw new RuntimeException('Not rejected: '.$label);}
try{
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);
    $official=file_get_contents(dirname(__DIR__,3).'/app/install/source/database.sql');
    if(!preg_match('/CREATE TABLE\s+`sys_attachment`\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/si',$official,$matches))throw new RuntimeException('Missing official attachment fixture');
    $pdo->exec(str_replace('CREATE TABLE `sys_attachment`','CREATE TABLE `lt_sys_attachment`',$matches[0]));
    $beforeTables=Db::query('SHOW TABLES');$beforeFields=Db::query('SHOW COLUMNS FROM lt_hsx_lead_merchant');
    $ctx=new Context($site,0,101);$other=new Context($site,0,102);$cross=new Context($site+1,0,103);
    $photos=new StorefrontService($ctx);$profiles=new PartnerService($ctx);
    $photos->assertUploadAllowed();storefrontCheck(true,'valid member can upload before onboarding');
    storefrontCheck(method_exists(UploadService::class,'storefront'),'dedicated upload uses plugin service');
    $route=file_get_contents(dirname(__DIR__).'/app/api/route/cooperation.php');
    storefrontCheck(str_contains($route,"Route::post('storefront'")&&str_contains($route,'ApiCheckToken::class,true'),'storefront route retains member authentication');
    storefrontReject(fn()=>(new StorefrontService(new Context($site,1)))->assertUploadAllowed(),'admin identity is not substituted for merchant upload identity');
    storefrontReject(fn()=>(new StorefrontService(new Context($site+1,0,101)))->assertUploadAllowed(),'cross-site member identity cannot upload');
    $fixture=static function(string $name,Context $owner,string $kind=StorefrontService::UPLOAD_ENTITY)use($site):string{
        $path='upload/hsx_lead/storefront-fixture/'.$name.'.png';
        $id=(int)Db::name('sys_attachment')->insertGetId(['site_id'=>$owner->site,'path'=>$path,'url'=>'https://cdn.invalid/'.$name.'.png','att_type'=>'image']);
        $owner->audit($kind,$id,'uploaded',[]);return $path;
    };
    $own=$fixture('own',$ctx);$otherImage=$fixture('other',$other);$crossImage=$fixture('cross',$cross);$chat=$fixture('private-chat',$ctx,'cooperation_attachment');
    $input=['name'=>'Storefront fixture','contact_name'=>'Fixture contact','mobile'=>'13900000101','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'address'=>'Fixture address','provider'=>'wanji','storefront_image'=>$own,'is_reliable'=>true,'certification_status'=>'active'];
    $profile=$profiles->saveProfile($input);$id=(int)$profile['id'];
    storefrontCheck($profile['storefront_image']===$own,'new onboarding persists and returns the uploaded path');
    storefrontCheck($profile['is_reliable']===false&&$profile['certification_status']==='none','member cannot acquire reliable certification through profile payload');
    storefrontCheck(Db::name('sys_config')->where('site_id',$site)->where('config_key','HSX_LEAD_STOREFRONT_'.$id)->count()===1,'media uses existing per-site config row');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['storefront_image']===$own,'public store cards receive the same storefront');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),true,[])['storefront_image']===$own,'private profile receives storefront');
    storefrontCheck((new StorefrontService($cross))->image($id)==='','same numeric merchant id does not expose another site photo');
    storefrontReject(fn()=>(new StorefrontService($other))->save($id,['storefront_image'=>$otherImage]),'another member cannot mutate this merchant media');
    foreach([$otherImage,$crossImage,$chat,'https://invalid.example/unowned.png','https://cdn.invalid/own.png','../own.png',str_repeat('x',1001),null,[],true] as $bad){
        storefrontReject(fn()=>$profiles->saveProfile(array_replace($input,['name'=>'must-rollback','storefront_image'=>$bad])),'reject invalid, foreign or non-storefront reference');
        storefrontCheck(Data::row('merchant',$site,$id)['name']==='Storefront fixture','media validation failure rolls profile edits back');
        storefrontCheck($photos->image($id)===$own,'media validation failure preserves original storefront');
    }
    $without=$input;unset($without['storefront_image']);$without['name']='Profile without photo field';
    storefrontCheck($profiles->saveProfile($without)['storefront_image']===$own,'omitting photo field preserves saved storefront');
    storefrontCheck($profiles->saveProfile(array_replace($without,['storefront_image'=>'']))['storefront_image']==='','explicit empty string clears storefront');
    storefrontCheck($profiles->saveProfile($input)['storefront_image']===$own,'existing profile can set own storefront again');
    storefrontCheck($profiles->saveProfile($input)['storefront_image']===$own,'repeat save does not duplicate media configuration');
    storefrontCheck(Db::name('sys_config')->where('site_id',$site)->where('config_key','HSX_LEAD_STOREFRONT_'.$id)->count()===1,'repeated save updates one config row');
    $profiles2=new PartnerService($other);storefrontReject(fn()=>$profiles2->saveProfile(array_replace($input,['mobile'=>'13900000102','storefront_image'=>$own])),'new merchant cannot onboard with another member image');
    storefrontCheck(Data::query('merchant',$site)->where('member_id',102)->count()===0,'failed media onboarding leaves no merchant');
    storefrontCheck(Store::query('partner',$site)->where('merchant_id','<>',$id)->count()===0,'failed media onboarding leaves no partner');
    Db::name('member')->where('member_id',101)->update(['status'=>0]);storefrontReject(fn()=>$photos->assertUploadAllowed(),'disabled member cannot upload');Db::name('member')->where('member_id',101)->update(['status'=>1]);
    Db::name('member')->where('member_id',101)->update(['is_del'=>1]);storefrontReject(fn()=>$photos->assertUploadAllowed(),'deleted member cannot upload');Db::name('member')->where('member_id',101)->update(['is_del'=>0]);
    Data::query('merchant',$site)->where('id',$id)->update(['status'=>0]);storefrontReject(fn()=>$photos->assertUploadAllowed(),'paused merchant cannot upload');Data::query('merchant',$site)->where('id',$id)->update(['status'=>1]);
    Store::query('partner',$site)->where('merchant_id',$id)->update(['certification_status'=>'active','cert_expires_at'=>time()+86400]);
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['is_reliable']===true,'active future certification remains server-owned reliable state');
    Store::query('partner',$site)->where('merchant_id',$id)->update(['cert_expires_at'=>time()-1]);
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['is_reliable']===false,'expired certification never yields reliable effect');
    Store::query('partner',$site)->where('merchant_id',$id)->update(['cert_expires_at'=>time()+86400,'blocked_at'=>time()]);
    storefrontReject(fn()=>$photos->assertUploadAllowed(),'blocked merchant cannot upload');
    storefrontReject(fn()=>$profiles->saveProfile($input),'blocked merchant cannot save storefront or profile');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['is_reliable']===false,'blocked certification never yields reliable effect');
    storefrontCheck(Db::query('SHOW TABLES')===$beforeTables&&Db::query('SHOW COLUMNS FROM lt_hsx_lead_merchant')===$beforeFields,'no application table or field added');
    echo "PASS cooperation storefront: {$checks} checks; real framework storage references/config with isolated local schema, no uploads or external requests\n";
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL cooperation storefront: '.$error->getMessage()."\n".$error->getFile().':'.$error->getLine()."\n");}
finally{if($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema);}
exit($failed?1:0);
