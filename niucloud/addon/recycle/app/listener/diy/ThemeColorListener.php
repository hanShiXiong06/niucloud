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

namespace addon\recycle\app\listener\diy;

/**
 * 主题色
 * Class ThemeColorListener
 * @package addon\recycle\app\listener\diy
 */
class ThemeColorListener
{

    public function handle($params)
    {
        if (!empty($params[ 'key' ]) && $params[ 'key' ] == 'shop') {
            return [
                'title' => '环保绿',
                'name' => 'green',
                'theme' => [
                    '--primary-color' => '#44B464', // 主色
                    '--primary-help-color' => '#67C083', // 辅色
                    '--price-text-color' => '#44B464',// 价格颜色
                    '--primary-color-dark' => '#389652', // 灰色
                    '--primary-color-disabled' => '#A9E0B8', // 禁用色
                    '--primary-color-light' => '#EAFBEF', // 边框色（深）
                    '--primary-color-light2' => '#F7FFF9', // 边框色（淡）
                    '--page-bg-color' => '#f6f6f6', // 页面背景色
                ],
            ];
        }
    }
} 