<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 积分商品模型
 */
class PointsGoods extends BaseModel
{
    protected $name = 'xiaoyuan_points_goods';
    protected $pk = 'id';

    /**
     * 搜索器:商品名称
     */
    public function searchNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->whereLike('name', '%' . $value . '%');
        }
    }

    /**
     * 搜索器:状态
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', intval($value));
        }
    }
}
