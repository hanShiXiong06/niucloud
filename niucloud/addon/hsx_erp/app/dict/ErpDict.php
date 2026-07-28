<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

class ErpDict
{
    public const WAREHOUSE_OWNED = 'owned';
    public const WAREHOUSE_NEW_DEVICE = 'new_device';
    public const WAREHOUSE_ACCESSORY = 'accessory';
    public const WAREHOUSE_PEER = 'peer';
    public const WAREHOUSE_CONSIGNMENT = 'consignment';
    public const WAREHOUSE_EXCEPTION = 'exception';
    // 配件仓
    public const WAREHOUSE_ACCESSORY_OWNED = 'accessory_owned';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOID = 'void';

    public const ASSET_IN_STOCK = 'in_stock';
    public const ASSET_SOLD = 'sold';
    public const ASSET_RETURNED = 'returned';
    public const ASSET_VOID = 'void';
    public const ASSET_LOST = 'lost';

    public const TARGET_PAYABLE = 'payable';
    public const TARGET_RECEIVABLE = 'receivable';

    public const SETTLEMENT_PAYMENT = 'payment';
    public const SETTLEMENT_RECEIPT = 'receipt';
    public const SETTLEMENT_OFFSET = 'offset';

    public const PURCHASE_RETURN_DIRECT = 'direct_void';
    public const PURCHASE_RETURN_REFUND = 'refund_receivable';
    public const PURCHASE_RETURN_MIXED = 'mixed';

    public static function financeStatus(float $amount, float $settled): string
    {
        $amount = round($amount, 2);
        $settled = round($settled, 2);
        if ($amount <= 0.0001) {
            return self::STATUS_SETTLED;
        }
        if ($settled <= 0) {
            return self::STATUS_PENDING;
        }
        if ($settled + 0.0001 >= $amount) {
            return self::STATUS_SETTLED;
        }
        return self::STATUS_PARTIAL;
    }

    /** 设备库存轨迹动作；账本保存英文稳定值，界面只展示这里的中文。 */
    public static function getLedgerActionMap(): array
    {
        return [
            'inbound' => '采购入库',
            'consignment_inbound' => '代卖登记入库',
            'sold' => '销售出库',
            'purchase_return' => '采购退货出库',
            'sale_return' => '销售退货入库',
            'sale_return_cancel' => '销售退货撤销恢复',
            'purchase_cancel' => '采购撤销',
            'sale_cancel' => '整单销售撤销',
            'sale_item_cancel' => '单台销售撤销',
            'cost_adjust' => '成本调整',
            'retail_price_adjust' => '零售价调整',
            'refurbish' => '整备费用登记',
            'refurbish_send' => '开始整备',
            'refurbish_complete' => '整备完工',
            'flow' => '流转设置',
            'flow_set' => '流转设置',
            'transfer' => '库存调拨',
            'ownership_purchase' => '代卖转自有',
            'listing_photo_complete' => '商品拍摄完成',
            'listing_price_complete' => '销售定价完成',
            'listing_material_complete' => '商城资料整理完成',
            'listing_handoff' => '交接商城运营',
            'listing_publish' => '商城上架',
            'stocktake_loss' => '盘点确认盘亏',
            'stocktake_location' => '盘点修正库位',
        ];
    }

    /** 成本变化类型；整备支出与采购本金必须保持独立语义。 */
    public static function getCostTypeMap(): array
    {
        return [
            'purchase_adjust' => '供应商调价',
            'supplier_adjust' => '供应商调价',
            'refurbish' => '整备费用',
            'internal_adjust' => '内部成本修正',
            'cost_adjust' => '成本调整',
        ];
    }

