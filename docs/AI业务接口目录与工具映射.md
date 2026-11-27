# AI 业务接口目录 & 工具映射

记录 AI 能调用的「业务能力」、它们对应的 route 接口、以及权限口径。
AI 不直接连数据库，也不裸调 service —— 每个工具都**声明它对应的 route 接口权限**，执行前用
`AuthService::getAuthApiList()` 校验当前操作人是否拥有该接口权限，判定与走 route 时的 `AdminCheckRole` 完全一致。
即：**提问人没有某接口权限，AI 就查不到该数据。**

## 一、为什么不真的走 HTTP 自调 route

你最初想「走 route」是为了让控制器的权限中间件生效，这个诉求是对的。
但真去 `curl` 本站 route 有三个代价：多一层 HTTP/loopback、要转发并复现 token 鉴权上下文、出错点更多；
而且你的典型链路（查人 → 查主体 → 查账目）是**有依赖的串行**，并发也用不上。

采用的方案：工具内部直调 service（快、稳），但**补上与 route 一致的权限校验**。
鱼和熊掌兼得：保留 route 的权限管控，省掉 HTTP 开销。

## 二、当前工具 → 接口 → 权限

| AI 工具 | 业务能力 | 对应 route 接口 | 校验的接口权限(api_url) | 背后 service |
|---|---|---|---|---|
| `search_counterparty` | 按姓名/手机号搜对接人，带出所属主体 | `GET erp/counterparty/lists` | `erp/counterparty/lists` | ErpCounterpartyAdminService::memberOptions |
| `get_counterparty_dealings` | 主体级往来流水(含应收应付/含已结清)，每笔标注归属人 | `GET erp/finance/payable/lists`、`GET erp/finance/receivable/lists` | 同左两者都需 | ErpCounterpartyAdminService::counterpartyDealings |
| `get_finance_summary` | 全站财务汇总(应收/应付/净额/账户余额) | `GET erp/finance/board` | `erp/finance/board` | FinanceCounterpartyBalanceService::getSummary |
| `get_device_detail` | 账→货:按 device_id 查设备(货)明细(型号/IMEI/IMEI2/内存/颜色/质检/回收价/成本/售价/利润/从谁收/卖给谁)+时间线经手人 | `GET erp/device_trace/detail` | `erp/device_trace/detail` | DeviceTraceService::detail |
| `get_business_report` | 经营/利润报表(采购入库、销售毛利、期末在库)，默认最近7天 | `GET erp/asset/lists` | `erp/asset/lists` | ErpAssetService::businessReport |

> 时间默认：`get_counterparty_dealings` 与 `get_business_report` 默认只取**最近 7 天**；
> 查全部历史时 dealings 传 `all=true`，或两者均可显式传 `start_date/end_date`（或 report 传 `period`）。

> 校验逻辑：工具 `permissions` 里每条 `{api, method}` 都必须命中当前用户的
> `getAuthApiList()[method]`，否则该工具返回「无权限」，AI 会如实告诉用户查不了。

## 三、怎么加新工具（扩展业务接口）

在 `AiToolService::registry()` 加一项：

```php
'tool_name' => [
    'definition'  => [ /* OpenAI function schema：name/description/parameters */ ],
    'permissions' => [ ['api' => 'erp/xxx/yyy', 'method' => 'get'] ],  // 对应 route 权限
    'handler'     => fn(array $args) => $this->yourHandler($args),       // 内部调只读 service
],
```

再到对应场景 `AiSceneService` 的 `'tools' => [...]` 把工具名加进去。
建议同时在 `statusBefore/statusAfter` 补该工具的进度文案（流式体验）。

### 可继续接入的业务接口（候选）

| 业务能力 | route 接口 | 用途 |
|---|---|---|
| 结算记录 | `GET erp/finance/settlement/lists` | 查某主体历史结算 |
| 设备追溯 | `GET erp/device_trace/search` / `detail` | 设备从回收到卖出的链路 |
| 入库工作台 | `GET erp/stock_order/lists` | 入库单/采购情况 |
| 资金账户流水 | `GET erp/capital_account/ledger` | 账户收付明细 |
| 库存资产 | `GET erp/asset/lists` | 在库设备分布 |

## 四、流式与进度

- 端点：`POST erp/ai/stream`（SSE）。事件：`status`(进度) / `delta`(逐字答案) / `done` / `error` / `end`。
- 串行链路天然产生进度：每调一个工具，`statusBefore/statusAfter` 各推一条状态
  （如「正在查找张三相关的人…」→「找到 2 个相关的人：张三、张三丰」→「正在查询往来账目明细…」→「主体「韩」共 4 笔往来」）。
- 最终分析逐字流出。
- 前端：`api/ai-stream.ts` 用 `fetch` 流式读取，组件渲染进度步骤 + Markdown 答案。

> 注意：若用 nginx，SSE 需关闭对该路径的 gzip/proxy 缓冲（已发 `X-Accel-Buffering: no` 头；
> 如仍不实时，检查 nginx `gzip`/`proxy_buffering`）。
