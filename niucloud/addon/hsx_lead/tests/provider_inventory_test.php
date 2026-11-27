<?php
declare(strict_types=1);

// Pure local provider tests. Guzzle MockHandler and a fake transport perform no network or DB I/O.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__, 3).'/vendor/autoload.php';

use addon\hsx_lead\app\provider\GuzzleJsonTransport;
use addon\hsx_lead\app\provider\ProviderException;
use addon\hsx_lead\app\provider\ProviderRegistry;
use addon\hsx_lead\app\provider\SoujikeProvider;
use addon\hsx_lead\app\provider\TransportContract;
use addon\hsx_lead\app\provider\WanjiProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class InventoryMockTransport implements TransportContract
{
    public array $calls = [];
    public function __construct(private array $responses)
    {
    }
    public function post(string $endpoint, array $headers, array $body): array
    {
        $this->calls[] = compact('endpoint', 'headers', 'body');
        $response = array_shift($this->responses);
        if (!is_array($response)) throw new RuntimeException('No local response fixture');
        return $response;
    }
}

$checks = 0;
function verify(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    $GLOBALS['checks']++;
}
function failure(callable $action, string $reason): ProviderException
{
    try {
        $action();
    } catch (ProviderException $exception) {
        verify($exception->reason() === $reason, 'safe failure reason '.$reason);
        verify($exception->getPrevious() === null, 'no previous exception can leak credentials');
        return $exception;
    }
    throw new RuntimeException('FAIL: expected '.$reason);
}
function stockPage(array $rows, int $num = 1, int $size = 2, bool $more = false): array
{
    return ['code' => 200, 'rows' => $rows, 'pageNum' => $num, 'pageSize' => $size, 'hasMore' => $more];
}
function skuPage(array $rows, int $total, int $num = 1, int $size = 2): array
{
    return ['code' => 200, 'data' => ['records' => $rows, 'total' => $total, 'current' => $num, 'size' => $size, 'pages' => (int) ceil($total / $size)]];
}

$wanjiRow = ['tenantId' => 742, 'shopId' => 6422, 'machineId' => '18307859', 'shopName' => '测试店',
    'productName' => 'iPhone 16', 'brandName' => '苹果', 'memory' => '256G', 'color' => '白色',
    'retailAmount' => '2000.01', 'wholesaleAmount' => '***', 'wholesaleAmountNot' => '1900',
    'purchaseAmount' => '***', 'purchaseAmountNot' => 1000, 'totalCostNot' => 1200,
    'innerRemark' => 'private-note-fixture', 'remark' => 'private-remark-fixture',
    'machineStatus' => 2, 'machineType' => 1, 'storeMallFlag' => 1, 'imeiNoFirst' => '123456789012345',
    'homeImageUrl' => 'https://example.test/cover.jpg',
    'imageList' => [['type' => 'image', 'url' => 'https://example.test/cover.jpg', 'private' => 'discard'],
        ['type' => 'video', 'url' => 'https://example.test/video.mp4'], ['type' => 'image', 'url' => 'javascript:alert(1)']],
    'publishPlatforms' => ['小店(1)', ['private' => 'discard']]];
$context = ['connection_id' => '12', 'tenant_id' => '742', 'store_id' => '6422', 'fetched_at' => '2026-10-30T00:00:00.000Z'];
$wanji = new WanjiProvider(new InventoryMockTransport([]));
$normalized = $wanji->normalize($wanjiRow, $context);
verify($normalized['source_key'] === 'wanji:12:742:6422:18307859', 'provider/connection/tenant/store/source stable identity');
verify($normalized['source_id'] === '18307859' && $normalized['source_store_id'] === '6422', 'identifiers remain exact strings');
verify($normalized['retail_price'] === '2000.01' && $normalized['trade_price'] === null, 'exact retail price, masked wholesale stays null');
verify($normalized['availability'] === 'unavailable' && $normalized['source_status']['machine_status'] === 2, 'verified unlisted flag excludes a row without guessing machineStatus stock enum');
verify($normalized['listing_status'] === 'unlisted' && $normalized['stock_status'] === 'unknown'
    && $normalized['quantity'] === null && $normalized['saleable_quantity'] === null
    && $normalized['quantity_basis'] === 'unknown', 'verified Wanji unlisted state does not fabricate stock or quantities');
