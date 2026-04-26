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

namespace addon\wj_books\app\model\wj_books_info;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 图书信息模型
 * Class WjBooksInfo
 * @package addon\wj_books\app\model\wj_books_info
 */
class WjBooksInfo extends BaseModel
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
    protected $name = 'wj_books_info';

    

    

    /**
     * 搜索器:图书信息13位ISBN码
     * @param $value
     * @param $data
     */
    public function searchIsbnAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("isbn", $value);
        }
    }
    
    /**
     * 搜索器:图书信息10位ISBN码
     * @param $value
     * @param $data
     */
    public function searchIsbn10Attr($query, $value, $data)
    {
       if ($value) {
            $query->where("isbn10", $value);
        }
    }
    
    /**
     * 搜索器:图书信息书名
     * @param $value
     * @param $data
     */
    public function searchTitleAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("title", $value);
        }
    }
    
    /**
     * 搜索器:图书信息是否可回收(0:不可回收,1:可回收)
     * @param $value
     * @param $data
     */
    public function searchCanRecycleAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("can_recycle", $value);
        }
    }
    
    

    

    
}
