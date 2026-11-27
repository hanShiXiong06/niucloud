ALTER TABLE `shop_goods`
    ADD COLUMN goods_image_width INT NOT NULL DEFAULT 800 COMMENT '商品详情首图宽' AFTER `goods_image`;
ALTER TABLE `shop_goods`
    ADD COLUMN goods_image_height INT NOT NULL DEFAULT 800 COMMENT '商品详情首图高' AFTER `goods_image_width`;