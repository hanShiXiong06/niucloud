<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

/**
 * ERP 账务轨迹统一字典。
 *
 * 这些值会长期保存在账本中，因此中文展示必须由后端统一输出，不能让 PC、移动端
 * 分别维护一套临时映射。历史插件不存在时也仍能依靠快照和这里的稳定字典阅读。
 */
class FinanceDict
{
    public static function getBizTypeMap(): array
    {
        return [
            'purchase' => '采购应付',
            'purchase_cancel' => '采购撤销冲回',
            'purchase_return' => '采购退货冲回',
            'purchase_return_loss' => '采购退货损失',
            'sale' => '销售应收',
            'sale_cancel' => '整单销售撤销',
            'sale_item_cancel' => '单台销售撤销',
            'sale_return' => '销售退货冲回',
            'sale_return_cancel' => '销售退货撤销恢复',
            'sale_compensation' => '售后补差应付',
            'refurbish' => '整备费用应付',
            'operating' => '经营收支',
            'payment' => '实际付款',
            'receipt' => '实际收款',
            'offset' => '往来折账',
            'adjust' => '账务调整',
        ];
    }

    public static function getSourceTypeMap(): array
    {
        return [
            'purchase' => '采购单',
            'purchase_asset' => '采购设备',
            'purchase_cancel' => '采购撤销单',
            'purchase_return' => '采购退货单',
            'sale' => '销售单',
            'sale_cancel' => '销售撤销单',
            'sale_item_cancel' => '销售设备撤销',
            'sale_return' => '销售退货单',
            'sale_return_cancel' => '销售退货撤销单',
            'sale_compensation' => '售后补差单',
            'refurbish' => '整备费用单',
            'hsx_erp.operating_expense' => '经营支出单',
            'hsx_erp.operating_income' => '经营收入单',
            'payable' => '应付款',
            'receivable' => '应收款',
            'payment' => '付款结算',
            'receipt' => '收款结算',
            'offset' => '折账结算',
            'asset' => '设备档案',
            'external' => '插件业务单',
        ];
    }

    public static function bizTypeText(string $value): string
    {
        return self::getBizTypeMap()[$value] ?? ($value !== '' ? '其他账务事件' : '未记录业务');
    }

    public static function sourceTypeText(string $value): string
    {
        return self::getSourceTypeMap()[$value] ?? ($value !== '' ? '其他来源单据' : '未记录来源');
    }
}
