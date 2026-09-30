<?php
declare(strict_types=1);
/** 无数据库、无外部网络、无真实取号/扣费/打印。 */
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $data = [];
        public function getConfigValue($site, $key) { return self::$data[$site][$key] ?? []; }
        public function setConfig($site, $key, $value) { self::$data[$site][$key] = $value; }
    }
}
namespace {
    function env($key, $default = null) { return $key === 'app.auth_key' ? 'test-only-not-a-production-secret' : $default; }
    $root = dirname(__DIR__);
    foreach (['support/Cipher', 'service/core/ProviderException', 'service/core/Kuaidi100Client', 'service/core/WaybillCarrierCatalog', 'service/core/ConfigService', 'service/core/WaybillProtocol', 'service/core/TaskRepository', 'service/core/LogisticsService'] as $file) require $root . '/app/' . $file . '.php';
    use addon\hsx_express\app\support\Cipher;
    use addon\hsx_express\app\service\core\ConfigService;
    use addon\hsx_express\app\service\core\Kuaidi100Client;
    use addon\hsx_express\app\service\core\WaybillProtocol;
    use addon\hsx_express\app\service\core\ProviderException;
    use addon\hsx_express\app\service\core\TaskRepository;
    use addon\hsx_express\app\service\core\LogisticsService;
    use addon\hsx_express\app\service\core\WaybillCarrierCatalog;
    class MemoryTasks extends TaskRepository {
        public array $rows = [];
        public function find(int $siteId, int $id): array { return isset($this->rows[$id]) && $this->rows[$id]['site_id'] === $siteId ? $this->rows[$id] : []; }
        public function findBusiness(int $siteId, string $type, string $id): array {
            foreach (array_reverse($this->rows) as $row) if ($row['site_id'] === $siteId && $row['business_type'] === $type && $row['business_id'] === $id) return $row;
            return [];
        }
        public function findNumber(int $siteId, string $number): array {
            foreach ($this->rows as $row) if ($row['site_id'] === $siteId && $row['task_no'] === $number) return $row;
            return [];
        }
        public function activeForOrder(int $siteId, string $type, int $orderId): array {
            return array_values(array_filter($this->rows, static fn($r) => $r['site_id'] === $siteId && $r['business_type'] === $type && $r['business_order_id'] === $orderId && !in_array($r['state'], ['failed', 'cancelled'], true)));
        }
        public function create(array $data): array {
            $id = count($this->rows) + 1;
            return $this->rows[$id] = $data + ['id' => $id, 'provider_task_id' => '', 'waybill_no' => '', 'provider_order_no' => '', 'reprint_count' => 0, 'label' => '', 'last_code' => ''];
        }
        public function update(array $task, array $changes, string $operation = ''): array {
            $changes['update_at'] = time();
            return $this->rows[$task['id']] = array_replace($task, $changes);
        }
    }
    $checks = 0;
    $ok = function ($passed, $message) use (&$checks) { if (!$passed) throw new \RuntimeException('FAIL: ' . $message); ++$checks; };
    $throws = function (callable $fn, string $message) use ($ok) { try { $fn(); } catch (\Throwable $e) { $ok(true, $message); return; } $ok(false, $message); };
    $cfg = array_replace(ConfigService::defaults(), ['enabled' => 1, 'carrier' => 'shunfeng', 'exp_type' => '顺丰标快', 'key' => 'test-key', 'secret' => 'test-secret', 'partner_id' => 'monthly-account', 'partner_key' => 'customer-code', 'partner_secret' => 'verify-secret', 'template_id' => 'v2-template', 'use_ack' => 1, 'callback_base_url' => 'https://example.com']);
    $payload = ['business_type' => 'phone_shop', 'business_id' => '101:stable', 'order_no' => 'SALE-TEST-101', 'order_id' => 101, 'order_goods_ids' => [2, 1],
        'sender' => ['name' => '发件测试', 'mobile' => '13800000000', 'address' => '河北省测试市测试区测试路1号'],
        'receiver' => ['name' => '收件测试', 'mobile' => '13900000000', 'address' => '河北省测试市测试区测试路2号'], 'cargo' => '手机', 'weight' => 0.5, 'count' => 1];
    $configService = new ConfigService();
    $saved = $configService->save(100005, $cfg);
    $ok($saved['secret'] === '******' && $saved['has_secret'], 'credentials masked');
    $ok(strpos(json_encode(\app\service\core\sys\CoreConfigService::$data), 'test-secret') === false, 'credentials encrypted in config');
    $configService->save(100005, ['secret' => '', 'key' => '******']);
    $ok($configService->get(100005)['secret'] === 'test-secret', 'empty/mask retains existing credentials');
    $ok(ConfigService::readiness($cfg)['ready'] && !ConfigService::readiness($cfg)['external_verified'], 'local check never claims external verified');
    $throws(fn() => $configService->save(100005, ['secret' => [], 'enabled' => 0]), 'invalid config types rejected');
    $configService->save(100005, ['enabled' => 0, 'clear_secrets' => ['secret']]);
    $ok(!$configService->get(100005, true)['has_secret'], 'explicit clear supported');
    $ok(ConfigService::validBaseUrl('https://example.com') && !ConfigService::validBaseUrl('http://example.com') && !ConfigService::validBaseUrl('https://example.com/path') && !ConfigService::validBaseUrl('https://a:b@example.com') && !ConfigService::validBaseUrl('https://127.0.0.1'), 'callback HTTPS public root restriction');
    $encrypted = Cipher::encrypt('sensitive-address');
    $ok(Cipher::decrypt($encrypted) === 'sensitive-address' && $encrypted !== 'sensitive-address', 'cipher round trip');
    $throws(fn() => Cipher::decrypt(substr($encrypted, 2)), 'tampered cipher fails closed');
    $normalized = WaybillProtocol::normalizePayload($payload);
    $ok($normalized['order_goods_ids'] === [1, 2], 'stable sorted package');
    $param = WaybillProtocol::create($cfg, $normalized, 'task123', 'salt123', 100005);
    $ok($param['partnerSecret'] === 'verify-secret' && $param['partnerSecret'] !== $param['partnerId'], 'partner secret not confused with monthly account');
    $ok($param['printType'] === 'IMAGE' && $param['code'] === 'sf_secret', 'web IMAGE supports official reprint; SF auth code included');
    $ok($param['reorder'] === false, 'provider-side duplicate order protection explicitly enabled');
    $throws(fn() => WaybillProtocol::normalizePayload(array_replace($payload, ['weight' => 0])), 'reject zero weight');
    $throws(fn() => WaybillProtocol::normalizePayload(array_replace($payload, ['count' => 2])), 'no unsupported multi-child parcel');
    $ok(WaybillProtocol::labels('https://api.kuaidi100.com/label/1,javascript:alert(1),https://evil.com/x,http://ckd.im/abc') === ['https://api.kuaidi100.com/label/1', 'http://ckd.im/abc'], 'only trusted short links, never raw HTML');
    $calls = [];
    $response = ['success' => true, 'code' => 200, 'data' => ['taskId' => 'provider-1', 'kuaidinum' => 'SF100', 'label' => 'https://api.kuaidi100.com/label/1', 'kdComOrderNum' => 'original-carrier-order']];
    $transport = function ($url, $form) use (&$calls, &$response, $ok) {
        $ok($form['sign'] === strtoupper(md5($form['param'] . $form['t'] . $form['key'] . 'test-secret')), 'official request signature');
        $calls[] = ['url' => $url, 'form' => $form];
        if ($response instanceof \Throwable) throw $response;
        return $response;
    };
    $repo = new MemoryTasks(); $locks = [];
    $lock = function ($site, $key, $fn) use (&$locks) { $locks[] = [$site, $key]; return $fn(); };
    $blocked = false;
    $service = new LogisticsService(new Kuaidi100Client($transport), $repo, function () use (&$cfg) { return $cfg; }, $lock,
        function ($data) use (&$blocked) { if ($blocked) throw new \RuntimeException('already dispatched'); return [true]; });
    $created = $service->execute(100005, 'create', $payload);
    $ok($created['state'] === 'ready' && $created['success'] && $created['can_reprint'], 'web create has printable result');
    $ok($created['business_no'] === 'SALE-TEST-101', 'human business number retained separately from idempotency key');
    $ok(count($calls) === 1 && count($repo->rows) === 1, 'one create request');
    $ok(!isset($created['snapshot_cipher']) && strpos(json_encode($created), 'test-secret') === false, 'task public view no credentials');
    $ok($locks[0] === [100005, 'phone_shop:order:101'], 'all packages for same order share lock');
    $service->execute(100005, 'create', $payload);
    $ok(count($calls) === 1, 'same business id idempotent');
    $throws(fn() => $service->execute(100005, 'create', array_replace($payload, ['business_id' => '101:other', 'order_goods_ids' => [2]])), 'overlapping goods prevent second waybill');
    $ok($service->execute(100024, 'query', ['task_id' => $created['task_id']]) === [], 'cross-tenant task inaccessible');
    $changed = $payload; $changed['receiver']['address'] .= '新地址';
    $reused = $service->execute(100005, 'create', $changed);
    $ok(count($calls) === 1 && strpos($reused['message'], '原地址') !== false, 'changed address never silently resubmits');
    $response = ['success' => true, 'code' => 200, 'data' => 'https://api.kuaidi100.com/label/new'];
    $cfg['key'] = 'new-key'; $cfg['secret'] = 'new-secret';
    $reprint = $service->execute(100005, 'reprint', ['task_id' => $created['task_id']]);
    $ok(end($calls)['form']['key'] === 'test-key', 'reprint uses original account snapshot');
    $ok(json_decode(end($calls)['form']['param'], true)['taskId'] === 'provider-1' && $reprint['state'] === 'ready', 'reprint original task, no new order');
    $blocked = true;
    $before = count($calls);
    $throws(fn() => $service->execute(100005, 'cancel', ['task_id' => $created['task_id'], 'reason' => '不寄了']), 'business guard blocks cancellation after delivery');
    $ok(count($calls) === $before, 'guard blocks before external call');
    $missingGuardService = new LogisticsService(new Kuaidi100Client($transport), $repo, fn() => $cfg, $lock, fn() => []);
    $throws(fn() => $missingGuardService->execute(100005, 'cancel', ['task_id' => $created['task_id'], 'reason' => 'test']), 'missing business guard fails closed');
    $blocked = false; $response = ['success' => true, 'code' => 200];
    $cancelled = $service->execute(100005, 'cancel', ['task_id' => $created['task_id'], 'reason' => '改地址']);
    $ok($cancelled['state'] === 'cancelled' && !$cancelled['can_reprint'], 'confirmed cancellation terminal');
    $ok(json_decode(end($calls)['form']['param'], true)['orderId'] === 'original-carrier-order', 'cancel includes carrier original order');
    $cancelParam = json_decode(end($calls)['form']['param'], true);
    $ok($cancelParam['code'] === 'sf_secret' && $cancelParam['partnerSecret'] === 'verify-secret' && $cancelParam['partnerKey'] === 'customer-code', 'cancel retains full original carrier credentials');
    $before = count($calls); $service->execute(100005, 'cancel', ['task_id' => $created['task_id']]);
    $ok(count($calls) === $before, 'cancel duplicate idempotent');
    $cfg['key'] = 'test-key'; $cfg['secret'] = 'test-secret';
    $response = ['success' => true, 'code' => 200, 'data' => ['taskId' => 'provider-2', 'kuaidinum' => 'SF200']];
    $next = $service->execute(100005, 'create', $changed);
    $ok($next['task_id'] !== $created['task_id'] && $next['attempt'] === 2 && count($repo->rows) === 2, 'cancelled parcel may create new attempt, original retained');
    $response = new \RuntimeException('raw request including test-secret');
    $unknownPayload = array_replace($payload, ['business_id' => '102:timeout', 'order_id' => 102]);
    $unknown = $service->execute(100005, 'create', $unknownPayload);
    $ok($unknown['state'] === 'unknown' && !$unknown['success'] && !$unknown['can_cancel'], 'timeout unknown not rejected');
    $before = count($calls); $service->execute(100005, 'create', $unknownPayload);
    $ok(count($calls) === $before, 'unknown never retried');
    $ok(strpos($unknown['message'], 'test-secret') === false, 'transport errors do not expose request secrets');
    $throws(fn() => $service->execute(100005, 'recover', ['task_id' => $unknown['task_id']]), 'recovery requires explicit consent for possible first charge');
    $blocked = true;
    $throws(fn() => $service->execute(100005, 'recover', ['task_id' => $unknown['task_id'], 'confirm' => 1]), 'recovery blocked if original business no longer permits shipment');
    $blocked = false;
    $cfg['key'] = 'new-key'; $cfg['secret'] = 'new-secret';
    $response = ['success' => true, 'code' => 30011, 'data' => ['taskId' => 'provider-recovered', 'kuaidinum' => 'SFRECOVER', 'label' => 'https://api.kuaidi100.com/label/recovered']];
    $rowCount = count($repo->rows);
    $recovered = $service->execute(100005, 'recover', ['task_id' => $unknown['task_id'], 'confirm' => 1]);
    $ok($recovered['task_id'] === $unknown['task_id'] && $recovered['state'] === 'ready' && count($repo->rows) === $rowCount, '30011 recovery updates original attempt, no new local order');
    $recoverForm = end($calls)['form']; $recoverParam = json_decode($recoverForm['param'], true);
    $ok($recoverForm['key'] === 'test-key' && $recoverParam['orderId'] === $unknown['task_no'] && $recoverParam['reorder'] === false, 'recovery uses exact original account and no-reorder id');
    $throws(fn() => $service->execute(100005, 'recover', ['task_id' => $unknown['task_id'], 'confirm' => 1]), 'already resolved task not eligible for recovery');
    $cfg['key'] = 'test-key'; $cfg['secret'] = 'test-secret';
    $expired = array_replace($repo->rows[$unknown['task_id']], ['state' => 'unknown', 'submitted_at' => time() - 169201]);
    $ok(!LogisticsService::view($expired)['can_recover'], '47-hour safety window prevents resubmission after provider dedup expiry');
    $cfg['scene'] = 'waybill_cloud'; $cfg['device_id'] = 'DEVICE1';
    $response = ['success' => true, 'code' => 200, 'data' => ['taskId' => 'provider-cloud', 'kuaidinum' => 'SF300']];
    $cloud = $service->execute(100005, 'create', array_replace($payload, ['business_id' => '103:cloud', 'order_id' => 103]));
    $ok($cloud['state'] === 'print_pending', 'cloud accepted not printed');
    $ok(!$cloud['can_reprint'], 'new pending print cannot be immediately repeated');
    $repo->rows[$cloud['task_id']]['update_at'] = time() - 91;
    $pending = $service->execute(100005, 'query', ['task_id' => $cloud['task_id']]);
    $ok($pending['can_reprint'], 'pending over 90 seconds allows explicit original-task reprint');
    $throws(fn() => $service->execute(100005, 'reprint', ['task_id' => $cloud['task_id']]), 'pending reprint requires physical output confirmation');
    $response = ['success' => true, 'code' => 200];
    $pendingAgain = $service->execute(100005, 'reprint', ['task_id' => $cloud['task_id'], 'confirm' => 1]);
    $ok($pendingAgain['state'] === 'print_pending' && !$pendingAgain['can_reprint'], 'explicit pending reprint resets cooldown without reordering');
    $raw = $repo->rows[$cloud['task_id']]; $snapshot = json_decode(Cipher::decrypt($raw['snapshot_cipher']), true);
    $callbackParam = '{"status":"200","message":"ok"}';
    $callback = ['taskId' => 'provider-cloud', 'param' => $callbackParam, 'sign' => md5($callbackParam . $snapshot['salt']), 'pushType' => 'printStatus'];
    $throws(fn() => $service->callback(100005, $cloud['task_no'], array_replace($callback, ['sign' => str_repeat('0', 32)])), 'forged callback rejected');
    $throws(fn() => $service->callback(100024, $cloud['task_no'], $callback), 'callback tenant isolation');
    $ack = $service->callback(100005, $cloud['task_no'], $callback);
    $ok($ack['result'] && $ack['returnCode'] === '200' && $repo->rows[$cloud['task_id']]['state'] === 'printed', 'signed callback marks physical provider print');
    $service->callback(100005, $cloud['task_no'], $callback);
    $ok($repo->rows[$cloud['task_id']]['state'] === 'printed', 'duplicate callback harmless');
    $callback['param'] = '{"status":"201"}'; $callback['sign'] = md5($callback['param'] . $snapshot['salt']);
    $service->callback(100005, $cloud['task_no'], $callback);
    $ok($repo->rows[$cloud['task_id']]['state'] === 'printed', 'late failure cannot downgrade printed');
    $raw['submitted_at'] = time() - 172801;
    $ok(!LogisticsService::view($raw)['can_reprint'], '2-day reprint limit');
    $raw['submitted_at'] = time(); $raw['state'] = 'printed'; $raw['reprint_count'] = 10;
    $ok(!LogisticsService::view($raw)['can_reprint'], '10 reprint cap');
    $response = ['success' => false, 'code' => 30001, 'message' => 'bad parameter'];
    $rejectedPayload = array_replace($payload, ['business_id' => '104:reject', 'order_id' => 104]);
    $failed = $service->execute(100005, 'create', $rejectedPayload);
    $ok($failed['state'] === 'failed', 'known parameter rejection safe to fix');
    $response = ['success' => true, 'code' => 200, 'data' => ['taskId' => 'provider-fixed', 'kuaidinum' => 'SF400']];
    $fixed = $service->execute(100005, 'create', $rejectedPayload);
    $ok($fixed['attempt'] === 2 && $fixed['task_id'] !== $failed['task_id'], 'safe failure retry retains historical attempt');
    $ok(!WaybillProtocol::safeCreateFailure(['success' => false, 'code' => 30010]), 'printer failure cannot prove no waybill');
    $response = new \RuntimeException('timeout');
    $unresolvedPayload = array_replace($payload, ['business_id' => '105:unclear', 'order_id' => 105]);
    $unresolved = $service->execute(100005, 'create', $unresolvedPayload);
    $response = ['success' => false, 'code' => 30001, 'message' => 'recovery now rejected'];
    $stillUnknown = $service->execute(100005, 'recover', ['task_id' => $unresolved['task_id'], 'confirm' => 1]);
    $ok($stillUnknown['state'] === 'unknown', 'recovery rejection does not prove original request failed');
    $client = new Kuaidi100Client(fn() => []);
    $throws(fn() => $client->request('https://evil.com', 'order', [], $cfg), 'outbound endpoint allowlist');
    $ok(strpos(file_get_contents($root . '/app/adminapi/route/route.php'), 'AdminLog::class') === false, 'no plaintext admin request logging');
    $ok(ConfigService::defaults()['carrier'] === '', 'new site is never forced to use SF');
    $catalog = WaybillCarrierCatalog::options();
    $ok(count($catalog) === 72 && count(array_unique(array_column($catalog, 'value'))) === count($catalog), 'official domestic directory complete and unique');
    foreach ($catalog as $carrier) {
        $ok(count(array_diff(array_column($carrier['fields'], 'key'), WaybillCarrierCatalog::ACCOUNT_FIELDS)) === 0, 'catalog account fields use known protocol keys: ' . $carrier['value']);
        $ok($carrier['available'] === ($carrier['unavailable_reason'] === ''), 'unavailable carrier always explains why: ' . $carrier['value']);
    }
    $zto = array_replace(ConfigService::defaults(), ['enabled' => 1, 'carrier' => 'zhongtong', 'exp_type' => '标准快递', 'key' => 'test-key', 'secret' => 'test-secret',
        'partner_id' => 'zto-account', 'partner_key' => 'zto-password', 'net' => 'zto-station', 'template_id' => 'zto-template', 'use_ack' => 1]);
    $ok(ConfigService::readiness($zto)['ready'], 'ZTO complete without any SF credentials');
    $ztoChecks = ConfigService::readiness(array_replace($zto, ['net' => '']))['checks'];
    $missing = array_values(array_filter($ztoChecks, static fn($check) => !$check['passed']));
    $ok(count($missing) === 1 && $missing[0]['key'] === 'account_net' && str_contains($missing[0]['message'], '中通快递'), 'ZTO missing station gets specific actionable message');
    $ok(!ConfigService::readiness(array_replace($zto, ['exp_type' => '顺丰标快']))['ready'], 'ZTO cannot send SF product');
    $ok(!ConfigService::readiness(array_replace($zto, ['carrier' => 'invented']))['ready'], 'unknown company cannot enable');
    $ok(!ConfigService::readiness(array_replace($zto, ['carrier' => 'honghua']))['ready'], 'carrier without returned label cannot pretend printable');
    $ok(!ConfigService::readiness(array_replace($zto, ['carrier' => 'youzhengguonei', 'exp_type' => '邮政快递包裹', 'pay_type' => 'CONSIGNEE']))['ready'], 'unsupported COD freight blocked');
    $savedZto = $configService->save(100024, $zto + ['partner_secret' => '']);
    $ok($savedZto['readiness']['ready'] && $savedZto['code'] === 'ztoOpen' && !$savedZto['has_partner_secret'], 'ZTO save needs only own fields and auto-fills fixed code');
    $ztoParam = WaybillProtocol::create(array_replace($zto, ['partner_secret' => 'must-not-send']), $normalized, 'ZTO-TASK', 'salt', 100024);
    $ok($ztoParam['kuaidicom'] === 'zhongtong' && $ztoParam['partnerId'] === 'zto-account' && $ztoParam['partnerKey'] === 'zto-password' && $ztoParam['net'] === 'zto-station' && $ztoParam['code'] === 'ztoOpen', 'ZTO protocol sends account, password, station and fixed code');
    $ok(!isset($ztoParam['partnerSecret']) && !isset($ztoParam['partnerName']), 'ZTO payload never carries unrelated SF fields');
    $sf = array_replace($zto, ['carrier' => 'shunfeng', 'exp_type' => '顺丰标快', 'partner_id' => 'sf-account', 'partner_key' => 'sf-code', 'partner_secret' => 'sf-secret']);
    $configService->save(100026, $sf);
    $switched = $configService->save(100026, ['carrier' => 'zhongtong', 'enabled' => 0, 'code' => 'sf_secret']);
    $ok($switched['partner_id'] === '' && !$switched['has_partner_key'] && !$switched['has_partner_secret'] && $switched['template_id'] === '' && $switched['exp_type'] === '' && $switched['use_ack'] === 0, 'server clears company-scoped fields when carrier changes');
    $ok($switched['has_key'] && $switched['has_secret'] && $switched['code'] === 'ztoOpen', 'switch retains shared API credentials, overrides untrusted fixed code');
    $throws(fn() => $configService->save(100026, ['enabled' => 1]), 'new carrier cannot enable with retained previous account');
    $configService->save(100026, $zto);
    $retainedZto = $configService->save(100026, ['partner_key' => '']);
    $ok($retainedZto['has_partner_key'], 'same-carrier blank secret retains only same-carrier password');
    $configService->save(100026, ['carrier' => 'jd', 'enabled' => 0, 'partner_key' => '******']);
    $ok(!$configService->get(100026, true)['has_partner_key'], 'switching carrier never reuses masked old carrier password');

