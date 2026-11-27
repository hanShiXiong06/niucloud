<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\hsx_recycle\app\listener\app;

/**
 * 应用信息
 * Class ShopPromotion
 * @package addon\shop\app\listener
 */
class ShopPromotionListener
{
    public function handle(array $params)
    {
        if($params['addon'] == 'hsx_recycle')
        {
            return [
                "category" => [
                    [
                        "key" => "hsx_recycle",
                        "name" => '回收系统',
                        "sort" => 10
                    ],
                ],
                [
                    "addon" => "hsx_recycle",
                    "title" => '回收系统',
                    "category" => "recycle",
                    "desc" => '回收系统',
                    "icon" => "addon/hsx_recycle/icon.png",
                    "cover" => "addon/hsx_recycle/icon.png",
                    "url" => "/hsx_recycle/index"
                ],
            ];
        }
        return [];
    }
}