foreach ([[1, 'unlisted'], ['1', 'unlisted'], [2, 'listed'], ['2', 'listed']] as [$flag, $listing]) {
    $listingItem = $wanji->normalize(array_replace($wanjiRow, ['storeMallFlag' => $flag]), $context);
    verify($listingItem['listing_status'] === $listing && $listingItem['source_status']['mall_flag'] === $flag,
        'official Wanji listing flags map exact integer/string 1 and 2 while retaining their raw type');
    verify($listingItem['stock_status'] === 'unknown' && $listingItem['quantity'] === null
        && $listingItem['saleable_quantity'] === null && $listingItem['availability'] === ($listing === 'unlisted' ? 'unavailable' : 'unknown'),
        'Wanji listed flag alone never asserts stock or sellability');
}
foreach ([null, '', 0, 3, true, false, 2.0, [], '02', ' 2', 'sold'] as $unknownFlag) {
    $listingItem = $wanji->normalize(array_replace($wanjiRow, ['storeMallFlag' => $unknownFlag]), $context);
    verify($listingItem['listing_status'] === 'unknown' && $listingItem['stock_status'] === 'unknown'
        && $listingItem['availability'] === 'unknown' && $listingItem['saleable_quantity'] === null,
        'unknown, malformed, padded or different Wanji values never default to an active listing');
}
$withoutMallFlag = $wanjiRow;
unset($withoutMallFlag['storeMallFlag']);
verify($wanji->normalize($withoutMallFlag, $context)['listing_status'] === 'unknown', 'missing Wanji mall flag stays unknown');
verify($normalized['images'] === ['https://example.test/cover.jpg'], 'deduplicate photos, exclude video and executable URLs');
verify($normalized['fetched_at'] === $context['fetched_at'], 'preserve sync run timestamp');
$safe = json_encode($normalized, JSON_UNESCAPED_UNICODE);
verify(!preg_match('/AmountNot|totalCost|purchaseAmount|innerRemark|private-note|private-remark/', $safe), 'no costs, Not fields or internal remarks in normalized snapshot');
verify($normalized['safe_snapshot']['publishPlatforms'] === ['小店(1)'], 'nested unapproved snapshot objects are discarded');
verify($wanji->normalize($wanjiRow, array_replace($context, ['connection_id' => '13']))['source_key'] !== $normalized['source_key'], 'separate connections do not merge');
verify($wanji->normalize(array_replace($wanjiRow, ['machineId' => '18307860']), $context)['source_key'] !== $normalized['source_key'], 'same IMEI does not merge independent source records');
failure(fn () => $wanji->normalize(array_replace($wanjiRow, ['tenantId' => null]), $context), 'invalid_scope');
failure(fn () => $wanji->normalize(array_replace($wanjiRow, ['machineId' => 1.234]), $context), 'invalid_scope');
failure(fn () => $wanji->normalize($wanjiRow, array_replace($context, ['tenant_id' => 'other'])), 'scope_mismatch');
failure(fn () => $wanji->normalize($wanjiRow, array_replace($context, ['store_id' => 'other'])), 'scope_mismatch');
failure(fn () => $wanji->normalize($wanjiRow, array_replace($context, ['fetched_at' => 'tomorrow'])), 'invalid_scope');
foreach (['' => null, '***' => null, '-1' => null, '1.001' => null, '1e3' => null, '0002.1' => '2.10', '0' => '0.00', '99999999999999.99' => '99999999999999.99'] as $input => $expected) {
    verify($wanji->normalize(array_replace($wanjiRow, ['retailAmount' => (string) $input]), $context)['retail_price'] === $expected, 'decimal mapping preserves missing/masked/invalid/zero/exact values');
}
verify($wanji->normalize(array_replace($wanjiRow, ['retailAmount' => 2000.01]), $context)['retail_price'] === null, 'untrusted binary float price is not silently rounded');

