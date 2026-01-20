ALTER TABLE tkjhkd_order_delivery
    ADD COLUMN price_rule LONGTEXT,
     ADD COLUMN original_rule LONGTEXT;
ALTER TABLE tkjhkd_order_add
    ADD COLUMN notice_num int(11) NULL DEFAULT 0,
     ADD COLUMN remark VARCHAR(255);
