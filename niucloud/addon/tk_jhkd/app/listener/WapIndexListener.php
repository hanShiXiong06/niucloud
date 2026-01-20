<?php
namespace addon\tk_jhkd\app\listener;

use app\service\core\site\CoreSiteService;

/**
 * 手机端首页加载事件
 */
class WapIndexListener
{
    public function handle()
    {
        $site_addon = (new CoreSiteService())->getAddonKeysBySiteId(request()->siteId());
        if (!in_array('tk_jhkd', $site_addon)) return;

        return [
            [
                'key' => 'tk_jhkd',
                "title" => '聚合快递首页',
                'desc' => '低价寄快递，上门取件',
                "url" => "/addon/tk_jhkd/pages/index",
                'icon'=>'addon/tk_jhkd/icon.png'
            ],
        ];
    }
}
