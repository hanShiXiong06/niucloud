<?php
declare(strict_types=1);

use addon\hsx_phone_query\app\dict\HsxPhoneQueryResultDict;
use addon\hsx_phone_query\app\service\core\report\QueryResultFormatter;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/dict/HsxPhoneQueryResultDict.php';
require_once dirname(__DIR__) . '/app/service/core/report/QueryResultFormatter.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$checks = 0;
$assertSame = static function ($expected, $actual, string $message) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
    ++$checks;
};

$formatter = new QueryResultFormatter();
$assertSame('容量：256GB', $formatter->stringify(['capacity' => '256GB']), 'String children must not be passed to an array-only helper');
$assertSame('剩余保修天数：0，激活状态：未激活', $formatter->stringify(['days_left' => 0, 'activated' => false]), 'Zero and false children must remain visible');
$assertSame('--', $formatter->stringify(['missing' => null, 'empty' => '', 'list' => []]), 'Empty children must be skipped');
$assertSame('0：256GB，1：512GB', $formatter->stringify(['256GB', '512GB']), 'String lists must remain supported');
$assertSame('保修信息：保修状态：保修中，剩余保修天数：0', $formatter->stringify([
    'coverage' => ['status' => 'Limited Warranty', 'days-remaining' => 0],
]), 'Nested objects must retain labels and values');
$assertSame('items：model：iPhone；days_left：0，model：iPad', $formatter->stringify([
    'items' => [['model' => 'iPhone', 'days_left' => 0], ['model' => 'iPad']],
]), 'Lists of objects must use the existing dictionary formatter');

foreach ([0, '0', 0.0] as $zero) {
    $assertSame('0', $formatter->stringify($zero), 'Scalar zero must not become an empty placeholder');
}
$assertSame('未开启', $formatter->stringify(false, 'activationlock.locked'), 'Boolean status translation must be preserved');

$context = ['type_name' => '测试查询', 'create_time' => '2026-09-27 12:00:00'];
$info = [
    'model' => ['name' => 'iPhone', 'capacity' => '256GB'],
    'capacity' => ['primary' => '256GB'],
    'color' => ['name' => '黑色'],
    'coverage' => ['status' => 'Limited Warranty', 'days-remaining' => 0],
    'activationlock' => ['locked' => false, 'lost' => false],
    'simlock' => ['carrier' => 'China', 'locked' => false],
    'image' => 'https://example.com/device.png',
    'details' => [['model' => 'iPhone', 'capacity' => '256GB'], 'extra', false, 0, null, []],
];
$original = $info;
$report = $formatter->format($info, $context);
$assertSame($original, $info, 'Formatting must not mutate the original report');
$assertSame('name：iPhone，容量：256GB', $report['title'], 'Object-valued titles must render');
$assertSame('primary：256GB / name：黑色', $report['subtitle'], 'Object-valued capacity and color must render');
$assertSame('https://example.com/device.png', $report['image'], 'Image output must remain unchanged');
$fields = array_column($report['fields'], null, 'key');
$assertSame(false, isset($fields['image']), 'Image metadata must stay hidden from detail fields');
$assertSame('0', $fields['coverage.days-remaining']['display_value'], 'Detail zero must remain visible');
$assertSame('未开启', $fields['activationlock.locked']['display_value'], 'Detail false must be translated');
$assertSame($info['details'], $fields['details']['value'], 'Raw list data must be preserved');
$assertSame(true, str_contains($fields['details']['display_value'], 'iPhone'), 'Mixed list details must render');
$tags = array_column($report['status_tags'], null, 'label');
$assertSame('运营商：China，激活锁状态：否', $tags['网络锁']['value'], 'Object-valued status tags must render');
$summary = array_column($report['summary'], 'value', 'label');
$assertSame('primary：256GB', $summary['容量'], 'Object-valued summaries must render');
$assertSame(true, $report['summary_text'] !== '', 'Summary text must be produced');
$assertSame($report, $formatter->format(json_encode($info, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $context), 'JSON and decoded reports must match');

foreach (HsxPhoneQueryResultDict::summaryPaths() as $path) {
    $data = [];
    $cursor = &$data;
    foreach (explode('.', $path) as $segment) {
        $cursor[$segment] = [];
        $cursor = &$cursor[$segment];
    }
    $cursor = ['description' => 'test', 'count' => 0, 'flag' => false, 'missing' => null];
    unset($cursor);
    $output = $formatter->format($data);
    $assertSame(true, count($output['summary']) > 0, 'Array-valued summary path must render: ' . $path);
}

$emptyReport = $formatter->format([], $context);
foreach ([null, '', 'invalid json', '"text"', '0', 'null', '{}', '[]', false, 123] as $empty) {
    $assertSame($emptyReport, $formatter->format($empty, $context), 'Invalid or empty top-level data must keep the existing fallback');
}

$flat = $formatter->format(['model' => 'iPhone', 'capacity' => '256GB', 'color' => '黑色', 'locked' => false], $context);
$assertSame('iPhone', $flat['title'], 'Flat report title must remain unchanged');
$assertSame('256GB / 黑色', $flat['subtitle'], 'Flat report subtitle must remain unchanged');
$assertSame('未开启', $flat['status_tags'][0]['value'], 'Flat report status must remain unchanged');

restore_error_handler();
echo "hsx_phone_query query_result_formatter: {$checks} checks passed; no database or external requests.\n";
