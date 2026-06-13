# ERP 设备出路：调拨 / 出库 / 代卖闭环设计

> 状态：**待评审**（评审通过后按"分期实施"逐步落地）
> 范围：ERP 侧的"设备卖往哪、怎么出库、代卖怎么结"。中台（hsx_device_asset）只做拍照+定价，本设计不改变这一点。
> 原则：复用已有的财务/对手方/事件总线积木，**只补"调拨"和"出库"两块缺口**，事件驱动、低耦合、可独立运行。

---

## 0. 先说结论：哪些是现成的，哪些要新建

自读核实（非转述）后的家底：

**已经有、直接复用：**
- 仓库 `erp_warehouse.business_type`：`mall商城 / peer同行 / scrap报废 / hold暂存`（差一个 `consignment代卖`）。
- 自有/代卖：`erp_asset.ownership_type = owned | consign`，代卖入库已做到"无采购成本、无应付"。
- 对手方 `erp_counterparty`：`role_type` 含 `supplier / customer / both / consignor(寄卖人)`。
- 财务：应付 `erp_finance_payable`、应收 `erp_finance_receivable`、结算 `erp_finance_settlement`（现金/折账/混合）全部建好。
- 财务记账：`CoreFinanceLedgerService::recordPayable() / recordReceivable()`，事件驱动、`(site_id,event_id)` 幂等。
- 事件总线：`FinancePayableCreated / FinanceReceivableCreated / RequestFinanceSettlement / GetFinanceCounterpartyBalance` 监听器都在。
- 成交价回填：`ErpExternalPricingService::applyDeviceAssetPrice()`（中台定价回填到 `current_sale_price`）。
- 库存流水 `erp_stock_ledger`：有 `action / before_status / after_status / warehouse_id / location_id`，天生适合记调拨与出库。
- 成本流水 `erp_cost_ledger`、定价日志 `erp_price_log`。

> **2026-06-13 实读修正**：经逐文件核实，**出库与调拨其实已经实现**（之前的探查结论有误）：
> - `ErpOutboundService`（327 行）：`createOutbound` 出库两段式（现结 now / 回填 later→应收）、`fillPrice` 回填→`FinanceReceivableCreated` 应收（幂等 `receivable_emitted`）、`transfer` 调拨（移库 + `erp_asset_move_log`）、报废出库、同行销售必选对手方、`counterparty_enterprise_id` 主体字段，**全部已就绪**。
> - 表 `erp_outbound_order / erp_outbound_item / erp_asset_move_log` 已在 install.sql + update_outbound.sql。
> - `ErpDict` 已含 `INVENTORY_OUTBOUND / OUTBOUND_TYPE_* / SETTLE_MODE_* / OUTBOUND_PRICE_*`。
> 因此原 P2（调拨基础）、P4（出库两段式）**视为已完成**，本设计聚焦下列真实缺口。

**真实缺口（要新建/补全）：**
1. **库位责任人**——ERP 只有粗粒度 `SysUserRole`，没有"谁负责哪个库位/分类"的精确到人指派表（中台已有同款 `device_asset_location_assign`，ERP 无）。【P1】
2. **仓库类型 `consignment`**——`ErpWarehouseService::$allowedTypes` 只有 `mall/peer/scrap/hold`，缺 `consignment`。【P1】
3. **调拨的"类型联动"**——现有 `transfer()` 只是纯移库，**没有**：→mall 发 `ready_for_photo`（已拍过不重拍）、离开 mall 发"商城下架"、`consignment→mall` 买断应付+改 ownership+计成本。【P3】
4. **代卖卖出→应付寄卖人**——`createOutbound` 只发应收（买家欠我），未对 `ownership=consign` 的设备发"应付寄卖人"。金额人手填。【P3/P5】
5. **财务任务/待办筛选**（打款/追款）——应付应收表已有，缺面向财务的任务化视图。【P5】
6. **主体关联就地编辑**——`erp_counterparty_member` 映射已有、出库已有 `counterparty_enterprise_id`，缺各环节随手可改的统一入口。【P6】
7. **中台 re-delegation**——`createFromReadyForPhotoEvent` 已按 device_id 去重（不会重复建），补"已拍过照则回推可售、不退回待拍照"。【中台】

---

## 1. 仓库类型 = 设备出路（核心抽象）

设备"卖往哪"由它**所在仓库的 `business_type`** 决定。销路在回收入仓时由"进哪个仓"确定，之后可用"调拨"改判。