    /**
     * 仓库类型是稳定的业务枚举，不是允许商户随意增删的展示字典。
     * 调拨、物权、应收应付和上架策略均依赖 value；前端只消费这里的展示与预设。
     */
    public static function getWarehouseTypeOptions(): array
    {
        return [
            [
                'value' => self::WAREHOUSE_OWNED,
                'label' => '二手机仓',
                'type' => 'primary',
                'description' => '自有库存仓，入库即生成采购应付；补齐分类、规格、图片和售价后可直接上商城，也可卖同行。',
                'preset' => ['need_photo' => 1, 'need_pricing' => 1, 'allow_direct_sale' => 1, 'allow_transfer' => 1, 'default_sale_target' => 'mall'],
                'constraints' => [],
            ],
            [
                'value' => self::WAREHOUSE_NEW_DEVICE,
                'label' => '新机仓',
                'type' => 'primary',
                'description' => '公司自有的新机库存仓，可直接销售和调拨；通常按标准商品资料及数量库存管理。',
                'preset' => ['need_photo' => 0, 'need_pricing' => 0, 'allow_direct_sale' => 1, 'allow_transfer' => 1, 'default_sale_target' => 'mall'],
                'constraints' => [],
            ],
            [
                'value' => self::WAREHOUSE_ACCESSORY,
                'label' => '配件仓',
                'type' => 'success',
                'description' => '公司自有的膜、壳及其他配件库存仓，可供零售、会员卡核销和库存调拨共同消费。',
                'preset' => ['need_photo' => 0, 'need_pricing' => 0, 'allow_direct_sale' => 1, 'allow_transfer' => 1, 'default_sale_target' => 'mall'],
                'constraints' => [],
            ],
            [
                'value' => self::WAREHOUSE_PEER,
                'label' => '同行仓',
                'type' => 'success',
                'description' => '自有库存仓，通常无需商品图片，可直接同行出库，成交后按实际售价确认利润。',
                'preset' => ['need_photo' => 0, 'need_pricing' => 0, 'allow_direct_sale' => 1, 'allow_transfer' => 1, 'default_sale_target' => 'peer'],
                'constraints' => [],
            ],
            [
                'value' => self::WAREHOUSE_CONSIGNMENT,
                'label' => '代卖仓',
                'type' => 'warning',
                'description' => '寄售库存，不属于自有资产，入库不生成普通采购应付，不能通过普通调拨转为自有库存。',
                'preset' => ['need_photo' => 1, 'need_pricing' => 1, 'allow_direct_sale' => 1, 'allow_transfer' => 0, 'default_sale_target' => 'mall'],
                'constraints' => ['allow_transfer' => 0],
            ],
            [
                'value' => self::WAREHOUSE_EXCEPTION,
                'label' => '异常仓',
                'type' => 'danger',
                'description' => '退回、复检、争议或待处理设备，默认不允许直接销售。',
                'preset' => ['need_photo' => 0, 'need_pricing' => 0, 'allow_direct_sale' => 0, 'allow_transfer' => 0, 'default_sale_target' => 'unset'],
                'constraints' => [],
            ],
            // 手机配件仓 -> 自有库存仓 -> 自有的 -> 自己花钱买的


        ];
    }

    public static function ledgerActionText(string $value): string
    {
        return self::getLedgerActionMap()[$value] ?? ($value !== '' ? '其他库存动作' : '未记录动作');
    }

    public static function costTypeText(string $value): string
    {
        return self::getCostTypeMap()[$value] ?? ($value !== '' ? '其他成本调整' : '未记录成本类型');
    }

    public static function assetStatusText(string $value): string
    {
        return [
            self::ASSET_IN_STOCK => '在库',
            self::ASSET_SOLD => '已售',
            self::ASSET_RETURNED => '已退货',
            self::ASSET_VOID => '已作废',
            self::ASSET_LOST => '已盘亏',
            'available_for_sale' => '可销售',
        ][$value] ?? ($value !== '' ? '其他状态' : '未记录状态');
    }

