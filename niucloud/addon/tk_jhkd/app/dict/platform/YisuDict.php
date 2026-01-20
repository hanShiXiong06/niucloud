<?php

namespace addon\tk_jhkd\app\dict\platform;

class YisuDict
{
    public function getType()
    {
        $data = [
            'yisu' => [
                "is_use"=>'启用',
                "type" => 'yisu',
                "name" => "亿速",
                "desc" => "亿速开放平台对接",
                //配置参数
                'params' => [
                    "is_use"=>'启用',//必须
                    'appid' => 'appid',
                    'appkey' => 'appkey',
                ],
                'component' => '/src/addon/tk_jhkd/views/platform/components/yisu.vue',//前端配置信息定义文件
                'callback' => '/api/tk_jhkd/yisunotice'
               ]
            ];
        return $data;
    }
}