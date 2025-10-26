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

namespace addon\home_service\app\api\controller\store;

use addon\home_service\app\service\api\store\StoreOrderService;
use addon\vipcard\app\model\MemberCardVerify;
use core\base\BaseApiController;

/**
 * 订单控制器
 * Class GoodsController
 * @package app\adminapi\controller
 */
class StoreOrder extends BaseApiController
{


    /**
     * 订单状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', (new StoreOrderService())->getStatus());
    }


    /**
     * 任务订单状态
     * @return \think\Response
     */
    public function taskStatus()
    {
        return success('SUCCESS', (new StoreOrderService())->getTaskStatus());
    }


    /**
     * 获取订单列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_status', ''],
            ['true_order_status', ''],
            ['category_id', ''],
            ['lat', ''],
            ['lng', ''],
            ['distance', 'all'],
        ]);
        return success('SUCCESS', (new StoreOrderService())->getPage($data));
    }


    /**
     * 获取订单详情
     * @param $order_id
     * @return \think\Response
     */
    public function detail($id)
    {
        return success('SUCCESS', (new StoreOrderService())->getDetail($id));
    }


    public function selectTechnician()
    {
        $data = $this->request->params([
            ['reserve_service_time_stamp', ''],
            ['category_id', ''],
        ]);
        return success('SUCCESS', (new StoreOrderService())->selectTechnician($data));
    }


    /**
     * 门店派单
     * @param $order_id
     * @return \think\Response
     */
    public function dispatch()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['technician_id', ''],
        ]);
        return success('SUCCESS', (new StoreOrderService())->dispatch($data));
    }

    /**
     * 门店 转单
     * @param $order_id
     * @return \think\Response
     */
    public function transfer()
    {
        $data = $this->request->params([
            ['order_id', ''],
            ['technician_id', ''],
        ]);
        return success('SUCCESS', (new StoreOrderService())->orderTransfer($data));
    }

    /**
     * 催单
     * @return \think\Response
     */
    public function reminder()
    {
        $params = $this->request->params([
            ['order_id', '']
        ]);
        $res = (new StoreOrderService)->reminder($params['order_id']);
        return success("SUCCESS");

    }

}
