-- 商城插件（shop） 更新sql
ALTER TABLE `shop_delivery_company` CHANGE COLUMN `express_no` `express_no` VARCHAR (255) NOT NULL DEFAULT '' COMMENT '快递鸟:物流公司编号(用于物流跟踪)';

ALTER TABLE `shop_delivery_company` CHANGE COLUMN `express_no_electronic_sheet` `express_no_electronic_sheet` VARCHAR (255) NOT NULL DEFAULT '' COMMENT '快递鸟:物流公司编号(用于电子面单)';
