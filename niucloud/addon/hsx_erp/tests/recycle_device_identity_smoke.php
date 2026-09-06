<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\listener\ErpDeviceSyncStatus;
use addon\hsx_erp\app\listener\RecycleErpPaymentOwnershipRequested;
use addon\hsx_erp\app\service\admin\ErpRecycleDeviceIdentityService;
use addon\hsx_erp\app\service\admin\ErpSourcePayableSettlementService;
use addon\hsx_erp\app\service\admin\ErpFinanceService;
use addon\hsx_erp\app\support\ErpRecycleDeviceIdentity;
use addon\hsx_recycle\app\listener\downstream\ErpAssetDownstreamListener;

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};
$throws = static function (callable $call, string $contains) use ($assert): void {
    try { $call(); } catch (Throwable $e) {
        $assert(str_contains($e->getMessage(), $contains), '异常必须明确：' . $contains);
        return;
    }
    $assert(false, '必须阻断：' . $contains);
};

class FakeRecycleIdentityService extends ErpRecycleDeviceIdentityService
{
    public array $assets = [];
    public array $items = [];
    public array $payables = [];
    public bool $fail = false;
    protected function loadAssets(int $siteId): array
    {
        if ($this->fail) throw new RuntimeException('database unavailable');
        return $this->assets;
    }
    protected function loadPurchaseItems(int $siteId, array $assetIds): array { return $this->items; }
    protected function loadPayables(int $siteId): array { return $this->payables; }
}
class FakeOwnershipListener extends RecycleErpPaymentOwnershipRequested
{
    public ErpRecycleDeviceIdentityService $service;
    protected function identityService(): ErpRecycleDeviceIdentityService { return $this->service; }
}
class FakeIdentitySyncStatus extends ErpDeviceSyncStatus
{
    public ErpRecycleDeviceIdentityService $service;
    protected function identityService(): ErpRecycleDeviceIdentityService { return $this->service; }
}
class FakeIdentityFinanceService extends ErpFinanceService
{
    public ErpRecycleDeviceIdentityService $identity;
    public function __construct() {}
    protected function recycleDeviceIdentityService(): ErpRecycleDeviceIdentityService { return $this->identity; }
    public function snapshot(array $row): array { return $this->settlementAssetSnapshot($row); }
}

$asset = ['id' => 101, 'site_id' => 7, 'source_plugin' => 'hsx_recycle',
    'source_type' => 'hsx_recycle.recycle_purchase', 'source_id' => '900',
    'purchase_order_id' => 20, 'purchase_item_id' => 21,
    'spec_json' => '{"source_plugin":"hsx_recycle","source_device_id":42}',
    'asset_no' => 'AS-101', 'imei' => 'IMEI-42', 'sn' => 'SN-42', 'model' => '测试设备', 'spec' => '256G',
    'status' => 'in_stock', 'warehouse_id' => 1, 'warehouse_name' => '仓库',
    'location_id' => 2, 'location_name' => '库位', 'update_at' => 1];
$id = ErpRecycleDeviceIdentity::resolve($asset);
$assert($id['device_id'] === 42 && !$id['ambiguous'], '普通采购只读设备快照，不读来源订单ID');
$missing = array_replace($asset, ['spec_json' => '{}', 'imei' => '42', 'sn' => '42']);
$assert(ErpRecycleDeviceIdentity::resolve($missing)['device_id'] === 0, '不能通过订单ID、IMEI或SN猜设备ID');
$consigned = array_replace($missing, ['source_type' => 'hsx_recycle.consignment', 'source_id' => '43', 'ownership_type' => 'owned']);
$assert(ErpRecycleDeviceIdentity::resolve($consigned)['device_id'] === 43, '明确代卖来源允许历史设备ID回退，买断后仍可关联');
$assert(ErpRecycleDeviceIdentity::resolve(array_replace($consigned, ['source_type' => 'consignment']))['device_id'] === 0, '不接受未经明确约定的代卖来源类型');
$item = ['id' => 21, 'site_id' => 7, 'asset_id' => 101, 'purchase_order_id' => 20,
    'spec_json' => ['source_plugin' => 'hsx_recycle', 'source_device_id' => 42]];
