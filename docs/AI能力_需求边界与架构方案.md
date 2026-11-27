# AI 能力接入 — 需求边界与架构方案

> 版本 v0.1（待评审）· 适用于 niucloud 平台（ThinkPHP8 + Vue3/Element Plus）
> 定位：以 **ERP 插件（hsx_erp）为承载主体** 的特色 AI 能力，逐步打通 **回收 / ERP / 商城** 三大插件
> 本文档只解决"做什么、边界在哪、怎么分层"，不含最终实现代码。评审通过后再进入编码阶段。

---

## 0. 一句话目标

在 hsx_erp 内建一套 **可配置、可切换、按权限分场景** 的 AI 能力中台：后端统一服务层 + 后端代理转发第三方大模型（云雾 API），前端把"AI 按钮"抽成通用组件，通过"场景注册"的方式被回收、ERP、商城三端复用，并支持站点自定义新场景。

---

## 1. 关键决策（已确认）

| 维度 | 决策 | 说明 |
|---|---|---|
| 承载方式 | **作为 hsx_erp 的特色功能** | AI 服务层、配置、通用组件都落在 hsx_erp。回收/商城通过"跨插件调用约定"复用，不重复造轮子。 |
| 配置层级 | **仅站点级** | 每个站点（商户）各自配置密钥、模型、开关。平台不内置默认密钥。配置走现有 `CoreConfigService`。 |
| 调用路径 | **后端代理转发** | 密钥只存后端，前端调本站接口，后端再请求云雾 API。可鉴权、可统计用量、可限流、可脱敏。 |
| 第三方 | 云雾 API（yunwu） | **已确认：完全 OpenAI 兼容**。`POST https://yunwu.ai/v1/chat/completions`，`Authorization: Bearer <key>`，标准 `messages`/`stream`/`usage` 结构。详见第 8 节接口对照表。 |
| 本期交付 | **需求边界 + 架构方案文档** | 即本文。编码分期进行（见第 9 节）。 |

> ⚠️ 架构补充建议：虽然落在 hsx_erp，但 AI 服务层应放在 `hsx_erp/app/service/core/ai/` 下、保持**对外低耦合**（不依赖 ERP 业务表），为将来抽成独立插件 `hsx_ai` 留好退路。回收/商城调用时只依赖"AI 服务接口"，不依赖 ERP 业务逻辑。

---

## 2. 范围边界（Scope）

### 2.1 本期要做（In Scope）

1. 后端 AI 服务层（统一入口 / 渠道适配 / 提示词与场景管理 / 调用日志）。
2. 站点级 AI 配置（密钥、Base URL、默认模型、可用模型列表、各场景开关与模型覆盖、用量上限）。
3. 后端代理接口：对话 / 流式（SSE）/ 场景执行 / 配置读写 / 用量查询。
4. 前端通用 AI 按钮组件 + AI 结果面板（抽屉/弹窗），支持流式输出、复制、重试、采纳。
5. 场景注册机制：四个内置场景（验机 / 总结 / 财务 / 财报数据）+ 站点自定义场景。
6. 权限分场景：复用现有 `menu_key` + `v-permission` 体系，每个 AI 场景一个权限节点。
7. ERP 端先落地，回收/商城通过约定接入（至少打通 1 个场景作为样板）。

### 2.2 本期不做（Out of Scope）

- 不自建/不微调大模型（"训练大模型"理解为：接入并按场景调优 Prompt/参数，而非自训权重）。
- 不做向量库 / RAG 知识库检索（列入二期可选）。
- 不做多模态训练，只在"验机"场景按云雾能力做图片理解（若文档支持）。
- 不做平台级统一密钥与计费分账（仅站点级配置）。
- 不做 Agent 多步工具调用编排（一期为"单轮/单场景"调用，二期再评估）。

### 2.3 明确的非目标

- AI 输出**不直接写入业务数据**。一律"建议 → 人工确认 → 采纳"，采纳动作走原有业务接口与权限校验。财务/财报场景尤其严格：AI 只读不写。

---

## 3. 场景清单与输入输出边界

> 每个场景定义五要素：触发位置、输入数据、输出形态、是否可写回、所需权限。AI 始终是"辅助"，关键动作回到既有业务流程。

