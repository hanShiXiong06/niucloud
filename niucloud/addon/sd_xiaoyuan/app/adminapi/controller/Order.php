<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\OrderService;
use core\base\BaseAdminController;

class Order extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['task_type', ''],
            ['keyword', ''],
            ['order_no', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $params['site_id'] = $this->request->siteId();
        
        $service = new OrderService();
        $result = $service->getOrderList($params);
        
        return success($result);
    }

    public function detail()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $detail = $service->getOrderDetail($orderId, $this->request->siteId());
        
        return success($detail);
    }

    /**
     * 指派接单员
     */
    public function assignRunner()
    {
        $orderId = $this->request->param('order_id', 0);
        $runnerId = $this->request->param('runner_id', 0);
        
        if (empty($orderId) || empty($runnerId)) {
            return fail('参数错误');
        }
        
        $orderModel = new \addon\sd_xiaoyuan\app\model\order\Order();
        $order = $orderModel->where([
            ['id', '=', $orderId],
            ['site_id', '=', $this->request->siteId()]
        ])->findOrEmpty();
        
        if ($order->isEmpty()) {
            return fail('订单不存在');
        }
        
        if ($order['status'] != 10) {
            return fail('只能指派待接单的订单');
        }
        
        $runnerModel = new \addon\sd_xiaoyuan\app\model\runner\Runner();
        $runner = $runnerModel->where([
            ['id', '=', $runnerId],
            ['site_id', '=', $this->request->siteId()],
            ['status', '=', 1]
        ])->findOrEmpty();
        
        if ($runner->isEmpty()) {
            return fail('接单员不存在');
        }
        
        if ($runner['is_online'] != 1) {
            return fail('该接单员当前不在线，无法指派');
        }
        
        $orderModel->where('id', $orderId)->update([
            'runner_id' => $runnerId,
            'status' => 20,
            'accept_time' => time(),
            'update_time' => time()
        ]);
        
        return success('指派成功');
    }

    public function stat()
    {
        $service = new OrderService();
        $orderModel = new \addon\sd_xiaoyuan\app\model\order\Order();
        
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd = $todayStart + 86400;
        
        $stat = [
            'total_orders' => $orderModel->where('site_id', $this->request->siteId())->count(),
            'today_orders' => $orderModel->where([
                ['site_id', '=', $this->request->siteId()],
                ['create_time', '>=', $todayStart],
                ['create_time', '<', $todayEnd]
            ])->count(),
            'pending_orders' => $orderModel->where([
                ['site_id', '=', $this->request->siteId()],
                ['status', '=', 10]
            ])->count(),
            'completed_orders' => $orderModel->where([
                ['site_id', '=', $this->request->siteId()],
                ['status', '=', 50]
            ])->count()
        ];
        
        return success($stat);
    }

    /**
     * 取消订单
     */
    public function cancel()
    {
        $orderId = $this->request->param('id', 0);
        
        if (empty($orderId)) {
            return fail('参数错误');
        }
        
        try {
            $service = new OrderService();
            $result = $service->cancel($orderId, $this->request->siteId());
            
            if ($result) {
                return success('取消成功');
            } else {
                return fail('取消失败');
            }
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
