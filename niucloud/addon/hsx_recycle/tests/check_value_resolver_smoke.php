<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;

$assertSame = static function (string $expected, string $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "[FAIL] {$message}: expected={$expected}, actual={$actual}\n");
        exit(1);
    }
};

$labels = ['1' => '256GB', '2' => '512GB', '9' => '深空黑色'];

$assertSame('256GB、512GB', DeviceSummaryHelper::resolveDisplayValue('["1","2"]', $labels), 'JSON多选值应解析为选项文案');
$assertSame('深空黑色', DeviceSummaryHelper::resolveDisplayValue(['id' => 9], $labels), 'option id 应兼容解析');
$assertSame('自定义成色', DeviceSummaryHelper::resolveDisplayValue(['value' => 'x', 'label' => '自定义成色'], $labels), '对象值应优先使用自带 label');
$assertSame('是', DeviceSummaryHelper::resolveDisplayValue(true, []), '布尔值应转换为可读文案');
$assertSame('未知值', DeviceSummaryHelper::resolveDisplayValue('未知值', $labels), '未命中选项时应保留原值');

echo "[PASS] recycle check value resolver smoke test\n";
