ALTER TABLE `{{prefix}}recycle_quotation_v2_sync_log`
  ADD COLUMN `sync_source` varchar(20) NOT NULL DEFAULT 'manual' COMMENT '同步来源：manual手动同步 auto自动同步 preview手动预览' AFTER `duration`,
  ADD KEY `idx_source` (`site_id`,`sync_source`);
