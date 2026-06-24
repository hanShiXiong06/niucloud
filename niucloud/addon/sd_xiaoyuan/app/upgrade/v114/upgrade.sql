-- 接单员等级：跑腿(ERRAND) 单独佣金比例（与订单 task_type 一致）
ALTER TABLE `xiaoyuan_runner_level` ADD COLUMN `rate_errand` tinyint(3) NULL DEFAULT NULL COMMENT '跑腿佣金比例(%)' AFTER `rate_buy`;
