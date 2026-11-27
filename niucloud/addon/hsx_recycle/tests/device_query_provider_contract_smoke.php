<?php
declare(strict_types=1);

use addon\hsx_recycle\app\dict\device_query\DeviceQueryServiceDict;
use addon\hsx_recycle\app\dict\device_query\GkdtDeviceQueryServiceDict;
use addon\hsx_recycle\app\service\core\device_query\DeviceQueryCatalogService;
use addon\hsx_recycle\app\service\core\device_query\DeviceQueryNormalizer;
use addon\hsx_recycle\app\service\core\device_query\provider\GkdtQueryProvider;
use addon\hsx_recycle\app\service\core\device_query\provider\PathQueryProvider;

$root = dirname(__DIR__, 3);
require_once $root . '/addon/hsx_recycle/app/service/core/device_query/contract/DeviceQueryProviderInterface.php';
require_once $root . '/addon/hsx_recycle/app/service/core/device_query/provider/AbstractDeviceQueryProvider.php';
require_once $root . '/addon/hsx_recycle/app/service/core/device_query/provider/PathQueryProvider.php';
require_once $root . '/addon/hsx_recycle/app/service/core/device_query/provider/GkdtQueryProvider.php';
require_once $root . '/addon/hsx_recycle/app/dict/device_query/DeviceQueryServiceDict.php';
require_once $root . '/addon/hsx_recycle/app/dict/device_query/GkdtDeviceQueryServiceDict.php';
require_once $root . '/addon/hsx_recycle/app/service/core/device_query/DeviceQueryCatalogService.php';
require_once $root . '/addon/hsx_recycle/app/service/core/device_query/DeviceQueryNormalizer.php';

final class TestableGkdtQueryProvider extends GkdtQueryProvider
{
    public function signedParams(array $channel, string $serviceId, string $queryCode, int $timestamp): array
    {
        return $this->buildSignedParams($channel, $serviceId, $queryCode, $timestamp);
    }

    public function normalizeResponse(array $response): array
    {
        return $this->normalizeGkdtResponse($response);
    }
}

