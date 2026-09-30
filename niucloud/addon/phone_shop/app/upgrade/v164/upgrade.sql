ALTER TABLE `phone_shop_goods`
    ADD COLUMN `device_color` VARCHAR(50) NOT NULL DEFAULT ''
    COMMENT '设备颜色(结构化筛选事实)' AFTER `memory_group`,
    ADD COLUMN `battery_health` SMALLINT(4) NOT NULL DEFAULT -1
    COMMENT '电池健康度0-100，-1未知' AFTER `device_color`,
    ADD COLUMN `warranty_expire_time` INT(11) NOT NULL DEFAULT 0
    COMMENT '保修到期时间(当天23:59:59时间戳，0未知)' AFTER `battery_health`,
    ADD KEY `idx_goods_site_color` (`site_id`, `device_color`),
    ADD KEY `idx_goods_site_battery` (`site_id`, `battery_health`),
    ADD KEY `idx_goods_site_warranty` (`site_id`, `warranty_expire_time`);

ALTER TABLE `phone_shop_device_intake`
    ADD COLUMN `battery_health` SMALLINT(4) NOT NULL DEFAULT -1
    COMMENT '电池健康度0-100，-1未知' AFTER `color`,
    ADD COLUMN `warranty_expire_time` INT(11) NOT NULL DEFAULT 0
    COMMENT '保修到期时间(当天23:59:59时间戳，0未知)' AFTER `battery_health`;
