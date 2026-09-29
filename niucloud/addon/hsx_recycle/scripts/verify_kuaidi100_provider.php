<?php
declare(strict_types=1);

// 插件升级可复用的独立验证：不连接数据库、真实账号或外部网络，完整使用假传输器执行协议契约。
require dirname(__DIR__, 3) . '/core/exception/CommonException.php';
require __DIR__ . '/../app/service/core/express/ExpressSubmissionException.php';
require __DIR__ . '/../app/service/core/express/contract/ExpressProviderInterface.php';
require __DIR__ . '/../app/service/core/express/provider/Kuaidi100Protocol.php';
require __DIR__ . '/../app/service/core/express/provider/Kuaidi100ExpressProvider.php';

use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
use addon\hsx_recycle\app\service\core\express\provider\Kuaidi100Protocol as P;
use addon\hsx_recycle\app\service\core\express\provider\Kuaidi100ExpressProvider as Provider;

$count = 0;
function check(bool $value, string $label): void { global $count; if (!$value) throw new RuntimeException('FAIL ' . $label); $count++; }
function rejects(callable $fn, string $outcome, string $label): void {
    try { $fn(); } catch (ExpressSubmissionException $e) { check($e->outcome() === $outcome, $label); return; }
    throw new RuntimeException('FAIL no exception: ' . $label);
}
$config = ['api_key' => 'test-key', 'secret' => 'test-secret', 'callback_salt' => str_repeat('s', 32),
    'callback_url' => 'https://test.example/api/recycle/express/kuaidi100_push', 'carrier_code' => 'shunfeng',
    'carrier_name' => '顺丰速运', 'service_type' => '顺丰标快', 'mode' => 'online', 'environment' => 'sandbox',
    'payment' => 'SHIPPER', 'channel_sw' => '', 'timeout' => 5];
$day = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
$request = ['senderName' => '测试寄方', 'senderMobile' => '13800000000', 'senderProvince' => '广东省', 'senderCity' => '深圳市', 'senderDistrict' => '南山区', 'senderAddress' => '测试路1号',
    'receiveName' => '测试收方', 'receiveMobile' => '13900000000', 'receiveProvince' => '广东省', 'receiveCity' => '广州市', 'receiveDistrict' => '天河区', 'receiveAddress' => '测试路2号',
    'weight' => 1, 'packageCount' => 1, 'goods' => '手机', 'thirdOrderNo' => 'recycle_100000_42', 'orderSendTime' => $day . ' 09:00-11:00'];

P::validateConfig($config);
$productCode = P::productCode($config);
check(strlen($productCode) === 50 && preg_match('/^kuaidi100:[a-f0-9]{40}$/D', $productCode) === 1, 'stable product code fits varchar50');
check(P::productCode(array_reverse($config, true)) === $productCode, 'product identity independent of config key order');
$longCarrier = array_replace($config, ['carrier_code' => str_repeat('s', 40)]);
P::validateConfig($longCarrier);
check(strlen(P::productCode($longCarrier)) === 50, 'maximum allowed carrier remains within database limit');
foreach (['mode' => 'offline', 'carrier_code' => 'jd', 'service_type' => '顺丰特快', 'channel_sw' => 'contract-channel', 'payment' => 'CONSIGNEE'] as $field => $value) {
    check(P::productCode(array_replace($config, [$field => $value])) !== $productCode, 'product identity separates ' . $field);
}
check(P::productCode(array_replace($longCarrier, ['carrier_code' => str_repeat('s', 39) . 'x'])) !== P::productCode($longCarrier), 'long carrier suffix participates in identity');
check(P::productCode(array_replace($config, ['service_type' => 'a|b', 'channel_sw' => 'c'])) !== P::productCode(array_replace($config, ['service_type' => 'a', 'channel_sw' => 'b|c'])), 'delimiter in product name cannot alias another identity');
check(P::productCode(array_replace($config, ['api_key' => 'rotated', 'secret' => 'rotated'])) === $productCode, 'account credential rotation does not rename product');
check(P::endpoint($config) === 'http://e-test.kuaidilab.com/api/order/borderapi.do', 'online sandbox stays sandbox');
check(P::endpoint(array_replace($config, ['environment' => 'production'])) === 'https://poll.kuaidi100.com/order/borderapi.do', 'online production host');
check(P::endpoint(array_replace($config, ['environment' => 'production', 'mode' => 'offline'])) === 'https://order.kuaidi100.com/order/corderapi.do', 'offline production host');
rejects(fn() => P::validateConfig(array_replace($config, ['payment' => 'CONSIGNEE'])), 'rejected', 'online rejects COD');
rejects(fn() => P::validateConfig(array_replace($config, ['mode' => 'offline', 'payment' => 'CONSIGNEE', 'carrier_code' => 'zhongtong'])), 'rejected', 'offline carrier COD restriction');
rejects(fn() => P::validateConfig(array_replace($config, ['callback_salt' => ''])), 'rejected', 'empty callback salt rejected');
rejects(fn() => P::validateConfig(array_replace($config, ['callback_url' => 'http://example.test/x'])), 'rejected', 'insecure callback rejected');
rejects(fn() => P::endpoint(array_replace($config, ['environment' => 'typo'])), 'rejected', 'invalid environment never defaults production');

