<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 3);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($path);

$event = require $root . '/app/event.php';
$assert(isset($event['listen']['HsxAiAgentExecuteRequested']), '必须注册智能体调度事件');

$agent = $read($root . '/app/service/core/AiAgentService.php');
foreach (['MAX_STEPS', 'MAX_TOOL_CALLS', 'AiToolRun', 'approved_tool_calls', 'tool_calls', 'toolProtocolUnsupported', 'tool_results', 'tool_result', '上一轮业务主题'] as $needle) {
    $assert(str_contains($agent, $needle), '智能体内核缺少：' . $needle);
}
$assert(str_contains($agent, 'default_tool_key') && str_contains($agent, 'HsxAiDefaultToolArgumentsRequested'), '业务插件必须能声明并解析专用智能体的首轮默认工具');
$intent = $read($root . '/app/service/core/AiToolIntentService.php');
$assert(str_contains($agent, 'AiToolIntentService') && str_contains($intent, 'HsxAiToolIntentRequested'), '自然语言业务意图必须由插件通过事件路由');
$assert(str_contains($intent, 'resolveAll') && str_contains($intent, "'aggregate'"), '老板经营总览必须支持多插件聚合调度');
$assert(str_contains($agent, 'previousToolResult') && str_contains($intent, 'previous_tool_result'), '连续追问必须将上一轮授权查询快照交给业务插件');
$assert(str_contains($agent, 'fallbackResult') && str_contains($agent, 'assistant_summary'), '默认业务查询必须在推理模型无正文时流式返回真实数据兜底');
$assert(str_contains($agent, 'allow_model_override') && str_contains($agent, 'allow_disabled'), '后台智能体必须保留模型测试通道能力');
$assert(!str_contains($agent, 'Erp') && !str_contains($agent, 'PhoneShop'), 'AI智能体内核不能直接依赖业务插件');

$tool = $read($root . '/app/service/core/AiToolService.php');
foreach (['requires_confirmation', 'approvalFingerprint', 'additionalProperties', 'permissions', 'integration_key'] as $needle) {
    $assert(str_contains($tool, $needle), 'AI工具安全边界缺少：' . $needle);
}

$gateway = $read($root . '/app/service/core/AiGatewayService.php');
$provider = $read($root . '/app/provider/OpenAiCompatibleProvider.php');
$assert(str_contains($gateway, "'_agent_internal'") && str_contains($gateway, "'role' => 'tool'"), '网关必须仅向内部智能体放行工具消息');
$assert(str_contains($provider, "'tool_calls'") && str_contains($provider, "'tool_choice'"), 'OpenAI兼容通道必须支持工具协议');
$assert(str_contains($provider, 'AiToolCallProtocolService'), 'OpenAI兼容通道必须适配模型文本工具协议并阻止协议泄漏');

$knowledge = $read($root . '/app/service/core/AiKnowledgeService.php');
$assert(str_contains($knowledge, 'HsxAiKnowledgeRetrieveRequested'), '必须保留可替换的RAG检索端口');
$actor = $read($root . '/app/service/core/AiActorContextService.php');
$assert(str_contains($actor, 'HsxAiActorContextEnrichRequested'), '必须保留身份权限扩展端口');

