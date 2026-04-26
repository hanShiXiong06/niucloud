-- 可视化模板编辑器增强 - 新增打印机绑定和触发时机字段
-- 执行时间: 2026-03-14

ALTER TABLE `saas_recycle_printer_template`
  ADD COLUMN `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '绑定打印机ID，0为默认打印机' AFTER `is_default`,
  ADD COLUMN `trigger_event` varchar(20) NOT NULL DEFAULT '' COMMENT '触发时机：draft=暂存,complete=完成,空=手动' AFTER `printer_id`;
