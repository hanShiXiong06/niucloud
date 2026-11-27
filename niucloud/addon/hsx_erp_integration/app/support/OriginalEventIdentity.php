<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

/** JSON object key order is not a fact. Only defined JSON money numbers may be
 * serialized as 1 by ERP and 1.0 by PHP; types of IDs, strings and list order stay significant. */
final class OriginalEventIdentity
{
    public static function payload(string $kind,array $payload): array
    {
        if(in_array($kind,['payment','receipt'],true)) {
            self::wholeMoney($payload,'amount');
            if(isset($payload['targets']) && is_array($payload['targets'])) {
                foreach($payload['targets'] as &$target) if(is_array($target)) {
                    foreach(['applied_amount','target_amount','settled_amount'] as $field) self::wholeMoney($target,$field);
                }
                unset($target);
            }
        }
        return self::ordered($payload);
    }

    private static function wholeMoney(array &$row,string $field): void
    {
        $v=$row[$field]??null;
        if(is_float($v) && is_finite($v) && $v>=0 && $v<=999999999999 && floor($v)===$v) $row[$field]=(int)$v;
    }

    public static function same(array $a,array $b): bool
    {
        unset($a['_delivery'],$b['_delivery']);
        if(($a['event_name']??null)!==($b['event_name']??null)) return false;
        if(($a['event_name']??'')===BridgeFact::PAYMENT && is_array($a['payload']??null) && is_array($b['payload']??null)) {
            $a['payload']=self::payload('payment',$a['payload']);
            $b['payload']=self::payload('payment',$b['payload']);
        }
        return self::ordered($a)===self::ordered($b);
    }

    private static function ordered(array $v): array
    {
        if(array_keys($v)!==array_keys(array_values($v))) ksort($v);
        foreach($v as &$item) if(is_array($item)) $item=self::ordered($item);
        return $v;
    }
}
