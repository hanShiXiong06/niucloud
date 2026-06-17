-- 仓库「必须拍照」开关：开启后，设备进此仓（入库/调拨）必须先拍照才入库在库；
-- 关闭则不拍照，直接在 ERP 定价销售。默认 0（不要求）。
ALTER TABLE `{{prefix}}erp_warehouse`
  ADD COLUMN `require_photo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '进仓是否必须拍照(0否1是)：开启则走拍照→定价→上架流水线' AFTER `allow_inbound`;
