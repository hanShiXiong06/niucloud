<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\recycle\app\job\order_event;

use core\base\BaseJob;
use think\facade\Log;

/**
 * 订单同意后事件
 */
class OrderAgreeAfter extends BaseJob
{

    public function doJob($data)
    {
        try {
            Log::info('【回收通知】订单同意后事件Job开始执行', [
                'data' => $data,
                'time' => date('Y-m-d H:i:s')
            ]);

            // 验证数据完整性
            if (empty($data) || !isset($data['data'])) {
                Log::error('【回收通知】订单同意后事件数据为空或格式错误', [
                    'received_data' => $data
                ]);
                return false;
            }

            $eventData = $data['data'];
            
            // 记录事件详细信息
            Log::info('【回收通知】订单同意后事件数据', [
                'order_id' => $eventData['order_id'] ?? 'unknown',
                'site_id' => $eventData['site_id'] ?? 'unknown',
                'event_data' => $eventData
            ]);

            // 触发事件
            event('AfterRecycleOrderAgree', $eventData);
            
            Log::info('【回收通知】订单同意后事件执行成功', [
                'order_id' => $eventData['order_id'] ?? 'unknown',
                'event_triggered' => 'AfterRecycleOrderAgree'
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('【回收通知】订单同意后事件执行异常', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'data' => $data ?? 'null',
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

}
