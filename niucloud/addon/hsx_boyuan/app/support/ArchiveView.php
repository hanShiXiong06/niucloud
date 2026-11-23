<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

/** Presentation snapshot, never an order payload. A field is shown even when its value is protected. */
final class ArchiveView
{
    public const SNAPSHOT='_boyuan_view_cipher';
    // Stored verbatim in the local party archive, not copied to selector extras or business documents.
    public const PARTY_PHONE='_boyuan_help_code';

    public static function safe(string $kind,array $row): array
    {
        $walk=function(array $data) use(&$walk,$kind): array {
            $out=[];
            $help=$kind==='parties'?($data['help_code']??null):null;
            $digitsOf=static fn($v)=>is_string($v)||is_int($v)?str_replace(['+','(',')',' ','-'],'',trim((string)$v)):'';
            $agreed=$digitsOf($help);
            foreach($data as $rawKey=>$value) {
                $key=is_string($rawKey)?trim($rawKey):$rawKey;
                $k=strtolower(preg_replace('/([a-z])([A-Z])/','$1_$2',(string)$key));
                if($value===null || $value==='') $out[$key]=$value;
                elseif(preg_match('/secret|password|passwd|token|cipher|authorization|(?:private|access|api|app|client|sign)_?key|app_?sec|credential|signature|^sign$|^key$|密码|密钥/i',$k)) $out[$key]='（凭据不展示）';
                elseif(preg_match('/id_?card|identity_?(card|num)|certificate_?no|account_?(no|num)|bank_?(account|card)|card_?(no|number)|iban|routing_?num|身份证|证件号|银行卡/',$k)) $out[$key]='（敏感号码已隐藏）';
                elseif(($kind==='parties' && ($k==='help_code' || (in_array($k,['code','spell_code'],true) && ctype_digit($agreed) && $digitsOf($value)===$agreed))) || preg_match('/phone|mobile|telephone|手机号|电话/',$k)) {
                    $val=is_scalar($value)?(string)$value:''; $digits=preg_replace('/\D/','',$val);
                    $out[$key]=preg_match('/^[0-9]{3}\*{4}[0-9]{4}$/D',$val)?$val:(strlen($digits)>=8?substr($digits,0,3).'****'.substr($digits,-4):'（原值已隐藏）');
                } elseif(preg_match('/(^|_)(email|weixin|qq|wx_id|open_?id|union_?id)($|_)|邮箱/',$k)
                    || (in_array($kind,['parties','staff','members'],true) && preg_match('/address|详细地址/',$k))) $out[$key]='（个人资料已隐藏）';
                elseif(is_array($value)) $out[$key]=$walk($value);
                elseif(is_string($value)) $out[$key]=preg_replace('/(?<![0-9])(1[3-9][0-9])[0-9]{4}([0-9]{4})(?![0-9])/','$1****$2',$value);
                else $out[$key]=$value;
            }
            return $out;
        };
        return $walk($row);
    }
    public static function extras(string $json): array
    {
        return self::publicExtras(Contract::decode($json));
    }
    public static function publicExtras(array $e): array {unset($e[self::SNAPSHOT],$e[self::PARTY_PHONE]);return $e;}
    public static function context(string $key,string $kind,string $id): string {return $key.':archive:'.$kind.':'.$id;}
}
