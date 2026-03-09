<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice;

use core\exception\CommonException;

/**
 * 订单完成通知
 */
class OrderCompleteNotice
{
    public function handle($data)
    {
        $type = $data['type'] ?? '';
        
        // 任务完成通知
        if ($type == 'TASK') {
            return [
                'key' => 'SD_XIAOYUAN_TASK_COMPLETE',
                'title' => '任务已完成',
                'content' => '您的任务已完成，请及时确认',
                'data' => $data['data'] ?? [],
            ];
        }
        
        // 订单完成通知
        if ($type == 'ORDER') {
            return [
                'key' => 'SD_XIAOYUAN_ORDER_COMPLETE',
                'title' => '订单已完成',
                'content' => '您的订单已完成，请及时确认',
                'data' => $data['data'] ?? [],
            ];
        }
        
        throw new CommonException('不支持的通知类型');
    }
}
