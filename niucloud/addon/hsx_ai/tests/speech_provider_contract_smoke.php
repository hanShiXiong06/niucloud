<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn(string $path): string => (string)file_get_contents($path);

$interface = $read($root . '/app/service/core/AiSpeechProviderInterface.php');
foreach (['configure', 'speechToText', 'textToSpeech', 'test'] as $needle) {
    $assert(str_contains($interface, $needle), '语音Provider契约缺少：' . $needle);
}

$gateway = $read($root . '/app/service/core/AiSpeechService.php');
foreach (['BaiduSpeechService', 'TencentSpeechService', 'validateCredentials', 'latency_ms'] as $needle) {
    $assert(str_contains($gateway, $needle), '统一语音服务缺少：' . $needle);
}

$tencent = $read($root . '/app/service/core/TencentSpeechService.php');
foreach ([
    'asr.tencentcloudapi.com', 'SentenceRecognition', '2019-06-14',
    'tts.tencentcloudapi.com', 'TextToVoice', '2019-08-23',
    'TC3-HMAC-SHA256', 'X-TC-Action', 'X-TC-Timestamp', 'X-TC-Version',
    '3 * 1024 * 1024', "'Codec' => 'mp3'",
] as $needle) {
    $assert(str_contains($tencent, $needle), '腾讯云语音适配器缺少：' . $needle);
}

$baidu = $read($root . '/app/service/core/BaiduSpeechService.php');
$assert(str_contains($baidu, 'implements AiSpeechProviderInterface'), '百度语音必须实现统一Provider契约');
$assert(str_contains($baidu, '语音服务连接测试成功'), '百度语音必须支持真实试听测试');
$assert(str_contains($baidu, 'Authorization: Bearer '), '百度语音必须支持新版API Key直连鉴权');
$assert(str_contains($baidu, 'usesDirectApiKey'), '百度语音必须自动区分新旧鉴权');
$assert(!str_contains($baidu, "TOKEN_URL . '?'"), '百度OAuth密钥不能拼到URL并进入异常日志');
$assert(str_contains($baidu, 'private array $config'), '语音密钥必须保存在Provider私有状态，不能作为请求方法参数传播');
$assert(str_contains($tencent, '语音服务连接测试成功'), '腾讯云语音必须支持真实试听测试');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "hsx_ai speech provider contract smoke passed\n";