$payload = rtrim(strtr(base64_encode('{"tenant_id":742,"password":"must-not-leak"}'), '+/', '-_'), '=');
$jwt = 'fixture.'.$payload.'.signature';
$loginTransport = new InventoryMockTransport([['code' => 200, 'msg' => null, 'data' => ['access_token' => $jwt, 'link' => 'unused', 'expires_in' => 7200, 'password' => 'must-not-leak']]]);
$start = time();
$login = (new WanjiProvider($loginTransport))->login(['username' => ' fixture-user ', 'password' => 'fixture-password', 'device_info' => 'fixture-device']);
verify($login['access_token'] === $jwt && $login['expires_in'] === 7200, 'verified login schema uses data.access_token and expires_in');
verify($login['expires_at'] >= $start + 7200 && $login['expires_at'] <= time() + 7200, 'bounded seconds expiration calculated');
verify($login['identity'] === ['tenant_id' => '742', 'store_id' => null, 'store_name' => null, 'verified' => false], 'JWT tenant claim is only a hint, never verified store identity');
verify(!isset($login['password']) && !str_contains(json_encode($login['identity']), 'password'), 'safe identity has no credential fields');
verify($loginTransport->calls[0]['body'] === ['username' => 'fixture-user', 'password' => 'fixture-password'], 'HTTPS login sends ordinary password string without mutation');
verify($loginTransport->calls[0]['headers']['Platsource'] === 'MiniPrograms' && $loginTransport->calls[0]['headers']['DeviceInfo'] === 'fixture-device', 'known configurable client headers');
failure(fn () => (new WanjiProvider(new InventoryMockTransport([['code' => 200, 'data' => ['token' => 'wrong-path']]])))->login(['username' => 'u', 'password' => 'p']), 'schema_changed');
$invalidLogin = failure(fn () => (new WanjiProvider(new InventoryMockTransport([['code' => 500, 'msg' => 'fixture-password must-not-leak']])))->login(['username' => 'u', 'password' => 'p']), 'upstream_error');
verify(!str_contains($invalidLogin->getMessage(), 'must-not-leak'), 'upstream login messages are not exposed');
$expiry = (new WanjiProvider(new InventoryMockTransport([['code' => 200, 'data' => ['access_token' => 'fixture-token', 'expires_in' => 720000000]]])))->login(['username' => 'u', 'password' => 'p']);
verify($expiry['expires_in'] === 720000000 && $expiry['expires_at'] === null && $expiry['warnings'] !== [], 'unknown expiration units do not become distant future authorization');

