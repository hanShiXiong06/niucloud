<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\service\admin\ErpFinanceService;
use addon\hsx_erp\app\service\admin\ErpRecycleDeviceIdentityService;
use addon\hsx_erp\app\support\ErpRecycleDeviceIdentity;
use core\exception\CommonException;

final class PureGuardDeviceIdentity extends ErpRecycleDeviceIdentityService
{
    public function assetDeviceId(array $asset, string $operation = '销售'): int
    {
        $identity = ErpRecycleDeviceIdentity::resolve($asset, []);
        if ($identity['ambiguous'] || $identity['device_id'] <= 0) throw new CommonException('来源设备关联待核对');
        return (int)$identity['device_id'];
    }
}

final class PureSourcePaymentFinance extends ErpFinanceService
{
    public array $assets = [];
    public array $requests = [];
    public array $responses = [['consumer' => 'hsx_recycle', 'status' => 'processed']];
    public bool $lookupFailure = false;
    public bool $listenerFailure = false;

    public function __construct() { $this->site_id = 7; }

    public function check(array $payables): void { $this->assertSourcePaymentAllowed($payables); }

    public function reconcile(array $payables, array $amounts): void
    {
        $this->assertHistoricalSourcePaidReconciliationAllowed($payables, $amounts);
    }

    protected function sourcePaymentGuardAssets(array $payable): array
    {
        if ($this->lookupFailure) throw new RuntimeException('database lookup failure');
        return $this->assets[(int)$payable['id']] ?? [];
    }

    protected function recycleDeviceIdentityService(): ErpRecycleDeviceIdentityService
    {
        return new PureGuardDeviceIdentity();
    }

    protected function requestSourcePaymentGuard(array $payload): array
    {
        $this->requests[] = $payload;
        if ($this->listenerFailure) throw new CommonException('回收设备已有付款事实');
        return $this->responses;
    }
}

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) throw new RuntimeException($message);
};
$reject = static function (callable $call, string $message) use ($assert): void {
    try { $call(); } catch (Throwable $e) { $assert(true, $message); return; }
    $assert(false, $message);
};
$asset = ['id' => 501, 'site_id' => 7, 'source_plugin' => 'hsx_recycle', 'source_type' => 'order',
    'source_id' => 88, 'purchase_order_id' => 91, 'purchase_item_id' => 701,
    'spec_json' => json_encode(['source_device_id' => 101])];
$payable = ['id' => 11, 'site_id' => 7, 'source_type' => 'purchase_asset', 'source_id' => 501,
    'asset_id' => 501, 'origin_plugin' => 'hsx_recycle'];
$newService = static function () use ($asset): PureSourcePaymentFinance {
    $service = new PureSourcePaymentFinance();
    $service->assets = [11 => [$asset]];
    return $service;
};
$newHistoricalService = static function () use ($newService): PureSourcePaymentFinance {
    $service = $newService();
    $service->responses[0]['purpose'] = 'historical_paid_reconciliation';
    return $service;
};
$service = $newService();
$service->check([$payable]);
$assert($service->requests === [[
    'event_name' => 'erp.source_payment.guard_requested.v1', 'event_version' => 1, 'site_id' => 7,
    'source_plugin' => 'hsx_recycle', 'source_device_ids' => [101],
    'purpose' => 'payment',
    'prior_erp_paid_amounts' => [['device_id' => 101, 'amount' => '0.00']],
]], '付款前契约仅传真实来源设备，不把订单ID/ERP资产ID当设备，不发送付款动作或金额');

$service = $newService();
$service->assets[12] = [array_replace($asset, ['id' => 502, 'spec_json' => json_encode(['source_device_id' => 99])])];
$second = array_replace($payable, ['id' => 12, 'asset_id' => 502, 'source_id' => 502]);
$service->check([$payable, $second, $payable]);
$assert(count($service->requests) === 1 && $service->requests[0]['source_device_ids'] === [99, 101], '整批合并去重后按设备ID升序一次核对，不能逐应付混序追加锁');
$service->requests = [];
$service->responses[0]['purpose'] = 'historical_paid_reconciliation';
$service->reconcile([$payable, $second], [11 => 12.34, 12 => 5]);
$assert($service->requests[0]['purpose'] === 'historical_paid_reconciliation'
    && $service->requests[0]['device_amounts'] === [['device_id' => 99, 'amount' => '5.00'], ['device_id' => 101, 'amount' => '12.34']],
    '历史入口必须逐设备携带本次实际应付分配金额供来源真实旧账核验，不能无条件跳过');