$assert(ErpRecycleDeviceIdentity::resolve($missing, [$item])['device_id'] === 42, '双向关联确证的采购明细可补齐历史快照');
foreach ([['site_id' => 8], ['asset_id' => 102], ['id' => 22], ['purchase_order_id' => 99]] as $change) {
    $assert(ErpRecycleDeviceIdentity::resolve($missing, [array_replace($item, $change)])['device_id'] === 0, '不同站点/设备/明细/采购单快照不可补猜');
}
$conflict = ErpRecycleDeviceIdentity::resolve($asset, [array_replace($item, ['spec_json' => ['source_device_id' => 43]])]);
$assert($conflict['ambiguous'] && $conflict['device_id'] === 0 && $conflict['candidate_device_ids'] === [42, 43], '资产与明细快照冲突必须给出歧义');
$assert(ErpRecycleDeviceIdentity::resolve(array_replace($asset, ['source_plugin' => 'other']))['device_id'] === 0, '其他插件不能被识别为回收设备');
foreach (['42x', '-42', '4.2', '9999999999999999999999999', 4.2] as $badId) {
    $assert(ErpRecycleDeviceIdentity::positiveId($badId) === 0, '拒绝非法或溢出设备ID');
}

$service = new FakeRecycleIdentityService();
$service->assets = [$asset, array_replace($asset, ['id' => 999, 'site_id' => 8])];
$map = $service->deviceAssets(7, [42, 900]);
$assert($map[42]['asset_ids'] === [101] && !$map[900]['has_asset'], '跨站和订单ID数值碰撞不能命中资产');
$assert($service->uniqueAssets(7, [42])[0]['id'] === 101, '付款必须返回精确关联资产');
$throws(static fn() => $service->uniqueAssets(7, [42, -1]), '来源设备ID无效');
$normalizer = new ReflectionMethod(ErpSourcePayableSettlementService::class, 'normalizePayload');
$normalizer->setAccessible(true);
$paymentService = (new ReflectionClass(ErpSourcePayableSettlementService::class))->newInstanceWithoutConstructor();
foreach (['42x', 42.9, true, -1, 0] as $badDeviceId) {
    $throws(static fn() => $normalizer->invoke($paymentService, ['source_device_ids' => [42, $badDeviceId]]), '来源设备ID无效');
}
$assert($service->assetDeviceId($asset) === 42, '销售事件设备ID来自统一解析器');
$throws(static fn() => $service->assetDeviceId($missing), '未执行销售');
$throws(static fn() => $service->uniqueAssets(7, [42, 900]), '整批付款');
$service->assets[] = array_replace($asset, ['id' => 102, 'status' => 'returned']);
$map = $service->deviceAssets(7, [42]);
$assert($map[42]['ambiguous'] && $map[42]['asset_ids'] === [101, 102], '同设备多个资产包括退货历史必须歧义，不挑最新');
$throws(static fn() => $service->uniqueAssets(7, [42]), '歧义');
$throws(static fn() => $service->assetDeviceId($asset), '未执行销售');
$sync = new FakeIdentitySyncStatus();
$sync->service = $service;
$throws(static fn() => $sync->handle(['site_id' => 7, 'source_device_id' => '42x']), '来源设备ID无效');
$health = $sync->handle(['site_id' => 7, 'source_device_ids' => [42]]);
$assert($health[42]['stuck'] && $health[42]['ambiguous'] && $health[42]['has_asset'], '歧义回查必须提示异常并保留已有关联事实');
$throws(static fn() => $sync->handle(['site_id' => 7, 'source_device_id' => 42]), '未执行重新同步');
$service->assets = [$missing];
$service->items = [$item];
$resolvedSync = $sync->handle(['site_id' => 7, 'device_ids' => [42]]);
$assert(($resolvedSync[42]['source_device_id'] ?? 0) === 42, '采购明细补证的身份必须带到快照刷新入口');
$syncSource = (string)file_get_contents(dirname(__DIR__) . '/app/listener/ErpDeviceSyncStatus.php');
$assert(str_contains($syncSource, "\$specJson['source_device_id'] = \$sourceDeviceId;"), '规格刷新不能抹掉采购明细中补证的来源设备身份');

