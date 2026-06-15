-- ERP 盘点升级脚本(已装实例手动执行; CREATE IF NOT EXISTS 幂等)
-- ============ 库存盘点 ============
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stocktake_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `stocktake_no` varchar(40) NOT NULL DEFAULT '' COMMENT '盘点单号',
  `warehouse_id` int NOT NULL DEFAULT 0 COMMENT '盘点仓库',
  `warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '仓库名快照',
  `location_id` int NOT NULL DEFAULT 0 COMMENT '盘点库位(0=整仓)',
  `location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '库位名快照',
  `status` varchar(20) NOT NULL DEFAULT 'counting' COMMENT 'counting盘点中/finished已完成/void作废',
  `system_count` int NOT NULL DEFAULT 0 COMMENT '应在库台数(快照时)',
  `counted_count` int NOT NULL DEFAULT 0 COMMENT '实盘到台数',
  `match_count` int NOT NULL DEFAULT 0 COMMENT '账实相符',
  `loss_count` int NOT NULL DEFAULT 0 COMMENT '盘亏台数',
  `profit_count` int NOT NULL DEFAULT 0 COMMENT '盘盈台数',
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `started_at` int NOT NULL DEFAULT 0,
  `finished_at` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wh` (`site_id`,`warehouse_id`,`status`),
  KEY `idx_no` (`stocktake_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-盘点单';

CREATE TABLE IF NOT EXISTS `{{prefix}}erp_stocktake_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `stocktake_id` int NOT NULL DEFAULT 0 COMMENT '盘点单ID',
  `asset_id` int NOT NULL DEFAULT 0 COMMENT '资产ID(盘盈未建档为0)',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '跨插件设备锚',
  `imei` varchar(64) NOT NULL DEFAULT '',
  `model` varchar(255) NOT NULL DEFAULT '',
  `system_status` varchar(32) NOT NULL DEFAULT '' COMMENT '快照时库存状态',
  `result` varchar(20) NOT NULL DEFAULT 'uncounted' COMMENT 'uncounted待盘/matched相符/loss盘亏/profit盘盈',
  `counted` tinyint NOT NULL DEFAULT 0 COMMENT '是否盘到',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_take` (`stocktake_id`,`result`),
  KEY `idx_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-盘点明细';
