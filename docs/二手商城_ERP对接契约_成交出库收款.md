# 二手商城 ⇄ ERP · 成交/出库/收款 对接契约

> 目的：把"成交（线下开单 / 线上支付）"和"出库 / 收款 / 设备全链路"在 商城(phone_shop) 与 ERP(hsx_erp / hsx_device_asset) 之间打通。
> 状态：契约草案，供两侧对齐后实现。

---

## 1. 架构原则（一个引擎，多个触点）

```
触点(发起成交)                          引擎(执行+记账)
  ├─ ERP 出库页(后台直接卖)
  ├─ 商城后台开单(线下,入口在“商品中心”)  ──→  ERP = 财务中台 + 出库引擎
  └─ 商城线上下单+支付(将来,可选)               · 出库 / 设备状态 / 应收 / 现金
                                                · 收款账户 / 多账户拆分 / 资金流水
            ↑ 回流(订单/商品状态/推送)            · 每台设备全链路(回收→销售→收款)
  商城 = 销售触点 + 客户壳(不重建财务)
```

- **唯一真相 = ERP**。所有成交不论从哪个触点发起，最终都由 ERP 执行出库与记账。
- **商城不重建财务**：只采集参数、调用 ERP、接收回流（订单/商品状态/推送）。
- **无应收 ≠ 不记账**：付清（线下现结/线上微信）也要在 ERP 记一条"现金销售 + 收款流水"，财务可见"卖了多少、怎么结清、打到哪个账户"。

---

## 2. 标识与锚（对齐口径）

| 概念 | 字段 | 说明 |
|---|---|---|
| 设备唯一身份 | `erp_asset_id` | 商城 SKU `erp_asset_id` ↔ ERP 资产 id，一物一码关联键 |
| 回收设备 | `source_device_id` | 设备全链路回溯回收侧 |
| 买家/客户 | `member_id` | 往来单位锚；ERP 交易人(counterparty) 经 `erp_counterparty_member` 映射到 member_id（同行也建 member） |
| 商城订单 | `order_no` | 商城订单号 |
| ERP 出库单 | `outbound_no` | ERP 出库单号 |
| 幂等键 | `event_id` | 所有事件带版本化 event_id，重复发不重复处理 |

---

## 3. 商城 → ERP

### 3.1 线下开单出库（商品中心开单，交互式）
商城开单面板采集参数 → 调 ERP 出库（建议：同步调用 ERP 出库接口拿即时结果；异步回流另走 5.1）。

```jsonc
// 请求 mall.outbound.request.v1
{
  "site_id": 1,
  "request_id": "mall_ob_<order_no>",      // 幂等
  "counterparty": { "member_id": 123, "name": "张三(同行)" },
  "settle_mode": "now",                     // now现结 / later挂账
  "devices": [
    { "erp_asset_id": 67890, "sale_price": 8000.00 }
  ],
  "payments": [                              // 多账户收款(现结时;挂账可空)
    { "account_id": 11, "method": "alipay", "amount": 4000.00 },
    { "account_id": 12, "method": "wechat", "amount": 4000.00 }
  ],
  "buyer_type": "b",                         // c散客 / b同行
  "staff_id": 1001,                          // 开单销售员
  "remark": "客户讲价后实付"
}
// 期望返回: { outbound_no, receivable(挂账时), settled_amount, status }
```

### 3.2 线上成交出库（将来开线上支付时；挂在 `PhoneShopOrderPay`）
商城线上订单支付成功 → 对订单里 `sku.erp_asset_id > 0` 的设备发：
```jsonc
// event MallOrderSold (mall.order.sold.v1)
{
  "event_name": "mall.order.sold.v1",
  "event_id": "mall_sold_<order_no>",
  "site_id": 1,
  "payload": {
    "order_no": "PS202606170001",
    "member_id": 123,
    "items": [ { "erp_asset_id": 67890, "sale_price": 8000.00 } ],
    "payment_mode": "online",
    "paid": true,
    "payments": [ { "method": "wechat", "amount": 8000.00, "out_trade_no": "wx..." } ]
  }
}
// ERP: 对这些设备出库(已售)、记现金销售+收款流水、付清不发应收
```

### 3.3 退款 / 退货（商城退款成功）
```jsonc
// event MallOrderRefunded (mall.order.refunded.v1)
{ "event_name": "mall.order.refunded.v1", "event_id": "mall_refund_<refund_no>",
  "site_id": 1,
  "payload": { "order_no": "...", "items": [ { "erp_asset_id": 67890 } ], "reason": "..." } }
// ERP: 设备退货回在库 / 作废应收 / 现金退款流水
```

