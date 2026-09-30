ALTER TABLE `shop_order`
    ADD COLUMN user_delete_time INT NOT NULL DEFAULT 0 COMMENT '用户删除订单时间' AFTER `relate_source`;