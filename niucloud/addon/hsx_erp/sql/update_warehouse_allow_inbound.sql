-- 仓库「允许调入」开关：把写死的"代卖仓不接受调入"改成每个仓库可配置。
-- 加列；存量代卖仓默认置为"不允许调入"以保持原有行为，其它仓默认允许。
ALTER TABLE `{{prefix}}erp_warehouse`
  ADD COLUMN `allow_inbound` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许调拨/设库位调入本仓(0否1是)' AFTER `business_type`;

UPDATE `{{prefix}}erp_warehouse` SET `allow_inbound` = 0 WHERE `business_type` = 'consignment';
