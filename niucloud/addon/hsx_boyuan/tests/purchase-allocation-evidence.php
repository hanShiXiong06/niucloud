<?php
declare(strict_types=1);

use addon\hsx_boyuan\app\service\PurchaseAllocationEvidence;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/support/Problem.php';
require_once dirname(__DIR__).'/app/support/Contract.php';
require_once dirname(__DIR__).'/app/service/PurchaseAllocationEvidence.php';

// In-memory reader, no application bootstrap, account, database, HTTP transport or funds.
$fixture = json_decode(file_get_contents(__DIR__.'/fixtures/purchase-allocation-evidence.json'), true, 64, JSON_THROW_ON_ERROR);
['expected' => $source, 'evidence' => $snapshot] = $fixture;
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$isPending = static fn(array $row): bool => $row['status'] === 'pending' && $row['verified'] === false
    && $row['applied_amount'] === null && $row['remaining_amount'] === null && $row['allocations'] === [];
$read = static fn($reply, array $facts) => (new PurchaseAllocationEvidence(static fn(array $query) => $reply))->lookup($facts);

foreach (['mock', 'test', 'production'] as $mode) {
    $result = (new PurchaseAllocationEvidence())->lookup(array_merge($source, ['mode'=>$mode, 'evidence'=>$snapshot, 'scenario'=>'success']));
    $check($isPending($result) && $result['reason']==='contract_unavailable', 'Default runtime must never consume inline evidence: '.$mode);
}
$check($isPending((new PurchaseAllocationEvidence())->lookup()), 'No expectation does not imply zero balance');
$result = $read($snapshot, $source);
$check($result['verified'] && $result['status'] === 'settled' && $result['applied_amount'] === '100.00'
    && $result['remaining_amount'] === '0.00' && count($result['allocations']) === 3, 'Two payments, two devices, one merged voucher: reconcile full scoped amount');

$partial = $snapshot;
array_pop($partial['allocations']);
$partial['allocations'][0]['amount'] = '59.99';
$partial['applied_amount'] = '69.99';
$partial['remaining_amount'] = '30.01';
$result = $read($partial, $source);
$check($result['verified'] && $result['status']==='partial' && $result['applied_amount']==='69.99'
    && $result['remaining_amount']==='30.01', 'Partial provider allocations must not become fully paid due to local payments');
$zero = array_replace($snapshot, ['allocations'=>[], 'applied_amount'=>'0.00', 'remaining_amount'=>'100.00']);
$result = $read($zero, $source);
$check($result['verified'] && $result['status']==='unsettled' && $result['applied_amount']==='0.00', 'Explicit authoritative zero is not missing evidence');

foreach (['site_id'=>90102, 'mode'=>'production', 'account_id'=>'other-book', 'connection_key'=>'another-connection', 'company_id'=>'other-company',
    'source_document_id'=>'purchase:21', 'source_document_no'=>'LOCAL-P21', 'external_purchase_id'=>'other-batch', 'external_purchase_no'=>'other-no',
    'purchase_amount'=>'101.00', 'currency'=>'USD', 'applied_amount'=>100.0, 'remaining_amount'=>'0.01', 'complete'=>false, 'effective'=>false] as $field=>$wrong) {
    $check($isPending($read(array_replace($snapshot, [$field=>$wrong]), $source)), 'Reject wrong scope, original or amount: '.$field);
}
foreach (['external_line_id'=>'BY-L1', 'external_purchase_id'=>'other-batch', 'external_purchase_no'=>'other-purchase',
    'external_payment_id'=>'BY-V2', 'external_payment_no'=>'other-payment', 'voucher_amount'=>'81.00',
    'effective'=>false, 'external_allocation_id'=>'A-1', 'amount'=>'-10.00'] as $field=>$wrong) {
    $wrongRow=$snapshot;
    $wrongRow['allocations'][1][$field]=$wrong;
    $check($isPending($read($wrongRow, $source)), 'Reject wrong allocation: '.$field);
}
$wrong = $snapshot;
$wrong['allocations'][0]['amount'] = '50.00';
$wrong['allocations'][1]['amount'] = '20.00';
$check($isPending($read($wrong, $source)), 'Same total cannot conceal an over-applied device');
$wrong = $partial;
$wrong['allocations'][0]['amount'] = '58.99';
$wrong['allocations'][1]['amount'] = '11.00';
$check($isPending($read($wrong, $source)), 'Same total and within device principal, but wrong settlement application');
$wrong = $snapshot;
array_pop($wrong['allocations']);
$check($isPending($read($wrong, $source)), 'Summary claiming fully settled without all allocation rows');
foreach ([null, [], ['bill_id'=>'PAY-1','un_settle_amounts'=>0], ['payment_registered'=>true,'allocation_applied_verified'=>true],
    ['code'=>'OK','complete'=>true,'effective'=>true]] as $reply) {
    $check($isPending($read($reply, $source)), 'Missing details / voucher alone / success flags are not purchase reconciliation');
}
$offline = (new PurchaseAllocationEvidence(static function () { throw new RuntimeException('fixture timeout'); }))->lookup($source);
$check($isPending($offline) && $offline['reason']==='read_unavailable', 'Unavailable reader yields no amounts');
$calls=0;
$reader = new PurchaseAllocationEvidence(static function () use (&$calls, $snapshot) { $calls++; return $snapshot; });
$wrong = $source;
$wrong['lines'][1]['payable_id'] = 201;
$check($reader->lookup($wrong)['reason']==='source_unverified', 'Duplicate payable');
$wrong = $source;
$wrong['lines'][1]['device_ref'] = 'asset:1011';
$check($reader->lookup($wrong)['reason']==='source_unverified', 'Duplicate original device');
$wrong = $source;
$wrong['payments'][0]['applications'][0]['payable_id'] = 99999;
$check($reader->lookup($wrong)['reason']==='source_unverified', 'Application to another payable');
$wrong = $source;
$wrong['payments'][0]['voucher_amount'] = '69.99';
$check($reader->lookup($wrong)['reason']==='source_unverified', 'Applications exceed voucher');
$wrong = $source;
$wrong['payments'][1]['external_payment_no'] = 'PAY-1';
$check($reader->lookup($wrong)['reason']==='source_unverified', 'Ambiguous original payment');
$check($calls===0, 'Invalid original facts do not invoke the evidence reader');
echo "PASS: $checks isolated purchase allocation evidence checks (no network or database).\n";
