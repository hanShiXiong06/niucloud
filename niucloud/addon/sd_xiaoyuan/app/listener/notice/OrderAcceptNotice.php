<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice;

use core\exception\CommonException;

/**
 * 订单接单通知
 */
class OrderAcceptNotice
{
    public function handle($data)
    {
        $type = $data['type'] ?? '';
        
        // 任务接单通知
        if ($type == 'TASK') {
            return [
                'key' => 'SD_XIAOYUAN_TASK_ACCEPT',
                'title' => '任务已被接单',
                'content' => '您的任务已被接单，接单员正在处理中',
                'data' => $data['data'] ?? [],
            ];
        }
        
        // 订单接单通知
        if ($type == 'ORDER') {
            return [
                'key' => 'SD_XIAOYUAN_ORDER_ACCEPT',
                'title' => '订单已被接单',
                'content' => '您的订单已被接单，接单员正在处理中',
                'data' => $data['data'] ?? [],
            ];
        }
         return ;
        // throw new CommonException('不支持的通知类型');
    }
}
