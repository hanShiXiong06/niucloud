<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $f)if(function_exists($f))exit("Disable external HTTP/network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\{PartnerService,ConfigService,PaymentService,Rules,Store};
use addon\hsx_lead\app\support\Data;
use app\dict\pay\PayDict;
use core\exception\CommonException;
use think\facade\Db;

$checks=0;$pdo=null;$schema='';$failed=false;
function permanentCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function permanentReject(callable $call,string $label):void{try{$call();}catch(CommonException $e){permanentCheck(true,$label);return;}throw new RuntimeException('Not rejected: '.$label);}
try{
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);
    $official=file_get_contents(dirname(__DIR__,3).'/app/install/source/database.sql');
    if(!preg_match('/CREATE TABLE\s+`pay`\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/si',$official,$matches))throw new RuntimeException('Missing official pay fixture');
    $pdo->exec(str_replace('CREATE TABLE `pay`','CREATE TABLE `lt_pay`',$matches[0]));
    $beforeTables=Db::query('SHOW TABLES');$beforeFields=Db::query('SHOW COLUMNS FROM lt_hsx_lead_partner');
    $admin=new Context($site,1);$member=new Context($site,0,101);$other=new Context($site,0,102);
    $configService=new ConfigService($admin);$config=ConfigService::defaults();$config['enabled']=true;$config['certification_price']='10.00';$config['certification_months']=0;
    permanentCheck($configService->save($config)['certification_months']===0,'zero certification months is persisted as permanent');
    foreach([-1,121,'unknown'] as $bad)permanentReject(fn()=>$configService->save(array_replace($config,['certification_months'=>$bad])),'invalid certification months rejected');
    $ids=[];foreach([101,102] as $mid)$ids[$mid]=(int)Data::query('merchant',$site)->insertGetId(['site_id'=>$site,'member_id'=>$mid,'name'=>'Permanent fixture '.$mid,'contact_name'=>'Fixture','mobile'=>'13900000'.$mid,'status'=>1,'province_id'=>910000,'city_id'=>910100,'district_id'=>910101]);
    $partners=new PartnerService($admin);$rights=['grade_mode'=>'auto','grade_id'=>'','certification_status'=>'active','cert_expires_at'=>0,'publisher_until'=>0];
    $id=$ids[101];
    permanentCheck($partners->update($id,$rights)['is_reliable']===true,'active certification with zero expiry is reliable');
    $missing=$rights;unset($missing['cert_expires_at']);
    permanentCheck($partners->update($id,$missing)['cert_expires_at']===0,'omitted certification expiry is permanent');
    permanentReject(fn()=>$partners->update($id,array_replace($rights,['cert_expires_at'=>time()-10])),'active finite certification cannot be saved already expired');
    foreach(['none','pending','revoked'] as $status)permanentCheck($partners->update($id,array_replace($rights,['certification_status'=>$status]))['is_reliable']===false,'zero expiry is not reliable for '.$status);
    $partners->update($id,$rights);
    $read=static fn(int $mid):array=>$partners->present(Data::row('merchant',$site,$mid),false,[]);
    Store::query('partner',$site)->where('merchant_id',$id)->update(['cert_expires_at'=>time()-1]);
    permanentCheck($read($id)['is_reliable']===false,'expired finite grant is not reliable');
    Store::query('partner',$site)->where('merchant_id',$id)->update(['cert_expires_at'=>time()+3600]);
    permanentCheck($read($id)['is_reliable']===true,'future finite grant remains reliable');
    Store::query('partner',$site)->where('merchant_id',$id)->update(['cert_expires_at'=>0]);Data::query('merchant',$site)->where('id',$id)->update(['status'=>0]);
    permanentCheck($read($id)['is_reliable']===false,'paused merchant does not display permanent reliable certification');
    Data::query('merchant',$site)->where('id',$id)->update(['status'=>1]);Store::query('partner',$site)->where('merchant_id',$id)->update(['blocked_at'=>time()]);
    permanentCheck($read($id)['is_reliable']===false,'blocked merchant does not display permanent reliable certification');
    Store::query('partner',$site)->where('merchant_id',$id)->update(['blocked_at'=>0]);
    permanentCheck($read($id)['is_reliable']===true,'unblocked active enabled permanent grant has authoritative reliable state');
    $otherId=$ids[102];$partners->update($otherId,array_replace($rights,['certification_status'=>'none']));$payments=new PaymentService($other);
    $order=$payments->createOrder(['purpose'=>'certification','request_id'=>'permanent-cert-first']);
    permanentCheck(Data::decode(Store::row('coop_order',$site,(int)$order['id'])['rule_json'])['months']===0,'permanent duration is snapshotted on order creation');
    permanentCheck(Store::partner($site,$otherId)['certification_status']==='none','creating permanent paid option does not grant certification');
    $fact=static function(array $o,string $no,int $mid)use($site):array{
        Db::name('pay')->insert(['site_id'=>$site,'trade_type'=>PaymentService::TRADE_TYPE,'trade_id'=>$o['id'],'main_type'=>PayDict::MEMBER,'main_id'=>$mid,'from_main_id'=>$mid,'out_trade_no'=>$no,'money'=>$o['amount'],'status'=>PayDict::STATUS_FINISH]);
        return ['site_id'=>$site,'trade_type'=>PaymentService::TRADE_TYPE,'trade_id'=>$o['id'],'out_trade_no'=>$no];
    };
    $config['certification_months']=12;$configService->save($config);
    $event=$fact($order,'PERMANENT_FIXTURE_PAID',102);PaymentService::confirm($event);
    $grant=Store::partner($site,$otherId);
    permanentCheck((int)$grant['cert_expires_at']===0&&$grant['certification_status']==='pending','confirmed permanent order stays zero even after config changes, awaiting review');
    permanentCheck($read($otherId)['is_reliable']===false,'paid permanent application is not reliable before approval');
    PaymentService::confirm($event);permanentCheck((int)Store::partner($site,$otherId)['cert_expires_at']===0,'duplicate completed payment cannot change permanent grant');
    $partners->update($otherId,$rights);$beforeOrders=Store::query('coop_order',$site)->count();
    permanentReject(fn()=>$payments->createOrder(['purpose'=>'certification','request_id'=>'permanent-do-not-charge-again']),'already permanently active merchant is not charged for another certification');
    permanentCheck(Store::query('coop_order',$site)->count()===$beforeOrders,'unnecessary permanent renewal creates no new payment order');
    // The customer had a finite order already in flight; an administrator grants permanent access before its payment arrives.
    $partners->update($id,array_replace($rights,['cert_expires_at'=>time()+3600]));$memberPayments=new PaymentService($member);
    $finite=$memberPayments->createOrder(['purpose'=>'certification','request_id'=>'finite-before-permanent']);
    $partners->update($id,$rights);PaymentService::confirm($fact($finite,'LATE_FINITE_AFTER_PERMANENT',101));
    permanentCheck((int)Store::partner($site,$id)['cert_expires_at']===0&&$read($id)['is_reliable']===true,'late finite renewal preserves already-active permanent status');
    // A genuinely finite application remains finite and still requires approval.
    $partners->update($otherId,array_replace($rights,['certification_status'=>'revoked','cert_expires_at'=>time()+60]));
    $finiteOther=$payments->createOrder(['purpose'=>'certification','request_id'=>'finite-real-application']);$before=time();
    PaymentService::confirm($fact($finiteOther,'FINITE_APPLICATION_PAID',102));$finiteGrant=Store::partner($site,$otherId);
    permanentCheck((int)$finiteGrant['cert_expires_at']>=Rules::addMonths($before,12)&&$finiteGrant['certification_status']==='pending','finite configuration still grants finite future period pending review');
    permanentCheck(Db::query('SHOW TABLES')===$beforeTables&&Db::query('SHOW COLUMNS FROM lt_hsx_lead_partner')===$beforeFields,'permanent certification needs no application schema changes');
    echo "PASS permanent certification: {$checks} checks; isolated database with synthetic completed payment facts, no real payment or external requests\n";
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL permanent certification: '.$error->getMessage()."\n".$error->getFile().':'.$error->getLine()."\n");}
finally{if($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema);}
exit($failed?1:0);