| 仓库类型 | 名称 | 设备出路 | 是否进中台拍照 |
|---|---|---|---|
| `mall` | 二手机仓（商城仓） | 上架商城零售 | ✅ 进中台拍照+定价 |
| `peer` | 同行仓 | 批发给同行，一入一出 | ❌ 不拍照，直接出库 |
| `consignment` | 代卖仓（**新增**） | 寄卖：①上架商城 或 ②我方买断 | ①上架时进中台（已拍过不重拍） |
| `hold` | 暂存仓 | 暂不处理，等后续调拨 | ❌ |
| `scrap` | 报废仓 | 报废出账 | ❌ |

落地点：`ErpWarehouseService::saveWarehouse()` 的 `$allowedTypes` 增加 `consignment`，前端仓库表单类型选项同步增加。

---

## 2. 销路分流与改判

- **分流点 = 回收入仓**：设备入哪个仓，就走那个仓的出路。`source_snapshot.sale_destination` 与目标仓 `business_type` 对齐（`mall/peer/consignment/hold`）。
- **`ready_for_photo` 门槛保持不变**：只有进入 `mall` 仓（`sale_destination='mall'`）才发 `erp.asset.ready_for_photo.v1` 进中台。其余仓不进中台。
- **改判 = 调拨**：入仓后随时可调拨到别的仓，调拨触发对应的联动（见 §3）。

> 注：本期把 `SALE_DESTINATION` 常量补全为 `mall/peer/consignment/hold`，但**真正驱动行为的是设备当前所在仓的 `business_type`**，`sale_destination` 只作来源意图快照。以仓为准，避免两个字段打架。

---

## 3. 调拨（transfer）+ 类型联动矩阵

**基础动作**：把资产从 A 仓/库位移到 B 仓/库位 —— 改 `warehouse_id/location_id`，写一条 `erp_stock_ledger(action='transfer', before/after warehouse)`，记操作人。乐观锁 `version+1`。

**按"目标仓类型"触发联动**（这是复杂度所在，逐条钉死）：

| 调拨方向 | 触发的联动 | 用什么实现 |
|---|---|---|
| 任意 → `mall` | 中台没拍过 → 发 `erp.asset.ready_for_photo.v1`（进待拍照）；**已拍过照** → 不重拍，直接进待定价/可售 | 复用中台 `DeviceAssetErpEventService`：按 `device_id` 查已有资产，已存在且 `photo_status=approved` 则跳过拍照 |
| `mall` → 非 `mall`（已上架商城） | 发"商城下架"通知 `erp.asset.delisted.v1` | 新事件，商城插件监听下架（商城不在则空操作） |
| `consignment` → `mall`（我方买断） | ① 对**寄卖人(consignor)** 产生**应付**（买断价）→ 交财务结；② `ownership` 由 `consign→owned`；③ 再按"→mall"联动进中台 | `event('FinancePayableCreated', …)` + 改 `ownership_type` + 走上一行的 →mall 逻辑 |
| → `peer` / `hold` / `scrap` | 仅移库，无中台联动 | 只写 stock_ledger |

**已拍过不重拍**：中台 `createFromReadyForPhotoEvent()` 现在按 `device_id` 查重，已存在就返回 `created:false`。本期补一条：若已存在且照片已完成，则把 ERP 状态直接推到"待定价/可售"，不再退回待拍照。

---

## 4. 出库（outbound）两段式

对应你说的"出库 ≠ 收款"。两个动作解耦，出库后钱没回来也不卡。

**动作一·出库**
- 入口：`peer` 仓的可售设备（或 `mall`/`consignment` 卖给同行的）。
- 行为：`inventory_status → sold`（新增常量 `INVENTORY_SOLD`），写 `stock_out_at`、记录**买方对手方**（同行 = `erp_counterparty`，`role_type` 含 customer），写 `erp_stock_ledger(action='sales_out')`。
- **不产生应收**（钱还没谈定/没收）。可在此刻顺手填成交价（则直接走动作二）。

**动作二·成交价回填**
- 行为：填实际成交价 → `event('FinanceReceivableCreated', […])` 生成**应收**（同行欠我），交财务收款/折账。
- 应收落库走现成的 `CoreFinanceLedgerService::recordReceivable()`，`event_id` 用 `erp_sale_receivable_{asset_id}` 幂等。
- `current_sale_price` 保留为中台/ERP 的**参考定价**；**成交价**记在应收 `amount` 与 stock_ledger payload 里，不混淆两者。

**收款本身**：由已有的财务结算服务处理（现金/折账/混合），不在出库里做。

