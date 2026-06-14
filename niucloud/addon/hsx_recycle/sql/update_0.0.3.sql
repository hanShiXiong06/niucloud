-- hsx_recycle 0.0.3
-- 下游流转回流：在回收设备上镜像 ERP/中台 的生命周期阶段，使回收侧能看全程（已入库/转中台/已定价/已售）。
-- 纯加法列，不改动现有状态机；由回收侧监听器幂等写入，缺失插件时列保持默认值，无副作用。

ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `downstream_stage` tinyint NOT NULL DEFAULT 0 COMMENT '下游流转阶段镜像：0-未流转,10-已入库,20-转中台待拍照,30-已定价可售,40-已售下架' AFTER `last_cost_adjust_time`,
  ADD COLUMN `downstream_stage_at` int NOT NULL DEFAULT 0 COMMENT '下游流转阶段更新时间' AFTER `downstream_stage`,
  ADD COLUMN `downstream_erp_asset_id` int NOT NULL DEFAULT 0 COMMENT '关联ERP资产ID(下游回流)' AFTER `downstream_stage_at`,
  ADD COLUMN `downstream_sale_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '中台销售定价(下游回流)' AFTER `downstream_erp_asset_id`,
  ADD COLUMN `downstream_event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '最近一次应用的下游事件ID(幂等追溯)' AFTER `downstream_sale_price`,
  ADD COLUMN `target_warehouse_id` int NOT NULL DEFAULT 0 COMMENT '目标仓库ID(ERP安装时定价选择,0为未指定)' AFTER `downstream_event_id`,
  ADD COLUMN `target_warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标仓库名称快照' AFTER `target_warehouse_id`,
  ADD COLUMN `target_location_id` int NOT NULL DEFAULT 0 COMMENT '目标库位ID(定价手动选择,0为未指定)' AFTER `target_warehouse_name`,
  ADD COLUMN `target_location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标库位名称快照' AFTER `target_location_id`;

-- 修复历史安装中"报价单每日快照"任务的非法 cron：
-- 旧 time JSON 误用 minute 且缺 day，type=day 拼出 `0 * 23 */* * *`，被 workerman/crontab 判为非法字符串导致调度进程崩溃。
-- 修正为合法的"每天 23:00 执行"（day=1 即每天）。
UPDATE `{{prefix}}sys_schedule`
  SET `time` = '{"type":"day","day":1,"hour":23,"min":0}'
  WHERE `addon` = 'hsx_recycle' AND `key` = 'quote_daily_snapshot';

-- 选择频次统计表（通用：按场景记录某用户被选中的次数，用于"常用优先"排序，如整备负责人）
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_user_pick_stat` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `scene` varchar(50) NOT NULL DEFAULT '' COMMENT '选择场景，如 refurbishment_assignee',
  `user_id` int NOT NULL DEFAULT 0 COMMENT '被选用户ID',
  `pick_count` int NOT NULL DEFAULT 0 COMMENT '被选次数',
  `last_pick_at` int NOT NULL DEFAULT 0 COMMENT '最近被选时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_scene_user` (`site_id`,`scene`,`user_id`),
  KEY `idx_site_scene_count` (`site_id`,`scene`,`pick_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收选择频次统计表';

-- ============ 质检：参考表 + 数据表(全ID映射) ============
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_dict` (
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

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_import_batch` (
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

-- recycle_check_option 加 severity(级别)列（仅 0.0.1/0.0.2 升级时执行；全新安装已在 install.sql 含此列）
ALTER TABLE `{{prefix}}recycle_check_option`
  ADD COLUMN `severity` varchar(16) NOT NULL DEFAULT 'normal' COMMENT '级别 normal/general/abnormal' AFTER `is_default`;
