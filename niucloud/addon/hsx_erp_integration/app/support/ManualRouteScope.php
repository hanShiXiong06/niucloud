<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

use InvalidArgumentException;

/** A manual connection's authority is defined by its site's adapter profile, not client config. */
final class ManualRouteScope
{
    public static function options(array $profile): array
    {
        $items=$profile['manual_scope_options']??[];
        if (!is_array($items) || count($items)>40) throw new InvalidArgumentException('厂商提供的适用范围无法识别');
        $out=[];
        foreach($items as $item) {
            $v=$item['value']??null; $l=$item['label']??null;
            if (!is_string($v) || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/D',$v) || isset($out[$v]) || !is_string($l) || !trim($l) || mb_strlen($l)>160)
                throw new InvalidArgumentException('厂商提供的适用范围无效或重复');
            $out[$v]=['value'=>$v,'label'=>trim($l)];
        }
        return array_values($out);
    }

    /** $bound=null is a new connection; '' is an existing unrestricted one (never auto-upgrade). */
    public static function resolve(array $profile,?string $bound,$requested,array $business): string
    {
        $default=(string)($profile['manual_scope']??'');
        $options=self::options($profile);$values=array_column($options,'value');
        if ($requested!==null && !is_string($requested)) throw new InvalidArgumentException('请选择有效的适用范围');
        if ($bound!==null) {
            if ($requested!==null && $requested!==$bound) throw new InvalidArgumentException('已有连接不可更换适用范围，请新建连接');
            $scope=$bound;
        } elseif ($options) {
            if (!in_array($requested,$values,true)) throw new InvalidArgumentException('请明确选择厂商提供的本次适用范围');
            $scope=$requested;
        } else $scope=$requested??$default;
        if ($scope==='' && $default==='' && !$options) return '';
        if ($scope==='' || ($scope!==$default && !in_array($scope,$values,true)) || empty($profile['manual_scope_types'])
            || !is_array($profile['manual_scope_types']) || !$business || array_diff($business,$profile['manual_scope_types']))
            throw new InvalidArgumentException('此连接与厂商当前授权范围不一致，请保留原连接并核对授权');
        return $scope;
    }

    public static function label(array $profile,string $value,string $fallback=''): string
    {
        foreach(self::options($profile) as $o) if ($o['value']===$value) return $o['label'];
        return $fallback ?: ($value!==''?'原限定范围':'');
    }
}
