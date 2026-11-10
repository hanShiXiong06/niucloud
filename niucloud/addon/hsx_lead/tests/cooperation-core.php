<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $f) if (function_exists($f)) exit("Disable HTTP functions before testing\n");
if (filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
require __DIR__.'/membership-fixtures.php';
require_once dirname(__DIR__,3).'/vendor/autoload.php';
use addon\hsx_lead\app\service\{Context,ConnectionService};
use addon\hsx_lead\app\service\cooperation\{ConfigService,Rules,Store,PartnerService,CooperationService,ReviewService,MembershipCatalogService};
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\provider\{ProviderRegistry,TransportContract};
use addon\hsx_lead\app\support\Cipher;
use core\exception\CommonException;
use think\facade\{Db,Cache};

$checks=0;$pdo=null;$schema='';$failed=false;
function coopCheck(bool $ok,string $message):void {if (!$ok) throw new RuntimeException('Assertion failed: '.$message);$GLOBALS['checks']++;}
function coopDeny(callable $action,string $message):void {try {$action();} catch(CommonException $error) {coopCheck(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
try {
    coopCheck(Rules::cents('50.01')===5001&&Rules::amount(5001)==='50.01','integer minor units');
    foreach(['-1','1.001','1e2','NaN',1.2] as $bad) coopDeny(fn()=>Rules::cents($bad),'invalid amount rejected');
    $cfg=ConfigService::defaults();$cfg['fee_confirmed']=true;
    coopCheck(Rules::split(5000,$cfg)===['amount'=>'50.00','fee_amount'=>'9.90','net_amount'=>'40.10'],'minimum platform fee split');
    coopCheck(Rules::split(20000,$cfg)['fee_amount']==='20.00','percentage fee split');
    coopDeny(fn()=>Rules::split(4999,$cfg),'minimum commission enforced');
    coopCheck(date('Y-m-d',Rules::addMonths(strtotime('2026-01-31 10:20:30'),1))==='2026-02-28','month-end entitlement clamp');
    coopCheck(date('H:i:s',Rules::addMonths(strtotime('2026-01-31 10:20:30'),1))==='10:20:30','entitlement preserves time');

    [$app,$pdo,$schema,$site]=leadBoot();
    leadMembershipSeed($site);
    Db::name('member_level')->where('site_id',$site)->where('level_id',704)->update(['level_benefits'=>leadMembershipDefinition(704,4,10,false,['all_models'=>true])['level_benefits']]);
    $official=file_get_contents(dirname(__DIR__,3).'/app/install/source/database.sql');
    if (!preg_match('/CREATE TABLE\s+`sys_attachment`\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;/si',$official,$ddl)) throw new RuntimeException('Missing official attachment DDL');
    $pdo->exec(str_replace('`sys_attachment`','`lt_sys_attachment`',$ddl[0]));
    $ctx=new Context($site,1);$admin=new CooperationService($ctx);$partners=new PartnerService($ctx);$configService=new ConfigService($ctx);
    $cfg=$configService->get();$cfg['enabled']=true;$cfg['publishing_required']=false;$cfg['fee_confirmed']=true;$configService->save($cfg);
    coopCheck($configService->get()['enabled'],'site settings persisted');
    $bad=leadMembershipDefinition(701,1,1);$bad['growth']='abc';coopDeny(fn()=>MembershipCatalogService::normalize($bad),'malformed framework growth does not coerce to zero');
    coopDeny(fn()=>MembershipCatalogService::normalize(leadMembershipDefinition(701,1,1,false,['account_limit'=>'1.5'])),'fractional account allowance rejected');
    $bad=$cfg;$bad['fee_minimum']='50.00';coopDeny(fn()=>$configService->save($bad),'fee cannot consume entire minimum commission');
    $publisherCtx=new Context($site,0,101);$receiverCtx=new Context($site,0,102);$publisherService=new PartnerService($publisherCtx);$receiverService=new PartnerService($receiverCtx);
    coopCheck($publisherService->bootstrap()['needs_onboarding'],'unbound member sees onboarding');
    $base=['name'=>'Synthetic publisher','contact_name'=>'Fixture contact','mobile'=>'18800000001','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,'address'=>'Synthetic road','provider'=>'wanji'];
    $publisher=$publisherService->saveProfile($base);$receiver=$receiverService->saveProfile(array_replace($base,['name'=>'Synthetic receiver','mobile'=>'18800000002']));
    $pid=(int)$publisher['id'];$rid=(int)$receiver['id'];
    coopCheck((int)Data::row('merchant',$site,$pid)['member_id']===101,'profile identity comes from context');
    coopCheck($receiverService->bootstrap()['profile']['id']===$receiver['id'],'onboarding reuses bound member shop');
    leadSetEntryDefinition($site,704);leadMembershipGrant($site,102,'ML704');
    $p=new CooperationService($publisherCtx);$r=new CooperationService($receiverCtx);$model=$p->catalog(['keyword'=>'iPhone'])[0];
    coopCheck(is_string($model['id'])&&str_starts_with($model['id'],'cat_'),'catalog identifiers are strings');
    $input=['request_id'=>'cooperation-test-0001','customer_name'=>'Synthetic buyer','mobile'=>'13800000000','province_id'=>910000,'city_id'=>910100,'district_id'=>910101,
        'business_type'=>'purchase','model_ids'=>[$model['id']],'attachments'=>[],'remark'=>'Synthetic private note','consent'=>true];
    $lead=$p->create($input);$id=(int)$lead['id'];
    coopCheck($lead['status']==='selecting'&&$lead['can_assign']&&count($lead['models'])===1&&!isset($lead['models'][0]['quantity']),'new lead stores models without quantities');
    coopCheck($p->create($input)['id']===$lead['id']&&Store::query('collaboration',$site)->count()===1,'creation retry is idempotent');
    coopDeny(fn()=>$p->create(array_replace($input,['customer_name'=>'Another buyer'])),'same request cannot overwrite content');
    coopDeny(fn()=>$r->detail($id),'unassigned merchant cannot read lead');
    $stores=$p->stores(['scope'=>'auto'],$id);
    coopCheck(!in_array((int)$publisher['id'],array_map('intval',array_column($stores['data'],'id')),true),'single-account shop is not offered as a self-delivery option');
    $stores['data']=array_values(array_filter($stores['data'],static fn(array $row):bool=>empty($row['is_internal'])));
    coopCheck($stores['scope']==='district'&&(int)$stores['data'][0]['id']===$rid&&$stores['data'][0]['membership_active'],'external local paid capable shop remains recommended');
    coopCheck(!isset($stores['data'][0]['available_balance']),'recommendations do not reveal wallet balances');
    $assigned=$p->assign($id,['merchant_id'=>$rid,'assignment_version'=>0]);
    coopCheck($assigned['status']==='assigned'&&(int)$assigned['assignment_version']===1,'single versioned assignment');
    $pending=$r->detail($id);coopCheck($pending['mobile']===''&&$pending['attachments']===[]&&$pending['remark']===''&&$pending['can_accept'],'pending receiver cannot read private contacts');
    Store::query('collaboration',$site)->where('id',$id)->update(['response_deadline'=>time()-1]);
    $late=$r->detail($id);coopCheck($late['response_overdue']&&$late['needs_attention']&&$late['can_accept']&&$late['status']==='assigned','deadline is an attention signal, never silently releases the selected store');
    coopDeny(fn()=>$r->action($id,['action'=>'accept','assignment_version'=>0]),'stale version cannot accept');
    $accepted=$r->action($id,['action'=>'accept','assignment_version'=>1]);
    coopCheck($accepted['status']==='following'&&$accepted['mobile']==='13800000000'&&$accepted['can_report'],'accept reveals necessary contact');
    Store::query('collaboration',$site)->where('id',$id)->update(['accepted_at'=>time()-25*3600]);$late=$r->detail($id);
    coopCheck($late['contact_overdue']&&$late['status']==='following'&&$late['can_report'],'late followup remains an actionable business record');
    coopDeny(fn()=>$p->action($id,['action'=>'withdraw','assignment_version'=>1]),'accepted assignment cannot be withdrawn');
    $done=$r->action($id,['action'=>'deal','assignment_version'=>1]);coopCheck($done['status']==='unpaid'&&$done['can_pay']&&!$done['can_report'],'deal creates unpaid state, not fake earnings');
    coopCheck((string)Db::name('member')->where('site_id',$site)->where('member_id',101)->value('commission')==='0.00','reporting deal does not credit framework commission');
    coopDeny(fn()=>$r->action($id,['action'=>'no_deal','assignment_version'=>1]),'result cannot be contradicted after reporting');
    coopDeny(fn()=>(new CooperationService(new Context($site+1,0,103)))->detail($id),'cross-tenant cannot read lead');
    coopDeny(fn()=>$receiverCtx->connectionMerchant($pid),'ERP owner guard excludes another merchant');
    coopCheck($receiverCtx->connectionMerchant($rid)['member_id']===102,'ERP owner guard accepts current binding');
    $transport=new class implements TransportContract {
        private int $step=0;
        public function post(string $endpoint,array $headers,array $body):array {
            if (++$this->step===1) return ['code'=>200,'data'=>['access_token'=>'synthetic-owner-token','expires_in'=>7200]];
            return ['code'=>200,'rows'=>[['tenantId'=>742,'shopId'=>6422,'machineId'=>'fixture-owner-device','shopName'=>'Synthetic source shop','productName'=>'iPhone 13','brandName'=>'苹果','memory'=>'128GB','color'=>'白色','fineness'=>'95新','retailAmount'=>'2000.00','imeiNoFirst'=>'SYNTHETICIMEI']], 'pageNum'=>1,'pageSize'=>100,'hasMore'=>false];
        }
    };
    $probe=(new ConnectionService($receiverCtx,new ProviderRegistry($transport)))->probe($rid,['provider'=>'wanji','credentials'=>['username'=>'synthetic-owner','password'=>'synthetic-password'],'scope'=>[]]);
    $proof=Cipher::decrypt(Cache::get('hsx_lead.probe.'.hash('sha256',$probe['probe_token'])),$site,-$rid);
    coopCheck($proof['member_id']===102&&$proof['uid']===0,'ERP probe proof is bound to current member without fake administrator');
    Db::name('member')->insert(['site_id'=>$site,'member_id'=>105,'nickname'=>'Rebound fixture','mobile'=>'19900000105']);Data::query('merchant',$site)->where('id',$rid)->update(['member_id'=>105]);
    coopDeny(fn()=>(new ConnectionService(new Context($site,0,105)))->activate($rid,['probe_token'=>$probe['probe_token'],'confirm_switch'=>true]),'new member cannot consume previous member proof after rebinding');
    Data::query('merchant',$site)->where('id',$rid)->update(['member_id'=>102]);
    coopDeny(fn()=>Store::attachments(['https://invalid.example/private.png'],$publisherCtx),'arbitrary URL attachment denied');
    $attachment=(int)Db::name('sys_attachment')->insertGetId(['site_id'=>$site,'path'=>'upload/hsx_lead/fixture/image.png','url'=>'https://cdn.invalid/fixture/image.png','att_type'=>'image']);
    coopDeny(fn()=>Store::attachments(['upload/hsx_lead/fixture/image.png'],$publisherCtx),'unowned site attachment denied');
    $publisherCtx->audit('cooperation_attachment',$attachment,'uploaded',[]);
    coopCheck(Store::attachments(['https://cdn.invalid/fixture/image.png'],$publisherCtx)===['upload/hsx_lead/fixture/image.png'],'cloud URL resolves to owned material path without fetching');
    coopDeny(fn()=>Store::attachments(['upload/hsx_lead/fixture/image.png'],$receiverCtx),'another member cannot reuse attachment');

    $second=$p->create(array_replace($input,['request_id'=>'cooperation-test-0002']));$sid=(int)$second['id'];$p->assign($sid,['merchant_id'=>$rid,'assignment_version'=>0]);
    $r->action($sid,['action'=>'reject','assignment_version'=>1]);coopCheck($p->detail($sid)['status']==='selecting','rejection returns to publisher selection');
    coopDeny(fn()=>$r->detail($sid),'former declined receiver loses current lead access');
    $p->assign($sid,['merchant_id'=>$rid,'assignment_version'=>2]);$r->action($sid,['action'=>'accept','assignment_version'=>3]);$r->action($sid,['action'=>'no_deal','assignment_version'=>3]);
    coopCheck($p->detail($sid)['status']==='no_deal','no-deal ends without commission');
    $reviews=new ReviewService($ctx);$review=$reviews->create(['lead_id'=>$sid,'customer_outcome'=>'deal','note'=>'Synthetic customer confirms a transaction','evidence'=>[]]);
    coopCheck($reviews->lists(['keyword'=>'Synthetic buyer'])['total']===1&&$reviews->lists(['keyword'=>'no-such-customer'])['total']===0,'review keyword filters before pagination');
    coopCheck($reviews->lists(['start_time'=>time()+100])['total']===0,'review date range filters server-side');
    coopCheck(!isset($partners->lists()['data'][0]['available_balance']),'shared merchant list does not expose financial balances');
    coopDeny(fn()=>$reviews->resolve((int)$review['id'],['decision'=>'confirmed_violation','reason'=>'Synthetic evidence']),'violation needs explicit second confirmation');
    $resolved=$reviews->resolve((int)$review['id'],['decision'=>'confirmed_violation','reason'=>'Synthetic evidence','confirm'=>true]);
    coopCheck($resolved['status']==='resolved'&&Store::partner($site,$rid)['blocked_at']>0,'human verified violation stops cooperation');
    coopCheck($receiverService->bootstrap()['profile']['blocked']&&$r->detail($id)['status']==='unpaid','blocked merchant retains historical view');
    coopDeny(fn()=>$receiverService->saveProfile(array_replace($base,['name'=>'New shop'])),'blocked merchant cannot re-onboard');
    coopDeny(fn()=>$receiverCtx->connectionMerchant($rid),'blocked merchant cannot re-authorize ERP');
    $dashboard=$admin->dashboard();coopCheck($dashboard['leads_total']===2&&$dashboard['unpaid']===1&&$dashboard['no_deal']===1,'dashboard reflects persisted states');
    echo "PASS cooperation core: $checks checks; isolated local schema; no network or real payment\n";
} catch(Throwable $error) {$failed=true;fwrite(STDERR,'FAIL cooperation core: '.$error->getMessage()."\n".$error->getTraceAsString()."\n");}
finally {if($pdo instanceof PDO&&$schema!=='') leadCleanup($pdo,$schema);}
exit($failed?1:0);
