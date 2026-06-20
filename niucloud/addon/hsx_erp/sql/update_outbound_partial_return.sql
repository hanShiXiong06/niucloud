-- 部分退回: 出库单追踪销售渠道 + 出库明细追踪退回状态
-- 手动执行(已装实例)；IF NOT EXISTS 幂等

ALTER TABLE `{{prefix}}erp_outbound_order`
  ADD COLUMN  `sale_channel` varchar(20) NOT NULL DEFAULT 'peer'
    COMMENT 'peer同行/mall商城(退回时据此回源上架)'
    AFTER `outbound_type`;

ALTER TABLE `{{prefix}}erp_outbound_item`
  ADD COLUMN  `is_returned` tinyint NOT NULL DEFAULT 0
    COMMENT '1=已退回(部分退回按明细标记)'
    AFTER `receivable_emitted`,
  ADD COLUMN  `returned_at` int NOT NULL DEFAULT 0
    COMMENT '退回时间戳'
    AFTER `is_returned`;
