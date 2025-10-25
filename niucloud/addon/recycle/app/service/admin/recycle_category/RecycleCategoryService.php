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

namespace addon\recycle\app\service\admin\recycle_category;

use addon\recycle\app\model\recycle_category\RecycleCategory;
use addon\recycle\app\service\core\RecycleCategory\CoreRecycleCategoryService;
use addon\recycle\app\model\recycle_category\RecycleCategoryConfig;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 二手机分类服务层
 * Class RecycleCategoryService
 * @package addon\recycle\app\service\admin\recycle_category
 */
class RecycleCategoryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleCategory();
    }

    /**
     * 获取二手机分类列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'category_id,site_id,category_name,image,level,pid,category_full_name,is_show,sort,create_time,update_time,images, need_vip';
        $order = '';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["category_name","level","pid","category_full_name","is_show","sort","create_time","update_time"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 查询商品分类树结构
     * @return array
     */
    public function getTree()
    {
        if($this->site_id !== 0) {
            $config = (new RecycleCategoryConfig())->where([
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
        }
        
        $site_id = empty($config) || empty($config['is_enable']) ? $this->site_id : $this->site_id.",0";
        return (new CoreRecycleCategoryService())->getTree([['site_id', 'in', "{$site_id}"]]);
    }
  

    /**
     * 获取二手机分类信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'category_id,site_id,category_name,image,need_vip,level,pid,category_full_name,is_show,sort,create_time,update_time,images';

        $info = $this->model->field($field)->where([['category_id', "=", $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加商品分类
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data[ 'category_full_name' ] = $data[ 'category_name' ];
        $data[ 'level' ] = 1;
        $data['images'] = $data[ 'images' ];

        $condition = [
            [ 'site_id', '=', $this->site_id ],
            [ 'category_name', '=', $data[ 'category_name' ] ]
        ];
        if ($data[ 'pid' ] > 0) {
            $condition[] = [ 'pid', '=', $data[ 'pid' ] ];
        } else {
            $condition[] = [ 'level', '=', 1 ];
        }

        $categoryInfo = $this->model->where($condition)->findOrEmpty()->toArray();
        if ($categoryInfo) {
            throw new AdminException('分类已存在，请检查');
        }
        if ($data[ 'pid' ] > 0) {
            $info = $this->model->field("category_id, category_name")->where([ [ 'category_id', '=', $data[ 'pid' ] ] ])->findOrEmpty();
            if ($info->isEmpty()) throw new AdminException('SHOP_GOODS_CATEGORY_NOT_EXIST');
            $data[ 'category_full_name' ] = $info->category_name . '/' . $data[ 'category_name' ];
            $data[ 'level' ] = 2;
        }

        $data[ 'site_id' ] = $this->site_id;
        $data[ 'create_time' ] = time();
        $res = $this->model->create($data);
        return $res->category_id;
    }

    /**
     * 商品分类编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
      
        $category_info = $this->getInfo($id);

        

        if ($category_info && $category_info[ 'category_id' ] != $id) {
            throw new AdminException('分类已存在，请检查');
        }
        
        // 检查是否有子分类，如果有子分类且要改变父级，则不允许
        if ($category_info[ 'level' ] == 1 && $category_info[ 'pid' ] != $data[ 'pid' ]) {
            // 查询子分类数量
            $child_count = $this->model->where([
                ['pid', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->count();
            
            if ($child_count > 0) {
                throw new AdminException('SHOP_GOODS_CATEGORY_EXIST_CHILD');
            }
        }

        $data[ 'category_full_name' ] = $data[ 'category_name' ];
        $data[ 'level' ] = 1;
        $data['images'] = $data[ 'images' ];
        if ($data[ 'pid' ] > 0) {
            $info = $this->model->field("category_id, category_name")->where([ [ 'category_id', '=', $data[ 'pid' ] ] ])->findOrEmpty();
            if ($info->isEmpty()) throw new AdminException('SHOP_GOODS_CATEGORY_NOT_EXIST');
            $data[ 'category_full_name' ] = $info->category_name . '/' . $data[ 'category_name' ];
            $data[ 'level' ] = 2;
        }
        $data[ 'sort' ] = $category_info[ 'sort' ];
        $data['need_vip'] = $data['need_vip'];
        $data[ 'update_time' ] = time();
        $this->model->where([ [ 'category_id', '=', $id ], [ 'site_id', '=', $this->site_id ] ])->update($data);
        return true;
    }

    // 拖拽editCategory 改变其sort
    public function updateCategory($data)
    {
        foreach ($data[ 'category_sort_array' ] as $key => $val) {
            $info = $this->model->field("category_id, category_name")->where([ [ 'category_id', '=', $val[ 'category_id' ] ], [ 'site_id', '=', $this->site_id ] ])->findOrEmpty();
            if (!$info->isEmpty()) {
                $data[ 'update_time' ] = time();
                $this->model->where([ [ 'category_id', '=', $val[ 'category_id' ] ], [ 'site_id', '=', $this->site_id ] ])->update([ 'sort' => $val[ 'sort' ] ]);
            }
        }
        return true;
    }

    /**
     * 删除二手机分类
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['category_id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
}
