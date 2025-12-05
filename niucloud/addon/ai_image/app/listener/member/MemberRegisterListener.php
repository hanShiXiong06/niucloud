<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\ai_image\app\listener\member;

use addon\ai_image\app\service\core\ConfigService;
use app\dict\member\MemberAccountTypeDict;
use app\model\member\Member;
use app\service\core\member\CoreMemberAccountService;

/**
 * 会员注册成功事件
 */
class MemberRegisterListener
{
    /**
     * Author: TK
     * Notes:  会员注册进行激励
     * @param $member
     * @return string
     * 2025/10/16 15:36
     */
    public function handle($member)
    {
        if (isset($member['pid'])) {
            $this->site_id = $member['site_id'];
            $config = (new ConfigService())->getConfigSite($this->site_id);
            $share_point = $config['share_point'] ?? 0;
            if ($share_point > 0) {
                $member_info = (new Member())->where([['member_id', '=', $member['pid']]])->findOrEmpty();
                if ($member_info->isEmpty()) return '';
                (new CoreMemberAccountService())->addLog($this->site_id, $member['pid'], MemberAccountTypeDict::POINT, $share_point, 'ai_image_share', 'AI设计邀请奖励积分', '');
            }
        }
        return '';
    }
}