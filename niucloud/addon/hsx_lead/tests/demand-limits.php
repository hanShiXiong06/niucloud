<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/vendor/autoload.php';
use addon\hsx_lead\app\service\cooperation\{Rules,ConfigService};
use core\exception\CommonException;
$checks=0;
function demandCheck(bool $condition,string $label): void { if (!$condition) throw new RuntimeException($label);$GLOBALS['checks']++; }
function demandDenied(callable $work): void { try {$work();} catch (CommonException $e) {$GLOBALS['checks']++;return;}throw new RuntimeException('Expected demand limit rejection'); }
demandCheck(ConfigService::defaults()['demand_model_limit']===5,'new sites default to five demand models');
foreach ([0,3,5,5000,'0','5'] as $value) demandCheck(Rules::demandLimit($value)===(int)$value,'valid configurable limit');
foreach ([-1,'-1',1.5,'1.5','1e2',true,null,5001,[],new stdClass] as $value) demandDenied(fn()=>Rules::demandLimit($value));
demandCheck(Rules::demandIds(['cat_a','cat_a','cat_b'],2)===['cat_a','cat_b'],'duplicates do not consume the configured limit');
demandDenied(fn()=>Rules::demandIds(['a','b','c','d'],3));
demandCheck(count(Rules::demandIds(array_map('strval',range(1,100)),0))===100,'zero means no business limit, not a hidden twenty item cap');
foreach ([[],null,'a',[2],[''],[null],['a'=> 'b'],array_fill(0,5001,'a')] as $value) demandDenied(fn()=>Rules::demandIds($value,0));
echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'pure demand limits; no database or network']),PHP_EOL;
