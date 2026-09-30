ALTER TABLE `phone_shop_goods_grade`
    ADD COLUMN `grade_desc` VARCHAR(500) NOT NULL DEFAULT ''
    COMMENT '成色等级描述' AFTER `grade_name`;

ALTER TABLE `phone_shop_goods_grade`
    ADD COLUMN `grade_image` VARCHAR(1000) NOT NULL DEFAULT ''
    COMMENT '成色等级图片' AFTER `grade_desc`;
