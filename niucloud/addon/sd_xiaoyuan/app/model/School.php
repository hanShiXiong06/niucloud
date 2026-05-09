<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 学校模型
 */
class School extends BaseModel
{
    protected $name = 'xiaoyuan_school';
    protected $pk = 'id';

    // campus_list 以逗号分隔字符串存储

    /**
     * 搜索器:学校名称
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

    /**
     * 搜索器:省份
     */
    public function searchProvinceAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('province', $value);
        }
    }

    /**
     * 搜索器:城市
     */
    public function searchCityAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('city', $value);
        }
    }
}
