<?php
declare(strict_types=1);

// Explicitly authorized demo acceptance. Never loads an application or its database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
ini_set('display_errors', '0');
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_kingdee\app\gateway\{ApiFailure, EndpointPolicy, OfficialSdk, ReadOnlyCurlTransport, ScopedWriteTransport, WebApiClient};

$options = getopt('', ['run:', 'allow-test-writes']);
$run = (string)($options['run'] ?? '');
if (!isset($options['allow-test-writes']) || !preg_match('/^HSXTEST[0-9A-F]{16}$/D', $run)) {
    fwrite(STDERR, "Requires --run=HSXTEST<16 uppercase hex digits> --allow-test-writes\n");
    exit(2);
}
$path = dirname(__DIR__, 3) . '/runtime/hsx_kingdee/live/' . $run . '.jsonl';
$handle = null;
$report = ['run' => $run, 'status' => 'started', 'stages' => [], 'remote_write_attempted' => false];
$secret = '';
$exit = 1;
try {
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) throw new RuntimeException('journal_directory');
    $handle = fopen($path, 'c+');
    if (!$handle || !chmod($path, 0600) || !flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('journal_locked');
    while (($line = fgets($handle)) !== false) {
        if (!str_ends_with($line, "\n")) throw new RuntimeException('incomplete_journal_requires_review');
        $report = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
    }
    $persist = static function () use (&$report, $handle): void {
        $report['updated_at'] = gmdate(DATE_ATOM);
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (fseek($handle, 0, SEEK_END) !== 0 || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('journal_failed');
    };
    $ini = parse_ini_file(dirname(__DIR__, 3) . '/vendor/SDK_PHP7.0_V8.2.0/php_sdk_demo/Config/conf.ini', false, INI_SCANNER_RAW);
    $url = 'https://apiexp.open.kingdee.com/k3cloud/';
    if (($ini['X-KDApi-ServerUrl'] ?? '') !== $url || ($ini['X-KDApi-AcctID'] ?? '') !== '6304ba61219bf5'
        || ($ini['X-KDApi-UserName'] ?? '') !== 'demo') throw new RuntimeException('approved_demo_mismatch');
    $endpoint = new EndpointPolicy($url, ['apiexp.open.kingdee.com']);
    $config = ['account_id' => $ini['X-KDApi-AcctID'], 'username' => $ini['X-KDApi-UserName'],
        'app_id' => $ini['X-KDApi-AppID'], 'organization' => '100'];
    $secret = $ini['X-KDApi-AppSec'];
    unset($ini);
    $query = new WebApiClient($endpoint, $config, $secret, new ReadOnlyCurlTransport($endpoint));
    $org = $query->queryExact('ORG_Organizations', ['FOrgId', 'FNumber', 'FName'], ['FNumber' => '100'], 2);
    if (count($org) !== 1 || $org[0][2] !== 'ApiTest') throw new RuntimeException('organization_not_unique');
    $orgId = (string)$org[0][0];
    $report['organization'] = ['id' => $orgId, 'number' => '100', 'name' => 'ApiTest'];
    $ref = static fn(string $number): array => ['FNumber' => $number];
    $material = $run . 'M';
    $description = 'HSX isolated acceptance ' . $run;
    $model = [
        'FNumber' => $material, 'FName' => $material, 'FDescription' => $description,
        'FCreateOrgId' => $ref('100'), 'FUseOrgId' => $ref('100'),
        'SubHeadEntity' => ['FErpClsID' => '1', 'FBaseUnitId' => $ref('Pcs'),
            'FIsPurchase' => true, 'FIsInventory' => true, 'FIsSale' => true],
        'SubHeadEntity1' => ['FStoreUnitID' => $ref('Pcs'), 'FCurrencyId' => $ref('PRE001'),
            'FIsSNManage' => false, 'FIsBatchManage' => false],
        'SubHeadEntity2' => ['FSaleUnitId' => $ref('Pcs'), 'FSalePriceUnitId' => $ref('Pcs'), 'FIsReturn' => true],
        'SubHeadEntity3' => ['FPurchaseUnitId' => $ref('Pcs'), 'FPurchasePriceUnitId' => $ref('Pcs'),
            'FPurchaseOrgId' => $ref('100'), 'FIsReturnMaterial' => true],
        'FEntityInvPty' => [['FInvPtyId' => $ref('01'), 'FIsEnable' => true], ['FInvPtyId' => $ref('02'), 'FIsEnable' => true]],
    ];
    $rows = $query->queryExact('BD_MATERIAL', ['FMaterialId', 'FNumber', 'FDescription', 'FDocumentStatus'],
        ['FNumber' => $material, 'FUseOrgId.FNumber' => '100'], 2);
    if (count($rows) > 1 || ($rows && $rows[0][2] !== $description)) throw new RuntimeException('material_identity_conflict');
    $transport = new ScopedWriteTransport($endpoint, 'BD_MATERIAL', $material, 'FNumber', 'FMaterialId', $orgId);
    foreach (['save', 'submit', 'audit'] as $method) {
        $stage = 'material_' . $method;
        $status = $rows[0][3] ?? '';
        if (($method === 'save' && $rows) || ($method === 'submit' && in_array($status, ['B', 'C'], true))
            || ($method === 'audit' && $status === 'C')) continue;
        if (isset($report['stages'][$stage])) throw new RuntimeException('previous_write_requires_manual_review');
        $data = $method === 'save' ? ['ValidateFlag' => true, 'IsVerifyBaseDataField' => true,
            'IsAutoSubmitAndAudit' => false, 'Model' => $model] : ['Numbers' => [$material], 'CreateOrgId' => (int)$orgId];
        $report['stages'][$stage] = ['state' => 'sending', 'number' => $material, 'at' => gmdate(DATE_ATOM)];
        $report['remote_write_attempted'] = true;
        $persist();
        $response = OfficialSdk::command($method, 'BD_MATERIAL', $data, $endpoint, $config, $secret, $transport);
        $result = $response['Result']['ResponseStatus'] ?? [];
        $report['stages'][$stage]['state'] = ($result['IsSuccess'] ?? null) === true ? 'accepted' : 'rejected';
        $report['stages'][$stage]['errors'] = array_map(static function ($error) use ($secret): array {
            return ['field' => mb_substr((string)($error['FieldName'] ?? ''), 0, 100),
                'message' => mb_substr(str_replace($secret, '[redacted]', (string)($error['Message'] ?? '')), 0, 500)];
        }, array_slice(is_array($result['Errors'] ?? null) ? $result['Errors'] : [], 0, 10));
        $persist();
        if (($result['IsSuccess'] ?? null) !== true) throw new RuntimeException('remote_business_rejected');
        $rows = $query->queryExact('BD_MATERIAL', ['FMaterialId', 'FNumber', 'FDescription', 'FDocumentStatus'],
            ['FNumber' => $material, 'FUseOrgId.FNumber' => '100'], 2);
        if (count($rows) !== 1 || $rows[0][2] !== $description) throw new RuntimeException('write_result_not_verified');
        $expected = ['save' => ['A', 'Z'], 'submit' => ['B'], 'audit' => ['C']][$method];
        if (!in_array($rows[0][3], $expected, true)) throw new RuntimeException('write_status_unexpected');
        $report['stages'][$stage]['state'] = 'verified';
        $report['stages'][$stage]['remote_id'] = (string)$rows[0][0];
        $persist();
    }
    $report['status'] = 'material_audited';
    $report['scope'] = 'Master-data write verified only; inventory and financial lifecycle not yet verified.';
    $exit = 0;
} catch (Throwable $error) {
    $report['status'] = 'blocked';
    $report['code'] = $error instanceof ApiFailure ? $error->reason : (get_class($error) === RuntimeException::class ? $error->getMessage() : 'local_failure');
} finally {
    if (isset($persist)) $persist();
    if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); }
    $secret = '';
}
echo json_encode($report + ['audit_file' => $path], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
exit($exit);
