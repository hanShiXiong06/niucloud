<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\home_service\app\job\card;

use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\model\card\MemberCard;
use addon\home_service\app\service\core\card\CoreMemberCardService;
use core\base\BaseJob;

/**
 * 会员卡到期
 */
class MemberCardExpire extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        try {
            $ids = (new MemberCard())->where([
                ['status', '=', MemberCardDict::WAIT_USE],
                ['expire_time', '<=', time()]
            ])->column('id');
            if(!empty($ids)){
                //过期
                (new CoreMemberCardService())->expire($ids);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
