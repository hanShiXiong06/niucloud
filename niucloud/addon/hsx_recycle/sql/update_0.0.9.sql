-- 0.0.9 方案A：公司与服务商绑定拆分
-- 1) 新增「公司-服务商绑定」表：一家公司可对接多个服务商，各自编码/面单能力独立
-- 2) 从快递公司表移除"塞在一起"的服务商编码与面单字段（迁移到绑定表）

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_delivery_company_provider` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `company_id` int NOT NULL DEFAULT '0' COMMENT '快递公司ID',
  `provider` varchar(30) NOT NULL DEFAULT '' COMMENT '服务商 yisu/kuaidi100',
  `provider_code` varchar(60) NOT NULL DEFAULT '' COMMENT '该服务商下的公司编码(kuaidicom / 易速productCode)',
  `electronic_sheet_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '该服务商是否出面单 0否1是',
  `exp_type` text COMMENT '业务类型列表JSON [{text,value}]',
  `print_style` text COMMENT '打印样式列表JSON [{template_name,template_size}]',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用1启用',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_company_provider` (`site_id`,`company_id`,`provider`),
  KEY `idx_site_provider` (`site_id`,`provider`,`electronic_sheet_switch`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收-快递公司服务商绑定';

-- 快递公司表精简：移除按服务商的编码与面单字段（已迁到绑定表）
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `kuaidi100_com`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `yisu_product_code`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `electronic_sheet_switch`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `exp_type`;
ALTER TABLE `{{prefix}}recycle_delivery_company` DROP COLUMN `print_style`;
