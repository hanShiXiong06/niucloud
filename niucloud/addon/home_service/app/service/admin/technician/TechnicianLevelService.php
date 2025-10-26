<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\admin\technician;

use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\technician\TechnicianLevel;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 师傅等级服务层
 * Class TechnicianService
 * @package app\service\admin\technician
 */
class TechnicianLevelService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianLevel();
    }

    /**
     * 获取列表
     * @param array $where
     * @return array
     */
    public function getList(array $where = [])
    {
        $where[] = ['site_id', '=', $this->site_id];
        $field = 'level_id,site_id,level_num,level_name,order_rate,achievement,is_default,create_time,update_time';
        $order = 'create_time desc';
        return $this->model->where([['site_id', '=', $this->site_id]])
            ->field($field)
            ->withSearch([], $where)
            ->order($order)
            ->select()->toArray();
    }

    /**
     * 获取默认
     * @param int $id
     * @return array
     */
    public function getDefault()
    {
        $field = 'level_id,level_name';
        $info = $this->model->where([['is_default', '=', 1], ['site_id', '=', $this->site_id]])->field($field)->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 获取师傅等级列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $order = 'level_num asc';
        $search_model = $this->model->where([['site_id', "=", $this->site_id]])
            ->withSearch(["level_name"], $where)
            ->order($order);

        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as &$datum) {
            $datum['technician_count'] = (new TechnicianService)->getCount(['level_id' => $datum['level_id'] ?? 0]);
        }
        return $list;
    }

    /**
     * 获取师傅等级信息
     * @param int $level_id
     * @return array
     */
    public function getInfo(int $level_id)
    {
        $info = $this->model->where([['site_id', '=', $this->site_id], ['level_id', "=", $level_id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加师傅等级
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;

        $default_count = $this->model->where([['is_default', '=', 1], ['site_id', '=', $this->site_id]])->count();
        if ($default_count > 0 && $data['is_default'] == 1) throw new AdminException('ONLY_HAVE_ONE_DEFAULT_LEVEL');

        $level_num_count = $this->model->where([['level_num', '=', $data['level_num']], ['site_id', '=', $this->site_id]])->count();
        if ($level_num_count > 0) throw new AdminException('ONLY_HAVE_ONE_LEVEL_NUM');

        $res = $this->model->create($data);
        return $res->level_id;

    }

    /**
     * 师傅等级编辑
     * @param int $level_id
     * @param array $data
     * @return bool
     */
    public function edit(int $level_id, array $data)
    {
        $default_count = $this->model->where([['level_id', '<>', $level_id], ['is_default', '=', 1], ['site_id', '=', $this->site_id]])->count();
        if ($default_count > 0 && $data['is_default'] == 1) throw new AdminException('ONLY_HAVE_ONE_DEFAULT_LEVEL');
        $this->model->where([['level_id', '=', $level_id], ['site_id', '=', $this->site_id]])->update($data);

        return true;
    }

    /**
     * 删除师傅等级
     * @param int $level_id
     * @return bool
     */
    public function del(int $level_id)
    {
        //先判断等级是否有被使用
        $technician_info = (new Technician())->where([['site_id', '=', $this->site_id], ['level_id', '=', $level_id]])->findOrEmpty();
        if (!$technician_info->isEmpty()) throw new AdminException('TECHNICIAN_HAS_LEVEL_TECHNICIAN_NOT_ALLOW_DELETE');

        $info = $this->model->where([['level_id', '=', $level_id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if (!$info->isEmpty() && $info->is_builtin_data == 1) throw new AdminException('BUILTIN_DATA_NOT_DEL');

        $model = $this->model->where([['level_id', '=', $level_id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    /**
     * 获取分销等级权重
     * @return array
     */
    public function getLevelNumList()
    {
        $list = $this->model->field('level_id,level_num,level_name')->where([['site_id', "=", $this->site_id]])->select()->toArray();
        return $list;
    }


}
