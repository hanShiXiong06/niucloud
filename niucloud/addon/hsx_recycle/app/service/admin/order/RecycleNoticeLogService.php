<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\model\order\RecycleNoticeLog;
use core\base\BaseAdminService;

/**
 * 回收通知日志管理服务
 */
class RecycleNoticeLogService extends BaseAdminService
{
    public function getOrderLogs(int $orderId): array
    {
        $logs = RecycleNoticeLog::where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])
            ->order('id desc')
            ->limit(50)
            ->select()
            ->toArray();

        return array_map(function (array $item) {
            $item['status_name'] = $this->getStatusName((int)($item['status'] ?? 0));
            $item['scene_name'] = $this->getSceneName((string)($item['scene'] ?? ''));
            $item['device_ids'] = $this->jsonDecodeArray($item['device_ids'] ?? '');
            $item['request_data'] = $this->jsonDecodeArray($item['request_data'] ?? '');
            $item['response_data'] = $this->jsonDecodeArray($item['response_data'] ?? '');
            $item['send_time_text'] = !empty($item['send_time']) ? date('Y-m-d H:i:s', (int)$item['send_time']) : '';
            $item['create_time_text'] = !empty($item['create_at']) ? date('Y-m-d H:i:s', (int)$item['create_at']) : '';
            return $item;
        }, $logs);
    }

    private function getStatusName(int $status): string
    {
        return [
            0 => '待发送',
            1 => '发送成功',
            2 => '发送失败',
        ][$status] ?? '未知';
    }

    private function getSceneName(string $scene): string
    {
        return [
            'order_confirm' => '整单待客户确认',
            'device_confirm' => '设备待客户确认',
            'manual_order_confirm' => '手动推送整单确认',
            'manual_device_confirm' => '手动推送设备确认',
        ][$scene] ?? ($scene ?: '订单通知');
    }

    private function jsonDecodeArray($value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
