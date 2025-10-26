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

namespace addon\home_service\app\model\card;

use app\dict\sys\FileDict;
use core\base\BaseModel;
/**
 * 次卡订单项模型
 * Class CardOrderItem
 * @package app\model\card_order_item
 */
class CardOrderItem extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'order_item_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_card_order_item';

    /**
     * 小图生成
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getItemImageThumbSmallAttr($value, $data) {
        if(!empty($data['goods_sku_image'])){
            return get_thumb_images($data['site_id'], $data['goods_sku_image'], FileDict::SMALL);
        }
    }

    /**
     * 中图生成
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getItemImageThumbMidAttr($value, $data)
    {
        if (!empty($data['goods_sku_image'])) {
            return get_thumb_images($data['site_id'], $data['goods_sku_image'], FileDict::MID);
        }
        return '';
    }

}
