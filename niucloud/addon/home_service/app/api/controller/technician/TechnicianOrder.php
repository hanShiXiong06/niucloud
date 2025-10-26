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

namespace addon\home_service\app\api\controller\technician;

use addon\home_service\app\service\api\technician\TechnicianOrderService;
use core\base\BaseApiController;

/**
 * 订单控制器
 * Class GoodsController
 * @package app\adminapi\controller
 */
class TechnicianOrder extends BaseApiController
{


    /**
     * 订单状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', (new TechnicianOrderService())->getStatus());
    }

    /**
     * 任务订单状态
     * @return \think\Response
     */
    public function taskStatus()
    {
        return success('SUCCESS', (new TechnicianOrderService())->getTaskStatus());
    }


    /**
     * 获取订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_status', ''],
            ['lng', ''],
            ['lat', ''],
            ['distance', 'all'],
            ['order_id', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->getPage($data));
    }




    /**
     * 获取订单详情
     * @param $order_id
     * @return \think\Response
     */
    public function detail($id)
    {
        return success('SUCCESS', (new TechnicianOrderService())->getDetail($id));
    }


    /**
     * 师傅出发
     * @param $order_id
     * @return \think\Response
     */
    public function depart()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['depart_lat_lng', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->depart($data));
    }


    /**
     * 师傅拍照
     * @param $order_id
     * @return \think\Response
     */
    public function photoTaken()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['take_photos', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->photoTaken($data));
    }


    /**
     * 师傅开始服务
     * @param $order_id
     * @return \think\Response
     */
    public function start()
    {
        $data = $this->request->params([
            ['order_id', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->start($data));
    }

    /**
     * 师傅提交验证
     * @param $order_id
     * @return \think\Response
     */
    public function savecheck()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['check_photos', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->savecheck($data));
    }

    /**
     * 获取商品增项服务列表
     * @return \think\Response
     */
    public function getGoodsItemList()
    {
        $data = $this->request->params([
            ['order_id', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->getGoodsItemList($data));
    }

    /**
     * 获取订单增项服务列表
     * @return \think\Response
     */
    public function getItemList()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['is_pay', 'all'],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->getItemList($data));
    }


    /**
     * 添加服务项目
     * @return \think\Response
     */
    public function addItem()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['item_list', []],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->orderAddItem($data));
    }

    /**
     * 编辑服务项目
     * @return \think\Response
     */
    public function editItem()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['item_list', []],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->orderEditItem($data));
    }
    /**
     * 删除服务项目
     * @return \think\Response
     */
    public function delItem()
    {
        $data = $this->request->params([
            ['order_item_ids', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->orderDelItem($data));
    }

    /**
     * 修改预约时间订单
     * @param int $order_id
     * @return \think\Response
     */
    public function editReserveServiceTime(int $order_id)
    {
        $data = $this->request->params([
            ['reserve_service_time', ''],
        ]);
        return success('SUCCESS', (new TechnicianOrderService())->editReserveServiceTime($order_id,$data));
    }


    /**
     * 取消订单
     * @param int $order_id
     * @return \think\Response
     */
    public function cancel(int $order_id)
    {
        return success('SUCCESS', (new OrderService())->cancel($order_id));
    }

    /**
     * 删除订单
     * @param int $order_id
     * @return \think\Response
     */
    public function delete(int $order_id)
    {
        return success('DELETE_SUCCESS', (new OrderService())->delete($order_id));
    }


    /**
     * 获取订单数量
     * @return \think\Response
     */
    public function getNum()
    {
        return success((new OrderService())->num());
    }


}
