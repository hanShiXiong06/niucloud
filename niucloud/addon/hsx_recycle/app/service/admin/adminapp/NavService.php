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
                'iconPath' => 'static/resource/images/tabbar/index.png',
                'iconSelectPath' => 'static/resource/images/tabbar/index-selected.png',
                'text' => '工作台',
                'link' => [
                    'url' => '/app/pages/index/index',
                ]
            ],
            [
                'iconPath' => 'static/resource/images/tabbar/index.png',
                'iconSelectPath' => 'static/resource/images/tabbar/index-selected.png',
                'text' => '应用',
                'link' => [
                    'url' => '/app/pages/index/menu',
                ]
            ],
            [
                'iconPath' => 'static/resource/images/tabbar/my.png',
                'iconSelectPath' => 'static/resource/images/tabbar/my-selected.png',
                'text' => '我的',
                'link' => [
                    'url' => '/app/pages/site/index',
                ]
            ]
        ];
    }
}
