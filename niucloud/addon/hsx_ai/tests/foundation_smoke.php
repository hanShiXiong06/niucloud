<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($path);

$info = json_decode($read($root . '/info.json'), true);
$assert(is_array($info) && ($info['key'] ?? '') === 'hsx_ai', '插件标识必须为 hsx_ai');
$assert(($info['version'] ?? '') === '0.3.1', '当前版本必须为0.3.1');

$event = require $root . '/app/event.php';
$assert(isset($event['listen']['HsxAiExecuteRequested']), '必须注册AI同步执行契约');
$assert(isset($event['listen']['HsxAiCapabilityRequested']), '必须注册AI能力发现契约');
$assert(isset($event['listen']['HsxAiIntegrationAccessRequested']), '必须注册业务接入授权契约');

$sql = $read($root . '/sql/install.sql');
foreach (['ai_call_log', 'uk_site_request', 'prompt_hash', 'prompt_tokens', 'latency_ms'] as $needle) {
    $assert(str_contains($sql, $needle), 'AI调用日志SQL缺少：' . $needle);
}

$config = $read($root . '/app/service/core/AiConfigService.php');
foreach (['HSX_AI_CONFIG', 'SECRET_MASK', 'https://yunwu.ai', '/v1/models', '/v1/chat/completions'] as $needle) {
    $assert(str_contains($config, $needle), 'AI配置服务缺少：' . $needle);
}
$assert(str_contains($config, 'phone_shop.customer_assistant'), '必须内置商城选机场景');
$assert(str_contains($config, 'normalizeSpeech'), '必须提供可选语音配置');
$assert(str_contains($config, 'normalizeIntegrations'), '必须提供按站点保存的业务接入锁');
$assert(str_contains($config, "\$data['providers'] = \$this->preserveProviderSecrets"), '保存配置必须把还原后的密钥数组写回请求数据');

$provider = $read($root . '/app/provider/OpenAiCompatibleProvider.php');
foreach (['AiStreamingProviderInterface', 'Authorization: Bearer ', 'CURLOPT_FOLLOWLOCATION', 'CURLOPT_WRITEFUNCTION', 'CURLOPT_IPRESOLVE', 'CURL_HTTP_VERSION_1_1', 'appconnect_time', 'canRetryConnection', 'stream_options', 'reasoning_content', 'response_format', 'prompt_tokens', 'completion_tokens'] as $needle) {
    $assert(str_contains($provider, $needle), 'OpenAI兼容Provider缺少：' . $needle);
}

$gateway = $read($root . '/app/service/core/AiGatewayService.php');
foreach (['site_id', 'request_id', 'prompt_hash', 'redact_sensitive', 'response_mode', 'AiStreamingProviderInterface', 'first_token_ms', 'duplicateResult', '该AI请求正在处理中', '模型未按要求返回有效JSON'] as $needle) {
    $assert(str_contains($gateway, $needle), 'AI网关缺少：' . $needle);
}
$assert(!str_contains($gateway, 'ErpAsset::') && !str_contains($gateway, 'RecycleDevice::'), 'AI插件不能直接依赖业务模型');

$routes = $read($root . '/app/adminapi/route/route.php');
$assert(str_contains($routes, "Route::group('ai'"), 'AI后台路由必须使用ai前缀');
foreach (["'config/model'", "'config/integration'", "'config/speech'", "'playground/config'", "'playground/speech/stt'", "'playground/speech/tts'", "'speech/test'", "'provider/test'", "'provider/models'", "'execute'", "'stream'", "'logs'"] as $needle) {
    $assert(str_contains($routes, $needle), 'AI后台路由缺少：' . $needle);
}
$assert(!str_contains($routes, "Route::get('config',") && !str_contains($routes, "Route::post('config',"), '分菜单后不能保留可绕过权限的整页配置接口');
$menu = $read($root . '/app/dict/menu/site.php');
foreach (['模型与场景', '业务接入', '语音服务', '在线测试', '调用日志', 'ai/config/model', 'ai/config/integration', 'ai/config/speech', 'ai/speech/test', 'ai/playground/config', 'ai/playground/speech/stt', 'ai/playground/speech/tts', 'ai/stream'] as $needle) {
    $assert(str_contains($menu, $needle), 'AI后台分菜单或权限缺少：' . $needle);
}

