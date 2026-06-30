<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

/**
 * 推荐角色预设（店长 / 库管 / 销售 / 验机收货 / 财务出纳）。
 *
 * 仅作为「一键生成角色」的起始模板，生成后管理员可在后台「角色管理」自由增删权限点。
 * keys = 该角色拥有的权限点(菜单/按钮 menu_key)，跨 hsx_erp / hsx_recycle 通用。
 * all_erp=true 表示该角色拥有 hsx_erp 的全部权限点(运行时从菜单字典收集)。
 */
class RolePresetDict
{
    public static function all(): array
    {
        return [
            [
                'role_name' => '店长',
                'desc'      => '全部权限：经营、财务、出入库、调成本、角色配置等',
                'all_erp'   => true,
                'keys'      => self::recycleManage(),
            ],
            [
                'role_name' => '库管',
                'desc'      => '入库确认、整备、盘点、调拨、仓库/库位查看；不含财务与定价',
                'all_erp'   => false,
                'keys'      => [
                    'hsx_erp_manage', 'hsx_erp_dashboard',
                    'hsx_erp_asset_list', 'hsx_erp_asset_overview', 'hsx_erp_asset_info',
                    'hsx_erp_asset_manual_inbound', 'hsx_erp_confirm_inbound', 'hsx_erp_asset_confirm_inbound', 'hsx_erp_asset_batch_confirm_inbound',
                    'hsx_erp_stock_order_list', 'hsx_erp_stock_order_info', 'hsx_erp_stock_order_confirm_items', 'hsx_erp_stock_order_reject_items', 'hsx_erp_stock_order_resubmit_item',
                    'hsx_erp_refurbishment_list', 'hsx_erp_refurbishment_info', 'hsx_erp_refurbishment_create', 'hsx_erp_refurbishment_complete', 'hsx_erp_refurbishment_cancel', 'hsx_erp_refurbishment_skip', 'hsx_erp_refurbishment_user_options',
                    'hsx_erp_stocktake_list', 'hsx_erp_stocktake_info', 'hsx_erp_stocktake_create', 'hsx_erp_stocktake_scan', 'hsx_erp_stocktake_finish',
                    'hsx_erp_warehouse_list', 'hsx_erp_warehouse_options',
                    'hsx_erp_location_assign_list', 'hsx_erp_location_assign_tree', 'hsx_erp_location_assign_staff_options',
                    'hsx_erp_outbound_list', 'hsx_erp_outbound_info', 'hsx_erp_outbound_transfer',
                    'hsx_erp_device_trace', 'hsx_erp_device_trace_detail',
                ],
            ],
            [
                'role_name' => '销售',
                'desc'      => '定价、出库销售、回填价、往来单位；不含调成本/财务结算',
                'all_erp'   => false,
                'keys'      => [
                    'hsx_erp_manage', 'hsx_erp_dashboard',
                    'hsx_erp_asset_list', 'hsx_erp_asset_overview', 'hsx_erp_asset_info',
                    'hsx_erp_pricing_list', 'hsx_erp_pricing_info', 'hsx_erp_pricing_save',
                    'hsx_erp_outbound_list', 'hsx_erp_outbound_info', 'hsx_erp_outbound_create', 'hsx_erp_outbound_fill_price', 'hsx_erp_peer_sale_todo',
                    'hsx_erp_counterparty_list', 'hsx_erp_counterparty_options', 'hsx_erp_counterparty_save',
                    'hsx_erp_device_trace', 'hsx_erp_device_trace_detail',
                ],
            ],
            [
                'role_name' => '验机收货',
                'desc'      => '回收下单/验机/确认价、设备建档；不含财务',
                'all_erp'   => false,
                'keys'      => [
                    'hsx_erp_manage', 'hsx_erp_dashboard',
                    'hsx_erp_asset_list', 'hsx_erp_asset_info', 'hsx_erp_asset_manual_inbound',
                    'hsx_erp_device_trace', 'hsx_erp_device_trace_detail',
                    // 回收侧：代下单、签收(edit/order_sign)、验机、确认价、机型字典、打印标签
                    'recycle_order_list', 'recycle_order_add', 'recycle_order_edit', 'recycle_order_detail', 'recycle_order_status', 'recycle_order_business_stage_options',
                    'recycle_my_task',
                    'recycle_device_check', 'recycle_device_info', 'recycle_device_confirm_price', 'recycle_device_re_confirm_price', 'recycle_device_update', 'recycle_device_batch_recycle',
                    'recycle_device_model_dict_list', 'recycle_device_model_dict_options', 'recycle_device_model_dict_tree',
                    'recycle_printer_device_label', 'recycle_printer_device_label_plan',
                ],
            ],
            [
                'role_name' => '财务出纳',
                'desc'      => '对账、折账/付款/收款、户头、调成本、回收打款、AI 财务分析；不含出入库操作',
                'all_erp'   => false,
                'keys'      => [
                    'hsx_erp_manage', 'hsx_erp_dashboard',
                    'hsx_erp_finance_board', 'hsx_erp_finance_reconciliation', 'hsx_erp_finance_payable_lists', 'hsx_erp_finance_receivable_lists', 'hsx_erp_finance_payable_outstanding', 'hsx_erp_finance_receivable_outstanding', 'hsx_erp_finance_settlement_preview', 'hsx_erp_finance_settlement_settle',
                    'hsx_erp_capital_account_list', 'hsx_erp_capital_account_save', 'hsx_erp_capital_account_entry', 'hsx_erp_capital_account_ledger',
                    'hsx_erp_asset_list', 'hsx_erp_asset_info', 'hsx_erp_asset_adjust_cost',
                    'hsx_erp_outbound_list', 'hsx_erp_outbound_info', 'hsx_erp_outbound_cancel', 'hsx_erp_outbound_fill_price', 'hsx_erp_peer_sale_todo',
                    'hsx_erp_counterparty_list', 'hsx_erp_counterparty_options', 'hsx_erp_counterparty_save',
                    'hsx_erp_device_trace', 'hsx_erp_device_trace_detail',
                    'hsx_erp_ai', 'hsx_erp_ai_chat', 'hsx_erp_ai_chat_send', 'hsx_erp_ai_scenes', 'hsx_erp_ai_run', 'hsx_erp_ai_stream', 'hsx_erp_ai_finance', 'hsx_erp_ai_report', 'hsx_erp_ai_summary', 'hsx_erp_ai_conv_list', 'hsx_erp_ai_conv_detail', 'hsx_erp_ai_conv_save', 'hsx_erp_ai_conv_delete',
                    // 回收侧：打款确认
                    'recycle_my_task', 'recycle_order_payment_confirm', 'recycle_order_merchant_pay_info',
                ],
            ],
        ];
    }

    /** 店长附带的回收侧管理权限点 */
    private static function recycleManage(): array
    {
        return [
            'recycle_my_task',
            'recycle_order_list', 'recycle_order_add', 'recycle_order_detail', 'recycle_order_edit', 'recycle_order_delete', 'recycle_order_status', 'recycle_order_payment_confirm', 'recycle_order_merchant_pay_info',
            'recycle_device_check',
            'recycle_device_info', 'recycle_device_confirm_price', 'recycle_device_re_confirm_price', 'recycle_device_cost_adjust', 'recycle_device_cost_adjust_ability', 'recycle_device_cost_adjust_logs', 'recycle_device_batch_recycle', 'recycle_device_batch_return', 'recycle_device_update',
            'recycle_return_order_list', 'recycle_return_order_create', 'recycle_return_order_detail', 'recycle_return_order_status', 'recycle_return_order_update_status', 'recycle_return_order_cancel',
        ];
    }
}