$assistant = $read($root . '/app/service/api/AiMallAssistantService.php');
$assert(str_contains($assistant, 'AiAgentService') && str_contains($assistant, "'data_scope'"), '商城助手必须接入统一智能体和服务端身份');
$config = $read($root . '/app/service/core/AiConfigService.php');
$assert(str_contains($config, 'business.admin_assistant') && str_contains($config, '经营管理助手'), '必须提供后台经营管理场景');
$admin = $read($root . '/app/service/admin/AiConfigAdminService.php');
$controller = $read($root . '/app/adminapi/controller/Config.php');
$adminAgent = $read($root . '/app/service/admin/AiAdminAgentService.php');
$assert(str_contains($adminAgent, 'getAuthMenuList') && str_contains($adminAgent, 'AiActorContextService'), '后台智能体必须注入当前管理员权限');
foreach (['business.owner', 'HsxAiAdminAgentRegistryRequested', 'allowed_tool_keys'] as $needle) {
    $assert(str_contains($adminAgent, $needle), '后台多智能体目录缺少：' . $needle);
}
$assert(str_contains($adminAgent, 'quick_actions') && str_contains($adminAgent, 'requestedDefaultToolKey'), '后台快捷问题必须显式绑定已授权工具，避免模型反复猜测');
$assert(substr_count($controller, "['default_tool_key', '']") >= 2, '同步和流式控制器必须接收快捷入口绑定的默认工具');
$assert(str_contains($adminAgent, "'current_prompt'") && str_contains($admin, "prepared['prompt']"), '会话入库后仍必须把本轮原始问题交给业务工具解析参数');
$assert(!str_contains($adminAgent, 'hsx_erp.') && !str_contains($adminAgent, 'hsx_recycle.') && !str_contains($adminAgent, 'phone_shop.'), 'AI中台不能硬编码业务插件工具');
$assert(str_contains($read($repo . '/niucloud/addon/hsx_erp/app/event.php'), 'HsxAiAdminAgentRegistryRequested'), 'ERP 必须通过事件注册后台智能体');
$assert(str_contains($read($repo . '/niucloud/addon/hsx_recycle/app/event.php'), 'HsxAiAdminAgentRegistryRequested'), '回收必须通过事件注册后台智能体');
$assert(str_contains($read($repo . '/niucloud/addon/phone_shop/app/event.php'), 'HsxAiAdminAgentRegistryRequested'), '商城必须通过事件注册后台智能体');
$assert(str_contains($adminAgent, 'is_site_admin') && str_contains($adminAgent, 'AiToolService'), '智能体可见性必须由站点身份和工具权限共同决定');
$memory = $read($root . '/app/service/admin/AiAdminAssistantConversationService.php');
foreach (['actor_type', "'admin'", 'conversation_id', '上一轮已授权业务查询快照', 'tool_result', 'findOwned', 'recent'] as $needle) {
    $assert(str_contains($memory, $needle), '后台经营助手会话记忆缺少：' . $needle);
}
$assert(str_contains($memory, 'public function delete') && str_contains($memory, 'Db::transaction'), '后台经营助手必须支持按所有权删除会话正文');
$assert(str_contains($admin, 'AiAdminAssistantConversationService') && str_contains($admin, "['type' => 'conversation']"), '后台经营助手必须保存会话并向前端返回会话标识');
$block = $read($root . '/app/service/core/AiBlockService.php');
foreach (['stat_grid', 'table', 'chart', 'notice', 'action_group'] as $type) {
    $assert(str_contains($block, $type), '结构化渲染块缺少：' . $type);
}
$page = $read($root . '/admin/views/assistant/index.vue');
$renderer = $read($repo . '/admin/src/addon/hsx_components/components/HsxMarkdownRenderer/index.vue');
$assert(str_contains($page, 'HsxBlockRenderer') && str_contains($page, 'agent_key'), 'PC经营助手必须使用统一组件和多智能体协议');
$assert(str_contains($page, 'getAiAssistantConversations') && str_contains($page, 'recentConversations'), 'PC经营助手必须展示当前管理员的历史会话');
$assert(str_contains($page, 'deleteAiAssistantConversation') && str_contains($page, 'deleteConversation'), 'PC经营助手必须提供带确认的会话删除入口');
$assert(str_contains($page, 'drainPaintQueue') && str_contains($page, 'takePaintCharacter'), 'PC经营助手必须逐字符绘制流式回答');
$assert(str_contains($page, 'default_tool_key') && str_contains($page, 'quickActions'), 'PC经营助手快捷入口必须把目标工具提交给服务端');
$assert(str_contains($page, 'messages.value[messages.value.length - 1]'), '流式回答必须修改 Vue 响应式消息代理，不能在结束时批量刷新');
$assert(str_contains($page, "action.approved !== true") && str_contains($page, "route.startsWith('/site/')"), 'PC经营助手必须校验可信操作和管理端路由');
$assert(str_contains($renderer, 'html: false') && str_contains($renderer, 'noopener noreferrer nofollow'), 'Markdown渲染必须禁用HTML并限制外链');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai agent foundation contract smoke passed\n";
