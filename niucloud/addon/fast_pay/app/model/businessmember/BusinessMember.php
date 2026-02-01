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

namespace addon\fast_pay\app\model\businessmember;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use addon\fast_pay\app\model\business\Business;

use app\model\member\Member;

/**
 * 商户会员模型
 * Class BusinessMember
 * @package addon\fast_pay\app\model\businessmember
 */
class BusinessMember extends BaseModel
{

    

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'fastpay_business_member';

    

    

    /**
     * 搜索器:商户会员商户ID
     * @param $value
     * @param $data
     */
    public function searchBusinessIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("business_id", $value);
        }
    }
    
    /**
     * 搜索器:商户会员活动名称
     * @param $value
     * @param $data
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("member_id", $value);
        }
    }
    
    /**
     * 搜索器:商户会员等级
     * @param $value
     * @param $data
     */
    public function searchLevelAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("level", $value);
        }
    }
    
    /**
     * 搜索器:商户会员余额
     * @param $value
     * @param $data
     */
    public function searchBalanceAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("balance", $value);
        }
    }
    
    /**
     * 搜索器:商户会员创建时间
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["create_time", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["create_time", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["create_time", "<=", $end]]);
        }
    }
    
    

    

    
    public function business(){
       return $this->hasOne(Business::class, 'id', 'business_id')->joinType('left')->withField('name,id')->bind(['business_id_name'=>'name']);
    }

    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name'=>'nickname']);
    }

}
