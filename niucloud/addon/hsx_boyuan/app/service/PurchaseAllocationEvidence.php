<?php
declare(strict_types=1);

namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\{Contract, Problem};

/**
 * Read-only reconciliation of ONE external purchase batch against its verified local bindings.
 * This is an internal normalized record format, not a Boyuan API or an instruction to settle.
 * A future reader must independently supply effective allocations and an exhaustive, consistent
 * purchase balance from the authenticated scope, not echo the caller's expected facts.
 */
final class PurchaseAllocationEvidence
{
    private const SCOPE = ['site_id', 'mode', 'connection_key', 'account_id', 'company_id'];
    private const DOCUMENT = ['source_document_id', 'source_document_no', 'external_purchase_id', 'external_purchase_no'];

    public function __construct(private ?\Closure $readEvidence = null) {}

    public function lookup(array $expected = []): array
    {
        if ($this->readEvidence === null) return $this->pending('contract_unavailable');
        try {
            $this->require(is_int($expected['site_id'] ?? null) && $expected['site_id'] > 0
                && in_array($expected['mode'] ?? null, ['test', 'production'], true)
                && ($expected['currency'] ?? null) === 'CNY');
            foreach (array_merge(['connection_key', 'account_id', 'company_id'], self::DOCUMENT) as $key) Contract::id($expected[$key] ?? null);
            $total = Contract::cents($expected['purchase_amount'] ?? null);
            $this->require($total > 0);
            [$lines, $payments] = $this->bindings($expected, $total);
        } catch (Problem $e) {
            return $this->pending('source_unverified');
        }

        try {
            $evidence = ($this->readEvidence)($expected);
        } catch (\Throwable $e) {
            return $this->pending('read_unavailable');
        }
        if (!is_array($evidence) || ($evidence['complete'] ?? null) !== true || ($evidence['effective'] ?? null) !== true) {
            return $this->pending('evidence_unavailable');
        }

        try {
            foreach (array_merge(self::SCOPE, self::DOCUMENT, ['currency', 'purchase_amount']) as $key) {
                $this->require(($evidence[$key] ?? null) === $expected[$key]);
            }
            $applied = Contract::cents($evidence['applied_amount'] ?? null);
            $remaining = Contract::cents($evidence['remaining_amount'] ?? null);
            $this->require($applied + $remaining === $total);
            $seen = $linePaid = $paymentPaid = $linkPaid = $matched = [];
            $sum = 0;
            foreach ($this->rows($evidence['allocations'] ?? null) as $row) {
                $id = Contract::id($row['external_allocation_id'] ?? null);
                $line = Contract::id($row['external_line_id'] ?? null);
                $payment = Contract::id($row['external_payment_id'] ?? null);
                $amount = Contract::cents($row['amount'] ?? null);
                $this->require(!isset($seen[$id]) && $amount > 0 && isset($lines[$line], $payments[$payment]['limits'][$line])
                    && ($row['effective'] ?? null) === true
                    && ($row['external_purchase_id'] ?? null) === $expected['external_purchase_id']
                    && ($row['external_purchase_no'] ?? null) === $expected['external_purchase_no']
                    && ($row['external_payment_no'] ?? null) === $payments[$payment]['number']
                    && Contract::cents($row['voucher_amount'] ?? null) === $payments[$payment]['total']);
                $seen[$id] = true;
                $linkPaid[$payment][$line] = ($linkPaid[$payment][$line] ?? 0) + $amount;
                $paymentPaid[$payment] = ($paymentPaid[$payment] ?? 0) + $amount;
                $linePaid[$line] = ($linePaid[$line] ?? 0) + $amount;
                $sum += $amount;
                $this->require($linkPaid[$payment][$line] <= $payments[$payment]['limits'][$line]
                    && $paymentPaid[$payment] <= $payments[$payment]['total']
                    && $linePaid[$line] <= $lines[$line] && $sum <= $applied);
                $matched[] = ['external_allocation_id' => $id, 'external_line_id' => $line,
                    'external_payment_id' => $payment, 'external_payment_no' => $payments[$payment]['number'],
                    'amount' => Contract::amount($amount)];
            }
            $this->require($sum === $applied);
        } catch (Problem $e) {
            return $this->pending('evidence_mismatch');
        }
        return ['status' => $remaining === 0 ? 'settled' : ($applied > 0 ? 'partial' : 'unsettled'),
            'verified' => true, 'reason' => 'allocations_matched', 'currency' => 'CNY',
            'applied_amount' => Contract::amount($applied), 'remaining_amount' => Contract::amount($remaining),
            'allocations' => $matched];
    }

