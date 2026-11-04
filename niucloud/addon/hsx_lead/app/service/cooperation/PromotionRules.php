<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use core\exception\CommonException;

/** One-level promotion uses integer cents. It never reduces the publisher's cooperation income. */
final class PromotionRules
{
    public static function defaults(): array
    {
        return ['enabled'=>false,'settlement_days'=>3,'eligibility_months'=>0,'sources'=>[
            'publishing'=>['enabled'=>false,'rate'=>'0.00','renewal'=>false],
            'certification'=>['enabled'=>false,'rate'=>'0.00','renewal'=>false],
            'maintenance'=>['enabled'=>false,'rate'=>'0.00','renewal'=>true],
        ]];
    }
    public static function source(string $purpose): string
    {
        return ['commission'=>'maintenance','publishing'=>'publishing','certification'=>'certification'][$purpose]??'';
    }
    public static function bool($value): bool
    {
        if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new CommonException('请明确设置推广开关');
        return in_array($value,[true,1,'1'],true);
    }
    public static function config(array $input): array
    {
        $next=self::defaults();$next['enabled']=self::bool($input['enabled']??false);
        foreach(['settlement_days'=>[0,365],'eligibility_months'=>[0,120]] as $key=>$range) {
            $value=$input[$key]??$next[$key];
            if ((!is_int($value)&&!is_string($value))||!preg_match('/^(?:0|[1-9][0-9]{0,2})$/D',(string)$value)||(int)$value>$range[1]) throw new CommonException('推广期限设置不正确：'.$key);
            $next[$key]=(int)$value;
        }
        if (isset($input['sources'])&&!is_array($input['sources'])) throw new CommonException('推广收入规则格式不正确');
        foreach($next['sources'] as $source=>$default) {
            $row=$input['sources'][$source]??$default;if (!is_array($row)) throw new CommonException('请核对推广收入来源');
            $rate=Rules::cents($row['rate']??'0.00');if ($rate>10000) throw new CommonException('推广比例须在0—100%之间');
            $next['sources'][$source]=['enabled'=>self::bool($row['enabled']??false),'rate'=>Rules::amount($rate),'renewal'=>$source==='maintenance'?true:self::bool($row['renewal']??false)];
        }
        return $next;
    }
    public static function reward(int $base,string $rate): int
    {
        $basis=Rules::cents($rate);
        if ($base<0||$base>9999999999||$basis>10000) throw new CommonException('推广计佣金额或比例超出可处理范围');
        // Round down to a whole cent; no fractional-cent credit or float drift.
        return intdiv($base*$basis,10000);
    }
    public static function eligible(array $config,string $source,bool $renewal,int $boundAt,int $paidAt): bool
    {
        if (empty($config['enabled'])||empty($config['sources'][$source]['enabled'])) return false;
        if ($source!=='maintenance'&&$renewal&&empty($config['sources'][$source]['renewal'])) return false;
        $months=(int)($config['eligibility_months']??0);
        return $months===0||($boundAt>0&&$paidAt<Rules::addMonths($boundAt,$months));
    }
}