---

## 4. ERP → 商城

### 4.1 出库成交回流（线下成交 → 商城建订单+商品状态+推送）
ERP 出库完成后发（现有 `ErpDomainEvent / erp.asset.sold.v1`，**需扩字段**）：
```jsonc
{
  "event_name": "erp.asset.sold.v1",
  "event_id": "erp_sold_<asset_id>_<ts>",
  "site_id": 1,
  "aggregate_id": 67890,
  "payload": {
    "asset_id": 67890,
    "source_device_id": 1,
    "outbound_no": "OB...",
    "member_id": 123,                 // ★需补:买家member(从 erp_counterparty_member 查)
    "sale_price": 8000.00,            // ★需补:成交价
    "result_status": "sold",          // ★需补:sold已售(现结) / locked锁定(挂账) —— 商城据此置商品状态
    "settle_mode": "now",            // ★需补
    "payment_mode": "offline",
    "payments": [ {"method":"alipay","amount":4000},{"method":"wechat","amount":4000} ] // ★需补:多账户收款(展示用)
  }
}
// 商城: 找到 erp_asset_id 对应商品 → 建商城订单(挂member,线下完成) → 商品 sale_status=sold/locked → 推送客户
```

### 4.2 退回（ERP 退回锁定单 → 商城订单作废+商品回在售）
```jsonc
{ "event_name": "erp.asset.returned.v1", "event_id": "erp_return_<asset_id>_<ts>",
  "site_id": 1, "aggregate_id": 67890,
  "payload": { "asset_id": 67890, "source_device_id": 1, "outbound_no": "..." } }
// 商城: 订单作废 + 商品 sale_status=available(回在售)
```

### 4.3 价格回填（挂账后回填实际价 → 商城订单金额更新）
```jsonc
{ "event_name": "erp.asset.price_filled.v1", "event_id": "erp_pricefill_<asset_id>_<ts>",
  "site_id": 1, "aggregate_id": 67890,
  "payload": { "asset_id": 67890, "outbound_no": "...", "final_price": 7980.00 } }
// 商城: 对应订单金额更新为实付(讲价后) → 客户可见
```

---

## 5. 多账户收款结构（一笔成交，多条收款）

一台 8000，支付宝 4000 + 微信 4000：
```jsonc
"payments": [
  { "account_id": 11, "method": "alipay", "amount": 4000.00 },
  { "account_id": 12, "method": "wechat", "amount": 4000.00 }
]
```
- ERP 出库收款由"单账户 `capitalAccountId`"升级为 **`payments[]`（账户+方式+金额）**，校验 `sum(amount)` 与应收/售价一致（现结）。
- 每条 payment → ERP 一条资金流水，财务可见"怎么结清的"。

---

## 6. 财务口径

| 场景 | 应收 | 收款流水 |
|---|---|---|
| 线下挂账(settle later) | 发应收(待结)，回填后据实 | 结款时记 |
| 线下现结付清 | 不发应收 | 记现金销售 + 多账户流水 |
| 线上微信付清 | 不发应收 | 记现金销售 + 微信流水 |
| 退货/退款 | 作废应收 | 退款流水 |

---

## 7. 设备全链路字段（ERP 侧补齐）
ERP 设备资产已有：回收源(`source_device_id`)、出库(买家 counterparty / 售价 / 时间 / 出库单)。**补**：`payment_mode`(现结/挂账)、收款 `payments[]`(账户/方式/金额)。补齐后每台设备"回收→销售→收款"全链路完整。

---

## 8. 状态映射

| 商城 `sale_status` | ERP `inventory_status` | 触发 |
|---|---|---|
| available 在售 | 在库 | 建品上架 / 退回 |
| locked 锁定 | locked(挂账可退) | 挂账出库 |
| sold 已售(终态) | outbound(已售下架) | 现结出库 / 线上付清 |

商城 `status`(0/1) 控制可见性，`sale_status` 控制售卖生命周期；可购买 = 可见 且 available。

---

## 9. 约束
- 所有跨插件交互带 `event_id` 幂等；副作用 try/catch 故障隔离，失败只记日志不打断主流程。
- 一物一码 `stock=1` + `sale_status` 双重防超卖。
- 一单可混 一物一码机(erp_asset_id>0,走ERP出库) + 标品配件(无,商城自发货)；出库只对前者。

---

## 10. UI 目标（商城开单面板）
入口在**商品中心**（在售商品列表每台机一个"开单/卖出"动作）。面板**图标化、好看易用**：选客户、现结/挂账切换、多账户收款（账户图标 + 金额）、一键完成。
