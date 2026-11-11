<?php
declare(strict_types=1);
namespace addon\hsx_lead\scripts;

use addon\hsx_lead\app\service\virtualpay\VirtualPaySchema;
use RuntimeException;
use Throwable;

if(!defined('HSX_LEAD_VIRTUAL_PAYMENT_UPGRADE_LIBRARY_ONLY')){
    if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80000)exit(1);
    if(count($argv)!==2||!in_array($argv[1],['--check','--apply'],true)){fwrite(STDERR,"php addon/hsx_lead/scripts/upgrade-virtual-payment.php --check|--apply\n");exit(2);}
    define('HSX_LEAD_UPGRADE_LIBRARY_ONLY',true);require __DIR__.'/upgrade.php';
    try{$backend=dirname(__DIR__,3);bootstrap($backend);$schema=new VirtualPaySchema($backend);echo json_encode($argv[1]==='--check'?$schema->check():$schema->apply(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;}
    catch(Throwable $error){fwrite(STDERR,'[未确认完成] '.($error instanceof RuntimeException?$error->getMessage():'虚拟支付结构检查失败，连接信息已隐藏')."\nDDL逐表提交，失败后重新--check；勿卸载或重装插件。\n");exit(1);}
}
