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

namespace addon\phone_shop\app\service\core\RecycleCategory;

use addon\phone_shop\app\model\recycle_category\RecycleCategory;
use core\base\BaseCoreService;


/**
 * 商品分类
 * Class CoreRecycleCategoryService
 * @package addon\phone_shop\app\service\core\goods
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
    public function getTree($condition = [], $field = 'category_id,site_id,images,need_vip, category_name,image,level,pid,category_full_name,is_show,sort', $order = 'sort desc, create_time desc')
    {
        $list = $this->model->where($condition)->field($field)->order($order)->select()->toArray();
        $list = list_to_tree($list, 'category_id', 'pid', 'child_list');
        return $list;
    }

}