$service = $newHistoricalService();
$service->reconcile([array_replace($payable, ['settled_amount' => '8.20'])], [11 => 12.34]);
$assert($service->requests[0]['prior_erp_paid_amounts'] === [['device_id' => 101, 'amount' => '8.20']],
    '先前ERP核销金额来自已锁定应付当前快照，不依赖跨插件普通查询的旧快照');
foreach ([-1, INF, NAN] as $invalidPaid) {
    $service = $newHistoricalService();
    $reject(static fn() => $service->reconcile([array_replace($payable, ['settled_amount' => $invalidPaid])], [11 => 12.34]), '已核销快照无效不得伪造为零后核销');
}
foreach ([[], [11 => 0], [11 => -1], [11 => INF]] as $amounts) {
    $service = $newHistoricalService();
    $reject(static fn() => $service->reconcile([$payable], $amounts), '历史核销缺少精确正金额不得请求来源许可');
}
$service = $newHistoricalService();
$service->responses = [];
$reject(static fn() => $service->reconcile([$payable], [11 => 12.34]), '历史事实核销同样必须收到来源明确ACK');
$service = $newHistoricalService();
$service->listenerFailure = true;
$reject(static fn() => $service->reconcile([$payable], [11 => 12.34]), '历史来源金额核对失败必须阻断核销');
$service = $newService();
$reject(static fn() => $service->reconcile([$payable], [11 => 12.34]), '历史核销不能接受旧消费者未确认历史用途的普通付款ACK');

foreach ([[], [['consumer' => 'other', 'status' => 'processed']], [['consumer' => 'hsx_recycle']],
    [['consumer' => 'hsx_recycle', 'status' => 'failed']], [['consumer' => 'hsx_recycle', 'status' => 'unknown']],
    [['consumer' => 'hsx_recycle', 'status' => 'processed', 'error' => 'already paid']],
    [['consumer' => 'hsx_recycle', 'status' => 'processed'], ['consumer' => 'hsx_recycle', 'status' => 'processed']],
    [['consumer' => 'hsx_recycle', 'status' => 'processed'], ['consumer' => 'hsx_recycle', 'status' => 'failed']]] as $responses) {
    $service = $newService();
    $service->responses = $responses;
    $reject(static fn() => $service->check([$payable]), '缺失、失败、含错或重复ACK均阻断付款，不能静默放行');
}
$service = $newService();
$service->responses = [['consumer' => 'other', 'status' => 'failed'], ['consumer' => 'hsx_recycle', 'status' => 'processed']];
$service->check([$payable]);
$assert(count($service->requests) === 1, '仅以来源消费者的明确ACK为准');
$service = $newService();
$service->listenerFailure = true;
$reject(static fn() => $service->check([$payable]), '来源已付/矛盾事实抛异常时必须阻断，不能吞错继续结算');
$service = $newService();
$service->lookupFailure = true;
$reject(static fn() => $service->check([$payable]), '关联查询异常不能伪装其他来源或未找到后付款');

