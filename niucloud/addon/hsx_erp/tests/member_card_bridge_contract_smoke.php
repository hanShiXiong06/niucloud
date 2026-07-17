<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\service\admin\ErpExternalContractService;
use addon\hsx_erp\app\service\admin\ErpFinanceFactVoidService;
use addon\hsx_erp\app\service\admin\ErpFinanceFactService;
use addon\hsx_erp\app\service\admin\ErpFinanceSettlementRequestService;
use addon\hsx_erp\app\service\admin\ErpPartyBridgeService;
use core\exception\CommonException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

class FakeExternalContractService extends ErpExternalContractService
{
    public array $inboxes = [];
    public int $handled = 0;
    private int $nextId = 0;

    public function __construct()
    {
        $this->site_id = 100005;
    }

    public function consume(array $event): array
    {
        $payload = array_merge($this->normalizeEnvelope($event, 'test.contract.v1'), [
            'event_name' => 'test.contract.v1',
            'event_version' => 1,
            'value' => trim((string)($event['value'] ?? '')),
        ]);
        return $this->consumeOnce($payload, 'TestContractRequested', function (array $request): array {
            $this->handled++;
            if ($request['value'] === 'fail') throw new CommonException('模拟业务失败');
            return ['value' => $request['value'], 'result_id' => 99];
        });
    }

    protected function findInbox(int $siteId, string $eventId, bool $lock = false): ?array
    {
        return $this->inboxes[$siteId . ':' . $eventId] ?? null;
    }

    protected function createInbox(array $payload, string $eventName): int
    {
        $id = ++$this->nextId;
        $this->inboxes[$payload['site_id'] . ':' . $payload['event_id']] = [
            'id' => $id,
            'status' => 'pending',
            'payload_json' => json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE),
        ];
        return $id;
    }

    protected function completeInbox(int $inboxId, array $payload, array $result): void
    {
        $key = $payload['site_id'] . ':' . $payload['event_id'];
        $this->inboxes[$key]['status'] = 'processed';
        $this->inboxes[$key]['payload_json'] = json_encode(['request' => $payload, 'result' => $result], JSON_UNESCAPED_UNICODE);
    }

    protected function recordFailedInbox(array $payload, string $eventName, string $message): void
    {
        $key = $payload['site_id'] . ':' . $payload['event_id'];
        $this->inboxes[$key] = [
            'id' => (int)($this->inboxes[$key]['id'] ?? ++$this->nextId),
            'status' => 'failed',
            'payload_json' => json_encode(['request' => $payload, 'error' => $message], JSON_UNESCAPED_UNICODE),
        ];
    }

    protected function withinTransaction(callable $callback): array
    {
        return $callback();
    }
}

class TestPartyBridgeService extends ErpPartyBridgeService
{
    public function __construct() { $this->site_id = 100005; }
    public function normalized(array $event): array { return $this->normalizePayload($event); }
}

class TestSettlementRequestService extends ErpFinanceSettlementRequestService
{
    public function __construct() { $this->site_id = 100005; }
    public function normalized(array $event): array { return $this->normalizePayload($event); }
    public function guard(array $target, array $payload): void { $this->assertTargetCanSettle($target, $payload); }
}

class TestFactVoidService extends ErpFinanceFactVoidService
{
    public function __construct() { $this->site_id = 100005; }
    public function normalized(array $event): array { return $this->normalizePayload($event); }
}

class TestFinanceFactService extends ErpFinanceFactService
{
    public function __construct() { $this->site_id = 100005; }
    public function normalized(array $event): array { return $this->normalizePayload($event); }
}

$envelope = [
    'site_id' => 100005,
    'event_id' => 'hsx_member_card:test:1',
    'source_plugin' => 'hsx_member_card',
    'occurred_at' => 1784160000,
    'value' => 'ok',
];
$external = new FakeExternalContractService();
$first = $external->consume($envelope);
$assert(($first['status'] ?? '') === 'processed' && ($first['result_id'] ?? 0) === 99, '跨插件写契约首次请求必须处理成功');
$duplicate = $external->consume($envelope);
$assert(($duplicate['status'] ?? '') === 'duplicate' && ($duplicate['result_id'] ?? 0) === 99, '跨插件写契约必须返回首次结果并阻止重复处理');
$assert($external->handled === 1, '相同event_id不能重复执行业务处理器');

$collision = $envelope;
$collision['value'] = 'changed';
$collisionRejected = false;
try { $external->consume($collision); } catch (CommonException $e) { $collisionRejected = str_contains($e->getMessage(), 'event_id'); }
$assert($collisionRejected, '相同event_id携带不同payload必须拒绝');

$wrongSite = $envelope;
$wrongSite['event_id'] = 'hsx_member_card:test:wrong-site';
$wrongSite['site_id'] = 100006;
$siteRejected = false;
try { $external->consume($wrongSite); } catch (CommonException $e) { $siteRejected = str_contains($e->getMessage(), '站点'); }
$assert($siteRejected, '所有外部契约必须校验当前请求站点');

$partyPayload = (new TestPartyBridgeService())->normalized([
    'site_id' => 100005,
    'event_id' => 'hsx_member_card:party:member-8',
    'source_plugin' => 'hsx_member_card',
    'member' => ['member_id' => 8, 'name' => '测试会员', 'mobile' => '13800138000'],
    'role' => 'sale_customer',
]);
$assert($partyPayload['member_id'] === 8 && $partyPayload['role'] === 'sale_customer', '会员往来主体解析契约必须保留会员ID和业务身份');

