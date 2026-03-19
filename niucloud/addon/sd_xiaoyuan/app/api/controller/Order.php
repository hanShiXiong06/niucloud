<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\OrderService;
use core\base\BaseApiController;

class Order extends BaseApiController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['task_type', ''],
            ['school_id', 0],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new OrderService();
        $result = $service->getMyOrderList($params);
        
        return success($result);
    }

    public function hall()
    {
        $params = $this->request->params([
            ['task_type', ''],
            ['school_id', 0],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $params['status'] = 10; // 只显示待接单订单
        
        $service = new OrderService();
        $result = $service->getOrderList($params);
        
        return success($result);
    }

    public function detail()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $detail = $service->getOrderDetail($orderId);
        
        return success($detail);
    }

    public function create()
    {
        $data = $this->request->params([
            ['task_type', ''],
            ['school_id', 0],
            ['campus', ''],
            ['pickup_name', ''],
            ['pickup_mobile', ''],
            ['pickup_address', ''],
            ['pickup_lng', ''],
            ['pickup_lat', ''],
            ['receive_name', ''],
            ['receive_mobile', ''],
            ['receive_address', ''],
            ['receive_lng', ''],
            ['receive_lat', ''],
            ['express_company', ''],
            ['express_no', ''],
            ['pickup_code', ''],
            ['goods_name', ''],
            ['goods_image', ''],
            ['task_desc', ''],
            ['remark', ''],
            ['ext', ''],
            ['distance', 0],
            ['weight', 0],
            ['is_urgent', 0],
            ['is_appointment', 0],
            ['appointment_time', ''],
            ['tip_fee', 0],
            ['total_fee', 0],
            ['base_fee', 0],
            ['urgent_fee', 0],
            ['images', '']
        ]);
        
        if (!empty($data['appointment_time']) && !is_numeric($data['appointment_time'])) {
            $data['appointment_time'] = strtotime($data['appointment_time']);
        }
        $data['appointment_time'] = intval($data['appointment_time']);
        
        $data['status'] = 0;

        // 检查信誉分是否受限（<60分不能下单）
        $memberId = $this->request->memberId();
        if ($memberId) {
            (new \addon\sd_xiaoyuan\app\service\core\CreditService())->checkCanOperate((int)$memberId);
        }
        
        $service = new OrderService();
        
        $data['total_fee'] = floatval($data['total_fee']);
        $data['base_fee'] = floatval($data['base_fee']) > 0 ? floatval($data['base_fee']) : $data['total_fee'];
        $data['urgent_fee'] = floatval($data['urgent_fee']);
        $data['distance_fee'] = 0;
        $data['weight_fee'] = 0;
        $data['actual_fee'] = $data['total_fee'];
        
        $result = $service->create($data);
        
        return success($result);
    }

    public function cancel()
    {
        $orderId = $this->request->param('id', 0);
        $reason = $this->request->param('reason', '');
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);

        if (empty($order) || $order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }

        $service->updateStatus($orderId, 90, [
            'cancel_time' => time(),
            'cancel_reason' => $reason,
            'cancel_role' => 'USER'
        ]);

        // 取消订单扣信誉分 -1
        if (!empty($order['member_id'])) {
            try {
                (new \addon\sd_xiaoyuan\app\service\core\CreditService())->onOrderCancel((int)$order['member_id'], 'ORDER', (int)$orderId);
            } catch (\Exception $e) {}
        }
        
        return success('取消成功');
    }

    public function calculateFee()
    {
        $params = $this->request->params([
            ['distance', 0],
            ['weight', 0],
            ['is_urgent', 0]
        ]);
        
        $service = new OrderService();
        $result = $service->calculateFee($params);
        
        return success($result);
    }

    public function getRunnerLocation()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('无权查看此订单');
        }
        
        if (empty($order['runner_id'])) {
            return fail('订单暂无接单员接单');
        }
        
        $runnerModel = new \addon\sd_xiaoyuan\app\model\runner\Runner();
        $runner = $runnerModel->where('id', $order['runner_id'])->find();
        
        if (empty($runner)) {
            return fail('接单员信息不存在');
        }
        
        return success([
            'runner_id' => $runner['id'],
            'real_name' => $runner['real_name'],
            'mobile' => $runner['mobile'],
            'avatar' => $runner['avatar'],
            'score' => $runner['score'],
            'lng' => $runner['lng'],
            'lat' => $runner['lat'],
            'last_location_time' => $runner['last_location_time'],
            'order_status' => $order['status']
        ]);
    }

    public function confirm()
    {
        $orderId = $this->request->param('id', 0);
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('无权操作此订单');
        }
        
        if ($order['status'] != 50) {
            return fail('订单状态不正确');
        }
        
        return success('确认成功');
    }

    public function tip()
    {
        $orderId = $this->request->param('id', 0);
        $tipAmount = floatval($this->request->param('amount', 0));
        
        if ($tipAmount <= 0) {
            return fail('打赏金额必须大于0');
        }
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }
        
        if ($order['status'] != 50) {
            return fail('只能对已完成订单打赏');
        }
        
        // 创建打赏订单记录
        $tipOrder = new \addon\sd_xiaoyuan\app\model\TipOrder();
        $tipOrderData = $tipOrder->create([
            'site_id' => $this->request->siteId(),
            'order_id' => $orderId,
            'member_id' => $this->request->memberId(),
            'runner_id' => $order['runner_id'] ?? 0,
            'amount' => $tipAmount,
            'pay_status' => 0,
            'create_time' => time()
        ]);
        
        return success([
            'tip_order_id' => $tipOrderData->id,
            'trade_type' => 'sd_xiaoyuan_tip',
            'trade_id' => $tipOrderData->id
        ]);
    }

    public function pay()
    {
        $orderId = $this->request->param('id', 0);
        $payType = $this->request->param('pay_type', 'wechat');
        
        $service = new OrderService();
        $order = $service->getOrderDetail($orderId);
        
        if (empty($order)) {
            return fail('订单不存在');
        }
        
        if ($order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }
        
        if ($order['status'] != 0) {
            return fail('订单状态不正确');
        }
        
        // 检查支付过期时间（如果设置了的话）
        if (!empty($order['expire_pay_time']) && $order['expire_pay_time'] > 0 && $order['expire_pay_time'] < time()) {
            return fail('订单已过期，请重新下单');
        }
        
        $service->updateStatus($orderId, 10, [
            'pay_status' => 1,
            'pay_type' => $payType,
            'pay_time' => time(),
            'expire_accept_time' => time() + 1800
        ]);
        
        return success('支付成功');
    }
}
