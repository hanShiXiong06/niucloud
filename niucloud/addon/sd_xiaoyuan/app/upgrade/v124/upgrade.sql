ALTER TABLE `xiaoyuan_runner_level` ADD COLUMN `rate_parttime` tinyint(3) NULL DEFAULT NULL COMMENT '兼职招聘佣金比例(%)' AFTER `rate_group`;
ALTER TABLE `xiaoyuan_runner_level` ADD COLUMN `rate_companion` tinyint(3) NULL DEFAULT NULL COMMENT '约伴组局佣金比例(%)' AFTER `rate_parttime`;
