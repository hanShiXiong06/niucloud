# ERP 动态字典与插件 Hook 契约

## 目标

ERP 只维护基础字典，其他业务插件通过牛云事件机制贡献扩展项。最终可用字典为：

`ERP 基础项 + 商家自定义项 + 当前站点套餐已安装插件的 Hook 返回项`

合并以稳定 `key` 为准；同 key 冲突时保留先注册项并记录警告，插件不能覆盖 ERP 核心或其他插件。业务单据保存 key、来源插件、插件内编码和中文名称快照，避免插件改名或卸载影响历史单据。

## 销售渠道 Hook

事件名：`HsxErpSaleChannelOptions`

监听器参数：

- `site_id`：当前站点。
- `erp_version`：ERP 契约版本，目前为 `0.0.1`。

监听器返回：

```php
return [
    'channels' => [[
        'key' => 'phone_shop_mini_program',
        'name' => '小程序商城',
        'channel_type' => 'platform', // peer/store/retail/platform/other
        'source_plugin' => 'phone_shop',
        'source_key' => 'mini_program_order',
        'enabled' => 1,
        'is_default' => 0,
        'sort' => 85,
    ]],
];
```

ERP 基础渠道为“同行、门店”。例如站点套餐安装 `phone_shop` 后，该插件监听器贡献“小程序商城”，页面自动从 AB 变为 ABC。

旧版允许自由输入销售渠道，可能产生“小程序、123”等无来源配置。动态字典启用后不再读取旧版自由输入配置；历史销售单继续使用原渠道快照展示，新销售只能选择基础渠道或当前可用插件提供的渠道。

牛云事件加载器会依据当前站点、站点套餐和已安装插件装配监听器。如果插件内部还有功能套餐，监听器应自行检查权益；不满足时返回空数组。

## 业务来源 Hook

事件名：`HsxErpBusinessSourceOptions`

业务来源只描述“业务从哪个插件、哪个业务对象进入 ERP”，不得与销售渠道或财务分类混用。例如小程序订单的业务来源是 `phone_shop`，销售渠道是“小程序商城”，财务分类仍然是“销售收入”。

监听器返回：

```php
return [
    'sources' => [[
        'key' => 'phone_shop.mini_program_sale',
        'name' => '小程序销售',
        'direction' => 'income', // income/expense
        'scene' => 'sale',       // purchase/sale/purchase_return/sale_return/refurbish
        'source_plugin' => 'phone_shop',
        'source_key' => 'mini_program_sale',
        'enabled' => 1,
        'sort' => 150,
    ]],
];
```

ERP 基础来源包括 ERP 采购、ERP 销售、ERP 采购退货、ERP 销售退货和 ERP 整备。`phone_shop` 贡献“小程序销售”，`hsx_recycle` 贡献“回收插件采购”。

约束：

- `key` 必须使用插件命名空间，例如 `phone_shop.*`。
- 同一 `key` 被重复注册时，ERP 保留先注册项并记录冲突日志，禁止后注册插件静默覆盖。
- `source_plugin` 是业务来源插件；销售渠道项中的 `source_plugin` 只是渠道字典提供者，两者不能互相替代。
- 插件卸载后新业务不再出现该来源，历史单据必须保存 key、名称、插件及插件内编码快照。

## 收支类型 Hook

事件名：`HsxErpFinanceCategories`

监听器返回：

```php
return [
    'categories' => [[
        'key' => 'repair_center.repair_screen',
        'name' => '屏幕维修费',
        'direction' => 'expense', // expense/income
        'scope' => 'refurbish',
        'affects_asset_cost' => 1,
        'creates_finance' => 1,
        'party_required' => 1,
        'source_plugin' => 'repair_center',
        'source_key' => 'screen_repair',
        'enabled' => 1,
        'sort' => 80,
    ]],
];
```

整备类支出必须选择费用收款方/整备服务商。确认后同时：

