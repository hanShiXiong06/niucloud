-- =============================================
-- 线下销售功能 - 数据库升级脚本
-- 阶段1: 基础线下销售功能
-- =============================================

-- 1. 扩展商品SKU表 - 添加锁定库存字段
ALTER TABLE `phone_shop_goods_sku`
ADD COLUMN `locked_stock` int(11) NOT NULL DEFAULT 0 COMMENT '已锁定库存(未付款/挂单订单)' AFTER `stock`;

-- 2. 扩展订单表 - 添加支付方式字段
ALTER TABLE `phone_shop_order`
ADD COLUMN `pay_type` varchar(50) DEFAULT NULL COMMENT '支付方式(wechatpay/alipay/hsx_offlinepay等)' AFTER `pay_time`;

-- 3. 扩展订单表 - 添加线下收款账户字段
ALTER TABLE `phone_shop_order`
ADD COLUMN `offline_pay_account` varchar(100) DEFAULT NULL COMMENT '线下收款账户名称(如:微信1、支付宝1)' AFTER `pay_type`;

-- 注意: 执行此脚本前请备份数据库

-- 添加 is_deleted 字段到订单商品表
-- 用于标识在挂单确认收款时，客户返还的商品
ALTER TABLE `phone_shop_order_goods`
ADD COLUMN `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已删除/返还：0-正常，1-已删除' AFTER `is_enable_refund`;

-- 为 is_deleted 字段添加索引以提高查询性能
ALTER TABLE `phone_shop_order_goods`
ADD INDEX `idx_is_deleted` (`is_deleted`);
