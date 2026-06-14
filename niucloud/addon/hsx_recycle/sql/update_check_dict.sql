-- 质检：参考表(ID+中文) + 数据表(全ID映射)。两张表。
-- 把 {prefix} 换成你的表前缀(如 ns_)。
-- 说明：去重在本地完成，配套的 dict.tsv / data.tsv 用 LOAD DATA 灌入即可，服务器零计算。

-- 参考表：所有中文只此一份。dict_type: group分类 / field检测项 / option选项。
-- option 行带 severity(级别)：normal正常(success绿)/general一般(info)/abnormal异常(danger红)，默认 normal。
CREATE TABLE IF NOT EXISTS `{prefix}recycle_check_dict` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `dict_type` varchar(10) NOT NULL DEFAULT '' COMMENT 'group分类/field检测项/option选项',
  `text` varchar(255) NOT NULL DEFAULT '' COMMENT '中文文本',
  `severity` varchar(16) NOT NULL DEFAULT 'normal' COMMENT '仅option用: normal/general/abnormal',
  `is_user_modified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户改过=1，重导不覆盖',
  `sort` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_type_text` (`site_id`,`dict_type`,`text`(180)),
  KEY `idx_site_type_sev` (`site_id`,`dict_type`,`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检参考表(字典:分类/检测项/选项)';

-- 数据表：一机一档的检测项，全部用ID映射。一行=某型号的某个检测项。
CREATE TABLE IF NOT EXISTS `{prefix}recycle_check_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `model_key` varchar(120) NOT NULL DEFAULT '' COMMENT '型号(如 1MORE_AERO)',
  `product_id` int NOT NULL DEFAULT 0 COMMENT '来源产品ID',
  `group_id` int NOT NULL DEFAULT 0 COMMENT '分类→dict.id',
  `field_id` int NOT NULL DEFAULT 0 COMMENT '检测项→dict.id',
  `default_option_id` int NOT NULL DEFAULT 0 COMMENT '默认选项→dict.id',
  `option_ids` varchar(500) NOT NULL DEFAULT '' COMMENT '全部选项id(逗号分隔，按顺序)',
  `import_hash` char(32) NOT NULL DEFAULT '' COMMENT '内容哈希(去重/变更判定)',
  `is_user_modified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户改过=1，重导永久跳过',
  `sort` int NOT NULL DEFAULT 0,
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_model_field` (`site_id`,`model_key`,`field_id`),
  KEY `idx_site_model` (`site_id`,`model_key`),
  KEY `idx_site_product` (`site_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检数据表(全ID映射)';

-- 导入批次(可选)
CREATE TABLE IF NOT EXISTS `{prefix}recycle_check_import_batch` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `source` varchar(40) NOT NULL DEFAULT 'paijitang',
  `file_name` varchar(255) NOT NULL DEFAULT '',
  `total_rows` int NOT NULL DEFAULT 0,
  `inserted` int NOT NULL DEFAULT 0,
  `updated` int NOT NULL DEFAULT 0,
  `skipped_same` int NOT NULL DEFAULT 0,
  `skipped_user` int NOT NULL DEFAULT 0,
  `new_dict` int NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'processing',
  `error_message` varchar(1000) NOT NULL DEFAULT '',
  `operator_uid` int NOT NULL DEFAULT 0,
  `operator_name` varchar(60) NOT NULL DEFAULT '',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site` (`site_id`,`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检导入批次';
