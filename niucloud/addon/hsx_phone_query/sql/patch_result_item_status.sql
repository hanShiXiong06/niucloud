ALTER TABLE `{{prefix}}hsx_phone_query_info`
  ADD COLUMN `query_status` tinyint NOT NULL DEFAULT 3 COMMENT '查询状态：3成功 -1失败' AFTER `info`,
  ADD COLUMN `refund_status` tinyint NOT NULL DEFAULT 0 COMMENT '退款状态：0未退款 1已退款' AFTER `query_status`,
  ADD COLUMN `refund_money` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '单条退款金额' AFTER `refund_status`,
  ADD COLUMN `refund_point` int NOT NULL DEFAULT 0 COMMENT '单条退款积分' AFTER `refund_money`,
  ADD COLUMN `fail_reason` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因' AFTER `refund_point`,
  ADD KEY `idx_site_status_time` (`site_id`, `query_status`, `create_time`);
