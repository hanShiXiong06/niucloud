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
$assert(($info['version'] ?? '') === '0.3.3', '当前版本必须为0.3.3');

$event = require $root . '/app/event.php';
$assert(isset($event['listen']['HsxAiExecuteRequested']), '必须注册AI同步执行契约');
$assert(isset($event['listen']['HsxAiCapabilityRequested']), '必须注册AI能力发现契约');
$assert(isset($event['listen']['HsxAiIntegrationAccessRequested']), '必须注册业务接入授权契约');
$assert(isset($event['listen']['DiyComponent']), '必须注册AI装修组件');

$sql = $read($root . '/sql/install.sql');
foreach (['ai_call_log', 'uk_site_request', 'prompt_hash', 'prompt_tokens', 'latency_ms'] as $needle) {
    $assert(str_contains($sql, $needle), 'AI调用日志SQL缺少：' . $needle);
}
foreach (['ai_conversation', 'ai_message', 'ai_tool_run', 'ai_risk_event', 'ai_demand', 'uk_conversation_request_role'] as $needle) {
    $assert(str_contains($sql, $needle), 'AI业务中台数据表缺少：' . $needle);
}
$assert(is_file($root . '/sql/update_0.3.3.sql'), '缺少0.3.3升级SQL');

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
$streamResponse = $read($root . '/app/support/AiStreamResponse.php');
foreach (['X-Accel-Buffering', 'no-transform', 'ob_implicit_flush'] as $needle) {
    $assert(str_contains($streamResponse, $needle), 'SSE响应缺少实时刷新保障：' . $needle);
}
$streamController = $read($root . '/app/adminapi/controller/Config.php');
$assert(str_contains($streamController, "str_repeat(' ', 2048)"), 'SSE首包必须越过代理的小响应缓冲区');

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
foreach (['conversations', 'conversations/:id/messages', 'conversations/:id/archive'] as $needle) {
    $assert(str_contains($apiRoutes, $needle), 'AI会话路由缺少：' . $needle);
}
$toolService = $read($root . '/app/service/core/AiToolService.php');
foreach (['HsxAiToolRegistryRequested', 'HsxAiToolExecuteRequested', 'permissions', 'site_id'] as $needle) {
    $assert(str_contains($toolService, $needle), 'AI工具白名单缺少：' . $needle);
}
$integrationService = $read($root . '/app/service/core/AiIntegrationService.php');
foreach (['HsxAiIntegrationRegistryRequested', 'isEnabled', 'assertEnabled', "'enabled' => (int)(\$enabledMap[\$key] ?? 0)"] as $needle) {
    $assert(str_contains($integrationService, $needle), 'AI业务接入锁缺少：' . $needle);
}
$skillService = $read($root . '/app/service/core/AiSkillService.php');
foreach (['HsxAiSkillRegistryRequested', 'quickActions', 'integration_key', 'submit_mode'] as $needle) {
    $assert(str_contains($skillService, $needle), 'AI快捷能力契约缺少：' . $needle);
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
$assert(!str_contains($frontendSource, 'testForm.response_mode = scene.response_mode'), '在线测试不能把场景JSON模式强制带入普通对话');
$assert(str_contains($frontendSource, '回答不是有效 JSON，已按文本保留'), '流式JSON校验失败时必须保留模型原始回答');
$assert(str_contains($frontendSource, 'showReasoning = ref(false)'), '模型推理内容必须默认隐藏');
$assert(str_contains($frontendSource, '直接对话') && str_contains($frontendSource, 'showAdvanced = ref(false)'), '在线测试必须默认直接对话并收起高级设置');
$assert(str_contains($frontendSource, 'if (shouldSend) await runTest()'), '语音识别成功后必须自动发送');
$assert(str_contains($frontendSource, 'unlockAnswerAudio') && str_contains($frontendSource, 'decodeAudioData'), '自动朗读必须提前解锁并使用浏览器音频上下文');
$assert(!str_contains($frontendSource, '<el-tabs') && !str_contains($frontendSource, '<el-tab-pane'), 'AI配置必须使用框架菜单权限，不能退回页内Tab');
$assert(
    !is_file($packagedFrontend) || hash_file('sha256', $frontend) === hash_file('sha256', $packagedFrontend),
    '插件内PC页面与当前项目源码不一致'
);
$frontendApi = $repo . '/admin/src/addon/hsx_ai/api/index.ts';
$frontendApiSource = is_file($frontendApi) ? $read($frontendApi) : '';
$assert(str_contains($frontendApiSource, 'nextPaint') && str_contains($frontendApiSource, 'await consume(block)'), 'SSE前端必须逐帧消费同批到达的内容事件');

$diyDict = $root . '/app/dict/diy/components.php';
$diyListener = $root . '/app/listener/diy/DiyComponentListener.php';
$assert(is_file($diyDict) && is_file($diyListener), 'AI插件缺少DIY组件字典或监听器');
$diyDictSource = is_file($diyDict) ? $read($diyDict) : '';
foreach (['HSX_AI_COMPONENT', 'AiAssistantEntry', 'edit-ai-assistant-entry', 'AI 选机助手'] as $needle) {
    $assert(str_contains($diyDictSource, $needle), 'AI装修组件定义缺少：' . $needle);
}
$diyAdmin = $repo . '/admin/src/addon/hsx_ai/views/diy/components/edit-ai-assistant-entry.vue';
$packagedDiyAdmin = $root . '/admin/views/diy/components/edit-ai-assistant-entry.vue';
$diyWap = $repo . '/uni-app/src/addon/hsx_ai/components/diy/ai-assistant-entry/index.vue';
$packagedDiyWap = $root . '/uni-app/components/diy/ai-assistant-entry/index.vue';
$diyGroup = $repo . '/uni-app/src/addon/components/diy/group/index.vue';
$assert(is_file($diyAdmin) && is_file($packagedDiyAdmin), '缺少AI装修后台编辑组件');
$assert(is_file($diyWap) && is_file($packagedDiyWap), '缺少AI装修移动端组件');
$assert(!is_file($diyAdmin) || !is_file($packagedDiyAdmin) || hash_file('sha256', $diyAdmin) === hash_file('sha256', $packagedDiyAdmin), 'AI装修后台组件与安装包不一致');
$assert(!is_file($diyWap) || !is_file($packagedDiyWap) || hash_file('sha256', $diyWap) === hash_file('sha256', $packagedDiyWap), 'AI装修移动端组件与安装包不一致');
$diyWapSource = is_file($diyWap) ? $read($diyWap) : '';
$assert(str_contains($diyWapSource, 'getAiAssistantCapability') && str_contains($diyWapSource, '/addon/hsx_ai/pages/chat/index'), 'AI装修入口必须校验能力并跳转对话页');
$diyGroupSource = is_file($diyGroup) ? $read($diyGroup) : '';
$assert(str_contains($diyGroupSource, "component.componentName == 'AiAssistantEntry'") && str_contains($diyGroupSource, 'diyAiAssistantEntry'), '当前移动端DIY渲染器未注册AI入口');
foreach (['model', 'integration', 'speech', 'playground', 'log'] as $section) {
    $projectPage = $repo . '/admin/src/addon/hsx_ai/views/' . $section . '/index.vue';
    $packagePage = $root . '/admin/views/' . $section . '/index.vue';
    $assert(is_file($projectPage), '项目PC端缺少独立菜单页面：' . $section);
    $assert(is_file($packagePage), '插件包缺少独立菜单页面：' . $section);
    $assert(!is_file($projectPage) || !is_file($packagePage) || hash_file('sha256', $projectPage) === hash_file('sha256', $packagePage), '独立菜单页面与插件包不一致：' . $section);
}
$conversationPage = $repo . '/admin/src/addon/hsx_ai/views/conversation/index.vue';
$packagedConversationPage = $root . '/admin/views/conversation/index.vue';
$assert(is_file($conversationPage) && is_file($packagedConversationPage), '缺少AI用户会话管理页面');
$assert(!is_file($conversationPage) || !is_file($packagedConversationPage) || hash_file('sha256', $conversationPage) === hash_file('sha256', $packagedConversationPage), '用户会话管理页面与插件包不一致');

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
