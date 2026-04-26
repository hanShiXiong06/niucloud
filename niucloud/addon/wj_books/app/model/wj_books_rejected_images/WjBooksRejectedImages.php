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

namespace addon\wj_books\app\model\wj_books_rejected_images;

use core\base\BaseModel;
use think\model\relation\BelongsTo;

/**
 * 拒收书籍审核图片模型
 * Class WjBooksRejectedImages
 * @package addon\wj_books\app\model\wj_books_rejected_images
 */
class WjBooksRejectedImages extends BaseModel
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
    protected $name = 'wj_books_rejected_images';

    /**
     * 搜索器:拒收书籍ID
     * @param $query
     * @param $value
     */
    public function searchRejectedBookIdAttr($query, $value)
    {
        if ($value) {
            $query->where("rejected_book_id", $value);
        }
    }

    /**
     * 搜索器:图片类型
     * @param $query
     * @param $value
     */
    public function searchImageTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("image_type", $value);
        }
    }

    /**
     * 关联拒收书籍
     */
    public function rejectedBook(): BelongsTo
    {
        return $this->belongsTo('addon\wj_books\app\model\wj_books_rejected_book\WjBooksRejectedBook', 'rejected_book_id', 'id');
    }
} 