<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $f)if(function_exists($f))exit("Disable external HTTP/network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
require __DIR__.'/membership-fixtures.php';
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{PartnerService,StorefrontService,Store,UploadService,ConfigService};
use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

$checks=0;$pdo=null;$schema='';$failed=false;
function storefrontCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function storefrontReject(callable $call,string $label):void{try{$call();}catch(CommonException $e){storefrontCheck(true,$label);return;}throw new RuntimeException('Not rejected: '.$label);}
try{
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);
    leadMembershipSeed($site);
    $settings=new ConfigService(new Context($site,1));$config=$settings->get();$config['enabled']=true;$settings->save($config);
    $official=file_get_contents(dirname(__DIR__,3).'/app/install/source/database.sql');
    if(!preg_match('/CREATE TABLE\s+`sys_attachment`\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/si',$official,$matches))throw new RuntimeException('Missing official attachment fixture');
    $pdo->exec(str_replace('CREATE TABLE `sys_attachment`','CREATE TABLE `lt_sys_attachment`',$matches[0]));
    foreach(['pay','pay_refund'] as $table){preg_match('/CREATE TABLE\s+`'.$table.'`\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/si',$official,$match);$pdo->exec(str_replace('CREATE TABLE `'.$table.'`','CREATE TABLE `lt_'.$table.'`',$match[0]));}
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
        $id=(int)Db::name('sys_attachment')->insertGetId(['site_id'=>$owner->site,'path'=>$path,'url'=>$path,'att_type'=>'image','storage_type'=>'local']);
        $owner->audit($kind,$id,'uploaded',[]);return $path;
    };
    $own=$fixture('own',$ctx);$otherImage=$fixture('other',$other);$crossImage=$fixture('cross',$cross);$chat=$fixture('private-chat',$ctx,'cooperation_attachment');
    $input=['name'=>'Storefront fixture','contact_name'=>'Fixture contact','mobile'=>'13900000101','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'address'=>'Fixture address','provider'=>'wanji','storefront_image'=>$own,'is_reliable'=>true,'certification_status'=>'active'];
    $profile=$profiles->saveProfile($input);$id=(int)$profile['id'];
    storefrontCheck($profile['storefront_image']===$own,'new onboarding persists and returns the uploaded path');
    storefrontCheck(!$profile['membership_active']&&$profile['membership']['level_code']==='','member cannot acquire paid rights through profile payload');
    storefrontCheck(Db::name('sys_config')->where('site_id',$site)->where('config_key','HSX_LEAD_STOREFRONT_'.$id)->count()===1,'media uses existing per-site config row');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['storefront_image']===$own,'public store cards receive the same storefront');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),true,[])['storefront_image']===$own,'private profile receives storefront');
    storefrontCheck((new StorefrontService($cross))->image($id)==='','same numeric merchant id does not expose another site photo');
    // COS objects live behind the storage driver's domain, not this app's
    // /upload route. Preserve canonical references but project the registered
    // public URL; never create an artificial site-domain URL from an object key.
    $cloudPath='upload/hsx_lead/'.$site.'/m101/202611/10/photo_tencent.jpg';
    $cloudUrl='https://merchant-images.example.invalid/'.$cloudPath;
    $cloudId=(int)Db::name('sys_attachment')->insertGetId(['site_id'=>$site,'path'=>$cloudPath,'url'=>$cloudUrl,'att_type'=>'image','storage_type'=>'tencent']);
    $ctx->audit(StorefrontService::UPLOAD_ENTITY,$cloudId,'uploaded',[]);
    $savedConfig=static fn():array=>Db::name('sys_config')->where('site_id',$site)->where('config_key','HSX_LEAD_STOREFRONT_'.$id)->find();
    $storedImage=static function()use($savedConfig):string{$row=$savedConfig();$value=Data::decode($row['value']);return(string)($value['image']??'');};
    $cloudInput=array_replace($input,['storefront_image'=>$cloudPath]);
    storefrontCheck($profiles->saveProfile($cloudInput)['storefront_image']===$cloudUrl,'saving a cloud object key projects the actual registered COS URL');
    storefrontCheck($storedImage()===$cloudPath,'cloud persistence retains its canonical storage path');
    $beforeRead=$savedConfig();
    storefrontCheck($photos->image($id)===$cloudUrl,'saved legacy cloud path resolves to registered public URL on read');
    storefrontCheck($savedConfig()===$beforeRead,'reading legacy cloud path does not rewrite its configuration or timestamps');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['storefront_image']===$cloudUrl,'public storefront card uses COS domain, not the SaaS host');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),true,[])['storefront_image']===$cloudUrl,'private form receives the same resolved COS image');
    storefrontCheck($profiles->saveProfile(array_replace($input,['storefront_image'=>$cloudUrl]))['storefront_image']===$cloudUrl,'already-released client may return the exact registered cloud URL on save');
    storefrontCheck($storedImage()===$cloudPath,'saving projected cloud URL still writes canonical path, not presentation URL');
    $cloudWithout=$cloudInput;unset($cloudWithout['storefront_image']);
    storefrontCheck($profiles->saveProfile($cloudWithout)['storefront_image']===$cloudUrl&&$storedImage()===$cloudPath,'omitting cloud field preserves canonical reference and correct display');
    $configs=new CoreConfigService();
    $configs->setConfig($site,'HSX_LEAD_STOREFRONT_'.$id,['image'=>$cloudUrl]);$beforeRead=$savedConfig();
    storefrontCheck($photos->image($id)===$cloudUrl&&$savedConfig()===$beforeRead,'registered legacy URL can display without a history migration');
    storefrontCheck($profiles->saveProfile(array_replace($input,['storefront_image'=>$cloudUrl]))['storefront_image']===$cloudUrl&&$storedImage()===$cloudPath,'normal save canonicalizes a registered legacy URL');
    foreach(['',$cloudPath]as$unresolvedCloudUrl){
        Db::name('sys_attachment')->where('site_id',$site)->where('att_id',$cloudId)->update(['url'=>$unresolvedCloudUrl]);$beforeRead=$savedConfig();
        storefrontCheck($photos->image($id)===''&&$savedConfig()===$beforeRead,'cloud attachment without an absolute URL never falls back to the SaaS domain');
        storefrontReject(fn()=>$profiles->saveProfile($cloudInput),'missing cloud domain cannot be confirmed as a valid storefront photo');
    }
    Db::name('sys_attachment')->where('site_id',$site)->where('att_id',$cloudId)->update(['url'=>$cloudUrl]);
    foreach([[$otherImage,$site],[$crossImage,$site+1],[$chat,$site]]as[$badPath,$badSite]){
        $badUrl='https://unshared.example.invalid/'.basename($badPath);
        Db::name('sys_attachment')->where('site_id',$badSite)->where('path',$badPath)->update(['url'=>$badUrl]);
        storefrontReject(fn()=>$profiles->saveProfile(array_replace($input,['storefront_image'=>$badUrl])),'registered URL does not bypass uploader, site or public-storefront audit scope');
        storefrontCheck($storedImage()===$cloudPath&&$photos->image($id)===$cloudUrl,'forbidden URL save leaves the existing cloud image intact');
    }
    foreach(['https://merchant-images.example.invalid/unregistered.jpg','https://merchant-images.example.invalid/'.$cloudPath.'?forged=1']as$badUrl){
        storefrontReject(fn()=>$profiles->saveProfile(array_replace($input,['storefront_image'=>$badUrl])),'same CDN host or similar URL is not an exact registered image');
    }
    // Local storage can return a blank public URL. That case must retain its
    // valid local reference instead of losing the photo or adding a wrong host.
    Db::name('sys_attachment')->where('site_id',$site)->where('path',$own)->update(['url'=>'']);
    storefrontCheck($profiles->saveProfile($input)['storefront_image']===$own,'blank local attachment URL falls back to its canonical local path');
    $configs->setConfig($site,'HSX_LEAD_STOREFRONT_'.$id,['image'=>'upload/hsx_lead/missing_tencent.jpg']);$beforeRead=$savedConfig();
    storefrontCheck($photos->image($id)===''&&$savedConfig()===$beforeRead,'missing attachment is hidden, not guessed as a SaaS-host URL or silently rewritten');
    foreach(['javascript:alert(1)','data:image/png;base64,AAAA','//untrusted.example.invalid/photo.jpg','https://user:password@example.invalid/photo.jpg']as$index=>$unsafeUrl){
        $unsafePath='upload/hsx_lead/storefront-fixture/unsafe-'.$index.'_tencent.jpg';
        $unsafeId=(int)Db::name('sys_attachment')->insertGetId(['site_id'=>$site,'path'=>$unsafePath,'url'=>$unsafeUrl,'att_type'=>'image','storage_type'=>'tencent']);
        $ctx->audit(StorefrontService::UPLOAD_ENTITY,$unsafeId,'uploaded',[]);
        $configs->setConfig($site,'HSX_LEAD_STOREFRONT_'.$id,['image'=>$unsafePath]);$beforeRead=$savedConfig();
        storefrontCheck($photos->image($id)===''&&$savedConfig()===$beforeRead,'unsafe registered display URL never reaches image markup or rewrites history');
        storefrontReject(fn()=>$profiles->saveProfile(array_replace($input,['storefront_image'=>$unsafePath])),'unsafe attachment URL is rejected even when its path and uploader are real');
        storefrontReject(fn()=>$profiles->saveProfile(array_replace($input,['storefront_image'=>$unsafeUrl])),'unsafe URL cannot be used as the client image reference');
    }
    $configs->setConfig($site,'HSX_LEAD_STOREFRONT_'.$id,['image'=>$cloudPath]);
    $duplicate=(int)Db::name('sys_attachment')->insertGetId(['site_id'=>$site,'path'=>'upload/hsx_lead/duplicate_tencent.jpg','url'=>$cloudUrl,'att_type'=>'image','storage_type'=>'tencent']);
    $ctx->audit(StorefrontService::UPLOAD_ENTITY,$duplicate,'uploaded',[]);
    storefrontReject(fn()=>$profiles->saveProfile(array_replace($input,['storefront_image'=>$cloudUrl])),'ambiguous registered URL does not arbitrarily select one attachment');
    Db::name('sys_attachment')->where('site_id',$site)->where('att_id',$duplicate)->delete();
    storefrontCheck($profiles->saveProfile($input)['storefront_image']===$own,'restore local profile without converting or losing stored media');
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
    $paid=leadMembershipGrant($site,101,'ML701');
    storefrontCheck($profiles->present(Data::row('merchant',$site,$id),false,[])['membership_active'],'paid rights come from verified subject ledger');
    Store::query('coop_order',$site)->where('id',$paid['id'])->update(['status'=>'closed']);
    storefrontCheck(!$profiles->present(Data::row('merchant',$site,$id),false,[])['membership_active'],'cached partner code alone never grants paid effects');
    Store::query('partner',$site)->where('merchant_id',$id)->update(['blocked_at'=>time()]);
    storefrontReject(fn()=>$photos->assertUploadAllowed(),'blocked merchant cannot upload');
    storefrontReject(fn()=>$profiles->saveProfile($input),'blocked merchant cannot save storefront or profile');
    storefrontCheck(!$profiles->present(Data::row('merchant',$site,$id),false,[])['can_collaborate'],'blocked merchant never yields an actionable collaboration card');
    storefrontCheck(Db::query('SHOW TABLES')===$beforeTables&&Db::query('SHOW COLUMNS FROM lt_hsx_lead_merchant')===$beforeFields,'no application table or field added');
    echo "PASS cooperation storefront: {$checks} checks; real framework storage references/config with isolated local schema, no uploads or external requests\n";
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL cooperation storefront: '.$error->getMessage()."\n".$error->getFile().':'.$error->getLine()."\n");}
finally{if($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema);}
exit($failed?1:0);
