<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/support/RecycleErpOwnershipPolicy.php';
use addon\hsx_recycle\app\support\RecycleErpOwnershipPolicy as Policy;

$count = 0;
$assert = static function (string $expected, array $config, int $createdAt = 100, string $recorded = '', array $evidence = [], bool $available = true) use (&$count): void {
    $actual = Policy::resolve($config, $createdAt, $recorded, $evidence, $available);
    if ($actual !== $expected) throw new RuntimeException("case {$count}: expected {$expected}, got {$actual}");
    $count++;
};
$local = ['mode' => 'local', 'configured' => false];
$erp = ['mode' => 'self_erp', 'configured' => false];
$history = ['mode' => 'self_erp', 'initial_mode' => 'self_erp', 'configured' => true,
    'history' => [['at' => 200, 'mode' => 'local'], ['at' => 300, 'mode' => 'self_erp']]];

$assert('local', $local);
$assert('self_erp', $erp);
$assert('local', $local, 0);
$assert('self_erp', $history, 199);
$assert('local', $history, 200);
$assert('local', $history, 299);
$assert('self_erp', $history, 300);
$assert('self_erp', $history, 500);
$assert('unknown', $history, 0);
$assert('self_erp', $local, 500, 'self_erp');
$assert('local', $erp, 500, 'local');
$assert('unknown', $erp, 500, 'third_party');
$assert('self_erp', $local, 100, '', ['has_asset' => true]);
$assert('self_erp', $local, 100, '', ['has_payable' => 1]);
$assert('self_erp', $local, 100, '', ['has_settlement' => '1']);
$assert('self_erp', $local, 100, '', ['asset_id' => 5]);
$assert('self_erp', $local, 100, '', ['payable_id' => 5]);
$assert('self_erp', $local, 100, '', ['settlement_id' => 5]);
$assert('unknown', $local, 100, 'local', ['has_asset' => true]);
$assert('unknown', $local, 100, 'local', ['has_payable' => true]);
$assert('self_erp', $local, 100, 'self_erp', ['has_asset' => true]);
$assert('unknown', $local, 100, '', [], false);
$assert('unknown', $local, 100, 'self_erp', [], false);
$assert('unknown', $local, 100, '', ['ambiguous' => true]);
$assert('unknown', $local, 100, 'local', ['error' => 'lookup failed']);
$assert('unknown', $local, 100, '', ['lookup_failed' => true]);
$assert('unknown', $local, 100, '', ['status' => 'failed']);
$assert('local', $local, 100, '', ['has_asset' => 'false', 'asset_id' => 0]);
$assert('unknown', []);
$assert('local', [], 0, 'local');
$assert('self_erp', [], 0, '', ['has_asset' => true]);
$assert('unknown', ['mode' => 'bogus', 'configured' => false]);
$assert('unknown', ['mode' => 'local', 'configured' => true]);
$assert('local', ['mode' => 'local', 'initial_mode' => 'local', 'configured' => true, 'history' => []], 0);
$assert('unknown', ['mode' => 'local', 'initial_mode' => 'self_erp', 'configured' => true, 'history' => []]);
$broken = $history;
$broken['history'][1]['at'] = 200;
$assert('unknown', $broken, 100);
$broken['history'][1]['at'] = 199;
$assert('unknown', $broken, 250);
$broken['history'][1]['at'] = '300';
$assert('unknown', $broken, 250);
$broken = $history;
$broken['mode'] = 'local';
$assert('unknown', $broken, 250);
$rawHistory = $history;
unset($rawHistory['configured']);
$assert('local', $rawHistory, 250);
echo "[PASS] recycle ERP ownership policy: {$count} assertions; pure policy only\n";
