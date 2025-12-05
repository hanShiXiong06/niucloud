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

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Category;
use core\base\BaseCoreService;


/**
 * 商品分类
 * Class CoreGoodsCategoryService
 * @package addon\phone_shop\app\service\core\goods
 */
class CoreGoodsCategoryService extends BaseCoreService
{
    //系统配置文件

    public function __construct()
    {
        parent::__construct();
        $this->model = new Category();
    }

    /**
     * 设置商品分类配置
     * @param array $params
     * @return array
     */
    public function setGoodsCategoryConfig(array $params = [])
    {
        $goods_config_service = new CoreGoodsConfigService();
        $res = $goods_config_service->setGoodsCategoryConfig($params);
        return $res;
    }

    /**
     * 获取商品分类配置
     * @return array
     */
    public function getGoodsCategoryConfig(int $site_id)
    {
        $goods_config_service = new CoreGoodsConfigService();
        $res = $goods_config_service->getGoodsCategoryConfig($site_id);
        return $res;
    }

    /**
     * 查询商品分类树结构
     * @param string $condition
     * @param string $field
     * @param string $order
     * @return array
     */
//     public function getTree( $condition = [], $params =[] ,$field = 'category_id,site_id, category_name,image,level,pid,category_full_name,is_show,sort', $order = 'sort desc, create_time desc')
// {
   
//      $list = $this->model->where($condition)->field($field)->order($order)->select()->toArray();
    
//     // 判断是否需要计算 count 
//     if($params['flag']  == 1){

//         // 获取分类列表
//         // 计算每个分类下的商品数量
//         foreach ($list as &$category) {
//             // 处理多对多关系的 category_id
//             $category['goods_count'] = $this->model->calcCount($category['category_id'],$params['site_id']);
//         }
//     }
//     // 转换为树形结构
//     $list = list_to_tree($list, 'category_id', 'pid', 'child_list');
    
//     return $list;
// }   
    
public function getTree($condition = [], $params = [], $field = 'category_id,site_id, category_name,image,level,pid,category_full_name,is_show,sort,memory_group', $order = 'sort desc, create_time desc')
{
    // 获取分类列表
    $list = $this->model->where($condition)->field($field)->order($order)->select()->toArray();

    // 判断是否需要计算 count 
    // if (!empty($params['flag']) && $params['flag'] == 1) {
        // 使用子查询优化商品数量统计
        $goods_model = new \addon\phone_shop\app\model\goods\Goods();
        $query = $goods_model->field('goods_category')
            ->where([
                ['site_id', '=', $params['site_id']],
                ['status', '=', 1]  // 只统计上架商品
            ]);
            
        // 如果指定了内存分组，则添加条件
        if (!empty($params['memory_ids'])) {
            $memory_ids = is_array($params['memory_ids']) ? $params['memory_ids'] : [$params['memory_ids']];
            $query->whereIn('memory_ids', $memory_ids);
        }
        
        $goodsCount = $query->select()->toArray();

        // 统计每个分类的商品数量
        $countMap = [];
        foreach ($goodsCount as $goods) {
            // 处理商品分类，可能是数组或JSON字符串
            $categoryIds = $goods['goods_category'];
            if (is_string($categoryIds)) {
                $categoryIds = json_decode($categoryIds, true) ?: explode(',', $categoryIds);
            }
            
            if (is_array($categoryIds)) {
                foreach ($categoryIds as $categoryId) {
                    if (!empty($categoryId)) {
                        if (!isset($countMap[$categoryId])) {
                            $countMap[$categoryId] = 0;
                        }
                        $countMap[$categoryId]++;
                    }
                }
            } elseif (!empty($categoryIds)) {
                // 如果是单个分类ID
                if (!isset($countMap[$categoryIds])) {
                    $countMap[$categoryIds] = 0;
                }
                $countMap[$categoryIds]++;
            }
        }

        // 将商品数量添加到分类中
        foreach ($list as &$category) {
            $category['goods_count'] = $countMap[$category['category_id']] ?? 0;
            // 获取内存分组名称
            if (!empty($category['memory_group'])) {
                $category['group_name'] = $this->model->getMemoryGroup($category['memory_group']);
            } else {
                $category['group_name'] = '';
            }
        }
    // } else {
    //     // 即使不需要计算商品数量，也需要获取内存分组名称
    //     foreach ($list as &$category) {
    //         if (!empty($category['memory_group'])) {
    //             $category['group_name'] = $this->model->getMemoryGroup($category['memory_group']);
    //         } else {
    //             $category['group_name'] = '';
    //         }
    //     }
    // }

    // 转换为树形结构
    $list = list_to_tree($list, 'category_id', 'pid', 'child_list');

    return $list;
}




}