$transport = new InventoryMockTransport([stockPage([$wanjiRow], 1, 2, true), stockPage([], 2, 2, false)]);
$provider = new WanjiProvider($transport);
$scope = ['tenant_id' => '742', 'store_id' => '6422', 'page_size' => 2];
$page1 = $provider->fetchPage(['access_token' => 'fixture-token'], [], $scope);
$page2 = $provider->fetchPage(['access_token' => 'fixture-token'], $page1['cursor'], $scope);
verify($page1['has_more'] && !$page2['has_more'] && $page2['cursor'] === ['page' => 3], 'caller can page to explicit end, empty final page is not data deletion');
verify($transport->calls[0]['endpoint'] === WanjiProvider::STOCK_ENDPOINT && $transport->calls[0]['headers']['Authorization'] === 'Bearer fixture-token', 'stock calls only fixed read-only endpoint');
verify($transport->calls[1]['body']['pageNum'] === 2 && $transport->calls[1]['body']['shopId'] === '6422', 'cursor and explicit store scope sent');
verify(!str_contains(json_encode($page1['rows']), 'private-note') && !isset($page1['rows'][0]['purchaseAmountNot']), 'fetch boundary filters private source data before returning rows');
failure(fn () => $provider->fetchPage(['access_token' => "bad\r\nheader"], [], $scope), 'invalid_credentials');
failure(fn () => $provider->fetchPage(['access_token' => 'fixture'], [], ['page_size' => 101]), 'invalid_scope');
failure(fn () => $provider->fetchPage(['access_token' => 'fixture'], [], ['query' => ['writeback' => true]]), 'invalid_scope');
failure(fn () => (new WanjiProvider(new InventoryMockTransport([stockPage([$wanjiRow, $wanjiRow])])))->fetchPage(['access_token' => 'fixture'], [], $scope), 'schema_changed');
failure(fn () => (new WanjiProvider(new InventoryMockTransport([stockPage([array_replace($wanjiRow, ['tenantId' => 743])])])))->fetchPage(['access_token' => 'fixture'], [], $scope), 'scope_mismatch');
failure(fn () => (new WanjiProvider(new InventoryMockTransport([stockPage([], 1, 2, true)])))->fetchPage(['access_token' => 'fixture'], [], $scope), 'schema_changed');
failure(fn () => (new WanjiProvider(new InventoryMockTransport([['code' => 401, 'msg' => 'secret']])))->fetchPage(['access_token' => 'fixture'], [], $scope), 'auth_required');
$allTypesTransport = new InventoryMockTransport([stockPage([])]);
(new WanjiProvider($allTypesTransport))->fetchPage(['access_token' => 'fixture'], [], $scope + ['omit_machine_type' => true]);
verify(!isset($allTypesTransport->calls[0]['body']['machineType']), 'explicit all-types scope omits original enum instead of inventing one');

$sku = ['id' => '1654363600596014518', 'storeId' => '36183', 'shopId' => null, 'categoryName' => 'iPhone 12', 'productName' => 'iPhone 12 128GB 白色',
    'brandName' => '苹果', 'capacity' => '128GB', 'color' => '白色', 'retailPrice' => '1599', 'tradePrice' => '',
    'imei' => '123456789012345', 'firstResIsVideo' => false, 'coverImage' => 'https://example.test/phone.jpg',
    'remark' => 'private-remark-fixture', 'inlineRemark' => 'private-internal-fixture', 'cost' => 1200];
$sjkContext = ['connection_id' => '20', 'store_id' => '36183', 'fetched_at' => '2026-10-30T00:00:00Z'];
$sjk = new SoujikeProvider(new InventoryMockTransport([]));
$skuNormalized = $sjk->normalize($sku, $sjkContext);
verify($skuNormalized['source_key'] === 'soujike:20::36183:1654363600596014518' && $skuNormalized['source_id'] === $sku['id'], 'soujike large source ID stable and exact');
verify($skuNormalized['retail_price'] === '1599.00' && $skuNormalized['trade_price'] === null && $skuNormalized['availability'] === 'unknown', 'same price and unknown availability contract');
verify($skuNormalized['listing_status'] === 'unknown' && $skuNormalized['stock_status'] === 'unknown'
    && $skuNormalized['quantity'] === null && $skuNormalized['saleable_quantity'] === null,
    'Soujike list without status and quantity fields remains unverified instead of assuming stock');
