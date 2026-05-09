<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\pay;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Task;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use app\model\pay\Refund;

/**
 * 退款成功监听器
 */
class RefundSuccessListener
{
    public function handle($data)
    {
        $trade_type = $data['trade_type'] ?? '';
        
        // 订单退款成功
        if ($trade_type == 'sd_xiaoyuan_order') {
            $order_id = $data['trade_id'] ?? 0;
            if (!$order_id) return true;
            
            $order = (new Order())->where('id', $order_id)->find();
            if (!$order) return true;

            $siteId = (int)($data['site_id'] ?? $order['site_id'] ?? 0);
            $refundNo = (string)($data['refund_no'] ?? '');
            $refundMoney = 0.0;
            if ($refundNo !== '' && $siteId > 0) {
                $refundMoney = (float)((new Refund())->where([
                    ['site_id', '=', $siteId],
                    ['refund_no', '=', $refundNo],
                ])->value('money') ?: 0);
            }
            
            $order->save([
                'status' => 91, // 已退款
                'pay_status' => 2,
                'refund_fee' => $refundMoney > 0 ? $refundMoney : ($order['refund_fee'] ?? 0),
                'refund_time' => time(),
                'update_time' => time(),
            ]);
            
            // 发送系统消息
            (new MessageService())->send($order['member_id'], 'ORDER', '订单退款成功', '您的订单已退款成功', ['order_id' => $order_id]);
            
            return true;
        }
        
        // 任务退款成功
        if ($trade_type == 'sd_xiaoyuan_task') {
            $task_id = $data['trade_id'] ?? 0;
            if (!$task_id) return true;
            
            $task = (new Task())->where('id', $task_id)->find();
            if (!$task) return true;
            
            $task->save([
                'status' => 91, // 已退款
                'update_time' => time(),
            ]);
            
            // 发送系统消息
            (new MessageService())->send($task['member_id'], 'TASK', '任务退款成功', '您的任务已退款成功', ['task_id' => $task_id]);
            
            return true;
        }
        
        return true;
    }
}
