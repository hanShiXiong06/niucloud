# 二手机回收 / ERP 代码走查报告（第一轮）

**走查范围**：`hsx_recycle`、`hsx_device_asset`、`hsx_erp` 三个插件
**走查维度**：架构与事件解耦 · 安全与数据 · 产品体验/UX · 代码质量
**走查时间**：2026-06-12
**当前分支**：`dev`（领先 `origin/dev` 7 个提交，尚未推送）

---

## 一、总体结论

这套系统的**底层骨架是对的，方向也是对的**。`hsx_erp` 的事件驱动、Inbox/Outbox、设备身份+经营周期的建模，是可以支撑「回收 + 销售 + 财务 + 未来电商」长期演进的工业级设计。真正拖后腿的不是新代码，而是 `hsx_recycle` 早期快速迭代时堆积的**重复副本与死代码**，以及一批「商用前必须补齐」的运营/体验缺口。

一句话定调：**架构可以商用，工程卫生和产品打磨还没到商用。**

| 维度 | 评级 | 说明 |
|---|---|---|
| 架构与事件解耦 | 🟢 良好 | 插件边界清晰，标准事件契约成立，接电商无需返工 |
| 安全与数据 | 🟡 中等偏上 | 多租户隔离到位、有行锁与幂等索引；个别新模块校验偏薄 |
| 产品体验/UX | 🟡 中等 | 近期页面精致，但全链路状态/空态/错误反馈不统一 |
| 代码质量 | 🔴 需整改 | `hsx_recycle` 存在大量重复副本与死代码，是最大债务 |

---

## 二、架构与事件解耦（🟢 良好）

### 做得好的地方

1. **跨插件真解耦。** `hsx_recycle` 同步设备到 ERP 时，只通过 `event('ErpDeviceInboundRequested', $event)` 发布一份**标准设备快照**，完全不 import 任何 `hsx_erp` 的类或表。ERP 侧用监听器接收。回收插件被卸载、替换成别的 ERP，链路都不会断。
   - 证据：`hsx_recycle/.../RecycleDeviceErpSyncService.php` 全程只构造数组 + 发事件。

2. **Inbox/Outbox 模式落地正确。** ERP 接收事件先写 `erp_inbox_event` 去重，处理完通过 `erp_outbox_event` + `PublishOutboxEvent` 异步对外广播 `ErpDomainEvent`。这正是未来「财务插件订阅入库/成本/出库事件」的标准接入点。

3. **领域事件带版本号。** `ErpDomainEvent` 强制事件名包含 `.vN`，并在消费端校验版本，为协议演进留了余地。

4. **幂等与事务完整。** `ErpInboundService::receive()` 全程 `Db::startTrans()`，失败回滚；Inbox 命中直接返回既有结果。

### 需要改进

| # | 问题 | 影响 | 建议 |
|---|---|---|---|
| A1 | **事件命名两套风格。** 跨插件触发事件（`ErpDeviceInboundRequested`、`DeviceAssetPriceCompleted`、`GetErpDeviceSyncStatus`）是裸 ThinkPHP 事件名，无版本号；而 `ErpDomainEvent` 内部事件强制 `.vN`。 | 未来电商系统接入时，「请求类事件」没有版本协议，容易出现字段不兼容 | 把所有**跨插件契约事件**统一纳入带版本号 + schema 校验的规范，建立一份《事件契约清单》文档 |
| A2 | **`hsx_device_asset` 监听的是泛化 `ErpDomainEvent`**，靠在监听器内部用 `event_name` 分支判断。 | 事件类型一多，监听器会变成大 switch | 约定「按具体事件名订阅」优先于「订阅全量再分流」 |
| A3 | **同步结果依赖 `event()` 返回值是否为空**来判断 ERP 是否安装（`RecycleDeviceErpSyncService` 末尾）。 | 隐式耦合，错误提示也不够明确 | 增加一个显式的「ERP 接收能力探测」事件或开关 |
| A4 | **缺少事件契约的自动化测试。** `hsx_erp/tests` 目录存在但覆盖未知。 | 协议是整个系统的命脉，无测试则每次改快照字段都靠人肉验证 | 为「回收快照 → ERP 入库」「ERP 出库 → 财务」两条主链路补契约测试 |

---

## 三、安全与数据（🟡 中等偏上）

### 做得好的地方

