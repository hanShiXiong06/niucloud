<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use think\facade\Db;

/**
 * ERP 数据库结构迁移入口。
 *
 * 只允许由插件 install/upgrade 生命周期调用，业务请求不得执行 DDL。
 */
final class ErpSchema
{
    public static function migrate(): void
    {
        $prefix = (string)config('database.connections.mysql.prefix');

        Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}erp_kpi_rule` (
            `id` int unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
            `metric_key` varchar(40) NOT NULL DEFAULT '', `metric_name` varchar(60) NOT NULL DEFAULT '',
            `unit` varchar(20) NOT NULL DEFAULT '', `target_value` decimal(14,2) NOT NULL DEFAULT 0.00,
            `weight` decimal(6,2) NOT NULL DEFAULT 0.00, `enabled` tinyint(1) NOT NULL DEFAULT 1,
            `sort` int NOT NULL DEFAULT 0, `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_site_metric` (`site_id`,`metric_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-员工KPI规则'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}erp_catalog_product_master` (
            `master_product_id` int unsigned NOT NULL AUTO_INCREMENT,
            `created_site_id` int NOT NULL DEFAULT 0, `source_key` varchar(40) NOT NULL DEFAULT '', `source_product_id` varchar(80) NOT NULL DEFAULT '',
            `category_source_id` varchar(80) NOT NULL DEFAULT '', `category_path` varchar(255) NOT NULL DEFAULT '',
            `brand_source_id` varchar(80) NOT NULL DEFAULT '', `brand_name` varchar(100) NOT NULL DEFAULT '',
            `series_name` varchar(100) NOT NULL DEFAULT '', `product_name` varchar(150) NOT NULL DEFAULT '',
            `data_hash` char(64) NOT NULL DEFAULT '', `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`master_product_id`), UNIQUE KEY `uk_source_product` (`source_key`,`source_product_id`),
            KEY `idx_product_name` (`product_name`), KEY `idx_brand_series` (`brand_name`,`series_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-平台标准产品模板'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}erp_site_catalog_product` (
            `site_product_id` int unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
            `master_product_id` int NOT NULL DEFAULT 0,
            `category_path` varchar(255) NOT NULL DEFAULT '', `product_name` varchar(150) NOT NULL DEFAULT '',
            `brand_name` varchar(100) NOT NULL DEFAULT '', `series_name` varchar(100) NOT NULL DEFAULT '',
            `is_enabled` tinyint(1) NOT NULL DEFAULT 1, `sort` int NOT NULL DEFAULT 0,
            `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`site_product_id`), UNIQUE KEY `uk_site_master` (`site_id`,`master_product_id`),
            KEY `idx_site_category_path` (`site_id`,`category_path`(100),`is_enabled`),
            KEY `idx_site_brand_series` (`site_id`,`brand_name`,`series_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-站点产品目录绑定'");

        Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}erp_catalog_import_task` (
            `id` int unsigned NOT NULL AUTO_INCREMENT, `site_id` int NOT NULL DEFAULT 0,
            `operator_uid` int NOT NULL DEFAULT 0, `operator_name` varchar(60) NOT NULL DEFAULT '',
            `source_key` varchar(40) NOT NULL DEFAULT 'excel_product_catalog',
            `file_name` varchar(255) NOT NULL DEFAULT '', `file_path` varchar(500) NOT NULL DEFAULT '',
            `sheet_name` varchar(120) NOT NULL DEFAULT '', `status` varchar(20) NOT NULL DEFAULT 'pending',
            `queue_enabled` tinyint(1) NOT NULL DEFAULT 0, `total_rows` int NOT NULL DEFAULT 0,
            `processed_rows` int NOT NULL DEFAULT 0, `created_count` int NOT NULL DEFAULT 0,
            `updated_count` int NOT NULL DEFAULT 0, `skipped_count` int NOT NULL DEFAULT 0,
            `error_count` int NOT NULL DEFAULT 0, `result_json` longtext NULL,
            `message` varchar(500) NOT NULL DEFAULT '', `error_message` varchar(1000) NOT NULL DEFAULT '',
            `start_at` int NOT NULL DEFAULT 0, `finish_at` int NOT NULL DEFAULT 0,
            `create_at` int NOT NULL DEFAULT 0, `update_at` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), KEY `idx_site_status` (`site_id`,`status`), KEY `idx_site_create` (`site_id`,`create_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ERP-商品目录异步导入任务'");

        $columns = [
            'erp_catalog_product_master' => [
                'created_site_id' => "`created_site_id` int NOT NULL DEFAULT 0 COMMENT '首次写入站点，仅用于审计' AFTER `master_product_id`",
            ],
            'erp_party' => [
                'role_flags' => "`role_flags` varchar(255) NOT NULL DEFAULT '' COMMENT '多身份：purchase_supplier,sale_customer,recycle_customer,refurbish_provider' AFTER `party_type`",
                'group_keys' => "`group_keys` varchar(255) NOT NULL DEFAULT '' COMMENT '业务分组，逗号分隔' AFTER `role_flags`",
            ],
            'erp_warehouse' => [
                'manager_uid' => "`manager_uid` int NOT NULL DEFAULT 0 COMMENT '仓库负责人UID' AFTER `warehouse_code`",
                'manager_name' => "`manager_name` varchar(60) NOT NULL DEFAULT '' COMMENT '仓库负责人名称快照' AFTER `manager_uid`",
                'warehouse_type' => "`warehouse_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned二手机/peer同行/consignment代卖/exception异常' AFTER `warehouse_code`",
                'ownership_type' => "`ownership_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned自有/consigned代卖/pending待定' AFTER `warehouse_type`",
                'need_photo' => "`need_photo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要拍照' AFTER `ownership_type`",
                'need_pricing' => "`need_pricing` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要定价' AFTER `need_photo`",
                'allow_direct_sale' => "`allow_direct_sale` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许直接销售' AFTER `need_pricing`",
                'allow_transfer' => "`allow_transfer` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许调拨' AFTER `allow_direct_sale`",
                'default_sale_target' => "`default_sale_target` varchar(20) NOT NULL DEFAULT 'unset' COMMENT 'unset未定/peer同行/mall商城' AFTER `allow_transfer`",
            ],
            'erp_warehouse_location' => [
                'manager_uid' => "`manager_uid` int NOT NULL DEFAULT 0 COMMENT '库位负责人UID，0表示继承仓库负责人' AFTER `location_code`",
                'manager_name' => "`manager_name` varchar(60) NOT NULL DEFAULT '' COMMENT '库位负责人名称快照' AFTER `manager_uid`",
            ],
            'erp_purchase_order' => [
                'request_id' => "`request_id` varchar(80) DEFAULT NULL COMMENT '客户端幂等请求ID' AFTER `site_id`",
                'capital_account_id' => "`capital_account_id` int NOT NULL DEFAULT 0 COMMENT '本次付款账户' AFTER `settle_method`",
                'capital_account_name' => "`capital_account_name` varchar(100) NOT NULL DEFAULT '' COMMENT '本次付款账户名称' AFTER `capital_account_id`",
                'location_id' => "`location_id` int NOT NULL DEFAULT 0 AFTER `warehouse_name`",
                'location_name' => "`location_name` varchar(100) NOT NULL DEFAULT '' AFTER `location_id`",
                'origin_plugin' => "`origin_plugin` varchar(40) NOT NULL DEFAULT 'hsx_erp' COMMENT '真实业务来源插件' AFTER `source_id`",
                'origin_plugin_name' => "`origin_plugin_name` varchar(60) NOT NULL DEFAULT '二手机ERP' COMMENT '来源插件名称快照' AFTER `origin_plugin`",
                'origin_type' => "`origin_type` varchar(80) NOT NULL DEFAULT 'hsx_erp.manual_purchase' COMMENT '业务来源稳定编码' AFTER `origin_plugin_name`",
                'origin_name' => "`origin_name` varchar(80) NOT NULL DEFAULT 'ERP采购' COMMENT '业务来源名称快照' AFTER `origin_type`",
                'origin_id' => "`origin_id` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务ID' AFTER `origin_name`",
                'origin_no' => "`origin_no` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务单号' AFTER `origin_id`",
                'origin_event_id' => "`origin_event_id` varchar(80) NOT NULL DEFAULT '' COMMENT '外部事件幂等键' AFTER `origin_no`",
            ],
            'erp_purchase_item' => [
                'warehouse_id' => "`warehouse_id` int NOT NULL DEFAULT 0 COMMENT '明细入库仓库ID' AFTER `asset_id`",
                'warehouse_name' => "`warehouse_name` varchar(100) NOT NULL DEFAULT '' COMMENT '明细入库仓库名称快照' AFTER `warehouse_id`",
                'location_id' => "`location_id` int NOT NULL DEFAULT 0 COMMENT '明细入库库位ID' AFTER `warehouse_name`",
                'location_name' => "`location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '明细入库库位名称快照' AFTER `location_id`",
                'spec_json' => "`spec_json` longtext COMMENT '结构化规格JSON' AFTER `spec`",
                'color' => "`color` varchar(50) NOT NULL DEFAULT '' COMMENT '颜色' AFTER `spec_json`",
                'battery' => "`battery` tinyint NOT NULL DEFAULT 0 COMMENT '电池效率百分比' AFTER `color`",
                'warranty' => "`warranty` int NOT NULL DEFAULT 0 COMMENT '保修截止时间' AFTER `battery`",
                'catalog_product_id' => "`catalog_product_id` int NOT NULL DEFAULT 0 COMMENT '本站ERP目录产品ID' AFTER `spec`",
                'category_name' => "`category_name` varchar(100) NOT NULL DEFAULT '' COMMENT '末级品类名称快照' AFTER `catalog_product_id`",
                'category_path' => "`category_path` varchar(255) NOT NULL DEFAULT '' COMMENT '商品分类路径' AFTER `category_name`",
                'inspector_uid' => "`inspector_uid` int NOT NULL DEFAULT 0 COMMENT '质检员UID' AFTER `spec`",
                'inspector_name' => "`inspector_name` varchar(60) NOT NULL DEFAULT '' COMMENT '质检员名称快照' AFTER `inspector_uid`",
                'estimate_sale_price' => "`estimate_sale_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '入库预估售价' AFTER `inspector_name`",
                'retail_price' => "`retail_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '零售价' AFTER `estimate_sale_price`",
                'image_urls' => "`image_urls` text COMMENT '入库图片JSON/逗号分隔' AFTER `retail_price`",
                'quality_remark' => "`quality_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '质检/外观备注' AFTER `image_urls`",
            ],
            'erp_asset' => [
                'ownership_type' => "`ownership_type` varchar(20) NOT NULL DEFAULT 'owned' COMMENT 'owned自有/consigned代卖/pending待确认' AFTER `party_name`",
                'owner_party_id' => "`owner_party_id` int NOT NULL DEFAULT 0 COMMENT '当前物权主体，0表示本公司' AFTER `ownership_type`",
                'owner_party_name' => "`owner_party_name` varchar(100) NOT NULL DEFAULT '' COMMENT '当前物权主体名称快照' AFTER `owner_party_id`",
                'ownership_source_type' => "`ownership_source_type` varchar(40) NOT NULL DEFAULT '' COMMENT '最近一次物权取得业务类型' AFTER `owner_party_name`",
                'ownership_source_id' => "`ownership_source_id` int NOT NULL DEFAULT 0 COMMENT '最近一次物权取得业务ID' AFTER `ownership_source_type`",
                'ownership_source_no' => "`ownership_source_no` varchar(40) NOT NULL DEFAULT '' COMMENT '最近一次物权取得业务单号' AFTER `ownership_source_id`",
                'ownership_changed_at' => "`ownership_changed_at` int NOT NULL DEFAULT 0 COMMENT '最近一次物权变更时间' AFTER `ownership_source_no`",
                'location_id' => "`location_id` int NOT NULL DEFAULT 0 COMMENT '库位ID' AFTER `warehouse_name`",
                'location_name' => "`location_name` varchar(100) NOT NULL DEFAULT '' COMMENT '库位名称快照' AFTER `location_id`",
                'spec_json' => "`spec_json` longtext COMMENT '结构化规格JSON' AFTER `spec`",
                'color' => "`color` varchar(50) NOT NULL DEFAULT '' COMMENT '颜色' AFTER `spec_json`",
                'battery' => "`battery` tinyint NOT NULL DEFAULT 0 COMMENT '电池效率百分比' AFTER `color`",
                'warranty' => "`warranty` int NOT NULL DEFAULT 0 COMMENT '保修截止时间' AFTER `battery`",
                'catalog_product_id' => "`catalog_product_id` int NOT NULL DEFAULT 0 COMMENT '本站ERP目录产品ID' AFTER `spec`",
                'category_name' => "`category_name` varchar(100) NOT NULL DEFAULT '' COMMENT '末级品类名称快照' AFTER `catalog_product_id`",
                'category_path' => "`category_path` varchar(255) NOT NULL DEFAULT '' COMMENT '商品分类路径' AFTER `category_name`",
                'inspector_uid' => "`inspector_uid` int NOT NULL DEFAULT 0 COMMENT '质检员UID' AFTER `spec`",
                'inspector_name' => "`inspector_name` varchar(60) NOT NULL DEFAULT '' COMMENT '质检员名称快照' AFTER `inspector_uid`",
                'estimate_sale_price' => "`estimate_sale_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '入库预估售价' AFTER `inspector_name`",
                'retail_price' => "`retail_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '零售价（上架商城定价，由库管设置）' AFTER `estimate_sale_price`",
                'image_urls' => "`image_urls` text COMMENT '入库图片JSON/逗号分隔' AFTER `retail_price`",
                'quality_remark' => "`quality_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '质检/外观备注' AFTER `image_urls`",
                'refurbish_status' => "`refurbish_status` varchar(20) NOT NULL DEFAULT 'none' COMMENT 'none无需/pending待整备/processing整备中/done已完成/failed整备异常' AFTER `total_cost`",
                'refurbish_pending_at' => "`refurbish_pending_at` int NOT NULL DEFAULT 0 COMMENT '进入待整备时间' AFTER `refurbish_status`",
                'refurbish_batch_no' => "`refurbish_batch_no` varchar(40) NOT NULL DEFAULT '' COMMENT '整备交接批次号' AFTER `refurbish_pending_at`",
                'refurbish_provider_id' => "`refurbish_provider_id` int NOT NULL DEFAULT 0 COMMENT '当前外送整备商主体ID' AFTER `refurbish_batch_no`",
                'refurbish_provider_name' => "`refurbish_provider_name` varchar(100) NOT NULL DEFAULT '' COMMENT '当前外送整备商名称快照' AFTER `refurbish_provider_id`",
                'refurbish_sent_at' => "`refurbish_sent_at` int NOT NULL DEFAULT 0 COMMENT '送修/开始整备时间' AFTER `refurbish_provider_name`",
                'refurbish_sent_uid' => "`refurbish_sent_uid` int NOT NULL DEFAULT 0 COMMENT '送修经手人UID' AFTER `refurbish_sent_at`",
                'refurbish_sent_name' => "`refurbish_sent_name` varchar(60) NOT NULL DEFAULT '' COMMENT '送修经手人名称' AFTER `refurbish_sent_uid`",
                'refurbish_result' => "`refurbish_result` varchar(20) NOT NULL DEFAULT '' COMMENT 'success修复成功/partial部分修复/failed修复失败' AFTER `refurbish_sent_name`",
                'refurbish_completed_at' => "`refurbish_completed_at` int NOT NULL DEFAULT 0 COMMENT '整备完成时间' AFTER `refurbish_result`",
                'refurbish_completed_uid' => "`refurbish_completed_uid` int NOT NULL DEFAULT 0 COMMENT '整备完工登记人UID' AFTER `refurbish_completed_at`",
                'refurbish_completed_name' => "`refurbish_completed_name` varchar(60) NOT NULL DEFAULT '' COMMENT '整备完工登记人名称' AFTER `refurbish_completed_uid`",
                'refurbish_voucher_urls' => "`refurbish_voucher_urls` text COMMENT '整备结果/费用凭证图片' AFTER `refurbish_completed_name`",
                'refurbish_remark' => "`refurbish_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '整备交接及结果说明' AFTER `refurbish_voucher_urls`",
                'sale_target' => "`sale_target` varchar(20) NOT NULL DEFAULT 'unset' COMMENT 'unset未定/peer卖同行/mall上商城' AFTER `refurbish_status`",
                'listing_status' => "`listing_status` varchar(20) NOT NULL DEFAULT 'none' COMMENT 'none无需/need_photo待补图片/need_price待补售价/ready资料完整/listed商城已上架' AFTER `sale_target`",
                'remark_public' => "`remark_public` varchar(500) NOT NULL DEFAULT '' COMMENT '对外说明' AFTER `remark`",
                'remark_internal' => "`remark_internal` varchar(500) NOT NULL DEFAULT '' COMMENT '对内备注' AFTER `remark_public`",
                'stock_in_at' => "`stock_in_at` int NOT NULL DEFAULT 0 COMMENT '实际入库时间戳（计算库龄）' AFTER `remark_internal`",
            ],
            'erp_sale_order' => [
                'request_id' => "`request_id` varchar(80) DEFAULT NULL COMMENT '客户端/外部事件幂等请求ID' AFTER `site_id`",
                'sale_channel_key' => "`sale_channel_key` varchar(80) NOT NULL DEFAULT '' COMMENT '销售渠道稳定编码' AFTER `sale_channel`",
                'channel_source_plugin' => "`channel_source_plugin` varchar(40) NOT NULL DEFAULT '' COMMENT '渠道来源插件' AFTER `sale_channel_key`",
                'channel_source_key' => "`channel_source_key` varchar(80) NOT NULL DEFAULT '' COMMENT '插件内渠道编码' AFTER `channel_source_plugin`",
                'origin_plugin' => "`origin_plugin` varchar(40) NOT NULL DEFAULT 'hsx_erp' COMMENT '真实业务来源插件' AFTER `channel_source_key`",
                'origin_plugin_name' => "`origin_plugin_name` varchar(60) NOT NULL DEFAULT '二手机ERP' COMMENT '来源插件名称快照' AFTER `origin_plugin`",
                'origin_type' => "`origin_type` varchar(80) NOT NULL DEFAULT 'hsx_erp.manual_sale' COMMENT '业务来源稳定编码' AFTER `origin_plugin_name`",
                'origin_name' => "`origin_name` varchar(80) NOT NULL DEFAULT 'ERP销售' COMMENT '业务来源名称快照' AFTER `origin_type`",
                'origin_id' => "`origin_id` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务ID' AFTER `origin_name`",
                'origin_no' => "`origin_no` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务单号' AFTER `origin_id`",
                'origin_event_id' => "`origin_event_id` varchar(80) NOT NULL DEFAULT '' COMMENT '外部事件幂等键' AFTER `origin_no`",
            ],
            'erp_account_ledger' => [
                'balance_after' => "`balance_after` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '记账后余额' AFTER `amount`",
            ],
            'erp_asset_ledger' => [
                'request_id' => "`request_id` varchar(80) DEFAULT NULL COMMENT '客户端幂等请求ID' AFTER `site_id`",
                'ledger_no' => "`ledger_no` varchar(40) NOT NULL DEFAULT '' COMMENT '流水号' AFTER `site_id`",
                'asset_no' => "`asset_no` varchar(40) NOT NULL DEFAULT '' COMMENT '设备资产号快照' AFTER `asset_id`",
                'imei' => "`imei` varchar(80) NOT NULL DEFAULT '' COMMENT 'IMEI快照' AFTER `asset_no`",
                'model' => "`model` varchar(120) NOT NULL DEFAULT '' COMMENT '型号快照' AFTER `imei`",
                'before_warehouse_id' => "`before_warehouse_id` int NOT NULL DEFAULT 0 AFTER `after_status`",
                'before_warehouse_name' => "`before_warehouse_name` varchar(100) NOT NULL DEFAULT '' AFTER `before_warehouse_id`",
                'before_location_id' => "`before_location_id` int NOT NULL DEFAULT 0 AFTER `before_warehouse_name`",
                'before_location_name' => "`before_location_name` varchar(100) NOT NULL DEFAULT '' AFTER `before_location_id`",
                'after_warehouse_id' => "`after_warehouse_id` int NOT NULL DEFAULT 0 AFTER `before_location_name`",
                'after_warehouse_name' => "`after_warehouse_name` varchar(100) NOT NULL DEFAULT '' AFTER `after_warehouse_id`",
                'after_location_id' => "`after_location_id` int NOT NULL DEFAULT 0 AFTER `after_warehouse_name`",
                'after_location_name' => "`after_location_name` varchar(100) NOT NULL DEFAULT '' AFTER `after_location_id`",
                'before_total_cost' => "`before_total_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变化前成本' AFTER `after_location_name`",
                'after_total_cost' => "`after_total_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变化后成本' AFTER `before_total_cost`",
                'cost_delta' => "`cost_delta` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本变化' AFTER `after_total_cost`",
                'party_id' => "`party_id` int NOT NULL DEFAULT 0 AFTER `cost_delta`",
                'party_name' => "`party_name` varchar(100) NOT NULL DEFAULT '' AFTER `party_id`",
                'source_no' => "`source_no` varchar(40) NOT NULL DEFAULT '' AFTER `source_id`",
                'extra_json' => "`extra_json` longtext COMMENT '扩展快照' AFTER `remark`",
                'occurred_at' => "`occurred_at` int NOT NULL DEFAULT 0 AFTER `extra_json`",
            ],
            'erp_settlement' => [
                'request_id' => "`request_id` varchar(80) DEFAULT NULL COMMENT '客户端幂等请求ID' AFTER `site_id`",
                'voucher_urls' => "`voucher_urls` text COMMENT '收付款凭证图片JSON/逗号分隔' AFTER `confirmed_at`",
            ],
            'erp_money_ledger' => [
                'category_key' => "`category_key` varchar(80) NOT NULL DEFAULT '' COMMENT '收支分类稳定编码' AFTER `direction`",
                'category_name' => "`category_name` varchar(60) NOT NULL DEFAULT '' COMMENT '收支分类名称快照' AFTER `category_key`",
                'category_statement_group' => "`category_statement_group` varchar(40) NOT NULL DEFAULT '' COMMENT '经营报表分组' AFTER `category_name`",
                'category_source_plugin' => "`category_source_plugin` varchar(40) NOT NULL DEFAULT '' COMMENT '分类来源插件' AFTER `category_statement_group`",
                'category_source_key' => "`category_source_key` varchar(80) NOT NULL DEFAULT '' COMMENT '插件内分类编码' AFTER `category_source_plugin`",
                'balance_after' => "`balance_after` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT '记账后余额' AFTER `amount`",
                'voucher_urls' => "`voucher_urls` text COMMENT '资金凭证图片JSON/逗号分隔' AFTER `occurred_at`",
            ],
            'erp_payable' => [
                'origin_plugin' => "`origin_plugin` varchar(40) NOT NULL DEFAULT 'hsx_erp' COMMENT '真实业务来源插件' AFTER `source_no`",
                'origin_plugin_name' => "`origin_plugin_name` varchar(60) NOT NULL DEFAULT '二手机ERP' COMMENT '来源插件名称快照' AFTER `origin_plugin`",
                'origin_type' => "`origin_type` varchar(80) NOT NULL DEFAULT '' COMMENT '业务来源稳定编码' AFTER `origin_plugin_name`",
                'origin_name' => "`origin_name` varchar(80) NOT NULL DEFAULT '' COMMENT '业务来源名称快照' AFTER `origin_type`",
                'origin_id' => "`origin_id` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务ID' AFTER `origin_name`",
                'origin_no' => "`origin_no` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务单号' AFTER `origin_id`",
                'biz_scene' => "`biz_scene` varchar(40) NOT NULL DEFAULT '' COMMENT '业务场景' AFTER `origin_no`",
                'category_key' => "`category_key` varchar(80) NOT NULL DEFAULT '' COMMENT '财务分类稳定编码' AFTER `biz_scene`",
                'category_name' => "`category_name` varchar(60) NOT NULL DEFAULT '' COMMENT '财务分类名称快照' AFTER `category_key`",
                'category_statement_group' => "`category_statement_group` varchar(40) NOT NULL DEFAULT '' COMMENT '经营报表分组' AFTER `category_name`",
                'category_source_plugin' => "`category_source_plugin` varchar(40) NOT NULL DEFAULT '' COMMENT '分类提供插件' AFTER `category_statement_group`",
                'category_source_key' => "`category_source_key` varchar(80) NOT NULL DEFAULT '' COMMENT '插件内分类编码' AFTER `category_source_plugin`",
                'channel_code' => "`channel_code` varchar(80) NOT NULL DEFAULT '' COMMENT '业务渠道稳定编码' AFTER `category_source_key`",
                'channel_name' => "`channel_name` varchar(60) NOT NULL DEFAULT '' COMMENT '业务渠道名称快照' AFTER `channel_code`",
                'business_reason' => "`business_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '形成应付的业务原因' AFTER `channel_name`",
                'asset_id' => "`asset_id` int NOT NULL DEFAULT 0 COMMENT '设备级应付关联资产ID' AFTER `business_reason`",
            ],
            'erp_receivable' => [
                'origin_plugin' => "`origin_plugin` varchar(40) NOT NULL DEFAULT 'hsx_erp' COMMENT '真实业务来源插件' AFTER `source_no`",
                'origin_plugin_name' => "`origin_plugin_name` varchar(60) NOT NULL DEFAULT '二手机ERP' COMMENT '来源插件名称快照' AFTER `origin_plugin`",
                'origin_type' => "`origin_type` varchar(80) NOT NULL DEFAULT '' COMMENT '业务来源稳定编码' AFTER `origin_plugin_name`",
                'origin_name' => "`origin_name` varchar(80) NOT NULL DEFAULT '' COMMENT '业务来源名称快照' AFTER `origin_type`",
                'origin_id' => "`origin_id` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务ID' AFTER `origin_name`",
                'origin_no' => "`origin_no` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务单号' AFTER `origin_id`",
                'biz_scene' => "`biz_scene` varchar(40) NOT NULL DEFAULT '' COMMENT '业务场景' AFTER `origin_no`",
                'category_key' => "`category_key` varchar(80) NOT NULL DEFAULT '' COMMENT '财务分类稳定编码' AFTER `biz_scene`",
                'category_name' => "`category_name` varchar(60) NOT NULL DEFAULT '' COMMENT '财务分类名称快照' AFTER `category_key`",
                'category_statement_group' => "`category_statement_group` varchar(40) NOT NULL DEFAULT '' COMMENT '经营报表分组' AFTER `category_name`",
                'category_source_plugin' => "`category_source_plugin` varchar(40) NOT NULL DEFAULT '' COMMENT '分类提供插件' AFTER `category_statement_group`",
                'category_source_key' => "`category_source_key` varchar(80) NOT NULL DEFAULT '' COMMENT '插件内分类编码' AFTER `category_source_plugin`",
                'channel_code' => "`channel_code` varchar(80) NOT NULL DEFAULT '' COMMENT '业务渠道稳定编码' AFTER `category_source_key`",
                'channel_name' => "`channel_name` varchar(60) NOT NULL DEFAULT '' COMMENT '业务渠道名称快照' AFTER `channel_code`",
                'business_reason' => "`business_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '形成应收的业务原因' AFTER `channel_name`",
                'asset_id' => "`asset_id` int NOT NULL DEFAULT 0 COMMENT '设备级应收关联资产ID' AFTER `business_reason`",
            ],
            'erp_settlement_link' => [
                'asset_id' => "`asset_id` int NOT NULL DEFAULT 0 COMMENT '关联设备' AFTER `target_id`",
                'biz_scene' => "`biz_scene` varchar(40) NOT NULL DEFAULT '' COMMENT '业务场景快照' AFTER `asset_id`",
                'category_key' => "`category_key` varchar(80) NOT NULL DEFAULT '' COMMENT '财务分类编码快照' AFTER `biz_scene`",
                'category_name' => "`category_name` varchar(60) NOT NULL DEFAULT '' COMMENT '财务分类名称快照' AFTER `category_key`",
                'category_statement_group' => "`category_statement_group` varchar(40) NOT NULL DEFAULT '' COMMENT '经营报表分组' AFTER `category_name`",
                'category_source_plugin' => "`category_source_plugin` varchar(40) NOT NULL DEFAULT '' COMMENT '分类提供插件' AFTER `category_statement_group`",
                'category_source_key' => "`category_source_key` varchar(80) NOT NULL DEFAULT '' COMMENT '插件内分类编码' AFTER `category_source_plugin`",
                'origin_plugin' => "`origin_plugin` varchar(40) NOT NULL DEFAULT '' COMMENT '真实业务来源插件' AFTER `category_source_key`",
                'origin_type' => "`origin_type` varchar(80) NOT NULL DEFAULT '' COMMENT '业务来源稳定编码' AFTER `origin_plugin`",
                'origin_id' => "`origin_id` varchar(80) NOT NULL DEFAULT '' COMMENT '原系统业务ID' AFTER `origin_type`",
            ],
            'erp_purchase_return' => [
                'request_id' => "`request_id` varchar(80) DEFAULT NULL COMMENT '客户端幂等请求ID' AFTER `site_id`",
            ],
            'erp_purchase_return_item' => [
                'supplier_amount' => "`supplier_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '供应商结算成本快照' AFTER `paid_amount`",
                'unpaid_offset_amount' => "`unpaid_offset_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '冲销未付款金额' AFTER `supplier_amount`",
                'refund_receivable_amount' => "`refund_receivable_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '生成退款应收金额' AFTER `unpaid_offset_amount`",
                'retained_payable_amount' => "`retained_payable_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '退货后仍保留应付金额' AFTER `refund_receivable_amount`",
                'internal_cost' => "`internal_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '整备及内部成本快照' AFTER `retained_payable_amount`",
                'policy_json' => "`policy_json` longtext COMMENT '退货决策快照' AFTER `internal_cost`",
            ],
            'erp_sale_return' => [
                'request_id' => "`request_id` varchar(80) DEFAULT NULL COMMENT '客户端幂等请求ID' AFTER `site_id`",
                'business_type' => "`business_type` varchar(30) NOT NULL DEFAULT 'return_refund' COMMENT 'return_refund退货退款/after_sale_compensation售后补差' AFTER `request_id`",
            ],
        ];

        foreach ($columns as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                self::ensureColumn($prefix . $table, $column, $definition);
            }
        }

        // 早期业务入口曾把缺省幂等键保存成空字符串，导致同站点第二次结算撞唯一索引。
        // 空请求号不代表同一个请求，统一迁移成 NULL 后仍由真实 request_id 保证幂等。
        Db::execute("UPDATE `{$prefix}erp_settlement` SET `request_id` = NULL WHERE `request_id` = ''");

        self::ensureIndex($prefix . 'erp_asset', 'idx_site_sn', 'KEY `idx_site_sn` (`site_id`,`sn`)');
        self::ensureIndex($prefix . 'erp_asset', 'idx_site_ownership', 'KEY `idx_site_ownership` (`site_id`,`ownership_type`,`owner_party_id`,`status`)');
        self::ensureIndex($prefix . 'erp_asset', 'idx_site_catalog_product', 'KEY `idx_site_catalog_product` (`site_id`,`catalog_product_id`,`status`)');
        self::ensureIndex($prefix . 'erp_purchase_item', 'idx_site_catalog_product', 'KEY `idx_site_catalog_product` (`site_id`,`catalog_product_id`)');
        self::ensureIndex($prefix . 'erp_warehouse', 'idx_site_manager', 'KEY `idx_site_manager` (`site_id`,`manager_uid`,`status`)');
        self::ensureIndex($prefix . 'erp_warehouse_location', 'idx_site_manager', 'KEY `idx_site_manager` (`site_id`,`manager_uid`,`status`)');
        self::ensureIndex($prefix . 'erp_asset', 'idx_site_refurbish', 'KEY `idx_site_refurbish` (`site_id`,`refurbish_status`,`refurbish_pending_at`)');
        self::ensureIndex($prefix . 'erp_asset', 'idx_site_refurbish_provider', 'KEY `idx_site_refurbish_provider` (`site_id`,`refurbish_provider_id`,`refurbish_status`)');
        self::ensureIndex($prefix . 'erp_asset_ledger', 'idx_action_source_no', 'KEY `idx_action_source_no` (`site_id`,`action`,`source_no`)');
        self::ensureIndex($prefix . 'erp_purchase_order', 'uk_site_request', 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)');
        self::ensureIndex($prefix . 'erp_sale_order', 'uk_site_request', 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)');
        self::ensureIndex($prefix . 'erp_asset_ledger', 'uk_site_request', 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)');
        self::ensureIndex($prefix . 'erp_settlement', 'uk_site_request', 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)');
        self::ensureIndex($prefix . 'erp_payable', 'idx_asset', 'KEY `idx_asset` (`site_id`,`asset_id`)');
        self::ensureIndex($prefix . 'erp_receivable', 'idx_asset', 'KEY `idx_asset` (`site_id`,`asset_id`)');
        self::ensureIndex($prefix . 'erp_purchase_return', 'uk_site_request', 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)');
        self::ensureIndex($prefix . 'erp_sale_return', 'uk_site_request', 'UNIQUE KEY `uk_site_request` (`site_id`,`request_id`)');
        Db::execute("UPDATE `{$prefix}erp_party` SET role_flags = CASE party_type WHEN 'supplier' THEN 'purchase_supplier' WHEN 'customer' THEN 'sale_customer' WHEN 'channel' THEN 'sale_customer' ELSE 'other' END WHERE role_flags = ''");
        Db::execute("UPDATE `{$prefix}erp_asset` a LEFT JOIN `{$prefix}erp_warehouse` w ON w.site_id = a.site_id AND w.id = a.warehouse_id SET a.ownership_type = 'consigned', a.owner_party_id = a.party_id, a.owner_party_name = a.party_name WHERE w.ownership_type = 'consigned' AND a.ownership_type = 'owned'");
        self::backfillHistoricalFinanceState($prefix);
        self::backfillFinanceSourceSnapshots($prefix);
    }

    /**
     * 修复旧版本账目结构，但只在证据唯一时建立设备关系。
     *
     * 历史整备应付曾经没有 asset_id，不过通常仍保留 RF 来源号、资产号或
     * refurbish 资产流水。下面分三步从强到弱匹配；任何来源号对应多台设备时
     * 都不会更新，留给人工核对，避免把财务事实猜到错误设备上。
     */
    private static function backfillHistoricalFinanceState(string $prefix): void
    {
        // 1. RF/历史来源号在整备资产流水中唯一对应一台有效资产。
        Db::execute("UPDATE `{$prefix}erp_payable` p
            INNER JOIN (
                SELECT l.site_id, l.source_no, MIN(l.asset_id) AS asset_id
                FROM `{$prefix}erp_asset_ledger` l
                INNER JOIN `{$prefix}erp_asset` a ON a.site_id = l.site_id AND a.id = l.asset_id
                WHERE l.action = 'refurbish' AND l.asset_id > 0 AND l.source_no <> ''
                GROUP BY l.site_id, l.source_no
                HAVING COUNT(DISTINCT l.asset_id) = 1
            ) resolved ON resolved.site_id = p.site_id AND resolved.source_no = p.source_no
            SET p.asset_id = resolved.asset_id
            WHERE p.source_type = 'refurbish' AND p.asset_id = 0 AND p.source_no <> ''");

        // 2. 来源 ID 指向整备资产流水时，仍要求来源号与流水号或资产号吻合。
        Db::execute("UPDATE `{$prefix}erp_payable` p
            INNER JOIN `{$prefix}erp_asset_ledger` l
                ON l.site_id = p.site_id AND l.id = p.source_id AND l.action = 'refurbish' AND l.asset_id > 0
            INNER JOIN `{$prefix}erp_asset` a ON a.site_id = l.site_id AND a.id = l.asset_id
            SET p.asset_id = l.asset_id
            WHERE p.source_type = 'refurbish' AND p.asset_id = 0 AND p.source_no <> ''
              AND (p.source_no = l.source_no OR p.source_no = a.asset_no)
              AND (p.party_id = 0 OR l.party_id = 0 OR p.party_id = l.party_id)
              AND (l.cost_delta = 0 OR ABS(p.amount - ABS(l.cost_delta)) < 0.01)");

        // 3. 部分早期数据直接把设备资产号保存为来源号；资产号有站点唯一约束。
        Db::execute("UPDATE `{$prefix}erp_payable` p
            INNER JOIN `{$prefix}erp_asset` a ON a.site_id = p.site_id AND a.asset_no = p.source_no
            SET p.asset_id = a.id
            WHERE p.source_type = 'refurbish' AND p.asset_id = 0 AND p.source_no <> ''");

        // 已作废是独立审计状态，不能因为金额为零被改写；其余零余额事实统一结清。
        Db::execute("UPDATE `{$prefix}erp_payable`
            SET status = 'settled'
            WHERE status NOT IN ('settled', 'void') AND amount <= settled_amount");
        Db::execute("UPDATE `{$prefix}erp_receivable`
            SET status = 'settled'
            WHERE status NOT IN ('settled', 'void') AND amount <= settled_amount");
    }

    /** 为升级前历史账目补齐不可变来源与财务分类快照。 */
    private static function backfillFinanceSourceSnapshots(string $prefix): void
    {
        Db::execute("UPDATE `{$prefix}erp_purchase_order` SET
            origin_plugin = IF(source_plugin IN ('', 'erp'), 'hsx_erp', source_plugin),
            origin_plugin_name = CASE WHEN source_plugin = 'hsx_recycle' THEN '手机回收' ELSE '二手机ERP' END,
            origin_type = CASE WHEN source_plugin = 'hsx_recycle' THEN 'hsx_recycle.recycle_purchase' ELSE 'hsx_erp.manual_purchase' END,
            origin_name = CASE WHEN source_plugin = 'hsx_recycle' THEN '回收插件采购' ELSE 'ERP采购' END,
            origin_id = IF(origin_id = '', source_id, origin_id),
            origin_no = IF(origin_no = '', purchase_no, origin_no)
            WHERE origin_no = '' OR origin_type IN ('', 'erp_purchase', 'hsx_recycle_purchase')");

        Db::execute("UPDATE `{$prefix}erp_sale_order` SET
            origin_plugin = IF(origin_plugin = '', 'hsx_erp', origin_plugin),
            origin_plugin_name = IF(origin_plugin_name = '', '二手机ERP', origin_plugin_name),
            origin_type = IF(origin_type IN ('', 'erp_sale', 'phone_shop_sale'), IF(origin_plugin = 'phone_shop', 'phone_shop.mini_program_sale', 'hsx_erp.manual_sale'), origin_type),
            origin_name = IF(origin_name = '', 'ERP销售', origin_name),
            origin_no = IF(origin_no = '', sale_no, origin_no)
            WHERE origin_no = '' OR origin_type IN ('', 'erp_sale', 'phone_shop_sale')");

        Db::execute("UPDATE `{$prefix}erp_payable` p
            LEFT JOIN `{$prefix}erp_asset` a ON a.site_id = p.site_id AND a.id = IF(p.asset_id > 0, p.asset_id, IF(p.source_type = 'purchase_asset', p.source_id, 0))
            LEFT JOIN `{$prefix}erp_purchase_order` o ON o.site_id = p.site_id AND o.id = IF(p.source_type = 'purchase_asset', a.purchase_order_id, p.source_id)
            SET p.origin_plugin = COALESCE(NULLIF(o.origin_plugin, ''), 'hsx_erp'),
                p.origin_plugin_name = COALESCE(NULLIF(o.origin_plugin_name, ''), '二手机ERP'),
                p.origin_type = COALESCE(NULLIF(o.origin_type, ''), 'hsx_erp.manual_purchase'),
                p.origin_name = COALESCE(NULLIF(o.origin_name, ''), 'ERP采购'),
                p.origin_id = COALESCE(NULLIF(o.origin_id, ''), CAST(o.id AS CHAR), ''),
                p.origin_no = COALESCE(NULLIF(o.origin_no, ''), o.purchase_no, ''),
                p.biz_scene = 'purchase',
                p.category_key = 'inventory_purchase',
                p.category_name = '设备采购支出',
                p.category_statement_group = 'purchase',
                p.category_source_plugin = 'hsx_erp',
                p.category_source_key = 'inventory_purchase',
                p.channel_code = IF(p.channel_code = '', o.purchase_channel, p.channel_code),
                p.channel_name = IF(p.channel_name = '', o.purchase_channel, p.channel_name),
                p.business_reason = IF(p.business_reason = '', '采购入库形成设备采购应付，财务按设备核对并付款。', p.business_reason)
            WHERE p.source_type IN ('purchase', 'purchase_asset') AND p.category_key = ''");

        Db::execute("UPDATE `{$prefix}erp_payable` p SET
            p.origin_plugin = IF(p.origin_plugin = '', 'hsx_erp', p.origin_plugin),
            p.origin_plugin_name = IF(p.origin_plugin_name = '', '二手机ERP', p.origin_plugin_name),
            p.origin_type = IF(p.origin_type IN ('', 'erp_refurbish'), 'hsx_erp.manual_refurbish', p.origin_type),
            p.origin_name = IF(p.origin_name = '', 'ERP整备', p.origin_name),
            p.origin_no = IF(p.origin_no = '', p.source_no, p.origin_no),
            p.biz_scene = 'refurbish',
            p.category_key = 'refurbish_labor',
            p.category_name = '整备费用',
            p.category_statement_group = 'operating_expense',
            p.category_source_plugin = 'hsx_erp',
            p.category_source_key = 'refurbish_labor',
            p.channel_code = IF(p.channel_code = '', 'refurbish_service', p.channel_code),
            p.channel_name = IF(p.channel_name = '', '整备服务', p.channel_name),
            p.business_reason = IF(p.business_reason = '', '设备整备或维修产生支出并计入设备成本，财务向服务商付款。', p.business_reason)
            WHERE p.source_type = 'refurbish' AND p.category_key = ''");

        Db::execute("UPDATE `{$prefix}erp_payable` p
            LEFT JOIN `{$prefix}erp_sale_return` sr ON sr.site_id = p.site_id AND sr.id = p.source_id
            LEFT JOIN `{$prefix}erp_sale_order` so ON so.site_id = p.site_id AND so.id = sr.sale_order_id
            SET p.origin_plugin = 'hsx_erp', p.origin_plugin_name = '二手机ERP',
                p.origin_type = 'hsx_erp.manual_sale_return',
                p.origin_name = IF(sr.business_type = 'after_sale_compensation', 'ERP售后补差', 'ERP销售退货'),
                p.origin_id = CAST(sr.id AS CHAR), p.origin_no = sr.return_no,
                p.biz_scene = IF(sr.business_type = 'after_sale_compensation', 'after_sale_compensation', 'sale_return'),
                p.category_key = IF(sr.business_type = 'after_sale_compensation', 'after_sale_compensation', 'sale_refund'),
                p.category_name = IF(sr.business_type = 'after_sale_compensation', '售后补差', '销售退货退款'),
                p.category_statement_group = 'revenue_reversal',
                p.category_source_plugin = 'hsx_erp',
                p.category_source_key = IF(sr.business_type = 'after_sale_compensation', 'after_sale_compensation', 'sale_refund'),
                p.channel_code = IF(p.channel_code = '', so.sale_channel_key, p.channel_code),
                p.channel_name = IF(p.channel_name = '', so.sale_channel, p.channel_name),
                p.business_reason = IF(p.business_reason = '', IF(sr.business_type = 'after_sale_compensation', '售后协商补差形成公司应付。', '客户退回已售设备，已收款部分形成销售退款应付。'), p.business_reason)
            WHERE p.source_type = 'sale_return' AND p.category_key = ''");

        Db::execute("UPDATE `{$prefix}erp_receivable` r
            LEFT JOIN `{$prefix}erp_sale_order` so ON so.site_id = r.site_id AND so.id = r.source_id
            SET r.origin_plugin = COALESCE(NULLIF(so.origin_plugin, ''), 'hsx_erp'),
                r.origin_plugin_name = COALESCE(NULLIF(so.origin_plugin_name, ''), '二手机ERP'),
                r.origin_type = COALESCE(NULLIF(so.origin_type, ''), 'hsx_erp.manual_sale'),
                r.origin_name = COALESCE(NULLIF(so.origin_name, ''), 'ERP销售'),
                r.origin_id = COALESCE(NULLIF(so.origin_id, ''), CAST(so.id AS CHAR), ''),
                r.origin_no = COALESCE(NULLIF(so.origin_no, ''), so.sale_no, ''),
                r.biz_scene = 'sale', r.category_key = 'sale_revenue', r.category_name = '销售收入',
                r.category_statement_group = 'revenue', r.category_source_plugin = 'hsx_erp', r.category_source_key = 'sale_revenue',
                r.channel_code = IF(r.channel_code = '', so.sale_channel_key, r.channel_code),
                r.channel_name = IF(r.channel_name = '', so.sale_channel, r.channel_name),
                r.business_reason = IF(r.business_reason = '', '设备销售出库形成销售应收，财务按实际到账确认收款。', r.business_reason)
            WHERE r.source_type = 'sale' AND r.category_key = ''");

        Db::execute("UPDATE `{$prefix}erp_receivable` r
            LEFT JOIN `{$prefix}erp_purchase_return` pr ON pr.site_id = r.site_id AND pr.id = r.source_id
            LEFT JOIN `{$prefix}erp_purchase_order` po ON po.site_id = r.site_id AND po.id = pr.purchase_order_id
            SET r.origin_plugin = 'hsx_erp', r.origin_plugin_name = '二手机ERP',
                r.origin_type = 'hsx_erp.manual_purchase_return', r.origin_name = 'ERP采购退货',
                r.origin_id = CAST(pr.id AS CHAR), r.origin_no = pr.return_no,
                r.biz_scene = 'purchase_return', r.category_key = 'purchase_refund', r.category_name = '采购退货款收回',
                r.category_statement_group = 'purchase_reversal', r.category_source_plugin = 'hsx_erp', r.category_source_key = 'purchase_refund',
                r.channel_code = IF(r.channel_code = '', po.purchase_channel, r.channel_code),
                r.channel_name = IF(r.channel_name = '', po.purchase_channel, r.channel_name),
                r.business_reason = IF(r.business_reason = '', '采购退货中已付款部分形成退款应收，财务需确认供货商实际退款到账。', r.business_reason)
            WHERE r.source_type = 'purchase_return' AND r.category_key = ''");

        // 将已执行过早期迁移的非命名空间编码统一到 Hook 注册键，确保前端来源筛选可直接命中历史数据。
        Db::execute("UPDATE `{$prefix}erp_payable` SET origin_type = CASE
                WHEN origin_type = 'erp_purchase' THEN 'hsx_erp.manual_purchase'
                WHEN origin_type = 'hsx_recycle_purchase' THEN 'hsx_recycle.recycle_purchase'
                WHEN origin_type = 'erp_refurbish' THEN 'hsx_erp.manual_refurbish'
                WHEN origin_type = 'erp_sale_return' THEN 'hsx_erp.manual_sale_return'
                ELSE origin_type END
            WHERE origin_type IN ('erp_purchase','hsx_recycle_purchase','erp_refurbish','erp_sale_return')");
        Db::execute("UPDATE `{$prefix}erp_receivable` SET origin_type = CASE
                WHEN origin_type = 'erp_sale' THEN 'hsx_erp.manual_sale'
                WHEN origin_type = 'phone_shop_sale' THEN 'phone_shop.mini_program_sale'
                WHEN origin_type = 'erp_purchase_return' THEN 'hsx_erp.manual_purchase_return'
                ELSE origin_type END
            WHERE origin_type IN ('erp_sale','phone_shop_sale','erp_purchase_return')");

        // 历史核销明细也要保留设备、场景、收支分类和来源快照。否则新单可追溯，旧单仍只有“付款/收款”。
        Db::execute("UPDATE `{$prefix}erp_settlement_link` l
            LEFT JOIN `{$prefix}erp_payable` p ON l.target_type = 'payable' AND p.site_id = l.site_id AND p.id = l.target_id
            LEFT JOIN `{$prefix}erp_receivable` r ON l.target_type = 'receivable' AND r.site_id = l.site_id AND r.id = l.target_id
            SET l.asset_id = IF(l.asset_id = 0, COALESCE(p.asset_id, 0), l.asset_id),
                l.biz_scene = COALESCE(NULLIF(l.biz_scene, ''), NULLIF(p.biz_scene, ''), NULLIF(r.biz_scene, ''), ''),
                l.category_key = COALESCE(NULLIF(l.category_key, ''), NULLIF(p.category_key, ''), NULLIF(r.category_key, ''), ''),
                l.category_name = COALESCE(NULLIF(l.category_name, ''), NULLIF(p.category_name, ''), NULLIF(r.category_name, ''), ''),
                l.category_statement_group = COALESCE(NULLIF(l.category_statement_group, ''), NULLIF(p.category_statement_group, ''), NULLIF(r.category_statement_group, ''), ''),
                l.category_source_plugin = COALESCE(NULLIF(l.category_source_plugin, ''), NULLIF(p.category_source_plugin, ''), NULLIF(r.category_source_plugin, ''), ''),
                l.category_source_key = COALESCE(NULLIF(l.category_source_key, ''), NULLIF(p.category_source_key, ''), NULLIF(r.category_source_key, ''), ''),
                l.origin_plugin = COALESCE(NULLIF(l.origin_plugin, ''), NULLIF(p.origin_plugin, ''), NULLIF(r.origin_plugin, ''), ''),
                l.origin_type = COALESCE(NULLIF(l.origin_type, ''), NULLIF(p.origin_type, ''), NULLIF(r.origin_type, ''), ''),
                l.origin_id = COALESCE(NULLIF(l.origin_id, ''), NULLIF(p.origin_id, ''), NULLIF(r.origin_id, ''), '')
            WHERE l.category_key = '' OR l.origin_plugin = '' OR l.biz_scene = ''");
        Db::execute("UPDATE `{$prefix}erp_settlement_link` SET origin_type = CASE
                WHEN origin_type = 'erp_purchase' THEN 'hsx_erp.manual_purchase'
                WHEN origin_type = 'hsx_recycle_purchase' THEN 'hsx_recycle.recycle_purchase'
                WHEN origin_type = 'erp_refurbish' THEN 'hsx_erp.manual_refurbish'
                WHEN origin_type = 'erp_sale_return' THEN 'hsx_erp.manual_sale_return'
                WHEN origin_type = 'erp_sale' THEN 'hsx_erp.manual_sale'
                WHEN origin_type = 'phone_shop_sale' THEN 'phone_shop.mini_program_sale'
                WHEN origin_type = 'erp_purchase_return' THEN 'hsx_erp.manual_purchase_return'
                ELSE origin_type END
            WHERE origin_type IN ('erp_purchase','hsx_recycle_purchase','erp_refurbish','erp_sale_return','erp_sale','phone_shop_sale','erp_purchase_return')");

        // 真实资金流水头保留汇总分类：单一类型直接快照，跨类型批量结算标记 mixed，明细口径以 settlement_link 为准。
        Db::execute("UPDATE `{$prefix}erp_money_ledger` m
            INNER JOIN (
                SELECT site_id, settlement_id,
                    COUNT(DISTINCT category_key) AS category_count,
                    MAX(category_key) AS category_key,
                    MAX(category_name) AS category_name,
                    MAX(category_statement_group) AS category_statement_group,
                    MAX(category_source_plugin) AS category_source_plugin,
                    MAX(category_source_key) AS category_source_key
                FROM `{$prefix}erp_settlement_link`
                WHERE category_key <> ''
                GROUP BY site_id, settlement_id
            ) f ON f.site_id = m.site_id AND f.settlement_id = m.settlement_id
            SET m.category_key = IF(f.category_count = 1, f.category_key, 'mixed'),
                m.category_name = IF(f.category_count = 1, f.category_name, IF(m.direction = 'in', '混合收入', '混合支出')),
                m.category_statement_group = IF(f.category_count = 1, f.category_statement_group, 'mixed'),
                m.category_source_plugin = IF(f.category_count = 1, f.category_source_plugin, 'hsx_erp'),
                m.category_source_key = IF(f.category_count = 1, f.category_source_key, 'mixed')
            WHERE m.category_key = ''");
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        if (!empty(Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"))) {
            return;
        }
        Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }

    private static function ensureIndex(string $table, string $index, string $definition): void
    {
        if (!empty(Db::query("SHOW INDEX FROM `{$table}` WHERE `Key_name` = '{$index}'"))) {
            return;
        }
        Db::execute("ALTER TABLE `{$table}` ADD {$definition}");
    }
}
