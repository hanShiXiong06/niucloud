ALTER TABLE `xiaoyuan_order`
ADD COLUMN `auto_confirm_time` int(11) NOT NULL DEFAULT 0 COMMENT '自动确认时间' AFTER `complete_time`;

ALTER TABLE `xiaoyuan_order`
ADD COLUMN `confirm_time` int(11) NOT NULL DEFAULT 0 COMMENT '确认完成时间' AFTER `auto_confirm_time`;

ALTER TABLE `xiaoyuan_order`
ADD COLUMN `confirm_source` varchar(20) NOT NULL DEFAULT '' COMMENT '确认来源 USER/AUTO/ADMIN' AFTER `confirm_time`;
