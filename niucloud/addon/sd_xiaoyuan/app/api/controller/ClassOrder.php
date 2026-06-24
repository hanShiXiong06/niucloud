<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\OrderService;
use core\base\BaseApiController;

/**
 * 代上课（task_type=CLASS）
 */
class ClassOrder extends BaseApiController
{
    /**
     * 创建代上课订单
     */
    public function create()
    {
        $data = $this->request->params([
            ['class_subject', ''],
            ['class_location', ''],
            ['building_type', 'TEACHING'],
            ['class_duty', 'SIGNIN'],
            ['class_duration', 90],
            ['class_time', ''],
            ['remark', ''],
            ['total_fee', 0],
            ['school_id', 0],
            ['campus', ''],
        ]);

        if (empty($data['class_subject'])) {
            return fail('请填写课程名称');
        }
        if (empty($data['class_location'])) {
            return fail('请填写上课地点');
        }
        if (empty($data['class_time'])) {
            return fail('请选择上课时间');
        }
        if ($data['total_fee'] <= 0) {
            return fail('请输入服务价格');
        }

        $dutyMap = [
            'SIGNIN' => '代为签到',
            'NOTE' => '课堂笔记',
            'FULL' => '全程代课',
        ];
        $dutyText = $dutyMap[$data['class_duty']] ?? '代上课';

        $ext = json_encode([
            'class_subject' => $data['class_subject'],
            'class_location' => $data['class_location'],
            'building_type' => $data['building_type'],
            'class_duty' => $data['class_duty'],
            'class_duty_text' => $dutyText,
            'class_duration' => intval($data['class_duration']),
            'class_time' => $data['class_time'],
        ], JSON_UNESCAPED_UNICODE);

        $orderData = [
            'task_type' => 'CLASS',
            'school_id' => $data['school_id'],
            'campus' => $data['campus'],
            'remark' => $data['remark'],
            'ext' => $ext,
            'goods_name' => '代上课服务',
            'task_desc' => $data['class_subject'],
            'total_fee' => floatval($data['total_fee']),
            'actual_fee' => floatval($data['total_fee']),
            'base_fee' => floatval($data['total_fee']),
            'distance_fee' => 0,
            'weight_fee' => 0,
            'urgent_fee' => 0,
            'tip_fee' => 0,
            'status' => 0,
        ];

        $result = (new OrderService())->create($orderData);
        return success($result);
    }

    /**
     * 代上课列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', ''],
        ]);
        $params['task_type'] = 'CLASS';
        if ($params['status'] === '') {
            $params['status'] = '0,10,20,30,40';
        }
        $result = (new OrderService())->getOrderList($params);
        return success($result);
    }

    /**
     * 代上课详情
     */
    public function detail($id)
    {
        $detail = (new OrderService())->getOrderDetail($id);
        if ($detail && $detail['task_type'] !== 'CLASS') {
            return fail('订单类型错误');
        }
        return success($detail);
    }
}
