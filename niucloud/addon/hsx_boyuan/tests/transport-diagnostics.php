<?php
declare(strict_types=1);
// Standalone regression: all DNS, cURL and Log calls are in-process stubs. No app bootstrap or database.
namespace think\facade {
    final class Log { public static array $lines=[]; public static bool $broken=false; public static function write($line,$level):void { if(self::$broken) throw new \RuntimeException('unwritable'); self::$lines[]=[$level,$line]; } }
}
namespace addon\hsx_boyuan\app\gateway {
    final class Wire {
        public static string $scenario='ok';
        public static int $calls=0;
        public static array $options=[];
        public static ?string $raw=null;
        public static ?\Closure $during=null;
    }
    function dns_get_record($host,$flags) { return match(Wire::$scenario){'no_dns'=>[],'private'=>[['ip'=>'10.0.0.2']],default=>[['ip'=>'8.8.8.8']]}; }
    function curl_init($url) { return new \stdClass(); }
    function curl_setopt_array($h,$opt) { Wire::$options=$opt; return true; }
    function curl_setopt($h,$key,$v) { return true; }
    function curl_exec($h) {
        Wire::$calls++;
        if(Wire::$during) (Wire::$during)();
        $f=Wire::$options[CURLOPT_WRITEFUNCTION]; $raw=Wire::$raw??match(Wire::$scenario){'over'=>str_repeat('S',2097153),'invalid'=>'private reply text',default=>'{"code":"OK"}'};
        return $f($h,$raw)===strlen($raw) && !in_array(Wire::$scenario,['connect','timeout','tls','recv'],true);
    }
    function curl_errno($h){ return match(Wire::$scenario){'connect'=>7,'timeout'=>28,'tls'=>60,'recv'=>56,'over'=>23,default=>0}; }
    function curl_getinfo($h,$field) {
        return match($field){CURLINFO_CONNECT_TIME=>0.071,CURLINFO_TOTAL_TIME=>0.235,CURLINFO_HTTP_CODE=>match(Wire::$scenario){'redirect'=>302,'429'=>429,'502'=>502,'400'=>400,'connect','timeout','tls','recv'=>0,default=>200},default=>throw new \RuntimeException('Do not read broad cURL info')};
    }
    function curl_close($h) {}
}
namespace {
    use addon\hsx_boyuan\app\gateway\{HttpTransport,Wire};
    use addon\hsx_boyuan\app\support\Problem;
    use think\facade\Log;
    foreach(['support/Contract','support/Problem','support/InterfaceTrace','gateway/Transport','gateway/OpenApi','gateway/HttpTransport'] as $file) require __DIR__.'/../app/'.$file.'.php';
    $checks=0;
    $check=static function(bool $v,string $m) use(&$checks){$checks++;if(!$v)throw new \RuntimeException($m);};
    $http=new HttpTransport(['endpoint'=>'https://private-vendor.example/api','account_id'=>'PRIVATE-ACCOUNT'],'PRIVATE-BEARER');
    $body=['service'=>'boyuan.biz.bill.sale.generate','app_id'=>'PRIVATE-APPID','sign'=>'PRIVATE-SIGN','biz'=>'PRIVATE-SERIAL-CUSTOMER'];
    foreach(['no_dns'=>['dns_unavailable','DNS_UNAVAILABLE'],'private'=>['address_denied','ADDRESS_DENIED'],
        'connect'=>['connect_failed','REMOTE_UNCERTAIN'],'timeout'=>['timeout','REMOTE_UNCERTAIN'],'tls'=>['tls_error','REMOTE_UNCERTAIN'],
        'recv'=>['curl_error','REMOTE_UNCERTAIN'],'over'=>['response_size_limit','REMOTE_UNCERTAIN'],'429'=>['rate_limited','REMOTE_UNCERTAIN'],
        '502'=>['upstream_5xx','REMOTE_UNCERTAIN'],'400'=>['upstream_4xx',null],'redirect'=>['redirect_denied','REDIRECT_DENIED'],
        'invalid'=>['response_invalid','RESPONSE_INVALID'],'ok'=>[null,null]] as $scenario=>[$category,$reason]) {
        Wire::$scenario=(string)$scenario;Log::$lines=[];$before=Wire::$calls;$code=null;
        try {$http->postForm('/private-gateway.do',$body);}catch(Problem $p){$code=$p->reason;}
        $dns=in_array($scenario,['no_dns','private'],true);
        $check($code===$reason,'existing result semantics: '.$scenario);
        $check(Wire::$calls===$before+($dns?0:1),'never retries');
        if($category===null){$check(Log::$lines===[],'no success noise');continue;}
        $check(count(Log::$lines)===1 && Log::$lines[0][0]==='warning','one PHP warning');
        $entry=json_decode(substr(Log::$lines[0][1],strlen('hsx_boyuan transport ')),true,512,JSON_THROW_ON_ERROR);
        $check(array_keys($entry)===['operation','category','curl_errno','http_status','connect_ms','curl_total_ms','wall_ms','response_truncated'],'closed diagnostic fields');
        $check($entry['category']===$category && $entry['operation']==='sale_create' && $entry['response_truncated']===($scenario==='over'),'safe class');
        $check($entry['connect_ms']===($dns?null:71) && $entry['curl_total_ms']===($dns?null:235) && $entry['wall_ms']>=0,'timing units');
        $check(!preg_match('/PRIVATE|private-vendor|private-gateway|private reply|8\.8\.8\.8|10\.0\.0\.2/i',Log::$lines[0][1]),'no sensitive values');
    }
    Wire::$scenario='timeout';Log::$lines=[];
    try {$http->postForm('/private-gateway.do',array_replace($body,['service'=>'boyuan.categorys.get']));throw new \RuntimeException('read unexpectedly succeeded');}
    catch(Problem $e){$check($e->reason==='REFERENCE_UNAVAILABLE' && $e->effect==='none' && str_contains($e->getMessage(),'超时') && !str_contains($e->getMessage(),'建单'),'read timeout is not an uncertain business write');}
    Log::$lines=[];
    try {$http->postForm('/private-gateway.do',array_replace($body,['service'=>'PRIVATE-UNKNOWN-URL']));}catch(Problem $ignored){}
    $check(str_contains(Log::$lines[0][1],'"operation":"openapi_other"') && !str_contains(Log::$lines[0][1],'PRIVATE'),'unknown operation is never echoed');
    $check(Wire::$options[CURLOPT_CONNECTTIMEOUT]===5 && Wire::$options[CURLOPT_TIMEOUT]===20 && Wire::$options[CURLOPT_PROXY]==='' && Wire::$options[CURLOPT_SSL_VERIFYPEER] && Wire::$options[CURLOPT_SSL_VERIFYHOST]===2 && !Wire::$options[CURLOPT_FOLLOWLOCATION],'original security/timeout policy unchanged');
    $window='BW0123456789abcdef'; $clock=static function() use(&$window){return $window;};
    $ctx=['kind'=>'purchase','task_id'=>37,'business_no'=>'CG-fixture-2026'];
    $trace=new \addon\hsx_boyuan\app\support\InterfaceTrace(101,'test',$ctx,$clock);
    $profile=['endpoint'=>'https://example.test/api','app_id'=>'PRIVATE-APPID','account_id'=>'80000','gateway_path'=>'/gateway.do'];
    $signed=new \addon\hsx_boyuan\app\gateway\OpenApi($profile,'PRIVATE-SECRET',null,'test',$trace);
    $client=new HttpTransport($profile,'PRIVATE-BEARER',$trace,'PRIVATE-SECRET');
    $entries=static function():array {
        $rows=[]; foreach(Log::$lines as [$level,$line]) if(str_starts_with($line,'hsx_boyuan trace ')) $rows[]=json_decode(substr($line,17),true,512,JSON_THROW_ON_ERROR);
        return $rows;
    };
    Wire::$scenario='ok';Log::$lines=[];
    $result=['head'=>['bill_id'=>'PUR-055','warehouse_id'=>'W9','help_code'=>'13900009999','appsec'=>'PRIVATE-SECRET'],'product_details'=>['add_details'=>[['imei'=>'990000000012345','qty'=>1]]]];
    Wire::$raw=json_encode(['return_code'=>'SUCCESS','api_response'=>['result'=>$result]]);
    $biz=['head'=>['warehouse_id'=>'W9','cash_term'=>0,'creater_id'=>'C1','remark'=>'PRIVATE-REMARK'],'product_details'=>['add_details'=>[['imei'=>'990000000012345','qty'=>1]]]];
    $before=Wire::$calls; $reply=$signed->purchaseCreate($biz);
    [$sent,$returned]=$entries(); $plain=json_encode(Log::$lines);
    $check(count($entries())===2 && Wire::$calls===$before+1 && $returned['trace_id']==='T101-37' && $sent['window_id']===$window && $sent['call_id']===$returned['call_id'] && $sent['business_no']===$ctx['business_no'],'ERP order and the pair correlate exactly');
    $check($sent['stage']==='request' && $sent['payload']['biz']['head']['warehouse_id']==='W9' && $sent['payload']['biz']['head']['creater_id']==='C1' && $returned['payload']['api_response']['result']['head']['bill_id']==='PUR-055' && $returned['gateway_reply']==='accepted_envelope','signed request and vendor response');
    $check(!preg_match('/PRIVATE|13900009999|990000000012345/',$plain) && $reply['result']['head']['help_code']==='13900009999' && $reply['result']['product_details']['add_details'][0]['imei']==='990000000012345','business data unchanged, redaction only on the log copy');
    Wire::$raw=json_encode(['return_code'=>'DENIED','return_msg'=>'创建人为空 PRIVATE-SECRET']);Log::$lines=[];
    try{$signed->purchaseCreate($biz);throw new \RuntimeException('rejection missing');}catch(Problem $e){$check($e->reason==='OPENAPI_DENIED','original rejection preserved');}
    $check($entries()[1]['gateway_reply']==='reported_error' && $entries()[1]['payload']['return_msg']==='创建人为空' && $entries()[0]['call_id']!==$sent['call_id'],'business denial recorded, new call id');
    Wire::$raw=null;
    foreach(['no_dns'=>['not_sent','none'],'timeout'=>['no_confirmed_response','incomplete'],'invalid'=>['response_received','non_json'],'over'=>['response_received','incomplete'],'502'=>['response_received','json']] as $scenario=>[$delivery,$bodyState]) {
        Wire::$scenario=(string)$scenario; Log::$lines=[];try{$client->postForm('/gateway.do',$body);}catch(Problem $e){}
        $out=$entries(); $check(count($out)===2 && $out[1]['delivery']===$delivery && $out[1]['body_state']===$bodyState && $out[1]['elapsed_ms']>=0,'failure attribution '.$scenario);
        $check(!str_contains(json_encode($out),'PRIVATE') && !str_contains(json_encode($out),'private reply'),'no raw content '.$scenario);
    }
    Wire::$scenario='ok'; Wire::$raw=json_encode(['data'=>['items'=>array_fill(0,100,['id'=>'item-1','phone'=>'13900009999'])],'code'=>'OK']);
    Log::$lines=[]; $client->send('GET','/reference-data',['resource'=>'products','page_no'=>2]);
    $check($entries()[1]['payload']['data']['items']['_omitted']===95 && strlen(json_encode($entries()))<16384,'bounded bridge samples');
    Wire::$during=static function()use(&$window){$window='';};Log::$lines=[];$client->send('GET','/reference-data');
    $check(count($entries())===2 && !isset($entries()[1]['payload']),'mid-flight stop closes metadata only');
    Wire::$during=null;Log::$lines=[];$client->send('GET','/reference-data');$check(!$entries(),'off by default and no process-global switch');
    $window='BW0123456789abcdef'; Log::$broken=true; $check($client->send('GET','/reference-data')['code']==='OK','unwritable logs never affect delivery');Log::$broken=false;
    echo "PASS $checks checks; fake DNS/cURL/Log only, no network, database, credentials or application boot.\n";
}
