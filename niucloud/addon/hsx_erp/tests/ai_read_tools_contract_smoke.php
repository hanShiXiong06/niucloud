<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\listener\ai\AiIntegrationRegistryRequested;
use addon\hsx_erp\app\listener\ai\AiAdminAgentRegistryRequested;
use addon\hsx_erp\app\listener\ai\AiDefaultToolArgumentsRequested;
use addon\hsx_erp\app\listener\ai\AiToolIntentRequested;
use addon\hsx_erp\app\listener\ai\AiToolRegistryRequested;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$root = dirname(__DIR__);
$event = require $root . '/app/event.php';
foreach (['HsxAiIntegrationRegistryRequested', 'HsxAiAdminAgentRegistryRequested', 'HsxAiToolRegistryRequested', 'HsxAiToolExecuteRequested', 'HsxAiDefaultToolArgumentsRequested', 'HsxAiToolIntentRequested'] as $name) {
    $assert(isset($event['listen'][$name]), 'ERP 插件缺少 AI 事件：' . $name);
}
$integration = (new AiIntegrationRegistryRequested())->handle([])[0] ?? [];
$assert(($integration['key'] ?? '') === 'hsx_erp', 'ERP AI 接入必须使用稳定插件键');
$assert(in_array('business.admin_assistant', (array)($integration['scenes'] ?? []), true), 'ERP 能力只能进入经营管理场景');
$agents = (new AiAdminAgentRegistryRequested())->handle([]);
$assert(array_column($agents, 'key') === ['business.finance', 'business.inventory_sales'], 'ERP 应只注册自身财务和库存销售智能体');
$argumentsListener = new AiDefaultToolArgumentsRequested();
$intentListener = new AiToolIntentRequested();
$payableIntent = $intentListener->handle([
    'prompt' => '还有哪些应付款没处理？',
    'available_tool_keys' => ['hsx_erp.payable.summary', 'hsx_erp.payable.search'],
]);
$assert(($payableIntent['tool_key'] ?? '') === 'hsx_erp.payable.search' && ($payableIntent['arguments']['status'] ?? '') === 'open', '应付明细问题必须路由到未结清列表');
$salesArguments = $argumentsListener->handle(['tool_key' => 'hsx_erp.sales.summary', 'prompt' => '今天卖了多少台，毛利多少？']);
$assert(($salesArguments['arguments']['period'] ?? '') === 'today', '销售总览必须正确解析今日口径');
$ownerIntent = $intentListener->handle([
    'prompt' => '今天经营情况怎么样？',
    'agent_key' => 'business.owner',
    'available_tool_keys' => ['hsx_erp.stock.summary', 'hsx_erp.payable.summary', 'hsx_erp.receivable.summary', 'hsx_erp.sales.summary'],
]);
$assert(count($ownerIntent) === 4 && !empty($ownerIntent[0]['aggregate']), '老板经营总览必须聚合 ERP 库存、应收应付和销售口径');

$tools = (new AiToolRegistryRequested())->handle([]);
$keys = array_column($tools, 'key');
foreach (['hsx_erp.stock.summary', 'hsx_erp.payable.summary', 'hsx_erp.payable.search', 'hsx_erp.receivable.summary', 'hsx_erp.receivable.search', 'hsx_erp.sales.summary', 'hsx_erp.sales.search'] as $key) {
    $assert(in_array($key, $keys, true), '缺少 ERP AI 工具：' . $key);
}
foreach ($tools as $tool) {
    $assert(!empty($tool['read_only']), 'ERP 首批 AI 工具必须只读');
    $assert(($tool['auth'] ?? '') === 'admin', 'ERP 经营数据只能向管理员开放');
    $assert(!empty($tool['permissions']), 'ERP 工具必须声明现有菜单权限');
    $assert(($tool['input_schema']['additionalProperties'] ?? true) === false, 'ERP 工具必须拒绝未知参数');
}
$service = (string)file_get_contents($root . '/app/service/core/ai/AiErpReadService.php');
$assert(substr_count($service, "site_id', '=', \$siteId") >= 3, 'ERP AI 查询必须始终绑定站点');
$assert(str_contains($service, 'amount - settled_amount'), 'ERP 财务结果必须使用剩余金额口径');
$assert(str_contains($service, "'_presentation'") && str_contains($service, "'action_group'"), 'ERP 工具必须提供可信结构化展示块');
$assert(str_contains($service, "'restricted_fields'") && str_contains($service, "'hsx_erp_sale_profit_report'"), 'ERP AI 必须继承成本和毛利字段权限');
$assert(substr_count($service, "'assistant_summary'") >= 5, 'ERP 高频查询必须可以直接返回可核对的业务摘要');
$assert(!str_contains($service, 'addon\\hsx_ai'), 'ERP 查询服务不能反向依赖 AI 插件实现');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_erp AI read tools contract smoke passed\n";