verify(!preg_match('/inlineRemark|private-internal|private-remark|"cost"/', json_encode($skuNormalized)), 'soujike remarks and financial extras excluded');
verify($sjk->normalize(array_replace($sku, ['firstResIsVideo' => true]), $sjkContext)['images'] === [], 'video-first resource is not a photo');
verify($sjk->normalize(array_replace($sku, ['categoryName' => '']), $sjkContext)['title'] === $sku['productName'], 'empty category falls back to known product name');
verify($sjk->login(['token' => 'fixture-token', 'store_id' => '36183'])['identity']['store_id'] === '36183', 'soujike explicit token fallback makes no account-password request');
failure(fn () => $sjk->normalize($sku, ['connection_id' => '20']), 'invalid_scope');
$sjkTransport = new InventoryMockTransport([skuPage([$sku, array_replace($sku, ['id' => '1654363600596014519'])], 3), skuPage([$sku], 3, 2)]);
$sjk = new SoujikeProvider($sjkTransport);
$sku1 = $sjk->fetchPage(['token' => 'fixture-token'], [], ['store_id' => '36183', 'page_size' => 2]);
$sku2 = $sjk->fetchPage(['token' => 'fixture-token'], $sku1['cursor'], ['store_id' => '36183', 'page_size' => 2]);
verify($sku1['has_more'] && !$sku2['has_more'], 'soujike data.total/current/size determines complete pagination');
verify($sku1['cursor']['source_total'] === 3 && $sku2['cursor']['source_total'] === 3, 'source total is carried across the complete pagination generation');
verify($sjkTransport->calls[0]['headers'] === ['recovery-token' => 'fixture-token'] && !isset($sjkTransport->calls[0]['body']['showHome']), 'token sent only known recovery header and dedicated stock endpoint replaces homepage');
verify($sjkTransport->calls[0]['body']['categoryIds'] === [] && $sjkTransport->calls[0]['body']['brandType'] === '', 'no hidden category or brand filtering');
verify(array_key_exists('saleStatus', $sjkTransport->calls[0]['body']) && $sjkTransport->calls[0]['body']['saleStatus'] === null && $sjkTransport->calls[0]['body']['isNew'] === null && $sjkTransport->calls[0]['body']['shopIds'] === [], 'full stock read includes sold and all new-device states without confusing branch and store IDs');
$listed = array_replace($sku, ['shelfStatus' => '1', 'shelfStatusName' => '已上架', 'saleStatus' => false, 'freeWarehouseCount' => 0, 'transferringCount' => 0, 'transferStatus' => 0, 'storage' => null, 'customerName' => 'private-customer', 'purchasePrice' => 'private-cost']);
$inStock = $sjk->normalize($listed, $sjkContext);
verify($inStock['listing_status'] === 'listed' && $inStock['stock_status'] === 'in_stock' && $inStock['availability'] === 'available', 'verified shelf and unsold status mark a normal source device available');
verify($inStock['quantity'] === null && $inStock['saleable_quantity'] === '1.000' && $inStock['quantity_basis'] === 'listed_stock_device', 'single-device proof does not require a source quantity or misuse zero warehouse counters');
verify($inStock['source_status'] === ['sale_status' => false, 'shelf_status' => '1', 'shelf_status_name' => '已上架'], 'raw source state types are retained without financial or client data');
verify(!preg_match('/private-|freeWarehouseCount|purchasePrice|customerName/', json_encode($inStock)), 'private accounting and customer fields and unrelated counters are discarded');
foreach ([['2', false, 'unlisted', 'in_stock', 'unavailable'], ['1', true, 'listed', 'out_of_stock', 'unavailable'], ['2', true, 'unlisted', 'out_of_stock', 'unavailable'], ['0', false, 'unknown', 'in_stock', 'unknown']] as [$shelf,$sold,$listing,$stock,$availability]) {
    $item = $sjk->normalize(array_replace($listed, ['shelfStatus' => $shelf, 'saleStatus' => $sold]), $sjkContext);
    verify($item['listing_status'] === $listing && $item['stock_status'] === $stock && $item['availability'] === $availability && $item['saleable_quantity'] === null, 'non-sellable shelf/sold combination remains unavailable without invented quantity');
}
foreach ([null, 0, 1, '0', '1', 'false', 'true', [], 2.0] as $sale) {
    $item = $sjk->normalize(array_replace($listed, ['saleStatus' => $sale]), $sjkContext);
    verify($item['availability'] !== 'available' && $item['stock_status'] === 'unknown' && $item['saleable_quantity'] === null, 'unverified sale-status types never become current stock');
}
foreach ([null, 0, 3, true, false, 1.0, [], '01', ' 1'] as $shelf) {
    $item = $sjk->normalize(array_replace($listed, ['shelfStatus' => $shelf]), $sjkContext);
    verify($item['availability'] !== 'available' && $item['listing_status'] === 'unknown', 'unknown shelf enums never become listed stock');
}
foreach (['已下架', '未上架', 'unknown-label', false, 1, []] as $name) {
    $item = $sjk->normalize(array_replace($listed, ['shelfStatusName' => $name]), $sjkContext);
    verify($item['availability'] === 'unknown' && $item['listing_status'] === 'unknown' && $item['saleable_quantity'] === null, 'a conflicting or changed listing name cannot promote a row to sellable stock');
}
$changedTotal = skuPage([$listed, array_replace($listed, ['id' => 'another-device'])], 4, 2);
$totalError = failure(fn () => (new SoujikeProvider(new InventoryMockTransport([$changedTotal])))->fetchPage(['token' => 'fixture'], ['page' => 2, 'source_total' => 3], ['store_id' => '36183', 'page_size' => 2]), 'schema_changed');
verify(str_contains($totalError->getMessage(), '总量发生变化'), 'a page-to-page total change stops publication before interpreting rows');
$invalidCursor = new InventoryMockTransport([]);
failure(fn () => (new SoujikeProvider($invalidCursor))->fetchPage(['token' => 'fixture'], ['page' => 2, 'source_total' => 'wrong'], ['store_id' => '36183', 'page_size' => 2]), 'invalid_scope');
verify($invalidCursor->calls === [], 'invalid page-total proof is rejected before HTTP');
failure(fn () => $sjk->normalize(array_replace($listed, ['storeId' => 'foreign']), $sjkContext), 'scope_mismatch');
$missingStore = $listed;
unset($missingStore['storeId']);
failure(fn () => $sjk->normalize($missingStore, $sjkContext), 'invalid_scope');
failure(fn () => (new SoujikeProvider(new InventoryMockTransport([skuPage([array_replace($listed, ['storeId' => 'foreign'])], 1)])))->fetchPage(['token' => 'fixture'], [], ['store_id' => '36183', 'page_size' => 2]), 'scope_mismatch');
$badPages = skuPage([$listed], 1);
$badPages['data']['pages'] = 2;
failure(fn () => (new SoujikeProvider(new InventoryMockTransport([$badPages])))->fetchPage(['token' => 'fixture'], [], ['store_id' => '36183', 'page_size' => 2]), 'schema_changed');
$gallery = array_replace($listed, ['firstResIsVideo' => true, 'coverImage' => 'https://example.test/video.mp4', 'skuImageList' => [['url' => 'https://example.test/video.mp4', 'id' => 'private-media-id'], ['url' => 'https://example.test/real.jpg?size=300', 'name' => 'private-uploader'], ['url' => 'https://example.test/unknown-resource'], ['url' => 'javascript:alert(1)']]]);
$galleryProvider = new SoujikeProvider(new InventoryMockTransport([skuPage([$gallery], 1)]));
$galleryRows = $galleryProvider->fetchPage(['token' => 'fixture'], [], ['store_id' => '36183', 'page_size' => 2]);
$galleryItem = $galleryProvider->normalize($galleryRows['rows'][0], $sjkContext);
verify($galleryItem['images'] === ['https://example.test/real.jpg?size=300'] && !preg_match('/video.mp4|private-media|private-uploader|unknown-resource|javascript/', json_encode($galleryItem)), 'video-first gallery retains only subsequent proven images through fetch and normalization');
failure(fn () => (new SoujikeProvider(new InventoryMockTransport([skuPage([$sku], 3)])))->fetchPage(['token' => 'fixture'], [], ['store_id' => '36183', 'page_size' => 2]), 'schema_changed');
failure(fn () => (new ProviderRegistry())->get('unknown'), 'unsupported_provider');
verify((new ProviderRegistry(new InventoryMockTransport([])))->get('wanjiguanjia')->key() === 'wanji', 'provider registry accepts documented alias');

