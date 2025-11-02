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

use app\dict\sys\FileDict;
use core\base\BaseModel;


/**
 * 次卡商品模型
 * Class Goods
 * @package app\model\goods
 */
class CardSku extends BaseModel
{


    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'sku_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_card_sku';


    /**
     * 搜索器:商品id
     * @param $value
     * @param $data
     */
    public function searchCardIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("card_id", 'in', $value);
        }
    }


    /**
     * 关联商品主表
     * @return \think\model\relation\HasOne
     */
    public function card()
    {
        return $this->hasOne(Card::class, 'card_id', 'card_id')
            ->joinType('left')
            ->withField('card_id, poster_id, member_discount, site_id, card_name, goods_cover, goods_image, sale_num + virtually_sale as sale_num, status,goods_content')
            ->append(['goods_cover_thumb_small', 'goods_cover_thumb_mid', 'goods_image_thumb_small', 'goods_image_thumb_mid', 'goods_image_thumb_big', 'buy_type_name']);
    }

    /**
     * 获取图片缩略图
     */
    public function getCardSkuImageThumbMidAttr($value, $data)
    {
        if (isset($data['sku_image']) && $data['sku_image'] != '') {
            return get_thumb_images($data['site_id'], $data['sku_image'], FileDict::MID);
        }
        return [];
    }

}
