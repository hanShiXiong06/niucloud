<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 校区模型
 */
class Campus extends BaseModel
{
    protected $name = 'xiaoyuan_campus';
    
    // 状态搜索器
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
    
    // 学校ID搜索器
    public function searchSchoolIdAttr($query, $value)
    {
        if ($value > 0) {
            $query->where('school_id', $value);
        }
    }
    
    // 关键词搜索器
    public function searchKeywordAttr($query, $value)
    {
        if (!empty($value)) {
            $query->whereLike('name|address', "%{$value}%");
        }
    }
}
