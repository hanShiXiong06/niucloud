<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/service/core/AiConfigService.php';

use addon\hsx_ai\app\service\core\AiConfigService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$service = new AiConfigService();
$reflection = new ReflectionClass($service);
$normalize = $reflection->getMethod('normalize');
$validate = $reflection->getMethod('validate');
$preserveSecrets = $reflection->getMethod('preserveProviderSecrets');
$normalizeProvider = $reflection->getMethod('normalizeProvider');
$normalizeSpeech = $reflection->getMethod('normalizeSpeech');
$preserveSpeech = $reflection->getMethod('preserveSpeechSecrets');
$normalize->setAccessible(true);
$validate->setAccessible(true);
$preserveSecrets->setAccessible(true);
$normalizeProvider->setAccessible(true);
$normalizeSpeech->setAccessible(true);
$preserveSpeech->setAccessible(true);

$default = $normalize->invoke($service, []);
$assert(($default['providers'][0]['id'] ?? '') === 'yunwu', '默认通道必须是 yunwu');
$assert(($default['scenes'][0]['key'] ?? '') === 'general', '必须生成 general 场景');
$assert((int)($default['enabled'] ?? 1) === 0, 'AI 默认必须停用');
$assert(($default['integrations'] ?? null) === [], '业务插件通信锁默认必须为空且关闭');
$assert((int)($default['speech']['auto_read_default'] ?? 1) === 0, '前台自动朗读必须默认关闭以控制语音合成费用');

$withIntegrations = $normalize->invoke($service, [
    'integrations' => [
        ['key' => 'phone-shop', 'enabled' => 1, 'name' => '不应持久化的展示字段'],
        ['key' => 'hsx_erp', 'enabled' => 0],
    ],
]);
$assert(($withIntegrations['integrations'][0] ?? []) === ['key' => 'phone_shop', 'enabled' => 1], '业务接入配置只允许持久化规范化标识和开关');
$assert(($withIntegrations['integrations'][1] ?? []) === ['key' => 'hsx_erp', 'enabled' => 0], '关闭状态必须被准确保存');

$preserved = $preserveSecrets->invoke($service, [
    ['id' => 'yunwu', 'api_key' => AiConfigService::SECRET_MASK],
], [
    'yunwu' => ['api_key' => 'real-token'],
]);
$assert(($preserved[0]['api_key'] ?? '') === 'real-token', '保存掩码时必须保留数据库中的真实密钥');
$corrupted = $preserveSecrets->invoke($service, [
    ['id' => 'yunwu', 'api_key' => AiConfigService::SECRET_MASK],
], [
    'yunwu' => ['api_key' => AiConfigService::SECRET_MASK],
]);
$assert(($corrupted[0]['api_key'] ?? 'invalid') === '', '数据库已被掩码污染时必须要求重新填写密钥');
$normalizedProvider = $normalizeProvider->invoke($service, [
    'id' => 'yunwu', 'base_url' => 'https://yunwu.ai', 'api_key' => '"Bearer real-token"',
], 0);
$assert(($normalizedProvider['api_key'] ?? '') === 'real-token', '密钥必须清理引号和 Bearer 前缀');

$tencentSpeech = $normalizeSpeech->invoke($service, [
    'enabled' => 1,
    'provider' => 'tencent',
    'secret_id' => 'secret-id',
    'secret_key' => 'secret-key',
    'region' => '',
    'tencent_voice' => 1002,
    'tencent_speed' => 99,
    'auto_read_default' => 1,
]);
$assert(($tencentSpeech['provider'] ?? '') === 'tencent', '语音配置必须支持腾讯云');
$assert(($tencentSpeech['region'] ?? '') === 'ap-shanghai', '腾讯云地域必须提供稳定默认值');
$assert((float)($tencentSpeech['tencent_speed'] ?? 0) === 6.0, '腾讯云语速必须限制在接口允许范围');
$assert((int)($tencentSpeech['auto_read_default'] ?? 0) === 1, '语音配置必须保存前台自动朗读默认值');
$switchedSpeech = $preserveSpeech->invoke($service, [
    'provider' => 'tencent', 'secret_id' => '', 'secret_key' => '',
], [
    'provider' => 'baidu', 'api_key' => 'baidu-key', 'secret_key' => 'baidu-secret',
]);
$assert(($switchedSpeech['secret_key'] ?? 'invalid') === '', '切换语音服务商时不能复用另一家SecretKey');
$assert(($switchedSpeech['api_key'] ?? 'invalid') === '', '腾讯云配置不能残留百度API Key');

$directBaidu = $default;
$directBaidu['speech'] = $normalizeSpeech->invoke($service, [
    'enabled' => 1,
    'provider' => 'baidu',
    'api_key' => 'bce-v3/example-key',
    'secret_key' => '',
]);
try {
    $validate->invoke($service, $directBaidu);
} catch (Throwable $e) {
    $failures[] = '百度新版API Key直连不应要求Secret Key：' . $e->getMessage();
}
$assert(AiConfigService::isBaiduDirectApiKey('bce-v3/example-key'), '必须识别百度新版bce-v3 API Key');

$invalid = $default;
$invalid['enabled'] = 1;
$missingSecretRejected = false;
try {
    $validate->invoke($service, $invalid);
} catch (Throwable $e) {
    $missingSecretRejected = str_contains($e->getMessage(), 'API Key');
}
$assert($missingSecretRejected, '启用 AI 时必须拒绝缺少 API Key 的通道');

$valid = $default;
$valid['enabled'] = 1;
$valid['default_model'] = 'demo-model';
$valid['providers'][0]['api_key'] = 'test-key';
$valid['providers'][0]['default_model'] = 'demo-model';
$valid['providers'][0]['models'] = [[
    'id' => 'demo-model',
    'name' => 'Demo Model',
    'owned_by' => 'test',
    'enabled' => 1,
]];
$valid['scenes'][0]['provider_id'] = 'yunwu';
$valid['scenes'][0]['model'] = 'demo-model';
try {
    $validate->invoke($service, $valid);
} catch (Throwable $e) {
    $failures[] = '有效配置不应被拒绝：' . $e->getMessage();
}

$invalidModel = $valid;
$invalidModel['scenes'][0]['model'] = 'missing-model';
$missingModelRejected = false;
try {
    $validate->invoke($service, $invalidModel);
} catch (Throwable $e) {
    $missingModelRejected = str_contains($e->getMessage(), '不存在或已停用');
}
$assert($missingModelRejected, '必须拒绝场景引用不存在的模型');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai config contract smoke passed\n";
