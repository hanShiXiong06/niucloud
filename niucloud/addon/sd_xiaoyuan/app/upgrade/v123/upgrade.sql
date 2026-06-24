ALTER TABLE `xiaoyuan_runner` ADD COLUMN `can_jiedan` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许抢单接单:0禁止,1允许' AFTER `is_online`;
