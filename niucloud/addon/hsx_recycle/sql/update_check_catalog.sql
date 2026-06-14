-- 质检检测目录(扁平) + 全局选项级别字典 + 结构化选项加级别
-- 把 {prefix} 换成你的表前缀(如 ns_)。测试环境可直接执行。

-- 1) 扁平检测目录表：50万行拍机堂数据进这张，按型号索引，查得快、导得快。
--    用户给某型号自定义时再 fork 成结构化可编辑模板；本表是“默认数据源”。
CREATE TABLE IF NOT EXISTS `{prefix}recycle_check_catalog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `source` varchar(40) NOT NULL DEFAULT 'paijitang' COMMENT '目录来源/批次',
  `model_key` varchar(120) NOT NULL DEFAULT '' COMMENT '型号(如 1MORE_AERO)',
  `product_id` int NOT NULL DEFAULT 0 COMMENT '来源产品ID(拍机堂)',
  `group_name` varchar(120) NOT NULL DEFAULT '' COMMENT '分类(主观问题/功能使用问题…)',
  `group_sort` int NOT NULL DEFAULT 0 COMMENT '分类排序',
  `field_name` varchar(160) NOT NULL DEFAULT '' COMMENT '检测项',
  `field_sort` int NOT NULL DEFAULT 0 COMMENT '检测项排序',
  `default_option` varchar(255) NOT NULL DEFAULT '' COMMENT '默认选项',
  `options_json` json DEFAULT NULL COMMENT '全部选项(数组)',
  `import_hash` char(32) NOT NULL DEFAULT '' COMMENT '本行内容哈希(去重/变更判定)',
  `is_user_modified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户改过=1，重导永久跳过',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_src_model_field` (`site_id`,`source`,`model_key`,`field_name`),
  KEY `idx_site_model` (`site_id`,`model_key`),
  KEY `idx_site_product` (`site_id`,`product_id`),
  KEY `idx_site_group` (`site_id`,`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检检测目录(扁平)';

-- 2) 全局“选项→级别”字典：按选项文本标一次(如“碎屏”=异常)，全局生效。
--    severity: normal正常(success绿) / general一般(info) / abnormal异常(danger红)。默认 normal。
CREATE TABLE IF NOT EXISTS `{prefix}recycle_check_option_severity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `option_label` varchar(255) NOT NULL DEFAULT '' COMMENT '选项文本(全局键)',
  `severity` varchar(16) NOT NULL DEFAULT 'normal' COMMENT 'normal/general/abnormal',
  `is_user_modified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户改过=1，重导播种时跳过',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_option` (`site_id`,`option_label`),
  KEY `idx_site_severity` (`site_id`,`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检选项级别字典(全局)';

-- 3) 结构化模板的选项加“级别”(fork 出来的可编辑模板用；默认从全局字典带过来)
ALTER TABLE `{prefix}recycle_check_option`
  ADD COLUMN `severity` varchar(16) NOT NULL DEFAULT 'normal' COMMENT 'normal/general/abnormal' AFTER `is_default`;

-- 4) 导入批次记录(可选，便于看每次导入结果/进度)
CREATE TABLE IF NOT EXISTS `{prefix}recycle_check_import_batch` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `source` varchar(40) NOT NULL DEFAULT 'paijitang',
  `file_name` varchar(255) NOT NULL DEFAULT '',
  `total_rows` int NOT NULL DEFAULT 0 COMMENT '源行数',
  `inserted` int NOT NULL DEFAULT 0,
  `updated` int NOT NULL DEFAULT 0,
  `skipped_same` int NOT NULL DEFAULT 0 COMMENT '内容未变跳过',
  `skipped_user` int NOT NULL DEFAULT 0 COMMENT '用户改过跳过',
  `seeded_options` int NOT NULL DEFAULT 0 COMMENT '新播种到级别字典的选项数',
  `status` varchar(20) NOT NULL DEFAULT 'processing' COMMENT 'processing/completed/failed',
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site` (`site_id`,`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检目录导入批次';
