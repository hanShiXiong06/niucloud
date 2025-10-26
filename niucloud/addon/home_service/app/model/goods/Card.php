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

use addon\home_service\app\dict\goods\CardDict;
use app\dict\sys\FileDict;
use core\base\BaseModel;
use think\db\Query;
use think\model\concern\SoftDelete;

/**
 * 商品模型
 * Class Goods
 * @package app\model\goods
 */
class Card extends BaseModel
{

    use SoftDelete;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'card_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_card';

    /**
     * 定义软删除标记字段
     * @var string
     */
    protected $deleteTime = 'delete_time';

    /**
     * 定义软删除字段的默认值
     * @var int
     */
    protected $defaultSoftDelete = 0;


    // 设置JSON数据返回数组
    protected $jsonAssoc = true;


    /**
     * 搜索器:此卡名称
     * @param $value
     * @param $data
     */
    public function searchCardNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("card_name", 'like', '%' . $value . '%');
        }
    }


    /**
     * 搜索器:商品表商品状态（1.正常0下架）
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }


    /**
     * 搜索器: 有效期
     * @param $value
     * @param $data
     */
    public function searchValidTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where("valid_type", $value);
        }
    }


    /**
     * 搜索器:商品销量
     * @param $value
     * @param $data
     */
    public function searchSaleNumAttr($query, $value, $data)
    {
        if (!empty($data['start_sale_num']) && !empty($data['end_sale_num'])) {
            $sale_num = [$data['start_sale_num'], $data['end_sale_num']];
            sort($sale_num);
            $query->where('card.sale_num', 'between', $sale_num);
        } else if (!empty($data['start_sale_num'])) {
            $query->where('card.sale_num', '>=', $data['start_sale_num']);
        } else if (!empty($data['end_sale_num'])) {
            $query->where('card.sale_num', '<=', $data['end_sale_num']);
        }

    }


    /**
     * 创建时间搜索器
     * @param $value
     */
    public function searchCreateTimeAttr(Query $query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);

        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('create_time', $start_time, $end_time);
        } else if ($start_time > 0 && $end_time == 0) {
            $query->where([['create_time', '>=', $start_time]]);
        } else if ($start_time == 0 && $end_time > 0) {
            $query->where([['create_time', '<=', $end_time]]);
        }
    }


    /**
     * 获取封面缩略图（小）
     */
    public function getCardCoverThumbSmallAttr($value, $data)
    {
        //        if (isset($data['card_cover']) && $data['card_cover'] != '') {
        //            //card_image
        //            return get_thumb_images($data['site_id'], $data['card_cover'], FileDict::SMALL);
        //        }
        if (isset($data['card_image']) && $data['card_image'] != '') {
            return get_thumb_images($data['site_id'], $data['card_image'], FileDict::SMALL);
        }
        return [];
    }

    /**
     * 获取封面缩略图（中）
     */
    public function getCardCoverThumbMidAttr($value, $data)
    {
        if (isset($data['card_cover']) && $data['card_cover'] != '') {
            return get_thumb_images($data['site_id'], $data['card_cover'], FileDict::MID);
        }
        return [];
    }

    /**
     * 获取封面缩略图（大）
     */
    public function getCardCoverThumbBigAttr($value, $data)
    {
        if (isset($data['card_cover']) && $data['card_cover'] != '') {
            return get_thumb_images($data['site_id'], $data['card_cover'], FileDict::BIG);
        }
        return [];
    }

    /**
     * 获取商品图片缩略图（小）
     */
    public function getCardImageThumbSmallAttr($value, $data)
    {
        if (isset($data['card_image']) && $data['card_image'] != '') {
            $card_image = explode(',', $data['card_image']);
            $img_arr = [];
            foreach ($card_image as $k => $v) {
                $img = get_thumb_images($data['site_id'], $v, FileDict::SMALL);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }

    /**
     * 获取商品图片缩略图（中）
     */
    public function getCardImageThumbMidAttr($value, $data)
    {
        if (isset($data['card_image']) && $data['card_image'] != '') {
            $card_image = explode(',', $data['card_image']);
            $img_arr = [];
            foreach ($card_image as $k => $v) {
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
     * 获取商品图片缩略图（大）
     */
    public function getCardImageThumbBigAttr($value, $data)
    {
        if (isset($data['card_image']) && $data['card_image'] != '') {
            $card_image = explode(',', $data['card_image']);
            $img_arr = [];
            foreach ($card_image as $k => $v) {
                $img = get_thumb_images($data['site_id'], $v, FileDict::BIG);
                if (!empty($img)) {
                    $img_arr[] = $img;
                }
            }
            return $img_arr;
        }
        return [];
    }


    /**
     * @param $value
     * @param $data   valid_type
     * @return mixed|void
     * @throws \Exception
     */
    public function getValidTypeNameAttr($value, $data)
    {
        if (isset($data['valid_type'])) {
            return CardDict::getValidType($data['valid_type']) ?? '';
        }
    }

    /**
     * 关联规格
     * @return \think\model\relation\HasOne
     */
    public function skuList()
    {
        return $this->hasMany(CardSku::class, 'card_id', 'card_id');
    }


    public function cardSku()
    {
        return $this->hasOne(CardSku::class, 'card_id', 'card_id');
    }

}
