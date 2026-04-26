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

namespace addon\wj_books\app\model\wj_books_rejected_book;

use core\base\BaseModel;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

/**
 * 拒收书籍模型
 * Class WjBooksRejectedBook
 * @package addon\wj_books\app\model\wj_books_rejected_book
 */
class WjBooksRejectedBook extends BaseModel
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
    protected $name = 'wj_books_rejected_book';

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
     * 搜索器:订单图书ID
     * @param $query
     * @param $value
     */
    public function searchOrderBookIdAttr($query, $value)
    {
        if ($value) {
            $query->where("order_book_id", $value);
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
     * 搜索器:是否可取回
     * @param $query
     * @param $value
     */
    public function searchCanRetrieveAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("can_retrieve", $value);
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
     * 关联订单图书
     */
    public function orderBook(): BelongsTo
    {
        return $this->belongsTo('addon\wj_books\app\model\wj_books_order_book\WjBooksOrderBook', 'order_book_id', 'id');
    }

    /**
     * 关联图书
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo('addon\wj_books\app\model\wj_books_info\WjBooksInfo', 'book_id', 'id');
    }

    /**
     * 关联审核图片
     */
    public function images(): HasMany
    {
        return $this->hasMany('addon\wj_books\app\model\wj_books_rejected_images\WjBooksRejectedImages', 'rejected_book_id', 'id');
    }
} 