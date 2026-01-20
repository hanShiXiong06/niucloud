<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------


namespace addon\tk_jhkd\app\listener;

/**
 * 应用管理
 * Class AppManage
 * @package app\listener\tk_jhkd
 */
class AppManageListener
{
    /**
     * 应用管理
     * @param $data
     */
    public function handle()
    {
        return [
            "category" => [
                //插件如果要单独分类展示，需要专门定义
                [
                    "key" => "tk_jhkd_category",
                    "name" => "第三方应用",
                ],
            ],
            [
                "addon" => "tk_jhkd",
                "title" => "聚合快递",
                "category" => "tk_jhkd_category",  //设置插件对应展示分类，默认basic
                "desc" => "聚合快递应用展示",
                "icon" => addon_resource("tk_jhkd", "icon.png"),  //图标
                "cover" => "",  //封面
                "url" => "/tk_jhkd"
            ],
        ];
    }
}