<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 拼单参与者模型
 */
class GroupMember extends BaseModel
{
    protected $name = 'xiaoyuan_group_member';
    protected $pk = 'id';

    /**
     * 关联拼单
     */
    public function groupOrder()
    {
        return $this->belongsTo(GroupOrder::class, 'group_id', 'id');
    }
}
