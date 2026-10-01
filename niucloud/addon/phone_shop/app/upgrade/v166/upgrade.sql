CREATE TABLE IF NOT EXISTS `phone_shop_goods_change_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '操作人，0为系统任务',
  `operator_name` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人名称快照',
  `source` varchar(32) NOT NULL DEFAULT '' COMMENT '修改来源',
  `changes` longtext NOT NULL COMMENT '实际修改前后值 JSON',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_goods_log` (`site_id`, `goods_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手机商城商品修改记录';
