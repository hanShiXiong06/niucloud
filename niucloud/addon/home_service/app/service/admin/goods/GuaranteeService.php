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

namespace addon\home_service\app\service\admin\goods;

use addon\home_service\app\model\goods\Guarantee;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/**
 * 服务保障管理服务类
 */
class GuaranteeService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Guarantee();
    }


    /**
     * 服务保障分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $where['site_id'] = $this->site_id;
        $field = 'id, guarantee_title,guarantee_image, guarantee_content,site_id, create_time, update_time';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['guarantee_title'], $where)->field($field)
            ->order($order)->append([]);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 服务保障详情
     * @param int $id
     * @return mixed
     */
    public function getInfo(int $id)
    {
        $field = 'id, guarantee_title, guarantee_image,guarantee_content, create_time, update_time';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])
            ->field($field)
            ->append([])->findOrEmpty()->toArray();
        return $info;
    }


    /**
     * 添加服务保障
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        // 补充基础数据
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        try {
            $res = $this->model->create($data);
            return $res->id;
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }


    /**
     * 编辑服务保障
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        Db::startTrans();
        try {
            // 补充更新时间
            $data['update_time'] = time();
            // 执行更新
            $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->update($data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }

    /**
     * 删除服务保障
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        Db::startTrans();
        try {
            $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])->delete();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }
}