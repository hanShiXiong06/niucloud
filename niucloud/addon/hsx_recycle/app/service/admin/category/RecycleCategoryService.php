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

namespace addon\hsx_recycle\app\service\admin\category;

use addon\hsx_recycle\app\model\category\RecycleCategory;
use addon\hsx_recycle\app\service\core\category\CoreRecycleCategoryService;
use addon\hsx_recycle\app\model\category\RecycleCategoryConfig;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 二手机分类服务层
 * Class RecycleCategoryService
 * @package addon\hsx_recycle\app\service\admin\recycle_category
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
     * @param string|null $date 指定日期（YYYY-MM-DD）查看该日及之前最新的报价单快照；为空取当前最新
     * @return array
     */
    public function getTree(?string $date = null)
    {
        if($this->site_id !== 0) {
            $config = (new RecycleCategoryConfig())->where([
                ['site_id', '=', $this->site_id]
            ])->findOrEmpty()->toArray();
        }

        $site_id = empty($config) || empty($config['is_enable']) ? $this->site_id : $this->site_id.",0";
        $tree = (new CoreRecycleCategoryService())->getTree([['site_id', 'in', "{$site_id}"]]);

        if ($date) {
            $this->applyHistoryImagesToTree($tree, $date);
        }

        return $tree;
    }

    /**
     * 根据指定日期，把 tree 里每个节点的 images 替换为该日期的历史快照
     */
    private function applyHistoryImagesToTree(array &$tree, string $date): void
    {
        $category_ids = [];
        $this->collectCategoryIds($tree, $category_ids);
        if (empty($category_ids)) {
            return;
        }

        $map = (new RecycleCategoryQuoteHistoryService())->getSnapshotMapByDate($date, $category_ids);
        $this->fillImagesByMap($tree, $map);
    }

    private function collectCategoryIds(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            $ids[] = (int)$node['category_id'];
            if (!empty($node['child_list'])) {
                $this->collectCategoryIds($node['child_list'], $ids);
            }
        }
    }

    private function fillImagesByMap(array &$nodes, array $map): void
    {
        foreach ($nodes as &$node) {
            $cid = (int)$node['category_id'];
            $node['images'] = isset($map[$cid]) ? $map[$cid]['images'] : '';
            if (!empty($node['child_list'])) {
                $this->fillImagesByMap($node['child_list'], $map);
            }
        }
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

        if (!empty($data['images'])) {
            (new RecycleCategoryQuoteHistoryService())->snapshot(
                (int)$res->category_id,
                (string)$data['images'],
                $data['quote_remark'] ?? ''
            );
        }

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

        $old_images = (string)($category_info['images'] ?? '');
        $new_images = (string)($data['images'] ?? '');
        if ($new_images !== '' && $new_images !== $old_images) {
            (new RecycleCategoryQuoteHistoryService())->snapshot(
                $id,
                $new_images,
                $data['quote_remark'] ?? ''
            );
        }

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
