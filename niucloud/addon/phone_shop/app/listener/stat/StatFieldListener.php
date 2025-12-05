<?php
declare (strict_types = 1);

namespace addon\phone_shop\app\listener\stat;

/**
 * 统计字段定义
 */
class StatFieldListener
{
    /**
     * 统计字段
     * @param $data
     * @return array
     */
    public function handle($data)
    {
         return [
             'shopOrder' => [
                 'name' => '二手商城订单',
             ],
             'shopRechargeOrder' => [
                 'name' => '二手商城会员充值',
             ],
         ];
    }
}
