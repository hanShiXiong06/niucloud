<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\listener\system;

use app\dict\site\SiteDict;

/**
 * 将营销中心挂载到系统“应用 > 营销活动”分组。
 */
final class ShowCustomerListener
{
    public function handle(): array
    {
        return [
            SiteDict::ADDON_CHILD_MENU_DICT_MARKING_ACTIVE => [
                [
                    'title' => '营销中心',
                    'desc' => '跨业务任务、奖励发放与事实台账',
                    'icon' => 'addon/hsx_marketing/icon.png',
                    'key' => 'hsx_marketing',
                    'url' => '/hsx_marketing/campaign',
                ],
            ],
        ];
    }
}
