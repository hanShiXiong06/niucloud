<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener;


/**
 * 协议类型
 */
class AgreementType
{

    public function handle($data){
        return [
            'recycle_service' => '回收设备协议',//服务协议,
        ];
    }
}
