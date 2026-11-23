<?php
declare(strict_types=1);
/** Unit contract: no framework boot, credentials, database or HTTP client. */
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\support\{Contract,Problem,ProductionPurchasePilot as Pilot,ProductionVoucher};
foreach(['support/Contract','support/Problem','support/ProductionVoucher','support/ProductionPurchasePilot','gateway/Transport','gateway/OpenApi'] as $f)
    require __DIR__.'/../app/'.$f.'.php';
$n=0; $check=static function(bool $ok)use(&$n){ if(!$ok) throw new \RuntimeException('pilot check failed #'.($n+1)); ++$n; };
$c=['mode'=>'production','purchase_mode'=>'manual','enabled'=>false,'purchase_enabled'=>false,'finance_enabled'=>false,'paused'=>true];
$p=['protocol'=>'openapi_v153','app_id'=>'FIXTURE','app_secret_cipher'=>'FIXTURE','connection_key'=>'fixture','endpoint'=>ProductionVoucher::ENDPOINT,
    'gateway_path'=>'/gateway.do','account_id'=>Pilot::ACCOUNT,'probe_at'=>1,'contract_confirmed'=>true];
$check(Pilot::profile(Pilot::SITE,$c,$p));
$check(!ProductionVoucher::ready($c,$p) && !ProductionVoucher::released($p));
foreach([['mode','test'],['purchase_mode','automatic'],['finance_enabled',true],['uat_payment_enabled',true],['purchase_enabled',true],['enabled',true]] as [$k,$v])
    $check(!Pilot::profile(Pilot::SITE,array_replace($c,[$k=>$v]),$p));
foreach([['account_id','80004'],['gateway_path','/other'],['endpoint','https://open-api-uat.139erp.com/api'],['probe_at',0],['contract_confirmed',false],['app_secret_cipher',''],['voucher_release',['at'=>1]]] as [$k,$v])
    $check(!Pilot::profile(Pilot::SITE,$c,array_replace($p,[$k=>$v])));
$check(!Pilot::profile(100003,$c,$p));
$e=['site_id'=>Pilot::SITE,'aggregate_type'=>'purchase','aggregate_id'=>1,'event_version'=>1,'event_name'=>'erp.integration.document.confirmed.v1',
    'payload'=>['kind'=>'purchase','purchase_source'=>'ordinary_customer','source_document_id'=>'purchase:1','lines'=>[['imei'=>Pilot::IMEI,'quantity'=>'1','amount'=>'1.00']]]];
$check(Pilot::event(Pilot::SITE,$e));
foreach([['imei','990000261121902'],['quantity',2],['amount','0.99']] as [$k,$v]) { $bad=$e; $bad['payload']['lines'][0][$k]=$v; $check(!Pilot::event(Pilot::SITE,$bad)); }
$bad=$e; $bad['payload']['lines'][]=$e['payload']['lines'][0]; $check(!Pilot::event(Pilot::SITE,$bad));
$bad=$e; $bad['payload']['purchase_source']='wholesale_supplier'; $check(!Pilot::event(Pilot::SITE,$bad));
$calls=[];$fake=static function($path,$form)use(&$calls){
    $calls[]=$form['service']; return ['return_code'=>'SUCCESS','api_response'=>['result'=>['ok'=>true]]];
};
$api=new OpenApi($p,'ISOLATED',$fake,'production');
$r=['pilot_bridge_id'=>1,'pilot_authorized_uid'=>1,'original_event_id'=>'fixture','stage'=>'frozen',
    'biz'=>['head'=>['cash_term'=>0,'is_auto_generate_supplier'=>0,'remark'=>Pilot::REMARK],'product_details'=>['add_details'=>[['imei'=>Pilot::IMEI,'qty'=>1,'unit_price'=>'1.00','amounts'=>'1.00']]]]];
$denied=static function(callable $fn)use($check,&$calls){ $before=$calls; try { $fn(); } catch(Problem $x){ $check($calls===$before); return; } throw new \RuntimeException('invalid pilot accepted'); };
foreach([['pilot_bridge_id',0],['pilot_authorized_uid','1'],['stage','draft'],['original_event_id','']] as [$k,$v]) $denied(fn()=>$api->purchasePilotCreate(array_replace($r,[$k=>$v])));
foreach([['qty',2],['amounts','2.00'],['unit_price','2.00'],['imei','x']] as [$k,$v]){$bad=$r;$bad['biz']['product_details']['add_details'][0][$k]=$v;$denied(fn()=>$api->purchasePilotCreate($bad));}
$bad=$r;$bad['biz']['cash_details']=[];$denied(fn()=>$api->purchasePilotCreate($bad));
$bad=$r;$bad['biz']['head']['cash_term']=1;$denied(fn()=>$api->purchasePilotCreate($bad));
$bad=$r;unset($bad['biz']['head']['remark']);$denied(fn()=>$api->purchasePilotCreate($bad));
$denied(fn()=>(new OpenApi($p,'ISOLATED',$fake,'test'))->purchasePilotCreate($r));
$denied(fn()=>(new OpenApi(array_replace($p,['account_id'=>'OTHER']),'ISOLATED',$fake,'production'))->purchasePilotCreate($r));
$api->purchasePilotCreate($r); $check($calls===['boyuan.biz.bill.purchase.generate']);
echo "$n pilot assertions passed; fake transport only; no finance calls.\n";
