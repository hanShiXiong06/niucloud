<?php
declare(strict_types=1);

/** 真实顺丰协议与回收桥接，配置/站点/网络均为替身；不读取 .env、不下单、不扣费、不发通知。 */
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $data = [];
        public function getConfigValue($site, $key) { return self::$data[$site][$key] ?? []; }
        public function setConfig($site, $key, $value) { self::$data[$site][$key] = $value; }
    }
}
namespace app\service\core\site {
    class CoreSiteService {
        public function getAddonKeysBySiteId(int $site): array { return $site === 17 ? ['hsx_express', 'hsx_recycle'] : []; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    function env($key, $default = null) { return $key === 'app.auth_key' ? 'mock-only-encryption-key' : $default; }
    $root = dirname(__DIR__);
    foreach (['support/Cipher', 'service/core/ProviderException', 'service/core/ConfigService', 'service/core/SfClient', 'service/core/SfConfigService', 'service/core/SfProtocol'] as $file) require $root . '/app/' . $file . '.php';
    require dirname($root) . '/hsx_recycle/app/service/core/express/contract/ExpressProviderInterface.php';
    require dirname($root) . '/hsx_recycle/app/service/core/express/ExpressSubmissionException.php';
    require dirname($root) . '/hsx_recycle/app/service/core/express/PickupState.php';
    require dirname($root) . '/hsx_recycle/app/service/core/express/PickupAppointmentPolicy.php';
    require $root . '/app/integration/RecycleSfPickupProvider.php';
    require $root . '/app/integration/RecycleSfProviderRegistry.php';
    require dirname($root) . '/hsx_recycle/app/dict/third_party/ThirdPartyDict.php';
    require dirname($root) . '/hsx_recycle/app/service/core/express/ExpressProviderRegistry.php';

    use addon\hsx_express\app\integration\RecycleSfPickupProvider as Provider;
    use addon\hsx_express\app\integration\RecycleSfProviderRegistry as Registration;
    use addon\hsx_express\app\service\core\SfClient;
    use addon\hsx_express\app\service\core\ProviderException;
    use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
    use addon\hsx_recycle\app\service\core\express\PickupState;

    function event(string $name, array $params): array {
        return $name === 'HsxExpressProviderRegistry' ? [(new Registration())->handle($params)] : [];
    }

    $checks = 0;
    function check(bool $ok, string $message): void {
        global $checks;
        if (!$ok) throw new \RuntimeException('FAIL ' . $message);
        $checks++;
    }
    function reject(callable $operation, string $outcome, string $message): void {
        try { $operation(); }
        catch (ExpressSubmissionException $e) { check($e->outcome() === $outcome, $message); return; }
        throw new \RuntimeException('FAIL no rejection: ' . $message);
    }
    $config = ['enabled' => 1, 'environment' => 'production', 'client_code' => 'MOCK_CLIENT',
        'check_word' => 'MOCK_CHECK_WORD', 'monthly_card' => '1234567890', 'product_code' => '2',
        'pay_method' => 1, 'template_code' => '', 'use_ack' => 1, 'callback_base_url' => ''];
    $requests = [];
    $loaded = [];
    $nextResponse = null;
    $transport = static function (string $service, array $message, array $cfg) use (&$requests, &$nextResponse): array {
        $requests[] = [$service, $message, $cfg];
        if ($nextResponse instanceof \Throwable) throw $nextResponse;
        if (is_array($nextResponse)) return $nextResponse;
        $data = ['orderId' => $message['orderId']];
        if ($service === SfClient::CANCEL_ORDER) $data['resStatus'] = 2;
        else $data += ['filterResult' => 2, 'waybillNoInfoList' => [['waybillType' => 1, 'waybillNo' => 'SF000000000001']]];
        return ['success' => true, 'errorCode' => 'S0000', 'msgData' => $data, '_response_id' => 'MOCK_RESPONSE'];
    };
    $provider = new Provider(static function (int $site, string $scene) use (&$config, &$loaded): array {
        $loaded[] = [$site, $scene]; return $config;
    }, $transport);
    $day = (new \DateTimeImmutable('+1 day', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    $input = ['thirdOrderNo' => 'recycle_17_100', 'deliveryType' => 'sf_pickup_2',
        'senderName' => '模拟客户', 'senderMobile' => '13800000000',
        'senderProvince' => '广东省', 'senderCity' => '深圳市', 'senderDistrict' => '南山区', 'senderAddress' => '测试路1号',
        'receiveName' => '模拟门店', 'receiveMobile' => '13900000000',
        'receiveProvince' => '广东省', 'receiveCity' => '深圳市', 'receiveDistrict' => '福田区', 'receiveAddress' => '测试路2号',
        'goods' => '回收设备', 'weight' => 0.5, 'packageCount' => 1, 'orderSendTime' => $day . ' 10:00-12:00',
        'payment' => 3, 'monthly_card' => 'ATTACKER_MONTHLY', 'service_type' => 'ATTACKER_PRODUCT',
        'provider' => 'kuaidi100', 'callback_url' => 'https://attacker.invalid/push'];

    check($provider->healthCheck(17), 'pickup configuration locally ready without waybill template');
    $products = $provider->products(17);
    check(count($products) === 1 && $products[0]['product_code'] === 'sf_pickup_2', 'only site-configured pickup product exposed');
    check($products[0]['capabilities']['waybill_print'] === false && $products[0]['capabilities']['quote'] === false, 'no electronic label or fabricated quote capability');
    check($products[0]['capabilities']['callback'] === false && $products[0]['capabilities']['courier_assignment'] === false, 'unverified callbacks and courier assignment not advertised');
    $snapshot = $provider->prepareSnapshot(17, $input);
    check($snapshot['provider_scene'] === 'pickup' && $snapshot['provider_site_id'] === 17, 'snapshot records pickup product and tenant');
    check(strlen($snapshot['provider_order_id']) === 32, 'stable official order number within documented 64 character limit');
    check($snapshot['provider_order_id'] === $provider->prepareSnapshot(17, $input)['provider_order_id'], 'same tenant business request keeps stable query number');
    check($snapshot['provider_order_id'] !== $provider->prepareSnapshot(18, $input)['provider_order_id'], 'same business number cannot collide across tenants');
    check(!str_contains(json_encode($snapshot), 'MOCK_CLIENT') && !str_contains(json_encode($snapshot), 'MOCK_CHECK_WORD') && !str_contains(json_encode($snapshot), '1234567890'), 'snapshot never stores credentials or monthly account');
    check($provider->queryRequirements() === ['provider_order_id'], 'reconciliation queries using pre-persisted original order number');

    $result = $provider->create(17, array_replace($input, $snapshot));
    check($result['booking_state'] === 'accepted' && !PickupState::view($result)['can_manual'], 'waybill allocated is accepted only, never pickup confirmed or self-send eligible');
    check($result['deliveryId'] === 'SF000000000001' && $result['orderNo'] === $snapshot['provider_order_id'], 'official identifiers preserved separately');
    check(!isset($result['courier_name']) && !isset($result['courier_phone']), 'no fabricated courier assignment');
    check(count($requests) === 1 && $requests[0][0] === SfClient::CREATE_ORDER, 'one create call, never print endpoint');
    $sent = $requests[0][1];
    check((int)$sent['isDocall'] === 1, 'pickup explicitly requests dispatch, unlike electronic label creation');
    check((int)$sent['payMethod'] === 1 && (string)$sent['monthlyCard'] === '1234567890' && (string)$sent['expressTypeId'] === '2', 'customer cannot replace site payer account or product');
    check($sent['sendStartTm'] === $day . ' 10:00:00', 'requested start sent as absolute Beijing time');
    check(str_contains(json_encode($sent['extraInfoList'] ?? []), 'pickupAppointEndTime') && str_contains(json_encode($sent['extraInfoList'] ?? []), '12:00:00'), 'requested end uses official extraInfoList key');
    check(!str_contains(json_encode($sent), 'attacker.invalid'), 'untrusted callback URL is never sent');
    check($result['pickup_time'] === $day . ' 10:00-12:00', 'customer requested time window retained as request, not arrival guarantee');
    check(count(array_filter($loaded, static fn(array $row): bool => $row[1] !== 'pickup')) === 0, 'never reads waybill credentials');

    $appointment = \addon\hsx_recycle\app\service\core\express\PickupAppointmentPolicy::resolve([]);
    $scheduled = $provider->create(17, array_replace($input, $snapshot, ['orderSendTime' => $appointment['pickup_time']]));
    check($scheduled['pickup_time'] === $appointment['pickup_time'], 'server-assigned window passes real SF adapter unchanged');
    check(end($requests)[1]['sendStartTm'] === substr($appointment['pickup_time'], 0, 16) . ':00', 'SF receives same server-generated future start');
    check(str_contains(json_encode(end($requests)[1]['extraInfoList']), substr($appointment['pickup_time'], -5) . ':00'), 'SF receives same window end as customer display');

    $before = count($requests);
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot, ['provider_scene' => 'waybill'])), 'rejected', 'cannot replace pickup scene');
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot, ['provider_site_id' => 18])), 'rejected', 'cannot replace snapshot tenant');
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot, ['provider_order_id' => 'OTHER_ORDER'])), 'rejected', 'cannot replace stable order number');
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot, ['orderSendTime' => '2026-02-30 10:00-12:00'])), 'rejected', 'invalid dates fail before external create');
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot, ['orderSendTime' => $day . ' 12:00-10:00'])), 'rejected', 'reversed time window rejected');
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot, ['packageCount' => 2])), 'rejected', 'unsupported multiple parcels rejected explicitly');
    check(count($requests) === $before, 'all invalid create inputs caused zero external calls');

    $nextResponse = new ProviderException('顺丰网络响应异常，结果待核实，请勿重复提交', true);
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot)), 'unknown', 'network uncertainty never marks failed or allows duplicate pickup');
    $nextResponse = new ProviderException('顺丰拒绝请求（A1004）：当前应用未开通此接口权限', false);
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot)), 'rejected', 'only definite client refusal permits failed state');
    try { $provider->create(17, array_replace($input, $snapshot)); }
    catch (ExpressSubmissionException $e) { check(str_contains($e->getMessage(), 'A1004') && str_contains($e->getMessage(), '接口权限'), 'shared client safe error code and remediation remain visible'); }
    $nextResponse = ['success' => false, 'errorCode' => '1012', 'errorMsg' => 'private customer address'];
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot)), 'rejected', 'documented creation rejection permits original order self-send fallback');
    reject(static fn() => $provider->detail(17, $snapshot), 'unknown', 'query rejection cannot prove original booking failed');
    foreach (['8016', '8018', '6150', 'UNKNOWN_ERROR'] as $code) {
        $nextResponse = ['success' => false, 'errorCode' => $code];
        reject(static fn() => $provider->create(17, array_replace($input, $snapshot)), 'unknown', 'duplicate or missing order remains unknown, never safe to rebook');
    }
    foreach ([3, 4, null] as $filter) {
        $nextResponse = ['success' => true, 'errorCode' => 'S0000', 'msgData' => ['orderId' => $snapshot['provider_order_id'], 'filterResult' => $filter]];
        $uncertain = $provider->create(17, array_replace($input, $snapshot));
        check($uncertain['booking_state'] === 'unknown' && !PickupState::view($uncertain)['can_manual'], 'unsupported or uncertain service area never presented as accepted or safe self-send');
        reject(static fn() => $provider->detail(17, $snapshot), 'unknown', 'uncertain query does not manufacture accepted pickup');
    }
    $nextResponse = ['success' => true, 'errorCode' => 'S0000', 'msgData' => ['orderId' => 'OTHER', 'filterResult' => 2]];
    reject(static fn() => $provider->detail(17, $snapshot), 'unknown', 'query cannot accept another order response');
    $nextResponse = null;
    $detail = $provider->detail(17, $snapshot);
    check($detail['booking_state'] === 'accepted', 'search result cannot become pickup success merely from filterResult two');
    check(end($requests)[0] === SfClient::SEARCH_ORDER && end($requests)[1]['orderId'] === $snapshot['provider_order_id'], 'unknown-result compensation searches original order only');

    $before = count($requests);
    reject(static fn() => $provider->detail(18, $snapshot), 'unknown', 'historical query rejects other tenant');
    $config['client_code'] = 'OTHER_CLIENT';
    reject(static fn() => $provider->detail(17, $snapshot), 'unknown', 'historical query rejects replacement account');
    $config['client_code'] = 'MOCK_CLIENT';
    $config['monthly_card'] = 'OTHER_MONTHLY';
    reject(static fn() => $provider->cancel(17, $snapshot), 'unknown', 'cancel does not use replacement monthly account');
    $config['monthly_card'] = '1234567890';
    $config['environment'] = 'sandbox';
    reject(static fn() => $provider->detail(17, $snapshot), 'unknown', 'historical query cannot combine new environment credentials with old endpoint');
    check($provider->healthCheck(17) && $provider->products(17) === [], 'sandbox local readiness cannot expose real customer pickup product');
    reject(static fn() => $provider->prepareSnapshot(17, $input), 'rejected', 'sandbox blocked before real recycle booking placeholder');
    reject(static fn() => $provider->create(17, $input), 'rejected', 'sandbox cannot update a real recycle order through create');
    $config['environment'] = 'production';
    check(count($requests) === $before, 'tenant and account mismatches make no request');

    foreach ([
        ['success' => true, 'errorCode' => 'S0000', 'msgData' => ['orderId' => $snapshot['provider_order_id'], 'resStatus' => 1]],
        ['success' => true, 'errorCode' => 'S0000', 'msgData' => ['orderId' => 'OTHER', 'resStatus' => 2]],
        ['success' => true, 'errorCode' => 'S0000', 'msgData' => ['resStatus' => 2]],
        ['success' => false, 'errorCode' => '8019', 'msgData' => ['orderId' => $snapshot['provider_order_id']]],
        ['success' => false, 'errorCode' => '8037', 'msgData' => ['orderId' => 'OTHER']],
        ['success' => false, 'errorCode' => '8253', 'msgData' => ['orderId' => 'OTHER']],
    ] as $response) {
        $nextResponse = $response;
        reject(static fn() => $provider->cancel(17, $snapshot), 'unknown', 'ambiguous, mismatched, or unowned cancellation is not confirmed');
    }
    $beforeRetry = count($requests);
    $nextResponse = new ProviderException('顺丰取消响应超时，原单结果待核实', true);
    reject(static fn() => $provider->cancel(17, $snapshot), 'unknown', 'cancellation timeout cannot confirm cancellation or release self-send');
    foreach (['8037', '8253'] as $alreadyCancelledCode) {
        $nextResponse = ['success' => false, 'errorCode' => $alreadyCancelledCode];
        $cancelled = $provider->cancel(17, $snapshot);
        check($cancelled['booking_state'] === 'cancelled' && PickupState::view($cancelled)['can_manual'], 'documented already-cancelled response on original cancel context confirms self-send eligibility');
        check($cancelled['orderNo'] === $snapshot['provider_order_id'], 'cancellation retry always retains original provider order identity');
        $nextResponse['msgData'] = ['orderId' => $snapshot['provider_order_id']];
        check($provider->cancel(17, $snapshot)['booking_state'] === 'cancelled', 'matching explicit order identity also confirms already-cancelled response');
    }
    $retryRequests = array_slice($requests, $beforeRetry);
    check(count($retryRequests) === 5 && count(array_filter($retryRequests, static fn(array $row): bool => $row[0] === SfClient::CANCEL_ORDER
        && $row[1]['orderId'] === $snapshot['provider_order_id'] && (int)$row[1]['dealType'] === 2)) === 5,
        'timeout and repeated cancellation never create another order or change the original order number');
    $nextResponse = null;
    $config['enabled'] = 0;
    check(!$provider->healthCheck(17) && $provider->products(17) === [], 'disabled pickup hides new pickup even if waybill product could be enabled');
    check($provider->detail(17, $snapshot)['booking_state'] === 'accepted', 'disabling new pickup preserves original order queries');
    check($provider->cancel(17, $snapshot)['booking_state'] === 'cancelled', 'only verified matching cancellation enables cancelled outcome');
    check(end($requests)[0] === SfClient::CANCEL_ORDER && (int)end($requests)[1]['dealType'] === 2, 'official cancellation call uses dealType two');
    reject(static fn() => $provider->create(17, array_replace($input, $snapshot)), 'rejected', 'new pickup cannot run while disabled');
    check((new Provider(null, $transport))->healthCheck(18) === false, 'tenant without logistics entitlement cannot use shared provider');
    $registered = (new Registration())->handle();
    check($registered['providers']['sf_direct'] === Provider::class, 'optional plugin registers dedicated pickup adapter');
    $registry = new \addon\hsx_recycle\app\service\core\express\ExpressProviderRegistry();
    check($registry->resolve(17, 'sf_direct') instanceof Provider, 'existing provider registry resolves optional SF adapter');
    check(isset($registry->all()['yisu'], $registry->all()['kuaidi100']), 'new adapter does not replace existing courier channels');
    check(($registry->queryRequirements()['sf_direct'] ?? null) === ['provider_order_id'], 'scheduler discovers SF requirements through provider event');
    $before = count($requests);
    reject(static fn() => $provider->waybill(17, []), 'rejected', 'pickup adapter never prints electronic labels');
    reject(static fn() => $provider->quote(17, []), 'rejected', 'unsupported quote not presented as zero freight');
    check(count($requests) === $before, 'unsupported operations cannot accidentally call external endpoints');
    echo "PASS {$checks} SF pickup bridge checks (real shared protocol; mock tenant/config/transport; no external IO)\n";
}
