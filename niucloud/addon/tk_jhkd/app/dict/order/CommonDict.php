<?php

namespace addon\tk_jhkd\app\dict\order;

/**
 *订单公共枚举类
 */
class CommonDict
{

    /**
     * 订单发单
     * @return array
     */
    public static function getIsSend()
    {
        return [
            ['value' => 0, 'name' => '未发单'],
            ['value' => 1, 'name' => '已发单'],
        ];
    }

    /**
     * 订单揽收
     * @return array
     */
    public static function getIsPick()
    {
        return [
            ['value' => 0, 'name' => '未揽收'],
            ['value' => 1, 'name' => '已揽收'],
        ];
    }

    public static function getYidaOrderStatus($value = '')
    {
        $data = [
            1 => [
                'value' => 1,
                'name' => '待取件'
            ],
            2 => [
                'value' => 2,
                'name' => '运输中'
            ],
            3 => [
                'value' => 3,
                'name' => '已签收'
            ],
            6 => [
                'value' => 6,
                'name' => '异常'
            ],
            10 => [
                'value' => 10,
                'name' => '已取消'
            ],

        ];
        if ($value == '') {
            return $data;
        }
        return $data[$value] ?? '';
    }

}