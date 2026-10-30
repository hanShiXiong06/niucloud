<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !in_array('--drop-isolated-database',$argv,true)) exit("Requires --drop-isolated-database --state-file=private-state.json\n");
require dirname(__DIR__).'/bootstrap.php';
$options=getopt('', ['drop-isolated-database','state-file:']); $paths=(array)($options['state-file']??[]);
foreach($paths as $path) {
    if(!preg_match('/^boyuan-(?:uat-)?acceptance-[a-f0-9]{16}\.json$/D',basename($path)) || !is_file($path)) throw new RuntimeException('Unknown acceptance state');
    $state=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    [$app,$pdo,$schema,$site]=boyuanBoot($state['schema']);
    if(\think\facade\Db::name('site')->where('site_id',$site)->value('site_name')!=='博远隔离验收') throw new RuntimeException('Not a known isolated site');
    $pdo->exec('DROP DATABASE `'.$schema.'`'); unlink($path); if(is_file($path.'.report.json')) unlink($path.'.report.json');
    echo "Removed one exact isolated acceptance schema.\n";
}
