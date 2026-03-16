-- 设备表新增系统版本和保修信息字段
-- 执行时间: 2026-03-14

ALTER TABLE `saas_recycle_device`
  ADD COLUMN `system_version` varchar(100) NOT NULL DEFAULT '' COMMENT '系统版本（如iOS 17.3.1）' AFTER `sn`,
  ADD COLUMN `warranty_info` varchar(100) NOT NULL DEFAULT '' COMMENT '保修信息（保修日期或"过保"）' AFTER `system_version`;
