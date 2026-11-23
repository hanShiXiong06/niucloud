<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\contract;

/** An optional read-through view of a vendor's existing local receipts. No remote
 * lookups (including allocation), mutations, dispatch, captures or submissions. */
interface LocalOriginalReceiptInterface
{
    public function localOriginalReceipt(array $event,array $context): array;
}
