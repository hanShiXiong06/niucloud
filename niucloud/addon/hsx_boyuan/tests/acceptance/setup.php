<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true)) exit(1);
require dirname(__DIR__).'/bootstrap.php'; require dirname(__DIR__).'/Fixtures.php';
[$app,$pdo,$schema,$site]=boyuanBoot();
try {
    \addon\hsx_boyuan\tests\Fixtures::seed($site);
    $path=sys_get_temp_dir().'/boyuan-acceptance-'.bin2hex(random_bytes(8)).'.json';
    file_put_contents($path,json_encode(['schema'=>$schema,'key'=>bin2hex(random_bytes(32))])); chmod($path,0600);
    echo $path."\n";
} catch(Throwable $e) { $pdo->exec('DROP DATABASE `'.$schema.'`'); fwrite(STDERR,'Setup failed: '.get_class($e).' '.basename($e->getFile()).':'.$e->getLine()."\n"); exit(1); }
