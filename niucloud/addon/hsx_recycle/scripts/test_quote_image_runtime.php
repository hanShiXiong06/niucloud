<?php
declare(strict_types=1);

// CLI诊断脚本回归：不加载App；子进程禁用网络连接函数，不访问业务数据库。
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$script = __DIR__ . '/check_quote_image_runtime.php';
$source = dirname(__DIR__) . '/app/service/core/quote_image/HuishouBaojiaSource.php';
$run = static function (array $arguments) use ($script): array {
    $command = [PHP_BINARY, '-n', '-d', 'memory_limit=64M', '-d',
        'disable_functions=curl_init,fsockopen,pfsockopen,stream_socket_client', $script, ...$arguments];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot create isolated CLI test process');
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $output . $error];
};
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    echo 'PASS ' . $label . "\n";
};
[$code, $output] = $run(['--help']);
$check($code === 0 && strpos($output, '用法') !== false && strpos($output, 'PHP_VERSION:') === false, '--help不加载Composer或长图校验');
[$code, $output] = $run(['--expected-sha256=invalid']);
$check($code === 2 && strpos($output, '64位') !== false && strpos($output, 'PHP_VERSION:') === false, '无效SHA256在bootstrap前拒绝');
[$code, $output] = $run(['--unexpected']);
$check($code === 2 && strpos($output, '未识别') !== false, '未知参数明确失败');
[$code, $output] = $run([]);
$check($code === 0 && strpos($output, 'REVISION: 20261028-longpng-v2') !== false
    && strpos($output, 'MAX_IMAGE_PIXELS: 60000000') !== false && strpos($output, '[通过] 3000万像素') !== false
    && strpos($output, 'PHP_BINARY:') !== false && strpos($output, 'LOADED_FILE:') !== false
    && strpos($output, '不证明已驻留worker已重载') !== false, '默认运行输出解释器与加载版本并通过真实30MP内存样本');
[$code, $output] = $run(['--expected-sha256=' . hash_file('sha256', $source)]);
$check($code === 0 && strpos($output, '[通过] SHA256与指定版本一致') !== false, '指定正确SHA256通过');
[$code, $output] = $run(['--expected-sha256=' . str_repeat('0', 64)]);
$check($code === 1 && strpos($output, 'SHA256与指定版本不一致') !== false && strpos($output, 'Stack trace') === false, '错误SHA256非零退出且不输出堆栈');
echo "完成：6项运行版本诊断检查；子进程禁用网络连接函数，未初始化App、操作DB或启动队列。\n";