> 出库的"待回款 / 已结算"不是资产状态，而是该笔**应收**的生命周期（pending→partial→settled）。资产侧只到 `sold` 为止，财务侧管钱。职责分离。

---

## 5. 代卖（consignment）闭环

代卖的本质：货是寄卖人的，**出口在销售侧——卖掉时才给寄卖人结账**。**结算金额由人手填，不做分成算法**。

**例**：上架/成交价 5800（我方收到的钱），寄卖人要 5500（人手填），差额 300 = 我方利润。

- **入库**：`ownership=consign`，无采购成本、无应付（已实现）。进 `consignment` 仓。寄卖人来自回收系统，**入库即有会员/主体**（无会员提交不了回收单），不存在"寄卖人没主体要手建"。
- **出路 ①·上架商城/零售卖出**：进 `mall` → 中台拍照定价 → 卖出（成交价收款）→ 对**寄卖人**产生应付（人手填 5500）。
- **出路 ②·我方买断**：`consignment→mall` 调拨时人手填买断价 → 对寄卖人产生应付，财务结款后变自有，按普通商城机走。
- 寄卖人 = `erp_counterparty(role_type=consignor)`，应付锚定它，复用现成应付+结算。

**卖出后的三种情况（驱动财务任务，见 §5.1）：**

| 情况 | 我方收款 | 应付寄卖人 | 财务任务 |
|---|---|---|---|
| 当场收现金 | 现金 5800 已到 | 5500（人手填） | 打款任务：给寄卖人结 5500 |
| 同行赊走 | 应收 5800（未到） | 5500（人手填） | 先追款 → 收到后再打款给寄卖人 |
| 机器退回 | — | 不产生 | **不进结算流程** |

> 不做分成算法 = 不写"卖价×比例"。系统只负责：卖出/买断时弹"应付寄卖人金额"输入框，人填多少记多少应付，交财务结。

### 5.1 财务任务 / 待办筛选

财务要清楚"自己该干嘛"，在财务中心提供**任务筛选**（基于现成的应付/应收表 + 对手方余额）：
- **打款任务**：待结的**应付**（`status=pending/partial`）——给寄卖人/供应商打款。
- **追款任务**：待收的**应收**（`status=pending/partial`）——同行赊账要追回。
- **联动**：应收追回（结算完成）后，对应寄卖人的打款任务才"可执行/提醒"。
- 退货不生成应付，自然不出现在任务里。

落地：财务看板加"待办/任务"标签页，按 payable/receivable 状态 + 对手方过滤；结算仍走现成 `FinanceSettlementService`。

---

## 6. 权限 / 责任：仓库责任人（你选的方案）

**责任精确到「库位/分类」级，不是整仓级。** 仓里按库位划分类（如二手机仓的 苹果区 / 安卓区 / 手表区），不同分类可由不同人或同一人负责；同行仓里的同类分区又可能是另一拨人。库位（`erp_warehouse_location`）就是表达这种"分类"的单元。

仿中台「库位责任」`device_asset_location_assign`，新建 **人↔库位** 表：

```sql
erp_location_assign (
  id, site_id,
  warehouse_id,        -- 冗余，便于按仓筛选
  location_id,         -- 负责哪个库位/分类（责任的原子单元）
  uid,                 -- 责任人
  create_at,
  UNIQUE(site_id, location_id, uid)
)
```

规则：
- **管理员/超管**：见所有库位、可操作所有设备（沿用 `AuthService::isSuperAdmin()` / `SysUserRole.is_admin`）。
- **普通责任人**：只**看见**自己负责库位的设备（按 `asset.location_id` 过滤），只能对这些设备做 **出库 / 成交价回填 / 调拨 / 暂存处理**。
- 一人可负责多个库位（跨仓也行），一个库位也可多人；**整仓授权 = 把该仓所有库位一次性指给他**（UI 做个"选整仓"快捷）。
- 角色/部门级以后再扩展，本期先做**人级**指派。
- **不跟定价记录耦合**——只表达"谁负责这个库位"。回填该由当初定销路的定价人来做，但系统层面只做"指定责任人"，不做强关联（符合你"做个指定就行，不要跟前面关联"）。

任务/列表查询按 `scopedLocationIds()` 过滤，与中台同名同写法。

> ERP 自建 `erp_location_assign`（管出库/调拨/暂存），与中台 `device_asset_location_assign`（管拍照）各自独立、都引用同一套 ERP 库位 `location_id`，遵循插件低耦合。物理上常是同一人管同一分区的两类活，后续如需可合并，但本期分开更安全。

---

## 7. 事件契约

