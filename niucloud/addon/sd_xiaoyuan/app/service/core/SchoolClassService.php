<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\SchoolClass;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 班级管理服务
 */
class SchoolClassService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new SchoolClass();
    }

    /**
     * 获取班级列表(分页)
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,school_id,department_id,major_id,name,code,grade,sort,status,create_time';
        $order = 'sort desc,id desc';

        $searchFields = [];
        if (!empty($where['name'])) $searchFields[] = 'name';
        if ($where['status'] !== '' && $where['status'] !== null) $searchFields[] = 'status';
        if (!empty($where['grade'])) $searchFields[] = 'grade';
        if (!empty($where['school_id'])) $searchFields[] = 'school_id';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch($searchFields, $where)->field($field)->order($order);
        return $this->pageQuery($search_model);
    }

    /**
     * 获取班级列表(不分页)
     */
    public function getList(array $where = [])
    {
        $field = 'id,name,code,grade,school_id';
        $order = 'sort desc,id desc';

        $condition = [['site_id', '=', $this->site_id], ['status', '=', 1]];
        if (!empty($where['school_id'])) {
            $condition[] = ['school_id', '=', intval($where['school_id'])];
        }
        if (!empty($where['grade'])) {
            $condition[] = ['grade', '=', $where['grade']];
        }

        return $this->model->where($condition)->field($field)->order($order)->select()->toArray();
    }

    /**
     * 获取年级列表(distinct)
     */
    public function getGrades(int $school_id)
    {
        $grades = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['school_id', '=', $school_id],
            ['status', '=', 1]
        ])->where('grade', '<>', '')->group('grade')->column('grade');

        rsort($grades);
        return $grades;
    }

    /**
     * 获取班级详情
     */
    public function getInfo(int $id)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('班级不存在');
        }
        return $info->toArray();
    }

    /**
     * 添加班级
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();

        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 编辑班级
     */
    public function edit(int $id, array $data)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('班级不存在');
        }

        $data['update_time'] = time();
        $info->save($data);
        return true;
    }

    /**
     * 删除班级
     */
    public function del(int $id)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('班级不存在');
        }

        $info->delete();
        return true;
    }

    /**
     * 修改班级状态
     */
    public function setStatus(int $id, int $status)
    {
        $info = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($info)) {
            throw new CommonException('班级不存在');
        }

        $info->save(['status' => $status, 'update_time' => time()]);
        return true;
    }
}
