<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use core\exception\CommonException;

/** Integer minor units throughout; no float arithmetic in settlement calculations. */
final class Rules
{
    public static function cents($value): int
    {
        if ((!is_string($value)&&!is_int($value)) || !preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',(string)$value,$m)) throw new CommonException('金额请填写非负数字，最多两位小数');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }
    public static function amount(int $cents): string
    {
        if ($cents<0) return '-'.self::amount(-$cents);
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }
    public static function split(int $gross,array $config): array
    {
        if ($gross<0 || $gross>99999999999999) throw new CommonException('金额超出系统可处理的精度范围');
        $minimum=max(5000,self::cents($config['commission_minimum']??'50.00'));
        if ($gross<$minimum) throw new CommonException('成交后协作佣金最低为'.self::amount($minimum).'元');
        if (empty($config['fee_confirmed'])) throw new CommonException('平台尚未确认收费规则，暂不能创建付佣订单，请联系平台');
        $basis=self::cents((string)($config['fee_percent']??'10.00'));
        if ($basis>10000) throw new CommonException('平台费比例无效');
        $fee=max(self::cents($config['fee_minimum']??'9.90'),intdiv($gross*$basis+5000,10000));
        if ($fee>=$gross) throw new CommonException('平台费须低于本次佣金，确保发布方有可得收益，请联系平台核对规则');
        return ['amount'=>self::amount($gross),'fee_amount'=>self::amount($fee),'net_amount'=>self::amount($gross-$fee)];
    }
    public static function addMonths(int $timestamp,int $months=1): int
    {
        if ($months<1 || $months>120) throw new CommonException('有效期月份应为1—120');
        $at=(new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $first=$at->modify('first day of this month')->modify('+'.$months.' months');
        return $first->setDate((int)$first->format('Y'),(int)$first->format('m'),min((int)$at->format('d'),(int)$first->format('t')))->getTimestamp();
    }
    public static function version(array $row,array $input): void
    {
        $value=$input['assignment_version']??null;
        if ((!is_int($value)&&!is_string($value)) || !preg_match('/^[0-9]{1,10}$/D',(string)$value) || (int)$value!==(int)$row['assignment_version']) throw new CommonException('交付对象或状态已变化，请刷新详情后操作');
    }
    public static function status(string $status): string
    {
        return ['selecting'=>'待选门店','assigned'=>'待接单','following'=>'跟进中','unpaid'=>'成交待付佣','paid'=>'已付佣','no_deal'=>'未成交','cancelled'=>'已结束'][$status]??'待核对';
    }
}
