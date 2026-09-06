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
$assert(
    str_contains($source, 'retryFailedExternalRequests'),
    '商城付款或退款已成功但ERP消费失败时，调度任务必须补偿入站事实'
);
$assert(
    str_contains($source, '入库/支付/退款收件箱'),
    '调度日志必须明确说明正在补偿入库、付款和退款，不能只写模糊的收件箱'
);

echo "[PASS] ERP domain event retry schedule contract smoke test\n";