$service->assets = [$asset];
$service->items = [];
$payable = ['id' => 201, 'site_id' => 7, 'asset_id' => 0, 'source_type' => 'purchase_asset',
    'source_id' => 101, 'origin_plugin' => 'hsx_recycle', 'amount' => 1000, 'settled_amount' => 250, 'status' => 'partial'];
$service->payables = [$payable, $payable,
    array_replace($payable, ['id' => 202, 'source_id' => 999]),
    array_replace($payable, ['id' => 203, 'status' => 'void']),
    array_replace($payable, ['id' => 204, 'asset_id' => 101, 'source_type' => 'refurbish']),
    array_replace($payable, ['id' => 205, 'site_id' => 8])];
$ownership = $service->paymentOwnership(7, [42, 900]);
$assert($ownership[42]['has_payable'] && !$ownership[42]['ambiguous'] && $ownership[42]['paid_amount'] === 250.0 && $ownership[42]['remaining_amount'] === 750.0, '兼容purchase_asset.source_id且按应付去重，排除作废/整备/跨站');
$assert(!$ownership[900]['has_asset'] && !$ownership[900]['has_payable'], '不能把回收订单ID碰撞识别为设备归属');
$service->payables = [array_replace($payable, ['source_type' => 'consignment_sale', 'source_id' => 888, 'asset_id' => 101])];
$assert($service->paymentOwnership(7, [42])[42]['remaining_amount'] === 750.0, '代卖成交通过明确asset_id关联，不把销售明细ID当资产ID');
$service->payables = [array_replace($payable, ['source_type' => 'purchase', 'source_id' => 20])];
$legacy = $service->paymentOwnership(7, [42])[42];
$assert($legacy['ambiguous'] && $legacy['has_payable'] && $legacy['paid_amount'] === 0.0 && $legacy['remaining_amount'] === 0.0, '历史整单应付无法确证设备分配，不重复分摊整单金额');
$service->payables = [$payable, array_replace($payable, ['id' => 206])];
$assert($service->paymentOwnership(7, [42])[42]['ambiguous'], '重复有效设备采购应付必须报告歧义');
$service->payables = [$payable, array_replace($payable, ['id' => 207, 'source_type' => 'purchase', 'source_id' => 20, 'asset_id' => 101])];
$assert($service->paymentOwnership(7, [42])[42]['ambiguous'], '整单类型显式设备应付与设备级采购应付并存必须报告重复采购义务');
$service->payables = [array_replace($payable, ['asset_id' => 999])];
$assert($service->paymentOwnership(7, [42])[42]['ambiguous'], '应付显式asset_id与purchase_asset.source_id冲突必须报告歧义');

$listener = new FakeOwnershipListener();
$listener->service = $service;
$response = $listener->handle(['site_id' => 7, 'device_ids' => [42]]);
$assert($response['consumer'] === 'hsx_erp' && $response['status'] === 'processed' && isset($response['devices'][42]), '只读归属事件返回约定契约');
$throws(static fn() => $listener->handle(['site_id' => 0, 'device_ids' => [42]]), '参数不正确');
$service->fail = true;
$throws(static fn() => $listener->handle(['site_id' => 7, 'device_ids' => [42]]), 'database unavailable');
$throws(static fn() => $sync->handle(['site_id' => 7, 'device_ids' => [42]]), 'database unavailable');

