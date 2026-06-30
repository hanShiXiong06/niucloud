-- hsx_recycle 0.0.4（线上 0.0.3 → 0.0.4 一次性升级）
-- 任务驱动工单系统数据地基 + 性能优化，全部合并在此一个升级文件：
--   1) 实时在途计数 recycle_stat_current
--   2) 按日流水汇总 recycle_stat_daily
--   3) 任务认领 recycle_task_claim
--   4) recycle_device / recycle_order 补高频索引（在线 DDL）
--   5) 每日维度汇总 recycle_stat_daily_dim（型号/分类/成色/来源，抗千万级）
-- 表均 CREATE TABLE IF NOT EXISTS，已手动建过的会自动跳过。


-- 2. 实时态计数（看板/待办读它，免聚合）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stat_current` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `metric_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '指标键:stage_check/stage_price/stage_confirm/stage_pay 等,当前在该环节台数',
  `uid` int NOT NULL DEFAULT 0 COMMENT '维度:0=全站汇总,>0=经手人',
  `value` int NOT NULL DEFAULT 0 COMMENT '当前数量',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_site_metric_uid` (`site_id`,`metric_key`,`uid`) USING BTREE,
  KEY `site_id` (`site_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收实时态计数(看板/待办读它)';

-- 3. 按日流水汇总（趋势/绩效读它）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stat_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `stat_date` int NOT NULL DEFAULT 0 COMMENT '统计日 YYYYMMDD',
  `metric_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '指标键:enter_check/done_check/recycled/paid 等,当日发生量',
  `uid` int NOT NULL DEFAULT 0 COMMENT '维度:0=全站,>0=经手人',
  `value` int NOT NULL DEFAULT 0 COMMENT '台数',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_site_date_metric_uid` (`site_id`,`stat_date`,`metric_key`,`uid`) USING BTREE,
  KEY `site_date` (`site_id`,`stat_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收按日流水汇总(趋势/绩效读它)';

-- 任务驱动工单 · 认领表：店员将某环节的某台设备认领到人（责任到人）。
-- 纯新增表，不改动设备主表/状态机。一台设备在一个环节最多一条认领记录；进入下一环节即另起。

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_task_claim` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID',
  `stage_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '环节标识',
  `assignee_uid` int NOT NULL DEFAULT 0 COMMENT '认领人UID',
  `assignee_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '认领人名称快照',
  `claimed_at` int NOT NULL DEFAULT 0 COMMENT '认领时间',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_site_device_stage` (`site_id`,`device_id`,`stage_key`) USING BTREE,
  KEY `assignee` (`site_id`,`assignee_uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收任务认领(责任到人)';

ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_status_pay` (`site_id`,`status`,`pay_status`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_order` (`site_id`,`order_id`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_create` (`site_id`,`create_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_category` (`site_id`,`category_id`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_check_uid` (`site_id`,`check_uid`,`check_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_device` ADD INDEX `idx_site_price_uid` (`site_id`,`price_uid`,`final_price_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_status` (`site_id`,`status`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_sign_at` (`site_id`,`sign_at`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_pay_time` (`site_id`,`pay_time`), ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE `{{prefix}}recycle_order` ADD INDEX `idx_site_create` (`site_id`,`create_at`), ALGORITHM=INPLACE, LOCK=NONE;

-- 每日维度汇总表：把"型号分布/分类排行/成色/来源"这类维度统计预聚合到按日小表，
-- 分析页读这张小表(大小=天数×维度数,不随设备总量增长)，天然抗千万级；大表只留给明细钻取。
-- 历史天由"懒回填"算一次即固定，当天实时算。

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_stat_daily_dim` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `stat_date` int NOT NULL DEFAULT 0 COMMENT '统计日 YYYYMMDD',
  `dim_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '维度类型:category/model/grade/source',
  `dim_value` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '维度值:分类ID/型号名/成色/来源',
  `cnt` int NOT NULL DEFAULT 0 COMMENT '当日该维度设备数(按 create_at 归日)',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '当日该维度金额(final_price 合计)',
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_dim` (`site_id`,`stat_date`,`dim_type`,`dim_value`) USING BTREE,
  KEY `idx_site_type_date` (`site_id`,`dim_type`,`stat_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='回收每日维度汇总(型号/分类/成色/来源)';

-- ===== 快递公司 + 电子面单模板（原 0.0.8，合并至此）=====

-- 快递公司：统一字典，供电子面单模板/发件下拉选择；存各服务商的编码映射与可选业务类型/打印样式
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_delivery_company` (
  `company_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `company_name` varchar(60) NOT NULL DEFAULT '' COMMENT '快递公司名称',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT 'LOGO',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '官网链接',
  `express_code` varchar(50) NOT NULL DEFAULT '' COMMENT '通用/物流跟踪编码',
  `kuaidi100_com` varchar(50) NOT NULL DEFAULT '' COMMENT '快递100编码(kuaidicom)',
  `yisu_product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '易速产品编码(productCode)',
  `electronic_sheet_switch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否支持电子面单 0否1是',
  `exp_type` text COMMENT '业务类型列表JSON [{text,value}]',
  `print_style` text COMMENT '打印样式列表JSON [{template_name,template_size}]',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用1启用',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`company_id`),
  KEY `idx_site_status` (`site_id`,`status`,`electronic_sheet_switch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收-快递公司字典';

-- 电子面单模板：可管理对象（列表+新建向导+设默认）。provider 指向执行引擎(yisu/kuaidi100)；print_channel 决定打印方式(可切换)
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_express_sheet` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `template_name` varchar(60) NOT NULL DEFAULT '' COMMENT '模板名称',
  `provider` varchar(30) NOT NULL DEFAULT '' COMMENT '执行服务商 yisu/kuaidi100',
  `express_company_id` int NOT NULL DEFAULT '0' COMMENT '快递公司ID',
  `exp_type` varchar(50) NOT NULL DEFAULT '' COMMENT '业务类型值(来自公司exp_type)',
  `exp_type_name` varchar(60) NOT NULL DEFAULT '' COMMENT '业务类型名称',
  `print_style` varchar(60) NOT NULL DEFAULT '' COMMENT '打印样式标识(来自公司print_style)',
  `customer_name` varchar(120) NOT NULL DEFAULT '' COMMENT '电子面单客户账号',
  `customer_pwd` varchar(255) NOT NULL DEFAULT '' COMMENT '电子面单密码',
  `send_site` varchar(60) NOT NULL DEFAULT '' COMMENT '发件网点',
  `send_staff` varchar(60) NOT NULL DEFAULT '' COMMENT '发件员',
  `month_code` varchar(60) NOT NULL DEFAULT '' COMMENT '月结编码',
  `pay_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '邮费支付方式 1现付2到付3月结',
  `output_type` varchar(10) NOT NULL DEFAULT 'IMAGE' COMMENT '面单形式 IMAGE/HTML/CLOUD',
  `print_channel` varchar(10) NOT NULL DEFAULT 'browser' COMMENT '打印方式 browser网页/cloud云打印/lodop本地',
  `temp_id` varchar(120) NOT NULL DEFAULT '' COMMENT '面单模板ID(快递100)',
  `child_temp_id` varchar(120) NOT NULL DEFAULT '' COMMENT '子模板ID',
  `back_temp_id` varchar(120) NOT NULL DEFAULT '' COMMENT '回单模板ID',
  `siid` varchar(120) NOT NULL DEFAULT '' COMMENT '云打印机设备码(CLOUD)',
  `is_notice` tinyint(1) NOT NULL DEFAULT '0' COMMENT '上门揽件 0否1是',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用1启用',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认 0否1是',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_site_company` (`site_id`,`express_company_id`,`status`),
  KEY `idx_site_default` (`site_id`,`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收-电子面单模板';
