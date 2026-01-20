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

namespace addon\tk_jhkd\app\service\admin\orderdelivery;

use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use core\base\BaseAdminService;


/**
 * 订单快递信息服务层
 * Class OrderDeliveryService
 * @package addon\tk_jhkd\app\service\admin\orderdelivery
 */
class OrderDeliveryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderDelivery();
    }

    /**
     * 获取订单快递信息列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'order_id,order_no,start_address,end_address,goods,long,width,height,weight,package_count,delivery_id,delivery_status,delivery_type,customer_type,delivery_Business,delivery_info,online_pay,start_time,end_time,order_status_desc,order_status,courier_context,remark,create_time,update_time,delete_time';
        $order = '';

        $search_model = $this->model->withSearch(["order_id","order_no","start_address","end_address","goods","long","width","height","weight","package_count","delivery_id","delivery_status","delivery_type","customer_type","delivery_Business","delivery_info","online_pay","start_time","end_time","order_status_desc","order_status","courier_context","remark","create_time","update_time","delete_time"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取订单快递信息信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'order_id,order_no,start_address,end_address,goods,long,width,height,weight,package_count,delivery_id,delivery_status,delivery_type,customer_type,delivery_Business,delivery_info,online_pay,start_time,end_time,order_status_desc,order_status,courier_context,remark,create_time,update_time,delete_time';

        $info = $this->model->field($field)->where([['order_id', '=', $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加订单快递信息
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $res = $this->model->create($data);
        return $res->order_id;

    }

    /**
     * 订单快递信息编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['order_id', '=', $id]])->update($data);
        return true;
    }

    /**
     * 删除订单快递信息
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['order_id', '=', $id]])->find();
        $res = $model->delete();
        return $res;
    }

}
