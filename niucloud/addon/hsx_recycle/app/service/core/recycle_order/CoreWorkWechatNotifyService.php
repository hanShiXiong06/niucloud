<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\model\order\RecycleNoticeLog;
use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService;
use core\base\BaseCoreService;
use core\exception\CommonException;

/**
 * 企业微信群机器人通知服务
 */
class CoreWorkWechatNotifyService extends BaseCoreService
{
    public const SCENE_ORDER_URGE = 'order_urge';

    private CoreRecycleNoticeLogService $logService;

    public function __construct()
    {
        parent::__construct();
        $this->logService = new CoreRecycleNoticeLogService();
    }

    public function sendOrderUrge(int $siteId, array $order, array $devices = [], array $operator = []): array
    {
        $channel = $this->getChannelConfig($siteId, self::SCENE_ORDER_URGE);
        $orderId = (int)($order['id'] ?? 0);
        $orderNo = (string)($order['order_no'] ?? '');
        $memberId = (int)($order['member_id'] ?? 0);
        $deviceIds = array_values(array_filter(array_map(static function ($device) {
            return (int)($device['id'] ?? 0);
        }, $devices)));

        $logData = [
            'site_id' => $siteId,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'member_id' => $memberId,
            'notice_key' => 'work_wechat_order_urge',
            'scene' => self::SCENE_ORDER_URGE,
            'receiver_type' => 'work_wechat_group',
            'receiver_id' => 0,
            'device_ids' => $deviceIds,
            'device_count' => count($devices),
            'target_page' => '',
            'request_data' => [
                'channel' => $this->safeChannelForLog($channel),
                'operator' => $operator,
            ],
        ];

        if (empty($channel['enabled'])) {
            $logId = $this->logService->createPending($logData);
            $this->logService->markSkipped($logId, '订单催办群通知未启用');
            return ['success' => true, 'skipped' => true, 'message' => '订单催办通知未启用'];
        }

        if (empty($channel['webhook_url'])) {
            $logId = $this->logService->createPending($logData);
            $this->logService->markFail($logId, '未配置企业微信群机器人 Webhook 地址');
            throw new CommonException('未配置企业微信群机器人 Webhook 地址');
        }

        $dedupeSeconds = max(0, (int)($channel['dedupe_minutes'] ?? 0) * 60);
        if ($dedupeSeconds > 0) {
            $duplicate = $this->findRecentSuccess($siteId, $orderId, 'work_wechat_order_urge', self::SCENE_ORDER_URGE, $dedupeSeconds);
            if (!empty($duplicate)) {
                $logId = $this->logService->createPending($logData);
                $this->logService->markSkipped($logId, '短时间内已催办，跳过重复推送', [
                    'duplicate_log_id' => (int)$duplicate['id'],
                    'dedupe_minutes' => (int)($channel['dedupe_minutes'] ?? 0),
                ]);
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => sprintf('%d 分钟内已催办过，请稍后再试', (int)($channel['dedupe_minutes'] ?? 0)),
                    'log_id' => $logId,
                ];
            }
        }

        $dailyLimit = (int)($channel['daily_limit'] ?? 0);
        if ($dailyLimit > 0 && $this->countTodaySuccess($siteId, $orderId, 'work_wechat_order_urge', self::SCENE_ORDER_URGE) >= $dailyLimit) {
            $logId = $this->logService->createPending($logData);
            $this->logService->markSkipped($logId, '今日催办次数已达上限', ['daily_limit' => $dailyLimit]);
            return [
                'success' => true,
                'skipped' => true,
                'message' => '今日催办次数已达上限',
                'log_id' => $logId,
            ];
        }

