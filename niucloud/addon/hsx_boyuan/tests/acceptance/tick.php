<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
$path=getenv('HSX_BOYUAN_ACCEPTANCE_STATE'); if (!$path || !is_file($path)) exit(1);
$state=json_decode(file_get_contents($path),true);
require dirname(__DIR__).'/bootstrap.php'; require dirname(__DIR__).'/Fixtures.php';
[$app,$pdo,$schema,$site]=boyuanBoot($state['schema']);
\addon\hsx_boyuan\tests\Fixtures::tick($site);
