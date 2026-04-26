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
use think\model\relation\HasMany;

/**
 * 拒收书籍模型
 * Class WjBooksRejectedBook
 * @package addon\wj_books\app\model\wj_books_order
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
     * 关联拒收图片
     */
    public function rejectedImages(): HasMany
    {
        return $this->hasMany('addon\wj_books\app\model\wj_books_order\WjBooksRejectedImages', 'rejected_book_id', 'id');
    }
} 