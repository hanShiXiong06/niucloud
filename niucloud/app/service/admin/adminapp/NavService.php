<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace app\service\admin\adminapp;

use app\service\core\adminapp\CoreIndexService;
use core\base\BaseAdminService;

/**
 * 导航
 * Class NavService
 * @package app\service\admin\adminapp
 */
class NavService extends BaseAdminService
{


    /**
     * 统计项
     * @param array $param
     * @return array[]
     */
    public function getNavList(array $param = []){
        $list = [
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
        return $list;

    }


}