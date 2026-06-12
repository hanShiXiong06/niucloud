-- hsx_recycle 0.0.3
-- 下游流转回流：在回收设备上镜像 ERP/中台 的生命周期阶段，使回收侧能看全程（已入库/转中台/已定价/已售）。
-- 纯加法列，不改动现有状态机；由回收侧监听器幂等写入，缺失插件时列保持默认值，无副作用。

ALTER TABLE `{{prefix}}recycle_device`
  ADD COLUMN `downstream_stage` tinyint NOT NULL DEFAULT 0 COMMENT '下游流转阶段镜像：0-未流转,10-已入库,20-转中台待拍照,30-已定价可售,40-已售下架' AFTER `last_cost_adjust_time`,
  ADD COLUMN `downstream_stage_at` int NOT NULL DEFAULT 0 COMMENT '下游流转阶段更新时间' AFTER `downstream_stage`,
  ADD COLUMN `downstream_erp_asset_id` int NOT NULL DEFAULT 0 COMMENT '关联ERP资产ID(下游回流)' AFTER `downstream_stage_at`,
  ADD COLUMN `downstream_sale_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '中台销售定价(下游回流)' AFTER `downstream_erp_asset_id`,
  ADD COLUMN `downstream_event_id` varchar(64) NOT NULL DEFAULT '' COMMENT '最近一次应用的下游事件ID(幂等追溯)' AFTER `downstream_sale_price`;