**复用（已存在）：**
- `erp.asset.ready_for_photo.v1`（→中台拍照）
- `device_asset.price.completed.v1`（中台→ERP 回填定价）
- `FinancePayableCreated` / `finance.payable.created.v1`
- `FinanceReceivableCreated` / `finance.receivable.created.v1`
- `RequestFinanceSettlement` / `finance.settlement.completed.v1`

**新增：**
- `erp.asset.transferred.v1` —— 调拨完成（含 from/to 仓、操作人）
- `erp.asset.outbound.v1`（或 `erp.asset.sold.v1`）—— 出库完成
- `erp.asset.delisted.v1` —— 离开商城仓需下架（商城插件监听，缺则空操作）

全部走 `erp_outbox_event` 可靠发布 + `(site_id,event_id)` 幂等，与现有模式一致。

---

## 8. 新增常量（ErpDict）

```php
// 仓库业务类型 / 销路（与 warehouse.business_type 对齐）
SALE_DESTINATION_MALL        = 'mall';         // 已存在
SALE_DESTINATION_PEER        = 'peer';         // 新增
SALE_DESTINATION_CONSIGNMENT = 'consignment';  // 新增
SALE_DESTINATION_HOLD        = 'hold';         // 新增

// 库存状态：补"已出库/已售"
INVENTORY_SOLD = 'sold';                       // 新增（available_for_sale → sold）

// 库存流水动作
LEDGER_ACTION_TRANSFER  = 'transfer';          // 调拨
LEDGER_ACTION_SALES_OUT = 'sales_out';         // 出库
```

`ErpWarehouseService` 的 `$allowedTypes` 增加 `'consignment'`。

---

## 9. 状态机（保持最小，不过度设计）

```
pending_in → in_stock →(需整备?)→ refurbishing → pending_pricing
                     └─(无需整备)→ pending_pricing
pending_pricing →(定价)→ available_for_sale →(出库)→ sold
```

- **暂存 = `in_stock`**，不新增状态（按你的要求）。
- 报废走单独路径，本期不展开。
- `sold` 之后的"回款"在财务侧（应收生命周期），不污染库存状态机。

---

## 10. 分期实施（每期可独立上线、独立验证）

| 期 | 内容 | 依赖 |
|---|---|---|
| **P1** | 仓库类型补 `consignment`；建 `erp_location_assign`（人↔库位）+ 责任人指派 UI（支持"选整仓"快捷）+ 列表/任务按责任库位过滤 | 无 |
| **P2** | 调拨基础：移库 + `stock_ledger` + `erp.asset.transferred.v1` | P1 |
| **P3** | 调拨类型联动：→mall 拍照/已拍过不重拍、离开 mall 下架、consignment→mall 买断应付（人手填买断价） | P2 + 中台 re-delegation 改造 |
| **P4** | 出库两段式 + 成交价回填→应收；**出库必选对手方**（显示公司/主体绑定，缺则就地建主体）；责任库位权限校验 | P1 |
| **P5** | 代卖卖出 → 手填应付寄卖人；财务任务/待办筛选（打款/追款） | P3、P4 |
| **P6** | 主体关联就地编辑（回收/ERP/销售各环节内联改 member↔主体，见 §12） | 可独立 |
| **中台** | `createFromReadyForPhotoEvent` 支持"已拍过不重拍"回流 | 配合 P3 |

**低耦合保证**：ERP 不 import 商城/财务/中台的类，全部发标准事件；对方插件不在 = 监听为空 = 空操作，ERP 仍能独立跑。

---

## 11. 已拍板的决定

1. **代卖结算**：不做分成算法。出口在销售侧，卖出/买断时弹"应付寄卖人金额"输入框，人填多少记多少应付，交财务结。卖出后按 §5 三种情况驱动财务任务（打款/追款）；退货不进结算。
2. **出库买方**：必选对手方（同行/客户 = `erp_counterparty`），并显示他是哪个公司、有没有关联主体；不是会员就在 PC 端就地建主体再关联。寄卖人来自回收必有主体，不需手建。
3. **责任粒度**：精确到**库位/分类**（人↔库位），不是整仓；一人可管多库位、跨仓，整仓授权=一次性指派该仓全部库位；本期人级，角色/部门级以后扩展。
4. **主体关联随时可改**：见 §12。

---

## 12. 主体关联：随时可改、随手就做

**现状问题**：系统颗粒度按"用户身份（member）"走，没对应到"主体（公司/`erp_counterparty`）"。member↔主体 的映射在 `erp_counterparty_member`，但缺一个随处可改的入口。

