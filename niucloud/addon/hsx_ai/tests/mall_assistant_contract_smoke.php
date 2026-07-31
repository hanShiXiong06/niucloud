<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($path);

$assistant = $read($root . '/app/service/api/AiMallAssistantService.php');
foreach (['phone_shop.customer_assistant', 'AiBusinessContextService', 'AiIntegrationService', 'INTEGRATION', "'actor'", "'site_id'", 'throttle'] as $needle) {
    $assert(str_contains($assistant, $needle), '商城助手缺少安全上下文：' . $needle);
}
$assert(!str_contains($assistant, 'addon\\phone_shop\\'), 'AI插件不能直接依赖商城插件类');
$contextService = $read($root . '/app/service/core/AiBusinessContextService.php');
$assert(str_contains($contextService, 'HsxAiBusinessContextRequested'), 'AI插件必须通过事件请求业务上下文');

$controller = $read($root . '/app/api/controller/Assistant.php');
foreach (['assertSpeechCapability', 'throttleSpeech', 'AiSpeechService', 'speechToText', 'textToSpeech'] as $needle) {
    $assert(str_contains($controller, $needle), '商城助手语音入口缺少：' . $needle);
}
$assert(!str_contains($controller, 'BaiduSpeechService'), '商城入口不能直接绑定百度实现');

$phoneRoot = $repo . '/niucloud/addon/phone_shop';
$listener = $read($phoneRoot . '/app/listener/ai/AiMallBusinessContextRequested.php');
foreach (['request()->siteId()', 'GoodsService', "'in_stock' => 1", 'goods_cover_thumb_small', 'current_prompt', '系统提示'] as $needle) {
    $assert(str_contains($listener, $needle), '商城AI业务上下文缺少：' . $needle);
}
$event = $read($phoneRoot . '/app/event.php');
foreach (['HsxAiIntegrationRegistryRequested', 'HsxAiBusinessContextRequested', 'HsxAiToolRegistryRequested', 'HsxAiToolExecuteRequested'] as $needle) {
    $assert(str_contains($event, $needle), '商城插件缺少AI事件接入：' . $needle);
}
$guard = $read($phoneRoot . '/app/listener/ai/AiIntegrationGuard.php');
$assert(str_contains($guard, 'HsxAiIntegrationAccessRequested'), '商城插件必须反向校验AI通信授权');
$registry = $read($phoneRoot . '/app/listener/ai/AiIntegrationRegistryRequested.php');
foreach (["'key' => 'phone_shop'", "'capabilities'", '到货订阅'] as $needle) {
    $assert(str_contains($registry, $needle), '商城AI接入声明缺少：' . $needle);
}

$home = $read($repo . '/uni-app/src/addon/phone_shop/pages/index.vue');
$assert(str_contains($home, "request.get('ai/assistant/capability'"), '商城首页必须动态探测AI能力');
$assert(str_contains($home, 'aiAvailable.value = false'), 'AI未安装时商城入口必须静默隐藏');
$chat = $read($repo . '/uni-app/src/addon/hsx_ai/pages/chat/index.vue');
foreach (['streamAiAssistant', 'speechToText', 'textToSpeech', 'item.image', 'goods/detail'] as $needle) {
    $assert(str_contains($chat, $needle), '商城AI聊天页缺少：' . $needle);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai mall assistant contract smoke passed\n";
