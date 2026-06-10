<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\dict;

class ErpDict
{
    public const TARGET_SELF_ERP = 'self_erp';

    public const INVENTORY_PENDING_IN = 'pending_in';
    public const INVENTORY_IN_STOCK = 'in_stock';

    public const STOCK_ORDER_DRAFT = 'draft';
    public const STOCK_ORDER_CONFIRMED = 'confirmed';

    public const SYNC_PENDING = 'pending';
    public const SYNC_PROCESSING = 'processing';
    public const SYNC_COMPLETED = 'completed';
    public const SYNC_FAILED = 'failed';

    public const OWNERSHIP_OWNED = 'owned';
    public const OWNERSHIP_CONSIGN = 'consign';
}
