-- 出库单加「快递单号」：同行打包出货留物流单号，便于追踪。
ALTER TABLE `{{prefix}}erp_outbound_order`
  ADD COLUMN `express_no` varchar(64) NOT NULL DEFAULT '' COMMENT '快递/物流单号' AFTER `remark`;
