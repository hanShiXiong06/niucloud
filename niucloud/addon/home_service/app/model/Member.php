<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\model;

use core\base\BaseModel;
use addon\home_service\app\model\technician\Technician;

/**
 * 会员表
 */
class Member extends BaseModel
{

    protected $pk = 'member_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'member';


    /**
     * 关键字搜索
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('member.username|member.nickname|member.mobile', 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }


    /**
     * 师傅关联
     * @return HasOne
     */
    public function technician()
    {
        return $this->hasOne(Technician::class, 'member_id', 'member_id');
    }


}
