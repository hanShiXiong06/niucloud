<?php
declare(strict_types=1);

use addon\hsx_kingdee_jdy\app\gateway\Client;
use addon\hsx_kingdee_jdy\app\gateway\ReadOnlyTransport;
use addon\hsx_kingdee_jdy\app\gateway\Signature;
use addon\hsx_kingdee_jdy\app\gateway\Transport;
use addon\hsx_kingdee_jdy\app\support\PartyMatch;
use addon\hsx_kingdee_jdy\app\support\Plan;
use addon\hsx_kingdee_jdy\app\support\Problem;
use addon\hsx_kingdee_jdy\app\support\Secret;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (callable $fn, string $reason) use ($check): void {
    try { $fn(); } catch (Problem $error) { $check($error->reason === $reason, $reason); return; }
    $check(false, 'Expected rejection ' . $reason);
};

$check(Signature::hmac('abc', 'abc123') === 'ZDljMTI3NGIyNTE1MTRkYzlkNjc1MDNhYjUzMzgzNWMyY2M4YTdjMzdmNmM3YTVlNDkxMTkzNjdiOTFjNzUyZQ==', 'official app_signature vector');
$query = ['app_key' => 'bVZgAZOv1', 'app_signature' => 'MzZlYTk0ODk4MWZlNjdiODNmNWU4YzViNzYxNGM5MTFlOGJkN2NjMzk0MTJkZGNhZGM0NzZhN2YxZDJmOTlkZA=='];
$headers = Signature::headers('200421', 'f2adcfef73369bfc4e1384677d38a0ff', ReadOnlyTransport::TOKEN_PATH, $query, '1670305063559', '4427456950');
$check($headers['X-Api-Signature'] === 'OTFiZTliNDFiMjNkYTI3YzVhNzg4MDI4ZGU3MWY1ZTA5ZTk1NjVlNGM1YTI1ZjIxY2Y5YTA3ZGY2OGI1MGQ1MQ==', 'official gateway signature vector');
$check($headers === Signature::headers('200421', 'f2adcfef73369bfc4e1384677d38a0ff', ReadOnlyTransport::TOKEN_PATH, array_reverse($query, true), '1670305063559', '4427456950'), 'ASCII query sorting');
$check(str_contains(Signature::canonical('get', '/path', ['z' => '+ =中文', 'a' => 'x'], '1', '2'), '%252B%2520%253D'), 'double URL encoding');
$check(str_ends_with(Signature::canonical('get', '/path', [], '1', '2'), "x-api-timestamp:1\n"), 'final newline');

foreach (['http://vip1-gd.jdy.com', 'https://tf.jdy.com/', 'https://vip1.jdy.com'] as $domain) $check(ReadOnlyTransport::router($domain) === rtrim($domain, '/'), 'official IDC domain accepted');
foreach (['https://jdy.com.evil.example', 'https://user@vip1.jdy.com', 'https://127.0.0.1', "https://tf.jdy.com\r\nX: secret", 'https://tf.jdy.com/path', 'https://tf.jdy.com:8443', 'https://apiexp.open.kingdee.com/k3cloud/'] as $domain) $reject(fn() => ReadOnlyTransport::router($domain), 'ROUTER_INVALID');
foreach (ReadOnlyTransport::READ_PATHS as $path) { ReadOnlyTransport::assertAllowed('POST', $path); $checks++; }
foreach (['/jdyscm/purchase/add', '/jdyscm/bill/check', '/jdyscm/delivery/delete', '//evil.com', '/jdyscm/purchase/list?x=1'] as $path) {
    foreach (['GET', 'POST', 'DELETE'] as $method) $reject(fn() => ReadOnlyTransport::assertAllowed($method, $path), 'WRITE_DISABLED');
}
$cipher = Secret::seal('fixture-secret', 'fixture-key', 12, 'app_secret');
$check($cipher !== Secret::seal('fixture-secret', 'fixture-key', 12, 'app_secret'), 'random encryption nonce');
$check(Secret::open($cipher, 'fixture-key', 12, 'app_secret') === 'fixture-secret', 'encrypted secret roundtrip');
$reject(fn() => Secret::open($cipher, 'fixture-key', 13, 'app_secret'), 'CREDENTIAL_INVALID');
$reject(fn() => Secret::open($cipher, 'fixture-key', 12, 'client_secret'), 'CREDENTIAL_INVALID');
$reject(fn() => Secret::open($cipher, 'other-key', 12, 'app_secret'), 'CREDENTIAL_INVALID');
$reject(fn() => Secret::seal('x', '', 12, 'token'), 'ENCRYPTION_CONFIG');

