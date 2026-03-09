<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\School;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 学校管理服务
 */
class SchoolService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new School();
    }

    /**
     * 获取学校列表
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,name,short_name,logo,province,city,address,campus_list,lng,lat,sort,status,create_time';
        $order = 'sort desc,id desc';
        
        // 只使用模型中定义的搜索器
        $searchFields = [];
        if (!empty($where['name'])) $searchFields[] = 'name';
        if ($where['status'] !== '' && $where['status'] !== null) $searchFields[] = 'status';
        if (!empty($where['province'])) $searchFields[] = 'province';
        if (!empty($where['city'])) $searchFields[] = 'city';
        
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch($searchFields, $where)->field($field)->order($order);
        return $this->pageQuery($search_model);
    }

    /**
     * 获取学校列表(不分页)
     */
    public function getList(array $where = [])
    {
        $field = 'id,name,short_name,logo,province,city,address,campus_list';
        $order = 'sort desc,id desc';

        // 只使用模型中定义的搜索器
        $searchFields = [];
        if (!empty($where['name'])) $searchFields[] = 'name';
        
        $query = $this->model->where([['site_id', '=', $this->site_id], ['status', '=', 1]])->withSearch($searchFields, $where);
        $result = $query->field($field)->order($order)->select()->toArray();

        return $result;
    }

    /**
     * 获取学校详情
     */
    public function getInfo(int $id)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('学校不存在');
        }
        return $info->toArray();
    }

    /**
     * 添加学校
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        
        if (!empty($data['campus_list']) && is_array($data['campus_list'])) {
            $data['campus_list'] = implode(',', $data['campus_list']);
        }
        
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 编辑学校
     */
    public function edit(int $id, array $data)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('学校不存在');
        }
        
        $data['update_time'] = time();
        
        if (!empty($data['campus_list']) && is_array($data['campus_list'])) {
            $data['campus_list'] = implode(',', $data['campus_list']);
        }
        
        $info->save($data);
        return true;
    }

    /**
     * 删除学校
     */
    public function del(int $id)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('学校不存在');
        }
        
        $info->delete();
        return true;
    }

    /**
     * 修改学校状态
     */
    public function setStatus(int $id, int $status)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('学校不存在');
        }
        
        $info->save(['status' => $status, 'update_time' => time()]);
        return true;
    }

    /**
     * 获取学校校区列表
     */
    public function getCampusList(int $school_id)
    {
        $info = $this->model->where([['id', '=', $school_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('学校不存在');
        }
        
        $campus_list = $info['campus_list'];
        if (empty($campus_list)) {
            return [];
        }
        
        if (is_string($campus_list)) {
            return array_filter(array_map('trim', explode(',', $campus_list)));
        }
        
        return is_array($campus_list) ? $campus_list : [];
    }
}
