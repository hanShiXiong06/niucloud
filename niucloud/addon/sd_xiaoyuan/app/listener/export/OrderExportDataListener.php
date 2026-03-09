<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\export;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Task;

/**
 * 订单导出数据监听器
 */
class OrderExportDataListener
{
    public function handle($data)
    {
        $type = $data['type'] ?? '';
        
        if ($type == 'sd_xiaoyuan_order') {
            $order = (new Order())->where('id', $data['id'])->find();
            if (!$order) return [];
            
            return [
                'order_no' => $order['order_no'],
                'task_type' => $order['task_type'],
                'total_fee' => $order['total_fee'],
                'status' => $order['status'],
                'create_time' => date('Y-m-d H:i:s', $order['create_time']),
            ];
        }
        
        if ($type == 'sd_xiaoyuan_task') {
            $task = (new Task())->where('id', $data['id'])->find();
            if (!$task) return [];
            
            return [
                'task_no' => $task['task_no'],
                'task_type' => $task['task_type'],
                'reward' => $task['reward'],
                'status' => $task['status'],
                'create_time' => date('Y-m-d H:i:s', $task['create_time']),
            ];
        }
        
        return [];
    }
}
