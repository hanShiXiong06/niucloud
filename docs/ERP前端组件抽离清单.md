# ERP 前端/后端 可抽离清单（最小解耦）

> 目的：把各列表页/弹框里重复的代码抽成公共件，降低维护成本。按"收益/改动量"排了优先级。
> 已抽离的（保持）：`PremiumTheme`、`PageHeader`、`EmptyState`、`entity-drawer`(往来主体抽屉)。

---

## P0 · 收益最大，强烈建议先做

### 1. 列表页 composable：`useListQuery`
**痛点**：设备中心 / 往来单位 / 整备 / 出库 / 盘点 / 应付应收，每个页都各写一套：`search` 响应式、`loadList`、`handleSearch/reset`、`onSort`、日期区间→`start_time/end_time` 映射、分页。逻辑几乎一样，改一处要改 N 处。

**抽成**：`@/addon/hsx_erp/composables/useListQuery.ts`
```ts
const { search, table, loadList, onSort, reset, onPage } = useListQuery({
  api: getErpAssetList,
  defaults: { keyword: '', warehouse_id: '' },
  dateRangeField: 'stockInRange',           // 自动映射成 start_time/end_time
})
```
内部统一处理：`{...search, page, limit}`、日期区间映射、`sort_field/sort_order`、loading、total。
**替换**：上述 6 个页的 loadList/onSort/reset/分页样板。
**改动量**：中（新建1文件 + 各页接入）。**收益**：极高。

### 2. 服务端排序 trait：`HasWhitelistSort`（后端）
**痛点**：每个 `getPage` 都手写 `$sortMap=[...]; $sf=...; $so=...; $query->order(...)`（设备/往来/整备/出库都有）。
**抽成**：一个 trait 或 BaseAdminService 方法 `applySort($query, $where, array $whitelist, $default='id')`。
（财务已有 `FinanceCounterpartyBalanceService::applySort`，把它提到公共基类，全 ERP 复用。）
**改动量**：小。**收益**：高。

---

## P1 · 重复明显，建议做

### 3. 字典统一走后端（去掉前端散落的状态/类型映射）
**痛点**：前端各页各写 `statusName/statusType/resultType/methodTagType/sevType`，和后端 `ErpDict` 的中文字典重复，还容易不一致。
**做法**：列表/详情接口统一返回 `*_text` + `*_tag`（后端用 `ErpDict::getInventoryStatusMap/getLedgerActionMap/...` 已经做了一部分），前端只显示、不再自己映射。
**替换**：设备中心 `statusName/statusType`、盘点 `resultType`、财务 `methodTagType` 等。
**改动量**：中。**收益**：高（口径一处定义）。

### 4. 金额/数量区间筛选件：`RangeFilter`
**痛点**：成本区间、金额区间，到处是 `<el-input 最低> ~ <el-input 最高>` 两个框。
**抽成**：`<range-filter v-model="search.cost" />`（吐出 `cost_min/cost_max`）。
**改动量**：小。**收益**：中。

### 5. 日期范围筛选件：`DateRangeFilter`
**痛点**：`<el-date-picker type=daterange value-format=X>` + loadList 里手动拆 start/end，到处重复。
**抽成**：`<date-range-filter v-model="search.range" />`，配合 #1 的 composable 自动拆字段。
**改动量**：小。**收益**：中。

### 6. 资金账户选择件：`CapitalAccountSelect`
**痛点**：折账 / 付款 / 收款 / 记支出 4 个弹框都写 `<el-select> 资金账户(余额)`。
**抽成**：`<capital-account-select v-model="accountId" :accounts="summary.accounts" />`。
**改动量**：小。**收益**：中。

### 7. 设备信息块：`DeviceCell`
**痛点**：型号 + IMEI + SN 的两行展示，列表、弹框、详情里重复。
**抽成**：`<device-cell :row="row" />`（型号粗体 + IMEI/SN 小字）。
**改动量**：小。**收益**：中。

---

## P2 · 锦上添花

### 8. 设备详情抽屉抽成公共件：`AssetDetailDrawer`
**痛点**：设备详情抽屉目前内嵌在 `asset/list.vue`，盘点里"型号点击查详情"没法复用。
**抽成**：`@/addon/hsx_erp/components/asset-detail-drawer.vue`，传 `asset-id` 即可。设备中心、盘点、设备追溯都能用。
**改动量**：中（从 asset/list 抽出）。**收益**：中（解锁盘点点击查详情等）。

### 9. 公共格式化：`money() / formatTime()`
**痛点**：几乎每个 vue 都重复定义。
**抽成**：`@/addon/hsx_erp/utils/format.ts` 统一导出。
**改动量**：很小。**收益**：低但干净。

### 10. 关联人/主体解析（后端）：`ContactResolver`
**痛点**：`resolveMemberMap` + 设备/应付应收的"主体+关联人+电话"补充逻辑，分散在多个 service。
**抽成**：一个 `ContactResolver`（或 trait），统一"member_id/counterparty_id → 主体名+电话+关联人名+电话"。
**改动量**：中。**收益**：中（口径统一，已出现过"一边有名一边是壳"的问题）。

---

## 建议落地顺序
1. **#2 排序 trait** + **#1 useListQuery**（先把列表样板收敛，后面所有页都受益）。
2. **#3 字典走后端**（消除前后端不一致）。
3. 视情做 #4~#7 的小组件。
4. #8 设备详情抽屉（解锁盘点/追溯点击查详情）。

> 注意：抽离时仍守红线——公共件只放 ERP 插件内（`addon/hsx_erp/components|composables|utils`），不动核心；改完每个接入页都要回归一遍筛选/排序/分页。