final class TestablePathQueryProvider extends PathQueryProvider
{
    public function requestConfig(array $channel, array $mapping, string $queryCode, string $queryType): array
    {
        return $this->buildRequestConfig($channel, $mapping, $queryCode, $queryType);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$provider = new TestableGkdtQueryProvider();
$params = $provider->signedParams([
    'appid' => 'demo-app',
    'secret' => 'demo-secret',
    'style' => '11',
    'service_id_key' => 'key',
], '10101', '359167074097936', 1784563200);

$assert(($params['code'] ?? '') === '359167074097936', '爱查必须使用 code 传查询号码');
$assert(($params['key'] ?? '') === '10101', '爱查必须使用 key 传服务 ID');
$assert(($params['sign'] ?? '') === 'c07d7579f01f2f29c50ec62cafcb85c7', '爱查 MD5 签名不符合协议');

$pathProvider = new TestablePathQueryProvider();
$pathRequest = $pathProvider->requestConfig([
    'base_url' => 'https://api.3023data.com',
    'method' => 'GET',
    'auth_type' => 'header',
    'auth_key' => 'key',
    'token' => 'demo-api-key',
], [
    'endpoint_value' => '/apple/coverage',
    'query_param' => 'sn',
], 'FK1ST6G4G5QY', 'sn');
$assert(($pathRequest['method'] ?? '') === 'GET', '3023 查询必须保留配置的请求方法');
$assert(str_contains((string)($pathRequest['url'] ?? ''), '/apple/coverage?sn=FK1ST6G4G5QY'), '3023 序列号接口参数必须使用映射的 sn');
$assert(in_array('key: demo-api-key', $pathRequest['headers'] ?? [], true), '3023 API Key 必须写入 Header key');

$imeiRequest = $pathProvider->requestConfig([
    'base_url' => 'https://api.3023data.com',
    'auth_type' => 'header',
    'auth_key' => 'key',
    'token' => 'demo-api-key',
], [
    'endpoint_value' => '/imei/model',
    'query_param' => 'imei',
], '359167074097936', 'imei');
$assert(str_contains((string)($imeiRequest['url'] ?? ''), '/imei/model?imei=359167074097936'), '3023 IMEI 接口参数必须使用映射的 imei');

$response = $provider->normalizeResponse([
    'code' => 200,
    'data' => ['sn' => 'FK1ST6G4G5QY', 'imei2' => '354999072799096'],
]);
$assert(!empty($response['success']), '爱查 code=200 应识别为成功');
$assert(($response['data']['sn'] ?? '') === 'FK1ST6G4G5QY', '爱查响应数据未透传到标准结果');

$signatureError = $provider->normalizeResponse([
    'code' => 400100,
    'message' => '签名错误',
]);
$assert(empty($signatureError['success']), '爱查签名错误不应识别为成功');
$assert(str_contains((string)($signatureError['message'] ?? ''), 'AppID 与 Secret'), '爱查签名错误应给出可操作的账号配置提示');

$unrecognizedCode = $provider->normalizeResponse([
    'code' => 500001,
    'message' => '查询失败-接口返回错误：',
]);
$assert(empty($unrecognizedCode['success']), '爱查 500001 不应识别为成功');
$assert(str_contains((string)($unrecognizedCode['message'] ?? ''), '未能识别'), '爱查 500001 应给出号码核对提示');

$catalog = new DeviceQueryCatalogService();
$gkdtMapping = null;
foreach ($catalog->defaultMappings() as $mapping) {
    if (($mapping['channel_key'] ?? '') === 'gkdt_main' && ($mapping['service_code'] ?? '') === 'apple_imei2') {
        $gkdtMapping = $mapping;
        break;
    }
}
$assert(is_array($gkdtMapping), '爱查 10109 苹果IMEI2服务未注册');
$assert(($gkdtMapping['endpoint_value'] ?? '') === '10109', '苹果IMEI2服务 ID 错误');
$assert(($gkdtMapping['query_param'] ?? '') === 'code', '爱查映射参数必须为 code');

$gkdtPresets = $catalog->providerServicePresets()['gkdt_main'] ?? [];
$assert(count(GkdtDeviceQueryServiceDict::SERVICES) === 59, '爱查默认服务目录必须包含 59 项');
$assert(count($gkdtPresets) === 59, '爱查恢复默认预设必须包含 59 项');
$presetByEndpoint = [];
foreach ($gkdtPresets as $preset) {
    $endpoint = (string)($preset['mapping']['endpoint_value'] ?? '');
    if ($endpoint !== '') {
        $presetByEndpoint[$endpoint] = $preset;
    }
}
$assert(($presetByEndpoint['10101']['mapping']['cost_price'] ?? null) === 0.07, '爱查 10101 成本错误');
$assert(($presetByEndpoint['10125']['mapping']['cost_price'] ?? null) === 3.0, '爱查 10125 成本错误');
$assert(($presetByEndpoint['90902']['service']['query_type'] ?? '') === 'barcode', '条形码查询号码类型错误');
$assert(($presetByEndpoint['90903']['service']['query_type'] ?? '') === 'ip', 'IP 查询号码类型错误');
$assert(($presetByEndpoint['90904']['service']['query_type'] ?? '') === 'phone', '号码归属地查询号码类型错误');
$assert(($presetByEndpoint['90905']['service']['query_type'] ?? '') === 'sn', '大疆保修查询号码类型错误');

$normalized = (new DeviceQueryNormalizer())->normalize('apple_activationlock', [
    'sn' => '359167074097936',
    'model' => 'iPhone 7',
    'locked' => false,
]);
$assert(array_key_exists('locked', $normalized), '激活锁 locked 字段未进入标准结果');
$assert($normalized['activation_lock'] === false, '激活锁状态标准化错误');

echo "device_query_provider_contract_smoke: OK\n";