$fake = new class implements Transport {
    public array $calls = [];
    public array $responses = [];
    public function send(string $method, string $path, array $query, array $headers, array $body): array
    {
        ReadOnlyTransport::assertAllowed($method, $path);
        $this->calls[] = compact('method', 'path', 'query', 'headers', 'body');
        return array_shift($this->responses);
    }
};
$client = new Client($fake);
$config = ['client_id' => '123', 'client_secret' => 'fixture', 'app_key' => 'abc', 'app_secret' => 'abc123', 'account_id' => '456', 'domain' => 'https://tf.jdy.com'];
$fake->responses[] = ['errcode' => 0, 'data' => ['access_token' => 'fixture-token', 'expires' => (time() + 3600) * 1000]];
$check($client->token($config)['access_token'] === 'fixture-token', 'token contract');
$check($fake->calls[0]['query']['app_signature'] === Signature::hmac('abc', 'abc123'), 'correct credential pair signs app key');
$check(!str_contains(json_encode($fake->calls[0]), 'abc123'), 'no plaintext app secret sent');
$fake->responses[] = ['errcode' => 1, 'description' => 'secret should not leak'];
$reject(fn() => $client->token($config), 'AUTH_REJECTED');
$fake->responses[] = ['errcode' => 0, 'data' => ['access_token' => 'x', 'expires' => 123]];
$reject(fn() => $client->token($config), 'TOKEN_INVALID');
$fake->responses[] = ['code' => '0', 'items' => [], 'records' => 0];
$check($client->query($config, 'token', 'purchase', ['pageSize' => 9999])['items'] === [], 'successful empty list');
$last = end($fake->calls);
$check($last['body']['filter']['pageSize'] === 100 && $last['query']['dbId'] === '456', 'paging cap and account scoped');
$check($last['headers']['X-GW-Router-Addr'] === 'https://tf.jdy.com', 'IDC header');
foreach ([['code' => 0], ['msg' => 'success'], ['code' => '-3', 'items' => [['code' => '0']]]] as $data) {
    $fake->responses[] = $data;
    $reject(fn() => $client->query($config, 'token', 'purchase'), isset($data['code']) && (string)$data['code'] === '0' ? 'RESPONSE_INVALID' : 'QUERY_REJECTED');
}
$fake->responses[] = ['code' => '46002'];
$check($client->query($config, 'token', 'purchase')['records'] === 0, 'documented no-record code');
$reject(fn() => $client->query($config, 'token', 'audit'), 'QUERY_UNSUPPORTED');

$check(PartyMatch::phone('+86 138-0013-8000') === '13800138000', 'phone normalization');
$check(PartyMatch::decide('', [], true)['action'] === 'create', 'missing phone creates stable identity');
$check(PartyMatch::decide('138****8000', [], true)['action'] === 'manual', 'masked phone cannot match');
$check(PartyMatch::decide('13800138000', [], false)['action'] === 'manual', 'incomplete pages cannot create duplicate');
$check(PartyMatch::decide('13800138000', [], true)['action'] === 'create', 'complete absence can create');
$candidates = [['number' => 'C1', 'phones' => ['+86 13800138000']]];
$check(PartyMatch::decide('13800138000', $candidates, true)['number'] === 'C1', 'exact phone match');
$candidates[] = ['number' => 'C2', 'phones' => ['13800138000']];
$check(PartyMatch::decide('13800138000', $candidates, true)['action'] === 'manual', 'ambiguous phone blocks');
$check(PartyMatch::decide('', [], false, 'C9')['number'] === 'C9', 'existing explicit map takes priority');
$check(PartyMatch::decryptPhone('bsiV0ZrykeIH/5ZZ65lX5A==', '75eb3c7cd5974c3018d6934e142b5f81') === '18599999999', 'official privacy decryption vector');
$reject(fn() => PartyMatch::decryptPhone('bad', '75eb3c7cd5974c3018d6934e142b5f81'), 'PRIVACY_DECRYPT');

