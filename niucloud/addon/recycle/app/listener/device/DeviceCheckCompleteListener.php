<?php
namespace addon\recycle\app\listener\device;

use think\facade\Log;

/**
 * 设备质检完成事件监听
 * 注意：自动打印已迁移到 RecycleDeviceService::completeCheck() 中通过打印场景控制
 */
class DeviceCheckCompleteListener
{
    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle($params)
    {
        if (empty($params['site_id']) || empty($params['device_id'])) {
            return '';
        }

        Log::info("设备质检完成事件触发，设备ID：" . $params['device_id'], [
            'site_id' => $params['site_id'],
            'device_id' => $params['device_id'],
            'status' => $params['status'] ?? ''
        ]);

        // 自动打印已由 RecycleDeviceService::completeCheck() 中的打印场景逻辑统一处理
        // 不再在此处重复打印

        return '';
    }
}
