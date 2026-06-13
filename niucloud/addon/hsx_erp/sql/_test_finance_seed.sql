-- 财务中心折账测试数据(测试用 验完可删)
-- 前缀默认 ns_; 若你的 .env DB_PREFIX 不同 全局替换 ns_ 为你的前缀
-- site_id 默认 1; 多站点请改成你登录的站点ID
-- 场景 同一往来单位"测试客户A"(id=9001) 我应付他4000(回收) 他应收我5000(销售)
-- 预期 可折账4000 净额1000(我收) 结算方式=混合

INSERT INTO `ns_erp_finance_payable`
(site_id, counterparty_id, counterparty_name, amount, settled_amount, status, source_type, source_no, source_device_id, event_id, occurred_at, remark, create_time, update_time)
VALUES
(1, 9001, '测试客户A', 4000.00, 0.00, 'pending', 'recycle_device', 'TEST-R001', 0, 'test_payable_001', UNIX_TIMESTAMP(), '测试应付', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `ns_erp_finance_receivable`
(site_id, counterparty_id, counterparty_name, amount, settled_amount, status, source_type, source_no, source_device_id, event_id, occurred_at, remark, create_time, update_time)
VALUES
(1, 9001, '测试客户A', 5000.00, 0.00, 'pending', 'sale_order', 'TEST-S001', 0, 'test_receivable_001', UNIX_TIMESTAMP(), '测试应收', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 清理(验完执行):
-- DELETE FROM `ns_erp_finance_payable` WHERE event_id='test_payable_001';
-- DELETE FROM `ns_erp_finance_receivable` WHERE event_id='test_receivable_001';
-- DELETE FROM `ns_erp_finance_settlement` WHERE counterparty_id=9001;
-- DELETE FROM `ns_erp_finance_settlement_link` WHERE settlement_id IN (SELECT id FROM `ns_erp_finance_settlement` WHERE counterparty_id=9001);
