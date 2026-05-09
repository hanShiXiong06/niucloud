<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 二手交易分类模型
 */
class SecondhandCategory extends BaseModel
{
    protected $name = 'xiaoyuan_secondhand_category';
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
