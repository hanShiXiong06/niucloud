<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener;

/**
 * 底部导航监听器
 */
class BottomNavigationListener
{
    public function handle($data)
    {
        return [
            'key' => 'sd_xiaoyuan',
            'info' => [
                'type' => 'app',
                'name' => 'sd_xiaoyuan',
                'title' => '校园帮',
                'desc' => '校园生活服务平台',
                'url' => '/addon/sd_xiaoyuan/pages/index/index',
                'icon' => 'addon/sd_xiaoyuan/icon.png'
            ],
            'value' => [
                'type' => 1,
                'backgroundColor' => '#ffffff',
                'textColor' => '#999999',
                'textHoverColor' => '#52c41a',
                'list' => [
                    [
                        "text" => "首页",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_INDEX",
                            "title" => "首页",
                            "url" => "/addon/sd_xiaoyuan/pages/index/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabbar/index.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabbar/index2.png"
                    ],
                    [
                        "text" => "订单",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_ORDER",
                            "title" => "订单",
                            "url" => "/addon/sd_xiaoyuan/pages/order/list"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabbar/order.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabbar/order2.png"
                    ],
                    [
                        "text" => "树洞",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_COMMUNITY",
                            "title" => "树洞",
                            "url" => "/addon/sd_xiaoyuan/pages/community/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabbar/community.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabbar/community2.png"
                    ],
                    [
                        "text" => "消息",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_MESSAGE",
                            "title" => "消息",
                            "url" => "/addon/sd_xiaoyuan/pages/message/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabbar/message.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabbar/message2.png"
                    ],
                    [
                        "text" => "我的",
                        "link" => [
                            "parent" => "SD_XIAOYUAN_LINK",
                            "name" => "SD_XIAOYUAN_MY",
                            "title" => "我的",
                            "url" => "/addon/sd_xiaoyuan/pages/user/index"
                        ],
                        "iconPath" => "addon/sd_xiaoyuan/tabbar/my.png",
                        "iconSelectPath" => "addon/sd_xiaoyuan/tabbar/my2.png"
                    ]
                ]
            ]
        ];
    }
}
