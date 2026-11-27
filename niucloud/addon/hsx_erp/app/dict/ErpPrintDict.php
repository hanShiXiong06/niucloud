<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

/** ERP 打印能力与业务场景字典。 */
final class ErpPrintDict
{
    public static function providers(): array
    {
        return [
            'xpyun' => [
                'key' => 'xpyun', 'name' => '芯烨云', 'modes' => ['cloud'], 'types' => ['receipt', 'label'],
                'fields' => ['user' => '开发者账号', 'user_key' => '开发者密钥', 'sn' => '打印机编号'],
                'size_mode' => 'multiple', 'description' => '支持云小票和云标签；字号按设备指令倍数输出。',
            ],
            'feie' => [
                'key' => 'feie', 'name' => '飞鹅云', 'modes' => ['cloud'], 'types' => ['receipt', 'label'],
                'fields' => ['user' => '飞鹅账号', 'user_key' => 'UKEY', 'sn' => '打印机编号'],
                'size_mode' => 'multiple', 'description' => '支持云小票和标签；不同机型的标签能力以厂商为准。',
            ],
            'yilianyun' => [
                'key' => 'yilianyun', 'name' => '易联云', 'modes' => ['cloud'], 'types' => ['receipt'],
                'fields' => ['open_id' => '应用 ID', 'api_key' => '应用密钥', 'machine_code' => '终端号', 'machine_key' => '终端密钥'],
                'size_mode' => 'multiple', 'description' => '适合云小票；沿用框架内置官方 SDK。',
            ],
            'bluetooth_escpos' => [
                'key' => 'bluetooth_escpos', 'name' => '蓝牙 ESC/POS', 'modes' => ['bluetooth'], 'types' => ['receipt'],
                'fields' => [], 'size_mode' => 'flexible', 'description' => '由移动端连接并发送，支持常见蓝牙小票机。',
            ],
            'bluetooth_tspl' => [
                'key' => 'bluetooth_tspl', 'name' => '蓝牙 TSPL 标签', 'modes' => ['bluetooth'], 'types' => ['label'],
                'fields' => [], 'size_mode' => 'flexible', 'description' => '由移动端连接并发送，适合常见标签机；需选择正确服务与特征值。',
            ],
        ];
    }

    public static function scenes(): array
    {
        return [
            'sale_created' => ['name' => '销售开单', 'document_title' => '销售单', 'trigger' => 'sale.created', 'biz_type' => 'sale', 'template_type' => 'receipt', 'granularity' => 'order', 'description' => '销售订单创建后打印销售小票。'],
            'sale_cancelled' => ['name' => '销售撤销', 'document_title' => '销售撤销单', 'trigger' => 'sale.cancelled', 'biz_type' => 'sale', 'template_type' => 'receipt', 'granularity' => 'order', 'description' => '销售订单撤销后打印红冲/退回凭证。'],
            'receipt_confirmed' => ['name' => '确认收款', 'document_title' => '收款单', 'trigger' => 'finance.receipt.confirmed', 'biz_type' => 'receivable', 'template_type' => 'receipt', 'granularity' => 'settlement', 'description' => '财务确认实际到账后打印收款凭证。'],
            'payment_confirmed' => ['name' => '确认付款', 'document_title' => '付款单', 'trigger' => 'finance.payment.confirmed', 'biz_type' => 'payable', 'template_type' => 'receipt', 'granularity' => 'settlement', 'description' => '财务确认实际出账后打印付款凭证。'],
            'offset_confirmed' => ['name' => '确认折账', 'document_title' => '折账单', 'trigger' => 'finance.offset.confirmed', 'biz_type' => 'offset', 'template_type' => 'receipt', 'granularity' => 'settlement', 'description' => '应收应付确认折账后打印非现金结算凭证。'],
            'asset_inbound' => ['name' => '设备入库标签', 'trigger' => 'asset.inbound', 'biz_type' => 'asset', 'template_type' => 'label', 'granularity' => 'device', 'description' => '设备完成入库后按设备打印标签。'],
            'asset_label' => ['name' => '手动设备标签', 'trigger' => 'manual.asset.label', 'biz_type' => 'asset', 'template_type' => 'label', 'granularity' => 'device', 'description' => '库存中心手动补打单台或多台设备标签。'],
        ];
    }

    public static function variables(): array
    {
        return [
            ['key' => 'site_name', 'name' => '门店名称'], ['key' => 'document_title', 'name' => '单据标题'],
            ['key' => 'document_no', 'name' => '单号'], ['key' => 'source_no', 'name' => '来源单号'],
            ['key' => 'settlement_no', 'name' => '结算单号'], ['key' => 'business_reason', 'name' => '业务说明'],
            ['key' => 'settlement_method', 'name' => '结算方式/账户'],
            ['key' => 'occurred_at', 'name' => '业务时间'], ['key' => 'party_name', 'name' => '往来主体'],
            ['key' => 'operator_name', 'name' => '操作人'], ['key' => 'amount', 'name' => '金额'],
            ['key' => 'asset_no', 'name' => '资产号'], ['key' => 'imei', 'name' => 'IMEI/串号'],
            ['key' => 'model', 'name' => '设备名称'], ['key' => 'spec', 'name' => '规格'],
            ['key' => 'warehouse_name', 'name' => '仓库'], ['key' => 'items_text', 'name' => '设备明细'],
        ];
    }
}
