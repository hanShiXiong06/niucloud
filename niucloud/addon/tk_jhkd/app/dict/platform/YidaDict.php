<?php

namespace addon\tk_jhkd\app\dict\platform;

class YidaDict
{
    public function getType()
    {
        $data = [
            'yida' => [
                "is_use" => '启用',
                "type" => 'yida',
                "name" => "易达178",
                "desc" => "易达178开放平台对接",
                //配置参数
                'params' => [
                    "is_use" => '启用',//必须
                    'bindMobile' => '绑定手机',
                    'username' => '用户名',
                    'privateKey' => '密钥'
                ],
                'component' => '/src/addon/tk_jhkd/views/platform/components/yida.vue',//前端配置信息定义文件
                'callback' => '/api/tk_jhkd/yidanotice'
            ]
        ];
        return $data;
    }
}