$event = ['event_name' => 'erp.ledger.recorded.v1', 'event_version' => 1, 'event_id' => 'fixture-1', 'site_id' => 12, 'occurred_at' => time(),
    'payload' => ['kind' => 'asset', 'fact_key' => 'asset:1', 'ledger' => ['id' => 1, 'site_id' => 12, 'asset_id' => 6, 'party_id' => 2,
        'action' => 'sold', 'source_no' => 'ERP-S1', 'imei' => 'FIXTURE-IMEI', 'before_warehouse_id' => 3,
        'before_total_cost' => '4500', 'after_total_cost' => '4500', 'cost_delta' => 0], 'source_context' => ['business_amount' => '5000.00']]];
$maps = ['customer:2' => ['external_number' => 'C2'], 'supplier:2' => ['external_number' => 'S2'],
    'asset_material:6' => ['external_number' => 'P6', 'unit_id' => '1'], 'product:6' => ['external_number' => 'P6', 'unit_id' => '1'], 'warehouse:3' => ['external_number' => 'W3']];
$plan = Plan::fromEvent($event, $maps);
$check(!$plan['requirements'] && $plan['draft']['items'][0]['entries'][0]['price'] === 5000.0, 'sales price is not stock cost');
$check($plan['draft']['items'][0]['entries'][0]['serNumList'][0]['serNum'] === 'FIXTURE-IMEI', 'serial snapshot');
$check($plan['write_enabled'] === false, 'draft is not a remote write');
foreach (['sale_cancel', 'transfer', 'cost_adjust', 'purchase_return', 'sale_return'] as $action) {
    $copy = $event; $copy['payload']['ledger']['action'] = $action;
    $check(Plan::fromEvent($copy, $maps)['draft'] === null, 'unsafe flow blocked: ' . $action);
}
$copy = $event; unset($copy['payload']['source_context']);
$check(Plan::fromEvent($copy, $maps)['draft'] === null, 'missing sale price cannot use cost');
$copy = $event; $copy['payload']['source_context']['ownership_type'] = 'consigned';
$check(Plan::fromEvent($copy, $maps)['draft'] === null, 'consignment is not owned inventory');
$copy = $event; $copy['payload']['ledger']['site_id'] = 99;
$reject(fn() => Plan::fromEvent($copy), 'FACT_INVALID');
$copy = $event; $copy['payload']['fact_key'] = 'asset:2';
$reject(fn() => Plan::fromEvent($copy), 'FACT_INVALID');
$check(count(Plan::fromEvent($event)['requirements']) >= 3, 'missing maps surfaced');
$copy = $event; $copy['payload']['kind'] = 'quantity'; $copy['payload']['fact_key'] = 'quantity:1';
$copy['payload']['ledger'] = ['id' => 1, 'site_id' => 12, 'product_id' => 6, 'party_id' => 2, 'warehouse_id' => 3, 'biz_type' => 'sale_standard', 'quantity' => '-3', 'cost_delta' => '-30'];
$copy['payload']['source_context']['business_amount'] = '60.00';
$check(Plan::fromEvent($copy, $maps)['draft']['items'][0]['entries'][0]['price'] === 20.0, 'quantity sale uses revenue not weighted stock cost');
$menu = require dirname(__DIR__) . '/app/dict/menu/site.php';
$check($menu[0]['menu_key'] === 'hsx_kingdee_jdy', 'independent menu');
$check(json_decode(file_get_contents(dirname(__DIR__) . '/info.json'), true)['support_app'] === 'hsx_erp', 'depends only on ERP');
echo json_encode(['status' => 'passed', 'checks' => $checks, 'external_requests' => 0, 'remote_write_verified' => false], JSON_PRETTY_PRINT) . PHP_EOL;
