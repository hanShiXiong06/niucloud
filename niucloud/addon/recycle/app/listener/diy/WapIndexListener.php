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
 * 用于加载DIY页面配置
 * Class WapIndexListener
 * @package addon\recycle\app\listener\diy
 */
class WapIndexListener
{
    /**
     * 执行事件
     * @param array $params
     * @return array|mixed
     */
    public function handle($params = [])
    {
        // 引入DIY页面配置
        $pages = include __DIR__ . '/../../dict/diy/pages.php';
        
        // 默认返回DIY_RECYCLE_INDEX配置
        $page_index = $pages['DIY_RECYCLE_INDEX']['recycle_index'] ?? [];
        
        // 如果参数中传入了page_type值，则返回对应的配置
        if (isset($params['page_type']) && isset($pages[$params['page_type']])) {
            $page_key = key($pages[$params['page_type']]);
            return $pages[$params['page_type']][$page_key] ?? [];
        }
        
        return $page_index;
    }
}
