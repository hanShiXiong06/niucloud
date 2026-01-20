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

namespace addon\tk_jhkd\app\service\admin\shop_order;

use addon\tk_jhkd\app\model\shop_order\ShopOrder;

use core\base\BaseAdminService;


/**
 * 商城发单服务层
 * Class ShopOrderService
 * @package addon\tk_jhkd\app\service\admin\shop_order
 */
class ShopOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ShopOrder();
    }

    /**
     * 获取商城发单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,order_no,delivery_id,yida_order_no,order_status_name,is_pick,is_send,close_time,update_time,delete_time,create_time,real_price';
        $order = 'create_time desc';

         $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["id","order_no","delivery_id","order_status_name","create_time"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商城发单信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,order_no,delivery_id,yida_order_no,order_status_name,is_pick,is_send,close_time,update_time,delete_time,create_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();;
        return $info;
    }

    /**
     * 添加商城发单
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 商城发单编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除商城发单
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
}
