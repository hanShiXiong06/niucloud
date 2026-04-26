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

namespace addon\recycle\app\model\recycle_user_address;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

/**
 * 用户退货地址模型
 * Class RecycleUserAddress
 * @package addon\recycle\app\model\recycle_user_address
 */
class RecycleUserAddress extends BaseModel
{

     use SoftDelete;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_user_address';

    /**
    * 定义软删除标记字段.
    * @var string
    */
    protected $deleteTime = 'delete_time';

    /**
    * 定义软删除字段的默认值.
    * @var int
    * 0 不删除 1 删除
    */
    protected $defaultSoftDelete = 0;

    /**
     * 搜索器:用户退货地址ID
     * @param $value
     * @param $data
     */
    public function searchIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("id", $value);
        }
    }
    
    /**
     * 搜索器:用户退货地址会员ID
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
     * 搜索器:用户退货地址地址
     * @param $value
     * @param $data
     */
    public function searchAddressAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("address", $value);
        }
    }
    
    /**
     * 搜索器:用户退货地址身份证
     * @param $value
     * @param $data
     */
    public function searchIdCardAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("id_card", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:用户退货地址手机号
     * @param $value
     * @param $data
     */
    public function searchMobileAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("mobile", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:用户退货地址身份证图片
     * @param $value
     * @param $data
     */
    public function searchCardPicAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("card_pic", $value);
        }
    }
    
    /**
     * 搜索器:用户退货地址姓名
     * @param $value
     * @param $data
     */
    public function searchNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("name", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:用户退货地址创建时间
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
    
    /**
     * 搜索器:用户退货地址更新时间
     * @param $value
     * @param $data
     */
    public function searchUpdateTimeAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["update_time", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["update_time", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["update_time", "<=", $end]]);
        }
    }
    
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name'=>'nickname']);
    }

}
