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

namespace addon\phone_shop\app\listener\app;

/**
 * 应用信息
 * Class ShopPromotion
 * @package addon\phone_shop\app\listener
 */
class ShopPromotionListener
{
    public function handle(array $params)
    {
        if($params['addon'] == 'phone_shop')
        {
            return [
                "category" => [
                    [
                        "key" => "marketing",
                        "name" => get_lang('dict_app_manage.marketing'),
                        "sort" => 10
                    ],
                ],
                [
                    "addon" => "phone_shop",
                    "title" => get_lang('dict_shop_app_manage.coupon'),
                    "category" => "marketing",
                    "desc" => get_lang('dict_shop_app_manage.message_manage'),
                    "icon" => "addon/phone_shop/coupon.png",
                    "cover" => "addon/phone_shop/coupon.png",
                    "url" => "/shphone_shopop/marketing/coupon/list"
                ],
            ];
        }
        return [];
    }
}
