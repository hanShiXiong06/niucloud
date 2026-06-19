<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

/**
 * 财务字典: 把"应付/应收/结算/折账"的状态与方式语义集中定义。
 */
class FinanceDict
{
    // 应付/应收 结算状态
    public const STATUS_PENDING = 'pending';   // 待结算
    public const STATUS_PARTIAL = 'partial';   // 部分结算
    public const STATUS_SETTLED = 'settled';   // 已结清
    public const STATUS_VOID    = 'void';      // 已作废

    public static function getStatusMap(): array
    {
        return [
            self::STATUS_PENDING => '待结算',
            self::STATUS_PARTIAL => '部分结算',
            self::STATUS_SETTLED => '已结清',
            self::STATUS_VOID    => '已作废',
        ];
    }

    /** 应付/应收 来源(业务类型) → 中文 */
    public static function getSourceTypeMap(): array
    {
        return [
            'recycle_device'     => '回收',
            'recycle_order'      => '回收',
            'recycle'            => '回收',
            'erp_inbound'        => '入库应付',
            'refurbishment'      => '整备',
            'erp_consign_buyout' => '代卖买断',
            'consign_buyout'     => '代卖买断',
            'erp_consign_sale'   => '代卖销售',
            'expense'            => '经营支出',
            'prepay'             => '采购预付',
            'cost_adjust'        => '成本下调返还',
            'manual_inbound'     => '手工建档',
            'sale'               => '销售',
            'sales'              => '销售',
            'outbound'           => '出库销售',
            'erp_outbound'       => '出库销售',
            'peer_sale'          => '同行销售',
            'erp_peer_sale'      => '同行销售',
            'mall_sale'          => '商城销售',
            'erp_mall_sale'      => '商城销售',
            'erp_sale'           => '销售',
            'settlement'         => '结算',
            'manual'             => '手工',
        ];
    }

    public static function sourceTypeText(string $type): string
    {
        if ($type === '') {
            return '其它';
        }
        return self::getSourceTypeMap()[$type] ?? $type;
    }

    // 结算方式
    public const METHOD_CASH   = 'cash';   // 现金
    public const METHOD_OFFSET = 'offset'; // 折账(净额冲抵)
    public const METHOD_MIXED  = 'mixed';  // 混合

    public static function getMethodMap(): array
    {
        return [
            self::METHOD_CASH   => '现金',
            self::METHOD_OFFSET => '折账',
            self::METHOD_MIXED  => '混合',
        ];
    }

    // 现金方向
    public const CASH_PAY     = 'pay';     // 我方付出
    public const CASH_COLLECT = 'collect'; // 我方收取
    public const CASH_NONE    = 'none';    // 无现金(纯折账)

    // 目标类型
    public const TARGET_PAYABLE    = 'payable';
    public const TARGET_RECEIVABLE = 'receivable';

    // 事件契约(与《应付与结算契约》一致)
    public const EVENT_PAYABLE_CREATED    = 'finance.payable.created.v1';
    public const EVENT_RECEIVABLE_CREATED = 'finance.receivable.created.v1';
    public const EVENT_SETTLEMENT_DONE    = 'finance.settlement.completed.v1';
}
