<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_wecom\app\service\core\WecomNotificationService;

$service = (new ReflectionClass(WecomNotificationService::class))->newInstanceWithoutConstructor();
$contract = new ReflectionMethod(WecomNotificationService::class, 'targetContractError');
$resolve = new ReflectionMethod(WecomNotificationService::class, 'target');
$contract->setAccessible(true);
$resolve->setAccessible(true);

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
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

// 线下订单明确携带两种入口，不得借用消费者端不存在于管理端的订单详情页。
$offlinePath = 'addon/phone_shop/pages/order/list?order_id=123';
$offlineWebPath = 'site/phone_shop/order/offline?order_id=123';
$offlineEvent = [
    'source_plugin' => 'phone_shop',
    'source_type' => 'offline_order',
    'source_id' => 123,
    'target' => [
        'plugin' => 'phone_shop',
        'route_key' => 'phone_shop.order.offline',
        'params' => ['order_id' => 123],
        'web_path' => $offlineWebPath,
        'miniapp_path' => $offlinePath,
    ],
    'target_path' => $offlineWebPath,
];
$config = ['web_base_url' => 'https://example.com', 'miniapp_appid' => 'wx123'];
$assert($contract->invoke($service, $offlineEvent) === '', '线下订单双入口必须通过目标契约校验');
foreach (['web', 'miniapp', 'dual'] as $mode) {
    $target = $resolve->invoke($service, $offlineEvent, $config + ['jump_mode' => $mode]);
    $assert($target['miniapp_path'] === ($mode === 'web' ? '' : $offlinePath), $mode . '模式的线下订单小程序路径错误');
    $assert($target['miniapp_appid'] === ($mode === 'web' ? '' : 'wx123'), $mode . '模式的小程序 AppID 错误');
    $assert($target['web_url'] === ($mode === 'miniapp' ? '' : 'https://example.com/' . $offlineWebPath), $mode . '模式的网页入口语义发生变化');

    $snapshot = $resolve->invoke($service, ['wecom_target' => $target], $config + ['jump_mode' => $mode]);
    $assert($snapshot === $target, $mode . '模式的有效目标快照不应改变');
}

// 从旧 target_path 兜底时只允许内部页面，绝不能把 PC 路径传给企业微信的小程序按钮。
$pcOnlyEvent = $offlineEvent;
unset($pcOnlyEvent['target']['miniapp_path']);
foreach (['web', 'miniapp', 'dual'] as $mode) {
    $target = $resolve->invoke($service, $pcOnlyEvent, $config + ['jump_mode' => $mode]);
    $assert($target['miniapp_path'] === ($mode === 'miniapp' ? 'app/pages/index/index' : ''), $mode . '模式错误地将 PC 路径作为小程序页面');
    $assert($target['web_url'] === 'https://example.com/' . $offlineWebPath, $mode . '模式必须保留 PC 业务的精确网页入口');
}

$legacyMobile = $resolve->invoke($service, ['target_path' => '/' . $offlinePath], $config + ['jump_mode' => 'dual']);
$assert($legacyMobile['miniapp_path'] === $offlinePath, '合法的小程序 target_path 兜底不应被移除');

$invalidPaths = [
    $offlineWebPath,
    '/' . $offlineWebPath,
    'https://example.com/' . $offlineWebPath,
    'javascript:alert(1)',
    'addon/phone_shop/../../site/order',
    "addon/phone_shop/pages/order/list\n?order_id=123",
    'addon/phone_shop/pages/order/list#order',
];
foreach ($invalidPaths as $invalidPath) {
    $event = $offlineEvent;
    $event['target']['miniapp_path'] = $invalidPath;
    $target = $resolve->invoke($service, $event, $config + ['jump_mode' => 'dual']);
    $assert($target['miniapp_path'] === '', '结构化目标未拦截非法小程序路径：' . json_encode($invalidPath));

    foreach (['web', 'miniapp', 'dual'] as $mode) {
        $snapshot = $resolve->invoke($service, ['wecom_target' => [
            'plugin' => 'phone_shop',
            'route_key' => 'phone_shop.order.offline',
            'web_url' => 'https://example.com/' . $offlineWebPath,
            'miniapp_appid' => 'wx123',
            'miniapp_path' => $invalidPath,
        ]], $config + ['jump_mode' => $mode]);
        $assert($snapshot['miniapp_path'] === ($mode === 'miniapp' ? 'app/pages/index/index' : ''), $mode . '模式的快照绕过了小程序路径防护');
        $assert($snapshot['web_url'] === 'https://example.com/' . $offlineWebPath, '快照移除非法小程序入口时不能丢失网页入口');
    }
}

// 两个业务事件发生处必须真的携带显式移动路径，防止只修测试夹具而遗漏发件代码。
$phoneShopRoot = dirname(__DIR__, 2) . '/phone_shop';
foreach ([
    '/app/listener/order/OfflineOrderSubmitted.php' => "'miniapp_path' => 'addon/phone_shop/pages/order/list' . \$query",
    '/app/service/api/order/OfflineOrderService.php' => "'miniapp_path' => 'addon/phone_shop/pages/order/list?order_id=' . \$orderId",
] as $file => $expected) {
    // phone_shop 是可选插件，未安装时只运行上述通用契约测试。
    if (!is_file($phoneShopRoot . $file)) continue;
    $source = file_get_contents($phoneShopRoot . $file);
    $assert($source !== false && str_contains($source, $expected), '线下订单事件未配置真实管理端路径：' . $file);
}

echo "hsx_wecom target contract smoke passed ({$checks} checks)\n";
