<?php

namespace addon\tk_jhkd\app\dict\agreement;

/**
 * 协议相关枚举类
 * Class AgreementDict
 * @package app\dict\sys
 */
class AgreementDict
{
    //服务协议
    const JHKDSERVICE = 'jhkdservice';
    const JHKDYESGOODS = 'jhkdyesgoods';

    /**
     * 获取协议类型
     * @return string[]
     */
    public static function getType()
    {
        return [
            self::JHKDSERVICE => '快递协议',//快递服务协议,
            self::JHKDYESGOODS=>'违禁协议',//违禁品协议
        ];
    }

}