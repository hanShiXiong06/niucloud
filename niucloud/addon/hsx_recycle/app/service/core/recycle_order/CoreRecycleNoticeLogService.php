<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\model\order\RecycleNoticeLog;
use app\service\core\notice\NoticeService;
use core\base\BaseCoreService;

/**
 * 回收通知发送日志服务
 */
class CoreRecycleNoticeLogService extends BaseCoreService
{
    private NoticeService $noticeService;

    public function __construct()
    {
        parent::__construct();
        $this->noticeService = new NoticeService();
    }

    public function createPending(array $data): int
    {
        $now = time();
        $model = RecycleNoticeLog::create([
            'site_id' => (int)($data['site_id'] ?? 0),
            'order_id' => (int)($data['order_id'] ?? 0),
            'order_no' => (string)($data['order_no'] ?? ''),
            'member_id' => (int)($data['member_id'] ?? 0),
            'notice_key' => (string)($data['notice_key'] ?? ''),
            'scene' => (string)($data['scene'] ?? ''),
            'receiver_type' => (string)($data['receiver_type'] ?? 'member'),
            'receiver_id' => (int)($data['receiver_id'] ?? $data['member_id'] ?? 0),
            'device_ids' => $this->jsonEncode(array_values($data['device_ids'] ?? [])),
            'device_count' => (int)($data['device_count'] ?? count($data['device_ids'] ?? [])),
            'target_page' => (string)($data['target_page'] ?? ''),
            'request_data' => $this->jsonEncode($data['request_data'] ?? []),
            'response_data' => '',
            'status' => 0,
            'fail_reason' => '',
            'send_time' => 0,
            'create_at' => $now,
            'update_at' => $now,
        ]);

        return (int)$model->id;
    }

    public function markSuccess(int $id, $response = null): void
    {
        if ($id <= 0) {
            return;
        }

        RecycleNoticeLog::where('id', $id)->update([
            'status' => 1,
            'response_data' => $this->jsonEncode($response),
            'fail_reason' => '',
            'send_time' => time(),
            'update_at' => time(),
        ]);
    }

    public function markFail(int $id, string $reason, $response = null): void
    {
        if ($id <= 0) {
            return;
        }

        RecycleNoticeLog::where('id', $id)->update([
            'status' => 2,
            'response_data' => $this->jsonEncode($response),
            'fail_reason' => substr($reason, 0, 1000),
            'send_time' => time(),
            'update_at' => time(),
        ]);
    }

    public function sendWithLog(int $siteId, string $noticeKey, array $payload, array $meta = []): array
    {
        $logId = $this->createPending([
            'site_id' => $siteId,
            'order_id' => (int)($meta['order_id'] ?? $payload['order_id'] ?? 0),
            'order_no' => (string)($meta['order_no'] ?? $payload['order_no'] ?? ''),
            'member_id' => (int)($meta['member_id'] ?? $payload['member_id'] ?? 0),
            'notice_key' => $noticeKey,
            'scene' => (string)($meta['scene'] ?? ''),
            'receiver_type' => (string)($meta['receiver_type'] ?? 'member'),
            'receiver_id' => (int)($meta['receiver_id'] ?? $payload['member_id'] ?? 0),
            'device_ids' => array_values($meta['device_ids'] ?? []),
            'device_count' => (int)($meta['device_count'] ?? count($meta['device_ids'] ?? [])),
            'target_page' => (string)($meta['target_page'] ?? $payload['__weapp_page'] ?? ''),
            'request_data' => $payload,
        ]);

        try {
            $result = $this->noticeService->send($siteId, $noticeKey, $payload);
            if ($result === false) {
                $this->markFail($logId, '通知模板未启用或未配置', $result);
                return ['success' => false, 'log_id' => $logId, 'message' => '通知模板未启用或未配置'];
            }

            $this->markSuccess($logId, $result);
            return ['success' => true, 'log_id' => $logId, 'result' => $result];
        } catch (\Throwable $e) {
            $this->markFail($logId, $e->getMessage(), [
                'class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'log_id' => $logId, 'message' => $e->getMessage()];
        }
    }

    private function jsonEncode($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : $json;
    }
}