1. **多租户隔离扎实。** ERP 所有服务都以 `site_id` 为查询前缀（`ErpAssetService` 出现 31 处、`ErpInboundService` 17 处……），SQL 唯一索引也几乎都带 `site_id` 前缀（`uk_site_event_consumer`、`uk_site_source_device` 等）。跨站点串数据的风险低。
2. **后台鉴权统一。** ERP 控制器全部继承 `BaseAdminController`，走框架的 token + 角色中间件。
3. **并发有行锁。** 确认入库等关键写操作用了 `->lock(true)`（`ErpAssetService` 多处），避免库存/成本重复写。
4. **幂等有 DB 兜底。** `erp_inbox_event` 上有 `UNIQUE(site_id,event_id,consumer)`，即使应用层 `findOrEmpty`+`create` 之间发生并发，数据库也能挡住重复入库。
5. **金额用 bcmath。** `ErpMoney` 用 `bcadd` 等定点运算，避免浮点误差——财务级正确。

### 需要改进

| # | 问题 | 严重度 | 建议 |
|---|---|---|---|
| S1 | **并发幂等的「友好返回」缺失。** Inbox 唯一索引虽然挡住了重复，但并发命中时是抛 `CommonException` 报错，而不是像先到先得那样返回既有结果。 | 中 | 捕获唯一键冲突 → 转走 `existingResult()` 返回，避免前端看到「入库失败」 |
| S2 | **回收快照里金额用 `round((float)…)`** 而非 bcmath（`RecycleDeviceErpSyncService::buildSnapshot`）。虽然进了 ERP 会被 `ErpMoney::normalize` 收口，但跨边界传 float 仍有精度隐患。 | 低-中 | 快照里金额统一传字符串定点值 |
| S3 | **`ErpReconciliationService`（对账，未提交新文件）`site_id` 使用仅 4 处**，相对其它服务偏薄，疑似尚未完工。 | 中 | 对账涉及钱，上线前重点补 `site_id` 过滤 + 权限校验 + 测试 |
| S4 | **批量接口入参未见显式上限。** 如 `batchConfirmInbound`、回收同步 `dispatch` 接收 `asset_ids/deviceIds` 数组，未见数量上限。 | 低 | 加最大条数限制，防止超大批量拖垮事务 |
| S5 | **资金类操作（打款/成本调整）需要二次确认与审计闭环核查。** 回收插件有 `RecycleDevicePayment`、`RecycleDeviceCostAdjustment`。 | 中 | 确认每一笔资金变动都有操作日志 + 不可篡改流水，且前端有二次确认 |

> 备注：`whereRaw` 仅出现在 `RecycleOrder` 模型中，且拼接的是 `getTable()` 返回的表名（来自配置，非用户输入），**不构成 SQL 注入**。未发现拼接用户输入的原生 SQL。

---

## 四、产品体验 / UX（🟡 中等）

### 做得好的地方

- **近期开发的页面质量明显提升。** `PriceFormDialog.vue`（858 行）有渐变 Header、移动端自适应宽度（`isMobile ? 95vw : 960px`）、BEM 命名、设备信息卡 + 质检摘要 + 价格参考分区，是合格的商用级交互。
- **ERP 后台页面规范统一。** `hsx_erp` 的列表页用 Tailwind 工具类 + Element Plus，有清晰的页面标题、副标题引导语（「手工建档或外部业务同步后先进入待入库……」）、关键词/状态筛选，符合 niucloud 后台一致性。

### 需要打磨（商用化重点）

| # | 体验缺口 | 建议 |
|---|---|---|
| U1 | **全链路状态机对用户不透明。** 回收单/设备有 recycled、consigned、pending_in 等多状态，但用户（门店店员）未必看得懂「为什么这台机不能同步入库」。 | 在设备卡片上用**状态徽章 + 一句话下一步指引**（如「待质检 → 去质检」），把状态机翻译成动作 |
| U2 | **空态 / 加载态 / 错误态不统一。** 不同列表页对「无数据」「请求失败」的呈现各做各的。 | 抽一套统一的 `Empty / Loading / Error` 组件，全插件复用 |
| U3 | **按钮语义与权限态。** 关键动作（确认入库、打款、定价）需要：危险操作二次确认、提交期间 loading 禁用防重复点击、无权限时灰显而非报错。 | 建立按钮规范：主操作/危险操作/只读三态 |
| U4 | **跨端体验割裂。** 后台（admin）、商家移动端（site-uniapp）、C 端（uni-app）三端并存，链路文案与状态命名需对齐。 | 建立一份**术语 & 状态对照表**，三端统一叫法 |
| U5 | **新模块缺引导。** ERP 仓库/往来单位/定价/整备/对账是新概念，店员上手成本高。 | 每个模块首屏加一段「这是什么 / 什么时候用」引导，或做一个新手向导 |

---

## 五、代码质量（🔴 需整改）

