<?php
declare(strict_types=1);

// 只读部署核对：不初始化 ThinkPHP App，不联网，不操作数据库、上传或队列。
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$usage = static function (): void {
    echo "报价图片运行版本检查（不联网、不读写数据库、不上传、不启动队列）\n";
    echo "用法：php addon/hsx_recycle/scripts/check_quote_image_runtime.php [--expected-sha256=文件SHA256]\n";
    echo "默认检查实际加载文件，并在内存生成2000×15000的PNG验证长图；没有写入业务文件。\n";
    echo "建议用队列相同的PHP绝对路径运行，可加 -d opcache.enable_cli=0 排除本次CLI的OPcache。\n";
    echo "本检查不能证明已驻留worker使用相同版本；仍须以新任务ID及任务内版本标记核对。\n";
};
if (in_array('--help', $argv, true)) { $usage(); exit(0); }
$expectedHash = null;
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '--expected-sha256=') !== 0) {
        fwrite(STDERR, "[失败] 未识别的参数，请用 --help 查看用法。\n"); exit(2);
    }
    $candidate = substr($argument, strlen('--expected-sha256='));
    if ($expectedHash !== null || !preg_match('/^[a-f0-9]{64}$/iD', $candidate)) {
        fwrite(STDERR, "[失败] --expected-sha256 必须是一个64位十六进制SHA256。\n"); exit(2);
    }
    $expectedHash = strtolower($candidate);
}

// 先打印解释器信息；即使Composer加载失败，也能知道究竟用了哪个PHP。
echo "报价图片运行版本检查（只读、本地fixture、不初始化App）\n";
echo 'PHP_VERSION: ' . PHP_VERSION . "\n";
echo 'PHP_BINARY: ' . PHP_BINARY . "\n";
echo 'php.ini: ' . (php_ini_loaded_file() ?: '未加载') . "\n";
echo 'opcache.enable_cli: ' . (ini_get('opcache.enable_cli') === false ? '不可用' : (string)ini_get('opcache.enable_cli')) . "\n";
echo 'opcache.validate_timestamps: ' . (ini_get('opcache.validate_timestamps') === false ? '不可用' : (string)ini_get('opcache.validate_timestamps')) . "\n";
echo 'memory_limit: ' . (string)ini_get('memory_limit') . "\n";

