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

namespace addon\home_service\app\model\technician;

use addon\vipcard\app\model\MemberCardVerify;
use app\dict\sys\FileDict;
use core\base\BaseModel;


/**
 *  商品收藏模型
 * Class TechnicianCollect
 * @package app\model\TechnicianCollect
 */
class TechnicianCollect extends BaseModel
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
    protected $name = 'home_service_technician_collect';


    /**
     * 师傅信息
     * @return HasOne
     */
    public function technician()
    {
        return $this->hasOne(Technician::class, 'id', 'technician_id');
    }
//
//    /**
//     * 关联默认商品规格
//     * @return \think\model\relation\HasOne
//     */
//    public function goodsSku()
//    {
//        return $this->hasOne(GoodsSku::class, 'goods_id', 'goods_id')->joinType('left')->withField('goods_id,sku_id,sku_name,price,member_price')->bind(['sku_id', 'sku_name', 'price', 'member_price', 'sale_price']);
//    }


}
