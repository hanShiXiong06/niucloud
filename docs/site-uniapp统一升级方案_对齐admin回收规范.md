# site-uniapp 回收端统一升级方案（对齐 admin 规范）

**升级目标**：把 admin（PC 管理端 `hsx_recycle`）近期建立的设计系统与交互规范，整体移植到 `site-uniapp`（uniapp 移动端 `hsx_recycle`），保证**代码一致性**与**功能体验一致性**。
**对齐范围**：视觉 + 交互全面对齐。
**对照对象**：`admin/src/addon/hsx_recycle/`（基线） ↔ `site-uniapp/src/addon/hsx_recycle/`（待升级，34 个 vue）。
**时间**：2026-06-12　**分支**：`dev`（领先 origin/dev 44 个提交，未推送）
**说明**：本文为**差距分析 + 分阶段方案**，确认后再落地改码。

---

## 一、总体结论

移动端的**业务功能已经齐全，弹窗外壳也已天然统一**（16 个弹窗都是 `u-popup` 三段式：头部 + `scroll-view` 主体 + 底部按钮），防重提交在多数写操作里也已具备。真正的差距不在"缺功能"，而在"**没有共享的规范层**"——颜色、页头、空态、状态展示各页各写一套，导致同一套回收业务在 PC 与移动端**观感与交互细节割裂**。

一句话定调：**功能已对齐，规范层尚未对齐。**这次升级的本质是"抽公共件 + 统一令牌"，工程量集中在前半段（建立规范），后半段（逐页接入）是机械替换。

| 维度 | 当前评级 | 说明 |
|---|---|---|
| 主色 / 设计令牌 | 🔴 不一致 | 移动端主色 `#2563eb`(75 处)+`#2979ff`(10 处)，admin 已统一为 `#4f46e5`（移动端 0 处） |
| 页头 | 🟡 覆盖不全 | `RecyclePageHeader` 已有但仅 ~50% 页面接入，`express/list`、`stats` 自写或缺失 |
| 空态 | 🔴 无统一组件 | 混用 `z-paging`/`u-empty`/手写 div，无 admin 那种"引导式空态" |
| 弹窗外壳 | 🟢 形式已统一 | 16 个弹窗结构一致，但**无共享 FormDialog 基础件**，样式各写各 |
| 状态徽章 + 下一步 | 🔴 缺失 | 移动端只有 `tag--status/warning/success/purple` 颜色标签，无"状态→下一步动作"提示 |
| 防重提交 | 🟢 基本具备 | 各弹窗均有 `submitting` + 按钮 `:loading`，但散落各处、无共享守卫 |
| 金额二次确认 | 🟡 覆盖不全 | 打款 `PaymentConfirmPopup` 有，定价 `PriceDevicePopup` 缺 |
| 操作收敛（主+更多） | 🔴 缺失 | 移动端设备操作未做"主行动+更多"收敛 |
| emoji / 空字段 | 🟡 小瑕疵 | 仍有 2 处 `📱` emoji（定价/转代卖弹窗），空字段隐藏未成规范 |

---

## 二、规范基线（admin 端已确立，作为移植标尺）

以下是 admin `hsx_recycle` 近 44 个提交建立的规范，逐项标注源文件，供移动端对照实现。

### 2.1 设计令牌（`styles/premium-theme.scss`）

| 令牌 | admin 数值 | 含义 |
|---|---|---|
| 主色 | `#4f46e5`（靛蓝） | primary |
| 主色浅 9 | `#eeedfc` | 药丸/标签浅底 |
| 标准圆角 | `8px`（按钮/输入） | base radius |
| 卡片圆角 | `12px` | card |
| 轻阴影 | `0 1px 2px rgba(17,24,39,.04), 0 1px 3px rgba(17,24,39,.06)` | 卡片柔投影 |
| 边线 | `#eef0f4` | 浅分隔线 |

实现方式：作用域包裹类 `.hsx-premium`，CSS 变量级联覆盖 Element Plus，**不碰核心框架**、可整体下线（`components/PremiumTheme.vue`）。