### 3.1 AI 验机（回收 / ERP 入库）

- 触发：入库工作台、回收下单/质检页的设备卡片。
- 输入：设备型号、功能检测项、外观描述、（可选）图片。
- 输出：成色等级建议、问题点归纳、话术/备注文本、（可选）定价区间参考。
- 写回：否（仅填充到备注/质检表单草稿，人工确认）。
- 权限节点：`hsx_erp_ai_inspect`。

### 3.2 AI 总结（通用）

- 触发：任意列表/详情页的"AI 总结"按钮（订单、客户往来、设备流转记录等）。
- 输入：当前页选中数据 / 时间段聚合数据（后端按场景白名单取数）。
- 输出：要点摘要、异常提示、下一步建议（纯文本/Markdown）。
- 写回：否。
- 权限节点：`hsx_erp_ai_summary`。

### 3.3 AI 财务（ERP 财务）

- 触发：财务-结算记录 / 资金账户 / 应收应付页。
- 输入：**只读**的财务聚合数据（结算流水、应收应付、现金账户余额，经脱敏与白名单）。
- 输出：对账异常、欠款风险、回款建议、口径解释（呼应你近期"调成本/应付/应收"的复杂逻辑，AI 帮人话解释）。
- 写回：**严格否**。
- 权限节点：`hsx_erp_ai_finance`（建议单独高权限，默认仅财务/管理员）。

### 3.4 AI 财报数据（ERP 经营分析）

- 触发：经营报表 / 看板页。
- 输入：周期内经营指标（采购、销售、毛利、库存周转等聚合结果）。
- 输出：经营分析结论、同环比解读、风险与机会、（可选）生成可视化建议描述。
- 写回：否。
- 权限节点：`hsx_erp_ai_report`。

### 3.5 自定义场景（站点可配）

- 站点管理员在后台新建场景：场景 key、名称、所属模块、Prompt 模板、取数来源（从一组**预置白名单数据源**中勾选）、模型与参数、绑定的权限节点。
- 约束：自定义场景**只能选预置数据源**，不能任意 SQL/读全库，保证安全边界。
- 权限节点：动态生成，命名约定 `hsx_erp_ai_custom_{key}`。

### 3.6 场景边界对照表

| 场景 | 触发端 | 取数范围 | 可写回 | 权限节点 | 风险级别 |
|---|---|---|---|---|---|
| 验机 | 回收/ERP | 单设备 | 草稿填充 | `..._ai_inspect` | 中 |
| 总结 | 三端通用 | 选中/时段(白名单) | 否 | `..._ai_summary` | 低 |
| 财务 | ERP | 财务聚合(脱敏只读) | 否 | `..._ai_finance` | 高 |
| 财报数据 | ERP | 经营聚合(只读) | 否 | `..._ai_report` | 中高 |
| 自定义 | 站点配置 | 预置白名单 | 否 | `..._ai_custom_{key}` | 取决于数据源 |

---

## 4. 总体架构

```
┌──────────────────────────── 前端 (admin / web / uni-app) ────────────────────────────┐
│  通用组件 <ai-button> + <ai-panel>                                                    │
│      │  props: scene, getPayload(), permission                                        │
│      ▼                                                                                 │
│  场景注册表 aiScenes[]  ──►  api/ai.ts (本站接口, 非直连第三方)                         │
└───────────────────────────────────────────────┬──────────────────────────────────────┘
                                                 │ HTTPS (token + site-id 头, 现有拦截器)
┌────────────────────────────── 后端 hsx_erp (ThinkPHP8) ───────────────┼───────────────┐
│  adminapi/controller/Ai.php  (路由 Route::group('erp/ai'))             ▼               │
│        │  中间件: AdminCheckToken + AdminCheckRole(场景权限)                            │
│        ▼                                                                                │
│  service/core/ai/                                                                       │
│    ├─ AiService.php          统一入口: run(scene, payload, site_id)                     │
│    ├─ AiConfigService.php    站点配置读写 (基于 CoreConfigService, key=HSX_AI)          │
│    ├─ AiSceneService.php     场景注册/取数白名单/Prompt 组装                            │
│    ├─ AiChannelService.php   渠道适配 (云雾/OpenAI 兼容), 代理转发 + SSE 流式            │
│    └─ AiLogService.php       调用日志/用量统计/限流                                     │
│        │                                                                                │
│        ▼ 后端代理 (密钥只在此处)                                                        │
└────────────────────────────────────────────────┬──────────────────────────────────────┘
                                                  ▼
                                    云雾 API (OpenAI 兼容 /v1/chat/completions)

  回收(hsx_recycle) / 商城(shop)  ──►  通过"跨插件调用约定"复用 AiService（见 6.3）
```

