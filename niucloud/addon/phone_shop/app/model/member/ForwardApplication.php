<?php

namespace addon\phone_shop\app\model\member;

use app\model\member\Member;
use app\model\member\MemberLevel;
use core\base\BaseModel;

/** 同行商品转发权限申请。 */
class ForwardApplication extends BaseModel
{
    protected $pk = 'application_id';
    protected $name = 'phone_shop_forward_application';

    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')
            ->withField('member_id,member_no,nickname,username,mobile,headimg,member_level');
    }

    public function targetLevel()
    {
        return $this->hasOne(MemberLevel::class, 'level_id', 'target_level_id')->joinType('left')
            ->withField('level_id,level_name');
    }

    public function getStatusNameAttr($value, $data): string
    {
        return [
            'pending' => '待审核',
            'approved' => '已通过',
            'rejected' => '已拒绝',
            'cancelled' => '已取消',
        ][$data['status'] ?? ''] ?? '未知';
    }
}
