<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\contract;

/** Link a captured business fact to the vendor's existing manual workflow. Neither action sends money.
 * readOnly=true MUST NOT create a vendor task or submit a document. Return only local receipt evidence.
 */
interface ManualDocumentAdapterInterface
{
    /** Public identity of the already configured vendor connection. Never return credentials. */
    public function originalProfile(int $siteId): array;
    public function manualBusinessTypes(string $mode): array;
    public function originalDocument(array $event, array $context, bool $readOnly): array;
}
