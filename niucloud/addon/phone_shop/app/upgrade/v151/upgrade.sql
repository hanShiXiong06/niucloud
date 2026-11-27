
ALTER TABLE `shop_point_exchange` CHANGE COLUMN `total_point_num` `total_point_num` INT NOT NULL DEFAULT 0 COMMENT '积分消费总额';

ALTER TABLE `shop_delivery_company`CHANGE COLUMN `express_no` `express_no` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '快递鸟:物流公司编号(用于物流跟踪)';

ALTER TABLE `shop_delivery_company`CHANGE COLUMN `express_no_electronic_sheet` `express_no_electronic_sheet` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '快递鸟:物流公司编号(用于电子面单)';