---

## 5. 后端设计

### 5.1 目录落点（hsx_erp）

```
niucloud/addon/hsx_erp/
├─ app/
│  ├─ adminapi/
│  │  ├─ controller/Ai.php              # AI 接口控制器
│  │  └─ route/route.php                # 追加 erp/ai/* 路由组
│  ├─ service/core/ai/                  # 低耦合, 可被回收/商城调用, 将来可抽插件
│  │  ├─ AiService.php
│  │  ├─ AiConfigService.php
│  │  ├─ AiSceneService.php
│  │  ├─ AiChannelService.php
│  │  └─ AiLogService.php
│  ├─ model/ai/                         # AiScene(自定义场景表) + AiLog(调用日志表)
│  ├─ dict/
│  │  ├─ menu/site.php                  # 追加 AI 配置菜单 + 各场景权限节点
│  │  └─ ai/SceneDict.php               # 内置场景 + 数据源白名单常量
│  └─ validate/ai/                      # 配置与请求参数校验
└─ sql/                                 # 新增 2 张表的建表语句
```

> 内置场景的 Prompt 模板放代码/字典；自定义场景存表。两条路径都经 `AiSceneService` 统一组装。

### 5.2 站点级配置（复用现有机制）

直接用现有的 `CoreConfigService->getConfig($site_id, $key)` / `setConfig($site_id, $key, array $value)`，与支付、地图 key 等同一套机制，无需新建配置表。

- 配置 key：`HSX_AI`（建议在 `ConfigKeyDict` 增加常量）。
- 配置结构（示意）：

```jsonc
{
  "enabled": true,
  "base_url": "https://yunwu.ai/v1",     // 已确认; 国内部署建议改 CDN 分站 https://yunwu.zeabur.app/v1 或国内站 https://api3.wlai.vip/v1
  "api_key": "sk-***",                   // 云雾"令牌", 仅后端读取, 前端返回时脱敏为 sk-****1234
  "default_model": "gpt-4o-mini",
  "available_models": ["gpt-4o-mini", "gpt-4o", "deepseek-chat", "claude-3-5-sonnet"],
  "timeout": 60,
  "daily_token_limit": 200000,           // 站点用量上限, 0=不限
  "scenes": {                            // 各场景开关与模型覆盖
    "inspect":  { "enabled": true,  "model": "gpt-4o" },
    "summary":  { "enabled": true,  "model": "" },     // 空=用 default_model
    "finance":  { "enabled": false, "model": "" },
    "report":   { "enabled": true,  "model": "" }
  }
}
```

"AI 能配置能切换"即体现在此：站点可改密钥/Base URL、维护可用模型列表、每个场景单独开关并指定模型。

### 5.3 接口清单（adminapi，路由组 `erp/ai`）

| 方法 | 路径 | 用途 | 权限 |
|---|---|---|---|
| GET | `erp/ai/config` | 读取站点 AI 配置（密钥脱敏） | `hsx_erp_ai_config` |
| POST | `erp/ai/config` | 保存站点 AI 配置 | `hsx_erp_ai_config` |
| GET | `erp/ai/models` | 拉取/校验可用模型（可选连通测试） | `hsx_erp_ai_config` |
| GET | `erp/ai/scenes` | 列出当前用户可见/可用场景 | 登录态 |
| POST | `erp/ai/run` | 执行某场景（非流式，返回完整结果） | 场景级 |
| POST | `erp/ai/stream` | 执行某场景（SSE 流式） | 场景级 |
| GET | `erp/ai/usage` | 用量/调用日志 | `hsx_erp_ai_config` |
| POST | `erp/ai/scene/save` | 新建/编辑自定义场景 | `hsx_erp_ai_config` |
| DELETE | `erp/ai/scene/:id` | 删除自定义场景 | `hsx_erp_ai_config` |

