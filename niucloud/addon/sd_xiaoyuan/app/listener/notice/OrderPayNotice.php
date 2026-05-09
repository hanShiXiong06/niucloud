<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice;

use core\exception\CommonException;

/**
 * 订单支付通知
 */
class OrderPayNotice
{
    public function handle($data)
    {
        $type = $data['type'] ?? '';
        
        // 任务支付通知
        if ($type == 'TASK') {
            return [
                'key' => 'SD_XIAOYUAN_TASK_PAY',
                'title' => '任务支付成功',
                'content' => '您的任务已支付成功，等待接单员接单',
                'data' => $data['data'] ?? [],
            ];
        }
        
        // 订单支付通知
        if ($type == 'ORDER') {
            return [
                'key' => 'SD_XIAOYUAN_ORDER_PAY',
                'title' => '订单支付成功',
                'content' => '您的订单已支付成功，等待接单员接单',
                'data' => $data['data'] ?? [],
            ];
        }
         return ;
        // throw new CommonException('不支持的通知类型');
    }
}
