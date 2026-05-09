-- 订单隐私信息：发布人填写，仅接单员接单后可见（API 对非授权方会清空该字段）
ALTER TABLE `xiaoyuan_order` ADD COLUMN `yinsi_text` text NULL COMMENT '隐私信息(接单后对接单员可见)' AFTER `remark`;
