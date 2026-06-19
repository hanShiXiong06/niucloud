# ERP 架构通读 · 问题清单与改进方案（评审稿，先方案后实施）

> 本文为对 `hsx_erp` 插件的一次系统性通读结论：① 架构与模块全貌；② 发现的问题（重点是你提到的"英文没翻译"，已定位根因）；③ 分层改进方案与实施分期。**实施是最后一步，待你过稿。**

---

## 一、架构全貌（读码确认）

**规模**：admin 服务 23 个、core 服务 4 个、控制器 16 个、模型 31 张、路由 102 条、字典 2 个核心（`ErpDict`/`FinanceDict`）+ 菜单/角色字典、监听器 14 个。

**分层（现状）**：

```
控制器(adminapi/controller) —— 16 个，薄，主要做参数收口 + 调服务
        │
业务服务(service/admin) —— 23 个，按域划分：
   资产/库存：ErpAssetService（核心·大）、ErpStandaloneInboundService、ErpInboundOrderService、
              ErpWarehouseService、ErpLocationAssignService、ErpStocktakeService
   整备/定价：ErpRefurbishmentService、ErpPricingService、ErpExternalPricingService(core)
   出库/追溯：ErpOutboundService、DeviceTraceService
   财务：FinancePayableService、FinanceReceivableService、FinanceSettlementService、
        FinanceCounterpartyBalanceService、FinanceReconciliationService、ErpReconciliationService、
        ErpCapitalAccountService、CoreFinanceLedgerService(core)
   看板/其它：ErpDashboardService、Ai*、RolePreset、SyncRecovery、Counterparty
        │
核心服务(service/core) —— 跨域/跨插件：ErpInboundService(入库总线)、CoreFinanceLedgerService(记账)、
        ErpExternalPricingService(收中台定价)、ErpCounterpartyService
        │
事件脊柱：
   - 操作事件 ErpOperationEvent（每步留痕，喂追溯/KPI）
   - 领域事件 ErpDomainEvent + outbox（异步发布，PublishOutboxEvent）
   - 跨插件全局事件：FinancePayableCreated / FinanceReceivableCreated / FinanceSettlementCompleted /
        ErpAssetCostAdjusted / CollectRecycleDeviceTrace / DeviceAssetPriceCompleted / ErpDomainEvent(ready_for_photo)
        │
字典层 ErpDict/FinanceDict：枚举常量 + 中文 map（状态/动作/类型/源/方式…）
```

**资产状态机（库存生命周期）**：
`待入库 pending_in → 待拍照 pending_photo(新) → 在库 in_stock → 整备中 refurbishing → 待销售定价 pending_pricing → 在售 available_for_sale → 销售锁定 locked → 已出库 outbound / 盘亏 lost`
分支：`inbound_rejected 入库驳回`。商城路与同行直卖路在"是否拍照/是否交中台"分流。

**读模型/看板现状**：`ErpDashboardService` + `ErpAssetService::getOverview()`（在手/可售/本月已售毛利/平均库龄）+ "经营报表"按时间区间算真实毛利。**KPI 偏少**，缺动销率/客单价/均台毛利/毛利率/今日多维等。

---

## 二、问题清单（按严重度）

### ★ P0：文案/字典漏译（你看到的"英文没翻译"——已定位根因）

**根因**：前端大量展示字段用 `X_text || 原始值` 兜底（见 `asset/list.vue:462/465/476/495/1484`、`capital_account/list.vue:175`）；`X_text` 由后端按字典 map 生成。**字典 map 一旦缺某个 key，界面就回退显示原始英文。** 而代码里写入的枚举值，已经领先字典 map。

**实锤（代码里写入但字典缺映射 → 显示英文）**：
- 库存流水 `action`：`photo_completed`（新增的"完成拍照"）→ 不在 `getLedgerActionMap` → 显示 `photo_completed`。
- 成本流水 `cost_type`：`buyout`（买断）、`recycle_cost_sync`（回收成本同步）→ 不在 `getCostTypeMap` → 显示英文。
- 整备项目 `item_type`：`part/labor/external/logistics/inspection/other` → 无中文 map → 整备工单里显示英文。
- 资金流水 `biz_type`、出库/结算等：同类风险，需逐一核对 map 完整度。

