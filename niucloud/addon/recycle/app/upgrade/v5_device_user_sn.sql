-- 设备表新增用户提交SN码字段
-- 用于区分用户前端提交的串号和管理端签收/质检录入的 IMEI/SN，避免互相覆盖

ALTER TABLE `saas_recycle_device`
  ADD COLUMN `user_sn` varchar(100) NOT NULL DEFAULT '' COMMENT '用户提交SN码' AFTER `imei`;

UPDATE `saas_recycle_device`
SET `user_sn` = `imei`
WHERE (`user_sn` IS NULL OR `user_sn` = '') AND `imei` <> '';
