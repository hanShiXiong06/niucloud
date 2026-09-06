<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$projectRoot = dirname(dirname(dirname($pluginRoot)));

$read = static function (string $file): string {
    $content = file_get_contents($file);
    if ($content === false) throw new RuntimeException('无法读取文件：' . $file);
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$ticket = $read($pluginRoot . '/app/support/WecomEntryTicket.php');
$service = $read($pluginRoot . '/app/service/core/WecomEntryService.php');
$adminService = $read($pluginRoot . '/app/service/admin/WecomEntryAdminService.php');
$controller = $read($pluginRoot . '/app/adminapi/controller/Entry.php');
$routes = $read($pluginRoot . '/app/adminapi/route/route.php');
$page = $read($projectRoot . '/site-uniapp/src/app/pages/wecom/entry.vue');
$api = $read($projectRoot . '/site-uniapp/src/addon/hsx_wecom/api/index.ts');
$pages = $read($projectRoot . '/site-uniapp/src/pages.json');

$assert(str_contains($ticket, "hash_hmac('sha256'"), '入口票据必须使用 HMAC-SHA256 签名');
$assert(str_contains($ticket, "env('app.auth_key'"), '入口票据必须使用当前部署 app.auth_key');
$assert(str_contains($ticket, "'site_id' =>"), '入口票据缺少 site_id');
$assert(str_contains($ticket, "'miniapp_path' =>"), '入口票据缺少原始小程序路径');
$assert(str_contains($ticket, "'route_key' =>"), '入口票据缺少业务路由标识');
$assert(str_contains($ticket, "'exp' =>") && str_contains($ticket, "'nonce' =>"), '入口票据缺少有效期或随机数');
$assert(str_contains($service, "app/pages/wecom/entry?site_id="), '未生成统一的小程序安全入口 pagepath');
$assert(str_contains($adminService, '$siteId !== (int)$this->site_id'), '入口解析未核对请求站点与票据站点');
$assert(str_contains($adminService, '(int)$this->uid <= 0'), '入口解析未明确要求登录员工');
$assert(str_contains($controller, 'WecomEntryAdminService'), '入口控制器未调用入口服务');
$assert(str_contains($routes, "Route::get('entry/resolve'"), '缺少入口解析路由');
$assert(str_contains($routes, "Route::get('entry/resolve'") && str_contains($routes, 'AdminCheckToken::class, AdminLog::class'), '入口解析路由未受登录和站点权限中间件保护');
$assert(str_contains($page, "uni.setStorageSync('siteId'"), '小程序入口页未先切换站点');
$assert(str_contains($page, 'await resolveWecomEntry'), '小程序入口页未调用服务端验签接口');
$assert(str_contains($page, 'await getSiteInfo()'), '小程序入口页未刷新目标站点资料');
$assert(str_contains($api, "request.get('wecom/entry/resolve'"), '小程序缺少入口解析 API');
$assert(str_contains($pages, '"path": "app/pages/wecom/entry"'), 'pages.json 未注册企微入口页');

echo "hsx_wecom entry contract smoke passed\n";
