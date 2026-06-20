-- ERP 出库/调拨升级脚本(已装实例手动执行; CREATE IF NOT EXISTS 幂等)
-- ============ 出库 / 调拨(同行出货) ============
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_outbound_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `outbound_no` varchar(40) NOT NULL DEFAULT '' COMMENT '出库单号',
  `outbound_type` varchar(20) NOT NULL DEFAULT 'peer_sale' COMMENT 'peer_sale同行销售/scrap报废/other其他',
  `sale_channel` varchar(20) NOT NULL DEFAULT 'peer' COMMENT 'peer同行/mall商城(退回时据此回源上架)',
  `counterparty_id` int NOT NULL DEFAULT 0 COMMENT '往来单位锚(member_id, 同行也建member)',
  `counterparty_name` varchar(100) NOT NULL DEFAULT '' COMMENT '往来单位名(快照)',
  `counterparty_enterprise_id` int NOT NULL DEFAULT 0 COMMENT '关联企业主体ID(可选)',
  `settle_mode` varchar(20) NOT NULL DEFAULT 'now' COMMENT 'now现结/later回填/none无结算',
  `price_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending待回填/filled已定价',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '出货总额',
  `qty` int NOT NULL DEFAULT 0 COMMENT '台数',
  `status` varchar(20) NOT NULL DEFAULT 'completed' COMMENT 'completed已出库/void已作废',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '操作人',
  `operator_name` varchar(60) NOT NULL DEFAULT '' COMMENT '操作人名',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `out_at` int NOT NULL DEFAULT 0 COMMENT '出库时间',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cp` (`site_id`,`counterparty_id`),
  KEY `idx_no` (`outbound_no`),
  KEY `idx_type` (`site_id`,`outbound_type`,`price_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-出库单';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_outbound_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `outbound_id` int NOT NULL DEFAULT 0 COMMENT '出库单ID',
  `asset_id` int NOT NULL DEFAULT 0 COMMENT '资产ID',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '跨插件设备锚',
  `imei` varchar(64) NOT NULL DEFAULT '' COMMENT 'IMEI快照',
  `model` varchar(255) NOT NULL DEFAULT '' COMMENT '型号快照',
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '出库时成本(快照)',
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '出货价(回填前为0)',
  `receivable_emitted` tinyint NOT NULL DEFAULT 0 COMMENT '是否已生成应收(幂等)',
  `is_returned` tinyint NOT NULL DEFAULT 0 COMMENT '1=已退回(部分退回按明细标记)',
  `returned_at` int NOT NULL DEFAULT 0 COMMENT '退回时间戳',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_outbound` (`outbound_id`),
  KEY `idx_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-出库明细';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_asset_move_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `asset_id` int NOT NULL DEFAULT 0 COMMENT '资产ID',
  `from_warehouse_id` int NOT NULL DEFAULT 0,
  `from_location_id` int NOT NULL DEFAULT 0,
  `to_warehouse_id` int NOT NULL DEFAULT 0,
  `to_location_id` int NOT NULL DEFAULT 0,
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-调拨日志';
