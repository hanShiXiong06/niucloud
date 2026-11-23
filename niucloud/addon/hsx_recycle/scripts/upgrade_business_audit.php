<?php
declare(strict_types=1);
use addon\hsx_recycle\app\service\core\audit\OperationAuditSchema;
if(PHP_SAPI!=='cli') exit(2);
$root=dirname(__DIR__,3);require $root.'/vendor/autoload.php';
$app=new think\App($root.'/');$app->initialize();
if(!in_array($argv[1]??'',['--check','--apply'],true)){fwrite(STDERR,"用法：php upgrade_business_audit.php --check 或 --apply\n");exit(2);}
$changes=OperationAuditSchema::changes();
foreach($changes as $sql) echo $sql,";\n";
if($argv[1]==='--apply') OperationAuditSchema::apply();
echo (!$changes||$argv[1]==='--apply')?"日志表结构已就绪。\n":"仅预览，尚未执行。\n";
