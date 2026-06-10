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
source_device_id, imei, imei2, sn, model, category_id,
capacity, color, ownership_type, purchase_cost,
suggested_sale_price, acquired_at, check_snapshot
```

来源系统可附加自己的快照字段，ERP 会保存在 `source_snapshot`，但核心库存逻辑只使用上述标准字段。