$sjkHistory = [];
$sjkHandler = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(skuPage([], 0)))]));
$sjkHandler->push(Middleware::history($sjkHistory));
$sjkHttp = new GuzzleJsonTransport(new Client(['handler' => $sjkHandler]));
$sjkHttp->post(SoujikeProvider::STOCK_ENDPOINT, ['recovery-token' => 'synthetic-only'], ['current' => 1, 'size' => 2, 'saleStatus' => null, 'isNew' => null]);
verify((string) $sjkHistory[0]['request']->getUri() === 'https://www.dianddnet.cn/sjk-erp-service/api/sku/page' && $sjkHistory[0]['request']->getMethod() === 'POST', 'real transport explicitly permits only the verified new inventory request');
verify(json_decode((string) $sjkHistory[0]['request']->getBody(), true) === ['current' => 1, 'size' => 2, 'saleStatus' => null, 'isNew' => null], 'transport preserves JSON null filters rather than converting them to false or blank');

// Transport options and decoding are exercised through the actual Guzzle request pipeline.
$history = [];
$rawResponse = '{"code":200,"id":9223372036854775808,"price":2000.01,"text":'.json_encode('20.01 "inside"').',"negative":-0.05,"exponent":1e3}';
$handler = HandlerStack::create(new MockHandler([new Response(200, [], $rawResponse)]));
$handler->push(Middleware::history($history));
$http = new GuzzleJsonTransport(new Client(['handler' => $handler]));
$decoded = $http->post(WanjiProvider::STOCK_ENDPOINT, ['Authorization' => 'Bearer fixture-token'], ['pageNum' => 1]);
verify($decoded['id'] === '9223372036854775808' && $decoded['price'] === '2000.01' && $decoded['negative'] === '-0.05' && $decoded['exponent'] === '1e3', 'raw JSON integer/decimal lexemes are preserved without float arithmetic');
verify($decoded['text'] === '20.01 "inside"', 'decimal rewriting never changes quoted source strings');
verify(count($history) === 1 && $history[0]['request']->getMethod() === 'POST', 'one request, no retries');
$options = $history[0]['options'];
verify($options['allow_redirects'] === false && $options['verify'] === true && $options['timeout'] === 30 && $options['connect_timeout'] === 10, 'no redirects, verified TLS, bounded timeout');
failure(fn () => $http->post('https://example.test/steal', ['Authorization' => 'Bearer fixture-token'], []), 'forbidden_endpoint');
verify(count($history) === 1, 'unapproved endpoint never reaches HTTP client');
failure(fn () => $http->post(WanjiProvider::STOCK_ENDPOINT, ['Authorization' => "bad\r\nheader"], []), 'invalid_credentials');
foreach ([['not-json', 'schema_changed'], ['[]', 'schema_changed'], ['{"broken":', 'schema_changed']] as [$json, $reason]) failure(fn () => GuzzleJsonTransport::decode($json), $reason);
$redirectHistory = [];
$redirectHandler = HandlerStack::create(new MockHandler([new Response(302, ['Location' => 'https://example.test/steal'])]));
$redirectHandler->push(Middleware::history($redirectHistory));
failure(fn () => (new GuzzleJsonTransport(new Client(['handler' => $redirectHandler])))->post(WanjiProvider::STOCK_ENDPOINT, ['Authorization' => 'Bearer fixture-token'], []), 'http_error');
verify(count($redirectHistory) === 1, 'redirect response is rejected without forwarding authorization');
$expired = new GuzzleJsonTransport(new Client(['handler' => HandlerStack::create(new MockHandler([new Response(401, [], 'fixture-secret-response')]))]));
failure(fn () => $expired->post(WanjiProvider::STOCK_ENDPOINT, [], []), 'auth_required');
$network = new GuzzleJsonTransport(new Client(['handler' => HandlerStack::create(new MockHandler([new ConnectException('fixture-secret-password', new Request('POST', WanjiProvider::STOCK_ENDPOINT))]))]));
$networkError = failure(fn () => $network->post(WanjiProvider::STOCK_ENDPOINT, [], []), 'http_error');
verify(!str_contains($networkError->getMessage(), 'fixture-secret'), 'network diagnostic never includes request or secret');
$large = new GuzzleJsonTransport(new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], str_repeat(' ', 20 * 1024 * 1024 + 1))]))]));
failure(fn () => $large->post(WanjiProvider::STOCK_ENDPOINT, [], []), 'response_too_large');