$downstream = (new ErpAssetDownstreamListener())->handle(['event_name' => 'erp.asset.sold.v1', 'site_id' => 7,
    'source' => ['id' => 42, 'type' => 'asset'], 'aggregate_id' => 42, 'payload' => ['asset_id' => 42, 'source_device_id' => 0]]);
$assert(($downstream['reason'] ?? '') === 'missing_source_device_id', '缺失设备ID不得fallback ERP source.id，且测试不触发数据库');
$events = require dirname(__DIR__) . '/app/event.php';
$assert(in_array(RecycleErpPaymentOwnershipRequested::class, $events['listen']['RecycleErpPaymentOwnershipRequested'] ?? [], true), '归属事件必须注册');
$saleSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpSaleService.php');
$paymentSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpSourcePayableSettlementService.php');
$assert(str_contains($saleSource, '->assetDeviceId($asset->toArray())') && !str_contains($saleSource, 'is_numeric((string)$asset->source_id)'), '销售发布必须使用统一关联解析');
$assert(str_contains($paymentSource, '->uniqueAssets($siteId, $sourceDeviceIds)') && str_contains($paymentSource, "where('source_type', '=', 'purchase_asset')"), '付款入口必须使用精确资产与旧应付确证关联');

// 实际结算快照构建方法的纯内存测试：读取边界全部替换，不初始化请求或数据库。
$financeIdentity = new FakeRecycleIdentityService();
$financeIdentity->assets = [$missing];
$financeIdentity->items = [$item];
$finance = new FakeIdentityFinanceService();
$finance->identity = $financeIdentity;
$snapshot = $finance->snapshot($missing);
$assert($snapshot['source_device_id'] === 42 && $snapshot['source_plugin'] === 'hsx_recycle', '结算事件必须携带采购明细补证的回收设备身份');
$assert($snapshot['id'] === 101 && $snapshot['asset_no'] === 'AS-101' && $snapshot['imei'] === '42', '补证不得改动ERP资产ID、业务编号和IMEI');
$financeIdentity->items = [];
$throws(static fn() => $finance->snapshot($missing), '未执行结算');
$throws(static fn() => $financeIdentity->assetDeviceId($missing, '销售退货'), '未执行销售退货');
$financeIdentity->assets = [$asset];
$financeIdentity->items = [array_replace($item, ['spec_json' => ['source_device_id' => 43]])];
$throws(static fn() => $finance->snapshot($asset), '未执行结算');
$financeIdentity->items = [];
$financeIdentity->assets[] = array_replace($asset, ['id' => 102]);
$throws(static fn() => $finance->snapshot($asset), '未执行结算');
$financeIdentity->fail = true;
$throws(static fn() => $finance->snapshot($asset), 'database unavailable');
$otherSnapshot = $finance->snapshot(array_replace($asset, ['source_plugin' => 'other',
    'spec_json' => '{"source_plugin":"other","source_device_id":"external-42","source_order_no":"ORDER-9"}']));
$assert($otherSnapshot['source_plugin'] === 'other' && $otherSnapshot['source_device_id'] === 'external-42'
    && $otherSnapshot['source_order_no'] === 'ORDER-9', '非回收插件既有事件快照保持不变，不触发回收关联查询');
$saleReturnSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpSaleReturnService.php');
$financeSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpFinanceService.php');
$assert(str_contains($saleReturnSource, "->assetDeviceId(\$asset->toArray(), '销售退货')")
    && !str_contains($saleReturnSource, 'is_numeric((string)$asset->source_id)'), '销售退货事件不得把回收订单ID当设备ID');
$assert(str_contains($financeSource, '$this->settlementAssetSnapshot($map[$assetId])')
    && str_contains($financeSource, 'source_type,source_id,purchase_item_id,purchase_order_id'), '结算事件查询必须载入确证身份所需字段并使用补证快照构建');
echo "[PASS] ERP recycle device identity ({$count} assertions; no database)\n";
