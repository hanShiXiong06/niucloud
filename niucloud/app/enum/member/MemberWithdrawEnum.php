<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace app\enum\member;

/**
 * 会员提现
 * Class MemberAccountEnum
 * @package app\enum\member
 */
class MemberWithdrawEnum
{
    const WAIT_AUDIT = 1;//待审核
    const WAIT_TRANSFER = 2;//待转账
    const TRANSFERED = 3;//已转账
    const REFUSE = -1;//已拒绝
    const CANCEL = -2;//已取消

    /**
     * 用户状态
     * @return array
     */
    public static function getStatus(){
        return [
            self::WAIT_AUDIT => get_lang('enum_member_withdraw.status_wait_audit'),//待审核
            self::WAIT_TRANSFER  => get_lang('enum_member_withdraw.status_wait_transfer'),//待转账
            self::TRANSFERED => get_lang('enum_member_withdraw.status_transfered'),//已转账
            self::REFUSE  => get_lang('enum_member_withdraw.status_refuse'),//已拒绝
            self::CANCEL  => get_lang('enum_member_withdraw.status_cancel'),//已取消
        ];
    }

}