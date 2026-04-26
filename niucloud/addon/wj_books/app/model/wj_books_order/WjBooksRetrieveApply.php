<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\wj_books\app\model\wj_books_order;

use core\base\BaseModel;

/**
 * 不合格书籍取回申请模型
 * Class WjBooksRetrieveApply
 * @package addon\wj_books\app\model\wj_books_order
 */
class WjBooksRetrieveApply extends BaseModel
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
    protected $name = 'wj_books_retrieve_apply';

    /**
     * 搜索器:订单ID
     * @param $query
     * @param $value
     */
    public function searchOrderIdAttr($query, $value)
    {
        if ($value) {
            $query->where("order_id", $value);
        }
    }

    /**
     * 搜索器:会员ID
     * @param $query
     * @param $value
     */
    public function searchMemberIdAttr($query, $value)
    {
        if ($value) {
            $query->where("member_id", $value);
        }
    }

    /**
     * 搜索器:状态
     * @param $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("status", $value);
        }
    }

    /**
     * 搜索器:创建时间范围
     * @param $query
     * @param $value
     */
    public function searchCreateTimeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            $start_time = $value[0] ?? null;
            $end_time = $value[1] ?? null;
            if ($start_time && $end_time) {
                $query->whereBetweenTime('create_time', $start_time, $end_time);
            }
        }
    }
} 