### 2.2 通用组件

| 组件 | 路径 | 关键 API |
|---|---|---|
| `PageHeader` | `components/PageHeader.vue` | `title:string`（必填）、`description?:string`、`#actions` 插槽 |
| `EmptyState` | `components/empty-state/index.vue` | `title?`、`description?`、`icon?`（box/document/search/warning/folder）、`compact?`、`#action` 插槽 |
| `FormDialog` | `components/FormDialog.vue` | `visible`(v-model)、`title`、`subtitle?`、`width:'sm'/'md'/'lg'/'xl'`、`loading?`、`confirmDisabled?`、`#footer` 插槽；主按钮自带 loading 防重 |
| `DeviceStatusBadge` | `views/recycle_order/components/DeviceStatusBadge.vue` | 柔和药丸（圆点+浅底同色字）+ "下一步"提示 |

### 2.3 交互通用件

| 规范 | 路径 | 要点 |
|---|---|---|
| 提交守卫 | `hooks/useSubmit.ts` → `run(task, {success})` | `loading` 为真时直接返回，防重入；自动 toast |
| 危险确认 | `hooks/useSubmit.ts` → `confirmDanger(msg, {html})` | 不可逆操作二次确认，支持高亮金额，返回 bool 不抛错 |
| 状态→动作映射 | `hooks/useRecycleOrderUi.ts` | `getDeviceStatusText/Type/NextStep`，把状态翻译成"现在该做什么" |
| 操作收敛 | `hooks/useDeviceRowActions.ts` | 按状态给一个 primary 主行动 + 其余收进"更多"，危险项标红分隔 |

### 2.4 设备状态 → 下一步动作（移动端需复刻同一张表）

| 状态码 | 文案 | 下一步提示 | 色 |
|---|---|---|---|
| 1 待质检 | 待质检 | 开始质检 | info 灰 |
| 2 质检中 | 质检中 | 完成质检 | warning 橙 |
| 3 已质检 | 已质检 | 回收定价 | primary 靛 |
| 4 待确认 | 待确认 | 确认回收 / 重新定价 / 拒绝 | success 绿 |
| 5 已回收 | 已回收 | 等待打款与入库 | success 绿 |
| 6 已退回 | 已退回 | 流程结束 | danger 红 |
| 7/8 已定价/重新定价 | 已定价 | 待确认 | primary |
| 9 已转代卖 | 已转代卖 | 进入代卖流程 | purple |

> 移动端 `utils/device.ts` 已有这些状态常量，只缺"文案/下一步/色"的统一映射函数。

---

## 三、差距清单（视觉 + 交互两条线）

### A. 视觉线

**A1. 主色不统一（🔴 最高优先）**
移动端硬编码 `#2563eb`（75 处）与 `#2979ff`（10 处），与 admin `#4f46e5` 完全不一致。同一品牌、同一业务，两端主色不同。
涉及面广：`RecyclePageHeader`、各弹窗、状态点、按钮等。

**A2. 缺统一设计令牌**
移动端颜色/圆角/阴影散落硬编码（`#ea580c`/`#18a058`/`#ef4444` 等各处直写），`windi.config.ts` 虽有 `var(--primary-color)` 但回收模块未贯彻。需要一份**移动端版的 premium 令牌**（SCSS 变量 / CSS var），作为唯一颜色出口。

**A3. 页头覆盖不全**
`RecyclePageHeader` 已具备（固定顶栏、双端适配、`#right` 插槽），但 `express/list` 自写搜索条、`stats/index` 无顶栏。需统一接入并补 `description/副标题` 能力对齐 admin `PageHeader`。

**A4. 无引导式空态**
移动端空态混乱：列表靠 `z-paging` 自带、弹窗用 `u-empty`、`express/list` 手写 div、`stats` 空态模糊。缺 admin `EmptyState` 那种"一句引导 + 可选操作按钮 + 预置图标"。需新建移动端 `RecycleEmptyState` 组件。

