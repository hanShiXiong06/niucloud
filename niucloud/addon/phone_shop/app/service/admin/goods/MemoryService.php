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

namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\model\goods\Memory;
use addon\phone_shop\app\model\goods\MemoryGroup;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\site\Site;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 内存规格服务层
 * Class MemoryService
 * @package addon\phone_shop\app\service\admin\goods
 */
class MemoryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Memory();
    }

    /**
     * 获取内存规格列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'spec_id, spec_name,    site_id,  sort, create_time, update_time';
        $order = 'spec_id desc';
        if (!empty($where['order'])) {
            $order = $where['order'] . ' ' . $where['sort'];
        }
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_status')->where([['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        }
        
        $site_id = empty($sites['memory_status']) ? $this->site_id : $this->site_id.",0";
        $search_model = $this->model->where([['site_id', 'in', $site_id]])->withSearch(["spec_name", 'group_id'], $where)
            ->field($field)
            ->with([
                'group' => function($query) {
                    $query->field(' group_name');
                },
            ])
            
            ->order($order);
            
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取内存规格列表
     * @param array $where
     * @param string $field
     * @return array
     */
    public function getList(array $where = [], $field = 'spec_id, spec_name,    site_id,  sort, create_time, update_time')
    {
    // $where['id'] 是 分类id
    // 先获取 分类的 memory_group
    $memory_group = (new Category())->where([ ['category_id', '=', $where['id']]])->value('memory_group');
   
    if($this->site_id !== 0 ){
        $sites = (new Site())->field('memory_group_status')->where([['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
    }
    $site_id = empty($sites['memory_group_status']) ? $this->site_id : $this->site_id.",0";
       
    if(!empty($memory_group)){
       // 先查询 $where['id'] 在 memory_group 表中 获取 memory_ids
       $memory_ids = (new MemoryGroup())->where([['site_id', 'in',  $site_id], ['group_id', 'in', $memory_group ]])->value('memory_ids');
       if($memory_ids){
        $where['memory_ids'] = $memory_ids;
       }
    }
       // 然后通过 memory_ids 查询 memory 表 获取 spec_name
       $memory_list = (new Memory())->where([['site_id', 'in', $site_id], ['spec_id', 'in', $where['memory_ids']]])->field('spec_id, spec_name')->select()->toArray();

       return $memory_list;
        $order = 'sort desc,spec_id desc';
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_status')->where([['site_id', 'in', $site_id]])->findOrEmpty()->toArray();
        }
        
    
        $list=  $this->model->where([['site_id', 'in', $site_id]])->withSearch(["spec_name"])
            ->field($field)
            
            ->order($order)
            ->select()
            ->toArray();

            // 将其整理 去除不必要的字段
            // $list = array_map(function($item) {
            //     return [
            //         'memory_id' => $item['spec_id'],
            //         'memory_name' => $item['spec_name'],
            //     ];
            // }, $list);

        return $list;
    }

    /**
     * 获取内存规格信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'spec_id, spec_name,   site_id,  sort, create_time, update_time';
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_status')->where([['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        }
        
        $site_id = empty($sites['memory_status']) ? $this->site_id : $this->site_id.",0";
        $info = $this->model->field($field)
            ->where([['spec_id', '=', $id], ['site_id', 'in', $site_id]])
            ->findOrEmpty()
            ->toArray();
        return $info;
    }

    /**
     * 添加内存规格
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $specInfo = $this->model->where([
            ['site_id', '=', $this->site_id], 
            ['spec_name', '=', $data['spec_name']],
           
        ])->findOrEmpty()->toArray();
        if ($specInfo) {
            throw new AdminException('该分组下内存规格已存在，请检查');
        }
        $res = $this->model->create($data);
        return $res->spec_id;
    }

    /**
     * 编辑内存规格
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['update_time'] = time();
        $specInfo = $this->model->where([
            ['site_id', '=', $this->site_id], 
            ['spec_name', '=', $data['spec_name']],
            ['group_id', '=', $data['group_id']]
        ])->findOrEmpty()->toArray();
        if ($specInfo && $specInfo['spec_id'] != $id) {
            throw new AdminException('该分组下内存规格已存在，请检查');
        }

        $this->model->where([['spec_id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除内存规格
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
        // 检测内存规格是否被使用
        $goods_model = new Goods();
        $count = $goods_model->where([
            ['site_id', '=', $this->site_id],
            [' memory_ids', '=', $id]
        ])->count();
        if ($count) {
            throw new AdminException('该内存规格正在使用中，无法删除');
        }

        $model = $this->model->where([['spec_id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    /**
     * 修改排序
     * @param array $data
     * @return mixed
     */
    public function modifySort(array $data)
    {
        return $this->model->where([
            ['spec_id', '=', $data['spec_id']],
            ['site_id', '=', $this->site_id]
        ])->update(['sort' => $data['sort']]);
    }

    /**
     * 修改状态
     * @param array $data
     * @return mixed
     */
    public function modifyStatus(array $data)
    {
        return $this->model->where([
            ['spec_id', '=', $data['spec_id']],
            ['site_id', '=', $this->site_id]
        ])->update(['status' => $data['status']]);
    }
}
