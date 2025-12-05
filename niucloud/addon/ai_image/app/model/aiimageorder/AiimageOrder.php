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

namespace addon\ai_image\app\model\aiimageorder;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

use addon\ai_image\app\model\aiimagepackage\AiimagePackage;

/**
 * 订单列模型
 * Class AiimageOrder
 * @package addon\ai_image\app\model\aiimageorder
 */
class AiimageOrder extends BaseModel
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
    protected $name = 'aiimage_order';

    

    

    /**
     * 搜索器:订单列会员
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
     * 搜索器:订单列套餐id
     * @param $value
     * @param $data
     */
    public function searchPackageIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("package_id", $value);
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
     * 搜索器:订单列名称
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
     * 搜索器:订单列图像
     * @param $value
     * @param $data
     */
    public function searchImageAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("image", $value);
        }
    }
    
    /**
     * 搜索器:订单列状态
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("status", $value);
        }
    }
    
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name'=>'nickname']);
    }

    public function aiimagePackage(){
       return $this->hasOne(AiimagePackage::class, 'id', 'package_id')->joinType('left')->withField('name,id')->bind(['package_id_name'=>'name']);
    }

}
