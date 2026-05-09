<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\pay;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Task;

/**
 * 支付创建监听器
 */
class PayCreateListener
{
    public function handle($data)
    {
        $trade_type = $data['trade_type'] ?? '';
        
        // 订单支付
        if ($trade_type == 'sd_xiaoyuan_order') {
            $order_id = $data['trade_id'] ?? 0;
            if (!$order_id) return true;
            
            $order = (new Order())->withoutGlobalScope()->where('id', $order_id)->find();
            if (!$order) return [];
            
            // 使用total_fee作为支付金额（actual_fee用于优惠后的金额，如果没有优惠则等于total_fee）
            $money = floatval($order['total_fee']);
            
            return [
                'main_type' => 'member',
                'main_id' => $order['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $order_id,
                'body' => '校园帮订单支付',
            ];
        }
        
        // 打赏支付
        if ($trade_type == 'sd_xiaoyuan_tip') {
            $tip_id = $data['trade_id'] ?? 0;
            if (!$tip_id) return true;
            
            $tipOrder = (new \addon\sd_xiaoyuan\app\model\TipOrder())->where('id', $tip_id)->find();
            if (!$tipOrder) return true;
            
            return [
                'main_type' => 'member',
                'main_id' => $tipOrder['member_id'],
                'money' => $tipOrder['amount'],
                'trade_type' => $trade_type,
                'trade_id' => $tip_id,
                'body' => '校园帮打赏',
            ];
        }
        
        // 任务支付
        if ($trade_type == 'sd_xiaoyuan_task') {
            $task_id = $data['trade_id'] ?? 0;
            if (!$task_id) return true;
            
            $task = (new Task())->where('id', $task_id)->find();
            if (!$task) return true;
            
            return [
                'main_type' => 'member',
                'main_id' => $task['member_id'],
                'money' => $task['total_amount'],
                'trade_type' => $trade_type,
                'trade_id' => $task_id,
                'body' => '校园帮任务支付',
            ];
        }
        
        // 房屋租赁支付
        if ($trade_type == 'sd_xiaoyuan_house') {
            $order_id = $data['trade_id'] ?? 0;
            if (!$order_id) return true;
            
            $houseOrder = (new \addon\sd_xiaoyuan\app\model\HouseOrder())->where('id', $order_id)->find();
            if (!$houseOrder) return true;
            
            $house = (new \addon\sd_xiaoyuan\app\model\House())->where('id', $houseOrder['house_id'])->find();
            $money = $house ? $house['deposit'] : 0;
            
            return [
                'main_type' => 'member',
                'main_id' => $houseOrder['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $order_id,
                'body' => '房屋租赁押金支付',
            ];
        }
        
        return true;
    }
}
