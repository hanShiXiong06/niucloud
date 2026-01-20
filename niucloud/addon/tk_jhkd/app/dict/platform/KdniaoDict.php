<?php

namespace addon\tk_jhkd\app\dict\platform;

class KdniaoDict
{
    public function getType()
    {
        $data = [
            'kdniao' => [
                "is_use"=>'启用',
                "type" => 'kdniao',
                "name" => "快递鸟",
                "desc" => "快递鸟开放平台对接",
                //配置参数
                'params' => [
                    "is_use"=>'启用',//必须
                    'business_id' => '账户ID',
                    'api_key' => '密钥',
                    'shipper_type'=>'产品类型',
                    'is_test'=>'测试环境',
                ],
                'component' => '/src/addon/tk_jhkd/views/platform/components/kdniao.vue',//前端配置信息定义文件
                'callback' => '/api/tk_jhkd/kdniaonotice'
            ]
        ];
        return $data;
    }
}