    /**
     * 单台采购设备的退货财务分流。
     * 前端只展示这里给出的动作和说明，实际退货服务也使用同一决策。
     */
    public static function purchaseReturnFlow(float $payableAmount, float $paidAmount, ?float $returnAmount = null): array
    {
        $payableAmount = max(0, round($payableAmount, 2));
        $paidAmount = max(0, round($paidAmount, 2));
        $unpaidAmount = max(0, round($payableAmount - $paidAmount, 2));
        $returnAmount = max(0, round($returnAmount ?? $payableAmount, 2));
        // 完全没有付款/折账事实时，确认退货即整台撤销采购往来：
        // 强制作废全部未付款，不允许因为前端误填较小退货金额而留下应付尾款。
        if ($paidAmount <= 0.0001) {
            $returnAmount = $payableAmount;
        }
        $offsetAmount = min($unpaidAmount, $returnAmount);
        $refundAmount = max(0, round($returnAmount - $offsetAmount, 2));
        $retainedPayable = max(0, round($unpaidAmount - $offsetAmount, 2));

        $base = [
            'requires_refund' => $refundAmount > 0.0001,
            'payable_amount' => $payableAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $unpaidAmount,
            'return_amount' => $returnAmount,
            'offset_amount' => $offsetAmount,
            'refund_amount' => $refundAmount,
            'retained_payable_amount' => $retainedPayable,
        ];

        if ($refundAmount <= 0.0001) {
            return array_merge($base, [
                'value' => self::PURCHASE_RETURN_DIRECT,
                'label' => '直接退货',
                'type' => 'warning',
                'action_label' => '直接退货',
                'submit_label' => '确认直接退货',
                'description' => sprintf('退货金额优先冲销未付款 ¥%.2f，不生成退款应收。', $offsetAmount),
            ]);
        }

        if ($offsetAmount <= 0.0001) {
            return array_merge($base, [
                'value' => self::PURCHASE_RETURN_REFUND,
                'label' => '退货退款',
                'type' => 'warning',
                'action_label' => '退货退款',
                'submit_label' => '提交退货并生成退款应收',
                'description' => sprintf('提交后生成供应商退款应收 ¥%.2f，由财务确认实际到账。', $refundAmount),
            ]);
        }

        return array_merge($base, [
            'value' => self::PURCHASE_RETURN_MIXED,
            'label' => '退货退款',
            'type' => 'warning',
            'action_label' => '退货退款',
            'submit_label' => '提交退货并生成退款应收',
            'description' => sprintf('先冲销未付款 ¥%.2f，剩余 ¥%.2f 生成退款应收。', $offsetAmount, $refundAmount),
        ]);
    }

