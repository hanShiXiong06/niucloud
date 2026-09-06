<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_wecom\app\service\core\WecomNotificationService;

$service = (new ReflectionClass(WecomNotificationService::class))->newInstanceWithoutConstructor();
$contract = new ReflectionMethod(WecomNotificationService::class, 'targetContractError');
$resolve = new ReflectionMethod(WecomNotificationService::class, 'target');
$contract->setAccessible(true);
$resolve->setAccessible(true);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$payableEvent = [
    'source_plugin' => 'hsx_recycle',
    'target' => [
        'plugin' => 'hsx_erp',
        'route_key' => 'hsx_erp.payable.list',
        'web_path' => 'site/hsx_erp/payable?status=pending&source_no=RO20260716001',
        'miniapp_path' => 'addon/hsx_erp/pages/payable/list?status=pending&source_no=RO20260716001',
    ],
];
$assert($contract->invoke($service, $payableEvent) === '', '跨插件财务目标应允许来源为回收、目标为ERP');

$wrongEvent = $payableEvent;
$wrongEvent['target']['miniapp_path'] = 'addon/hsx_recycle/pages/order/detail?id=1';
$assert($contract->invoke($service, $wrongEvent) !== '', 'ERP目标误写回收路径时必须拒绝发送');

$target = $resolve->invoke($service, $payableEvent, [
    'jump_mode' => 'dual',
    'web_base_url' => 'https://example.com',
    'miniapp_appid' => 'wx123',
]);
$assert($target['web_url'] === 'https://example.com/site/hsx_erp/payable?status=pending&source_no=RO20260716001', 'ERP网页目标解析错误');
$assert($target['miniapp_path'] === 'addon/hsx_erp/pages/payable/list?status=pending&source_no=RO20260716001', 'ERP小程序目标解析错误');

$webOnlyEvent = [
    'target' => [
        'plugin' => 'hsx_project_center',
        'route_key' => 'hsx_project_center.application',
        'web_path' => 'site/hsx_project_center/application?application_id=9',
    ],
];
$assert($contract->invoke($service, $webOnlyEvent) === '', '仅有网页详情的业务目标也必须通过契约校验');
$webOnlyTarget = $resolve->invoke($service, $webOnlyEvent, [
    'jump_mode' => 'miniapp',
    'web_base_url' => 'https://example.com',
    'miniapp_appid' => 'wx123',
]);
$assert($webOnlyTarget['miniapp_path'] === 'app/pages/index/index', '没有移动详情页时必须回退到管理端小程序首页');
$assert($webOnlyTarget['web_url'] === 'https://example.com/site/hsx_project_center/application?application_id=9', '小程序回退时必须保留精确网页入口');

$legacyRecycleEvent = [
    'source_plugin' => 'hsx_recycle',
    'source_type' => 'recycle_device',
    'source_id' => 8,
    'stage_key' => 'check',
    'target' => [
        'plugin' => 'hsx_recycle',
        'route_key' => 'hsx_recycle.task.list',
        'params' => ['order_id' => 6, 'device_id' => 8, 'stage' => 'check'],
        'web_path' => 'site/stat/task?stage=check',
        'miniapp_path' => 'addon/hsx_recycle/pages/task/index?stage=check',
    ],
];
$legacyTarget = $resolve->invoke($service, $legacyRecycleEvent, [
    'jump_mode' => 'dual',
    'web_base_url' => 'https://example.com',
    'miniapp_appid' => 'wx123',
]);
$assert($legacyTarget['route_key'] === 'hsx_recycle.order.detail', '旧回收任务必须纠正为订单详情路由');
$assert($legacyTarget['miniapp_path'] === 'addon/hsx_recycle/pages/order/detail?id=6&device_id=8&stage=check', '旧回收任务详情路径纠正错误');
$assert($legacyTarget['web_url'] === 'https://example.com/site/recycle_order/list?order_id=6&device_id=8&stage=check', '旧回收任务网页路径纠正错误');

$listTarget = $resolve->invoke($service, $legacyRecycleEvent, [
    'jump_mode' => 'dual',
    'web_base_url' => 'https://example.com',
    'miniapp_appid' => 'wx123',
    'recycle_task_target' => 'list',
]);
$assert($listTarget['route_key'] === 'hsx_recycle.task.list', '回收任务列表配置未生效');
$assert($listTarget['miniapp_path'] === 'addon/hsx_recycle/pages/task/index?stage=check', '回收任务列表小程序路径错误');
$assert($listTarget['web_url'] === 'https://example.com/site/stat/task?stage=check', '回收任务列表网页路径错误');

echo "hsx_wecom target contract smoke passed\n";
