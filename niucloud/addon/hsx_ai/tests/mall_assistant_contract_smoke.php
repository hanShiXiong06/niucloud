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
$assert(str_contains($assistant, 'intent_state=needs_clarification'), '商城助手必须支持模糊购物意图澄清');
$assert(str_contains($assistant, '划线参考价当成正常售价'), '商城助手系统指令必须约束价格语义');
$assert(!str_contains($assistant, "'content' => '我只负责本站商品挑选和购买咨询。"), '商城助手不应保留机械式固定拒答');
$assert(!str_contains($assistant, 'addon\\phone_shop\\'), 'AI插件不能直接依赖商城插件类');
$contextService = $read($root . '/app/service/core/AiBusinessContextService.php');
$assert(str_contains($contextService, 'HsxAiBusinessContextRequested'), 'AI插件必须通过事件请求业务上下文');

$controller = $read($root . '/app/api/controller/Assistant.php');
foreach (['assertSpeechCapability', 'throttleSpeech', 'AiSpeechService', 'speechToText', 'textToSpeech'] as $needle) {
    $assert(str_contains($controller, $needle), '商城助手语音入口缺少：' . $needle);
}
$assert(!str_contains($controller, 'BaiduSpeechService'), '商城入口不能直接绑定百度实现');
$assert(str_contains($controller, "str_repeat(' ', 2048)"), 'SSE响应必须发送首屏填充，避免H5被代理缓冲成一次返回');
$assert(str_contains($assistant, "'auto_read_default'"), '能力接口必须下发前台自动朗读默认值');

$phoneRoot = $repo . '/niucloud/addon/phone_shop';
$listener = $read($phoneRoot . '/app/listener/ai/AiMallBusinessContextRequested.php');
foreach (['request()->siteId()', 'GoodsService', "'in_stock' => 1", 'goods_cover_thumb_small', 'current_prompt', '系统提示',
    'quality_inspection', 'market_reference_price', 'regular_price', 'member_price', 'member_context', 'current_price_label',
    'answer_requirements', 'publicInspection', 'CoreGoodsDescriptionService', 'facts_label', 'clarificationContext', 'categoryContext', 'category_browse'] as $needle) {
    $assert(str_contains($listener, $needle), '商城AI业务上下文缺少：' . $needle);
}
$assert(str_contains($listener, 'sensitive($name)'), '商城AI业务上下文必须过滤公开资料中的敏感字段');
$assert(str_contains($listener, '不得根据型号猜测'), '商城AI必须声明缺失参数边界');
$assert(str_contains($listener, '绝不能当作普通售价或成交价'), '商城AI必须区分划线参考价和真实售价');
$assert(str_contains($listener, '不要回复“我只负责本站商品挑选和购买咨询”'), '模糊购物意图必须澄清，不能机械拒绝');
$assert(str_contains($listener, 'customer_type_name'), '商城AI必须向模型提供同行/零售会员身份');
$assert(str_contains($listener, 'saving_vs_regular'), '商城AI必须提供会员相对普通售价的节省金额');
$assert(str_contains($listener, 'isShoppingFollowUp'), '商城AI必须结合上下文识别短追问');
$categoryQuery = $read($phoneRoot . '/app/listener/ai/AiMallCategoryQuery.php');
foreach (['GoodsCategoryService', 'request()->siteId()', 'category_id', 'full_name', 'has_children', 'child_list'] as $needle) {
    $assert(str_contains($categoryQuery, $needle), '商城AI分类查询缺少：' . $needle);
}
$toolRegistry = $read($phoneRoot . '/app/listener/ai/AiToolRegistryRequested.php');
$toolExecutor = $read($phoneRoot . '/app/listener/ai/AiToolExecuteRequested.php');
$assert(str_contains($toolRegistry, 'phone_shop.category.list'), '商城必须向AI注册分类查询工具');
$assert(str_contains($toolExecutor, 'phone_shop.category.list') && str_contains($toolExecutor, 'AiMallCategoryQuery'), '商城AI分类工具必须有独立执行器');

require_once $phoneRoot . '/app/listener/ai/AiMallBusinessContextRequested.php';
$intentListener = (new ReflectionClass(\addon\phone_shop\app\listener\ai\AiMallBusinessContextRequested::class))->newInstanceWithoutConstructor();
$shoppingMethod = new ReflectionMethod($intentListener, 'isShoppingQuestion');
$outOfScopeMethod = new ReflectionMethod($intentListener, 'isOutOfScopeQuestion');
$followUpMethod = new ReflectionMethod($intentListener, 'isShoppingFollowUp');
$categoryMethod = new ReflectionMethod($intentListener, 'isCategoryQuestion');
$clarificationMethod = new ReflectionMethod($intentListener, 'clarificationContext');
foreach ([$shoppingMethod, $outOfScopeMethod, $followUpMethod, $categoryMethod, $clarificationMethod] as $method) $method->setAccessible(true);
$assert($shoppingMethod->invoke($intentListener, '我想找一台给老人用的') === true, '用途型购物需求应被识别');
$assert($shoppingMethod->invoke($intentListener, '你好') === false, '问候不应被伪造成完整购物条件');
$assert($followUpMethod->invoke($intentListener, '还有别的吗') === true, '短追问应继承历史购物意图');
$assert($categoryMethod->invoke($intentListener, '你们都有哪些系列') === true, '分类/系列需求应进入分类查询');
$assert($outOfScopeMethod->invoke($intentListener, '今天天气怎么样') === true, '明确越界问题应被限制');
$clarification = $clarificationMethod->invoke($intentListener, '你好');
$clarificationContext = json_decode((string)($clarification['context'] ?? ''), true);
$assert(!empty($clarification['allowed']) && ($clarificationContext['intent_state'] ?? '') === 'needs_clarification', '模糊表达应进入澄清而不是拒答');
$event = $read($phoneRoot . '/app/event.php');
foreach (['HsxAiIntegrationRegistryRequested', 'HsxAiSkillRegistryRequested', 'HsxAiBusinessContextRequested', 'HsxAiToolRegistryRequested', 'HsxAiToolExecuteRequested'] as $needle) {
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
foreach (['streamAiAssistant', 'speechToText', 'speechBlobToText', 'recognizedAndSend', 'auto_read_default', 'if (completed && autoRead.value', 'recording-panel', 'AiMessageRenderer', 'goods/detail', 'loadingPhase', 'event.type === \'reasoning\'', 'event.type === \'blocks\'', 'capability.quick_actions', 'applyQuickAction', 'reactive<ChatMessage>', 'contentBuffer', 'scheduleScroll'] as $needle) {
    $assert(str_contains($chat, $needle), '商城AI聊天页缺少：' . $needle);
}
$messageRenderer = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiMessageRenderer.vue');
foreach (['item.image', 'facts_label', '匹配到的在售设备', 'current_price_label', 'regular_price', '普通价', 'thinking-dot', 'streaming-state', '价格、库存与质检信息'] as $needle) {
    $assert(str_contains($messageRenderer, $needle), '商城AI消息渲染器缺少：' . $needle);
}
$assistantApi = $read($repo . '/uni-app/src/addon/hsx_ai/api/assistant.ts');
foreach (['FormData', "body.append('audio'", "fetch(apiUrl('ai/assistant/speech/stt')"] as $needle) {
    $assert(str_contains($assistantApi, $needle), 'H5语音上传链路缺少：' . $needle);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai mall assistant contract smoke passed\n";