    public static function lists(): array
    {
        return [
            'warehouse_type' => self::getWarehouseTypeOptions(),
            'asset_status' => [
                ['value' => self::ASSET_IN_STOCK, 'label' => '在库', 'type' => 'success', 'filterable' => true],
                ['value' => self::ASSET_SOLD, 'label' => '已售', 'type' => 'primary', 'filterable' => true],
                ['value' => self::ASSET_RETURNED, 'label' => '已退货', 'type' => 'warning', 'filterable' => true],
                ['value' => self::ASSET_VOID, 'label' => '已作废', 'type' => 'info', 'filterable' => true],
                ['value' => self::ASSET_LOST, 'label' => '已盘亏', 'type' => 'danger', 'filterable' => true],
            ],
            'finance_status' => [
                ['value' => self::STATUS_PENDING, 'label' => '待结算', 'type' => 'warning', 'filterable' => true],
                ['value' => self::STATUS_PARTIAL, 'label' => '部分结算', 'type' => 'primary', 'filterable' => true],
                ['value' => self::STATUS_SETTLED, 'label' => '已结清', 'type' => 'success', 'filterable' => true],
                ['value' => self::STATUS_VOID, 'label' => '已作废', 'type' => 'info', 'filterable' => true],
            ],
            'purchase_finance_status' => [
                ['value' => self::STATUS_PENDING, 'label' => '待付款', 'type' => 'warning', 'filterable' => true],
                ['value' => self::STATUS_PARTIAL, 'label' => '部分付款', 'type' => 'primary', 'filterable' => true],
                ['value' => self::STATUS_SETTLED, 'label' => '已结清', 'type' => 'success', 'filterable' => true],
                ['value' => self::STATUS_VOID, 'label' => '已撤销', 'type' => 'info', 'filterable' => true],
            ],
            'purchase_order_status' => [
                ['value' => self::STATUS_COMPLETED, 'label' => '已完成', 'type' => 'success', 'filterable' => true],
                ['value' => self::ASSET_RETURNED, 'label' => '已退货', 'type' => 'warning', 'filterable' => true],
                ['value' => self::STATUS_VOID, 'label' => '已撤销', 'type' => 'info', 'filterable' => true],
            ],
            'purchase_return_flow' => [
                ['value' => self::PURCHASE_RETURN_DIRECT, 'label' => '直接退货', 'type' => 'warning', 'filterable' => false],
                ['value' => self::PURCHASE_RETURN_REFUND, 'label' => '退货退款', 'type' => 'warning', 'filterable' => false],
                ['value' => self::PURCHASE_RETURN_MIXED, 'label' => '退货退款', 'type' => 'warning', 'filterable' => false],
            ],
            'purchase_refund_mode' => [
                ['value' => 'none', 'label' => '无需退款', 'type' => 'info', 'filterable' => false],
                ['value' => 'cash', 'label' => '退款待确认', 'type' => 'warning', 'filterable' => false],
                ['value' => 'offset', 'label' => '往来折抵', 'type' => 'primary', 'filterable' => false],
            ],
            'return_status' => [
                ['value' => self::STATUS_PENDING, 'label' => '旧单待确认', 'type' => 'warning', 'filterable' => true],
                ['value' => 'confirmed', 'label' => '已完成', 'type' => 'success', 'filterable' => true],
                ['value' => 'cancelled', 'label' => '已撤销', 'type' => 'info', 'filterable' => true],
            ],
            'refurbish_status' => [
                ['value' => 'none', 'label' => '无需整备', 'type' => 'info', 'filterable' => true],
                ['value' => 'pending', 'label' => '待整备', 'type' => 'warning', 'filterable' => true],
                ['value' => 'processing', 'label' => '整备中', 'type' => 'primary', 'filterable' => true],
                ['value' => 'done', 'label' => '整备完成', 'type' => 'success', 'filterable' => true],
                ['value' => 'failed', 'label' => '整备异常', 'type' => 'danger', 'filterable' => true],
            ],
            'sale_target' => [
                ['value' => 'unset', 'label' => '去向未定', 'type' => 'info', 'filterable' => true],
                ['value' => 'peer', 'label' => '卖同行', 'type' => 'primary', 'filterable' => true],
                ['value' => 'mall', 'label' => '上商城', 'type' => 'primary', 'filterable' => true],
            ],
            'listing_status' => [
                ['value' => 'none', 'label' => '无需上架', 'type' => 'info', 'filterable' => true],
                ['value' => 'need_photo', 'label' => '待拍照', 'type' => 'warning', 'filterable' => true],
                ['value' => 'need_price', 'label' => '待商城定价', 'type' => 'warning', 'filterable' => true],
                ['value' => 'need_material', 'label' => '待完善资料', 'type' => 'warning', 'filterable' => true],
                ['value' => 'ready', 'label' => '待上架', 'type' => 'primary', 'filterable' => true],
                ['value' => 'pending_shop', 'label' => '待商城运营完善', 'type' => 'warning', 'filterable' => true],
                ['value' => 'listed', 'label' => '商城已上架', 'type' => 'success', 'filterable' => true],
            ],
            'stocktake_status' => [
                ['value' => 'counting', 'label' => '盘点中', 'type' => 'primary', 'filterable' => true],
                ['value' => 'pending_review', 'label' => '待复核', 'type' => 'warning', 'filterable' => true],
                ['value' => 'completed', 'label' => '已完成', 'type' => 'success', 'filterable' => true],
                ['value' => 'cancelled', 'label' => '已取消', 'type' => 'info', 'filterable' => true],
            ],
            'stocktake_result' => [
                ['value' => 'pending', 'label' => '待盘', 'type' => 'info', 'filterable' => true],
                ['value' => 'normal', 'label' => '正常', 'type' => 'success', 'filterable' => true],
                ['value' => 'missing', 'label' => '盘亏', 'type' => 'danger', 'filterable' => true],
                ['value' => 'surplus', 'label' => '盘盈', 'type' => 'warning', 'filterable' => true],
                ['value' => 'location_mismatch', 'label' => '位置不符', 'type' => 'warning', 'filterable' => true],
                ['value' => 'status_abnormal', 'label' => '状态异常', 'type' => 'danger', 'filterable' => true],
            ],
            'ledger_action' => self::mapOptions(self::getLedgerActionMap()),
            'cost_type' => self::mapOptions(self::getCostTypeMap()),
            'finance_biz_type' => self::mapOptions(FinanceDict::getBizTypeMap()),
            'finance_source_type' => self::mapOptions(FinanceDict::getSourceTypeMap()),
        ];
    }

    private static function mapOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => (string)$value, 'label' => (string)$label, 'type' => 'info', 'filterable' => true];
        }
        return $options;
    }
}
