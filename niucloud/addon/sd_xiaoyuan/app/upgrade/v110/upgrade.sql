-- 为房屋租赁表添加审核拒绝原因字段
ALTER TABLE `xiaoyuan_house` ADD COLUMN `refuse_reason` varchar(200) DEFAULT '' COMMENT '审核拒绝原因' AFTER `status`;

-- 为闲置市场表添加审核拒绝原因字段
ALTER TABLE `xiaoyuan_secondhand` ADD COLUMN `refuse_reason` varchar(200) DEFAULT '' COMMENT '审核拒绝原因' AFTER `status`;

-- 为失物招领表添加审核拒绝原因字段
ALTER TABLE `xiaoyuan_lost_found` ADD COLUMN `refuse_reason` varchar(200) DEFAULT '' COMMENT '审核拒绝原因' AFTER `status`;
