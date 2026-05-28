<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: hsx
// +----------------------------------------------------------------------

namespace addon\hsx_recycle\app\model\category;

use core\base\BaseModel;

/**
 * 回收分类报价单历史快照模型
 * Class RecycleCategoryQuoteHistory
 * @package addon\hsx_recycle\app\model\category
 */
class RecycleCategoryQuoteHistory extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_category_quote_history';

    public function searchCategoryIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('category_id', $value);
        }
    }

    /**
     * 截止时间查询：取 create_time <= 给定时间戳
     */
    public function searchEndTimeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('create_time', '<=', $value);
        }
    }

    /**
     * 起止时间区间
     */
    public function searchTimeRangeAttr($query, $value, $data)
    {
        if (is_array($value) && count($value) === 2 && $value[0] && $value[1]) {
            $query->whereBetween('create_time', [(int)$value[0], (int)$value[1]]);
        }
    }
}
