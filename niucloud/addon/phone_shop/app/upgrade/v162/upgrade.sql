-- 商城原生订单成本快照。
-- 历史站点的订单商品表没有这些字段时，下单会被 ORM 严格字段校验拦截。
ALTER TABLE `phone_shop_order_goods`
    ADD COLUMN `cost_price_snapshot` DECIMAL(12, 2) NOT NULL DEFAULT '0.00'
    COMMENT '下单时单件成本快照' AFTER `sku_id`;

ALTER TABLE `phone_shop_order_goods`
    ADD COLUMN `total_cost_snapshot` DECIMAL(12, 2) NOT NULL DEFAULT '0.00'
    COMMENT '下单时总成本快照' AFTER `cost_price_snapshot`;

ALTER TABLE `phone_shop_order_goods`
    ADD COLUMN `supplier_id_snapshot` INT(11) NOT NULL DEFAULT '0'
    COMMENT '下单时供应商ID快照，0为自有/期初商品' AFTER `total_cost_snapshot`;

ALTER TABLE `phone_shop_order_goods`
    ADD COLUMN `inventory_source` VARCHAR(30) NOT NULL DEFAULT 'self_owned'
    COMMENT 'supplier/self_owned/opening/erp_asset' AFTER `supplier_id_snapshot`;

-- 商城线上/线下统一计价快照。
ALTER TABLE `phone_shop_order`
    ADD COLUMN `base_order_money` DECIMAL(10, 2) NOT NULL DEFAULT '0.00'
    COMMENT '未追加支付手续费前的订单金额' AFTER `pay_money`;

ALTER TABLE `phone_shop_order`
    ADD COLUMN `pricing_identity` VARCHAR(20) NOT NULL DEFAULT 'retail'
    COMMENT '计价身份:retail零售/peer同行' AFTER `base_order_money`;

ALTER TABLE `phone_shop_order`
    ADD COLUMN `payment_fee_rate` DECIMAL(8, 6) NOT NULL DEFAULT '0.000000'
    COMMENT '订单创建时的支付手续费率快照' AFTER `pricing_identity`;

ALTER TABLE `phone_shop_order`
    ADD COLUMN `payment_fee_bearer` VARCHAR(20) NOT NULL DEFAULT 'merchant'
    COMMENT '手续费承担方:merchant商家/customer客户' AFTER `payment_fee_rate`;

ALTER TABLE `phone_shop_order`
    ADD COLUMN `payment_fee_amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00'
    COMMENT '预计支付手续费' AFTER `payment_fee_bearer`;

ALTER TABLE `phone_shop_order`
    ADD COLUMN `merchant_net_amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00'
    COMMENT '预计商家净入账' AFTER `payment_fee_amount`;

-- 历史订单产生时尚无手续费机制，按零手续费口径回填原始成交额和商家净额。
UPDATE `phone_shop_order`
SET `base_order_money` = `order_money`
WHERE `base_order_money` = 0
  AND `order_money` > 0;

UPDATE `phone_shop_order`
SET `merchant_net_amount` = `order_money`
WHERE `merchant_net_amount` = 0
  AND `order_money` > 0;
