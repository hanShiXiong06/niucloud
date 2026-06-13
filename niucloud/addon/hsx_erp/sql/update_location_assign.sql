-- 库位责任分配（人↔库位）。已安装站点执行此增量脚本。
CREATE TABLE IF NOT EXISTS `{{prefix}}erp_location_assign` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `warehouse_id` int NOT NULL DEFAULT 0 COMMENT '所属仓库(冗余,便于按仓筛选)',
  `location_id` int NOT NULL DEFAULT 0 COMMENT '负责的库位/分类(责任原子单元)',
  `uid` int NOT NULL DEFAULT 0 COMMENT '责任人(sys_user.uid)',
  `create_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_loc_uid` (`site_id`,`location_id`,`uid`),
  KEY `idx_site_uid` (`site_id`,`uid`),
  KEY `idx_site_wh` (`site_id`,`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP库位责任分配';