**本质不是"翻译没做"，是"字典与枚举各写各的、没有强约束保证一一对应"。** 修了眼前几个，以后加新枚举还会再漏。

### P1：KPI/看板偏薄
对比竞品，缺：今日销售台数/额/利润、均台毛利、毛利率、客单价、动销率；缺"昨天/今天/本月/更多"时间维度切换与"更多数据/查看明细"。

### P1：缺多维报表层
竞品的"对账统计按机型/员工/进货渠道/销售渠道汇总 + 明细筛选（销售渠道/结清状态/出库方式/操作人/时间/IMEI）"——我们是散在各页的查询，没有统一的"维度 × 指标 × 时间 × 筛选"聚合层。

### P2：ErpAssetService 偏大
核心域服务承载过多（入库确认/状态流转/定价决策/概览/列表富化…），后续可按子域拆分，降低维护成本。非紧急。

---

## 三、改进方案（分层 + 防漂移，不是打补丁）

### 方案 1：文案/字典**收口 + 防漂移机制**（治本，优先）
1. **补齐现有缺失映射**：把 `photo_completed / buyout / recycle_cost_sync / 整备item_type` 等全部补进字典 map（一次性扫清当前所有 `_text||原值` 字段的源枚举）。
2. **建立"枚举即字典"的单一真相**：每个枚举值在字典里必须有中文项；提供 `ErpDict::text($group, $key)` 统一取词，缺失时**回退为"中文占位+原值"而非纯英文**（如 `未知(photo_completed)`），让漏译一眼可见、且永不露纯英文给客户。
3. **加一道护栏（防回归）**：写一个轻量自检（开发期脚本/单测）：扫描代码里所有写入的 `action/cost_type/biz_type/item_type/...` 值，比对字典 map，缺失即报警。以后加枚举忘了配字典，CI/本地就拦住。

> 价值：一次根治"英文外露"，且**结构上**保证以后不再发生。这比逐个翻译更工程化。

### 方案 2：指标定义层（KPI 口径单一来源）
把每个 KPI 在**一个地方**定义清楚（公式/数据源/时间窗），全系统复用，避免各页面算不一致。需先与你**敲定口径**（见第四节待确认项）。

### 方案 3：报表聚合层（一个引擎，不是一堆页面）
统一"**维度 × 指标 × 时间区间 × 筛选**"：维度=机型/员工/进货渠道/销售渠道；指标=台数/成本/销售额/毛利/应收/应付;筛选=渠道/结清状态/出库方式/操作人/时间/IMEI。新增维度/指标=配置，不是复制页面。

### 方案 4：经营看板组装
基于 1–3，扩 `ErpDashboardService`：今日卡片 + 更多数据(昨天/今天/本月) + 查看明细(走报表层筛选)。

---

## 四、待你拍板（口径，工程上最该先定）
- **均台毛利** = 已售总毛利 ÷ 已售台数？
- **均摊毛利** 与均台毛利的区别？（是否把房租/人工等公共成本均摊进单台）
- **客单价** = 销售额 ÷ 成交台数（=均价），还是 ÷ 客户数（同一买家多台算一单）？
- **动销率** = 今日已售台数 ÷ 今日零点在库台数（你已给，确认沿用）。

---

## 五、实施分期（实施是最后一步）
- **阶段 0（治本·先做）**：方案 1 文案/字典收口 + 防漂移护栏。改动集中在字典层与少量服务，风险低、客户感知强（不再露英文）。
- **阶段 A**：方案 2 指标口径 + 方案 4 经营看板（今日台数/额/利润、均台毛利、毛利率、客单价、动销率）。
- **阶段 B**：方案 3 多维报表 + 钻取明细。
- **阶段 C**：方案 2(P2) ErpAssetService 拆分（可选、非紧急）。

> 建议先批阶段 0（文案治本）+ 确认第四节口径，我再开工。
