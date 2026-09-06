<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$adminRoutes = file_get_contents($pluginRoot . '/app/adminapi/route/route.php');
$publicRoutes = file_get_contents($pluginRoot . '/app/api/route/route.php');
if ($adminRoutes === false || $publicRoutes === false) {
    throw new RuntimeException('无法读取企业微信路由文件');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertContains = static function (string $needle, string $content, string $message) use ($assert): void {
    $assert(str_contains($content, $needle), $message);
};

$publicContracts = [
    "Route::any('wecom/provider/event/:channel', 'addon\\hsx_wecom\\app\\api\\controller\\ProviderCallback@event')"
        => '缺少企业微信服务商事件回调入口',
    "Route::get('wecom/provider/authorize/complete/:channel', 'addon\\hsx_wecom\\app\\api\\controller\\ProviderAuthorize@complete')"
        => '缺少客户企业安装授权完成入口',
    "Route::get('wecom/provider/member/complete/:channel', 'addon\\hsx_wecom\\app\\api\\controller\\ProviderAuthorize@member')"
        => '缺少企业成员身份绑定回调入口',
];
foreach ($publicContracts as $route => $message) $assertContains($route, $publicRoutes, $message);

$adminContracts = [
    "Route::get('provider/config', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Provider@info')"
        => '缺少服务商配置读取入口',
    "Route::post('provider/config', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Provider@save')"
        => '缺少服务商配置保存入口',
    "Route::post('provider/test', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Provider@test')"
        => '缺少服务商配置校验入口',
    "Route::get('authorization/status', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Authorization@status')"
        => '缺少客户企业授权状态入口',
    "Route::post('authorization/start', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Authorization@start')"
        => '缺少客户企业一键授权入口',
    "Route::post('authorization/check', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Authorization@check')"
        => '缺少客户企业授权校验入口',
    "Route::post('staff/:uid/bind-url', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Staff@bindUrl')"
        => '缺少员工企业微信绑定入口',
    "Route::post('messages/test', 'addon\\hsx_wecom\\app\\adminapi\\controller\\Message@test')"
        => '缺少通知连通测试入口',
];
foreach ($adminContracts as $route => $message) $assertContains($route, $adminRoutes, $message);

$assertContains(
    'AdminCheckToken::class, AdminCheckRole::class, AdminLog::class',
    $adminRoutes,
    '服务商后台入口必须受登录、权限和操作日志中间件保护'
);
$assert(!str_contains($publicRoutes, 'AdminCheckToken'), '企业微信服务器回调不得依赖后台登录态');

$controllerContracts = [
    '/app/api/controller/ProviderCallback.php' => ['event'],
    '/app/api/controller/ProviderAuthorize.php' => ['complete', 'member'],
    '/app/adminapi/controller/Provider.php' => ['info', 'save', 'test'],
    '/app/adminapi/controller/Authorization.php' => ['status', 'start', 'check'],
    '/app/adminapi/controller/Staff.php' => ['bindUrl'],
    '/app/adminapi/controller/Message.php' => ['test'],
];
foreach ($controllerContracts as $relative => $methods) {
    $source = file_get_contents($pluginRoot . $relative);
    $assert($source !== false, '缺少路由对应控制器：' . $relative);
    foreach ($methods as $method) {
        $assert(
            preg_match('/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/', (string)$source) === 1,
            $relative . ' 缺少公开方法 ' . $method
        );
    }
}

echo "hsx_wecom provider route contract smoke passed\n";
