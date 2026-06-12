<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

class ErpDict
{
    public const TARGET_SELF_ERP = 'self_erp';

    public const INVENTORY_PENDING_IN = 'pending_in';
    public const INVENTORY_INBOUND_REJECTED = 'inbound_rejected';
    public const INVENTORY_IN_STOCK = 'in_stock';
    public const INVENTORY_REFURBISHING = 'refurbishing';
    public const INVENTORY_PENDING_PRICING = 'pending_pricing';
    public const INVENTORY_AVAILABLE_FOR_SALE = 'available_for_sale';

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

    public const SALE_DESTINATION_MALL = 'mall';

    public const SYNC_PENDING = 'pending';
    public const SYNC_PROCESSING = 'processing';
    public const SYNC_COMPLETED = 'completed';
    public const SYNC_FAILED = 'failed';

    public const OWNERSHIP_OWNED = 'owned';
    public const OWNERSHIP_CONSIGN = 'consign';
}
