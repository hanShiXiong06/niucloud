# hsx_finance · 二手机财务插件

> 状态：脚手架 + 折账核心已落地（v0.0.1）。后端未经 PHP 运行时验证，需在你实例安装回归。

## 它负责什么（边界红线）

- **只负责**：往来单位的**应付/应收**事实归集、**结算**（现金 / 折账 / 混合）、**折账**（净额冲抵）。
- **不负责**：进销存/成本利润（ERP）、业务流转（回收/中台）。
- **解耦**：只消费业务插件发来的"应付/应收产生"事件落库；结算后回发"结算完成"事件让业务/ERP 自行更新展示。**绝不跨插件读写表**。

## 折账是什么

同一往来单位，应付（我欠他，来自回收）和应收（他欠我，来自销售）可净额冲抵：
应付 4000 + 应收 5000 → 折账 4000，对方再净付我 1000。
**只有财务能同时看到同一单位两侧**，所以折账只能在这里做。

## 数据模型（finance_ 前缀）

- `finance_payable` 应付（我欠往来单位）
- `finance_receivable` 应收（往来单位欠我）
- `finance_settlement` 结算单（method=cash/offset/mixed）
- `finance_settlement_link` 结算核销明细（每条应付/应收被核销了多少，其中现金/折账各多少）

## 事件契约（与《应付与结算契约》一致，`.vN` + `event_id` 幂等）

消费（业务 → 财务，ThinkPHP 通道名 → 契约名）：
- `FinancePayableCreated`  → `finance.payable.created.v1`
- `FinanceReceivableCreated` → `finance.receivable.created.v1`

产出（财务 → 业务/ERP）：
- `FinanceSettlementCompleted` → `finance.settlement.completed.v1`

### 生产者接入示例（回收确认回收时发应付）

```php
// 在回收插件内，确认回收/定价提交后，故障隔离地发事件：
try {
    \think\facade\Event::trigger('FinancePayableCreated', [
        'event'             => 'finance.payable.created.v1',
        'event_id'          => 'recycle_payable_' . $deviceId, // 幂等键
        'site_id'           => $siteId,
        'counterparty_id'   => $memberId,      // 往来单位=回收客户
        'counterparty_name' => $memberName,
        'amount'            => $recyclePrice,   // 应付=回收价
        'source_type'       => 'recycle_device',
        'source_no'         => $orderNo,
        'source_device_id'  => $deviceId,
        'occurred_at'       => time(),
    ]);
} catch (\Throwable $e) { /* 仅记日志，不打断回收 */ }
```

销售成交发 `FinanceReceivableCreated`（payload 同形，amount=售价）。

## 接口（adminapi，前缀 finance/）

- `GET finance/balance/board` 往来单位余额看板（应付/应收/可折账/净额）
- `GET finance/payable/lists` `GET finance/receivable/lists` 应付/应收列表
- `GET finance/payable/outstanding?counterparty_id=` 待结应付（结算选择用）
- `GET finance/receivable/outstanding?counterparty_id=` 待结应收
- `POST finance/settlement/preview` 结算预演（只算折账/现金，不落库）
- `POST finance/settlement/settle` 确认结算（自动判定现金/折账/混合）

## 前端

- 菜单：二手机财务 → 往来对账·折账（`hsx_finance/settlement`）
- 页面：`admin/views/settlement/board.vue`（余额看板 + 折账结算弹窗，含预演）

## 现状与待办

- 应付/应收**目前没有生产者**在发事件（契约预留）。接入时按上面示例在回收/销售侧发事件即可，财务零改动。
- 待办：结算单/明细的查询页；结算作废/冲红；多币种；部分结算（当前按"全额结清所选项"，模型已留 partial 状态与 settled_amount 余量字段，可平滑扩展）。
- **未经 PHP 运行时验证**：安装、建表、结算事务、聚合 SQL 需在你实例回归。