$service = $newService();
$service->check([array_replace($payable, ['origin_plugin' => ''])]);
$assert($service->requests[0]['source_device_ids'] === [101], '历史空origin仍由精确回收资产确认来源');
$service = $newService();
$service->check([array_replace($payable, ['source_type' => 'purchase', 'source_id' => 91])]);
$assert($service->requests[0]['source_device_ids'] === [101], '标准采购明细用asset_id确证，不展开同订单其他设备');
$service = $newService();
$service->check([array_replace($payable, ['source_type' => 'consignment_sale', 'source_id' => 301])]);
$assert($service->requests[0]['source_device_ids'] === [101], '代卖结算同样使用已确证资产来源设备');
foreach ([['source_type' => 'purchase', 'source_id' => 91, 'asset_id' => 0],
    ['source_type' => 'purchase_asset', 'source_id' => 999], ['site_id' => 8]] as $change) {
    $service = $newService();
    $reject(static fn() => $service->check([array_replace($payable, $change)]), '旧整单、冲突关联和跨站应付不得猜设备');
}
$service = $newService();
$service->assets = [];
$reject(static fn() => $service->check([$payable]), '已知回收来源却没有资产关联必须阻断');
$service = $newService();
$service->assets[11] = [array_replace($asset, ['source_plugin' => 'other'])];
$reject(static fn() => $service->check([$payable]), '明确回收应付与资产来源冲突必须阻断');
$service = $newService();
$service->assets[11] = [array_replace($asset, ['spec_json' => '{}'])];
$reject(static fn() => $service->check([$payable]), '普通来源订单ID不能补作设备ID');

foreach (['phone_shop', 'hsx_erp', 'third_party'] as $origin) {
    $service = $newService();
    $service->lookupFailure = true;
    $service->check([array_replace($payable, ['origin_plugin' => $origin])]);
    $assert($service->requests === [], '其他已明确来源付款保持原语义且不调用回收核对');
}
foreach (['refurbish', 'sale_return', 'hsx_erp.operating_expense'] as $type) {
    $service = $newService();
    $service->lookupFailure = true;
    $service->check([array_replace($payable, ['source_type' => $type])]);
    $assert($service->requests === [], '整备/销售退款/经营支出不能因资产曾来自回收而阻断');
}
$service = $newService();
$service->assets[11] = [array_replace($asset, ['source_plugin' => 'other'])];
$service->check([array_replace($payable, ['origin_plugin' => ''])]);
$assert($service->requests === [], '历史空origin的非回收资产不受新闸门影响');

$source = file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpFinanceService.php');
foreach (['settlePayableItemsInTransaction' => '$this->assertSourcePaymentAllowed($payables)',
    'confirmPaymentInTransaction' => '$this->assertSourcePaymentAllowed([$payable])',
    'confirmOffset' => '$this->assertSourcePaymentAllowed($payables)'] as $method => $gate) {
    $visibility = $method === 'settlePayableItemsInTransaction' ? 'private' : 'public';
    $start = strpos($source, $visibility . ' function ' . $method . '(');
    $end = strpos($source, "\n    public function ", $start + 1);
    if ($end === false) $end = strpos($source, "\n    private function ", $start + 1);
    $body = substr($source, $start, $end - $start);
    $gateAt = strpos($body, $gate);
    $createAt = $method === 'confirmOffset' ? strpos($body, 'ErpSettlement::create(') : strpos($body, '$this->createSettlement(');
    $assert($gateAt !== false && $createAt !== false && $gateAt < $createAt, $method . '最后核对必须早于首次结算事实写入');
}
$assert(str_contains($source, '$this->settlePayableItemsInTransaction($partyId, $items, $data, false)')
    && str_contains($source, '$this->settlePayableItemsInTransaction($partyId, $items, $data, true)'), '普通与历史专用入口由代码固定分支，不能让请求data控制是否跳过付款检查');
$assert(!str_contains($source, "\$data['purpose']") && !str_contains($source, "\$data['historical_paid_reconciliation']"), '用户请求标记不得激活内部历史事实分支');
$assert(!str_contains($source, "\$data['prior_erp_paid_amounts']") && str_contains($source, "\$row['settled_amount']"), '历史余额扣减所用ERP当前事实不得读取请求data');
$purchaseSource = file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpPurchaseService.php');
$assert(str_contains($purchaseSource, '$financeService->reconcileHistoricalSourcePaidInTransaction((int)$party->id, $itemsToPay, $settlementData)')
    && str_contains($purchaseSource, '$financeService->confirmPayableItemsInTransaction((int)$party->id, $itemsToPay, $settlementData)'),
    '采购已付事实核销使用专用内部入口，普通开单现结仍走新付款闸门');
$assert(str_contains($source, "event('ErpSourcePaymentGuardRequested', \$payload)"), '使用同步来源核对事件，不以异步通知代替付款前许可');
echo "[PASS] recycle source payment guard: {$count} assertions; no database\n";
