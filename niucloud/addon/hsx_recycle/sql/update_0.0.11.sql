-- hsx_recycle 0.0.11
-- 设备分类 Excel 导入改为后台任务，支持进度、结果、失败重试和历史记录。

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_device_model_import_task` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `operator_uid` int NOT NULL DEFAULT 0 COMMENT '操作人UID',
  `operator_name` varchar(60) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `source` varchar(50) NOT NULL DEFAULT 'recycle_spider' COMMENT '数据来源',
  `file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL DEFAULT '' COMMENT '服务端文件路径',
  `sheet_name` varchar(120) NOT NULL DEFAULT '' COMMENT '工作表名称',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/queued/processing/completed/partial/failed',
  `queue_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '创建时是否启用队列',
  `total_rows` int NOT NULL DEFAULT 0 COMMENT '数据总行数',
  `processed_rows` int NOT NULL DEFAULT 0 COMMENT '已处理行数',
  `created_count` int NOT NULL DEFAULT 0 COMMENT '新增数量',
  `updated_count` int NOT NULL DEFAULT 0 COMMENT '更新数量',
  `skipped_count` int NOT NULL DEFAULT 0 COMMENT '跳过数量',
  `error_count` int NOT NULL DEFAULT 0 COMMENT '错误数量',
  `result_json` longtext NULL COMMENT '结果与错误样例JSON',
  `message` varchar(500) NOT NULL DEFAULT '' COMMENT '任务提示',
  `error_message` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `start_at` int NOT NULL DEFAULT 0 COMMENT '开始时间',
  `finish_at` int NOT NULL DEFAULT 0 COMMENT '完成时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_create` (`site_id`,`create_at`)
) COMMENT='回收设备分类异步导入任务';
