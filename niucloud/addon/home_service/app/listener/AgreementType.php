<?php
declare (strict_types = 1);

namespace addon\home_service\app\listener;


/**
 * 协议类型
 */
class AgreementType
{

    public function handle($data){
        return [
            'store_privacy' => '机构隐私协议',
            'store_register' => '机构注册协议',
            'technician_privacy' => '服务人员隐私协议',
            'technician_register' => '服务人员注册协议',
        ];
    }
}
