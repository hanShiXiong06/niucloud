<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . ltrim($path, '/'));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$recycle = $read('addon/hsx_recycle/app/service/admin/order/RecycleDeviceErpSyncService.php');
$inbound = $read('addon/hsx_erp/app/listener/ErpDeviceInboundRequested.php');
$purchase = $read('addon/hsx_erp/app/service/admin/ErpPurchaseService.php');
$account = $read('addon/hsx_erp/app/service/admin/ErpCapitalAccountService.php');
$consignment = $read('addon/hsx_erp/app/service/admin/ErpConsignmentInboundService.php');

$assert(str_contains($recycle, 'SysUser::withTrashed()'), '历史定价员被停用或软删除后仍必须能够还原姓名快照');
$assert(str_contains($recycle, "(int)(\$device['pay_status'] ?? 0) === 1") && str_contains($recycle, '$sourcePaidAmount = $purchaseCost'), '老订单只有已付款状态时必须按最终成交价恢复已付金额');
$assert(str_contains($recycle, "'price_name' => \$pricingOperator['name']") && str_contains($recycle, "'historical_operator'"), '回收入库事件必须固化历史定价人姓名与状态');

$assert(str_contains($account, 'SOURCE_PAID_CLEARING_ACCOUNT_NO'), 'ERP必须有来源系统已付待核对账户，不能把历史付款伪造成未付');
$assert(str_contains($account, '防止重复打款'), '来源已付核对账户必须向财务解释用途');
$assert(str_contains($inbound, "'source_paid_allocation' =>"), '回收入库必须开启设备级已付金额分配');
$assert(str_contains($inbound, "\$item['source_paid_amount'] = \$devicePaidAmount"), '每台回收设备必须携带自己的已付金额');
$assert(str_contains($inbound, "'settlement_request_id' => 'source-paid:'"), '来源已付核销必须有稳定幂等键');
$assert(str_contains($inbound, '禁止重复付款'), '采购备注必须给财务明确的防重复付款提示');
$assert(str_contains($inbound, 'public static function forSite'), '定时任务重放回收入库时必须能显式锁定站点');
$assert(str_contains($inbound, "unset(\$event['_delivery'], \$event['_erp_source'])"), '消费端临时快照不得参与业务幂等碰撞判断');
$assert(str_contains($inbound, "\$payload['_retry'] = \$stored['_retry']"), '失败入库重放时必须保留累计尝试次数');
$assert(str_contains($consignment, 'public static function forSite') && str_contains($consignment, 'ErpPurchaseService::forSite'), '代卖入库补偿也必须继承显式站点边界');

$assert(str_contains($purchase, "\$useSourcePaidAllocation"), '采购服务必须显式区分来源已付核销与普通现结');
$assert(str_contains($purchase, "'explicit_paid_amount'"), '采购服务必须把已付金额精确落到设备应付，不能按列表顺序猜测');
$assert(str_contains($purchase, '来源系统已付金额与设备明细不一致'), '金额无法对平时必须停止核销并给出可操作原因');
$assert(str_contains($purchase, 'resolvePurchaser($data)'), '采购服务必须通过统一入口解析当前或历史采购员');
$assert(str_contains($purchase, "!in_array(\$sourcePlugin, ['erp', 'hsx_erp'], true)"), '历史人员快照只能由外部事件使用，手工采购仍须严格校验员工权限');

echo "[PASS] recycle historical paid accounting contract\n";
