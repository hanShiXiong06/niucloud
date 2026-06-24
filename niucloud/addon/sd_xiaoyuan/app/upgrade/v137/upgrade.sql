ALTER TABLE `xiaoyuan_lost_found` ADD COLUMN `contact_wechat` varchar(50) NOT NULL DEFAULT '' COMMENT '联系微信' AFTER `contact_mobile`;
ALTER TABLE `xiaoyuan_secondhand` ADD COLUMN `contact_wechat` varchar(50) NOT NULL DEFAULT '' COMMENT '联系微信' AFTER `contact_mobile`;
