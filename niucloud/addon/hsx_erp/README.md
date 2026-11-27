# 二手机 ERP

第一版提供：

- ERP 独立手工建档入库，不依赖回收插件。
- 回收/代卖设备批量同步到 ERP 待入库池。
- 物理设备身份与多次经营周期。
- 待入库资产、确认入库、库存流水和初始成本流水。
- 标准设备快照、Inbox 幂等消费和 Outbox 领域事件。
- ERP 设备库存列表和设备时间线。

当前链路：

```text
ERP 手工建档 ──────────────┐
                           ├→ ErpInboundService
回收插件发布标准入库事件 ───┘
→ 幂等创建设备身份、经营周期、待入库资产和入库草稿
→ 入库人员确认
→ 写入正式库存、库存流水、采购成本流水和 erp.asset.stocked 事件
```

## 插件边界

- `hsx_erp` 不读取 `hsx_recycle` 的模型或数据表。
- `hsx_recycle` 只负责将业务数据转换为标准设备快照并发布
  `ErpDeviceInboundRequested`。
- ERP 独立录入和回收同步共用 `ErpInboundService`，库存、成本和幂等规则只有一套。
- 后续博远或其他 ERP 可监听同一个标准入库事件，并独立完成字段转换、队列重试和结果记录。
- 财务插件订阅 `ErpDomainEvent` 中的入库、成本变化、销售出库等事件，生成应收应付和资金流水，不直接修改 ERP 库存表。

## 标准入库设备字段

```text
source_device_id, imei, imei2, sn, model, catalog_product_id,
capacity, color, ownership_type, purchase_cost,
counterparty, member_id, payable_amount, paid_amount, settlement_status,
suggested_sale_price, acquired_at, check_snapshot,
recycle_pricing_snapshot, sales_pricing_snapshot, refurbishment
```

`member_id` 是本次业务的具体经办会员，ERP 解析后保存的 `counterparty_id` 是最终财务结算主体。一个门店主体可以关联多个会员；会员改绑只影响后续业务，历史单据保留原结算主体。

`recycle_pricing_snapshot` 表示回收插件里给客户的回收价格事实；`sales_pricing_snapshot` 表示 ERP 销售侧价格建议或后续销售定价事实。两者不能混用。

`refurbishment.required=true` 表示来源业务已决定需要整备；ERP 确认入库后会自动创建整备工单并分配给 `refurbishment.assignee`。

## 卸载数据

卸载插件时会先将全部 `erp_*` 表结构和数据导出为单个 SQL 文件，备份目录为：

```text
runtime/adminapi/backup/hsx_erp/uninstall/
```

备份完整落盘后才会删除 ERP 数据表；备份失败时卸载会中止，不会删除表。

来源系统可附加自己的快照字段，ERP 会保存在 `source_snapshot`，但核心库存逻辑只使用上述标准字段。

## 规划文档

- [ERP V1 产品与技术规格](docs/ERP_V1产品与技术规格.md)
- [ERP V1 验收清单](docs/ERP_V1验收清单.md)
- [插件独立与集成架构](docs/插件独立与集成架构.md)
