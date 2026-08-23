<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\listener\ai\AiIntegrationRegistryRequested;
use addon\hsx_recycle\app\listener\ai\AiAdminAgentRegistryRequested;
use addon\hsx_recycle\app\listener\ai\AiDefaultToolArgumentsRequested;
use addon\hsx_recycle\app\listener\ai\AiToolIntentRequested;
use addon\hsx_recycle\app\listener\ai\AiToolRegistryRequested;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$root = dirname(__DIR__);
$event = require $root . '/app/event.php';
foreach (['HsxAiIntegrationRegistryRequested', 'HsxAiAdminAgentRegistryRequested', 'HsxAiToolRegistryRequested', 'HsxAiToolExecuteRequested', 'HsxAiDefaultToolArgumentsRequested', 'HsxAiToolIntentRequested'] as $name) {
    $assert(isset($event['listen'][$name]), '回收插件缺少 AI 事件：' . $name);
}
$integration = (new AiIntegrationRegistryRequested())->handle([])[0] ?? [];
$assert(($integration['key'] ?? '') === 'hsx_recycle', '回收 AI 接入必须使用稳定插件键');
$assert(in_array('business.admin_assistant', (array)($integration['scenes'] ?? []), true), '回收能力只能进入经营管理场景');
$agents = (new AiAdminAgentRegistryRequested())->handle([]);
$assert(($agents[0]['key'] ?? '') === 'business.recycle', '回收插件应只注册自身作业智能体');
$argumentsListener = new AiDefaultToolArgumentsRequested();
$mineArguments = $argumentsListener->handle(['tool_key' => 'hsx_recycle.workflow.summary', 'prompt' => '我本周还有哪些待处理工作？']);
$assert(($mineArguments['arguments']['mine'] ?? null) === true && ($mineArguments['arguments']['period'] ?? '') === 'week', '个人回收待办快捷查询参数解析错误');
$orderArguments = $argumentsListener->handle(['tool_key' => 'hsx_recycle.order.search', 'prompt' => '查询今天待签收的订单。']);
$assert(($orderArguments['arguments']['status'] ?? '') === 'pending_sign', '待签收订单快捷查询状态解析错误');
$assert(($orderArguments['arguments']['start_date'] ?? '') === date('Y-m-d'), '待签收订单快捷查询日期解析错误');
$intentListener = new AiToolIntentRequested();
$workflowIntent = $intentListener->handle([
    'prompt' => '全店回收流程堵在哪一步？',
    'available_tool_keys' => ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'],
]);
$assert(($workflowIntent['tool_key'] ?? '') === 'hsx_recycle.workflow.summary', '回收流程堵点问题必须直接路由待办总览');
$orderIntent = $intentListener->handle([
    'prompt' => '查询今天待签收的订单',
    'available_tool_keys' => ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'],
]);
$assert(($orderIntent['tool_key'] ?? '') === 'hsx_recycle.order.search' && ($orderIntent['arguments']['status'] ?? '') === 'pending_sign', '待签收自然语言必须直接路由订单明细');
$followupIntent = $intentListener->handle([
    'prompt' => '把刚才堵点的明细给我',
    'previous_tool_key' => 'hsx_recycle.workflow.summary',
    'previous_tool_result' => ['data' => ['pending' => ['sign' => 0, 'check' => 3, 'price' => 1]]],
    'available_tool_keys' => ['hsx_recycle.workflow.summary', 'hsx_recycle.order.search'],
]);
$assert(($followupIntent['tool_key'] ?? '') === 'hsx_recycle.order.search' && ($followupIntent['arguments']['status'] ?? '') === 'checking', '连续追问必须使用上轮堵点快照查明细');

$tools = (new AiToolRegistryRequested())->handle([]);
$keys = array_column($tools, 'key');
$assert(in_array('hsx_recycle.workflow.summary', $keys, true), '缺少回收流程待办工具');
$assert(in_array('hsx_recycle.order.search', $keys, true), '缺少回收订单查询工具');
foreach ($tools as $tool) {
    $assert(!empty($tool['read_only']), '回收首批 AI 工具必须只读');
    $assert(($tool['auth'] ?? '') === 'admin', '回收经营数据只能向管理员开放');
    $assert(in_array('recycle_order_list', (array)($tool['permissions'] ?? []), true), '回收工具必须复用订单菜单权限');
    $assert(($tool['input_schema']['additionalProperties'] ?? true) === false, '回收工具必须拒绝未知参数');
}
$service = (string)file_get_contents($root . '/app/service/core/ai/AiRecycleReadService.php');
$assert(substr_count($service, "site_id', '=', \$siteId") >= 5, '回收 AI 查询必须始终绑定站点');
$assert(str_contains($service, 'maskMobile'), '回收 AI 结果必须脱敏客户手机号');
$assert(str_contains($service, "'check_uid'") && str_contains($service, "'price_uid'") && str_contains($service, "'pay_uid'"), '个人完成量必须按实际操作人统计');
$assert(str_contains($service, '个人完成量不猜测'), '缺少可靠归属字段时必须明确说明，不能伪造个人数据');
$assert(str_contains($service, "'_presentation'") && str_contains($service, "'action_group'"), '回收工具必须提供可信结构化展示块');
$assert(str_contains($service, "'assistant_summary'"), '回收工具必须在模型超时时提供真实数据摘要兜底');
$assert(str_contains($service, '当前最大堵点'), '回收流程总览必须直接指出最大堵点');
$assert(!str_contains($service, 'addon\\hsx_ai'), '回收查询服务不能反向依赖 AI 插件实现');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_recycle AI read tools contract smoke passed\n";
