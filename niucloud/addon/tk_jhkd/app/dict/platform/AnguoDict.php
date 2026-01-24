<?php

namespace addon\tk_jhkd\app\dict\platform;

class AnguoDict
{
    public function getType()
    {
        $data = [
            'anguo' => [
                "is_use" => '启用',
                "type" => 'anguo',
                "name" => "安果ERP",
                "desc" => "安果ERP快递平台对接",
                //配置参数
                'params' => [
                    "is_use" => '启用',//必须
                    'base_url' => 'API地址',
                    'express_company_id' => '快递公司ID',
                ],
                'component' => '/src/addon/tk_jhkd/views/platform/components/anguo.vue',//前端配置信息定义文件
                'callback' => '/api/tk_jhkd/anguonotice'
            ]
        ];
        return $data;
    }
}
