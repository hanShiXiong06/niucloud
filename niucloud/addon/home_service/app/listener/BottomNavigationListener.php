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

namespace addon\home_service\app\listener;

use app\service\core\addon\CoreAddonService;
use app\service\core\site\CoreSiteService;

/**
 * 底部导航
 */
class BottomNavigationListener
{
    /**
     * @param array $params
     * @return array|void
     */
    public function handle($params = [])
    {
        $key = 'home_service';
        $site_addon = ( new CoreSiteService() )->getAddonKeysBySiteId(request()->siteId());
        if (!in_array($key, $site_addon)) return;

        if (!empty($params) && !empty($params[ 'key' ]) && $params[ 'key' ] != $key) return;

        $core_addon_service = new CoreAddonService();
        $addon_info = $core_addon_service->getAddonConfig($key);

        return [
            'key' => $key,
            'info' => $addon_info,
            'value' => [
                "backgroundColor" => "#ffffff",
                "textColor" => "#606266",
                "textHoverColor" => "#008610",
                "type" => "1",
                "list" => [
                    [
                        "text" => "首页",
                        "link" => [
                            "parent" => "HOME_SERVICE_LINK",
                            "name" => "HOME_SERVICE_INDEX",
                            "title" => "上门家政首页",
                            "url" => "/addon/home_service/user/pages/index",
                            "action" => "decorate"
                        ],
                        "iconPath" => "addon/home_service/tabbar/index.png",
                        "iconSelectPath" => "addon/home_service/tabbar/index_select.png"
                    ],
                    [
                        "text" => "分类",
                        "link" => [
                            "parent" => "HOME_SERVICE_LINK",
                            "name" => "HOME_SERVICE_CATEGORY",
                            "title" => "分类",
                            "url" => "/addon/home_service/user/pages/goods/category",
                            "action" => ""
                        ],
                        "iconPath" => "addon/home_service/tabbar/category.png",
                        "iconSelectPath" => "addon/home_service/tabbar/category_select.png"
                    ],
                    [
                        "text" => "订单",
                        "link" => [
                            "parent" => "HOME_SERVICE_LINK",
                            "name" => "HOME_SERVICE_ORDER_LIST",
                            "title" => "订单列表",
                            "url" => "/addon/home_service/user/pages/order/list",
                            "action" => ""
                        ],
                        "iconPath" => "addon/home_service/tabbar/order.png",
                        "iconSelectPath" => "addon/home_service/tabbar/order_select.png"
                    ],
                    [
                        "text" => "我的",
                        "link" => [
                            "parent" => "HOME_SERVICE_LINK",
                            "name" => "HOME_SERVICE_MEMBER_INDEX",
                            "title" => "上门家政个人中心",
                            "url" => "/addon/home_service/user/pages/member/index",
                            "action" => "decorate"
                        ],
                        "iconPath" => "addon/home_service/tabbar/member.png",
                        "iconSelectPath" => "addon/home_service/tabbar/member_select.png"
                    ]
                ]
            ]
        ];
    }
}
