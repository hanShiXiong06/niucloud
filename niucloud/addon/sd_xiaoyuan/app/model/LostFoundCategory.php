<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 失物招领分类模型
 */
class LostFoundCategory extends BaseModel
{
    protected $name = 'xiaoyuan_lost_found_category';
    protected $pk = 'id';

    /**
     * 搜索器:状态
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器:名称
     */
    public function searchNameAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->whereLike('name', '%' . $value . '%');
        }
    }
}