**A5. 状态标签未成体系**
移动端 `tag--status/warning/success/purple` 四类颜色标签零散使用，无统一"柔和药丸 + 圆点 + 下一步提示"。需新建 `DeviceStatusBadge`（uniapp 版）。

**A6. emoji 与硬编码残留**
`PriceDevicePopup.vue:13`、`ConsignmentPopup.vue:13` 仍有 `📱` emoji，应换 `nc-iconfont` 专业图标（移动端图标库已完善，仅这 2 处）。

### B. 交互线

**B1. 无共享提交守卫**
各弹窗自行 `const submitting = ref(false)` + try/finally，逻辑正确但重复 16 份、易漏。需抽 `useRecycleSubmit()`（移动端版 `useSubmit`），统一防重入 + toast。

**B2. 金额二次确认覆盖不全**
打款 `PaymentConfirmPopup` 已用 `uni.showModal` 弹金额确认；但**定价 `PriceDevicePopup` 缺**（金额同样关键）。需抽 `confirmDanger()`（移动端版，封装 `uni.showModal`，高亮金额）并补到定价/拒绝回收/转代卖等不可逆操作。

**B3. 缺操作收敛**
admin 已把设备操作收敛成"主行动 + 更多"（按状态出一个最该做的高亮行动）。移动端设备卡 `DeviceFlowCard` 操作区未做收敛，按钮平铺。需复刻 `useDeviceRowActions` 的状态→主行动映射到移动端（"更多"用 `u-action-sheet`/底部菜单承载）。

**B4. 空字段未隐藏**
admin 规范：容量/颜色/系统版本/保修等为空时不渲染，去"暂无"噪声。移动端 `order/detail`、`DeviceDetailPopup` 等需按 `filter(有值)` 统一处理。

---

## 四、分阶段升级方案

采用 **样板先行**（与 admin 同思路："导出页作为风格样板"），先打通规范层 + 一个样板页，确认风格后再机械铺开，降低返工。

### 第 0 阶段 · 规范底座（1 个 PR，前置）
1. 新建 `site-uniapp/src/addon/hsx_recycle/styles/premium-tokens.scss`：定义主色 `#4f46e5`、圆角、阴影、状态色等 CSS 变量，作为唯一颜色出口。
2. 全局替换主色：`#2563eb`/`#2979ff` → 令牌变量（85 处，脚本批量 + 人工抽检）。
3. 抽 `hooks/useRecycleSubmit.ts`（防重）与 `utils/confirm.ts`（`confirmDanger` 封装 `uni.showModal`）。
4. 抽 `utils/deviceStatus.ts`：复刻 admin `useRecycleOrderUi` 的状态→文案/下一步/色映射。

**产出**：规范底座可用，肉眼可见主色统一。

### 第 1 阶段 · 公共组件（1 个 PR）
5. `components/RecycleEmptyState.vue`：对齐 admin `EmptyState`（title/description/icon/compact + 操作插槽）。
6. `components/DeviceStatusBadge.vue`：柔和药丸 + 圆点 + 下一步提示。
7. `components/RecycleFormDialog.vue`：把现有 `u-popup` 三段式抽成共享外壳（title/subtitle/loading/confirmDisabled + footer 插槽），内置防重。
8. 升级 `RecyclePageHeader`：补 `description` 副标题，对齐 admin `PageHeader`。

### 第 2 阶段 · 样板页（1 个 PR，关键确认点）
9. 选 **`order/list.vue` + 其设备卡/定价弹窗** 作为样板：接入新令牌、`DeviceStatusBadge`、`RecycleEmptyState`、`RecycleFormDialog`、`confirmDanger`（定价金额确认）、操作收敛。
10. **暂停，交付样板页给你确认风格**，再继续铺开。

### 第 3 阶段 · 全量铺开（按模块拆 3~4 个 PR）
11. 订单模块其余弹窗（质检/打款/退货/签收/转代卖/设备详情）接入共享外壳 + 守卫 + 金额确认。
12. 退回 / 代卖 / 运单 / 看板模块：统一页头、空态、状态徽章、emoji 替换。
13. `express/list`、`stats/index` 补页头与空态。

