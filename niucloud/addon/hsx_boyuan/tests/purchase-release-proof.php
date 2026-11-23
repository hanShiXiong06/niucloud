<?php
declare(strict_types=1);
/** Fixtures only. No credential, external transport, purchase creation or payment registration. */
require __DIR__.'/bootstrap.php';
use addon\hsx_boyuan\app\service\{ConfigService,PurchaseReleaseReadiness,PurchaseSyncService};
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

if(!in_array('--isolated-local-mysql',$argv,true)) exit(2);
[$app,$pdo,$schema,$site]=boyuanBoot();
function checkRelease(bool $ok,string $msg):void { if(!$ok) throw new RuntimeException($msg); }
function rejectsRelease(string $code,callable $fn):void {
    try { $fn(); } catch(Problem $e) { checkRelease($e->reason===$code,$e->reason); return; } throw new RuntimeException('Expected '.$code);
}
try {
    $cfg=new ConfigService(); $svc=new PurchaseReleaseReadiness();
    $c=$cfg->get($site,false); $c['mode']='production';
    foreach(['test'=>'https://open-api-uat.139erp.com/api','production'=>'https://open-api.139erp.com/api'] as $mode=>$endpoint){
        $p=&$c['profiles'][$mode]; $p['account_id']=$mode==='test'?'T1':'P1'; $p['endpoint']=$endpoint; $p['app_id']='1';
        $p['connection_key']=bin2hex(random_bytes(16)); $p['app_secret_cipher']='fixture-not-a-credential'; $p['contract_confirmed']=true; $p['probe_at']=time(); unset($p);
    }
    $cfg->persist($site,$c); $test=$c['profiles']['test']['connection_key']; $prod=$c['profiles']['production']['connection_key'];
    $row=function(int $tenant,string $key,int $i) {
        $fact="purchase:$i:batch:1"; $no="Q$i"; $third='BP'.substr(hash('sha256',$tenant.':'.$key.':'.$fact),0,30);
        $line=['source_line_id'=>'item:2','device_ref'=>'asset:3','imei'=>'000000000000001'];
        $snap=['kind'=>'purchase','source_document_id'=>'purchase:'.$i,'source_document_no'=>'LOC'.$i,'batch_ref'=>'batch:1','lines'=>[$line]];
        $request=['stage'=>'frozen','schema'=>PurchaseSyncService::SCHEMA,'third_no'=>$third,'biz'=>['head'=>['third_no'=>$third]],
            'selection'=>['company_id'=>'company-1'],'lines'=>[$line]];
        $result=['verified'=>true,'external_no'=>$no,'external_id'=>'head-1','verification_level'=>'purchase_imei_records_matched',
            'lines'=>[$line+['external_line_id'=>'line-1','external_document_id'=>'head-1']],
            'evidence'=>['bill_type'=>'PUR','response_contract'=>OpenApi::PURCHASE_DIALECT_UAT,'record_is_effective'=>true,'request_third_no'=>$third,
                'company_id'=>'company-1','purchase_detail_count'=>1,'imei_detail_count'=>1]];
        return ['site_id'=>$tenant,'kind'=>'purchase','mode'=>'test','connection_key'=>$key,'query_only'=>1,'side_effect'=>'applied','status'=>'succeeded',
            'external_no'=>$no,'event_id'=>'evt'.$i,'fact_key'=>$fact,'operation_id'=>'op'.$i,'business_no'=>'LOC'.$i,
            'snapshot_json'=>Contract::json($snap),'request_json'=>Contract::json($request),'result_json'=>Contract::json($result),'create_at'=>time(),'update_at'=>time()];
    };
    $jobs=fn()=>Db::name('hsx_boyuan_task'); $in=['mode'=>'production','revision'=>0,'connection_key'=>$prod,'confirm_account'=>'P1','candidate_task_id'=>0];
    $jobs()->insert($row($site+1,$test,1)); $jobs()->insert($row($site,$prod,2));
    $jobs()->insert(array_replace($row($site,$test,3),['mode'=>'production'])); $jobs()->insert(array_replace($row($site,$test,4),['status'=>'created_review']));
    $bad=$row($site,$test,5); $r=Contract::decode($bad['result_json']); $r['lines'][0]['imei']='a-different-serial'; $bad['result_json']=Contract::json($r); $jobs()->insert($bad);
    $jobs()->insert(array_replace($row($site,$test,6),['result_json'=>'{']));
    checkRelease(!$svc->inspect($site,$c)['eligible'],'Foreign, invalid, wrong environment or created-only fixtures cannot release');
    rejectsRelease('PURCHASE_RELEASE_UNAVAILABLE',fn()=>$svc->enable($site,1,$in+['acceptance_ref'=>'invented']));
    rejectsRelease('ACCEPTANCE_REQUIRED',fn()=>$cfg->save($site,1,['mode'=>'production','revision'=>$c['revision'],'profile'=>['acceptance_ref'=>'invented']]));
    $good=$jobs()->insertGetId($row($site,$test,8)); $in['candidate_task_id']=(int)$good;
    $view=$svc->inspect($site,$c); checkRelease($view['eligible'] && $view['candidate']===['task_id'=>(int)$good,'test_account'=>'T1','test_bill'=>'Q8'],'Exact same-tenant verified UAT receipt');
    checkRelease(!$cfg->get($site,false)['enabled'],'Read never enables dispatch');
    rejectsRelease('ACCOUNT_CONFIRM_REQUIRED',fn()=>$svc->enable($site,1,array_replace($in,['confirm_account'=>'T1'])));
    rejectsRelease('CONFIG_STALE',fn()=>$svc->enable($site,1,array_replace($in,['candidate_task_id'=>(int)$good+1])));
    rejectsRelease('CONFIG_STALE',fn()=>$svc->enable($site,1,array_replace($in,['connection_key'=>$test])));
    $party=Db::name('erp_party')->insertGetId(['site_id'=>$site,'party_no'=>'TEST-S','party_name'=>'隔离供应商','party_type'=>'supplier','status'=>1]);
    $wh=Db::name('erp_warehouse')->insertGetId(['site_id'=>$site,'warehouse_name'=>'隔离仓','warehouse_type'=>'peer','status'=>1]);
    $bin=Db::name('erp_warehouse_location')->insertGetId(['site_id'=>$site,'warehouse_id'=>$wh,'location_name'=>'隔离库位','status'=>1]);
    $erp=\addon\hsx_erp\app\service\admin\ErpPurchaseService::forSite($site,1,'隔离经办人');
    $po=(int)$erp->create(['request_id'=>'RELEASE_ISOLATED_PREEXISTING','party_id'=>$party,'party_name'=>'隔离供应商','purchaser_uid'=>1,
        'settle_method'=>'挂账','origin_plugin'=>'hsx_erp','origin_type'=>'hsx_erp.manual_purchase','origin_no'=>'ISOLATED-1','items'=>[
        ['imei'=>'000000000000049','model'=>'隔离测试手机','spec'=>'256GB','color'=>'黑色','purchase_cost'=>1,'retail_price'=>2,
            'warehouse_id'=>$wh,'location_id'=>$bin,'image_urls'=>'["/static/fixture.png"]']]]);
    // All rows and clock values below are in this newly created, random, localhost-only test schema.
    sleep(1);
    $count=$jobs()->count(); $reply=$svc->enable($site,1,$in); $final=$cfg->get($site,false);
    checkRelease($reply['documents_sent']===0 && $final['enabled'] && !$final['paused'] && $final['purchase_enabled'] && $final['purchase_mode']==='manual' &&
        !$final['finance_enabled'] && !$final['uat_payment_enabled'] && $final['profiles']['production']['acceptance_ref']==='Q8' && $jobs()->count()===$count,'Explicit approval enables only future manual purchases; no finance or business writes');
    rejectsRelease('CONFIG_STALE',fn()=>$svc->enable($site,1,$in));
    $purchase=new PurchaseSyncService();
    rejectsRelease('PURCHASE_FACT_MISSING',fn()=>$purchase->capturePurchaseId($site,1,$po));
    $preview=$purchase->original($site,1,$po)['original_review'];
    checkRelease($preview['imei']==='000000000000049' && $preview['amount']==='1.00' && $preview['account_id']==='P1' && $jobs()->count()===$count,'Original preview is read-only');
    rejectsRelease('ORIGINAL_PURCHASE_STALE',fn()=>$purchase->original($site,1,$po,array_replace($preview,['amount'=>'2.00'])));
    $ticket=$purchase->original($site,1,$po,$preview); $again=$purchase->original($site,1,$po,$preview);
    $draft=$jobs()->where('id',$ticket['task_id'])->find(); $req=Contract::decode($draft['request_json']);
    checkRelease($ticket===$again && $jobs()->count()===$count+1 && $req['stage']==='draft' && !$req['auto_verify'] &&
        $req['original_event_id']===$preview['event_id'] && $draft['status']==='awaiting_confirmation' && (int)$draft['attempts']===0 && $draft['external_no']==='','One draft, original immutable event, no automatic send or financial effect');
    checkRelease($jobs()->where('kind','purchase_payment')->count()===0,'Release and preparation never create payment tasks');
    $grant=['mode'=>'production','revision'=>$final['revision'],'connection_key'=>$prod,'confirm_account'=>'P1','enable'=>true];
    rejectsRelease('CONFIG_STALE',fn()=>$svc->vouchers($site,1,array_replace($grant,['confirm_account'=>'T1'])));
    $opened=$svc->vouchers($site,1,$grant);$after=$cfg->get($site,false);
    checkRelease($opened['enabled'] && $opened['documents_sent']===0 && \addon\hsx_boyuan\app\support\ProductionVoucher::released($after['profiles']['production']) &&
        !$after['finance_enabled'] && !$after['uat_payment_enabled'] && $jobs()->where('kind','purchase_payment')->count()===0,'Grant only copies original confirmed vouchers, never queues an existing voucher');
    $cfg->save($site,1,['mode'=>'production','revision'=>$after['revision'],'paused'=>true]);
    checkRelease(!\addon\hsx_boyuan\app\support\ProductionVoucher::released($cfg->get($site,false)['profiles']['production']),'Pause revokes the voucher grant');
    // Same fact, separate UAT proof. No other tenant or formal receipt is reused.
    $u=$cfg->get($site,false);$u['mode']='test';$u['paused']=false;$u['profiles']['test']['app_id']='2';$cfg->persist($site,$u);
    $sandbox=$purchase->original($site,1,$po)['original_review'];
    checkRelease($sandbox['mode']==='test' && $sandbox['imei']===$preview['imei'] && $sandbox['account_id']==='T1','UAT prepares the same original and serial on its own account');
    rejectsRelease('ORIGINAL_PURCHASE_STALE',fn()=>$purchase->original($site,1,$po,$preview));
    $ud=$purchase->original($site,1,$po,$sandbox);checkRelease($ud===$purchase->original($site,1,$po,$sandbox) && $ud['task_id']!==$ticket['task_id'],'Only one UAT draft, separate from formal task');
    echo "PASS: verified UAT source, tenant, immutable fact, original IMEI and receipt match; no invented or cross-site release; stale/no-proof requests refused; manual purchase only. No HTTP.\n";
} finally {
    if (preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');
}
