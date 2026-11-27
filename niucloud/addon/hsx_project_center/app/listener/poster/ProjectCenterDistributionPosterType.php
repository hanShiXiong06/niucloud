<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\listener\poster;

/** 项目推广分享海报类型。 */
final class ProjectCenterDistributionPosterType
{
    /**
     * GetPosterType 在框架中可能以 event('GetPosterType') 无参数触发，
     * 事件调度器此时会把 null 传给监听器，因此这里不能限定为 array。
     */
    public function handle($data = []): array
    {
        return [[
            'type' => 'hsx_project_center_distribution',
            'addon' => 'hsx_project_center',
            'name' => '项目推广分享海报',
            'desc' => '具备推广资格的会员分享项目，扫码进入项目详情并绑定邀请关系',
            'icon' => 'addon/hsx_project_center/icon.png',
        ]];
    }
}
