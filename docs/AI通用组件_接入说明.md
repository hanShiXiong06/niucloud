# AI 通用组件 `<ai-assistant>` 接入说明

一个按钮 + 抽屉的通用 AI 对话组件。业务页一行接入，带着本页数据按场景提问。

组件位置：`admin/src/addon/hsx_erp/components/ai-assistant/index.vue`
后端入口：`POST erp/ai/run`（场景执行）、`GET erp/ai/scenes`（场景清单）

## 最简用法（自由提问）

```vue
<script setup lang="ts">
import AiAssistant from '@/addon/hsx_erp/components/ai-assistant/index.vue'
</script>

<template>
  <ai-assistant scene="general" button-text="问 AI" />
</template>
```

## 带本页数据 + 预设问题（推荐）

```vue
<ai-assistant
  scene="summary"
  permission="hsx_erp_ai_summary"
  title="AI 总结"
  button-text="AI 总结"
  :question="'请总结这批订单的关键情况与风险。'"
  :get-payload="() => ({ context: { 订单: selectedRows } })"
/>
```

- `get-payload` 在打开抽屉时调用，返回 `{ context?, question? }`。`context` 可以是对象或文本，后端会拼进 Prompt。
- 打开后若已有问题或数据，默认自动生成（`auto` 默认 true）。
- 用户可在抽屉里改问题再「重新生成」。

## 采纳回写（让 AI 结果填进表单）

```vue
<ai-assistant
  scene="inspect"
  :adoptable="true"
  :get-payload="() => ({ context: deviceInfo })"
  @adopt="(text) => form.remark = text"
/>
```

`@adopt` 把 AI 文本回传给业务页，由你决定写到哪。组件本身不写库，写回仍走你原有的业务接口与校验。

## Props 速查

| prop | 说明 | 默认 |
|---|---|---|
| `scene` | 场景 key：general/summary/finance/report/inspect | `general` |
| `permission` | 权限节点，无权限则按钮不渲染 | 无 |
| `title` / `button-text` | 抽屉标题 / 按钮文案 | — |
| `button-type` `button-size` `link` `plain` `icon` | 按钮样式 | primary/default |
| `question` | 预设问题 | 空 |
| `get-payload` | 返回 `{context?,question?}`，可异步 | 无 |
| `model` | 覆盖默认模型 | 用站点默认 |
| `auto` | 打开后自动生成 | true |
| `editable-question` | 允许用户改问题 | true |
| `adoptable` | 显示「采纳」按钮 | false |
| `drawer-size` | 抽屉宽度 | 42% |

## 内置场景（后端 `AiSceneService`）

| scene | 名称 | 建议权限 | 取数风险 |
|---|---|---|---|
| `general` | AI 助手 | hsx_erp_ai_run | — |
| `summary` | AI 总结 | hsx_erp_ai_summary | 低 |
| `finance` | AI 财务分析 | hsx_erp_ai_finance | 高 |
| `report` | AI 经营分析 | hsx_erp_ai_report | 中高 |
| `inspect` | AI 验机 | hsx_erp_ai_inspect | 中 |

新增场景：在 `niucloud/addon/hsx_erp/app/service/core/ai/AiSceneService.php` 的 `builtin()` 里加一项（key、name、system 提示词、temperature、permission）即可，前端 `scene` 传新 key。

## 跨插件复用（回收 / 商城）

- 前端：其它插件视图里同样 `import AiAssistant from '@/addon/hsx_erp/components/ai-assistant/index.vue'` 即可，接口统一走 `erp/ai/run`。
- 后端：其它插件需要直接调用时 `new \addon\hsx_erp\app\service\admin\AiService()`（需声明依赖 hsx_erp）。

## 已接入示范

财务中心（`views/finance/board.vue`）头部「AI 财务分析」按钮：把应收应付汇总 + 资金账户 + 往来未结前 20 条作为上下文，自动给出财务分析。

## Function Calling：让 AI 按需查数据（财务场景已启用）

`finance` 场景已接入「只读查询工具」，AI 可自主按需取数，不再只依赖前端传的 context。
例如在 AI 财务分析里直接问「查 15832117979 的账目往来」，AI 会：

1. 调 `search_counterparty('15832117979')` → 拿到 counterparty_id
2. 调 `get_counterparty_balance(id)` → 拿到应收应付/净额/可折账
3. 基于真实数据给出分析

安全边界：工具全部走现有 admin Service，内部以 `site_id` 锁定本站点，**只读、不可跨站、不可执行任意 SQL**；AI 只能从白名单工具里挑（`AiToolService`）。

给某场景开工具：在 `AiSceneService` 该场景加 `'tools' => ['工具名', ...]` 即可。
新增工具：在 `AiToolService::registry()` 加一项（OpenAI function schema + handler，handler 调只读 Service）。

> 需模型支持 function calling（gpt-4o / gpt-4o-mini / deepseek-chat / claude 等都支持）。

## 当前限制（后续迭代）

- 非流式输出（点一次等完整结果）；SSE 流式打字机效果待加。
- 低敏场景（如 summary）仍用前端传 context，够用；要更准可仿 finance 改成工具取数。
- 工具目前覆盖「搜往来对象 / 查某客户余额 / 全站汇总」三项，往来明细行级查询待补（注意 finance 表 counterparty_id 为对接人级，需按主体展开）。
- 服务端按场景的细粒度权限校验待加（现为前端 `permission` 隐藏按钮 + `/ai/run` 整体鉴权）。
- 调用日志与用量看板未做（已规划）。
