<?php

namespace addon\tk_jhkd\app\dict\order;

use app\dict\pay\PayDict;

/**
 *佣金状态枚举类
 */
class CommissionStatusDict
{
    const WAIT_JS=0;
    const FINISH_JS=1;
    const CLOSE=-1;
    public static function getStatus($status = '')
    {
        $data = [
            self::WAIT_JS => [
                'name' => '待结算',
                'status' => self::WAIT_JS,
                'type'=>'info'
            ],
            self::FINISH_JS => [
                'name' => '已结算',
                'status' => self::FINISH_JS,
                'type'=>'success'
            ],
            self::CLOSE => [
                'name' => '已关闭',
                'status' => self::CLOSE,
                'type'=>'warning'
            ],
        ];
        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? '';
    }

}