`run` / `stream` 请求体约定：

```jsonc
{
  "scene": "summary",          // 场景 key
  "biz": { "ids": [123,456] }, // 业务定位参数; 由后端按场景白名单取数, 前端不传敏感原文
  "model": "",                 // 可选, 覆盖场景默认
  "extra": { "note": "" }      // 可选附加上下文
}
```

> 关键安全点：前端只传"业务定位参数"（如订单 id），**真实数据由后端按场景白名单取数并脱敏**后再拼 Prompt，避免前端把全量敏感数据塞给模型，也便于审计。

### 5.4 权限与中间件

- 复用 `Route::group('erp', ...)` 同款中间件 `AdminCheckToken` + `AdminCheckRole`。
- 在 `dict/menu/site.php` 为每个场景追加 `menu_type=2`（接口/按钮权限）节点：`hsx_erp_ai_config / _inspect / _summary / _finance / _report`，并归到"二手机 ERP"菜单下的"AI 助手"分组。
- 服务层二次校验：`AiService::run()` 内再判一次场景开关 + 站点 `enabled`，双保险。

### 5.5 渠道适配与代理转发

- `AiChannelService` 封装对云雾的 HTTP 调用，按 OpenAI 兼容形态组织 `messages`，统一处理超时、重试、错误码映射。
- 流式：后端以 SSE 透传模型增量输出到前端 `erp/ai/stream`；非流式走 `run`。
- 适配层预留 `driver` 概念（yunwu / openai / 其它），便于未来换源，对上层场景无感。

### 5.6 日志、用量与限流

- `AiLog` 表记录：site_id、uid、scene、model、token 用量、耗时、状态、错误。
- 用量上限：按 `daily_token_limit` 在 `AiService` 入口校验，超限直接拒绝并提示。
- 便于后续做成本核算与异常排查。

---

## 6. 前端设计

### 6.1 通用 AI 按钮组件

落点：`admin/src/addon/hsx_erp/components/ai-button/`（先在 ERP 内沉淀，稳定后可上移到全局 `admin/src/components/`）。

- `<ai-button>`：统一外观（图标+文案+loading），点击后打开 `<ai-panel>`。
- `<ai-panel>`：抽屉/弹窗，负责发起请求、流式渲染（复用现有 `markdown` 组件渲染输出）、复制/重试/采纳。
- Props 约定：

```ts
interface AiButtonProps {
  scene: string                         // 场景 key, 对应后端
  getPayload: () => Record<string, any> // 返回 biz 定位参数(如 {ids:[...]})
  permission?: string                   // 权限节点, 用于 v-permission 控制显隐
  model?: string                        // 可选覆盖
  mode?: 'run' | 'stream'               // 默认 stream
  onAdopt?: (text: string) => void      // 采纳回调, 由调用方决定写回逻辑
}
```

- 权限显隐：组件外层用现有 `v-permission="scene权限"` 指令；无权限不渲染按钮。
- "采纳"不由组件写库，回调交给业务页，确保写回仍走原有业务接口+校验。

### 6.2 场景注册表与 API

- `admin/src/addon/hsx_erp/api/ai.ts`：封装 `runAi / streamAi / getAiConfig / saveAiConfig / getAiScenes` 等，走现有 `request`（自动带 token/site-id）。
- 场景注册表（前端侧，描述 UI 元信息）：

```ts
// admin/src/addon/hsx_erp/ai/scenes.ts
export const aiScenes = [
  { key: 'inspect', name: 'AI 验机', icon: '...', permission: 'hsx_erp_ai_inspect' },
  { key: 'summary', name: 'AI 总结', icon: '...', permission: 'hsx_erp_ai_summary' },
  { key: 'finance', name: 'AI 财务', icon: '...', permission: 'hsx_erp_ai_finance' },
  { key: 'report',  name: 'AI 财报数据', icon: '...', permission: 'hsx_erp_ai_report' },
  // 自定义场景由 erp/ai/scenes 接口动态合并
]
```