这是当前**最大、最该先动手**的债务，集中在 `hsx_recycle`（424 个 PHP 文件）。

### 关键问题：重复副本 + 死代码（高优先级）

走查发现 `hsx_recycle` 里大量「看着在用、其实没用」的重复文件，极易让人改错地方：

1. **`recycle_order copy` 目录**——字面意义的「副本」文件夹：
   `app/service/api/recycle_order copy/`（含 `RecycleDeviceService.php`、`RecycleOrderService.php`）。

2. **双份控制器**：`controller/order/RecycleOrder.php` 与 `controller/recycle_order/RecycleOrder.php` 并存。
   **经路由核查，实际生效的是 `order/` 这一份**（`route.php` 全部指向 `controller\order\...`）。`recycle_order/` 那份是死代码。

3. **双份模型**（同名不同路径，根目录那份基本是遗留）：

   | 模型 | 生效路径（被引用） | 遗留路径 |
   |---|---|---|
   | RecycleOrder | `model/order/RecycleOrder.php`（40 处引用） | `model/RecycleOrder.php`（8 处） |
   | RecycleDevice | `model/order/RecycleDevice.php` | `model/RecycleDevice.php` |
   | DeviceQueryResult | `model/third_party/…` | `model/DeviceQueryResult.php` |
   | DeviceQueryConfig | `model/third_party/…` | `model/DeviceQueryConfig.php` |
   | ShopAddress | `model/address/ShopAddress.php` | `model/ShopAddress.php` |
   | RecycleReturnOrder | `model/order/…` | `model/RecycleReturnOrder.php` |

4. **`hello_world` 脚手架残留**：`adminapi/controller/hello_world/`、`api/controller/hello_world/` 还在仓库里。

5. **服务层重复**：`DeviceQueryResultService`、`DeviceQueryConfigService` 等在 `service/admin/` 和 `service/admin/device_query/` 各有一份。

### 其它代码质量项

| # | 问题 | 建议 |
|---|---|---|
| C1 | **后台 Vue 源码两份并行副本。** `admin/src/addon/hsx_recycle/`（152 文件）与 `niucloud/addon/hsx_recycle/admin/`（152 文件）**字节级完全相同**，目前靠手动同步、git 里两边一起改。 | 这是 niucloud 插件分发约定（开发区 vs 打包区）。**绝不要手改两份**——用构建/同步脚本从 `admin/src` 单向生成插件包，并写进开发文档 |
| C2 | **命名/分层不一致。** 同类逻辑散落在 `order/`、`recycle_order/`、根目录三处命名空间。 | 收敛为单一约定（建议统一到 `order/`），其余物理删除 |
| C3 | **`.DS_Store` 进了仓库**（多处）。 | 加进 `.gitignore` 并清理 |
| C4 | **缺少自动化测试基线。** | 至少为 ERP 入库/出库、回收状态流转补单元 + 契约测试 |

---

## 六、商用化路线建议（按优先级）

**第 0 阶段 · 工程清创（建议最先做，1 轮即可见效）**
清理 `hsx_recycle` 的重复副本与死代码（`recycle_order copy/`、遗留双份模型/控制器、`hello_world`、`.DS_Store`），确立单一命名空间与「admin 源码单向生成」的同步规则。**这一步不改业务逻辑、风险低、收益大**，能让后续所有改动不再踩「改错文件」的坑。

**第 1 阶段 · 事件契约固化**
把跨插件契约事件统一加版本号 + schema 校验，产出《事件契约清单》，并为两条主链路补契约测试。——这是接入你那套成熟电商系统的前置条件。

**第 2 阶段 · 资金与对账加固**
补齐 `ErpReconciliationService` 的 `site_id`/权限/测试，资金操作全部走「二次确认 + 不可篡改流水 + 操作日志」。

**第 3 阶段 · 产品体验统一**
抽统一的空态/加载/错误组件与按钮三态规范，把状态机翻译成「徽章 + 下一步指引」，三端术语对齐，新模块加引导。

**第 4 阶段 · 接入销售/电商**
基于已固化的事件契约，把电商系统作为新的事件消费者/生产者接入 ERP（销售出库 → 库存扣减 → 成本结转 → 财务流水），全程不改 ERP 核心表。

---

## 七、下一步

本轮是「只看不改」的走查。请你确认两件事，我就按你定的顺序动手：

1. **是否从「第 0 阶段工程清创」开始？**（风险最低、收益最大，建议先做）
2. **清理死代码前，要不要我先生成一份「删除清单 + 影响面核对」让你过目？**（涉及物理删文件，稳妥起见建议先确认再删）