$factPayload = (new TestFinanceFactService())->normalized([
    'site_id' => 100005,
    'event_id' => 'hsx_member_card:fact:issue-9',
    'source_plugin' => 'hsx_member_card',
    'source_plugin_name' => '会员服务卡',
    'source_name' => '会员卡开卡订单',
    'source_type' => 'hsx_member_card.card_order',
    'order_no' => 'MC202607170001',
    'line_id' => 'order:9',
    'category_key' => 'hsx_member_card.card_sale',
    'party_id' => 12,
    'party_name' => '测试客户',
    'amount' => '100.00',
    'settlement' => ['mode' => 'immediate', 'name' => '现结'],
    'operator' => ['id' => 1, 'name' => 'admin'],
    'occurred_at' => 1784160000,
]);
$assert($factPayload['settlement_mode'] === 'immediate' && $factPayload['settlement_mode_name'] === '现结', '财务事实必须保存开单时结算约定快照');
$assert($factPayload['operator_id'] === 1 && $factPayload['operator_name'] === 'admin', '财务事实必须保存开单/业务经办人快照');

$settlementService = new TestSettlementRequestService();
$settlementPayload = $settlementService->normalized([
    'site_id' => 100005,
    'event_id' => 'hsx_member_card:settlement:issue-9',
    'source_plugin' => 'hsx_member_card',
    'target_type' => 'receivable',
    'target_id' => 12,
    'amount' => 99.9,
    'capital_account_id' => 3,
    'voucher_urls' => [' /upload/a.png ', '/upload/a.png', '/upload/b.png'],
]);
$assert($settlementPayload['amount'] === '99.90', '实际收付款契约金额必须规范为两位小数');
$assert($settlementPayload['voucher_urls'] === ['/upload/a.png', '/upload/b.png'], '收付款凭证必须去空格、去重并保留数组');
$settlementService->guard([
    'status' => 'pending', 'origin_plugin' => 'hsx_member_card', 'amount' => '100.00', 'settled_amount' => '0.00',
], $settlementPayload);

$foreignRejected = false;
try {
    $settlementService->guard([
        'status' => 'pending', 'origin_plugin' => 'another_plugin', 'amount' => '100.00', 'settled_amount' => '0.00',
    ], $settlementPayload);
} catch (CommonException $e) { $foreignRejected = str_contains($e->getMessage(), '自身'); }
$assert($foreignRejected, '插件不得结算其它插件产生的应收应付');

$overpay = $settlementPayload;
$overpay['amount'] = '100.01';
$overpayRejected = false;
try {
    $settlementService->guard([
        'status' => 'pending', 'origin_plugin' => 'hsx_member_card', 'amount' => '100.00', 'settled_amount' => '0.00',
    ], $overpay);
} catch (CommonException $e) { $overpayRejected = str_contains($e->getMessage(), '剩余'); }
$assert($overpayRejected, '实际收付款不能超过剩余应结金额');

$voidPayload = (new TestFactVoidService())->normalized([
    'site_id' => 100005,
    'event_id' => 'hsx_member_card:void:issue-9',
    'source_plugin' => 'hsx_member_card',
    'target_type' => 'receivable',
    'target_id' => 12,
    'reason' => '开卡单取消',
]);
$assert($voidPayload['reason'] === '开卡单取消', '财务事实作废必须保存明确原因');

$eventConfig = require dirname(__DIR__) . '/app/event.php';
$expectedListeners = [
    'ErpPartyResolveRequested' => 'addon\\hsx_erp\\app\\listener\\ErpPartyResolveRequested',
    'ErpCapitalAccountOptionsRequested' => 'addon\\hsx_erp\\app\\listener\\ErpCapitalAccountOptionsRequested',
    'ErpFinanceSettlementRequested' => 'addon\\hsx_erp\\app\\listener\\ErpFinanceSettlementRequested',
    'ErpFinanceFactVoidRequested' => 'addon\\hsx_erp\\app\\listener\\ErpFinanceFactVoidRequested',
];
foreach ($expectedListeners as $eventName => $listener) {
    $listeners = (array)($eventConfig['listen'][$eventName] ?? []);
    $assert(in_array($listener, $listeners, true), "event.php必须注册{$eventName}");
}

$accountSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpCapitalAccountOptionService.php');
$assert(str_contains($accountSource, "['status', '=', 1]"), '外部资金账户选项只能返回启用账户');
$assert(!str_contains($accountSource, "field('id,account_name,account_type,is_default,sort,balance") && !str_contains($accountSource, 'account_no'), '外部资金账户选项不得泄露余额和账号');

$settlementSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpFinanceSettlementRequestService.php');
$assert(str_contains($settlementSource, 'confirmReceipt') && str_contains($settlementSource, 'confirmPayableItemsPayment'), '外部结算必须复用ERP既有实际收付款领域服务');
$assert(!str_contains($settlementSource, 'ErpSettlement::create'), '外部结算边界不得另造结算记录');

$voidSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpFinanceFactVoidService.php');
$assert(!str_contains($voidSource, 'ErpMoneyLedger'), '作废未结财务事实不能产生实际资金流水');
$assert(str_contains($voidSource, 'STATUS_VOID') && str_contains($voidSource, "'direction' => 'decrease'"), '作废财务事实必须保留原单并写减少账目轨迹');

$configSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpConfigService.php');
$assert(str_contains($configSource, "'advance_receipt'") && str_contains($configSource, "'advance_receipt_reversal'"), 'ERP动态财务分类必须支持预收和预收冲回报表分组');

echo "ERP member-card bridge contract smoke passed.\n";