$apiRoutes = $read($root . '/app/api/route/route.php');
foreach (['capability', 'chat', 'stream', 'speech/stt', 'speech/tts'] as $needle) {
    $assert(str_contains($apiRoutes, "'" . $needle . "'"), 'AI用户路由缺少：' . $needle);
}
$toolService = $read($root . '/app/service/core/AiToolService.php');
foreach (['HsxAiToolRegistryRequested', 'HsxAiToolExecuteRequested', 'permissions', 'site_id'] as $needle) {
    $assert(str_contains($toolService, $needle), 'AI工具白名单缺少：' . $needle);
}
$integrationService = $read($root . '/app/service/core/AiIntegrationService.php');
foreach (['HsxAiIntegrationRegistryRequested', 'isEnabled', 'assertEnabled', "'enabled' => (int)(\$enabledMap[\$key] ?? 0)"] as $needle) {
    $assert(str_contains($integrationService, $needle), 'AI业务接入锁缺少：' . $needle);
}

$frontend = $repo . '/admin/src/addon/hsx_ai/views/config/index.vue';
$assert(is_file($frontend), '缺少AI PC配置页面');
$packagedFrontend = $root . '/admin/views/config/index.vue';
$assert(is_file($packagedFrontend), '插件安装包缺少AI PC配置页面');
$frontendSource = is_file($frontend) ? $read($frontend) : '';
foreach (['模型通道', '业务场景', '业务接入', '已锁定', '语音服务', '在线测试', '流式', '首字', '调用日志'] as $needle) {
    $assert(str_contains($frontendSource, $needle), 'AI配置页面缺少：' . $needle);
}
$assert(str_contains($frontendSource, 'recognizePlaygroundSpeech') && str_contains($frontendSource, 'synthesizePlaygroundSpeech'), '在线测试必须闭环接入语音识别和回复朗读');
$assert(str_contains($frontendSource, 'encodeWav') && str_contains($frontendSource, '16000'), '浏览器录音必须转换为16k WAV后再识别');
$assert(!str_contains($frontendSource, '<el-tabs') && !str_contains($frontendSource, '<el-tab-pane'), 'AI配置必须使用框架菜单权限，不能退回页内Tab');
$assert(
    !is_file($packagedFrontend) || hash_file('sha256', $frontend) === hash_file('sha256', $packagedFrontend),
    '插件内PC页面与当前项目源码不一致'
);
foreach (['model', 'integration', 'speech', 'playground', 'log'] as $section) {
    $projectPage = $repo . '/admin/src/addon/hsx_ai/views/' . $section . '/index.vue';
    $packagePage = $root . '/admin/views/' . $section . '/index.vue';
    $assert(is_file($projectPage), '项目PC端缺少独立菜单页面：' . $section);
    $assert(is_file($packagePage), '插件包缺少独立菜单页面：' . $section);
    $assert(!is_file($projectPage) || !is_file($packagePage) || hash_file('sha256', $projectPage) === hash_file('sha256', $packagePage), '独立菜单页面与插件包不一致：' . $section);
}

$wapPage = $repo . '/uni-app/src/addon/hsx_ai/pages/chat/index.vue';
$assert(is_file($wapPage), '缺少AI商城用户端聊天页');
$packagedWapPage = $root . '/uni-app/pages/chat/index.vue';
$assert(is_file($packagedWapPage), '插件安装包缺少AI商城用户端聊天页');
$wapApi = $repo . '/uni-app/src/addon/hsx_ai/api/assistant.ts';
$packagedWapApi = $root . '/uni-app/api/assistant.ts';
$assert(is_file($wapApi) && is_file($packagedWapApi), '缺少AI商城用户端接口封装');
$assert(!is_file($packagedWapPage) || hash_file('sha256', $wapPage) === hash_file('sha256', $packagedWapPage), '插件内聊天页与当前项目源码不一致');
$assert(!is_file($packagedWapApi) || hash_file('sha256', $wapApi) === hash_file('sha256', $packagedWapApi), '插件内用户接口与当前项目源码不一致');
$uniPages = $read($root . '/package/uni-app-pages.php');
$assert(str_contains($uniPages, 'pages/chat/index'), '插件安装包缺少商城AI页面注册');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai foundation smoke passed\n";
