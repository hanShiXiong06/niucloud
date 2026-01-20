<?php

namespace addon\kd_api\app\dict\order;

class OrderDict
{
    const PAY=1;//已经支付
    const OVER=2;//已经完成
    const REFUND=3;//已经取消
    const WAIT=0;//待支付
    public static  function getStatusDict($status='')
    {
        $data=[
            self::WAIT=>'待支付',
            self::PAY=>'已支付',
            self::OVER=>'已完成',
            self::REFUND=>'已取消',
        ];
        if($status!=''){
            return $data[$status]??'';
        }
        return $data;
    }
}