-- 接单员设置：可接单任务类型（与 runner/set_range、settings 页一致）
ALTER TABLE `xiaoyuan_runner` ADD COLUMN `accept_types` varchar(500) NULL DEFAULT '' COMMENT '接单服务类型(JSON数组)' AFTER `today_orders`;
