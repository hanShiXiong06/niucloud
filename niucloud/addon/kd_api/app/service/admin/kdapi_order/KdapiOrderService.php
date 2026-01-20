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

namespace addon\kd_api\app\service\admin\kdapi_order;

use addon\kd_api\app\dict\order\OrderDict;
use addon\kd_api\app\model\kdapi_order\KdapiOrder;
use app\model\member\Member;

use core\base\BaseAdminService;


/**
 * 订单列服务层
 * Class KdapiOrderService
 * @package addon\kd_api\app\service\admin\kdapi_order
 */
class KdapiOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new KdapiOrder();
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,order_id,title,order_money,pay_money,commission,status,is_js,sid,pub_id,create_time';
        $order = 'create_time desc';
        $search_model = $this->model
            ->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["member_id","order_id","title","status","is_js","sid","pub_id","create_time"], $where)->with(['member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['status_name'] = OrderDict::getStatusDict($v['status']);
        }
        return $list;
    }

    /**
     * 获取订单列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,order_id,title,order_money,pay_money,commission,status,is_js,sid,pub_id,create_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加订单列
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
     * 订单列编辑
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
     * 删除订单列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

}
