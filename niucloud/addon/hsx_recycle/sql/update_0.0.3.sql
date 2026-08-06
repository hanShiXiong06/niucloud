-- hsx_recycle 0.0.3
-- 物流车配送：订单保存现场信息，并按预计到达时间形成订单级取货任务。

ALTER TABLE `{{prefix}}recycle_order`
  ADD COLUMN `logistics_name` varchar(100) NOT NULL DEFAULT '' COMMENT '物流车线路或物流名称' AFTER `delivery_data`,
  ADD COLUMN `logistics_vehicle_no` varchar(50) NOT NULL DEFAULT '' COMMENT '物流车车牌号' AFTER `logistics_name`,
  ADD COLUMN `logistics_contact_name` varchar(50) NOT NULL DEFAULT '' COMMENT '物流车联系人' AFTER `logistics_vehicle_no`,
  ADD COLUMN `logistics_contact_mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '物流车联系电话' AFTER `logistics_contact_name`,
  ADD COLUMN `logistics_pickup_address` varchar(255) NOT NULL DEFAULT '' COMMENT '物流车到达后的取货地点' AFTER `logistics_contact_mobile`,
  ADD COLUMN `logistics_eta_at` int NOT NULL DEFAULT 0 COMMENT '预计可取货时间' AFTER `logistics_pickup_address`,
  ADD KEY `idx_delivery_eta` (`site_id`,`delivery_type`,`logistics_eta_at`),
  ADD KEY `idx_logistics_vehicle` (`site_id`,`logistics_vehicle_no`);
