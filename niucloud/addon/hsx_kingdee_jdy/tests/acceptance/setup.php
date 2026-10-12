<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/Environment.php';
try {
    $env = \HsxJdyAcceptance\Environment::boot(true);
    echo json_encode(['ready' => true, 'summary' => $env->summary()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['ready' => false, 'type' => get_class($e), 'file' => basename($e->getFile()), 'line' => $e->getLine()], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