**需求**：在**回收、ERP、销售**任一环节，操作人看到某台设备/某个人时，都能**就地查看并修改其关联主体**——谁在干这个业务、谁顺手把主体关联补对，不设专人、不靠后台集中维护。

**做法**：
- 提供统一的"关联主体"内联组件：显示当前主体（公司名/类型/有无绑定），可**选已有主体 / 新建主体 / 改绑**。
- 后端复用 `ErpCounterpartyService::resolve()` + `erp_counterparty_member` 映射；改绑即更新映射，并把当前设备的 `asset.counterparty_id` 同步为新主体。
- **历史账务不回溯**：已生成的应付/应收按当时锚定的主体快照不变（金额准确性优先）；改绑只影响该设备当前归属与之后新生成的账务。
- 入口位置：回收订单/设备详情、ERP 资产详情、销售/出库选对手方处，都挂这个组件。

> 本质：把"member → 主体"的对应，分散到每一次真实业务操作里顺手完成，而不是事后由专人补录。

---

## 13. 实施进度（2026-06-13 夜，自动执行）

> 说明：沙箱**无法连你内网数据库、也无 PHP 运行时**，故后端用「Python 括号/结构自检」+ 人工对照约定校验，前端用「@vue/compiler-sfc 编译校验」。**SQL 只写了迁移脚本，需你明早在库里执行**。

**已完成并校验：**
- **仓库类型 `consignment`**：`ErpDict` 补销路常量 + `warehouseBusinessTypes()`；`ErpWarehouseService::$allowedTypes` 加 `consignment`；仓库表单类型选项加"代卖"。
- **修复既有 Bug**：`Warehouse@save` 控制器之前漏传 `business_type`（表单选了类型但永远存成 mall），已补上。
- **库位责任（P1，全新）**：
  - 表 `erp_location_assign`（install.sql + `sql/update_location_assign.sql`，**待执行**）。
  - `ErpLocationAssign` 模型、`ErpLocationAssignService`（树/员工/列表/按库位设人/按人设库位）、`LocationAssign` 控制器、5 条路由、菜单页「库位责任」。
  - 资产列表 `ErpAssetService::getPage` 按 `scopedLocationIds()` 过滤（管理员看全部，员工只看负责库位）。
  - 管理端页面 `admin/views/location_assign/list.vue` + `admin/api/location_assign.ts`（编译通过）。
- **调拨类型联动（P3 后端）**：`ErpOutboundService::transfer()` 增加：进 mall 发 `ready_for_photo`（中台按 device_id 去重，已拍过不重拍）、离开 mall 发 `erp.asset.delisted.v1`、代卖进 mall 且 `consign_action=buyout` 时改 `ownership=owned`+计成本+对寄卖人发应付（买断价人手填）。`Outbound@transfer` 控制器加 `consign_action/buyout_prices` 入参。
- **代卖卖出应付寄卖人（P5 后端）**：`createOutbound` 的 item 支持 `consignor_payable`（人手填），代卖设备卖出时对其寄卖人 `counterparty_id` 发 `FinancePayableCreated`（幂等键按设备）。

**出库/调拨本体**：经核实**早已实现**（`ErpOutboundService` 全套 + 表 + 菜单 + 路由 + 出库 list.vue），本次只做"类型联动/代卖应付"的增量。

**尚未做（留待续做，多为前端 UI）：**
1. **调拨管理 UI**：目前 `transfer` 有后端+路由，但管理端没有调拨对话框（选目标仓/库位、代卖买断价输入）。需在资产列表加"调拨"入口。
2. **出库 create 表单**：加每台"应付寄卖人金额"输入（仅代卖设备显示）→ 传 `consignor_payable`。
3. **财务待办 Tab（P5）**：财务看板加"打款任务/追款任务"筛选（基于现成应付/应收 outstanding 接口）。
4. **主体关联内联编辑（P6）**：统一组件 + 端点，挂到回收/ERP/销售各处。
5. **中台回推可售**：当前去重已保证"不重拍"；"已拍过照自动回推可售"为可选优化，未做。

**明早操作清单：**
1. 在数据库执行 `niucloud/addon/hsx_erp/sql/update_location_assign.sql`（建库位责任表）。
2. ERP 插件菜单需重新同步（新增了「库位责任」菜单页与若干 API 节点）。
3. 验收：建一个 `consignment` 仓 → 仓库类型能存住代卖；「库位责任」页给员工指派库位 → 用普通员工账号登录看资产列表是否只显示其负责库位。
