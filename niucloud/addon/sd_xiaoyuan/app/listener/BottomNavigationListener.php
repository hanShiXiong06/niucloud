<?php

namespace addon\sd_xiaoyuan\app\listener;

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
        $key = 'sd_xiaoyuan';

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
                'textColor' => '#999999',
                'textHoverColor' => '#c0fe95',
                'type' => '1',
                'list' => [
                    [
                        "text" => "首页",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_INDEX",
                            "title" => "首页",
                            "url" => "/addon/sd_xiaoyuan/pages/index/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabber/2.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabber/3.png"
                    ],
                    [
                        "text" => "大厅",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_HALL",
                            "title" => "订单大厅",
                            "url" => "/addon/sd_xiaoyuan/pages/order/hall"
                        ],
                         "iconPath" => "addon/sd_xiaoyuan/tabber/10.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabber/1.png"
                    ],
                    [
                        "text" => "发布",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_PUBLISH",
                            "title" => "发布任务",
                            "url" => "/addon/sd_xiaoyuan/pages/order/publish"
                        ],
                       "iconPath" => "addon/sd_xiaoyuan/tabber/8.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabber/9.png"
                    ],
                    [
                        "text" => "消息",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_MESSAGE",
                            "title" => "消息",
                            "url" => "/addon/sd_xiaoyuan/pages/message/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabber/7.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabber/6.png"
                    ],
                    [
                        "text" => "我的",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_PERSONAL",
                            "title" => "我的",
                               "url" => "/addon/sd_xiaoyuan/pages/user/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabber/5.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabber/4.png"
                    ]
                ]
            ]
        ];
    }
}
