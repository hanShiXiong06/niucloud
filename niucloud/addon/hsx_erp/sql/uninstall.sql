-- ============================================================
-- hsx_erp 卸载脚本:删除本插件建的全部表 + 回收对共享表 member_level 追加的 level_no 列/索引。
-- 配合 install.sql:卸载→安装 = 干净重建(新列会跟着建出来,也不会再撞 Duplicate column 'level_no')。
-- 注意:开发环境会清空 ERP/财务全部数据,生产慎用。
-- ============================================================

DROP TABLE IF EXISTS `{{prefix}}erp_asset`;
DROP TABLE IF EXISTS `{{prefix}}erp_asset_cycle`;
DROP TABLE IF EXISTS `{{prefix}}erp_asset_move_log`;
DROP TABLE IF EXISTS `{{prefix}}erp_capital_account`;
DROP TABLE IF EXISTS `{{prefix}}erp_capital_ledger`;
DROP TABLE IF EXISTS `{{prefix}}erp_cost_ledger`;
DROP TABLE IF EXISTS `{{prefix}}erp_counterparty`;
DROP TABLE IF EXISTS `{{prefix}}erp_counterparty_member`;
DROP TABLE IF EXISTS `{{prefix}}erp_device_identity`;
DROP TABLE IF EXISTS `{{prefix}}erp_finance_payable`;
DROP TABLE IF EXISTS `{{prefix}}erp_finance_receivable`;
DROP TABLE IF EXISTS `{{prefix}}erp_finance_settlement`;
DROP TABLE IF EXISTS `{{prefix}}erp_finance_settlement_link`;
DROP TABLE IF EXISTS `{{prefix}}erp_inbox_event`;
DROP TABLE IF EXISTS `{{prefix}}erp_location_assign`;
DROP TABLE IF EXISTS `{{prefix}}erp_operation_event`;
DROP TABLE IF EXISTS `{{prefix}}erp_outbound_item`;
DROP TABLE IF EXISTS `{{prefix}}erp_outbound_order`;
DROP TABLE IF EXISTS `{{prefix}}erp_outbox_event`;
DROP TABLE IF EXISTS `{{prefix}}erp_price_log`;
DROP TABLE IF EXISTS `{{prefix}}erp_refurbish_item`;
DROP TABLE IF EXISTS `{{prefix}}erp_refurbish_order`;
DROP TABLE IF EXISTS `{{prefix}}erp_stock_ledger`;
DROP TABLE IF EXISTS `{{prefix}}erp_stock_order`;
DROP TABLE IF EXISTS `{{prefix}}erp_stock_order_item`;
DROP TABLE IF EXISTS `{{prefix}}erp_stocktake_item`;
DROP TABLE IF EXISTS `{{prefix}}erp_stocktake_order`;
DROP TABLE IF EXISTS `{{prefix}}erp_sync_batch`;
DROP TABLE IF EXISTS `{{prefix}}erp_sync_target`;
DROP TABLE IF EXISTS `{{prefix}}erp_warehouse`;
DROP TABLE IF EXISTS `{{prefix}}erp_warehouse_location`;

-- 注意:不删共享表 member_level 的 level_no 列(里面有真实数据)。该列由
-- MemberLevelNoService::ensureColumn() 运行时按需维护,重装时"已存在则跳过",不会冲突。