$param = P::createParams(100000, $config, $request);
check($param['kuaidicom'] === 'shunfeng' && $param['payment'] === 'SHIPPER', 'administrator controls carrier and payment');
check($param['sendManPrintAddr'] === '广东省深圳市南山区测试路1号', 'full sender address');
check($param['dayType'] === '明天' && $param['pickupStartTime'] === '09:00' && $param['pickupEndTime'] === '11:00', 'appointment conversion');
check($param['returnType'] === '' && !isset($param['siid']), 'no electronic label required');
check(strlen($param['thirdOrderId']) === 32 && $param['thirdOrderId'] === P::thirdOrderId(100000, $request['thirdOrderNo']), 'stable bounded id');
check($param['thirdOrderId'] !== P::thirdOrderId(100001, $request['thirdOrderNo']), 'site isolated request id');
$overridden = P::createParams(100000, $config, $request + ['callback_salt' => str_repeat('o', 48), 'callback_url' => $config['callback_url'] . '?record_id=42']);
check($overridden['salt'] === str_repeat('o', 48) && strpos($overridden['callBackUrl'], 'record_id=42') !== false, 'per order callback supplied by backend');
rejects(fn() => P::createParams(100000, $config, array_replace($request, ['senderAddress' => ''])), 'rejected', 'missing address before network');
rejects(fn() => P::createParams(100000, $config, array_replace($request, ['weight' => -1])), 'rejected', 'invalid weight');
rejects(fn() => P::createParams(100000, $config, array_replace($request, ['packageCount' => 2])), 'rejected', 'multi parcel explicit not silently ignored');
rejects(fn() => P::createParams(100000, $config, array_replace($request, ['orderSendTime' => $day . ' 10:00-10:30'])), 'rejected', 'SF one hour minimum');
rejects(fn() => P::createParams(100000, $config, array_replace($request, ['orderSendTime' => ''])), 'rejected', 'appointment required');
$signed = P::signedBody($config, 'bOrder', $param, '1234567890000');
check($signed['sign'] === strtoupper(md5($signed['param'] . '1234567890000test-keytest-secret')), 'exact request signature');

