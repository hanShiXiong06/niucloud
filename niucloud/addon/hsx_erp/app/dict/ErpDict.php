<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

class ErpDict
{
    public const TARGET_SELF_ERP = 'self_erp';

    public const INVENTORY_PENDING_IN = 'pending_in';
    public const INVENTORY_PENDING_PHOTO = 'pending_photo'; // 待拍照(已指派到"必拍照"仓, 拍完才入库在库)
    public const INVENTORY_INBOUND_REJECTED = 'inbound_rejected';
    public const INVENTORY_IN_STOCK = 'in_stock';
    public const INVENTORY_REFURBISHING = 'refurbishing';
    public const INVENTORY_PENDING_PRICING = 'pending_pricing';
    public const INVENTORY_AVAILABLE_FOR_SALE = 'available_for_sale';
    public const INVENTORY_LOCKED = 'locked'; // 已锁定/预留(挂单或未定价出库, 未收款可退回)

    public const REFURBISH_PROCESSING = 'processing';
    public const REFURBISH_COMPLETED = 'completed';
    public const REFURBISH_CANCELLED = 'cancelled';

    public const STOCK_ORDER_DRAFT = 'draft';
    public const STOCK_ORDER_PARTIAL_CONFIRMED = 'partial_confirmed';
    public const STOCK_ORDER_REJECTED = 'rejected';
    public const STOCK_ORDER_CONFIRMED = 'confirmed';

    public const STOCK_ITEM_PENDING = 'pending';
    public const STOCK_ITEM_CONFIRMED = 'confirmed';
    public const STOCK_ITEM_REJECTED = 'rejected';

    public const PRICE_ACTION_INITIAL = 'initial';
    public const PRICE_ACTION_ADJUST = 'adjust';

    // 销路 / 目标仓业务类型（与 erp_warehouse.business_type 对齐；真正驱动行为的是设备当前所在仓的 business_type）
    public const SALE_DESTINATION_MALL        = 'mall';        // 二手机仓/商城（进中台拍照+定价）
    public const SALE_DESTINATION_PEER        = 'peer';        // 同行仓（不拍照，直接出库）
    public const SALE_DESTINATION_CONSIGNMENT = 'consignment'; // 代卖仓
    public const SALE_DESTINATION_HOLD        = 'hold';        // 暂存仓
    public const SALE_DESTINATION_SCRAP       = 'scrap';       // 报废仓

    /** 仓库业务类型全集（= 销路） */
    public static function warehouseBusinessTypes(): array
    {
        return [
            self::SALE_DESTINATION_MALL        => '二手机仓(商城)',
            self::SALE_DESTINATION_PEER        => '同行仓',
            self::SALE_DESTINATION_CONSIGNMENT => '代卖仓',
            self::SALE_DESTINATION_HOLD        => '暂存仓',
            self::SALE_DESTINATION_SCRAP       => '报废仓',
        ];
    }

    public const SYNC_PENDING = 'pending';
    public const SYNC_PROCESSING = 'processing';
    public const SYNC_COMPLETED = 'completed';
    public const SYNC_FAILED = 'failed';

    public const OWNERSHIP_OWNED = 'owned';
    public const OWNERSHIP_CONSIGN = 'consign';

    // 出库后库存状态
    public const INVENTORY_OUTBOUND = 'outbound'; // 已出库(同行销售/报废等)

    // 出库类型
    public const OUTBOUND_TYPE_PEER_SALE = 'peer_sale'; // 同行销售
    public const OUTBOUND_TYPE_SCRAP     = 'scrap';     // 报废出库
    public const OUTBOUND_TYPE_OTHER     = 'other';     // 其他出库

    public static function getOutboundTypeMap(): array
    {
        return [
            self::OUTBOUND_TYPE_PEER_SALE => '同行销售',
            self::OUTBOUND_TYPE_SCRAP     => '报废出库',
            self::OUTBOUND_TYPE_OTHER     => '其他出库',
        ];
    }

    // 结算方式(出库时)
    public const SETTLE_MODE_NOW   = 'now';   // 现结(出库即定价, 立即生成应收)
    public const SETTLE_MODE_LATER = 'later'; // 价格未来回填(先出库, 回填价格后再生成应收)
    public const SETTLE_MODE_NONE  = 'none';  // 无结算(报废等, 不产生应收)

    // 出库单价格状态
    public const OUTBOUND_PRICE_PENDING = 'pending'; // 待回填价格
    public const OUTBOUND_PRICE_FILLED  = 'filled';  // 价格已确定

    // 出库单状态
    public const OUTBOUND_STATUS_COMPLETED = 'completed';
    public const OUTBOUND_STATUS_VOID      = 'void';

    // 盘点
    public const INVENTORY_LOST = 'lost'; // 盘亏丢失(盘点未盘到, 据实核销离库)

    public const STOCKTAKE_STATUS_COUNTING = 'counting'; // 盘点中
    public const STOCKTAKE_STATUS_FINISHED = 'finished'; // 已完成
    public const STOCKTAKE_STATUS_VOID     = 'void';

    public const STOCKTAKE_RESULT_UNCOUNTED = 'uncounted'; // 待盘(尚未盘到)
    public const STOCKTAKE_RESULT_MATCHED   = 'matched';   // 账实相符
    public const STOCKTAKE_RESULT_LOSS      = 'loss';      // 盘亏(应在库却未盘到)
    public const STOCKTAKE_RESULT_PROFIT    = 'profit';    // 盘盈(盘到但系统无在库记录)

    /** 库存状态中文(用于流水 before/after_status 等映射) */
    public static function getInventoryStatusMap(): array
    {
        return [
            self::INVENTORY_PENDING_IN         => '待入库',
            self::INVENTORY_PENDING_PHOTO      => '待拍照',
            self::INVENTORY_INBOUND_REJECTED   => '入库驳回',
            self::INVENTORY_IN_STOCK           => '在库',
            self::INVENTORY_REFURBISHING       => '整备中',
            self::INVENTORY_PENDING_PRICING    => '待销售定价',
            self::INVENTORY_AVAILABLE_FOR_SALE => '在售',
            self::INVENTORY_LOCKED             => '销售锁定',
            self::INVENTORY_OUTBOUND           => '已出库',
            self::INVENTORY_LOST               => '丢失',
        ];
    }

    /** 库存/操作流水动作中文 */
    public static function getLedgerActionMap(): array
    {
        return [
            'create_pending_asset'      => '建档待入库',
            'stock_in'                  => '入库',
            'confirm_stock_in'          => '确认入库',
            'skip_refurbishment'        => '跳过整备',
            'auto_create_refurbishment' => '自动发起整备',
            'refurbishment_start'       => '发起整备',
            'refurbishment_complete'    => '整备完工',
            'sales_priced'              => '销售定价',
            'price_adjust'              => '调价',
            'manual_cost_adjust'        => '手动调成本',
            'transfer'                  => '调拨',
            'outbound'                  => '出库',
            'return_outbound'           => '退货出库',
            'stocktake_loss'            => '盘点核销丢失',
        ];
    }

    /** 成本流水类型中文 */
    public static function getCostTypeMap(): array
    {
        return [
            'purchase'       => '采购成本',
            'manual_adjust'  => '手动调成本',
            'refurbishment'  => '整备费用',
            'return_reverse' => '退货冲回',
        ];
    }
}
