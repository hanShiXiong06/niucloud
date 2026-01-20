
ALTER TABLE tkjhkd_order
    ADD COLUMN sid VARCHAR(600) COMMENT '跟单参数',
    ADD COLUMN pub_id VARCHAR(255) COMMENT '推广api信息';
