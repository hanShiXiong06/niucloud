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
    public const STATUS_PENDING = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAIL = 2;
    public const STATUS_SKIPPED = 3;

    private NoticeService $noticeService;

    public function __construct()
    {
        parent::__construct();
        $this->noticeService = new NoticeService();
    }

    public function createPending(array $data): int
    {
        $now = time();
        $deviceIds = $this->normalizeDeviceIds($data['device_ids'] ?? []);
        $model = RecycleNoticeLog::create([
            'site_id' => (int)($data['site_id'] ?? 0),
            'order_id' => (int)($data['order_id'] ?? 0),
            'order_no' => (string)($data['order_no'] ?? ''),
            'member_id' => (int)($data['member_id'] ?? 0),
            'notice_key' => (string)($data['notice_key'] ?? ''),
            'scene' => (string)($data['scene'] ?? ''),
            'receiver_type' => (string)($data['receiver_type'] ?? 'member'),
            'receiver_id' => (int)($data['receiver_id'] ?? $data['member_id'] ?? 0),
            'device_ids' => $this->jsonEncode($deviceIds),
            'device_count' => (int)($data['device_count'] ?? count($deviceIds)),
            'target_page' => (string)($data['target_page'] ?? ''),
            'request_data' => $this->jsonEncode($data['request_data'] ?? []),
            'response_data' => '',
            'status' => self::STATUS_PENDING,
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
            'status' => self::STATUS_SUCCESS,
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
            'status' => self::STATUS_FAIL,
            'response_data' => $this->jsonEncode($response),
            'fail_reason' => substr($reason, 0, 1000),
            'send_time' => time(),
            'update_at' => time(),
        ]);
    }

    public function markSkipped(int $id, string $reason, $response = null): void
    {
        if ($id <= 0) {
            return;
        }

        RecycleNoticeLog::where('id', $id)->update([
            'status' => self::STATUS_SKIPPED,
            'response_data' => $this->jsonEncode($response),
            'fail_reason' => substr($reason, 0, 1000),
            'send_time' => 0,
            'update_at' => time(),
        ]);
    }

    public function sendWithLog(int $siteId, string $noticeKey, array $payload, array $meta = []): array
    {
        $logData = [
            'site_id' => $siteId,
            'order_id' => (int)($meta['order_id'] ?? $payload['order_id'] ?? 0),
            'order_no' => (string)($meta['order_no'] ?? $payload['order_no'] ?? ''),
            'member_id' => (int)($meta['member_id'] ?? $payload['member_id'] ?? 0),
            'notice_key' => $noticeKey,
            'scene' => (string)($meta['scene'] ?? ''),
            'receiver_type' => (string)($meta['receiver_type'] ?? 'member'),
            'receiver_id' => (int)($meta['receiver_id'] ?? $payload['member_id'] ?? 0),
            'device_ids' => $this->normalizeDeviceIds($meta['device_ids'] ?? []),
            'device_count' => (int)($meta['device_count'] ?? count($meta['device_ids'] ?? [])),
            'target_page' => (string)($meta['target_page'] ?? $payload['__weapp_page'] ?? ''),
            'request_data' => $payload,
        ];

        $dedupeWindow = (int)($meta['dedupe_window'] ?? 0);
        if (!empty($meta['dedupe']) && $dedupeWindow > 0) {
            $duplicate = $this->findSuccessDuplicate($logData, $dedupeWindow);
            if (!empty($duplicate)) {
                $reason = '短时间内已存在相同通知，已跳过重复发送';
                $logId = $this->createPending($logData);
                $this->markSkipped($logId, $reason, [
                    'duplicate_log_id' => (int)($duplicate['id'] ?? 0),
                    'duplicate_send_time' => !empty($duplicate['send_time']) ? date('Y-m-d H:i:s', (int)$duplicate['send_time']) : '',
                    'dedupe_window' => $dedupeWindow,
                ]);

                return [
                    'success' => true,
                    'skipped' => true,
                    'log_id' => $logId,
                    'duplicate_log_id' => (int)($duplicate['id'] ?? 0),
                    'message' => $reason,
                ];
            }
        }

        $logId = $this->createPending($logData);

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

    private function findSuccessDuplicate(array $logData, int $window): array
    {
        $query = RecycleNoticeLog::where([
            ['site_id', '=', (int)($logData['site_id'] ?? 0)],
            ['order_id', '=', (int)($logData['order_id'] ?? 0)],
            ['member_id', '=', (int)($logData['member_id'] ?? 0)],
            ['notice_key', '=', (string)($logData['notice_key'] ?? '')],
            ['scene', '=', (string)($logData['scene'] ?? '')],
            ['receiver_type', '=', (string)($logData['receiver_type'] ?? 'member')],
            ['receiver_id', '=', (int)($logData['receiver_id'] ?? 0)],
            ['device_ids', '=', $this->jsonEncode($this->normalizeDeviceIds($logData['device_ids'] ?? []))],
            ['status', '=', self::STATUS_SUCCESS],
        ]);

        $startTime = time() - $window;
        if ($startTime > 0) {
            $query->where('create_at', '>=', $startTime);
        }

        $duplicate = $query->order('id desc')->findOrEmpty();
        return $duplicate->isEmpty() ? [] : $duplicate->toArray();
    }

    private function normalizeDeviceIds($deviceIds): array
    {
        if (!is_array($deviceIds)) {
            return [];
        }

        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        sort($deviceIds);
        return $deviceIds;
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