- 业务页接入只需一行：

```vue
<ai-button scene="summary" :get-payload="() => ({ ids: selectedIds })"
           permission="hsx_erp_ai_summary" @adopt="fillRemark" />
```

### 6.3 跨插件复用约定（回收 / 商城）

- 后端：回收/商城在自己的 controller 里 `new \addon\hsx_erp\app\service\core\ai\AiService()->run(...)` 即可（hsx_erp 已安装为前置依赖）。在各自插件的 `depends`/安装说明中声明依赖 hsx_erp。
- 前端：把 `ai-button` 组件与 `api/ai.ts` 作为"可被引用的公共资产"。最简做法是稳定后上移到 `admin/src/components/`；过渡期回收/商城从 `@/addon/hsx_erp/components/ai-button` 引入。
- 取数白名单需为回收/商城各自的数据源单独登记（在 `AiSceneService` 的数据源注册表中），避免 ERP 服务越权读别的插件库。

---

## 7. 数据与安全边界

- 密钥只存后端配置，接口返回一律脱敏；保存时支持"不变更则不覆盖"。
- 前端永远不直连第三方，所有调用经本站代理。
- 取数走"场景 → 白名单数据源 → 脱敏"三步，禁止前端透传敏感原文、禁止自定义场景任意取数。
- 财务/财报场景默认关闭、单独高权限、只读不写。
- 全链路日志可审计；用量上限防滥用。
- 输出免责：面板固定提示"AI 生成内容仅供参考，请人工核对后使用"。

---

## 8. 云雾接口对照表（已确认）

云雾为**标准 OpenAI 兼容**接口，`AiChannelService` 直接按 OpenAI Chat Completions 形态实现即可。

### 8.1 连接参数

| 项 | 值 |
|---|---|
| Endpoint | `POST {base_url}/chat/completions` |
| Base URL（默认） | `https://yunwu.ai/v1` |
| Base URL（国内推荐） | CDN 分站 `https://yunwu.zeabur.app/v1`（全球 60+ 节点，国内快）；国内站 `https://api3.wlai.vip/v1` |
| 鉴权 | Header `Authorization: Bearer <令牌>` |
| Content-Type | `application/json` |
| 渠道/费率分组 | 在云雾"令牌"页设置，**不在 API 参数里传**；模型名 + 令牌分组共同决定渠道与费率（default 混合 *1，纯 AZ *1.5，直连 Claude *16 等） |
| 额度查询 | `https://chaxun.wlai.vip/` 输入 key 即可查余额/消费（仅运维参考，不入主流程） |

> 国内部署强烈建议把 `base_url` 设为可配置，默认填 CDN 分站，避免主站美国集群延迟。这点已体现在 5.2 配置结构里。

### 8.2 请求体（与 OpenAI 一致）

```jsonc
{
  "model": "gpt-4o",                          // 取自配置 available_models
  "messages": [
    { "role": "system", "content": "<场景 Prompt 模板>" },
    { "role": "user",   "content": "<后端按白名单取数+脱敏后的业务上下文>" }
  ],
  "temperature": 0.7,
  "stream": false                             // 流式场景置 true, 走 SSE
}
```

### 8.3 响应体（非流式）

```jsonc
{
  "id": "chatcmpl-abc123",
  "object": "chat.completion",
  "model": "gpt-4o",
  "usage": { "prompt_tokens": 13, "completion_tokens": 7, "total_tokens": 20 },
  "choices": [
    { "index": 0, "finish_reason": "stop",
      "message": { "role": "assistant", "content": "..." } }
  ]
}
```

- 取值：输出 = `choices[0].message.content`；用量入 `AiLog` = `usage.total_tokens`；正常结束判 `finish_reason == "stop"`。
- 流式：请求带 `stream:true`，响应为 `data: {chunk}\n\n` 的 SSE，增量在 `choices[0].delta.content`，以 `data: [DONE]` 结束 —— `AiChannelService` 透传到前端 `erp/ai/stream`。

### 8.4 端点清单与本期取舍（已确认）

云雾端点很多(音频/图像/Anthropic 原生/Gemini 原生/Responses 等),**本期只用下面两个,其余不介入**:

