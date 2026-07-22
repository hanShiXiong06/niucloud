<?php
declare(strict_types=1);

use addon\hsx_phone_query\app\dict\HsxPhoneQueryCategoryDict;
use addon\hsx_phone_query\app\dict\HsxPhoneQueryConfigDict;
use addon\hsx_phone_query\app\service\core\provider\GkdtPhoneQueryProvider;
use addon\hsx_phone_query\app\service\core\provider\PathPhoneQueryProvider;

$root = dirname(__DIR__, 3);
require_once $root . '/addon/hsx_phone_query/app/service/core/provider/contract/PhoneQueryProviderInterface.php';
require_once $root . '/addon/hsx_phone_query/app/service/core/provider/AbstractPhoneQueryProvider.php';
require_once $root . '/addon/hsx_phone_query/app/service/core/provider/GkdtPhoneQueryProvider.php';
require_once $root . '/addon/hsx_phone_query/app/service/core/provider/PathPhoneQueryProvider.php';
require_once $root . '/addon/hsx_phone_query/app/dict/HsxPhoneQueryCategoryDict.php';
require_once $root . '/addon/hsx_phone_query/app/dict/HsxPhoneQueryConfigDict.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$gkdt = new GkdtPhoneQueryProvider();
$gkdtRequest = $gkdt->buildRequest([
    'appid' => 'demo-app',
    'secret' => 'demo-secret',
    'style' => '11',
    'service_id_key' => 'key',
], ['endpoint_value' => '10101'], '359167074097936', 1784563200);

$assert(($gkdtRequest['params']['code'] ?? '') === '359167074097936', '爱查查询号码必须使用 code');
$assert(($gkdtRequest['params']['key'] ?? '') === '10101', '爱查服务 ID 必须使用 key');
$assert(($gkdtRequest['params']['sign'] ?? '') === 'c07d7579f01f2f29c50ec62cafcb85c7', '爱查签名不符合协议');
$assert(($gkdtRequest['log_params']['sign'] ?? '') === '***', '日志不得保存爱查签名');

$path = new PathPhoneQueryProvider();
$serialRequest = $path->buildRequest([
    'base_url' => 'https://api.3023data.com',
    'auth_type' => 'header',
    'auth_key' => 'key',
    'token' => 'demo-api-key',
], ['endpoint_value' => '/apple/coverage', 'query_param' => 'sn'], 'FK1ST6G4G5QY');
$assert(($serialRequest['params']['sn'] ?? '') === 'FK1ST6G4G5QY', '3023 序列号接口参数错误');
$assert(in_array('key: demo-api-key', $serialRequest['headers'] ?? [], true), '3023 API Key 未写入 Header');

$imeiRequest = $path->buildRequest([
    'base_url' => 'https://api.3023data.com',
    'token' => 'demo-api-key',
], ['endpoint_value' => '/imei/model'], '359167074097936');
$assert(($imeiRequest['params']['imei'] ?? '') === '359167074097936', '3023 /imei 接口应自动使用 imei');

$assert(HsxPhoneQueryCategoryDict::infer3023QueryParam('/item/barcode') === 'barcode', '条码接口参数推断错误');
$assert(HsxPhoneQueryCategoryDict::infer3023QueryParam('/ip/location') === 'ip', 'IP 接口参数推断错误');
$assert(HsxPhoneQueryCategoryDict::infer3023QueryParam('/phone/location') === 'phone', '手机号接口参数推断错误');

$config = HsxPhoneQueryConfigDict::normalizeConfig([
    'channels' => [[
        'key' => 'gkdt_main',
        'name' => '爱查助手',
        'provider' => 'service_id_query',
        'appid' => 'demo-app',
        'secret' => 'demo-secret',
    ]],
]);
$gkdtChannel = array_values(array_filter($config['channels'], static fn(array $channel) => $channel['key'] === 'gkdt_main'))[0] ?? [];
$assert(($gkdtChannel['provider'] ?? '') === 'gkdt_query', '旧爱查渠道应平滑迁移到新适配器');

$imeiMapping = array_values(array_filter(
    $config['mappings'],
    static fn(array $mapping) => $mapping['channel_key'] === '3023_main' && $mapping['service_code'] === 'imei_model'
))[0] ?? [];
$assert(($imeiMapping['query_param'] ?? '') === 'imei', '默认 3023 IMEI 映射未修正');

echo "hsx_phone_query provider_contract_smoke: OK\n";
