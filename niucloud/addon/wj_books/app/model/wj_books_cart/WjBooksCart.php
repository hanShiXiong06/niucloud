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

namespace addon\wj_books\app\model\wj_books_cart;

use core\base\BaseModel;
use think\model\relation\HasOne;
use app\model\member\Member;
use addon\wj_books\app\model\wj_books_info\WjBooksInfo;

/**
 * 回收车模型
 * Class WjBooksCart
 * @package addon\wj_books\app\model\wj_books_cart
 */
class WjBooksCart extends BaseModel
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
    protected $name = 'wj_books_cart';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = false;
    
    /**
     * 会员信息
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id')
            ->joinType('left')
            ->withField('member_no,member_id')
            ->bind(['member_name' => 'member_no']);
    }
    
    /**
     * 图书信息
     */
    public function bookInfo()
    {
        return $this->hasOne(WjBooksInfo::class, 'id', 'book_id')
            ->joinType('left')
            ->withField('title,author,publisher,img,small_img,recycle_price');
    }
} 