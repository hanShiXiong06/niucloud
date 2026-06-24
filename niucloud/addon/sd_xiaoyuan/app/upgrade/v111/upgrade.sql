-- 为接单员表添加审核时间字段
ALTER TABLE `xiaoyuan_runner` ADD COLUMN `audit_time` int(11) DEFAULT 0 COMMENT '审核时间' AFTER `refuse_reason`;
