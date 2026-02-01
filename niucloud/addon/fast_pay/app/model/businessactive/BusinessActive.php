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

namespace addon\fast_pay\app\model\businessactive;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use addon\fast_pay\app\model\business\Business;

/**
 * 商户活动模型
 * Class BusinessActive
 * @package addon\fast_pay\app\model\businessactive
 */
class BusinessActive extends BaseModel
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
    protected $name = 'fastpay_business_active';

    

    

    /**
     * 搜索器:商户活动主键
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
     * 搜索器:商户活动商户ID
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
     * 搜索器:商户活动活动名称
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
     * 搜索器:商户活动创建时间
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("create_time", $value);
        }
    }
    
    

    

    
    public function business(){
       return $this->hasOne(Business::class, 'id', 'business_id')->joinType('left')->withField('name,id')->bind(['business_id_name'=>'name']);
    }

}