// The shared normalization contract validates explicit adapter proofs, never
// arbitrary untrusted enum strings or guessed quantities from record presence.
final class StockContractFixtureProvider extends \addon\hsx_lead\app\provider\AbstractProvider
{
    public function key(): string
    {
        return 'fixture';
    }
    public function login(array $credentials): array
    {
        return [];
    }
    public function fetchPage(array $credentials, array $cursor, array $scope): array
    {
        return [];
    }
    public function normalize(array $row, array $context): array
    {
        return $this->common([], $context, array_replace(['id' => 'fixture-id', 'store_id' => 'fixture-store'], $row));
    }
}
$stockContract = new StockContractFixtureProvider(new InventoryMockTransport([]));
$stockContext = ['connection_id' => 'fixture-connection', 'store_id' => 'fixture-store'];
$unitProof = ['listing_status' => 'listed', 'stock_status' => 'in_stock', 'availability' => 'available',
    'quantity' => null, 'saleable_quantity' => '1', 'quantity_basis' => 'listed_stock_device'];
$verifiedUnit = $stockContract->normalize($unitProof, $stockContext);
verify($verifiedUnit['quantity'] === null && $verifiedUnit['saleable_quantity'] === '1.000'
    && $verifiedUnit['availability'] === 'available', 'verified per-device proof remains separate from raw source quantity');
