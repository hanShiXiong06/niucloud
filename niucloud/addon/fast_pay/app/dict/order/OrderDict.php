<?php

namespace addon\fast_pay\app\dict\order;


/**
 * 订单相关信息
 */
class OrderDict
{
    //待支付
    const WAIT_PAY = 0;
    //已支付
    const FINISH_PAY = 1;
    //已完成
    const FINISH = 10;
    //已关闭
    const CLOSE = -1;

    /**
     * 订单类型以及名称
     * @return array
     */
    public static function getOrderType()
    {
        return [
            'type' => 'fastPayOrder',
            'name' => '商家收银台'
        ];
    }
    public static function getBusinessOrderType()
    {
        return [
            'type' => 'fastPayBusinessOrder',
            'name' => '商家收银台'
        ];
    }

    public static function getOrderStatus($status='')
    {
        $data= [
            self::WAIT_PAY =>[
                'name' => '待支付',
                'status' => self::WAIT_PAY,
                'member_action' => [
                    [
                        'name' => '立即支付',
                        'class' => 'gopay',
                        'params' => ''
                    ],
                    [
                        'name' => '关闭订单',
                        'class' => 'close',
                        'params' => ''
                    ],
                ],
            ],
            self::FINISH => [
                'name' => '已完成',
                'status' => self::FINISH,
                'member_action' => [
                   
                ],
            ],
            self::CLOSE => [
                'name' => '已关闭',
                'status' => self::CLOSE,
                'member_action' => [
                    [
                        'name' => '删除订单',
                        'class' => 'del',
                        'params' => ''
                    ],
                ],
            ]
        ];
        if($status!=''){
            return $data[$status];
        }else{
            return $data;
        }
    }

}
