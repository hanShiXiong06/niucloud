<?php

namespace addon\tk_jhkd\app\dict\platform;

class XindaDict
{
    public function getType()
    {
        $data = [
            'xinda' => [
                "is_use"=>'启用',
                "type" => 'xinda',
                "name" => "辛达",
                "desc" => "辛达开放平台对接",
                //配置参数
                'params' => [
                    "is_use"=>'启用',//必须
                    'username' => '账户ID',
                    'secretkey' => '密钥',
                ],
                'component' => '/src/addon/tk_jhkd/views/platform/components/xinda.vue',//前端配置信息定义文件
                'callback' => '/api/tk_jhkd/xindanotice'
               ]
            ];
        return $data;
    }
}