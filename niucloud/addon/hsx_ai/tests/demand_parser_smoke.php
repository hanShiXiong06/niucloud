<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_ai\app\service\api\AiDemandService;

$reflection = new ReflectionClass(AiDemandService::class);
$service = $reflection->newInstanceWithoutConstructor();
$budgetMethod = $reflection->getMethod('budget');
$budgetMethod->setAccessible(true);
$entityMethod = $reflection->getMethod('entities');
$entityMethod->setAccessible(true);

$prompt = '预算3000元，想买苹果17 Pro Max 256G';
$budget = $budgetMethod->invoke($service, $prompt);
$entities = $entityMethod->invoke($service, $prompt);
$expectedEntities = ['brand:苹果', 'memory:256G', 'model:17 Pro Max'];

if ($budget !== [0.0, 3000.0] || array_diff($expectedEntities, $entities) !== []) {
    fwrite(STDERR, json_encode(['budget' => $budget, 'entities' => $entities], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}

if (in_array('model:30', $entities, true)) {
    fwrite(STDERR, "预算金额不能被识别成型号\n");
    exit(1);
}

echo "hsx_ai demand parser smoke passed\n";