$calls = [];
$transport = static function ($url, $body) use (&$calls) { $calls[] = [$url, $body]; return ['result' => true, 'returnCode' => '200', 'data' => ['taskId' => 'T42', 'orderId' => 'O42', 'kuaidinum' => null, 'pollToken' => 'private-token']]; };
$provider = new Provider(fn() => $config, $transport);
$configReads = 0;
$raceCalls = 0;
$racingProvider = new Provider(function () use (&$configReads, $config) {
    $configReads++;
    return $configReads === 1 ? $config : array_replace($config, ['api_key' => 'changed-between-snapshot-and-submit']);
}, function () use (&$raceCalls) { $raceCalls++; return []; });
$originalSnapshot = $racingProvider->prepareSnapshot(100000);
rejects(fn() => $racingProvider->create(100000, array_replace($request, $originalSnapshot)), 'rejected', 'account change after persisted snapshot rejects before submit');
check($configReads === 2 && $raceCalls === 0, 'create reads config once and never sends mismatched account');
$created = $provider->create(100000, $request);
check(count($calls) === 1 && $calls[0][1]['method'] === 'bOrder', 'one submission only');
check($created['orderNo'] === 'O42' && $created['deliveryId'] === '', 'provider ID never masquerades as tracking');
check($created['booking_state'] === 'accepted', 'API accepted not completed pickup');
check(!isset($created['raw']['pollToken']) && strpos(json_encode($created), 'test-secret') === false, 'secret not in normalized return');
check($provider->products(100000)[0]['pickup_time_required'] === true, 'frontend knows appointment required');
check($created['provider_account_fingerprint'] === hash('sha256', 'test-key'), 'account fingerprint');
$provider->detail(100000, $created);
check(json_decode($calls[1][1]['param'], true) === ['taskId' => 'T42'], 'detail uses original task id');
$provider->cancel(100000, $created);
check(json_decode($calls[2][1]['param'], true)['orderId'] === 'O42', 'cancel uses original order ID');
$changedProvider = new Provider(fn() => array_replace($config, ['api_key' => 'another-account']), $transport);
rejects(fn() => $changedProvider->detail(100000, $created), 'rejected', 'cannot read old order with new account');
rejects(fn() => $provider->detail(100000, array_replace($created, ['provider_task_id' => ''])), 'unknown', 'missing task cannot prove no booking');
$timeout = new Provider(fn() => $config, static function () { throw new RuntimeException('credentials must not leak'); });
rejects(fn() => $timeout->create(100000, $request), 'unknown', 'network timeout unknown');
$badResponse = new Provider(fn() => $config, fn() => ['result' => true, 'returnCode' => '200', 'data' => []]);
rejects(fn() => $badResponse->create(100000, $request), 'unknown', 'missing order identity unknown');
foreach (['400' => 'rejected', '503' => 'rejected', '500' => 'unknown', '501' => 'unknown'] as $code => $outcome) {
    $failed = new Provider(fn() => $config, fn() => ['result' => false, 'returnCode' => (string)$code, 'message' => '模拟失败']);
    rejects(fn() => $failed->create(100000, $request), $outcome, 'provider error ' . $code);
}
$offline = array_replace($config, ['mode' => 'offline', 'payment' => 'CONSIGNEE']);
$off = new Provider(fn() => $offline, $transport);
check(strpos($off->products(100000)[0]['payment_tips'], '到付：收件方') === 0, 'offline consignee fee responsibility visible');
$shipper = new Provider(fn() => array_replace($offline, ['payment' => 'SHIPPER']), $transport);
check(strpos($shipper->products(100000)[0]['payment_tips'], '寄付：寄件方') === 0, 'offline shipper fee responsibility visible');
$onlineTips = new Provider(fn() => $config, $transport);
check(strpos($onlineTips->products(100000)[0]['payment_tips'], '客户是否承担以门店约定为准') !== false, 'online never implies free customer shipping');
$off->create(100000, $request);
$last = end($calls);
check(strpos($last[0], 'corderapi.do') !== false && $last[1]['method'] === 'cOrder', 'offline protocol separated');
$quoteCalls = [];
$quoteProvider = new Provider(fn() => $offline, function ($url, $body) use (&$quoteCalls) { $quoteCalls[] = $body; return ['result' => true, 'returnCode' => '200', 'data' => ['price' => '23.00']]; });
$quote = $quoteProvider->quote(100000, $request);
check(isset(json_decode($quoteCalls[0]['param'], true)['kuaidicom']) && $quote[0]['is_estimate'], 'offline price field lowercase and estimate marker');
$nullPrice = new Provider(fn() => $config, fn() => ['result' => true, 'returnCode' => '200', 'data' => ['price' => null]]);
try { $nullPrice->quote(100000, $request); throw new RuntimeException('null price accepted'); } catch (\core\exception\CommonException $e) { check(strpos($e->getMessage(), '免费') !== false, 'null quote never free'); }

$callback = ['status' => '200', 'kuaidicom' => 'shunfeng', 'kuaidinum' => '', 'data' => ['orderId' => 'O42', 'status' => 1, 'courierName' => '测试员', 'courierMobile' => '13800000000', 'kuaidinum' => 'SF42', 'freight' => '18.5', 'feeDetails' => [['feeType' => 'PACKAGINGFEE', 'amount' => '1']]]];
$raw = json_encode($callback, JSON_UNESCAPED_UNICODE);
check(P::verifyCallback($raw, strtoupper(md5($raw . $config['callback_salt'])), $config['callback_salt']), 'callback correct signature');
check(!P::verifyCallback($raw . ' ', strtoupper(md5($raw . $config['callback_salt'])), $config['callback_salt']), 'callback tampering rejected');
$normalized = P::normalizeCallback($callback, 'T42', $created);
check($normalized['booking_state'] === 'assigned' && $normalized['deliveryId'] === 'SF42', 'courier assigned and inner tracking retained');
check($normalized['actual_cost'] === null && $normalized['reported_freight'] === '18.5', 'unreconciled freight not final expense');
check(P::normalize(['status' => 155], $created)['booking_state'] === '', 'fee update does not change logistics state');
check(P::normalize(['status' => 166], $created)['conflict'], 'revived order requires reconciliation');
check(P::normalize(['status' => 302, 'dispatchData' => ['orderId' => 'new']], $created)['conflict'], 'redispatch conflict retained');
$wrongCarrier = P::normalize(['status' => 1, 'kuaidiCom' => 'jd'], $created);
check($wrongCarrier['conflict'] && $wrongCarrier['carrier_name'] === '京东快递', 'different carrier not mislabeled original SF');
echo "PASS {$count} kuaidi100 provider checks (mock transport, no DB/network)\n";
