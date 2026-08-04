<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($path);

$conversationService = $read($root . '/app/service/api/AiConversationService.php');
foreach (["'site_id'", "'actor_type'", "'actor_id'", 'findOwned', 'saveUser', 'saveAssistant', 'request_id', 'resources_json'] as $needle) {
    $assert(str_contains($conversationService, $needle), '会话服务缺少租户隔离或消息能力：' . $needle);
}
$assert(!str_contains($conversationService, 'reasoning_content'), '会话消息禁止保存模型推理过程');
$actionService = $read($root . '/app/service/core/AiActionService.php');
foreach (['HsxAiActionRegistryRequested', "str_starts_with(\$route, '/addon/')", 'allowed_params', "'approved' => true"] as $needle) {
    $assert(str_contains($actionService, $needle), '结构化动作白名单缺少：' . $needle);
}
$assistant = $read($root . '/app/service/api/AiMallAssistantService.php');
foreach (['conversation_id', 'AiConversationService', 'AiRiskService', 'AiDemandService', 'saveFailure', "'type' => 'conversation'", 'conversationMeta'] as $needle) {
    $assert(str_contains($assistant, $needle), '商城助手未接入持久会话：' . $needle);
}
$riskService = $read($root . '/app/service/api/AiRiskService.php');
foreach (['freeze_ai', 'AiRiskEvent', 'prompt_hash', 'rate_limit', 'inspectPrompt'] as $needle) {
    $assert(str_contains($riskService, $needle), 'AI基础风控缺少：' . $needle);
}
$assert(!str_contains($riskService, "'prompt' =>"), '风险证据不能保存用户原始攻击提示');
$demandService = $read($root . '/app/service/api/AiDemandService.php');
foreach (['purchase_consultation', 'budget_min', 'requirements_json', 'source_message_ids_json'] as $needle) {
    $assert(str_contains($demandService, $needle), '结构化需求提取缺少：' . $needle);
}

$models = ['AiConversation', 'AiMessage', 'AiToolRun', 'AiRiskEvent', 'AiDemand'];
foreach ($models as $model) $assert(is_file($root . '/app/model/' . $model . '.php'), '缺少AI平台模型：' . $model);

$markdown = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiMarkdownContent.vue');
foreach (['markdown-code', 'markdown-list', 'markdown-quote', 'inline-strong', 'setClipboardData'] as $needle) {
    $assert(str_contains($markdown, $needle), '安全Markdown渲染器缺少：' . $needle);
}
$assert(!str_contains($markdown, 'v-html') && !str_contains($markdown, '<rich-text'), '模型内容不能通过HTML或rich-text直接注入');
$renderer = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiMessageRenderer.vue');
foreach (['AiMarkdownContent', 'message.resources', 'message.actions', '@click="copy"', '@click="$emit(\'speak\', message)"'] as $needle) {
    $assert(str_contains($renderer, $needle), '统一消息渲染器缺少：' . $needle);
}
$chat = $read($repo . '/uni-app/src/addon/hsx_ai/pages/chat/index.vue');
foreach (['AiMessageRenderer', 'openHistory', 'selectConversation', 'newConversation', 'conversation_id', 'history-panel', 'composer-setting'] as $needle) {
    $assert(str_contains($chat, $needle), '现代化聊天页缺少：' . $needle);
}

$adminPage = $read($repo . '/admin/src/addon/hsx_ai/views/conversation/index.vue');
foreach (['用户会话', 'getAiConversations', 'getAiConversation', 'message.resources', 'risk_level'] as $needle) {
    $assert(str_contains($adminPage, $needle), '后台会话审计缺少：' . $needle);
}
$riskPage = $read($repo . '/admin/src/addon/hsx_ai/views/risk/index.vue');
foreach (['风险事件', 'getAiRisks', '高频访问', '临时冻结 AI'] as $needle) {
    $assert(str_contains($riskPage, $needle), '后台风险审计缺少：' . $needle);
}
$menu = $read($root . '/app/dict/menu/site.php');
$assert(str_contains($menu, 'hsx_ai_conversation') && str_contains($menu, 'ai/conversations'), '后台会话菜单及权限缺失');

foreach ([
    [$repo . '/uni-app/src/addon/hsx_ai/components/AiMarkdownContent.vue', $root . '/uni-app/components/AiMarkdownContent.vue'],
    [$repo . '/uni-app/src/addon/hsx_ai/components/AiMessageRenderer.vue', $root . '/uni-app/components/AiMessageRenderer.vue'],
    [$repo . '/uni-app/src/addon/hsx_ai/components/AiBlockRenderer.vue', $root . '/uni-app/components/AiBlockRenderer.vue'],
    [$repo . '/uni-app/src/addon/hsx_ai/components/AiTableBlock.vue', $root . '/uni-app/components/AiTableBlock.vue'],
    [$repo . '/uni-app/src/addon/hsx_ai/components/AiChartBlock.vue', $root . '/uni-app/components/AiChartBlock.vue'],
    [$repo . '/uni-app/src/addon/hsx_ai/components/AiQuoteAggregate.vue', $root . '/uni-app/components/AiQuoteAggregate.vue'],
] as [$source, $package]) {
    $assert(is_file($source) && is_file($package), '聊天组件源码或安装包副本缺失');
    $assert(!is_file($source) || !is_file($package) || hash_file('sha256', $source) === hash_file('sha256', $package), '聊天组件源码与安装包副本不一致');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai conversation platform contract smoke passed\n";
