<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Campus;
use core\base\BaseService;
use think\facade\Db;

/**
 * 校区服务
 */
class CampusService extends BaseService
{
    /**
     * 获取校区列表
     */
    public function getPage(array $data = [])
    {
        $model = new Campus();
        
        // 搜索条件
        $where = [];
        if (!empty($data['school_id'])) {
            $where[] = ['school_id', '=', $data['school_id']];
        }
        if ($data['status'] !== '') {
            $where[] = ['status', '=', $data['status']];
        }
        if (!empty($data['keyword'])) {
            $where[] = ['name|address', 'like', "%{$data['keyword']}%"];
        }
        
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->order('sort asc, id asc')
            ->page($data['page'] ?? 1, $data['limit'] ?? 10)
            ->select()
            ->toArray();
            
        return ['count' => $count, 'list' => $list];
    }
    
    /**
     * 获取校区详情
     */
    public function getInfo(int $id)
    {
        return (new Campus())->find($id);
    }
    
    /**
     * 添加校区
     */
    public function add(array $data)
    {
        $data['create_time'] = time();
        $data['update_time'] = time();
        
        return (new Campus())->create($data);
    }
    
    /**
     * 编辑校区
     */
    public function edit(int $id, array $data)
    {
        $data['update_time'] = time();
        
        return (new Campus())->where('id', $id)->update($data);
    }
    
    /**
     * 删除校区
     */
    public function delete(int $id)
    {
        return (new Campus())->where('id', $id)->delete();
    }
    
    /**
     * 设置状态
     */
    public function setStatus(int $id, int $status)
    {
        return (new Campus())->where('id', $id)->update([
            'status' => $status,
            'update_time' => time()
        ]);
    }
    
    /**
     * 获取校区列表(不分页)
     */
    public function getList(array $where = [])
    {
        return (new Campus())->where($where)
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }
    
    /**
     * 根据学校ID获取校区列表
     */
    public function getBySchoolId(int $schoolId)
    {
        return $this->getList(['school_id' => $schoolId]);
    }
}
