<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/vendor/autoload.php';
use addon\hsx_erp_integration\app\support\{BridgeFact,OriginalEventIdentity};
$raw=['event_name'=>BridgeFact::PAYMENT,'event_id'=>'old-fixture','payload'=>['amount'=>2.0,'targets'=>[
    ['target_id'=>11,'applied_amount'=>1.0,'target_amount'=>1.0,'settled_amount'=>1.0],
    ['target_id'=>12,'applied_amount'=>1.0,'target_amount'=>2.5,'settled_amount'=>1.0],
]]];
$stored=json_decode(json_encode($raw,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
$stored['_delivery']=['attempts'=>3]; $raw['_delivery']=['attempts'=>1];
function ok($match,string $title):void{if(!$match)throw new RuntimeException($title);}
ok(OriginalEventIdentity::same($raw,$stored),'integral floats match persisted JSON integer only on defined paths');
ok(OriginalEventIdentity::payload('payment',$raw['payload'])===OriginalEventIdentity::payload('payment',$stored['payload']),'identical payload replay');
foreach([
    function(&$e){$e['payload']['amount']='2.00';},function(&$e){$e['payload']['amount']=2.01;},
    function(&$e){$e['payload']['targets'][0]['target_id']=11.0;},function(&$e){$e['event_id']='different';},
    function(&$e){$e['payload']['targets']=array_reverse($e['payload']['targets']);},function(&$e){$e['payload']['targets'][0]['applied_amount']=1.0001;},
    function(&$e){unset($e['payload']['targets'][0]['target_amount']);},
] as $change) {$bad=$stored;$change($bad);ok(!OriginalEventIdentity::same($raw,$bad),'strict mismatch');}
$raw['event_name']=$stored['event_name']=BridgeFact::PURCHASE;ok(!OriginalEventIdentity::same($raw,$stored),'no numeric equivalence for non-settlement events');
echo "PASS 10 exact payment event JSON identity and replay checks; no DB or network.\n";
