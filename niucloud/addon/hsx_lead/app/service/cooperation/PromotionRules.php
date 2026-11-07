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
            'membership'=>['enabled'=>false,'normal_rate'=>'0.00','provider_rate'=>'0.00','level_rates'=>[],'renewal'=>false],
            'maintenance'=>['enabled'=>false,'normal_rate'=>'0.00','provider_rate'=>'0.00','level_rates'=>[],'renewal'=>true],
        ]];
    }
    public static function source(string $purpose): string
    {
        return ['commission'=>'maintenance','membership'=>'membership'][$purpose]??'';
    }
    public static function bool($value): bool
    {
        if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new CommonException('请明确设置推广开关');
        return in_array($value,[true,1,'1'],true);
    }
    /**
     * Read a known pre-membership configuration without rewriting its stored evidence.
     * Retired rates never become membership rates: the operator must explicitly review
     * and save the two current sources. All writes still use the strict config() below.
     */
    public static function readConfiguration(array $input): array
    {
        $ignored=[];
        if (is_array($input['sources']??null)) {
            foreach(['publishing','certification'] as $source) if (array_key_exists($source,$input['sources'])) {
                $ignored[]=$source;unset($input['sources'][$source]);
            }
        }
        return self::config($input)+[
            'requires_review'=>$ignored!==[],
            'review_message'=>$ignored!==[]?'检测到旧版入驻或认证分佣规则，原配置已保留，未自动换算或合并为会员服务费分佣。请核对会员服务费（不含押金、经验费）与平台维护费的比例，确认后再保存。':'',
            'ignored_source_keys'=>$ignored,
        ];
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
        if (array_diff(array_keys($input['sources']??[]),array_keys($next['sources']))) throw new CommonException('推广分佣配置与当前版本不匹配，请刷新页面后重新核对；仅支持会员服务费（不含押金、经验费）和平台维护费');
        foreach($next['sources'] as $source=>$default) {
            $row=$input['sources'][$source]??$default;if (!is_array($row)) throw new CommonException('请核对推广收入来源');
            $normal=Rules::cents($row['normal_rate']??'0.00');$provider=Rules::cents($row['provider_rate']??'0.00');
            if ($normal>10000||$provider>10000) throw new CommonException('普通会员及服务商推广比例须在0—100%之间');
            $levelRates=$row['level_rates']??[];
            if (!is_array($levelRates)||count($levelRates)>1000) throw new CommonException('等级推广比例请使用最多1000项的等级配置');
            $normalizedRates=[];
            foreach($levelRates as $code=>$value) {
                if (!MembershipCatalogService::validCode($code)) throw new CommonException('等级推广比例必须关联有效的会员等级编号');
                $rate=Rules::cents($value);if($rate>10000) throw new CommonException('等级推广比例须在0—100%之间');
                $normalizedRates[$code]=Rules::amount($rate);
            }
            $next['sources'][$source]=['enabled'=>self::bool($row['enabled']??false),'normal_rate'=>Rules::amount($normal),
                'provider_rate'=>Rules::amount($provider),'level_rates'=>$normalizedRates,
                'renewal'=>$source==='maintenance'?true:self::bool($row['renewal']??false)];
        }
        return $next;
    }
    /** Exactly one tier applies; service-provider and normal rewards never stack. */
    public static function rateFor(array $config,string $source,string $role,string $levelCode=''): string
    {
        if (!in_array($role,['normal','provider'],true)||!isset($config['sources'][$source])) throw new CommonException('推广身份或收入来源无效');
        $row=$config['sources'][$source];
        // A free member uses normal_rate; a paid entity tier may override it.
        // Provider qualification wins, never normal + tier + provider stacking.
        $value=$role==='provider'?($row['provider_rate']??'0.00'):($row['level_rates'][$levelCode]??$row['normal_rate']??'0.00');
        $rate=Rules::cents($value);
        if ($rate>10000) throw new CommonException('推广比例须在0—100%之间');
        return Rules::amount($rate);
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
