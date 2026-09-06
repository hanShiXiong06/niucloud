<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_wecom\app\service\core\WecomDeliveryContextService;
use addon\hsx_wecom\app\service\core\WecomNotificationService;

$pluginRoot = dirname(__DIR__);
$repoRoot = dirname(__DIR__, 4);
$delivery = file_get_contents($pluginRoot . '/app/service/core/WecomDeliveryContextService.php');
$authorization = file_get_contents($pluginRoot . '/app/service/core/WecomProviderAuthorizationService.php');
$notification = file_get_contents($pluginRoot . '/app/service/core/WecomNotificationService.php');
$client = file_get_contents($pluginRoot . '/app/service/core/WecomClient.php');
if ($delivery === false || $authorization === false || $notification === false || $client === false) {
    throw new RuntimeException('无法读取企业微信服务商核心代码');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertContains = static function (string $needle, string $content, string $message) use ($assert): void {
    $assert(str_contains($content, $needle), $message);
};

// 授权必须同时被站点与服务商通道约束，避免两个 SaaS 或两个客户企业串线。
$assertContains("['site_id', '=', \$siteId]", $delivery, '投递上下文查询缺少 site_id 约束');
$assertContains("['provider_suite_id', '=', (int)\$suite->id]", $delivery, '投递上下文查询缺少 suite 约束');
$assertContains("'corp_id' => (string)\$authorization->auth_corpid", $delivery, 'CorpID 必须来自当前站点授权记录');
$assertContains("'agent_id' => (int)\$authorization->agent_id", $delivery, 'AgentID 必须来自当前站点授权记录');
$assertContains("'miniapp_appid' => (string)\$suite->admin_miniapp_appid", $delivery, '管理端小程序 AppID 必须来自当前 SaaS 通道');

$assertContains(
    '(int)$occupied->site_id !== (int)$intent->site_id',
    $authorization,
    '同一个授权企业必须禁止绑定到本系统的另一个站点'
);
$assertContains(
    '(int)$authorization->site_id !== (int)($config[\'site_id\'] ?? 0)',
    $client,
    '取企业凭证前必须核对授权记录与当前站点归属'
);
$assertContains(
    '(int)$authorization->provider_suite_id !== (int)$suite->id',
    $client,
    '取企业凭证前必须核对授权记录与 suite 的归属'
);
$assertContains(
    "trim((string)\$authorization->auth_corpid) !== trim((string)(\$config['auth_corpid'] ?? \$config['corp_id'] ?? ''))",
    $client,
    '取企业凭证前必须核对授权 CorpID'
);
$assertContains(
    '(int)$binding->corp_authorization_id !== (int)($config[\'corp_authorization_id\'] ?? 0)',
    $notification,
    '发通知前必须核对员工身份属于当前客户企业授权'
);

// 无数据库/无网络校验投递上下文的最低可用条件。
$contextService = new WecomDeliveryContextService();
$validContext = [
    'enabled' => 1,
    'credential_mode' => 'provider',
    'provider_status' => 'authorized',
    'corp_authorization_id' => 17,
    'agent_id' => 208,
    'jump_mode' => 'miniapp',
    'miniapp_appid' => 'wx-current-admin',
];
$contextService->assertUsable($validContext);

$expectFailure = static function (array $context, string $message) use ($contextService, $assert): void {
    try {
        $contextService->assertUsable($context);
        $assert(false, $message);
    } catch (Throwable) {
        // 期望失败。
    }
};
$expectFailure(array_replace($validContext, ['corp_authorization_id' => 0]), '缺少企业授权记录时必须禁止投递');
$expectFailure(array_replace($validContext, ['agent_id' => 0]), '缺少 AgentID 时必须禁止投递');
$expectFailure(array_replace($validContext, ['miniapp_appid' => '']), '缺少管理端小程序 AppID 时必须禁止小程序跳转');

// 队列里的旧 AppID 只能作为历史快照；服务商模式发送时必须使用当前 SaaS 通道 AppID。
$notificationService = (new ReflectionClass(WecomNotificationService::class))->newInstanceWithoutConstructor();
$targetMethod = new ReflectionMethod(WecomNotificationService::class, 'target');
$targetMethod->setAccessible(true);
$event = [
    'wecom_target' => [
        'plugin' => 'hsx_wecom',
        'route_key' => 'hsx_wecom.home',
        'web_url' => 'https://example.test/site/hsx_wecom/config',
        'miniapp_appid' => 'wx-stale-other-saas',
        'miniapp_path' => 'app/pages/index/index',
    ],
];
$target = $targetMethod->invoke($notificationService, $event, [
    'credential_mode' => 'provider',
    'connection_mode' => 'provider',
    'miniapp_appid' => 'wx-current-admin',
]);
$assert($target['miniapp_appid'] === 'wx-current-admin', '服务商投递错误复用了另一套 SaaS 的旧 AppID');
$assert($target['miniapp_path'] === 'app/pages/index/index', '通知测试卡片未跳转后台管理端首页');

$assertContains("'miniapp_path' => 'app/pages/index/index'", $notification, '测试通知必须固定跳转后台管理端首页');
$pagesJson = file_get_contents($repoRoot . '/site-uniapp/src/pages.json');
$assert($pagesJson !== false, '无法读取管理端小程序 pages.json');
$assertContains('"path": "app/pages/index/index"', (string)$pagesJson, '后台管理端首页路径未在小程序中注册');

echo "hsx_wecom provider identity contract smoke passed\n";
