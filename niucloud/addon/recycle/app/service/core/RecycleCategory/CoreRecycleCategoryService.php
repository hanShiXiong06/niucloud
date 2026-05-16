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

namespace addon\recycle\app\service\core\RecycleCategory;

use addon\recycle\app\model\recycle_category\RecycleCategory;
use core\base\BaseCoreService;


/**
 * 商品分类
 * Class CoreRecycleCategoryService
 * @package addon\recycle\app\service\core\goods
 */
class CoreRecycleCategoryService extends BaseCoreService
{
    //系统配置文件

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleCategory();
    }

 

    /**
     * 查询商品分类树结构
     * @param string $condition
     * @param string $field
     * @param string $order
     * @return array
     */
    public function getTree($condition = [], $used=0, $field = 'category_id,site_id,images,need_vip, category_name,image,level,pid,category_full_name,is_show,sort', $order = 'sort desc, create_time desc')
    {
        // Fetch the list of categories based on the given condition, field selection, and order
        $list = $this->model->where($condition)->field($field)->order($order)->select()->toArray();
    
        // Check if `$used` is set to true, and apply the VIP access rule for images
        // foreach ($list as $k => &$v) {
        //     if ($v['need_vip']) {
        //         // If the user is not a VIP, remove or hide the image
        //         if (!$used) {
        //             $v['images'] = ''; // You could also set this to an empty string or a placeholder image
        //         }
              
        //     }
        // }
    
        $list = list_to_tree($list, 'category_id', 'pid', 'child_list');
    
        return $list;
    }
    
    // Placeholder for checking VIP status, should be replaced with actual VIP check logic
    

}