        $content = $this->buildOrderUrgeText($order, $devices, $operator);
        $payload = [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $content,
            ],
        ];
        $logData['request_data']['payload'] = $payload;
        $logId = $this->logService->createPending($logData);

        try {
            $response = $this->postJson((string)$channel['webhook_url'], $payload);
            if ((int)($response['errcode'] ?? -1) !== 0) {
                $reason = (string)($response['errmsg'] ?? '企业微信推送失败');
                $this->logService->markFail($logId, $reason, $response);
                throw new CommonException('企业微信推送失败：' . $reason);
            }
            $this->logService->markSuccess($logId, $response);
            return [
                'success' => true,
                'skipped' => false,
                'message' => '已催办工作人员',
                'log_id' => $logId,
            ];
        } catch (\Throwable $e) {
            $this->logService->markFail($logId, $e->getMessage(), [
                'class' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e instanceof CommonException ? $e : new CommonException('企业微信推送失败：' . $e->getMessage());
        }
    }

    private function getChannelConfig(int $siteId, string $scene): array
    {
        $config = (new OrderSubmitConfigService())->getConfig($siteId);
        $workWechat = $config['work_wechat'] ?? [];
        $channel = $workWechat['channels'][$scene] ?? [];
        if (empty($workWechat['enabled'])) {
            $channel['enabled'] = 0;
        }
        return is_array($channel) ? $channel : [];
    }

    private function buildOrderUrgeText(array $order, array $devices, array $operator): string
    {
        $statusName = (string)($order['status_name'] ?? '');
        $deliveryTypeName = (string)($order['delivery_type_name'] ?? '');
        $customerName = (string)($order['customer_name'] ?? $order['member']['nickname'] ?? '未填写');
        $customerPhone = (string)($order['customer_phone'] ?? $order['member']['mobile'] ?? '');
        $deviceSummary = $this->buildDeviceSummary($devices);
        $createTime = $this->formatDateTime($order['create_at'] ?? null);
        $urgeTime = date('Y-m-d H:i:s');
        $operatorName = (string)($operator['name'] ?? '用户');

        return implode("\n", array_filter([
            "### 用户催办回收订单",
            "> 有用户点击了催一下，请相关工作人员及时处理。",
            "",
            "**订单编号：**" . ((string)($order['order_no'] ?? '') ?: '-'),
            "**订单状态：**" . ($statusName ?: (string)($order['status'] ?? '-')),
            "**交付方式：**" . ($deliveryTypeName ?: '-'),
            "**客户信息：**" . trim($customerName . ' ' . $customerPhone),
            "**设备数量：**" . count($devices) . " 台",
            "**主要设备：**" . $deviceSummary,
            "**提交时间：**" . $createTime,
            "**催办时间：**" . $urgeTime,
            "**催办来源：**" . $operatorName,
        ], static fn($line) => $line !== null));
    }

    private function formatDateTime($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_numeric($value)) {
            $timestamp = (int)$value;
            if ($timestamp > 9999999999) {
                $timestamp = (int)floor($timestamp / 1000);
            }
            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
        }

        $timestamp = strtotime((string)$value);
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
    }

    private function buildDeviceSummary(array $devices): string
    {
        if (empty($devices)) {
            return '暂无设备明细';
        }

        $names = [];
        foreach (array_slice($devices, 0, 3) as $device) {
            $parts = array_filter([
                trim((string)($device['model'] ?? '')),
                trim((string)($device['capacity'] ?? '')),
                trim((string)($device['color'] ?? '')),
                trim((string)($device['imei'] ?? '')),
            ]);
            $names[] = $parts ? implode(' ', $parts) : ('设备ID ' . (int)($device['id'] ?? 0));
        }

        $suffix = count($devices) > 3 ? ' 等' : '';
        return implode('；', $names) . $suffix;
    }

    private function postJson(string $url, array $payload): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $error !== '') {
            throw new CommonException('请求企业微信失败：' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new CommonException('企业微信接口 HTTP 状态异常：' . $status);
        }

        $decoded = json_decode((string)$body, true);
        return is_array($decoded) ? $decoded : ['raw' => (string)$body];
    }

    private function findRecentSuccess(int $siteId, int $orderId, string $noticeKey, string $scene, int $seconds): array
    {
        $row = RecycleNoticeLog::where([
            ['site_id', '=', $siteId],
            ['order_id', '=', $orderId],
            ['notice_key', '=', $noticeKey],
            ['scene', '=', $scene],
            ['receiver_type', '=', 'work_wechat_group'],
            ['status', '=', CoreRecycleNoticeLogService::STATUS_SUCCESS],
            ['create_at', '>=', time() - $seconds],
        ])->order('id desc')->findOrEmpty();

        return $row->isEmpty() ? [] : $row->toArray();
    }

    private function countTodaySuccess(int $siteId, int $orderId, string $noticeKey, string $scene): int
    {
        return (int)RecycleNoticeLog::where([
            ['site_id', '=', $siteId],
            ['order_id', '=', $orderId],
            ['notice_key', '=', $noticeKey],
            ['scene', '=', $scene],
            ['receiver_type', '=', 'work_wechat_group'],
            ['status', '=', CoreRecycleNoticeLogService::STATUS_SUCCESS],
            ['create_at', '>=', strtotime(date('Y-m-d 00:00:00'))],
        ])->count();
    }

    private function safeChannelForLog(array $channel): array
    {
        if (!empty($channel['webhook_url'])) {
            $channel['webhook_url'] = '******';
        }
        return $channel;
    }
}
