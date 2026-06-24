ALTER TABLE `xiaoyuan_runner_level` ADD COLUMN `rate_class` tinyint(3) NULL DEFAULT NULL COMMENT '代上课佣金比例(%)' AFTER `rate_queue`;
