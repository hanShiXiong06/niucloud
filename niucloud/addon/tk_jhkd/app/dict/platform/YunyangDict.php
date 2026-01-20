<?php

namespace addon\tk_jhkd\app\dict\platform;

class YunyangDict
{
    public function getType()
    {
        $data = [
            'yunyang' => [
                "is_use"=>'启用',
                "type" => 'yunyang',
                "name" => "云洋",
                "desc" => "云洋开放平台对接",
                //配置参数
                'params' => [
                    "is_use"=>'启用',//必须
                    'appid' => 'appid',
                    'appkey' => 'appkey',
                ],
                'component' => '/src/addon/tk_jhkd/views/platform/components/yunyang.vue',//前端配置信息定义文件
                'callback' => '/api/tk_jhkd/yunyangnotice'
               ]
            ];
        return $data;
    }
}