    $ztoRepo = new MemoryTasks(); $ztoCalls = [];
    $ztoClient = new Kuaidi100Client(function ($url, $form) use (&$ztoCalls) {
        $ztoCalls[] = $form;
        return $form['method'] === 'cancel' ? ['success' => true, 'code' => 200] : ['success' => true, 'code' => 200, 'data' => ['taskId' => 'zto-provider', 'kuaidinum' => 'ZTO-TEST', 'label' => 'https://api.kuaidi100.com/label/zto', 'kdComOrderNum' => 'zto-original-order']];
    });
    $currentZto = $zto;
    $ztoService = new LogisticsService($ztoClient, $ztoRepo, function () use (&$currentZto) { return $currentZto; }, $lock, fn() => [true]);
    $ztoTask = $ztoService->execute(100024, 'create', $payload);
    $ztoService->execute(100024, 'create', $payload);
    $ok(count($ztoCalls) === 1 && $ztoTask['state'] === 'ready' && $ztoTask['carrier_name'] === '中通快递', 'ZTO end-to-end mock create and repeated click reuse one task');
    $currentZto = $sf;
    $ztoCancelled = $ztoService->execute(100024, 'cancel', ['task_id' => $ztoTask['task_id'], 'reason' => '未交件，测试取消']);
    $ztoCancelParam = json_decode(end($ztoCalls)['param'], true);
    $ok($ztoCancelled['state'] === 'cancelled' && $ztoCancelParam['kuaidicom'] === 'zhongtong' && $ztoCancelParam['code'] === 'ztoOpen' && $ztoCancelParam['net'] === 'zto-station' && $ztoCancelParam['partnerId'] === 'zto-account', 'old ZTO task cancellation keeps original account and station after switching to SF');
    $raw['carrier'] = 'yuantong'; $raw['state'] = 'ready';
    $ok(!LogisticsService::view($raw)['can_cancel'] && LogisticsService::view($raw)['cancel_unavailable_reason'] !== '', 'carrier without API cancellation gives manual handling reason');
    echo 'PASS ' . $checks . " backend checks; no database writes, no live carrier calls.\n";
}
