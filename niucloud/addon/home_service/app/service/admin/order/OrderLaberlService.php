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

namespace addon\home_service\app\service\admin\order;

use addon\home_service\app\model\order\OrderLabel;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/**
 *标签标签
 * Class OrderService
 */
class OrderLaberlService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderLabel();
    }


    /**
     * 标签分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $where['site_id'] = $this->site_id;
        $field = 'label_id, site_id, label_name, label_color, create_time';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['label_name'], $where)->field($field)
            ->order($order)->append([]);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 标签详情
     * @param array $where
     * @return mixed
     */
    public function getInfo(int $label_id)
    {
        $field = 'label_id, site_id, label_name, label_color, create_time';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['label_id', '=', $label_id]])
            ->field($field)
            ->append([])->findOrEmpty()->toArray();
        return $info;
    }


    /**
     * 添加标签标签
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        // 补充基础数据
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        try {
            $res = $this->model->create($data);
            return $res->label_id;
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }


    /**
     * 标签标签编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        Db::startTrans();
        try {
            // 执行更新
            $this->model->where([
                ['label_id', '=', $id],
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
     * 删除标签
     * @param int $label_id
     * @return bool
     */
    public function del($label_id)
    {
        $res = $this->model->where([['label_id', '=', $label_id], ['site_id', '=', $this->site_id]])->delete();
        return $res;
    }
}
