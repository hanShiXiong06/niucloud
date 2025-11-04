-- 扣费配置表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_deduction_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT '0' COMMENT '站点ID',
  `config_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称',
  `goods_series` varchar(100) NOT NULL DEFAULT '' COMMENT '适用商品系列（如：iPhone 16系列、iPhone 15系列）',
  `price_type` varchar(50) NOT NULL DEFAULT '' COMMENT '适用报价类型（如：花机/内爆、靓机/小花）',
  `deduction_items` json DEFAULT NULL COMMENT '扣费项目列表 [{"item":"屏稀碎","min":400,"max":600,"unit":"元"},...]',
  `remark_text` text COMMENT '完整的备注文本（用于显示）',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序（数字越小越靠前）',
  `is_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用 1=启用 0=禁用',
  `create_at` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_goods_series` (`goods_series`),
  KEY `idx_price_type` (`price_type`),
  KEY `idx_is_enable` (`is_enable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扣费配置表';

-- 插入示例数据
INSERT INTO `{{prefix}}recycle_deduction_config` 
(`site_id`, `config_name`, `goods_series`, `price_type`, `deduction_items`, `remark_text`, `sort`, `is_enable`, `create_at`, `update_at`) 
VALUES 
(0, 'iPhone 16 Pro系列扣费', 'iPhone 16 Pro', '', 
'[{"item":"屏稀碎","deduction":"扣400-600","unit":"元"},{"item":"前像头彩点","deduction":"-300","unit":"元"},{"item":"后像头彩点","deduction":"-350","unit":"元"},{"item":"后摄0.5/3倍大彩点","deduction":"-650","unit":"元"},{"item":"前蓝光","deduction":"-100","unit":"元"},{"item":"后蓝光","deduction":"-150","unit":"元"},{"item":"电池低于95","deduction":"扣100","unit":"元"}]',
'16pm/16pro扣费：\n屏稀碎扣400-600\n前像头彩点-300\n后像头彩点-350\n后摄0.5/3倍\n大彩点-650\n前蓝光-100\n后蓝光-150\n电池低于95扣100',
1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

(0, 'iPhone 16/Plus扣费', 'iPhone 16', '', 
'[{"item":"屏稀碎","deduction":"扣200-400","unit":"元"},{"item":"前像头彩点","deduction":"-300","unit":"元"},{"item":"后像头彩点","deduction":"-350","unit":"元"},{"item":"后摄单1倍大彩点","deduction":"650","unit":"元"},{"item":"前蓝光","deduction":"-100","unit":"元"},{"item":"后蓝光","deduction":"-150","unit":"元"},{"item":"电池低于95","deduction":"-100","unit":"元"}]',
'16plus/16扣费：\n屏稀碎扣200-400\n前像头彩点-300\n后像头彩点-350\n后摄单1倍\n大彩点650\n前蓝光-100\n后蓝光-150\n电池低于95-100',
2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
