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

namespace addon\wj_books\app\listener;

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
        $key = 'wj_books';
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
                "textHoverColor" => "#4CAF50",
                "type" => "1",
                "list" => [
                    [
                        "text" => "首页",
                        "link" => [
                            "parent" => "WJ_BOOKS_LINK",
                            "name" => "WJ_BOOKS_INDEX",
                            "title" => "旧书回收首页",
                            "url" => "/addon/wj_books/pages/home/index",
                            "action" => "decorate"
                        ],
                        "iconPath" => "addon/wj_books/tabbar/index.png",
                        "iconSelectPath" => "addon/wj_books/tabbar/index_selected.png"
                    ],
                    [
                        "text" => "订单",
                        "link" => [
                            "parent" => "WJ_BOOKS_LINK",
                            "name" => "WJ_BOOKS_ORDER_LIST",
                            "title" => "回收订单",
                            "url" => "/addon/wj_books/pages/order/list",
                            "action" => ""
                        ],
                        "iconPath" => "addon/wj_books/tabbar/order.png",
                        "iconSelectPath" => "addon/wj_books/tabbar/order_selected.png"
                    ],
                    [
                        "text" => "我的",
                        "link" => [
                            "parent" => "WJ_BOOKS_LINK",
                            "name" => "WJ_BOOKS_MEMBER",
                            "title" => "旧书回收个人中心",
                            "url" => "/addon/wj_books/pages/home/my",
                            "action" => "decorate"
                        ],
                        "iconPath" => "addon/wj_books/tabbar/member.png",
                        "iconSelectPath" => "addon/wj_books/tabbar/member_selected.png"
                    ]
                ]
            ]
        ];
    }
}
