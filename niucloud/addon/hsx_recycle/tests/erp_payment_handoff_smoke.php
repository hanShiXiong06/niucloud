<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$capability = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpCapabilityService.php');
$integration = (string)file_get_contents($root . '/app/service/core/recycle_order/RecycleErpIntegrationService.php');
$devicePayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleDevicePaymentService.php');
$orderEvent = (string)file_get_contents($root . '/app/service/core/recycle_order/CoreRecycleOrderEventService.php');
$orderPayment = (string)file_get_contents($root . '/app/service/admin/order/RecycleOrderPaymentService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleOrder.php');
$runtimeActions = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/hooks/useRecycleOrderActions.ts');
$packageActions = (string)file_get_contents($root . '/admin/hooks/useRecycleOrderActions.ts');
$paymentScope = (string)file_get_contents($repo . '/admin/src/addon/hsx_recycle/utils/payment-scope.ts');

$assert(str_contains($integration, "in_array('hsx_erp', (new CoreSiteService())->getAddonKeysBySiteId(\$siteId), true)"), '安装能力必须由配置服务按当前站点插件权限确认');
$assert(str_contains($capability, "\$config['mode'] === 'self_erp' && \$config['installed']")
    && str_contains($capability, '->inspect($siteId, $orderId, $deviceIds)'), '新业务按启用配置提示，实际付款必须检查真实订单和设备归属，不能仅凭安装状态选ERP');
$assert(str_contains($capability, "->claim(\$siteId, \$orderId, \$deviceIds, 'local')")
    && str_contains($capability, 'catch (\\Throwable $e)') && str_contains($capability, 'if ($e instanceof CommonException) throw $e;'), '本地付款认领失败必须安全上抛');
$assert(str_contains($capability, 'ErpWarehouseOptionsRequested'), 'ERP接管必须通过跨插件事件契约确认仓库能力');
$assert(!str_contains($capability, "class_exists('\\\\addon\\\\hsx_erp"), '回收插件不能通过ERP具体服务类形成硬依赖');
$assert(str_contains($devicePayment, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '设备级打款必须向统一后端闸门传递真实站点、订单及设备ID');
$assert(str_contains($devicePayment, '$this->assertLocalPaymentAllowed($orderId, $deviceIds)')
    && str_contains($devicePayment, "->whereIn('id', \$deviceIds)->order('id asc')->lock(true)")
    && str_contains($devicePayment, "['site_id', '=', \$this->site_id]"), '设备交接前必须锁定本站实际选择设备，不能仅检查站点配置');
$assert(str_contains($devicePayment, '$completedOrderIds') && str_contains($devicePayment, 'marketingDeliveryFactAfter'), 'ERP结清回写必须补发营销事实');
$assert(str_contains($devicePayment, "'source_plugin' => 'hsx_erp'"), 'ERP结清事实必须标识 ERP 为事实来源');
$assert(strpos($devicePayment, 'Db::commit();') < strpos($devicePayment, 'CoreRecycleOrderEventService::marketingDeliveryFactAfter'), '营销事实必须在财务事务提交后发布');
$assert(str_contains($orderEvent, 'public static function marketingDeliveryFactAfter') && str_contains($orderEvent, 'self::emitMarketingFact($data, false)'), '营销事实补发必须使用独立入口，避免重复发送打款通知和旧积分');
$marketingStart = strpos($orderEvent, 'private static function emitMarketingFact(');
$marketingEnd = strpos($orderEvent, 'private static function emitMarketingDeviceFact(', $marketingStart === false ? 0 : $marketingStart);
$marketing = $marketingStart === false ? '' : substr($orderEvent, $marketingStart, $marketingEnd === false ? null : $marketingEnd - $marketingStart);
$assert((bool)preg_match('/paymentCapability\(\s*\(int\)\$order\[\'site_id\'\],\s*\$orderId,\s*array_map/', $marketing)
    && str_contains($marketing, "\$ownership[(int)\$device['id']] ?? []"), '营销事实必须逐设备查询真实站点、订单与设备付款归属，而非按ERP安装开关整单推断');
$assert(str_contains($marketing, "!in_array(\$owner, ['local', 'self_erp'], true)")
    && str_contains($marketing, "\$responsibility['pay_status'] ?? 0")
    && str_contains($marketing, 'PAY_STATUS_PAID') && substr_count($marketing, 'continue;') >= 3
    && str_contains($marketing, '营销事实等待 ERP 结清事件'), '营销正向事实必须跳过退回、未知归属和未付款设备，ERP设备未结清不能提前累计');
$assert(str_contains($marketing, "\$sourcePlugin = \$owner === 'self_erp' ? 'hsx_erp' : 'hsx_recycle';")
    && str_contains($orderEvent, "'hsx_recycle:device_delivered:' . \$siteId . ':' . \$deviceId"), '混合订单营销事实必须按每台设备实际归属标识来源，并保留跨回调稳定幂等键');
$assert(str_contains($orderPayment, 'assertLocalPaymentAllowed((int)$this->site_id, $orderId, $deviceIds)'), '整单打款必须向统一后端闸门传递真实站点、订单及设备集合');
$assert(str_contains($orderPayment, "['site_id', '=', \$this->site_id]") && str_contains($orderPayment, "['order_id', '=', \$order->id]")
    && str_contains($orderPayment, "->order('id asc')->lock(true)") && !str_contains($orderPayment, '$this->assertLocalPaymentAllowed();'), '整单交接必须在真实订单设备锁下检查归属，不能保留无上下文调用');
$assert(str_contains($controller, 'paymentCapability') && str_contains($controller, "array_merge(['accounts'"), '能力接口必须返回ERP财务接管状态');
$assert(str_contains($runtimeActions, 'getCapitalAccountOptions(row.id)') && str_contains($runtimeActions, "paymentOwner === 'self_erp'")
    && str_contains($runtimeActions, '前往 ERP 应付款'), 'PC打款入口必须按当前订单实际ERP归属提示并引导到ERP');
$assert(str_contains($runtimeActions, 'if (!canOpenLocalPayment(capability.data || {}, orderPaymentMode))')
    && str_contains($runtimeActions, "row.flow_mode || row.payment_mode || paymentMode.value || 'order'")
    && str_contains($runtimeActions, "payment_owner || 'unknown'")
    && str_contains($runtimeActions, '付款归属查询失败，尚未执行付款'), 'PC付款必须用真实归属及订单原模式判断，未知或查询失败不得默认放行');
$assert(str_contains($paymentScope, "scope.payment_owner === 'local' && scope.local_allowed === true")
    && str_contains($paymentScope, "scope.payment_owner === 'mixed' && mode === 'device'")
    && str_contains($paymentScope, "?.owner || 'unknown'"), '仅明确本地许可或原按设备模式的混合订单可进入选择器，整单混合和未知设备不得被当作本地');
$assert($runtimeActions === $packageActions, '运行目录和插件包的打款交接逻辑必须一致');

echo "[PASS] recycle ERP payment handoff smoke test\n";