1. 增加设备整备成本和总成本。
2. 写入设备流水与往来账目流水。
3. 按设备、费用类型、整备服务商生成应付款。
4. 财务在应付款中完成实际付款。

收入类型沿用相同结构，`direction` 改为 `income`，后续经营收入单据可据此生成应收或直接收款。

财务分类额外返回 `statement_group`，用于经营报表区分收入、成本及冲回，不允许仅凭现金流入/流出判断利润：

- `revenue`：销售收入。
- `revenue_reversal`：销售退款、售后补差等收入冲回。
- `purchase`：设备采购支出。
- `purchase_reversal`：采购退货款收回。
- `operating_expense`：整备等经营费用。
- `other_income` / `other_expense`：其他收支。

ERP 内置销售收入、设备采购支出、销售退货退款、采购退货款收回和售后补差五类核心财务分类。退货退款不得归入普通“其他收入/支出”。

## 边界

- 字典项不是财务事实，选择并提交业务单据后才生成应收、应付或成本流水。
- 插件卸载后，历史单据仍使用名称与来源快照展示；新业务不再出现该插件贡献项。
- 商家保存配置时只持久化 ERP/商家自己的条目，插件 Hook 条目不会写入 ERP 配置，避免卸载插件后仍残留为可选项。
- 插件不得覆盖其他插件 key；key 必须以插件名作为前缀。

## 标准业务入站 Hook

动态字典只负责“可选项”，不能替代真实业务落账。外部插件必须调用 ERP 标准入站 Hook，由 ERP 领域服务统一创建单据、库存、应收/应付和流水。

### 设备采购入站

- Hook：`ErpDeviceInboundRequested`
- 标准事件名：`erp.device.inbound_requested`
- 当前兼容旧回收事件：`recycle.device.inbound_requested`
- `event_version`：`1`

ERP 使用顶层 Inbox 校验完整 payload；同一 `event_id` 只能代表同一不可变事实。一次事件即使按来源订单拆为多张采购单，也处于同一个顶层事务，任一分组失败整体回滚。来源插件必须先通过 `HsxErpBusinessSourceOptions` 注册 `direction=expense, scene=purchase` 的来源。

### 销售出库入站

- Hook：`ErpSaleCreatedRequested`
- 标准事件名：`erp.sale.created_requested`
- `event_version`：`1`

事件最少携带：`event_id/site_id/source/counterparty/channel/occurred_at/items[]`。每个 item 必须给出 ERP `asset_id`、来源明细 ID 和销售价。ERP 统一执行库存锁定、销售单、设备成本结转和销售应收；外部系统即使已经收款，只要没有 ERP 资金账户快照，就不得伪造实际收款。

`phone_shop` 已在真实订单付款事件中仅筛选带 `erp_asset_id` 的一物一码商品，再调用本 Hook；ERP 回流生成的线下展示单会被排除，避免循环建单。

### 通用收入/支出事实

- Hook：`ErpFinanceFactRequested`
- 标准事件名：`erp.finance.fact.requested.v1`

适用于维修收入、维修支出、检测费等不产生采购/销售库存动作的财务事实。分类决定生成应收还是应付；只有 `affects_asset_cost=1` 的支出会同步增加设备成本。它不允许绕过采购、销售、退货核心分类，也绝不直接生成资金结算。

## 结算完成回调

ERP 完成实际付款、实际收款或折账后，在业务事务内写 Outbox，提交后以 `ErpDomainEvent` 派发：

- 事件名：`erp.settlement.completed.v1`
- payload：结算单号、结算类型、账户、往来主体、逐条 AP/AR、来源快照、核销金额、剩余金额及关联设备/来源设备行。

来源插件只在 `remaining_amount=0` 时把自己的设备标记为结清；部分付款、部分折账不能提前关闭来源业务。派发失败保留 Outbox 原 `event_id`、错误原因和尝试次数，由重试任务补偿。