    /** Device, payable and local payment bindings come from the original ERP facts, never HTTP input. */
    private function bindings(array $expected, int $total): array
    {
        $lines = $payables = $sourceLines = $devices = [];
        $sum = 0;
        foreach ($this->rows($expected['lines'] ?? null) as $row) {
            $line = Contract::id($row['external_line_id'] ?? null);
            $source = Contract::id($row['source_line_id'] ?? null);
            $device = Contract::id($row['device_ref'] ?? null);
            $payable = $row['payable_id'] ?? null;
            $amount = Contract::cents($row['amount'] ?? null);
            $sum += $amount;
            $this->require(is_int($payable) && $payable > 0 && $amount > 0 && !isset($lines[$line]) && !isset($payables[$payable])
                && !isset($sourceLines[$source]) && !isset($devices[$device]) && $sum <= $total);
            $lines[$line] = $amount;
            $payables[$payable] = $line;
            $sourceLines[$source] = $devices[$device] = true;
        }
        $this->require($sum === $total);

        $payments = $numbers = $settlements = $settlementNos = $lineTotals = [];
        foreach ($this->rows($expected['payments'] ?? null) as $row) {
            $payment = Contract::id($row['external_payment_id'] ?? null);
            $number = Contract::id($row['external_payment_no'] ?? null);
            $sid = $row['settlement_id'] ?? null;
            $localNo = Contract::id($row['settlement_no'] ?? null);
            $voucher = Contract::cents($row['voucher_amount'] ?? null);
            $this->require(is_int($sid) && $sid > 0 && !isset($settlements[$sid]) && !isset($settlementNos[$localNo])
                && !isset($payments[$payment]) && !isset($numbers[$number]) && $voucher > 0);
            $limits = []; $sum = 0;
            foreach ($this->rows($row['applications'] ?? null) as $application) {
                $pid = $application['payable_id'] ?? null;
                $amount = Contract::cents($application['amount'] ?? null);
                $this->require(is_int($pid) && isset($payables[$pid]) && $amount > 0);
                $line = $payables[$pid];
                $lineTotals[$line] = ($lineTotals[$line] ?? 0) + $amount;
                $sum += $amount;
                $this->require(!isset($limits[$line]) && $lineTotals[$line] <= $lines[$line] && $sum <= $voucher);
                $limits[$line] = $amount;
            }
            // A merged voucher may include other purchases; do not apply the whole voucher here.
            $this->require($sum > 0);
            $payments[$payment] = ['number' => $number, 'total' => $voucher, 'limits' => $limits];
            $settlements[$sid] = $settlementNos[$localNo] = $numbers[$number] = true;
        }
        return [$lines, $payments];
    }

    private function rows($rows): array
    {
        $this->require(is_array($rows) && $rows === array_values($rows));
        foreach ($rows as $row) $this->require(is_array($row));
        return $rows;
    }

    private function require(bool $valid): void
    {
        if (!$valid) throw new Problem('ALLOCATION_UNVERIFIED', '核销身份、金额或明细未匹配');
    }

    private function pending(string $reason): array
    {
        return ['status' => 'pending', 'verified' => false, 'reason' => $reason,
            'currency' => null, 'applied_amount' => null, 'remaining_amount' => null, 'allocations' => []];
    }
}
