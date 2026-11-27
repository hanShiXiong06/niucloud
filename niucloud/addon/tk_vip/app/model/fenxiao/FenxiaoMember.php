<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_vip\app\model\fenxiao;

use app\model\member\Member;
use core\base\BaseModel;

class FenxiaoMember extends BaseModel
{


    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'member_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tkvip_fenxiao_member';

    public function memberInfo()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }

}
