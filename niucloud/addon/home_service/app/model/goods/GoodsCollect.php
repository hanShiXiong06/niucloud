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

namespace addon\home_service\app\model\goods;

use addon\vipcard\app\model\MemberCardVerify;
use app\dict\sys\FileDict;
use core\base\BaseModel;


/**
 *  商品收藏模型
 * Class O2oGoodsCategory
 * @package app\model\o2o_goods_category
 */
class GoodsCollect extends BaseModel
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
    protected $name = 'home_service_goods_collect';


    /**
     * 商品信息
     * @return HasOne
     */
    public function goods()
    {
        return $this->hasOne(Goods::class, 'goods_id', 'goods_id')
            ->joinType('left')
            ->withField('site_id,goods_id, goods_name,goods_cover,status,goods_category')
//            ->with(['category']) // 新增：关联加载商品分类
            ->append(['goods_cover_thumb_mid'])
            ->bind(['goods_name', 'goods_cover_thumb_mid', 'status']);
    }


    /**
     * 关联默认商品规格
     * @return \think\model\relation\HasOne
     */
    public function goodsSku()
    {
        return $this->hasOne(GoodsSku::class, 'goods_id', 'goods_id')->joinType('left')->withField('goods_id,sku_id,sku_name,price,member_price')->bind(['sku_id', 'sku_name', 'price', 'member_price', 'sale_price']);
    }

    /**
     * 分类
     * @param $value
     * @return mixed
     */
    public function getCategoryNameAttr($value, $data)
    {
        if (isset($data['goods_category'])) {
            return (new GoodsCategory())->where([['category_id', '=', $data['goods_category']]])->value('category_name');
        }
    }

}
