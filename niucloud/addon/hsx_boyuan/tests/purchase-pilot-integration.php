<?php
declare(strict_types=1);
/** Random empty localhost schema. Account/serial literals refer ONLY to synthetic fixture rows here. */
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true) || filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','dns_get_record','gethostbyname','gethostbynamel'] as $f) if(function_exists($f)) exit(2);
require __DIR__.'/bootstrap.php';
use think\facade\Db;
use addon\hsx_boyuan\app\service\{ConfigService,PurchaseSyncService};
use addon\hsx_boyuan\app\support\{Contract,CatalogStore,Problem,ProductionPurchasePilot as Pilot,ProductionVoucher};
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use addon\hsx_erp_integration\app\support\{BridgeTarget,BridgeFact};
[$app,$pdo,$schema,$seed]=boyuanBoot();
$site=Pilot::SITE; $n=0; $fail=null; $calls=[];
$third=in_array('--pricer-third',$argv,true); $follow=$third||in_array('--pricer-followup',$argv,true); $inactive=$follow && in_array('--buyer-ineligible',$argv,true);
$incomplete=$follow&&in_array('--dedup-incomplete',$argv,true);$readFail=$follow&&in_array('--dedup-transport',$argv,true);$writeFail=$follow&&in_array('--create-timeout',$argv,true);
$imei=$third?Pilot::THIRD_IMEI:($follow?Pilot::NEXT_IMEI:Pilot::IMEI);
$ids=$follow?['companies'=>Pilot::COMPANY,'parties'=>Pilot::SUPPLIER,'products'=>Pilot::PRODUCT,'warehouses'=>Pilot::WAREHOUSE,'staff'=>Pilot::BUYER]:
    array_combine(['companies','parties','products','warehouses','staff'],['companies','parties','products','warehouses','staff']);
