<?php
namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

class PackagePrice extends BaseModel
{
    protected $name = 'xiaoyuan_package_price';
    protected $pk = 'id';

    /**
     * 搜索器:规格名称
     */
    public function searchNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器:状态
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }
}
