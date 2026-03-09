<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener;

use app\service\core\site\CoreSiteService;

/**
 * 手机端首页入口监听器
 */
class WapIndexListener
{
    public function handle($params = [])
    {
        $site_id = request()->siteId();
        if (!empty($params['site_id'])) {
            $site_id = $params['site_id'];
        }
        $site_addon = (new CoreSiteService())->getAddonKeysBySiteId($site_id);
        if (!in_array('sd_xiaoyuan', $site_addon)) return;

        return [
            [
                'key' => 'sd_xiaoyuan',
                'title' => '校园帮',
                'desc' => '校园互助服务平台',
                'icon' => 'addon/sd_xiaoyuan/icon.png',
                'url' => '/addon/sd_xiaoyuan/pages/index/index',
            ]
        ];
    }
}
