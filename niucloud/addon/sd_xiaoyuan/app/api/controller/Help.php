<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use core\base\BaseApiController;
use addon\sd_xiaoyuan\app\service\core\OrderService;

/**
 * 帮帮忙控制器 - 统一使用Order表，特殊字段存ext
 */
class Help extends BaseApiController
{
    /**
     * 创建帮帮忙订单（写入order表，task_type=HELP）
     */
    public function create()
    {
        $data = $this->request->params([
            ['help_type', 'ERRAND'],
            ['gender_limit', 'ALL'],
            ['images', ''],
            ['remark', ''],
            ['address_id', 0],
            ['reward', 0],
            ['school_id', 0],
            ['campus', '']
        ]);

        // 构建ext JSON
        $ext = json_encode([
            'help_type' => $data['help_type'],
            'gender_limit' => $data['gender_limit'],
            'images' => $data['images'],
            'address_id' => $data['address_id'],
        ], JSON_UNESCAPED_UNICODE);

        $orderData = [
            'task_type' => 'HELP',
            'school_id' => $data['school_id'],
            'campus' => $data['campus'],
            'remark' => $data['remark'],
            'ext' => $ext,
            'goods_image' => $data['images'],
            'total_fee' => floatval($data['reward']),
            'actual_fee' => floatval($data['reward']),
            'base_fee' => floatval($data['reward']),
            'distance_fee' => 0,
            'weight_fee' => 0,
            'urgent_fee' => 0,
            'tip_fee' => 0,
            'status' => 0,
        ];

        $service = new OrderService();
        $result = $service->create($orderData);
        return success($result);
    }

    /**
     * 帮帮忙列表（从order表查task_type=HELP）
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $params['task_type'] = 'HELP';
        if ($params['status'] === '') {
            $params['status'] = '0,10,20,30,40';
        }

        $service = new OrderService();
        $result = $service->getOrderList($params);
        return success($result);
    }

    /**
     * 帮帮忙详情
     */
    public function detail($id)
    {
        $service = new OrderService();
        $result = $service->getOrderDetail($id);
        return success($result);
    }

    /**
     * 接受帮帮忙（等同于接单）
     */
    public function accept()
    {
        $data = $this->request->params([
            ['id', 0]
        ]);

        $service = new OrderService();
        $service->updateStatus($data['id'], 20, [
            'runner_id' => $this->request->memberId(),
            'accept_time' => time()
        ]);
        return success('接单成功');
    }

    /**
     * 取消帮帮忙
     */
    public function cancel()
    {
        $data = $this->request->params([
            ['id', 0],
            ['reason', '']
        ]);

        $service = new OrderService();
        $order = $service->getOrderDetail($data['id']);
        if (empty($order) || $order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }

        $service->updateStatus($data['id'], 90, [
            'cancel_time' => time(),
            'cancel_reason' => $data['reason'],
            'cancel_role' => 'USER'
        ]);
        return success('取消成功');
    }

    /**
     * 完成帮帮忙
     */
    public function complete()
    {
        $data = $this->request->params([
            ['id', 0]
        ]);

        $service = new OrderService();
        $order = $service->getOrderDetail($data['id']);
        if (empty($order) || $order['member_id'] != $this->request->memberId()) {
            return fail('无权操作此订单');
        }

        $service->updateStatus($data['id'], 60, [
            'complete_time' => time()
        ]);
        return success('完成成功');
    }

    /**
     * 我发布的帮帮忙
     */
    public function myPublish()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $params['task_type'] = 'HELP';

        $service = new OrderService();
        $result = $service->getMyOrderList($params);
        return success($result);
    }

    /**
     * 我接受的帮帮忙
     */
    public function myAccept()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $params['task_type'] = 'HELP';
        $params['runner_id'] = $this->request->memberId();

        $service = new OrderService();
        $result = $service->getOrderList($params);
        return success($result);
    }
}
