ALTER TABLE `{{prefix}}wj_books_config`
ADD COLUMN `price_adjust_rate` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT '回收价调整比例，单位%' AFTER `rejected_book_retrieve_days`;
