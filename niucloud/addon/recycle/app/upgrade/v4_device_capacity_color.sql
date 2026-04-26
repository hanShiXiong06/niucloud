-- 设备表新增内存和颜色字段
-- 执行时间: 2026-03-14

ALTER TABLE `saas_recycle_device`
  ADD COLUMN `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '内存/规格（如256GB）' AFTER `warranty_info`,
  ADD COLUMN `color` varchar(50) NOT NULL DEFAULT '' COMMENT '颜色（如深空黑色）' AFTER `capacity`;
