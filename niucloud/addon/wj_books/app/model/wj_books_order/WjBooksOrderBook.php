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
 * 订单书籍明细模型
 * Class WjBooksOrderBook
 * @package addon\wj_books\app\model\wj_books_order
 */
class WjBooksOrderBook extends BaseModel
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
    protected $name = 'wj_books_order_book';

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
     * 搜索器:书籍标题
     * @param $query
     * @param $value
     */
    public function searchTitleAttr($query, $value)
    {
        if ($value) {
            $query->where("title", 'like', "%$value%");
        }
    }

    /**
     * 搜索器:ISBN编号
     * @param $query
     * @param $value
     */
    public function searchIsbnAttr($query, $value)
    {
        if ($value) {
            $query->where("isbn", $value);
        }
    }
} 