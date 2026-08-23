# HSX AI 智能体扩展契约

## 边界

- `hsx_ai` 负责模型调度、身份上下文、工具鉴权、审计、风险阻断和会话。
- ERP、回收、商城等插件保有业务数据和真正的读写逻辑。
- AI 插件不直接依赖业务插件的 Model/Service，只通过事件契约调用。

## 工具注册

业务插件监听 `HsxAiToolRegistryRequested`，返回：

```php
[
    'key' => 'your_plugin.order.search',
    'name' => '查询订单',
    'description' => '对模型说明何时调用和返回什么',
    'source_plugin' => 'your_plugin',
    'integration_key' => 'your_plugin',
    'scenes' => ['your_plugin.admin_assistant'],
    'auth' => 'admin', // public | member | admin
    'permissions' => ['your_plugin.order.list'],
    'read_only' => true,
    'risk_level' => 'read',
    'requires_confirmation' => false,
    'input_schema' => [
        'type' => 'object',
        'properties' => ['keyword' => ['type' => 'string', 'maxLength' => 100]],
        'additionalProperties' => false,
    ],
]
```

监听 `HsxAiToolExecuteRequested` 执行工具。执行结果必须同时返回注册时的 `tool_key` 和 `source_plugin`，防止不同插件之间冒名处理。`site_id` 和 `actor` 由 AI 内核注入，业务插件仍必须在自己的 Service 内做站点隔离和业务校验。

## 写操作

`read_only=false` 的工具默认需要二次确认。第一次调用只会返回 `approval_fingerprint`，不会修改数据。确认后由业务服务把指纹放入 `approved_tool_calls`再次调用。

## 知识检索

业务插件可监听 `HsxAiKnowledgeRetrieveRequested`。它可以背后使用 MySQL、向量库或第三方 RAG，AI 内核不限定存储方案。每条返回包含 `id`、`source_plugin`、`title`、`content`、`score`和 `metadata`。

## 智能体调用

服务端可发出 `HsxAiAgentExecuteRequested`，或在已鉴权 Service 内直接使用 `AiAgentService`。调用方必须传入已验证的 `actor`，不得采信前端传入的角色或权限。

业务插件可通过 `HsxAiActorContextEnrichRequested` 追加已验证的角色、权限、数据范围和业务属性。`site_id`、身份类型和用户 ID 不会被扩展结果覆盖。

内核当前限制单次最多 5 轮、10 次工具调用，并拦截相同参数的重复调用。每次调用均写入 `ai_tool_run`。

## PC 经营助手目录

业务插件可以监听 `HsxAiAdminAgentRegistryRequested` 追加岗位智能体。目录只声明智能体如何组织已有工具，不直接实现业务查询：

```php
[
    'key' => 'your_plugin.operator',
    'name' => '业务作业助手',
    'short_name' => '业务作业',
    'description' => '处理本岗位待办和业务查询。',
    'icon' => 'element List',
    'tone' => 'info',
    'tool_keys' => ['your_plugin.task.summary', 'your_plugin.order.search'],
    'quick_prompts' => ['我还有哪些待处理工作？'],
    'system_prompt' => '优先回答本人待办，事实必须来自工具。',
]
```

最终可见性由 AI 内核再次按业务接入开关、场景、管理员身份和原菜单权限过滤。角色名称只用于展示，不能作为授权依据。

## 结构化展示块

工具可以在结果中附带 `_presentation.blocks`。内核会先取出并清洗，再通过 SSE `block` 事件发送给 PC 端；该字段不会作为普通业务数据交给模型改写。

当前白名单为 `stat_grid`、`table`、`chart`、`notice`、`action_group`。`action_group` 中的跳转必须由业务服务生成，PC 端只执行 `approved=true` 且以 `/site/` 开头的管理端路由。模型 Markdown 中的链接和伪造 JSON 均不能触发业务操作。

成本、毛利、手机号等敏感信息仍应由业务插件在工具 Service 内按原业务字段权限裁剪，不能只依赖 AI 工具的菜单权限。
