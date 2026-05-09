<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\order;

/**
 * 手机端订单详情路径监听器
 */
class WapOrderDetailPathListener
{
    public function handle($data)
    {
        $trade_type = $data['trade_type'] ?? '';
        
        // 订单详情路径
        if ($trade_type == 'sd_xiaoyuan_order') {
            return '/addon/sd_xiaoyuan/pages/order/detail?id=' . $data['trade_id'];
        }
        
        // 任务详情路径
        if ($trade_type == 'sd_xiaoyuan_task') {
            return '/addon/sd_xiaoyuan/pages/task/detail?id=' . $data['trade_id'];
        }
        
        return '';
    }
}
