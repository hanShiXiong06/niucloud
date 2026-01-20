<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\kd_api\app\dict\status;

class StatusDict
{
    const WAIT=0;
    const SUCCESS=1;
    const FAIL=2;
    const CANCEL=3;
    public static function getStatusDict($status='')
    {
        $data=[
            self::WAIT=>'待处理',
            self::SUCCESS=>'成功',
            self::FAIL=>'失败',
            self::CANCEL=>'取消'
        ];
        if($status!=''){
            return $data[$status];
        }
        return $data;
    }
}
