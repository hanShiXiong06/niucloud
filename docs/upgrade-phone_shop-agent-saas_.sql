-- =====================================================================
-- phone_shop 多站代理铺货与联动 · 升级 SQL（库前缀 saas_）
-- 一次性执行即可。幂等说明见文末。
-- =====================================================================

-- 1) 站点代理订阅关系表（新表）
CREATE TABLE IF NOT EXISTS `saas_phone_shop_agent` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `master_site_id` int NOT NULL DEFAULT 0 COMMENT '主站id(被代理方,如100005)',
  `agent_site_id` int NOT NULL DEFAULT 0 COMMENT '子站id(代理方,接收铺货)',
  `markup_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '子站固定加价(主站零售价上叠加,默认0)',
  `subscribe_category` tinyint NOT NULL DEFAULT 1 COMMENT '是否订阅主站引用数据同步 1是0否',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_master_agent` (`master_site_id`,`agent_site_id`),
  KEY `idx_agent_site` (`agent_site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商城站点代理订阅关系表';

-- 2) 商品分类：加跨站公共键 + 来源主站
ALTER TABLE `saas_phone_shop_goods_category`
  ADD COLUMN `category_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站分类公共键(代理同步按此关联)' AFTER `site_id`,
  ADD COLUMN `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)' AFTER `category_no`,
  ADD KEY `idx_category_no` (`site_id`,`category_no`);

-- 3) 商品品牌
ALTER TABLE `saas_phone_shop_goods_brand`
  ADD COLUMN `brand_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站品牌公共键(代理同步按此关联)' AFTER `site_id`,
  ADD COLUMN `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)' AFTER `brand_no`,
  ADD KEY `idx_brand_no` (`site_id`,`brand_no`);

-- 4) 商品参数
ALTER TABLE `saas_phone_shop_goods_attr`
  ADD COLUMN `attr_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站参数公共键(代理同步按此关联)' AFTER `site_id`,
  ADD COLUMN `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)' AFTER `attr_no`,
  ADD KEY `idx_attr_no` (`site_id`,`attr_no`);

-- 5) 内存规格分组
ALTER TABLE `saas_phone_shop_memory_group`
  ADD COLUMN `memory_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站内存分组公共键(代理同步按此关联)' AFTER `site_id`,
  ADD COLUMN `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)' AFTER `memory_no`,
  ADD KEY `idx_memory_no` (`site_id`,`memory_no`);

-- 6) 内存规格
ALTER TABLE `saas_phone_shop_memory_spec`
  ADD COLUMN `spec_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站内存规格公共键(代理同步按此关联)' AFTER `site_id`,
  ADD COLUMN `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)' AFTER `spec_no`,
  ADD KEY `idx_spec_no` (`site_id`,`spec_no`);

-- =====================================================================
-- 说明：
-- · saas_phone_shop_goods 的 source / goods_no 列已存在，无需改（仅语义变化，代码层处理）。
-- · 第1条 CREATE TABLE IF NOT EXISTS 可重复执行。
-- · 第2~6条 ALTER 为一次性；若某条报 “Duplicate column name”，说明该列已加过，跳过即可。
-- =====================================================================