$safeMessage = static function (string $message): string {
    $message = preg_replace('~https?://[^\s]+~iu', '[地址已隐藏]', $message) ?? '诊断异常';
    $message = preg_replace('/\b(password|passwd|secret|token|authorization|access[_-]?key|api[_-]?key)\s*[:=]\s*[^\s,;]+/iu', '$1=[已隐藏]', $message) ?? '诊断异常';
    $message = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $message) ?? '诊断异常';
    return preg_match('/^.{0,300}/us', $message, $match) ? $match[0] : '诊断异常';
};
$deprecations = 0;
set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
    if (!(error_reporting() & $severity)) return false;
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) { $deprecations++; return true; }
    throw new ErrorException($message, 0, $severity);
});
$failures = [];
try {
    $root = dirname(__DIR__, 3);
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('找不到 vendor/autoload.php，请把脚本放到对应插件的scripts目录。');
    require $autoload;
    $class = 'addon\\hsx_recycle\\app\\service\\core\\quote_image\\HuishouBaojiaSource';
    if (!class_exists($class)) throw new RuntimeException('找不到报价来源类，请先上传完整插件后端。');
    $reflection = new ReflectionClass($class);
    $file = $reflection->getFileName();
    if (!$file || !is_file($file)) throw new RuntimeException('无法定位实际加载的报价来源类文件。');
    $hash = hash_file('sha256', $file);
    if (!is_string($hash)) throw new RuntimeException('无法读取来源类文件SHA256。');
    $revision = $reflection->hasConstant('REVISION') ? (string)$reflection->getConstant('REVISION') : '';
    $pixels = $reflection->hasConstant('MAX_IMAGE_PIXELS') ? (int)$reflection->getConstant('MAX_IMAGE_PIXELS') : 20000000;
    echo 'LOADED_FILE: ' . (realpath($file) ?: $file) . "\n";
    echo 'SHA256: ' . $hash . "\n";
    echo 'REVISION: ' . ($revision !== '' ? $revision : '缺失（旧版或不完整版本）') . "\n";
    echo 'MAX_IMAGE_PIXELS: ' . $pixels . ($reflection->hasConstant('MAX_IMAGE_PIXELS') ? '' : '（常量缺失，旧版内置2000万限制）') . "\n";
    if ($expectedHash !== null && !hash_equals($expectedHash, $hash)) {
        $failures[] = '磁盘来源文件SHA256与指定版本不一致。';
        echo "[失败] SHA256与指定版本不一致。\n";
    } elseif ($expectedHash !== null) echo "[通过] SHA256与指定版本一致。\n";
    if ($revision !== '20261028-longpng-v2') $failures[] = 'REVISION不是20261028-longpng-v2，请核对实际覆盖的文件。';
    if ($pixels < 60000000) $failures[] = '当前来源类仍未启用6000万像素长PNG支持。';

    foreach (['deflate_init', 'deflate_add'] as $function) {
        if (!function_exists($function)) throw new RuntimeException('当前PHP缺少zlib，无法生成只读PNG测试样本。');
    }
    $chunk = static fn(string $type, string $data): string => pack('N', strlen($data)) . $type . $data . hash('crc32b', $type . $data, true);
    $stream = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 6]);
    if ($stream === false) throw new RuntimeException('无法初始化PNG测试样本压缩流。');
    $compressed = ''; $row = "\0" . str_repeat("\xff", 2000 * 3);
    for ($i = 0; $i < 15000; $i++) {
        $part = deflate_add($stream, $row, ZLIB_NO_FLUSH);
        if ($part === false) throw new RuntimeException('PNG测试样本压缩失败。');
        $compressed .= $part;
    }
    $part = deflate_add($stream, '', ZLIB_FINISH);
    if ($part === false) throw new RuntimeException('PNG测试样本压缩未完整结束。');
    $compressed .= $part;
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 2000, 15000, 8, 2, 0, 0, 0))
        . $chunk('IDAT', $compressed) . $chunk('IEND', '');
    unset($stream, $compressed, $row, $part);
    echo 'FIXTURE: 2000×15000 / 30000000像素 / ' . strlen($png) . "字节\n";
    // 对未经识别的中间版本，不冒险让30MP样本进入整图GD解码。
    if ($pixels >= 30000000 && !$reflection->hasMethod('validatePng')) {
        throw new RuntimeException('该版本虽放宽像素，却没有PNG流式校验；为避免GD耗尽内存，未执行30MP校验。');
    }
    try {
        $result = $class::validateImage('https://cos.huishoubaojiadan.com/quotation/runtime-check.png', $png, 'image/png');
        if (($result['width'] ?? 0) !== 2000 || ($result['height'] ?? 0) !== 15000 || ($result['bytes'] ?? '') !== $png) {
            throw new RuntimeException('长PNG校验返回数据或原始字节不一致。');
        }
        echo "[通过] 3000万像素长PNG校验成功，原图字节保持不变。\n";
    } catch (Throwable $exception) {
        $failures[] = '长PNG校验失败：' . $safeMessage($exception->getMessage());
        echo '[失败] 长PNG校验失败：' . $safeMessage($exception->getMessage()) . "\n";
    }
} catch (Throwable $exception) {
    $failures[] = $safeMessage($exception->getMessage());
    echo '[失败] ' . $safeMessage($exception->getMessage()) . "\n";
} finally {
    restore_error_handler();
}
if ($deprecations) echo '[提醒] Composer加载期间出现' . $deprecations . "条弃用警告，未展开依赖路径；请同时核对PHP版本。\n";
echo 'PEAK_MEMORY_BYTES: ' . memory_get_peak_usage(true) . "\n";
echo "[边界] 本次只验证此CLI实际加载的版本，不证明已驻留worker已重载。请用新任务ID及任务内版本标记核对。\n";
if ($failures) {
    foreach (array_unique($failures) as $failure) echo '[需处理] ' . $failure . "\n";
    echo "[结果] 检查不通过，未操作数据库、队列或云存储。\n";
    exit(1);
}
echo "[结果] 版本、长图本地校验通过；未联网、未操作数据库、队列或云存储。\n";
exit(0);
