<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use core\base\BaseApiController;
use addon\sd_xiaoyuan\app\service\core\OrderService;

/**
 * 代占座位控制器 - 统一使用Order表，特殊字段存ext
 */
class Seat extends BaseApiController
{
    /**
     * 创建代占座位订单（写入order表，task_type=SEAT）
     */
    public function create()
    {
        $data = $this->request->params([
            ['location_type', 'LIBRARY'],
            ['location_detail', ''],
            ['seat_count', 1],
            ['start_time', ''],
            ['duration', 60],
            ['images', ''],
            ['remark', ''],
            ['reward', 0],
            ['school_id', 0],
            ['campus', '']
        ]);

        if (empty($data['location_detail'])) {
            return fail('请填写具体位置');
        }
        if (empty($data['start_time'])) {
            return fail('请选择占座时间');
        }
        if ($data['reward'] <= 0) {
            return fail('请输入赏金金额');
        }

        // 构建ext JSON
        $ext = json_encode([
            'location_type' => $data['location_type'],
            'location_detail' => $data['location_detail'],
            'seat_count' => intval($data['seat_count']),
            'start_time' => $data['start_time'],
            'duration' => intval($data['duration']),
            'images' => $data['images'],
        ], JSON_UNESCAPED_UNICODE);

        $orderData = [
            'task_type' => 'SEAT',
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
     * 代占座位列表（从order表查task_type=SEAT）
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $params['task_type'] = 'SEAT';
        if ($params['status'] === '') {
            $params['status'] = '0,10,20,30,40';
        }

        $service = new OrderService();
        $result = $service->getOrderList($params);
        return success($result);
    }

    /**
     * 代占座位详情
     */
    public function detail($id)
    {
        $service = new OrderService();
        $result = $service->getOrderDetail($id);
        return success($result);
    }
}
