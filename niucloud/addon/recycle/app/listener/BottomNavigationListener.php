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

namespace addon\recycle\app\listener;

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
        $key = 'recycle';

        $site_addon = ( new CoreSiteService() )->getAddonKeysBySiteId(request()->siteId());
        if (!in_array($key, $site_addon)) return;

        if (!empty($params) && !empty($params[ 'key' ]) && $params[ 'key' ] != $key) return;

        $core_addon_service = new CoreAddonService();
        $addon_info = $core_addon_service->getAddonConfig($key);

        return [
            'key' => $key,
            'info' => $addon_info,
            'value' => [
                'backgroundColor' => '#ffffff',
                'textColor' => '#333333',
                'textHoverColor' => '#44B464',
                'type' => '1',
                'list' => [
                    [
                        "text" => "首页",
                        "link" => [
                            "parent" => "RECYCLE_LINK",
                            "name" => "DIY_RECYCLE_INDEX",
                            "title" => "回收首页",
                            "url" => "/addon/recycle/pages/index"
                        ],
                        "iconPath" => "addon/recycle/diy/tabbar/system-home.png",
                        "iconSelectPath" => "addon/recycle/diy/tabbar/system-home-selected.png"
                    ],

                    [   
                        "text" => "下单",
                        "link" => [
                            "parent" => "RECYCLE_LINK",
                            "name" => "recycle_ORDER",
                            "title" => '回收下单',
                            "url" => "/addon/recycle/pages/order/order"
                        ],
                        "iconPath" => "addon/recycle/diy/tabbar/system-category.png",
                        "iconSelectPath" => "addon/recycle/diy/tabbar/system-category-selected.png"
                    ],
                    [   
                        "text" => "订单",
                        "link" => [
                            "parent" => "RECYCLE_LINK",
                            "name" => "recycle_ORDER_LIST",
                            "title" => '回收订单列表',
                            "url" => "/addon/recycle/pages/order/list"
                        ],
                        "iconPath" => "addon/recycle/diy/tabbar/system-order.png",
                        "iconSelectPath" => "addon/recycle/diy/tabbar/system-order-selected.png"
                    ],
                    [
                        "text" => "我的",
                        "link" => [
                            "parent" => "RECYCLE_LINK",
                            "name" => "RECYCLE_MEMBER_INDEX",
                            "title" => "个人中心",
                            "url" => "/addon/recycle/pages/member/index"
                        ],
                        "iconPath" => "addon/recycle/diy/tabbar/system-my.png",
                        "iconSelectPath" => "addon/recycle/diy/tabbar/system-my-selected.png"
                    ]
                ]
            ]
        ];
    }
} 