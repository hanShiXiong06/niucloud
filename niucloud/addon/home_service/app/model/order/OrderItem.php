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

namespace addon\home_service\app\model\order;

use addon\home_service\app\dict\order\OrderItemType;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\goods\GoodsSku;
use app\dict\sys\FileDict;
use app\model\pay\Pay;
use core\base\BaseModel;

/**
 *  卡项订单
 * Class O2oGoodsCategory
 * @package app\model\o2o_goods_category
 */
class OrderItem extends BaseModel
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
    protected $name = 'home_service_order_item';

    protected $type = [
        'pay_time' => 'timestamp',
    ];

    /**
     * 是否支付
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIsPayAttr($query, $value, $data)
    {
        if ($value != 'all') {
            $query->where('is_pay', '=', $value);
        }
    }

    /**
     * 退款状态字段转化
     * @param $value
     * @return mixed
     */
    public function getRefundStatusNameAttr($value, $data)
    {
        if(isset($data['refund_status']))
        {
            return RefundDict::getRefundStatus()[$data['refund_status']] ?? '';
        }
    }

    /**
     * 小图生成
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getItemImageThumbSmallAttr($value, $data) {
        if(!empty($data['item_image'])){
            return get_thumb_images($data['site_id'], $data['item_image'], FileDict::SMALL);
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
        if (!empty($data['item_image'])) {
            return get_thumb_images($data['site_id'], $data['item_image'], FileDict::MID);
        }
        return '';
    }

    /**
     * 师傅
     * @return \think\model\relation\HasMany
     */
    public function technicianInfo() {
        return $this->hasOne(Technician::class,'id', 'technician_id');
    }

    /**
     * 订单类型
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getItemTypeNameAttr($value, $data) {

        if(!empty($data['item_type'])){
            return OrderItemType::getStatus()[$data['item_type']]['name'];
        }
    }

    /**
     * 获取图片缩略图（中）
     */
    public function getItemImagesThumbMidAttr($value, $data)
    {
        if (isset($data[ 'item_images' ]) && $data[ 'item_images' ] != '') {
            $goods_image = explode(',', $data[ 'item_images' ]);
            $img_arr = [];
            foreach ($goods_image as $k => $v) {
                $img = get_thumb_images($data['site_id'], $v, FileDict::MID);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }

    /**
     * 获取图片缩略图（小）
     */
    public function getItemImagesThumbSmallAttr($value, $data)
    {
        if (isset($data[ 'item_images' ]) && $data[ 'item_images' ] != '') {
            $goods_image = explode(',', $data[ 'item_images' ]);
            $img_arr = [];
            foreach ($goods_image as $k => $v) {
                $img = get_thumb_images($data['site_id'], $v, FileDict::SMALL);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }




    // 关联Pay表（通过out_trade_no匹配）
    public function pay()
    {
        return $this->hasOne(Pay::class, 'out_trade_no', 'out_trade_no');
    }

    // 关联goos_sku
    public function goodsSku()
    {
        return $this->hasOne(goodsSku::class, 'sku_id', 'item_id')->bind(['sku_name']);
    }


}
