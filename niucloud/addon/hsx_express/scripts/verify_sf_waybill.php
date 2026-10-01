<?php
declare(strict_types=1);
/** Offline SF lifecycle tests: real protocol/services, in-memory persistence, mocked HTTP only. */
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public function getConfigValue($site, $key): array { return []; }
    }
}
namespace {
    function env($key, $default = null) { return $key === 'app.auth_key' ? 'sf-waybill-test-only-key' : $default; }
    foreach (['support/Cipher', 'service/core/ProviderException', 'service/core/WaybillCarrierCatalog', 'service/core/ConfigService',
        'service/core/WaybillProtocol', 'service/core/TaskRepository', 'service/core/LogisticsService', 'service/core/SfConfigService',
        'service/core/SfClient', 'service/core/SfProtocol', 'service/core/SfPdfDownloader', 'service/core/SfWaybillService', 'service/core/WaybillTaskDispatcher'] as $file) require dirname(__DIR__) . '/app/' . $file . '.php';
    use addon\hsx_express\app\service\core\{TaskRepository, SfConfigService, SfClient, SfProtocol, SfWaybillService, SfPdfDownloader, WaybillTaskDispatcher, ProviderException};
    use addon\hsx_express\app\support\Cipher;
    class SfMemoryTasks extends TaskRepository {
        public array $rows = [];
        public array $operations = [];
        public bool $failCreate = false;
        public function find(int $siteId, int $id): array { return ($this->rows[$id]['site_id'] ?? 0) === $siteId ? $this->rows[$id] : []; }
        public function findBusiness(int $siteId, string $type, string $id): array {
            foreach (array_reverse($this->rows) as $row) if ($row['site_id'] === $siteId && $row['business_type'] === $type && $row['business_id'] === $id) return $row;
            return [];
        }
        public function findWaybill(int $siteId, string $number): array { return array_values(array_filter($this->rows, fn($r) => $r['site_id'] === $siteId && $r['waybill_no'] === $number)); }
        public function activeForOrder(int $siteId, string $type, int $orderId): array {
            return array_values(array_filter($this->rows, fn($r) => $r['site_id'] === $siteId && $r['business_type'] === $type && $r['business_order_id'] === $orderId && !in_array($r['state'], ['failed','cancelled'], true)));
        }
        public function create(array $data): array {
            if ($this->failCreate) throw new \core\exception\CommonException('模拟存储不可用');
            $id = count($this->rows) + 1;
            return $this->rows[$id] = $data + ['id' => $id, 'provider_order_no' => '', 'provider_task_id' => '', 'waybill_no' => '', 'reprint_count' => 0];
        }
        public function update(array $task, array $changes, string $operation = ''): array {
            $this->operations[] = [$task['id'], $operation, $changes];
            return $this->rows[$task['id']] = array_replace($task, $changes, ['update_at' => time()]);
        }
    }
    $checks = 0;
    function check($value, string $message): void { global $checks; if (!$value) throw new \RuntimeException($message); $checks++; }
    function rejects(callable $callback, string $message): void { try { $callback(); } catch (\Throwable $e) { check(str_contains($e->getMessage(), $message), 'Unexpected refusal: ' . $e->getMessage()); return; } throw new \RuntimeException('Expected rejection: ' . $message); }
    function orderResult(string $orderNo, string $waybill = 'SF1234567890'): array {
        return ['success' => true, 'errorCode' => 'S0000', '_response_id' => 'fixture-response',
            'msgData' => ['orderId' => $orderNo, 'filterResult' => 2, 'waybillNoInfoList' => [['waybillType' => 1, 'waybillNo' => $waybill]]]];
    }
    function payload(int $id): array {
        return ['business_type' => 'phone_shop', 'business_id' => $id . ':package', 'order_id' => $id, 'business_no' => 'TEST-' . $id, 'order_goods_ids' => [$id],
            'sender' => ['name' => '寄件测试', 'mobile' => '13800000000', 'address' => '广东省深圳市测试区测试路1号'],
            'receiver' => ['name' => '收件测试', 'mobile' => '13900000000', 'address' => '广东省深圳市测试区测试路2号'], 'cargo' => '手机', 'weight' => 0.5, 'count' => 1];
    }
    $repo = new SfMemoryTasks();
    $config = array_replace(SfConfigService::defaults(), ['enabled' => 1, 'environment' => 'production', 'client_code' => 'fixture-client',
        'check_word' => 'fixture-secret', 'product_code' => '2', 'template_code' => 'fixture-template', 'use_ack' => 1]);
    $originalConfig = $config;
    $remote = []; $downloadCalls = []; $locks = []; $createMode = 'success'; $pdfMode = 'success'; $searchMode = 'success'; $cancelMode = 'success'; $guardAllowed = true;
    $url = 'https://' . SfPdfDownloader::HOST . '/fixture.pdf';
    $requester = function ($service, $data, $cfg) use (&$remote, &$createMode, &$pdfMode, &$searchMode, &$cancelMode, $repo, $url) {
        $remote[] = compact('service', 'data', 'cfg');
        if ($service === SfClient::CREATE_ORDER) {
            $rows = array_values(array_filter($repo->rows, fn($r) => $r['task_no'] === $data['orderId']));
            check(count($rows) === 1 && $rows[0]['state'] === 'creating', 'Task is durable before create request');
            check($data['isDocall'] === 0, 'Waybill scene never requests a pickup');
            if ($createMode === 'unknown') throw new ProviderException('模拟网络超时');
            if ($createMode === 'ip_rejected') throw new ProviderException('顺丰拒绝请求（A1003）：请求IP不在顺丰白名单内', false);
            if ($createMode === 'gateway') throw new ProviderException('权限未开通 A1004 fixture-secret', false);
            if ($createMode === 'reject') return ['success' => false, 'errorCode' => '1016', 'errorMsg' => 'untrusted fixture-secret'];
            if ($createMode === 'ambiguous') return ['success' => false, 'errorCode' => '8016'];
            return orderResult($createMode === 'missing_id' ? '' : ($createMode === 'wrong_id' ? 'OTHER' : $data['orderId']));
        }
        if ($service === SfClient::SEARCH_ORDER) {
            if ($searchMode === 'not_found') return ['success' => false, 'errorCode' => '8018'];
            if ($searchMode === 'error') throw new ProviderException('查询权限未开通 A1004', false);
            return orderResult($data['orderId'], $searchMode === 'conflict' ? 'SF9999999999' : 'SF1234567890');
        }
        if ($service === SfClient::PRINT_PDF) {
            $number = $data['documents'][0]['masterWaybillNo'];
            check((bool)array_filter($repo->rows, fn($r) => $r['waybill_no'] === $number && $r['provider_order_no'] !== '' && $r['state'] === 'print_pending'), 'Waybill is saved before requesting PDF');
            if ($pdfMode === 'error') throw new ProviderException('PDF权限未开通 A1004', false);
            return ['success' => true, 'obj' => ['fileType' => 'pdf', 'files' => [['waybillNo' => $pdfMode === 'wrong' ? 'SF9999999999' : $number, 'url' => $url, 'token' => 'fixture-download-token']]]];
        }
        if ($service === SfClient::CANCEL_ORDER) {
            if ($cancelMode === 'gateway') throw new ProviderException('取消权限未开通 A1004 fixture-secret', false);
            if ($cancelMode === 'error') throw new ProviderException('取消请求超时');
            if ($cancelMode === 'ambiguous') return ['success' => false, 'errorCode' => '8019'];
            if ($cancelMode === 'already_cancelled') return ['success' => false, 'errorCode' => '8037'];
            return ['success' => true, 'errorCode' => 'S0000', 'msgData' => ['orderId' => $cancelMode === 'wrong' ? 'OTHER' : $data['orderId'], 'resStatus' => 2]];
        }
        throw new \RuntimeException('Unexpected remote operation');
    };
    $downloader = new SfPdfDownloader(function ($target, $headers) use (&$downloadCalls) {
        $downloadCalls[] = [$target, $headers];
        return ['status' => 200, 'content_type' => 'application/pdf', 'body' => '%PDF-1.4 fixture-only'];
    });
    $service = new SfWaybillService($repo, $requester, function ($site) use (&$config) { return $config; },
        function ($site, $key, $fn) use (&$locks) { $locks[] = [$site, $key]; return $fn(); },
        function ($data) use (&$guardAllowed) { return [$guardAllowed]; }, $downloader);
    $task = $service->execute(1, 'create', payload(10));
    check($task['state'] === 'ready' && $task['can_download'] && $task['can_confirm_delivery'], 'Production waybill and PDF ready are separate from shipment');
    check($task['provider_key'] === 'hsx_express_sf_direct' && $task['print_type'] === 'PDF' && $task['environment'] === 'production', 'Public task identifies original provider and environment');
    check($task['exp_type_name'] === '顺丰标快', 'Public view uses readable original product name');
    check($task['labels'] === [] && $task['label'] === '' && $task['pdf_download_path'] === 'hsx_express/tasks/1/pdf', 'Browser receives only authenticated task download path');
    check(!str_contains(json_encode($task), 'fixture-secret') && !str_contains(json_encode($task), 'fixture-download-token') && !str_contains(json_encode($task), $url), 'Public responses never contain credentials or remote URL');
    check(!str_contains(json_encode($repo->rows), 'fixture-secret') && !str_contains(json_encode($repo->rows), 'fixture-download-token'), 'Sensitive snapshot and PDF tokens are encrypted at rest');
    check($locks[0] === [1, 'phone_shop:order:10'], 'Same site and order mutex as commerce delivery');
    $before = count($remote); $repeat = $service->execute(1, 'create', payload(10)); $service->execute(1, 'query', ['task_id' => 1]);
    check($repeat['task_id'] === 1 && count($remote) === $before, 'Reopen, double click and local query cannot create another order');
    rejects(fn() => $service->execute(2, 'reprint', ['task_id' => 1]), '不属于本站');
    rejects(fn() => $service->downloadPdf(2, 1), '不属于本站');
    $file = $service->downloadPdf(1, 1);
    check(str_starts_with($file['body'], '%PDF-') && $file['filename'] === 'sf-waybill-1.pdf', 'PDF download uses fixed safe filename');
    check($downloadCalls[0][1]['X-Auth-token'] === 'fixture-download-token' && $repo->rows[1]['state'] === 'ready', 'Download sends secret server-side and never claims printed');
    $config['client_code'] = 'changed-account'; $config['check_word'] = 'changed-secret'; $config['environment'] = 'sandbox'; $config['template_code'] = 'changed-template';
    $service->execute(1, 'reprint', ['task_id' => 1]);
    check(end($remote)['cfg']['client_code'] === 'fixture-client' && end($remote)['data']['templateCode'] === 'fixture-template', 'Existing task retains original encrypted credentials and template after site change');
    $sandbox = $service->execute(1, 'create', payload(11));
    check($sandbox['environment'] === 'sandbox' && !$sandbox['can_confirm_delivery'], 'Sandbox never becomes delivery eligible');
    rejects(fn() => $service->assertDeliveryAllowed(1, ['express_number' => $sandbox['waybill_no']]), '不能通过手工');
    $service->assertDeliveryAllowed(2, ['express_number' => $sandbox['waybill_no']]);
    $service->assertDeliveryAllowed(1, ['express_number' => 'UNRELATED']);
    check(true, 'Known sandbox manual guard is tenant scoped and leaves unrelated manual numbers alone');
    $config = $originalConfig; $pdfMode = 'error';
    $pdfFailed = $service->execute(1, 'create', payload(12));
    check($pdfFailed['state'] === 'print_failed' && $pdfFailed['waybill_no'] !== '' && $pdfFailed['can_reprint'], 'PDF failure preserves allocated waybill as non-reorderable');
    check(str_contains($pdfFailed['message'], 'A1004'), 'Safe concrete PDF rejection is visible');
    $before = count($remote); $service->execute(1, 'create', payload(12));
    check(count($remote) === $before, 'PDF failure cannot create a second order');
    $config['template_code'] = 'corrected-template'; $config['check_word'] = 'rotated-secret';
    $pdfMode = 'success'; $corrected = $service->execute(1, 'reprint', ['task_id' => $pdfFailed['task_id']]);
    check(end($remote)['service'] === SfClient::PRINT_PDF && count($remote) === $before + 1, 'Reprint only fetches original PDF');
    check($corrected['state'] === 'ready' && end($remote)['data']['templateCode'] === 'corrected-template' && str_contains($corrected['message'], '当前同账号模板'), 'Fixing template configuration recovers original PDF without another allocation');
    check(end($remote)['cfg']['check_word'] === 'fixture-secret', 'Template update cannot replace historical account credentials');
    $snapshot = json_decode(Cipher::decrypt($repo->rows[$pdfFailed['task_id']]['snapshot_cipher']), true);
    check($snapshot['config']['template_code'] === 'corrected-template' && $snapshot['config']['check_word'] === 'fixture-secret', 'Only corrected template is saved into historical account snapshot');
    $config = $originalConfig;
    $pdfMode = 'wrong'; $wrongPdf = $service->execute(1, 'create', payload(14));
    check($wrongPdf['state'] === 'print_failed' && $wrongPdf['waybill_no'] === 'SF1234567890' && !$wrongPdf['can_download'], 'A PDF for another waybill is never exposed; original allocation remains');
    $pdfMode = 'success';
    $createMode = 'unknown'; $unknown = $service->execute(1, 'create', payload(13));
    check($unknown['state'] === 'unknown' && !$unknown['can_cancel'] && !$unknown['can_reprint'], 'Network ambiguity is held, not retried');
    check(str_contains($unknown['cancel_unavailable_reason'], '状态不明'), 'Unknown requests retain the original-order warning');
    $before = count($remote); $service->execute(1, 'query', ['task_id' => $unknown['task_id']]);
    check(count($remote) === $before, 'Query is always local, including unknown tasks');
    $searchMode = 'not_found'; $unknown = $service->execute(1, 'refresh', ['task_id' => $unknown['task_id']]);
    check($unknown['state'] === 'unknown' && end($remote)['service'] === SfClient::SEARCH_ORDER && str_contains($unknown['message'], '8018'), 'Not found is not proof of no allocation');
    $searchMode = 'success'; $before = count($remote); $found = $service->execute(1, 'refresh', ['task_id' => $unknown['task_id']]);
    check($found['state'] === 'print_failed' && $found['waybill_no'] !== '' && count($remote) === $before + 1 && end($remote)['service'] === SfClient::SEARCH_ORDER, 'Refresh restores original allocation without creating order or PDF');
    $searchMode = 'conflict'; $conflict = $service->execute(1, 'refresh', ['task_id' => $found['task_id']]);
    check($conflict['state'] === 'unknown' && $conflict['waybill_no'] === 'SF1234567890', 'Conflicting upstream waybill never overwrites original');
    $searchMode = 'success'; $service->execute(1, 'refresh', ['task_id' => $found['task_id']]);
    foreach (['missing_id', 'wrong_id', 'ambiguous'] as $mode) {
        $createMode = $mode; $bad = $service->execute(1, 'create', payload(count($repo->rows) + 30));
        check($bad['state'] === 'unknown', 'Missing identity, wrong identity or duplicate result must stay unknown: ' . $mode);
    }
    $createMode = 'reject'; $rejected = $service->execute(1, 'create', payload(50));
    check($rejected['state'] === 'failed' && str_contains($rejected['message'], '1016') && !str_contains($rejected['message'], 'fixture-secret'), 'Only known parameter refusal releases new attempt, with safe reason');
    $createMode = 'gateway'; $gateway = $service->execute(1, 'create', payload(51));
    check($gateway['state'] === 'failed' && str_contains($gateway['message'], 'A1004') && !str_contains($gateway['message'], 'fixture-secret'), 'Gateway definite refusal retains actionable sanitized explanation');
    $createMode = 'ip_rejected'; $ipRejected = $service->execute(1, 'create', payload(53));
    check($ipRejected['state'] === 'failed' && empty($ipRejected['waybill_no']) && !$ipRejected['can_cancel'] && !$ipRejected['can_confirm_delivery'], 'A1003 means no accepted waybill and cannot ship or cancel');
    check(str_contains($ipRejected['cancel_unavailable_reason'], '没有运单需要取消') && !str_contains($ipRejected['cancel_unavailable_reason'], '状态不明'), 'Definite rejection explains configuration correction, not ambiguous-result recovery');
    $before = count($remote); $service->execute(1, 'query', ['task_id' => $ipRejected['task_id']]);
    check(count($remote) === $before, 'Reading an IP-rejected task never retries production creation');
    $createMode = 'success'; $retried = $service->execute(1, 'create', payload(53));
    check($retried['state'] === 'ready' && $retried['task_id'] !== $ipRejected['task_id'] && $repo->rows[$ipRejected['task_id']]['state'] === 'failed', 'Explicit retry after definite rejection preserves old attempt and creates one new waybill');
    $createMode = 'success'; $before = count($remote); $repo->failCreate = true;
    rejects(fn() => $service->execute(1, 'create', payload(52)), '模拟存储');
    check(count($remote) === $before, 'Persistence failure means no courier call'); $repo->failCreate = false;
    $guardAllowed = false; $before = count($remote);
    rejects(fn() => $service->execute(1, 'cancel', ['task_id' => 1, 'reason' => '测试取消']), '业务取消许可');
    check(count($remote) === $before && $repo->rows[1]['state'] === 'ready', 'Business guard rejects before cancellation request/state transition');
    $guardAllowed = true; $cancelMode = 'gateway';
    $notCancelled = $service->execute(1, 'cancel', ['task_id' => 1, 'reason' => '测试取消']);
    check($notCancelled['state'] === 'ready' && $notCancelled['can_cancel'] && str_contains($notCancelled['message'], 'A1004')
        && !str_contains($notCancelled['message'], 'fixture-secret'), 'Definite cancel gateway refusal restores prior state and shows safe actionable reason');
    $cancelMode = 'wrong';
    $cancel = $service->execute(1, 'cancel', ['task_id' => 1, 'reason' => '测试取消']);
    check($cancel['state'] === 'cancel_unknown' && !$cancel['can_download'] && $cancel['can_cancel'], 'Wrong cancellation identity remains locked but permits guarded same-order cancellation retry');
    $before = count($remote); $service->execute(1, 'create', payload(10));
    check(count($remote) === $before, 'Cancellation unknown cannot release another allocation');
    $cancelMode = 'error';
    $cancel = $service->execute(1, 'cancel', ['task_id' => 1, 'reason' => '重试确认原单取消']);
    check($cancel['state'] === 'cancel_unknown', 'Cancellation timeout never unlocks allocation');
    $before = count($remote); $cancelMode = 'already_cancelled';
    $cancel = $service->execute(1, 'cancel', ['task_id' => 1, 'reason' => '重试确认原单取消']);
    check($cancel['state'] === 'cancelled' && count($remote) === $before + 1 && end($remote)['service'] === SfClient::CANCEL_ORDER
        && end($remote)['data']['orderId'] === $task['task_no'], 'Explicit already-cancelled reply confirms only the original cancellation context; no CREATE');
    $cancelMode = 'success'; $cancel = $service->execute(1, 'cancel', ['task_id' => $pdfFailed['task_id'], 'reason' => '测试取消']);
    check($cancel['state'] === 'cancelled' && !$cancel['can_confirm_delivery'], 'Only matching explicit cancellation can terminate task');
    $new = $service->execute(1, 'create', payload(12));
    check($new['task_no'] !== $pdfFailed['task_no'] && $new['attempt'] === 2, 'Recreate after confirmed cancellation uses new merchant order identity');
    $overlap = payload(12); $overlap['business_id'] = '12:other-package';
    rejects(fn() => $service->execute(1, 'create', $overlap), '跨渠道重复');
    $routed = [];
    $dispatcher = new WaybillTaskDispatcher($repo, function ($provider) use (&$routed) {
        return new class($provider, $routed) {
            private $calls;
            public function __construct(private string $provider, array &$calls) { $this->calls =& $calls; }
            public function execute($site, $operation, $payload): array { $this->calls[] = [$this->provider, $operation, $payload]; return ['provider' => $this->provider]; }
        };
    }, fn($site, $key, $fn) => $fn());
    $dispatcher->execute(1, 'reprint', ['task_id' => $new['task_id']], 'kuaidi100');
    check($routed[0][0] === 'sf_direct', 'Saved task provider wins over newly selected Kuaidi100');
    $legacy = $repo->create(array_replace($repo->rows[$new['task_id']], ['id' => count($repo->rows) + 1, 'provider' => 'kuaidi100', 'business_id' => '60:legacy', 'business_order_id' => 60]));
    $dispatcher->execute(1, 'cancel', ['task_id' => $legacy['id']], 'sf_direct');
    check(end($routed)[0] === 'kuaidi100', 'Saved Kuaidi100 task never goes to SF after channel switch');
    $before = count($routed); $dispatcher->execute(1, 'query', ['task_id' => $new['task_id']]);
    check(count($routed) === $before, 'Dispatcher query does not instantiate or execute provider');
    $before = count($routed); $reused = $dispatcher->execute(1, 'create', payload(12), 'kuaidi100');
    check($reused['provider_key'] === 'hsx_express_sf_direct' && count($routed) === $before, 'Cross-channel create reuses original task before invoking a provider');
    $guardRepo = new SfMemoryTasks();
    $guardRow = $repo->rows[$new['task_id']]; $guardRow['id'] = 1; $guardRow['waybill_no'] = 'SF-KNOWN-GUARD';
    $guardRepo->rows[1] = $guardRow;
    $manualGuard = new SfWaybillService($guardRepo, static function () { throw new \RuntimeException('No HTTP permitted'); });
    $manualPayload = ['express_number' => 'SF-KNOWN-GUARD', 'order_id' => 12, 'order_goods_ids' => [12]];
    $manualGuard->assertDeliveryAllowed(1, $manualPayload); check(true, 'Valid known production waybill may be entered manually for the original exact package');
    foreach (['cancelled', 'cancel_unknown', 'unknown'] as $state) {
        $guardRepo->rows[1]['state'] = $state;
        rejects(fn() => $manualGuard->assertDeliveryAllowed(1, $manualPayload), '不能通过手工');
    }
    $guardRepo->rows[1]['state'] = 'ready';
    rejects(fn() => $manualGuard->assertDeliveryAllowed(1, array_replace($manualPayload, ['order_id' => 99])), '不属于本次商城订单');
    rejects(fn() => $manualGuard->assertDeliveryAllowed(1, array_replace($manualPayload, ['order_goods_ids' => [12, 13]])), '商品范围');
    $guardRepo->rows[1]['state'] = 'cancelling'; $guardRepo->rows[1]['update_at'] = time();
    check(!SfWaybillService::view($guardRepo->rows[1])['can_cancel'], 'An in-flight cancellation cannot be immediately submitted again');
    $guardRepo->rows[1]['update_at'] = time() - 91;
    check(SfWaybillService::view($guardRepo->rows[1])['can_cancel'], 'A crashed stale cancellation can be checked by retrying only its original cancellation');
    $artifact = ['url' => $url, 'token' => 'download-token', 'expires_at' => time() + 60];
    $downloaded = 0;
    $safeDownloader = new SfPdfDownloader(function ($u, $h) use (&$downloaded) { $downloaded++; return ['status' => 200, 'content_type' => 'application/pdf', 'body' => '%PDF-1.7 fixture']; });
    foreach (['http://' . SfPdfDownloader::HOST . '/x', 'https://example.com/x', 'https://' . SfPdfDownloader::HOST . '.evil.test/x',
        'https://name:password@' . SfPdfDownloader::HOST . '/x', 'https://' . SfPdfDownloader::HOST . ':444/x', $url . '#secret'] as $unsafe) {
        rejects(fn() => $safeDownloader->download(array_replace($artifact, ['url' => $unsafe])), '地址或下载凭据');
    }
    rejects(fn() => $safeDownloader->download(array_replace($artifact, ['token' => "token\r\nX: bad"])), '地址或下载凭据');
    rejects(fn() => $safeDownloader->download(array_replace($artifact, ['expires_at' => time() - 1])), '已过期');
    check($downloaded === 0, 'Unsafe URL/token/expiry never reaches downloader transport');
    foreach ([['status'=>302,'content_type'=>'application/pdf','body'=>'%PDF-1.7'], ['status'=>200,'content_type'=>'text/html','body'=>'%PDF-1.7'],
        ['status'=>200,'content_type'=>'application/pdf','body'=>'<html>'], ['status'=>200,'content_type'=>'application/pdf','body'=>'%PDF-' . str_repeat('x', SfPdfDownloader::MAX_BYTES)]] as $bad) {
        rejects(fn() => (new SfPdfDownloader(fn() => $bad))->download($artifact), '有效 PDF');
    }
    check($safeDownloader->download($artifact)['mime'] === 'application/pdf' && $downloaded === 1, 'Only bounded PDF content accepted');
    check(!str_contains(json_encode($repo->operations), 'fixture-secret') && !str_contains(json_encode($repo->operations), 'fixture-download-token'), 'Audit updates do not disclose secret or token');
    echo "PASS: {$checks} SF waybill checks; mocked HTTP only, no database or real courier requests.\n";
}
