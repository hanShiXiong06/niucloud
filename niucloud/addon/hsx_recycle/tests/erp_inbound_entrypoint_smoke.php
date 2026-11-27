<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$orderService = (string)file_get_contents($root . '/app/service/admin/recycle_order/RecycleOrderService.php');
$deviceController = (string)file_get_contents($root . '/app/adminapi/controller/order/RecycleDevice.php');
$workflowService = (string)file_get_contents($root . '/app/service/admin/order/RecycleDeviceService.php');
$erpSyncService = (string)file_get_contents($root . '/app/service/admin/order/RecycleDeviceErpSyncService.php');
$apiDeviceService = (string)file_get_contents($root . '/app/service/api/recycle_order/RecycleDeviceService.php');
$apiOrderService = (string)file_get_contents($root . '/app/service/api/recycle_order/RecycleOrderService.php');
$coreDeviceService = (string)file_get_contents($root . '/app/service/core/recycle_order/CoreRecycleDeviceService.php');
$eventConfig = (string)file_get_contents($root . '/app/event.php');
$confirmedListener = (string)file_get_contents($root . '/app/listener/order/RecycleDeviceConfirmedListener.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$assert(
    str_contains($orderService, 'service\\admin\\order\\RecycleDeviceService as WorkflowRecycleDeviceService'),
    '订单级批量回收必须使用会发布 ERP 事件的设备工作流服务'
);
$assert(
    str_contains($orderService, 'new WorkflowRecycleDeviceService()'),
    '订单级批量回收不得回退到旧版同名设备服务'
);
$assert(
    str_contains($deviceController, 'service\\admin\\order\\RecycleDeviceService'),
    '设备级确认回收必须使用当前设备工作流服务'
);
$assert(
    str_contains($workflowService, 'autoSyncErpInbound')
    && str_contains($workflowService, '(new RecycleDeviceErpSyncService())->dispatch'),
    '当前设备工作流服务必须保留 ERP 入库分发'
);
$assert(
    str_contains($workflowService, 'public function dispatchAfterRecycle')
    && str_contains($workflowService, '$this->autoSyncErpInbound($deviceIds)')
    && str_contains($workflowService, '$this->autoEmitPayable($deviceIds)'),
    '确认回收后的 ERP 入库、财务应付必须由统一编排入口负责'
);
$assert(
    str_contains($apiDeviceService, 'confirmMemberPrice(')
    && str_contains($coreDeviceService, "event('RecycleDeviceConfirmed'")
    && str_contains($eventConfig, "'RecycleDeviceConfirmed'")
    && str_contains($confirmedListener, 'dispatchAfterRecycle($deviceIds)'),
    '用户接受报价必须经 Core 提交确认事实，再由事件触发统一下游编排'
);
$assert(
    substr_count($apiDeviceService, 'dispatchAfterRecycle(') >= 2,
    '用户确认出售和批量确认仍必须触发统一下游编排'
);
$assert(
    str_contains($apiOrderService, 'dispatchAfterRecycle(array_map'),
    '用户订单级一键确认必须触发统一下游编排'
);
$assert(
    str_contains($erpSyncService, 'resolvePricingOperator($snapshots)')
    && str_contains($erpSyncService, "['price_uid']"),
    '回收入库事件的采购员必须由最终定价员 price_uid 确定'
);

echo "[PASS] recycle ERP inbound entrypoint smoke test\n";