| 端点 | 用途 | 本期 |
|---|---|---|
| `POST /v1/chat/completions` | 对话/流式/识图/函数调用/结构化输出 —— 四大场景全靠它 | ✅ 用 |
| `GET /v1/models` | 列出可用模型,驱动配置页"可用模型"动态下拉 | ✅ 用 |
| `/v1/audio/*`（转写/TTS/翻译） | 语音 | ❌ 不介入 |
| `/v1/images/*`、Gemini 图片生成 | 图像生成/编辑 | ❌ 不介入 |
| `/v1/embeddings` | 向量(留给二期 RAG) | ❌ 暂不 |
| `/v1/messages`（Anthropic 原生）、`/v1/responses`、Gemini 原生 | 其它协议形态 | ❌ 不介入,统一走 OpenAI `/v1/chat/completions` |

设计含义:
- `AiChannelService` 只实现 OpenAI Chat Completions 一种形态;Claude/Gemini/国产模型都通过云雾的 `/v1/chat/completions` 兼容层调用,**对上层场景完全无感**。
- 配置接口 `erp/ai/models` 后端直接转发 `GET /v1/models`,前端配置页"可用模型"改为动态拉取(可保留手动增删覆盖)。
- "AI 验机吃图"用同一端点的多模态 `content` 数组(图片 url/base64)+ vision 模型(如 gpt-4o),无需新端点。

### 8.5 仍需你确认/留意的点

1. **令牌分组**：财务/财报建议用稳定渠道（如官转/直连），验机/总结可用低费率分组（限时特价 *0.6）——决定你在云雾后台给令牌选哪个分组。
2. **图片理解（验机吃图）**：需选支持 vision 的模型（如 gpt-4o），`content` 用 OpenAI 多模态数组格式；确认你常用模型是否支持。
3. **模型清单**：`available_models` 的具体取值，从云雾首页"支持模型"列表选定后填入站点配置。
4. API 令牌：你说稍后给 —— 联调时填入站点配置 `api_key` 即可，代码侧无需硬编码。

---

## 9. 分期计划（建议）

**一期（地基 + ERP 落地）**
1. 站点级配置 `HSX_AI` + 配置页（密钥/模型/开关）。
2. 后端服务层骨架（AiService/Config/Scene/Channel/Log）+ 代理转发（非流式优先，再加 SSE）。
3. 通用 `<ai-button>` / `<ai-panel>` 组件 + `api/ai.ts`。
4. 落地"AI 总结"作为样板场景（最低风险、最通用）。
5. 权限节点接入。

**二期（场景铺开 + 跨插件）**
6. 验机 / 财务 / 财报数据三场景。
7. 回收、商城各打通 1 个场景（验证跨插件约定）。
8. 自定义场景配置（场景表 + 数据源白名单 UI）。
9. 用量统计与限流看板。

**三期（可选增强）**
10. 知识库/RAG、模型多渠道切换 UI、AI 验机吃图、Agent 多步编排评估。

---

## 10. 风险与开放问题

| 风险/问题 | 说明 | 处置 |
|---|---|---|
| 强耦合到 ERP | 落在 hsx_erp，回收/商城需依赖它 | 服务层放 `service/core/ai` 保持低耦合，预留抽 `hsx_ai` 插件退路 |
| 第三方接口不确定 | 云雾文档未联调 | 适配层抽象 driver；先用 OpenAI 兼容形态，联调后校正 |
| 数据安全 | AI 误读/泄露财务数据 | 白名单取数+脱敏+只读+高权限+日志 |
| 成本失控 | 站点滥用 | 用量上限+日志+按场景开关 |
| 输出可信度 | 模型幻觉 | 不自动写回，人工采纳，免责提示 |
| 多端一致 | admin/web/uni-app 三端组件 | 一期只做 admin，二期视需要再移植 |

**需你拍板的开放问题**
- "训练大模型"是否就是"接入+Prompt 调优"，还是后续真有自训/微调诉求？（影响是否预留训练数据管线）
- 财务/财报场景默认对哪些角色开放？
- 是否需要我下一步用浏览器读云雾 apifox 文档、补一份接口对照表，然后据此细化服务层骨架代码？
```
