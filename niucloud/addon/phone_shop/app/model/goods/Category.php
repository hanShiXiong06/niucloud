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

namespace addon\phone_shop\app\model\goods;
// 引入memory_group模型
use addon\phone_shop\app\model\goods\MemoryGroup;

use core\base\BaseModel;

/**
 * 商品分类模型
 * Class Category
 * @package addon\phone_shop\app\model\goods
 */
class Category extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'category_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'phone_shop_goods_category';

    /**
     * 搜索器:商品分类分类名称
     * @param $value
     * @param $data
     */
    public function searchCategoryNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("category_name", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:商品分类id
     * @param $value
     * @param $data
     */
    public function searchCategoryIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("category_id", "=",  $value );
        }
    }

    /**
     * 搜索器:pid
     * @param $value
     * @param $data
     */
    public function searchPidAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("pid", "=",  $value );
        }
    }
    /**
     * 计数器: 计算当前分类下有多少件商品
     * @param $value
     * @param $data
     * */ 
   /**
 * 计数器: 计算当前分类下有多少件商品
 * @param array $categoryIds
 * @return int
 */
public function calcCount($categoryIds, $site_id)
{
    // 如果 $categoryIds 不是数组，则将其转换为数组
    if (!is_array($categoryIds)) {
        $categoryIds = [$categoryIds];
    }

    // 处理多个分类 ID 的情况
    return $this->table('sass_phone_shop_goods')
                ->where([['status', '=', 1 ] , ['site_id','=',$site_id] ]) 
                ->where(function($query) use ($categoryIds) {
                    
                    // 添加第一个 whereRaw 使用 JSON_CONTAINS
                    $query->whereRaw('JSON_CONTAINS(goods_category, JSON_QUOTE(?))', [$categoryIds[0]]);

                    // 循环处理剩余的分类 ID，使用 whereOr
                    for ($i = 1; $i < count($categoryIds); $i++) {
                        $query->whereOrRaw('JSON_CONTAINS(goods_category, JSON_QUOTE(?))', [$categoryIds[$i]]);
                    }
                })
                ->count();
}
    // 处理 内存分组
    public function getMemoryGroup($memory_group)
    {
        return MemoryGroup::where('group_id', $memory_group)->value('group_name');
    }



}
