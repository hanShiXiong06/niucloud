# 二手机流转 · 操作事件脊柱 · KPI 架构方案

> 版本 v1 · 2026-06 · 主导：技术侧
> 目标：降本增效；员工 KPI 动态采集 + 关键动作考核；不碰 niucloud 核心、三插件独立、靠钩子串联。

---

## 0. 一句话

把一台二手机从「签收 → 质检 → 定回收价 → 打款 → 入库 → 拍照 → 定销售价 → 行情调价 → 销售下架」的**每一个关键动作，都化成一条标准「操作事件」追加到设备时间线上**。KPI、考核、成本、周转、对账，全部从这一条事件流派生——一处事实、处处复用。

---

## 1. 现状（已读代码验证）

### 1.1 三插件分工（与业务一致）

```
hsx_recycle  ── 回收流程（签收/质检/定回收价/打款）
     │  [钩子①] recycle.device.inbound_requested
     ▼
hsx_erp      ── 财务 + 入库 + 成本（资产成立、成本流水、仓库/库位）
     │  [钩子②] erp.asset.ready_for_photo.v1（Outbox 异步）
     ▼
hsx_device_asset ── 数据中台（拍照 + 定销售价）
     │  [钩子③] device_asset.price.completed.v1
     ▼
hsx_erp      ── 回写成本 / 可售状态
```

### 1.2 钩子链（producer↔consumer 事件名已逐一核对，**匹配、通畅**）

| 接缝 | 事件名 | 发射 | 接收 | 机制 |
|---|---|---|---|---|
| ① 回收→ERP 入库 | `ErpDeviceInboundRequested` / `recycle.device.inbound_requested` | `RecycleDeviceErpSyncService` | `DeviceInboundRequestedListener → ErpInboundService` | 同步 + 返回值反馈 |
| ② ERP→中台 拍照 | `ErpDomainEvent` / `erp.asset.ready_for_photo.v1` | `ErpAssetService.confirmInbound`(Outbox) | `ErpAssetReadyForPhotoListener` | **Outbox 异步可靠** |
| ③ 中台→ERP 回写 | `DeviceAssetPriceCompleted` / `device_asset.price.completed.v1` | `DeviceAssetService` | `DeviceAssetPriceCompletedListener → ErpExternalPricingService` | event() |

**强项**：Outbox 保证可靠投递、`.vN` 事件版本、`event_id` 幂等去重、插件间只发标准事件不跨插件读表。这套骨架是对的，能支撑长期演进。

### 1.3 关键缺口（本方案要补的）

1. **回收侧埋点不全** —— 签收/质检/定价/打款这几个最该考核的动作，处理器大多只改状态、未稳定写操作日志。**KPI 的洞主要在这里。**
2. **无统一操作事件脊柱** —— ERP/中台各发各的域事件，回收侧日志零散，三者没有归一的「谁/何时/什么动作/关键值」视图。
3. **无反馈钩子** —— 回收发完入库事件就「瞎」了，下游已拍照/已定价/已售/调价/下架不回流。
4. **链路尾巴未接** —— 行情调价、销售下架两段钩子未实现；③ 回写失败的补偿/死信可见性缺失。

---

## 2. 核心设计：统一「操作事件」脊柱

### 2.1 操作事件标准结构（三插件共用的契约）

```text
operation_event {
  event_id        // 幂等键
  site_id         // 多租户
  device_identity // 物理设备身份（贯穿全生命周期，跨插件对齐的锚）
  cycle_id        // 经营周期（一台机可多次回收）
  plugin          // recycle / erp / device_asset
  stage           // sign|check|price|pay|inbound|photo|sale_price|reprice|delist
  action          // 具体动作（开始质检/确认回收/打款/确认入库/拍照完成/销售定价...）
  operator        { id, name, type }   // 谁——KPI 的主语
  before_status / after_status         // 状态机前后
  value           { amount?, warehouse?, margin?, ... }  // 关键值——KPI 的客体
  occurred_at     // 何时——时效类 KPI 的基准
  source          { document_type, document_id }
}
```

