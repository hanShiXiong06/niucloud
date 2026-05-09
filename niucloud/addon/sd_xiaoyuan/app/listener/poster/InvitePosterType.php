<?php
declare ( strict_types = 1 );

namespace addon\sd_xiaoyuan\app\listener\poster;

/**
 * 校园帮邀请海报类型
 */
class InvitePosterType
{
    /**
     * 邀请海报类型
     * @param array $data
     * @return array
     */
    public function handle($data = [])
    {
        return [
            [
                'type' => 'xiaoyuan_invite',
                'addon' => 'sd_xiaoyuan',
                'name' => '校园帮邀请海报',
                'decs' => '邀请好友加入校园帮，分享后绑定推广关系',
                'icon' => 'addon/sd_xiaoyuan/icon.png'
            ]
        ];
    }
}
