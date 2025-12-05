<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\api\goods;

use addon\phone_shop\app\model\goods\Memory;
use addon\phone_shop\app\model\goods\MemoryGroup;
use addon\phone_shop\app\model\site\Site;
use core\base\BaseApiService;
use core\exception\AdminException;

/**
 * 内存规格分组服务层
 * Class MemoryGroupService
 * @package addon\phone_shop\app\service\admin\goods
 */
class MemoryGroupService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new MemoryGroup();
    }

    /**
     * 获取内存规格分组列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'group_id, group_name, memory_ids, sort, create_time, site_id, update_time';
        $order = 'sort desc, group_id desc';
        
        if (!empty($where['order'])) {
            $order = $where['order'] . ' ' . $where['sort'];
            unset($where['order'], $where['sort']);
        }
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_status')->where([['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        }
        
        $site_id = empty($sites['memory_status']) ? $this->site_id : $this->site_id.",0";
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_group_status')
                ->where([['site_id', 'in',  $site_id]])
                ->findOrEmpty()
                ->toArray();
        }
        
               
        $search_model = $this->model->where([['site_id', 'in', $site_id]])
            ->withSearch(['group_name'], $where)
            ->field($field)
            ->order($order)
            ->append(['memory_list', 'memory_names']);
        
        return $this->pageQuery($search_model);
    }

    /**
     * 获取内存规格分组列表（不分页）
     * @param array $where
     * @param string $field
     * @return array
     */
    public function getList(array $where = [], string $field = 'group_id, site_id, group_name, memory_ids, sort, create_time, update_time')
    {
        $order = 'sort desc,group_id desc';
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_status')->where([['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        }
        
        $site_id = empty($sites['memory_status']) ? $this->site_id : $this->site_id.",0";
        
        
        return $this->model->where([['site_id', 'in', $site_id]])
            ->withSearch(['group_name'], $where)
            ->field($field)
            ->order($order)
            ->select()
            ->toArray();
    }

    /**
     * 获取内存规格分组详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'group_id, group_name, memory_ids, sort, site_id, create_time, update_time';
        if($this->site_id !== 0 ){
            $sites = (new Site())->field('memory_group_status')
                ->where([['site_id', '=', $this->site_id]])
                ->findOrEmpty()
                ->toArray();
        }
        
        $site_id = empty($sites['memory_group_status']) ? $this->site_id : $this->site_id.",0";
        $info = $this->model->field($field)
            ->where([['group_id', '=', $id], ['site_id', 'in', $site_id]])
            ->findOrEmpty()
            ->toArray();
            
        if (!empty($info)) {
            // 获取内存规格列表
            $memory_model = new Memory();
            $memory_ids = explode(',', $info['memory_ids']);
            $memory_list = $memory_model->where([
                ['spec_id', 'in', $memory_ids],
                ['site_id', 'in', [$info['site_id'], 0]]
            ])->field('spec_id, spec_name, sort')
                ->order('sort desc, spec_id desc')
                ->select()
                ->toArray();
            
            $info['memory_list'] = $memory_list;
            $info['memory_names'] = array_column($memory_list, 'spec_name');
        }
        
        return $info;
    }

    /**
     * 添加内存规格分组
     * @param array $data
     * @return int
     */
    public function add(array $data)
    {
        // 数据验证
        $this->validateData($data);
        
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        
        // // 检查分组名称是否重复
        // $exists = $this->model->where([
        //     ['site_id', '=', $this->site_id],
        //     ['group_name', '=', $data['group_name']]
        // ])->findOrEmpty()->toArray();
        
        // if (!empty($exists)) {
        //     throw new AdminException('内存规格分组名称已存在');
        // }
        
        // 处理内存规格ID
        if (!empty($data['memory_ids']) && is_array($data['memory_ids'])) {
            $data['memory_ids'] = implode(',', array_unique(array_filter($data['memory_ids'])));
        }
        
        $res = $this->model->create($data);
        return $res->group_id;
    }

    /**
     * 编辑内存规格分组
     * @param array $data
     * @return bool
     */
    public function edit(array $data)
    {
        // 数据验证
        $this->validateData($data);
        
        if (empty($data['group_id'])) {
            throw new AdminException('分组ID不能为空');
        }
        
        // 检查分组是否存在
        $info = $this->model->where([
            ['group_id', '=', $data['group_id']],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        
        if (empty($info)) {
            throw new AdminException('内存规格分组不存在');
        }
        
        
        
        // 处理内存规格ID
        if (!empty($data['memory_ids']) && is_array($data['memory_ids'])) {
            $data['memory_ids'] = implode(',', array_unique(array_filter($data['memory_ids'])));
        }
        
        $data['update_time'] = time();
        
        return $this->model->where([
            ['group_id', '=', $data['group_id']],
            ['site_id', '=', $this->site_id]
        ])->update($data);
    }

    /**
     * 删除内存规格分组
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
        // 检查分组是否存在
        $info = $this->model->where([
            ['group_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        
        if (empty($info)) {
            throw new AdminException('内存规格分组不存在');
        }
        
        
       
        
        return $this->model->where([
            ['group_id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->delete();
    }

    /**
     * 修改排序
     * @param array $data
     * @return bool
     */
    public function modifySort(array $data)
    {
        if (empty($data['group_id']) || !isset($data['sort'])) {
            throw new AdminException('参数错误');
        }
        
        return $this->model->where([
            ['group_id', '=', $data['group_id']],
            ['site_id', '=', $this->site_id]
        ])->update(['sort' => intval($data['sort'])]);
    }

    /**
     * 数据验证
     * @param array $data
     * @return void
     */
    private function validateData(array $data)
    {
        if (empty($data['group_name'])) {
            throw new AdminException('分组名称不能为空');
        }
        
        if (empty($data['memory_ids'])) {
            throw new AdminException('请选择内存规格');
        }
        
        if (!isset($data['sort'])) {
            $data['sort'] = 0;
        }
    }
}
