<?php
declare(strict_types=1);

// No framework bootstrap, credentials, database, queue or network. Use the actual bridge scope and pilot rules.
namespace addon\hsx_boyuan\app\service {
    final class ConfigService {
        public static array $fixture;
        public function get(int $site,bool $public): array { return self::$fixture; }
    }
}
namespace {
    use addon\hsx_boyuan\app\service\{ConfigService,BridgeOriginalDocumentService};
    use addon\hsx_boyuan\app\support\ProductionPurchasePilot as Pilot;
    require __DIR__.'/../app/support/ProductionVoucher.php';
    require __DIR__.'/../app/support/ProductionPurchasePilot.php';
    require __DIR__.'/../app/support/Problem.php';
    require __DIR__.'/../app/service/PaymentSyncService.php';
    require __DIR__.'/../app/service/BridgeOriginalDocumentService.php';
    $check=static function(bool $condition,string $label):void { if(!$condition) throw new \RuntimeException($label); };
    $p=['account_id'=>Pilot::ACCOUNT,'protocol'=>'openapi_v153','endpoint'=>'https://open-api.139erp.com/api','gateway_path'=>'/gateway.do',
        'probe_at'=>1,'contract_confirmed'=>true,'app_secret_configured'=>true,'app_id'=>'fixtureApp','connection_key'=>'fixtureKey'];
    ConfigService::$fixture=['mode'=>'production','purchase_mode'=>'manual','enabled'=>false,'purchase_enabled'=>false,'paused'=>true,
        'finance_enabled'=>false,'uat_payment_enabled'=>false,'profiles'=>['production'=>$p]];
    $target=['manual_scope'=>'purchase_first_100004_89096','mode'=>'production','account_code'=>$p['account_id'],'api_url'=>$p['endpoint'],
        'app_id'=>$p['app_id'],'gateway_path'=>$p['gateway_path']];
    $line=['source_line_id'=>'purchase_line:9','device_ref'=>'asset:9','imei'=>Pilot::IMEI,'quantity'=>'1.00','amount'=>'1.00'];
    $e=['site_id'=>Pilot::SITE,'event_name'=>'erp.integration.document.confirmed.v1','event_version'=>1,'aggregate_type'=>'purchase','aggregate_id'=>13,
        'payload'=>['kind'=>'purchase','source_document_id'=>'purchase:13','purchase_source'=>'ordinary_customer','lines'=>[$line]]];
    $b=new BridgeOriginalDocumentService();
    $allows=static fn($event,$conn=null,$site=null)=>$b->admitsManualFact($site??Pilot::SITE,$conn??$target,$event);
    $check($allows($e),'only the real target site/account and single matching fact');
    foreach(['amount'=>'1.01','imei'=>'another-device','quantity'=>2] as $key=>$wrong) {
        $changed=$e; $changed['payload']['lines'][0][$key]=$wrong; $check(!$allows($changed),$key.' mismatch');
    }
    $changed=$e; $changed['payload']['lines'][]=$line; $check(!$allows($changed),'no multi-device capture');
    $changed=$e; $changed['event_name']='erp.settlement.completed.v1'; $check(!$allows($changed),'no payment');
    $changed=$e; $changed['payload']['kind']='sale'; $check(!$allows($changed),'no sale');
    $check(!$allows($e,array_replace($target,['account_code'=>'other'])),'other account');
    $check(!$allows($e,array_replace($target,['app_id'=>'changed'])),'changed project');
    $check(!$allows($e,array_replace($target,['mode'=>'test'])),'no UAT');
    $check(!$allows($e,null,Pilot::SITE+1),'no other tenant');
    $check(!$allows($e,array_replace($target,['manual_scope'=>'unrecognized'])),'unknown scope denied');
    $follow=$e; $follow['payload']['lines'][0]['imei']=Pilot::NEXT_IMEI; $next=array_replace($target,['manual_scope'=>Pilot::NEXT_SCOPE]);
    $check(!$allows($follow,$next),'assigned staff or document creator cannot replace actual price author');
    $follow['payload']['lines'][0]['pricing_operator']=['ref'=>'staff:1','name'=>'admin'];
    $check($allows($follow,$next),'explicit separate route for exact follow-up');
    $check(!$allows($follow) && !$allows($follow,array_replace($target,['manual_scope'=>''])) && !$allows($e,$next),'route grants cannot cross or be inferred');
    $wrong=$follow;$wrong['payload']['lines'][0]['pricing_operator']['ref']='staff:2';$check(!$allows($wrong,$next),'wrong original pricer');
    $wrong=$follow;$wrong['payload']['lines'][0]['imei']='990000261121903';$check(!$allows($wrong,$next),'no implicit future serials');
    ConfigService::$fixture['purchase_enabled']=true; ConfigService::$fixture['enabled']=true;
    $check(!$allows($e),'the saved pilot route never expands after normal purchase release');
    $check($allows($e,array_replace($target,['manual_scope'=>''])), 'separate ordinary connection preserves its existing policy');
    echo "PASS: pilot bridge is restricted to one purchase; other routes and payments stay separate.\n";
}
