<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use core\base\BaseApiController;
use addon\sd_xiaoyuan\app\service\core\OrderService;

/**
 * 代排队控制器 - 统一使用Order表，特殊字段存ext
 */
class Queue extends BaseApiController
{
    /**
     * 创建代排队订单（写入order表，task_type=QUEUE）
     */
    public function create()
    {
        $data = $this->request->params([
            ['location_type', 'CANTEEN'],
            ['queue_location', ''],
            ['queue_purpose', ''],
            ['estimated_duration', 60],
            ['queue_time', ''],
            ['remark', ''],
            ['total_fee', 0],
            ['school_id', 0],
            ['campus', '']
        ]);

        if (empty($data['queue_location'])) {
            return fail('请填写排队位置');
        }
        if (empty($data['queue_purpose'])) {
            return fail('请填写排队事由');
        }
        if (empty($data['queue_time'])) {
            return fail('请选择期望排队时间');
        }
        if ($data['total_fee'] <= 0) {
            return fail('请输入服务价格');
        }

        // 构建ext JSON
        $ext = json_encode([
            'location_type' => $data['location_type'],
            'queue_location' => $data['queue_location'],
            'queue_purpose' => $data['queue_purpose'],
            'estimated_duration' => intval($data['estimated_duration']),
            'queue_time' => $data['queue_time'],
        ], JSON_UNESCAPED_UNICODE);

        $orderData = [
            'task_type' => 'QUEUE',
            'school_id' => $data['school_id'],
            'campus' => $data['campus'],
            'remark' => $data['remark'],
            'ext' => $ext,
            'goods_name' => '代排队服务',
            'task_desc' => $data['queue_purpose'],
            'total_fee' => floatval($data['total_fee']),
            'actual_fee' => floatval($data['total_fee']),
            'base_fee' => floatval($data['total_fee']),
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
     * 代排队列表（从order表查task_type=QUEUE）
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $params['task_type'] = 'QUEUE';
        if ($params['status'] === '') {
            $params['status'] = '0,10,20,30,40';
        }

        $service = new OrderService();
        $result = $service->getOrderList($params);
        return success($result);
    }

    /**
     * 代排队详情
     */
    public function detail($id)
    {
        $service = new OrderService();
        $detail = $service->getOrderDetail($id);
        
        // 验证是否为QUEUE类型
        if ($detail && $detail['task_type'] !== 'QUEUE') {
            return fail('订单类型错误');
        }
        
        return success($detail);
    }
}