foreach ([['listing_status' => 'unknown'], ['stock_status' => 'unknown'], ['quantity_basis' => 'guess'],
    ['saleable_quantity' => null], ['saleable_quantity' => '2'], ['quantity' => '1']] as $contradiction) {
    verify($stockContract->normalize(array_replace($unitProof, $contradiction), $stockContext)['availability'] !== 'available',
        'missing or contradictory device proof cannot become available');
}
foreach ([['listing_status' => 'unlisted'], ['stock_status' => 'out_of_stock']] as $unavailable) {
    verify($stockContract->normalize(array_replace($unitProof, $unavailable), $stockContext)['availability'] === 'unavailable',
        'explicit negative listing or stock evidence takes precedence');
}
$countProof = ['listing_status' => 'listed', 'stock_status' => 'in_stock', 'availability' => 'available',
    'quantity' => '2.5', 'saleable_quantity' => '2.5', 'quantity_basis' => 'source_quantity'];
verify($stockContract->normalize($countProof, $stockContext)['availability'] === 'available'
    && $stockContract->normalize($countProof, $stockContext)['quantity'] === '2.500', 'real source quantity supports an exact matching count proof');
foreach ([['quantity' => null], ['quantity' => '0'], ['quantity' => '1'], ['saleable_quantity' => '0']] as $mismatch) {
    verify($stockContract->normalize(array_replace($countProof, $mismatch), $stockContext)['availability'] !== 'available',
        'missing or contradictory real-count proof does not create available inventory');
}
foreach ([null, '', '***', '-1', '1e3', '1.0001', 1.1, true, [], '999999999999'] as $invalidQuantity) {
    $item = $stockContract->normalize(['quantity' => $invalidQuantity], $stockContext);
    verify($item['quantity'] === null && $item['availability'] === 'unknown', 'unsupported or missing source quantities remain unknown');
}
foreach (['0' => '0.000', '2.5' => '2.500', '00012.003' => '12.003', '99999999999.999' => '99999999999.999'] as $sourceQuantity => $expectedQuantity) {
    verify($stockContract->normalize(['quantity' => (string) $sourceQuantity], $stockContext)['quantity'] === $expectedQuantity,
        'source quantity is an exact decimal within the existing database precision');
}

echo 'PASS '.$checks." read-only provider assertions; all HTTP mocked, no DB, no ERP mutation\n";
