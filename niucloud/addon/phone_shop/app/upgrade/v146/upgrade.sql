
ALTER TABLE `shop_discount` CHANGE COLUMN `remark` `remark` TEXT DEFAULT NULL COMMENT '活动说明';

ALTER TABLE `shop_discount` MODIFY `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '添加时间' AFTER `success_num`;

ALTER TABLE `shop_discount` MODIFY `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间' AFTER `create_time`;

ALTER TABLE `shop_delivery_local_delivery` COMMENT = '自提点表';

ALTER TABLE `shop_delivery_company` COMMENT = '站点快递表';
