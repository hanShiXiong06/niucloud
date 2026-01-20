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

namespace addon\tk_jhkd\app\service\admin\order;

use addon\tk_jhkd\app\model\order\OrderLog;

use core\base\BaseAdminService;


/**
 * 订单日志服务层
 * Class OrderLogService
 * @package addon\tk_jhkd\app\service\admin\order
 */
class OrderLogService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderLog();
    }

    /**
     * 获取订单日志列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,order_id,action,main_type,main_id,nick_name,order_status,create_time';
        $order = 'create_time desc';
        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["id","order_id","action","main_type","create_time"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取订单日志信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,order_id,action,main_type,main_id,nick_name,order_status,create_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加订单日志
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 订单日志编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id]])->update($data);
        return true;
    }

    /**
     * 删除订单日志
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id]])->find();
        $res = $model->delete();
        return $res;
    }

    

}
