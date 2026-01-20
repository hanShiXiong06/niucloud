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

namespace addon\kd_api\app\model\kdapi_order;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

/**
 * 订单列模型
 * Class KdapiOrder
 * @package addon\kd_api\app\model\kdapi_order
 */
class KdapiOrder extends BaseModel
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
    protected $name = 'kdapi_order';

    

    

    /**
     * 搜索器:订单列关联会员
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
     * 搜索器:订单列订单ID
     * @param $value
     * @param $data
     */
    public function searchOrderIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("order_id", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:订单列订单详情
     * @param $value
     * @param $data
     */
    public function searchTitleAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("title", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:订单列订单状态
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("status", $value);
        }
    }
    
    /**
     * 搜索器:订单列是否结算
     * @param $value
     * @param $data
     */
    public function searchIsJsAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("is_js", $value);
        }
    }
    
    /**
     * 搜索器:订单列跟单参数
     * @param $value
     * @param $data
     */
    public function searchSidAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("sid", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:订单列推广ID
     * @param $value
     * @param $data
     */
    public function searchPubIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("pub_id", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:订单列创建时间
     * @param $value
     * @param $data
     */
    /**
     * 搜索器:订单列创建时间
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
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name'=>'nickname']);
    }

}
