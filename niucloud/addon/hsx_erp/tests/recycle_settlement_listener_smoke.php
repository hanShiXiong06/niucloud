<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\listener\downstream\ErpSettlementCompletedListener;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

class FakeRecycleSettlementListener extends ErpSettlementCompletedListener
{
    public array $calls = [];
    public int $marked = 1;
    protected function settleDevices(array $deviceIds, string $settlementNo, array $info): int
    {
        $this->calls[] = compact('deviceIds', 'settlementNo', 'info');
        return $this->marked;
    }
}

$event = [
    'event_name' => 'erp.settlement.completed.v1',
    'event_id' => 'EV-SETTLEMENT-1',
    'operator' => ['name' => '财务员'],
    'payload' => [
        'settlement_no' => 'ST202607110001',
        'settlement_type' => 'payment',
        'amount' => 1000,
        'capital_account_name' => '公司银行卡',
        'targets' => [[
            'target_type' => 'payable',
            'remaining_amount' => 0,
            'origin' => ['plugin' => 'hsx_recycle', 'type' => 'hsx_recycle.recycle_purchase'],
            'assets' => [
                ['id' => 88, 'source_plugin' => 'hsx_recycle', 'source_device_id' => 101],
                ['id' => 89, 'source_plugin' => 'hsx_recycle', 'source_device_id' => 102],
            ],
        ], [
            'target_type' => 'payable',
            'remaining_amount' => 0,
            'origin' => ['plugin' => 'other_plugin'],
            'assets' => [['source_device_id' => 999]],
        ]],
    ],
];

$listener = new FakeRecycleSettlementListener();
$result = $listener->handle($event);
$assert(($result['status'] ?? '') === 'processed', '全额结清回收采购应付必须回写来源插件');
$assert(($listener->calls[0]['deviceIds'] ?? []) === [101, 102], '只能回写属于hsx_recycle的来源设备');
$assert(($listener->calls[0]['info']['method'] ?? '') === 'payment', '实际付款与折账必须保留不同结算类型');
$assert(($listener->calls[0]['info']['account'] ?? '') === '公司银行卡', '实际付款必须回写ERP付款账户快照');

$partial = $event;
$partial['event_id'] = 'EV-SETTLEMENT-2';
$partial['payload']['targets'][0]['remaining_amount'] = 1;
$partialListener = new FakeRecycleSettlementListener();
$partialResult = $partialListener->handle($partial);
$assert(($partialResult['status'] ?? '') === 'skipped' && $partialListener->calls === [], '部分核销不能提前把来源设备标成已结清');

$config = require dirname(__DIR__, 2) . '/hsx_recycle/app/event.php';
$domainListeners = (array)($config['listen']['ErpDomainEvent'] ?? []);
$assert(in_array('addon\hsx_recycle\app\listener\downstream\ErpSettlementCompletedListener', $domainListeners, true), '回收插件必须订阅ERP可重试结算领域事件');

$finance = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpFinanceService.php');
$assert(str_contains($finance, "'erp.settlement.completed.v1'") && str_contains($finance, "'remaining_amount'"), 'ERP结算事件必须包含结算结果和剩余金额');
$assert(str_contains($finance, "'source_device_id'") && str_contains($finance, "'source_plugin'"), 'ERP结算事件必须带来源设备行快照供插件回写');

$returnService = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpPurchaseReturnService.php');
$assetListener = (string)file_get_contents(dirname(__DIR__, 2) . '/hsx_recycle/app/listener/downstream/ErpAssetDownstreamListener.php');
$settlementListener = (string)file_get_contents(dirname(__DIR__, 2) . '/hsx_recycle/app/listener/downstream/ErpSettlementCompletedListener.php');
$downstreamDict = (string)file_get_contents(dirname(__DIR__, 2) . '/hsx_recycle/app/dict/order/RecycleDownstreamDict.php');
$assert(str_contains($returnService, "'erp.purchase_return.completed.v1'") && str_contains($returnService, "'source_device_id'"), 'ERP采购退货必须通过outbox回显到来源回收设备');
$assert(str_contains($assetListener, 'mirrorPurchaseReturn'), 'ERP采购退货必须同步回收设备业务状态');
$assert(str_contains($settlementListener, "'receivable'") && str_contains($settlementListener, 'STAGE_PURCHASE_RETURN_SETTLED'), '采购退货应收到账后必须更新回收端只读镜像');
$assert(str_contains($downstreamDict, 'ERP采退·已退回') && str_contains($downstreamDict, 'ERP采退·已到账'), '回收端必须区分设备已退回和退款已到账');

echo "[PASS] ERP recycle settlement callback smoke test\n";
