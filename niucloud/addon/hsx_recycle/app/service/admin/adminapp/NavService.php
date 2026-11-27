<?php

namespace addon\hsx_recycle\app\service\admin\adminapp;

use core\base\BaseAdminService;

/**
 * 手机管理端底部导航。
 */
class NavService extends BaseAdminService
{
    public function getNavList(array $param = [])
    {
        return [
            [
                'iconPath' => '/app/tabbar/index.png',
                'iconSelectPath' => '/app/tabbar/index-selected.png',
                'text' => '工作台',
                'link' => [
                    'url' => '/app/pages/index/index',
                ]
            ],
            [
                'iconPath' => '/app/tabbar/app.png',
                'iconSelectPath' => '/app/tabbar/app-selected.png',
                'text' => '应用',
                'link' => [
                    'url' => '/app/pages/index/menu',
                ]
            ],
            [
                'iconPath' => '/app/tabbar/my.png',
                'iconSelectPath' => '/app/tabbar/my-selected.png',
                'text' => '我的',
                'link' => [
                    'url' => '/app/pages/site/index',
                ]
            ]
        ];
    }
}
