<?php
declare(strict_types=1);

$jobFile = dirname(__DIR__) . '/app/job/schedule/DomainEventRetry.php';
$source = (string)file_get_contents($jobFile);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$assert(
    str_contains($source, 'function doJob(array $params = []): string'),
    '跨插件事件补偿 Job 必须返回可写入调度日志的标量文本'
);
$assert(
    str_contains($source, "return sprintf("),
    '跨插件事件补偿结果必须格式化为可读日志'
);
$assert(
    !str_contains($source, "return ['scanned' => 0"),
    '异常不得伪装成正常返回，否则调度器会误记为成功'
);
$assert(
    str_contains($source, 'throw $e;'),
    '业务异常必须交给调度器记录失败状态'
);

echo "[PASS] ERP domain event retry schedule contract smoke test\n";