### 2.2 落点原则：**埋点在「流程编排层」，不散在各 Handler**

- 回收侧：在 `CoreRecycleOrderFlowService` 执行「状态转换」的那**唯一一处**挂强制钩子——每次合法流转自动写一条操作事件。新增动作只加配置，不改采集代码。
- ERP 侧：`ErpOperationEvent` 已经是这个形态，保持。
- 中台侧：拍照/定价动作补齐操作事件。

> 这样「埋点」从分散的体力活变成框架级的一处保证，杜绝漏埋——这正是「动态采集」的工程落点。

### 2.3 跨插件归一

三插件各自写自己的操作事件存储（不强行合表，保持独立），由一个**只读的 KPI 聚合层**按 `device_identity / operator / 时间窗` 跨源聚合。读时归一，写时各管各的——符合插件独立 + 解耦红线。

---

## 3. KPI 规则模型（动态、可配置）

KPI = 在操作事件流上的**规则化聚合**，规则可配置，不写死：

```text
metric {
  key            // recycle.check.count / recycle.price.accuracy / ...
  source_action  // 过滤哪些操作事件
  aggregate      // count / avg(duration) / sum(margin) / rate ...
  group_by       // operator（员工）
  window         // 日/周/月
  weight         // 考核权重
}
```

**初版指标清单（关键动作考核）**

| 角色 | 指标 | 口径（源动作） |
|---|---|---|
| 质检员 | 日质检台数、平均时效、返工率 | 签收→质检完成 时间差 |
| 定价员 | 定价时效、**定价准确度**、毛利贡献 | 回收价 vs 最终成交价偏差 |
| 财务 | 打款时效 | 定价→打款 时间差 |
| 拍照/运营 | 拍照时效、上架时效 | 入库→拍照完成、拍照→上架 |
| 销售 | 周转天数、毛利率、调价命中率 | 入库→售出、售价-成本 |

新增考核项 = 加一条 metric 规则，零采集改动。

---

## 4. 钩子契约登记 + 补全

1. 把现有 `.v1` 事件整理成一份**权威事件契约清单**（名称/版本/payload/方向/幂等），新事件一律遵循。
2. 补 **反馈钩子**：erp/中台关键节点 → 回收（让回收能反映/统计下游状态）。
3. 补 **行情调价 / 销售下架** 两段（带 ErpPriceLog + 操作事件）。
4. ③ 回写失败的 **Outbox 重试可见性 + 死信**。

---

## 5. 分阶段落地（每阶段独立可交付、可验收）

- **P1 · 操作事件脊柱（地基）**：定义统一操作事件契约；回收侧在流程编排层补强制埋点；三插件操作事件归一只读视图。*不改业务流程，纯追加，低风险。*
- **P2 · KPI 聚合 + 规则引擎**：可配置 metric 规则；跨源聚合服务。
- **P3 · KPI 看板（用户可见）**：员工/团队维度的考核看板（这是你「只看体验」最终会看到的东西）。
- **P4 · 链路补全**：反馈钩子、行情调价、销售下架、失败补偿。

---

## 6. 边界与红线（始终遵守）

- **不碰 niucloud 核心**；样式/逻辑都在插件内。
- **插件独立**：只发标准事件，绝不跨插件读写对方的表。
- **版本化 + 幂等**：所有跨插件事件 `.vN` + `event_id` 去重。
- **设备身份是锚**：一切操作事件挂在 `device_identity / cycle` 上，保证全生命周期可追溯。

---

## 7. 下一步

从 **P1（操作事件脊柱）** 开始：先补齐回收侧流程编排层的统一埋点，把「签收/质检/定价/打款」这四个关键动作变成可追溯、可考核的操作事件。
