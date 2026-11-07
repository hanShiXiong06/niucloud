<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

/** PHP call-stack capability, not a request flag, database field or channel
 * reason string. Only a verified close operation may scope framework payClose.
 */
final class PaymentCloseScope
{
    private static array $active=[];
    public static function run(int $site,string $no,callable $operation)
    {
        if($site<=0||$no==='')throw new \InvalidArgumentException('Missing trusted payment close scope');
        $key=$site.':'.$no;self::$active[$key]=(self::$active[$key]??0)+1;
        try{return$operation();}finally{if(--self::$active[$key]===0)unset(self::$active[$key]);}
    }
    public static function allowed(int $site,string $no): bool{return$site>0&&$no!==''&&(self::$active[$site.':'.$no]??0)>0;}
}