$staffName=$follow?Pilot::BUYER_NAME:'fixturestaff';
$check=static function(bool $yes,string $why)use(&$n){ if(!$yes) throw new RuntimeException($why);++$n; };
$deny=static function(callable $f,string $code)use($check) {try { $f(); } catch(Problem $p) { $check($p->reason===$code,$p->reason.' instead of '.$code);return; } throw new RuntimeException('expected '.$code);};
try {
    $check(Db::query('SELECT DATABASE() AS db')[0]['db']===$schema,'not isolated');
    (new addon\hsx_boyuan\Addon())->install(); (new addon\hsx_erp_integration\Addon())->upgrade();
    Db::name('site')->where('site_id',$seed)->update(['site_id'=>$site,'addons'=>Contract::json(['hsx_erp','hsx_boyuan','hsx_erp_integration'])]);
    Db::name('sys_user_role')->where('site_id',$seed)->update(['site_id'=>$site]); request()->siteId($site);
    $svc=new ConfigService();$c=$svc->get($site,false);$c['mode']='production';$key=str_repeat('c',32);
    $p=array_replace($c['profiles']['production'],['protocol'=>'openapi_v153','app_id'=>'fixture','app_secret_cipher'=>'fake-only','account_id'=>Pilot::ACCOUNT,
        'endpoint'=>ProductionVoucher::ENDPOINT,'gateway_path'=>'/gateway.do','contract_confirmed'=>true,'probe_at'=>time(),'connection_key'=>$key]);
    $c['profiles']['production']=$p;$svc->persist($site,$c);
    foreach(['companies'=>['gsdm'=>'C'],'parties'=>['is_supplier'=>1],'products'=>['imei_count'=>1,'imei_len'=>15],'warehouses'=>['company_id'=>$ids['companies']],'staff'=>['is_buyer'=>1,'company_id'=>$ids['companies'],'gsdm'=>'C']] as $res=>$extra){
        $run=bin2hex(random_bytes(16));Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>$site,'connection_key'=>$key,'mode'=>'production','resource'=>$res,'run_key'=>$run,'status'=>'completed','scope_json'=>'{}','processed'=>1,'pages'=>1,'reported_total'=>1]);
        CatalogStore::query($res)->insert(['site_id'=>$site,'connection_key'=>$key,'external_id'=>$ids[$res],'code'=>$res,'name'=>$res==='staff'?$staffName:'fixture'.$res,'enabled'=>1,'extra_json'=>Contract::json($extra),'seen_run'=>$run]);
    }
    $party=Db::name('erp_party')->insertGetId(['site_id'=>$site,'party_no'=>'TEST','party_name'=>'fixture','party_type'=>'supplier','status'=>1]);
    $w=Db::name('erp_warehouse')->insertGetId(['site_id'=>$site,'warehouse_name'=>'fixture','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
    $l=Db::name('erp_warehouse_location')->insertGetId(['site_id'=>$site,'warehouse_id'=>$w,'location_name'=>'fixture','status'=>1]);
    $po=ErpPurchaseService::forSite($site,1,'fixture')->create(['request_id'=>'PILOT_ISOLATED_0001','party_id'=>$party,'party_name'=>'fixture','purchaser_uid'=>1,'settle_method'=>'挂账',
        'origin_plugin'=>'hsx_recycle','origin_type'=>'hsx_recycle.recycle_purchase','origin_id'=>$third?'34':'1','origin_no'=>$third?Pilot::THIRD_RECYCLE_NO:'ISO_RECYCLE','items'=>[
            ['imei'=>$imei,'model'=>'fixture','spec'=>'256','color'=>'black','purchase_cost'=>1,'retail_price'=>2,'warehouse_id'=>$w,'location_id'=>$l,'image_urls'=>'["/static/fixture.png"]',
                'spec_json'=>['source_device_id'=>$third?45:1,'source_order_no'=>$third?Pilot::THIRD_RECYCLE_NO:'ISO_RECYCLE','pricing_operator'=>['source'=>'recycle_final_price','uid'=>1,'name'=>'fixture','priced_at'=>time()]]]]]);
    $e=Contract::decode(Db::name('erp_outbox_event')->where(['site_id'=>$site,'event_name'=>'erp.integration.document.confirmed.v1'])->value('payload_json'));
    $check(Pilot::event($site,$e),'ERP event must narrow exactly');
    $cfg=['mode'=>'production','api_url'=>$p['endpoint'],'gateway_path'=>$p['gateway_path'],'account_code'=>$p['account_id'],'app_id'=>$p['app_id']];
    if($follow) $cfg['manual_scope']=$third?Pilot::THIRD_SCOPE:Pilot::NEXT_SCOPE;
    $conn=['site_id'=>$site,'adapter_code'=>'boyuan','status'=>'enabled','sync_mode'=>'manual','business_types'=>'["purchase"]','bridge_started_at'=>time()-60,'last_test_result'=>'success','config'=>Contract::json($cfg)];
    $cid=Db::name('erp_integration_connection')->insertGetId($conn);
    if($third) {
        $sibling=$cfg;$sibling['manual_scope']=Pilot::NEXT_SCOPE;
        Db::name('erp_integration_connection')->insert(array_replace($conn,['config'=>Contract::json($sibling)]));
        $check((new addon\hsx_boyuan\app\service\BridgeOriginalDocumentService())->admitsManualFact($site,$cfg,$e) &&
            !(new addon\hsx_boyuan\app\service\BridgeOriginalDocumentService())->admitsManualFact($site,$sibling,$e),'immutable independent routing');
        $h=Db::name('erp_purchase_order')->where(['site_id'=>$site,'id'=>$e['aggregate_id']]);
        $h->update(['origin_no'=>'OTHER']); $check(!Pilot::event($site,$e),'same IMEI on other recycle order blocked'); $h->update(['origin_no'=>Pilot::THIRD_RECYCLE_NO]);
        $h->update(['origin_id'=>'134']); $check(!Pilot::event($site,$e),'other recycle order id blocked'); $h->update(['origin_id'=>'34']);
    }
    $bid=Db::name('erp_integration_sync_task')->insertGetId(BridgeFact::project($e,'purchase')+['site_id'=>$site,'connection_id'=>$cid,'target_key'=>BridgeTarget::key($conn),
        'origin_event_id'=>$e['event_id'],'status'=>'awaiting_confirmation','payload'=>Contract::json($e)]);
    $s=new PurchaseSyncService();
    if($third) {
        $item=Db::name('erp_purchase_item')->where(['site_id'=>$site,'purchase_order_id'=>$e['aggregate_id']]);
        $raw=$item->value('spec_json');$spec=Contract::decode($raw);
        $item->update(['spec_json'=>Contract::json(array_replace($spec,['source_device_id'=>145]))]);
        $deny(fn()=>$s->capturePilot($site,1,$bid,$e),'PILOT_SOURCE_CHANGED');$item->update(['spec_json'=>$raw]);
    }
    $t=$s->capturePilot($site,1,$bid,$e)['task_id'];
    $check($s->capturePilot($site,1,$bid,$e)['task_id']===$t && $s->capture($site,$e)===[],'single draft and no broad listener');
    Db::name('erp_integration_sync_task')->where('id',$bid)->update(['receipt_json'=>Contract::json(['vendor_task_id'=>$t]),'status'=>'linked']);
    $v=$s->view($site,$t);$sel=['company_id'=>$ids['companies'],'supplier_id'=>$ids['parties'],'creator_id'=>$ids['staff'],'buyer_id'=>$ids['staff'],'lines'=>[['source_line_id'=>$e['payload']['lines'][0]['source_line_id'],'product_id'=>$ids['products'],'warehouse_id'=>$ids['warehouses']]]];
    $input=['revision'=>$v['task']['revision'],'mode'=>'production','connection_key'=>$key,'selection'=>$sel,'confirm_pricing_buyer'=>true];
    $deny(fn()=>$s->submit($site,$t,1,$input),'PILOT_CONFIRM_REQUIRED');
    $deny(fn()=>$s->submit($site,$t,2,$input+['confirm_pilot'=>true]),'PILOT_ADMIN_REQUIRED');
    $fake=new OpenApi($p,'SAFEFAKE',static function($path,$params)use(&$calls,$follow,$inactive,$ids,$staffName,$incomplete,$readFail,$writeFail){
        $service=$params['service']; $calls[]=$service;
        if($readFail && $service==='boyuan.biz.purchase.imeidetails.query') return ['return_code'=>'FIXTURE_REJECTED','return_msg'=>'PRIVATE_FIXTURE_CONTACT'];
        if($writeFail && $service==='boyuan.biz.bill.purchase.generate') throw new RuntimeException('timeout');
        if(in_array($service,['boyuan.biz.purchase.imeidetails.query','boyuan.biz.inventory.imeidetails.query'],true)) $ans=['total_results'=>0,'result_list'=>[]];
        elseif($service==='boyuan.biz.purchases.query' && $follow) $ans=['total_results'=>$incomplete?1:0,'result_list'=>[]];
        elseif($service==='boyuan.users.get' && $follow){
            if(($params['id']??'')!==Pilot::BUYER || $params['page_size']!==1) throw new RuntimeException('buyer lookup not single target');
            $ans=['total_results'=>1,'result_list'=>[['id'=>$ids['staff'],'real_name'=>$staffName,'company_id'=>$ids['companies'],'is_effective'=>1,'is_buyer'=>$inactive?0:1]]];
        } elseif($service==='boyuan.biz.bill.purchase.generate'){
            $biz=Contract::decode($params['biz']);
            if(($biz['head']['remark']??'')!==Pilot::REMARK) throw new RuntimeException('missing production pilot remark');
            if(($biz['head']['sales_id']??'')!==$ids['staff'] || ($biz['head']['sales_name']??'')!==$staffName) throw new RuntimeException('missing buyer on pilot purchase');
            $h=$biz['head']+['big_gsdm'=>Pilot::ACCOUNT,'id'=>'HEAD','bill_id'=>'FIXTURE-1','bill_type'=>'PUR'];
            $row=$biz['product_details']['add_details'][0]+['id'=>'LINE','seq_no'=>1];
            $ans=['result'=>['head'=>$h,'product_details'=>['add_details'=>[$row]]]];
        } else throw new RuntimeException('unexpected '.$service);
        return ['return_code'=>'SUCCESS','api_response'=>$ans];
    },'production');
    $v=$s->submit($site,$t,1,$input+['confirm_pilot'=>true],false,$fake);
    $expected=['boyuan.biz.purchase.imeidetails.query'];
    if($follow && !$readFail) $expected[]='boyuan.biz.purchases.query';
    if(!$readFail && !$incomplete) $expected[]='boyuan.biz.inventory.imeidetails.query';
    if($follow && !$readFail && !$incomplete) $expected[]='boyuan.users.get';
    if($inactive || $readFail || $incomplete){
        $stored=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'id'=>$t])->find();
        $issue=Contract::decode($stored['result_json'])['last_issue'];$notice=$issue['notice']??'';
        $check($stored['external_no']==='' && $stored['side_effect']==='none' && $stored['status']==='attention' && $stored['next_at']==0 &&
            !$v['can_verify'] && $issue['stage']==='prepare' && $issue['checkpoint']===($inactive?'buyer_check':'duplicate_check') &&
            $issue['code']===($inactive?'PILOT_BUYER_NOT_ELIGIBLE':($readFail?'PREFLIGHT_REMOTE_UNAVAILABLE':'PURCHASE_PAGE_INCOMPLETE')),'lookup-only receipt must never claim a purchase send');
        $check($v['task']['status']==='blocked' && $v['task']['reason']===$notice && str_starts_with($notice,'未发送采购单：') && !str_contains($notice,'PRIVATE_FIXTURE'),'show only safe prepare reason in vendor task');
        $list=(new addon\hsx_boyuan\app\service\DocumentResultService())->rows($site,'purchase',(int)explode(':',$stored['fact_key'])[1],$stored['business_no'],$svc->get($site,false));
        $check(($list[0]['status_label']??'')==='采购单未发送' && $list[0]['next_step']===$notice,'original ERP document should identify prepare failure');
        $proj=new addon\hsx_boyuan\app\service\BridgeOriginalDocumentService();$method=new ReflectionMethod($proj,'result');$method->setAccessible(true);
        $bridge=$method->invoke($proj,$stored,false);$check($bridge['status']==='blocked' && $bridge['reason']===$notice,'bridge should show same safe reason');
        $verify=['revision'=>$stored['revision'],'mode'=>'production','connection_key'=>$key,'external_no'=>'HAND-TYPED'];
        $deny(fn()=>$s->queueVerify($site,$t,1,$verify),'PILOT_UNSENT');
    } elseif($writeFail) {
        $expected[]='boyuan.biz.bill.purchase.generate';
        $stored=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'id'=>$t])->find();$res=Contract::decode($stored['result_json']);
        $check($stored['status']==='attention' && $stored['side_effect']==='unknown' && $res['last_issue']['stage']==='submit' &&
            Pilot::unsentNotice($stored,$res)==='' && $v['task']['status']!=='blocked','write timeout is not safe to resend');
    } else {
        $expected[]='boyuan.biz.bill.purchase.generate';
        $check($v['task']['external_no']==='FIXTURE-1' && $v['task']['status']==='created_review','receipt persisted: '.($v['task']['reason']??''));
        $check($v['pricing_buyer']['receipt_status']==='matched' && $v['pricing_buyer']['returned_name']===$staffName,'creation echoes verified buyer');
        $deny(fn()=>$s->submit($site,$t,1,$input+['confirm_pilot'=>true],false,$fake),'TASK_STALE');
    }
    $check($calls===$expected,'reads must precede the only possible create');
    $check(!$s->process($site,$t,$fake) && $calls===$expected,'replay blocked');
    $check(Db::name('erp_settlement')->where('site_id',$site)->count()==0 && empty($svc->get($site,false)['purchase_enabled']),'no payment or general release');
} catch(Throwable $x){$fail=$x;} finally {
    if(preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) $pdo->exec('DROP DATABASE `'.$schema.'`');
}
if($fail){fwrite(STDERR,'FAIL '.$fail->getMessage().' at '.basename($fail->getFile()).':'.$fail->getLine()."\n"); exit(1);}
echo "$n isolated lifecycle checks passed, fake wire calls: ".count($calls).", live calls: 0.\n";