### 第 4 阶段 · 验证收口（1 个 PR）
14. 逐页核对清单（见第六节）；小程序 + H5 双端真机/模拟器走查关键流程（签收→质检→定价→确认→打款、退回、转代卖）。

> 估算：阶段 0–1 是主要工作量（建规范），阶段 2 是确认门，阶段 3 多为机械替换。建议每阶段独立 PR，便于回滚。

---

## 五、组件 / API 映射表（admin → site-uniapp）

| admin（Element Plus） | site-uniapp（uniapp） | 适配要点 |
|---|---|---|
| `PremiumTheme` + SCSS 变量级联 | `premium-tokens.scss` 全局 CSS var | uniapp 无 teleport 问题，无需 overlay 类 |
| `PageHeader` | 升级版 `RecyclePageHeader` | 已有顶栏，补 `description` |
| `EmptyState` | 新建 `RecycleEmptyState` | 图标用 `nc-iconfont`/`u-empty` 底图 |
| `FormDialog`（el-dialog） | 新建 `RecycleFormDialog`（u-popup bottom） | 宽度档位→`round`+全宽底部弹层 |
| `DeviceStatusBadge`（el-tag） | 新建 `DeviceStatusBadge`（view+class） | 药丸样式用 rpx 复刻 |
| `useSubmit.run` | `useRecycleSubmit` | 逻辑直接复用，toast 换 `uni.showToast` |
| `confirmDanger`（ElMessageBox） | `confirmDanger`（`uni.showModal`） | 金额拼进 content |
| `useDeviceRowActions`（el-dropdown） | 同名 hook（`u-action-sheet`） | 主行动按钮 + "更多"动作面板 |
| `el-input type=number` | `<input type="digit">` | 金额输入 |
| `el-upload` | 已有 `RecycleImageUploader` | 复用 |

---

## 六、风险与验证

**风险点**
- 主色批量替换可能误伤非回收模块或第三方组件内联色——限定在 `addon/hsx_recycle/` 目录内替换，逐处抽检。
- `RecycleFormDialog` 抽取需保持 16 个弹窗现有行为不变——样板页先行验证，再逐个迁移，每迁一个跑一次该弹窗流程。
- 小程序与 H5 双端表现差异（安全区、`scroll-view` 高度、`u-popup` 圆角）——阶段 4 双端走查。

**逐页验证清单（每页过一遍）**
- [ ] 主色无 `#2563eb`/`#2979ff` 残留，统一走令牌
- [ ] 页头用 `RecyclePageHeader`（含副标题）
- [ ] 空列表显示 `RecycleEmptyState`（非干巴巴"暂无"）
- [ ] 设备状态用 `DeviceStatusBadge` 且带下一步提示
- [ ] 弹窗用 `RecycleFormDialog`，主按钮自带防重
- [ ] 金额类不可逆操作有 `confirmDanger` 二次确认
- [ ] 设备操作"主行动+更多"收敛
- [ ] 空字段不渲染、无 emoji

**建议**：阶段 4 用对照截图（PC ↔ 移动端同一业务）做最终一致性核验。

---

## 附：关键文件索引

```
基线（admin）
  admin/src/addon/hsx_recycle/
    styles/premium-theme.scss          # 设计令牌
    components/PremiumTheme.vue|PageHeader.vue|FormDialog.vue|empty-state/index.vue
    views/recycle_order/components/DeviceStatusBadge.vue
    hooks/useSubmit.ts|useRecycleOrderUi.ts|useDeviceRowActions.ts

待升级（site-uniapp）
  site-uniapp/src/addon/hsx_recycle/
    components/RecyclePageHeader.vue    # 升级
    components/（新增 RecycleEmptyState / DeviceStatusBadge / RecycleFormDialog）
    hooks/（新增 useRecycleSubmit）
    utils/（新增 confirm.ts / deviceStatus.ts；device.ts 已有状态常量）
    pages/order/list.vue + components/*  # 样板页优先
    windi.config.ts                     # 主色令牌入口
```
