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

use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\model\goods\Card;
use app\dict\sys\FileDict;
use core\base\BaseModel;
/**
 * 会员次卡模型
 * Class MemberCard
 * @package app\model\member_card
 */
class MemberCard extends BaseModel
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
    protected $name = 'home_service_member_card';

    /**
     * 状态
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('status', '=', $value);
        }
    }

    /**
     * 获取退款状态
     * @param $value
     * @param $data
     * @return array|mixed|string
     */
    public function getStatusNameAttr($value, $data)
    {
        return MemberCardDict::getStatus($data[ 'status' ])[ 'name' ] ?? '';
    }

    /**
     * 获取图片缩略图
     */
    public function getGoodsImageThumbAttr($value, $data)
    {
        $thumb_arr = [];
        if ($data['goods_image'] != '') {
            $img_arr = explode(",", $data['goods_image']);
            foreach ($img_arr as $item) {
                $thumb_arr[] = get_thumb_images($data['site_id'], $item);
            }
        }
        return $thumb_arr;
    }

    /**
     * 获取图片缩略图（大）
     */
    public function getGoodsImageThumbBigAttr($value, $data)
    {
        $thumb_arr = [];
        if ($data['goods_image'] != '') {
            $img_arr = explode(",", $data['goods_image']);
            foreach ($img_arr as $item) {
                $thumb_arr[] = get_thumb_images($data['site_id'], $item, FileDict::BIG);
            }
        }
        return $thumb_arr;
    }

    /**
     * 获取图片缩略图（中）
     */
    public function getGoodsImageThumbMidAttr($value, $data)
    {
        $thumb_arr = [];
        if ($data['goods_image'] != '') {
            $img_arr = explode(",", $data['goods_image']);
            foreach ($img_arr as $item) {
                $thumb_arr[] = get_thumb_images($data['site_id'], $item, FileDict::MID);
            }
        }
        return $thumb_arr;
    }

    /**
     * 获取图片缩略图（小）
     */
    public function getGoodsImageThumbSmallAttr($value, $data)
    {
        $thumb_arr = [];
        if ($data['goods_image'] != '') {
            $img_arr = explode(",", $data['goods_image']);
            foreach ($img_arr as $item) {
                $thumb_arr[] = get_thumb_images($data['site_id'], $item, FileDict::SMALL);
            }
        }
        return $thumb_arr;
    }



    /**
     * 获取封面缩略图（小）
     */
    public function getGoodsCoverThumbSmallAttr($value, $data)
    {
        if (isset($data[ 'goods_cover' ]) && $data[ 'goods_cover' ] != '') {
            return get_thumb_images($data[ 'site_id' ], $data[ 'goods_cover' ], FileDict::SMALL);
        }
        return [];
    }

    /**
     * 获取封面缩略图（中）
     */
    public function getGoodsCoverThumbMidAttr($value, $data)
    {
        if (isset($data[ 'goods_cover' ]) && $data[ 'goods_cover' ] != '') {
            return get_thumb_images($data[ 'site_id' ], $data[ 'goods_cover' ], FileDict::MID);
        }
        return [];
    }

    /**
     * 获取封面缩略图（大）
     */
    public function getGoodsCoverThumbBigAttr($value, $data)
    {
        if (isset($data[ 'goods_cover' ]) && $data[ 'goods_cover' ] != '') {
            return get_thumb_images($data[ 'site_id' ], $data[ 'goods_cover' ], FileDict::BIG);
        }
        return [];
    }



    /**
     * 次卡套餐子项
     * @return \think\model\relation\HasMany
     */
    public function item()
    {
        return $this->hasMany(MemberCardItem::class, 'member_card_id', 'id');
    }

    /**
     * 次卡套餐
     * @return \think\model\relation\HasMany
     */
    public function card()
    {
        return $this->hasOne(Card::class, 'card_id', 'card_id');
    }

    /**
     * 次卡套餐
     * @return \think\model\relation\HasMany
     */
    public function useRecords()
    {
        return $this->hasMany(CardUseRecords::class, 'member_card_id', 'id');
    }

}
