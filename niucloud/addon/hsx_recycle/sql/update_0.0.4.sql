-- hsx_recycle 0.0.4: device model alias bindings.
-- Replace {{prefix}} with the actual database prefix for manual execution.
-- Creates only the binding table. No config/catalog data is changed or migrated.
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_device_model_alias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '绑定ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `alias` varchar(120) NOT NULL DEFAULT '' COMMENT '设备工具返回的型号别名',
  `normalized_alias` varbinary(512) NOT NULL DEFAULT '' COMMENT '标准化别名，按字节精确匹配',
  `category_id` int NOT NULL DEFAULT 0 COMMENT '本站叶子型号ID',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '最近绑定操作人ID',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '首次绑定时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '最近绑定时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_alias` (`site_id`,`normalized_alias`),
  KEY `idx_site_category` (`site_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收设备型号别名绑定表';
