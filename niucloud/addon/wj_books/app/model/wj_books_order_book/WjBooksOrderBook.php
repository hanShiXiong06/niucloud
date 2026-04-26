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

namespace addon\wj_books\app\model\wj_books_order_book;

use core\base\BaseModel;
use think\model\relation\BelongsTo;

/**
 * 订单书籍明细模型
 * Class WjBooksOrderBook
 * @package addon\wj_books\app\model\wj_books_order_book
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
     * 搜索器:图书ID
     * @param $query
     * @param $value
     */
    public function searchBookIdAttr($query, $value)
    {
        if ($value) {
            $query->where("book_id", $value);
        }
    }

    /**
     * 搜索器:ISBN
     * @param $query
     * @param $value
     */
    public function searchIsbnAttr($query, $value)
    {
        if ($value) {
            $query->where("isbn", 'like', "%$value%");
        }
    }

    /**
     * 关联订单
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo('addon\wj_books\app\model\wj_books_order\WjBooksOrder', 'order_id', 'id');
    }

    /**
     * 关联图书
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo('addon\wj_books\app\model\wj_books_info\WjBooksInfo', 'book_id', 'id');
    }
} 