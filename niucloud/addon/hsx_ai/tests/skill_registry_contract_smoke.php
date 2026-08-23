<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($path);

$skillService = $read($root . '/app/service/core/AiSkillService.php');
foreach (['HsxAiSkillRegistryRequested', 'AiIntegrationService', "['fill', 'send']", 'integration_key', 'audiences', 'array_slice($actions, 0, 20)'] as $needle) {
    $assert(str_contains($skillService, $needle), 'AI技能聚合契约缺少：' . $needle);
}
$assistant = $read($root . '/app/service/api/AiMallAssistantService.php');
$assert(str_contains($assistant, "'quick_actions' => (new AiSkillService())"), '能力接口必须下发插件快捷能力列表');
$assert(str_contains($assistant, "['type' => 'blocks'"), '流式接口必须下发结构化业务块');
$contextService = $read($root . '/app/service/core/AiBusinessContextService.php');
$assert(str_contains($contextService, "'blocks' =>"), '业务上下文必须聚合结构化业务块');
$assert(str_contains($contextService, 'AiBlockService'), '业务块必须经过统一协议边界');
$blockService = $read($root . '/app/service/core/AiBlockService.php');
foreach (["'table'", "'chart'", '160000', 'array_slice($blocks, 0, 12)'] as $needle) {
    $assert(str_contains($blockService, $needle), '结构化渲染协议缺少：' . $needle);
}
require_once $root . '/app/service/core/AiBlockService.php';
$safeBlocks = (new \addon\hsx_ai\app\service\core\AiBlockService())->sanitize([
    ['type' => 'table', 'source_plugin' => 'hsx_erp', 'data' => ['columns' => ['name'], 'rows' => [['测试']]]],
    ['type' => 'html', 'data' => ['html' => '<script>alert(1)</script>']],
]);
$assert(count($safeBlocks) === 1 && ($safeBlocks[0]['type'] ?? '') === 'table', '业务块必须过滤未授权渲染类型');

$phoneRoot = $repo . '/niucloud/addon/phone_shop';
require_once $phoneRoot . '/app/listener/ai/AiSkillRegistryRequested.php';
$phoneSkills = (new \addon\phone_shop\app\listener\ai\AiSkillRegistryRequested())->handle([]);
$assert(count($phoneSkills) >= 2, '商城插件必须能够注册多个快捷能力');
$assert(count(array_unique(array_column($phoneSkills, 'key'))) === count($phoneSkills), '商城快捷能力 key 必须唯一');

$quoteRoot = $repo . '/niucloud/addon/recycle_daheng_quote';
require_once $quoteRoot . '/app/listener/ai/AiSkillRegistryRequested.php';
$quoteSkills = (new \addon\recycle_daheng_quote\app\listener\ai\AiSkillRegistryRequested())->handle([]);
$assert(count($quoteSkills) === 3, '报价插件必须注册问价、七天行情、历史报价三个入口');
$assert(in_array('seven_day_trend', array_column($quoteSkills, 'key'), true), '报价插件缺少固定七天行情入口');
$quoteEvent = $read($quoteRoot . '/app/event.php');
foreach (['HsxAiIntegrationRegistryRequested', 'HsxAiSkillRegistryRequested', 'HsxAiBusinessContextRequested'] as $needle) {
    $assert(str_contains($quoteEvent, $needle), '报价插件 AI 事件缺少：' . $needle);
}
$quoteReader = $read($quoteRoot . '/app/service/core/quotation/AiQuoteReadService.php');
foreach (['used_latest_snapshot', 'no_snapshot', 'adjustment_notice', "' +6 days'", "'mode' => 'trend'", "'mode' => 'quote_aggregate'", "'source_count'", 'resolveModels'] as $needle) {
    $assert(str_contains($quoteReader, $needle), '报价只读事实服务缺少：' . $needle);
}
require_once $quoteRoot . '/app/service/core/quotation/AiQuoteReadService.php';
$reader = (new ReflectionClass(\addon\recycle_daheng_quote\app\service\core\quotation\AiQuoteReadService::class))->newInstanceWithoutConstructor();
$timeMethod = new ReflectionMethod($reader, 'resolveTime');
$timeMethod->setAccessible(true);
$recent = $timeMethod->invoke($reader, '最近行情怎么样');
$month = $timeMethod->invoke($reader, '看2026年7月行情');
$history = $timeMethod->invoke($reader, '一周前收多少钱');
$assert(!empty($recent['trend']) && $recent['exact'] === false, '最近行情必须解析为连续七天窗口');
$assert(($month['date'] ?? '') === '2026-07-01' && !empty($month['trend']), '月份行情必须从当月1日开始展示七天');
$assert(empty($history['trend']) && !empty($history['exact']), '一周前问价必须查询精确历史快照');

$chat = $read($repo . '/uni-app/src/addon/hsx_ai/pages/chat/index.vue');
foreach (['capability.quick_actions', 'applyQuickAction', 'quick-action-track', "event.type === 'blocks'"] as $needle) {
    $assert(str_contains($chat, $needle), '聊天页快捷能力交互缺少：' . $needle);
}
$renderer = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiMessageRenderer.vue');
$assert(str_contains($renderer, 'AiBlockRenderer'), '消息渲染器必须接入结构化业务块');
$markdown = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiMarkdownContent.vue');
foreach (['isTableSeparator', 'splitTableRow', "block.type === 'table'", 'markdown-table-scroll'] as $needle) {
    $assert(str_contains($markdown, $needle), 'Markdown 表格渲染缺少：' . $needle);
}
$blockRenderer = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiBlockRenderer.vue');
foreach (['AiTableBlock', 'AiChartBlock', "block.type === 'table'", "block.type === 'chart'"] as $needle) {
    $assert(str_contains($blockRenderer, $needle), '通用 Block 渲染器缺少：' . $needle);
}
$assert(!str_contains($blockRenderer, '<component :is='), 'uni-app Block 渲染器不能使用小程序不支持的动态组件');
$trend = $read($repo . '/uni-app/src/addon/hsx_ai/components/AiPriceTrend.vue');
foreach (['@qiun/ucharts', "type: 'line'", '固定7天', 'selected_grade'] as $needle) {
    $assert(str_contains($trend, $needle), '七天行情图缺少：' . $needle);
}
$package = json_decode($read($root . '/package/uni-app-package.json'), true);
$assert(!empty($package['dependencies']['@qiun/ucharts']), 'AI 插件必须自行声明行情图依赖');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai skill registry contract smoke passed\n";
