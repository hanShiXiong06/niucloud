<?php
declare(strict_types=1);
/** 纯内存配置、协议与注入网络测试；不连数据库，不向顺丰发请求。 */
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $data = [];
        public function getConfigValue($site,$key) { return self::$data[$site][$key] ?? []; }
        public function setConfig($site,$key,$value) { self::$data[$site][$key]=$value; }
    }
}
namespace {
    function env($key,$default=null) { return $key==='app.auth_key' ? 'test-only-sf-protocol-key' : $default; }
    $root=dirname(__DIR__);
    foreach (['support/Cipher','service/core/ProviderException','service/core/ConfigService','service/core/SfConfigService','service/core/SfClient','service/core/SfProtocol'] as $file) require $root.'/app/'.$file.'.php';
    use addon\hsx_express\app\service\core\SfConfigService;
    use addon\hsx_express\app\service\core\SfClient;
    use addon\hsx_express\app\service\core\SfProtocol;
    use addon\hsx_express\app\service\core\ProviderException;
    $count=0;
    $ok=static function($condition,$label) use (&$count) { if (!$condition) throw new \RuntimeException('FAIL '.$label); $count++; };
    $throws=static function(callable $fn,$label) use ($ok) { try { $fn(); } catch (\Throwable $e) { $ok(true,$label); return $e; } $ok(false,$label); };
    $cfg=array_replace(SfConfigService::defaults(),['enabled'=>1,'client_code'=>'TEST_SF','check_word'=>'only-for-unit-tests','use_ack'=>1,'template_code'=>'fm_test']);
    $svc=new SfConfigService();
    $saved=$svc->save(101,'waybill',$cfg);
    $ok($saved['has_check_word'] && $saved['check_word']==='******','mask');
    $ok(!$saved['enabled'] && !$saved['use_ack'],'new identity must confirm again');
    $svc->save(101,'waybill',['enabled'=>1,'use_ack'=>1,'check_word'=>'******']);
    $ok($svc->get(101,'waybill')['check_word']==='only-for-unit-tests','retain mask');
    $svc->save(101,'waybill',['check_word'=>'']);
    $ok($svc->get(101,'waybill')['enabled']===1,'blank retains enabled account');
    $ok(strpos(json_encode(\app\service\core\sys\CoreConfigService::$data),'only-for-unit-tests')===false,'encrypted persistence');
    $ok($svc->get(101,'pickup')['check_word']==='' && $svc->get(102,'waybill')['check_word']==='','scene and tenant isolation');
    $switched=$svc->save(101,'waybill',['environment'=>'production']);
    $ok(!$switched['enabled'] && !$switched['has_check_word'] && !$switched['use_ack'],'environment resets credentials and consent');
    $throws(fn()=>$svc->save(101,'invalid',[]),'invalid scene');
    $throws(fn()=>$svc->save(101,'waybill',['client_code'=>[]]),'invalid field type');
    $svc->save(101,'waybill',['pay_method'=>2,'monthly_card'=>'7551234567']);
    $ok($svc->get(101,'waybill')['monthly_card']==='7551234567','waybill retains own account for later sender selection');
    $public=$svc->waybillOptions(101);
    $ok($public['freight_payment']['default']==='receiver' && $public['freight_payment']['sender_monthly_card_ready'] && $public['freight_payment']['sender_monthly_card_tail']==='4567','public payment options default receiver with masked account');
    $ok(!str_contains(json_encode($public),'7551234567') && !str_contains(json_encode($public),'check_word'),'options do not expose account or credentials');
    $ok(!$svc->waybillOptions(102)['freight_payment']['sender_monthly_card_ready'],'monthly account is tenant scoped');
    $throws(fn()=>$svc->save(101,'pickup',['pay_method'=>2,'monthly_card'=>'7551234567']),'pickup billing policy unchanged');
    $receiverConfig=SfConfigService::withWaybillPayment(array_replace($cfg,['monthly_card'=>'7551234567']));
    $ok($receiverConfig['pay_method']===2 && $receiverConfig['monthly_card']==='','receiver omits own monthly account');
    $senderConfig=SfConfigService::withWaybillPayment(array_replace($cfg,['pay_method'=>2,'monthly_card'=>'7551234567']),'sender');
    $ok($senderConfig['pay_method']===1 && $senderConfig['monthly_card']==='7551234567','sender uses saved site account');
    $throws(fn()=>SfConfigService::withWaybillPayment($cfg,'sender'),'sender account required');
    $throws(fn()=>SfConfigService::withWaybillPayment(array_replace($cfg,['pay_method'=>3,'monthly_card'=>'7551234567']),'sender'),'third-party account never treated as own');
    foreach ([1,3,[],true,'third_party',''] as $badPayment) $throws(fn()=>SfConfigService::withWaybillPayment($cfg,$badPayment),'invalid payment type');
    $throws(fn()=>$svc->save(101,'waybill',['monthly_card'=>'123']),'invalid account format');
    $ok(SfConfigService::readiness($cfg,'waybill')['ready'],'waybill ready');
    $ok(SfConfigService::readiness(array_replace($cfg,['template_code'=>'']),'pickup')['ready'],'pickup needs no template');
    $ok(!SfConfigService::readiness(array_replace($cfg,['template_code'=>'']),'waybill')['ready'],'waybill requires template');
    $ok(!SfConfigService::readiness($cfg,'waybill')['external_verified'],'no false test claim');
    $ok(SfClient::digest('{"language":"zh-CN","orderId":"QIAO-20200618-004"}','12312334453453','fjcg5PGKaNpPSHFAZ4QsCOkV71R3zVci')==='IIKJtuLVzoFTu4kHI8M8vA==','official public standard MD5 fixture');
    $ok(SfClient::digest('中 *~+','1','test')===base64_encode(md5('%E4%B8%AD+*%7E%2B1test',true)),'Java URLEncoder special characters');
    $payload=['sender'=>['name'=>'测试寄件人','mobile'=>'13800000000','address'=>'河北省测试市测试路1号'],
        'receiver'=>['name'=>'测试收件人','mobile'=>'13900000000','address'=>'河北省测试市测试路2号'],'cargo'=>'手机','weight'=>0.5,'count'=>1];
    $waybill=SfProtocol::createOrder($cfg,$payload,'TEST-ORDER');
    $ok($waybill['isDocall']===0 && !isset($waybill['sendStartTm']),'waybill never calls courier');
    $ok($waybill['payMethod']===1 && !isset($waybill['monthlyCard']),'cash payment no monthly account');
    $date=new \DateTimeImmutable('tomorrow 10:00',new \DateTimeZone('Asia/Shanghai'));
    $pickup=SfProtocol::createOrder($cfg,$payload+['pickup_start_at'=>$date->format('Y-m-d H:i:s'),'pickup_end_at'=>$date->modify('+1 hour')->format('Y-m-d H:i:s')],'PICKUP-ORDER','pickup');
    $ok($pickup['isDocall']===1 && $pickup['sendStartTm']===$date->format('Y-m-d H:i:s'),'pickup schedules call');
    $ok($pickup['extraInfoList'][0]['attrName']==='pickupAppointEndTime','explicit window end');
    $throws(fn()=>SfProtocol::createOrder($cfg,$payload,'PICKUP-ORDER','pickup'),'missing appointment');
    $throws(fn()=>SfProtocol::createOrder($cfg,$payload,str_repeat('A',65)),'order identifier never truncates');
    $throws(fn()=>SfProtocol::createOrder($cfg,array_replace($payload,['weight'=>0]),'TEST'),'invalid weight');
    $throws(fn()=>SfProtocol::createOrder($cfg,array_replace($payload,['weight'=>0.0001]),'TEST'),'weight cannot round to zero');
    $throws(fn()=>SfProtocol::createOrder($cfg,array_replace($payload,['count'=>2]),'TEST'),'unsupported multi-parcel');
    $throws(fn()=>SfProtocol::createOrder($cfg,array_replace($payload,['count'=>1.5]),'TEST'),'fractional parcel count cannot truncate');
    $bad=$payload; $bad['sender']['address']=str_repeat('地',201);
    $throws(fn()=>SfProtocol::createOrder($cfg,$bad,'TEST'),'reject long address before IO');
    $good=['success'=>true,'errorCode'=>'S0000','msgData'=>['orderId'=>'TEST-ORDER','filterResult'=>'2','waybillNoInfoList'=>[['waybillType'=>1,'waybillNo'=>'SF1234567890123']]]];
    $ok(SfProtocol::normalizeOrder($good)['confirmed'],'unique matching mother waybill evidence');
    foreach (['1','3','4',''] as $filter) { $r=$good; $r['msgData']['filterResult']=$filter; $ok(!SfProtocol::normalizeOrder($r)['confirmed'],'nonpassing filter '.$filter); }
    $r=$good; $r['success']='false'; $ok(!SfProtocol::normalizeOrder($r)['success'],'false string is not truthy success');
    $r=$good; $r['msgData']['waybillNoInfoList'][]=['waybillType'=>1,'waybillNo'=>'SF1234567890124']; $ok(!SfProtocol::normalizeOrder($r)['confirmed'],'conflicting mother waybills');
    $r=SfProtocol::normalizeOrder(['success'=>false,'errorCode'=>'8096','errorMsg'=>'test-secret','msgData'=>null]);
    $ok($r['definitive_rejected'] && strpos($r['message'],'营业时间')!==false,'known pickup time rejection is actionable');
    $ok(!SfProtocol::normalizeOrder(['success'=>false,'errorCode'=>'8016'])['definitive_rejected'],'duplicate is unknown, never new-order retry');
    $cancel=['success'=>true,'errorCode'=>'S0000','msgData'=>['orderId'=>'TEST-ORDER','resStatus'=>2]];
    $ok(SfProtocol::cancelConfirmed($cancel,'TEST-ORDER'),'cancel identity matches');
    $ok(!SfProtocol::cancelConfirmed($cancel,'OTHER'),'cancel identity mismatch');
    $cancel['msgData']['resStatus']=1; $ok(!SfProtocol::cancelConfirmed($cancel,'TEST-ORDER'),'resStatus1 not cancelled');
    $ok(!SfProtocol::cancelConfirmed(['success'=>false,'errorCode'=>'8019'],'TEST-ORDER'),'ambiguous cancel code');
    $ok(SfProtocol::cancelConfirmed(['success'=>false,'errorCode'=>'8037'],'TEST-ORDER'),'already cancelled within original request context');
    $ok(!SfProtocol::cancelConfirmed(['success'=>false,'errorCode'=>'8037']),'no original context cannot infer cancellation');
    $ok(!SfProtocol::cancelConfirmed(['success'=>false,'errorCode'=>'8253','msgData'=>['orderId'=>'OTHER']],'TEST-ORDER'),'cancel conflict never accepted');
    $pdf=['success'=>true,'obj'=>['fileType'=>'pdf','files'=>[['url'=>'https://eos-scp-core-shenzhen-futian1-oss.sf-express.com:443/test.pdf','token'=>'test-token','waybillNo'=>'SF1234567890123']]]];
    $meta=SfProtocol::normalizePdf($pdf,'SF1234567890123');
    $ok($meta['expires_at']>time() && $meta['expires_at']<time()+86400,'PDF expiry conservative');
    $throws(fn()=>SfProtocol::normalizePdf($pdf,'SF1234567890124'),'PDF wrong waybill');
    foreach (['http://127.0.0.1/a','https://evil.test/a','https://eos-scp-core-shenzhen-futian1-oss.sf-express.com.evil.test/a','https://user@eos-scp-core-shenzhen-futian1-oss.sf-express.com/a'] as $url) { $badPdf=$pdf; $badPdf['obj']['files'][0]['url']=$url; $throws(fn()=>SfProtocol::normalizePdf($badPdf,'SF1234567890123'),'PDF URL boundary'); }
    $badPdf=$pdf; $badPdf['obj']['files'][0]['token']="x\r\nInjected: yes"; $throws(fn()=>SfProtocol::normalizePdf($badPdf,'SF1234567890123'),'header injection');
    $mismatchMessage='顺丰PDF模板与当前顾客编码不匹配，请到当前应用的云打印接口详情复制已分配模板编码，再重新获取原单PDF';
    $e=$throws(fn()=>SfProtocol::normalizePdf(['success'=>false,'errorMessage'=>'templateCode:fm_test_private is not matched the clientCode:TEST_PRIVATE'],'SF1234567890123'),'verified PDF template account mismatch');
    $ok($e instanceof ProviderException && !$e->isUnknown() && $e->getMessage()===$mismatchMessage,'mismatch explains original PDF recovery without leaking template or account');
    foreach ([
        'unknown error with token=private-download-token and checkWord=private-secret',
        'templateCode:fm_test is not matched the clientCode:TEST_PRIVATE token=private-secret',
        'prefix templateCode:fm_test is not matched the clientCode:TEST_PRIVATE',
        "templateCode:fm_test is not matched the clientCode:TEST_PRIVATE\n",
        ['message'=>'templateCode:fm_test is not matched the clientCode:TEST_PRIVATE'],
    ] as $unknownMessage) {
        $e=$throws(fn()=>SfProtocol::normalizePdf(['success'=>false,'errorMessage'=>$unknownMessage],'SF1234567890123'),'unknown PDF errorMessage stays private');
        $ok($e->getMessage()==='顺丰尚未生成PDF，请核对模板及接口权限','only the complete verified sentence is recognized; unknown content never echoed');
    }
    $e=$throws(fn()=>SfProtocol::normalizePdf(['success'=>'false','errorMessage'=>'templateCode:fm_test is not matched the clientCode:TEST_PRIVATE'],'SF1234567890123'),'unverified success representation remains generic');
    $ok($e->getMessage()==='顺丰尚未生成PDF，请核对模板及接口权限','mismatch signature requires actual boolean false');
    $calls=0;
    $client=new SfClient(static function($url,$form) use (&$calls,$ok,$good) {
        $calls++; $ok($url===SfClient::SANDBOX_URL,'fixed sandbox gateway');
        $ok(strlen($form['requestID'])===36 && $form['serviceCode']===SfClient::CREATE_ORDER,'request envelope');
        $ok($form['msgDigest']===SfClient::digest($form['msgData'],$form['timestamp'],'only-for-unit-tests'),'signed exact JSON');
        return ['apiResultCode'=>'A1000','apiResponseID'=>'response-test','apiResultData'=>json_encode($good)];
    });
    $result=$client->request(SfClient::CREATE_ORDER,$waybill,$cfg);
    $ok($result['_response_id']==='response-test' && $calls===1,'parsed business response, one call');
    foreach (['A1001','A1002','A1003','A1004','A1005','A1006','A1008'] as $code) {
        $e=$throws(fn()=>(new SfClient(fn()=>['apiResultCode'=>$code,'apiErrorMsg'=>'secret']))->request(SfClient::CREATE_ORDER,[],$cfg),'known gateway rejection');
        $ok($e instanceof ProviderException && !$e->isUnknown() && strpos($e->getMessage(),'secret')===false,'safe known gateway');
    }
    foreach (['A1007','A1009','A1099',''] as $code) {
        $e=$throws(fn()=>(new SfClient(fn()=>['apiResultCode'=>$code]))->request(SfClient::CREATE_ORDER,[],$cfg),'unknown gateway');
        $ok($e instanceof ProviderException && $e->isUnknown(),'unknown not retryable');
    }
    $e=$throws(fn()=>(new SfClient(fn()=>throw new \RuntimeException('token=secret')))->request(SfClient::CREATE_ORDER,[],$cfg),'network throw');
    $ok($e->isUnknown() && strpos($e->getMessage(),'secret')===false,'network safe');
    $throws(fn()=>$client->request('ARBITRARY',[],$cfg),'no arbitrary endpoint');
    echo 'PASS '.$count." SF protocol/config/client checks; no DB/network/shipping\n";
}
