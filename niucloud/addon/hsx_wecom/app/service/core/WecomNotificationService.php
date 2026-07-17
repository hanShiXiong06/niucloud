<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\job\MessageSend;
use addon\hsx_wecom\app\model\WecomMessageLog;
use addon\hsx_wecom\app\model\WecomStaffBinding;
use app\model\sys\SysUser;
use think\facade\Log;

final class WecomNotificationService
{
    public function enqueueBusinessReport(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $eventId = trim((string)($event['event_id'] ?? ''));
        $receiverUids = array_values(array_unique(array_filter(array_map('intval', (array)($event['receiver_uids'] ?? [])))));
        if ($siteId <= 0 || $eventId === '') return ['accepted' => false, 'reason' => 'invalid_event'];
        $config = (new WecomConfigService())->get($siteId);
        if (empty($config['enabled']) || empty($config['report_notice_enabled'])) {
            return ['accepted' => false, 'queued' => 0, 'reason' => 'report_notice_disabled'];
        }
        if ($receiverUids === []) return ['accepted' => false, 'queued' => 0, 'reason' => 'no_receivers'];

        $users = SysUser::whereIn('uid', $receiverUids)->field('uid,username,real_name')->select()->toArray();
        $receiverNames = [];
        foreach ($users as $user) {
            $name = trim((string)($user['real_name'] ?? '')) ?: trim((string)($user['username'] ?? ''));
            $receiverNames[(int)$user['uid']] = $name !== '' ? $name : ('员工' . (int)$user['uid']);
        }

        $queued = 0;
        $accepted = false;
        $pendingIds = [];
        foreach ($receiverUids as $receiverUid) {
            $messageEventId = $eventId . ':receiver:' . $receiverUid;
            $exists = WecomMessageLog::where([['site_id', '=', $siteId], ['event_id', '=', $messageEventId]])->findOrEmpty();
            $binding = WecomStaffBinding::where([
                ['site_id', '=', $siteId], ['uid', '=', $receiverUid], ['status', '=', 1],
            ])->findOrEmpty();
            $targetError = $this->targetContractError($event);
            $status = 'pending';
            $error = '';
            if ($binding->isEmpty() || trim((string)$binding->wecom_userid) === '') {
                $status = 'skipped';
                $error = '报告接收人尚未绑定企业微信账号';
            } elseif ($targetError !== '') {
                $status = 'skipped';
                $error = $targetError;
            }
            $target = $this->target($event, $config);
            $payload = $event;
            $payload['event_id'] = $messageEventId;
            $payload['wecom_target'] = $target;
            $messageData = [
                'receiver_name' => (string)($receiverNames[$receiverUid] ?? ('员工' . $receiverUid)),
                'wecom_userid' => $binding->isEmpty() ? '' : trim((string)$binding->wecom_userid),
                'title' => trim((string)($event['title'] ?? '经营报告')),
                'content' => $this->reportDescription($event),
                'target_url' => $target['web_url'],
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'update_at' => time(),
            ];
            if (!$exists->isEmpty()) {
                if ((string)$exists->status === 'skipped' && $status === 'pending') {
                    $exists->save(array_merge($messageData, [
                        'status' => 'pending', 'retry_count' => 0, 'next_retry_at' => time(),
                        'error_message' => '', 'response_json' => '', 'sent_at' => 0,
                    ]));
                    $queued++;
                    $accepted = true;
                    $pendingIds[] = (int)$exists->id;
                } elseif (in_array((string)$exists->status, ['pending', 'failed', 'success'], true)) {
                    $accepted = true;
                }
                continue;
            }
            $log = WecomMessageLog::create([
                'site_id' => $siteId, 'event_id' => $messageEventId, 'scene' => 'business_report',
                'source_plugin' => 'hsx_performance', 'source_type' => (string)($event['report_type'] ?? 'report'),
                'source_id' => (int)($event['report_id'] ?? 0), 'receiver_uid' => $receiverUid,
                'receiver_name' => $messageData['receiver_name'], 'wecom_userid' => $messageData['wecom_userid'],
                'title' => $messageData['title'], 'content' => $messageData['content'],
                'target_url' => $messageData['target_url'], 'payload_json' => $messageData['payload_json'],
                'status' => $status, 'retry_count' => 0, 'next_retry_at' => $status === 'pending' ? time() : 0,
                'error_message' => $error, 'create_at' => time(), 'update_at' => time(),
            ]);
            $queued++;
            if ($status === 'pending') {
                $accepted = true;
                $pendingIds[] = (int)$log->id;
            }
        }
        $this->dispatchQueuedMessages($pendingIds);
        return ['accepted' => $accepted, 'queued' => $queued];
    }

    public function enqueueTaskAssigned(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $assigneeUid = (int)($event['assignee_uid'] ?? 0);
        $eventId = trim((string)($event['event_id'] ?? ''));
        if ($siteId <= 0 || $assigneeUid <= 0 || $eventId === '') {
            return ['queued' => false, 'reason' => 'invalid_event'];
        }
        $exists = WecomMessageLog::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$exists->isEmpty()) return ['queued' => false, 'duplicate' => true, 'id' => (int)$exists->id];

        $binding = WecomStaffBinding::where([
            ['site_id', '=', $siteId], ['uid', '=', $assigneeUid], ['status', '=', 1],
        ])->findOrEmpty();
        $config = (new WecomConfigService())->get($siteId);
        $status = 'pending';
        $error = '';
        $targetError = $this->targetContractError($event);
        if (empty($config['enabled']) || empty($config['task_notice_enabled'])) {
            $status = 'skipped';
            $error = '企业微信任务通知未启用';
        } elseif ($binding->isEmpty() || trim((string)$binding->wecom_userid) === '') {
            $status = 'skipped';
            $error = '责任人尚未绑定企业微信账号';
        } elseif ($targetError !== '') {
            $status = 'skipped';
            $error = $targetError;
        }

        $target = $this->target($event, $config);
        $event['wecom_target'] = $target;
        $log = WecomMessageLog::create([
            'site_id' => $siteId,
            'event_id' => $eventId,
            'scene' => 'task_assigned',
            'source_plugin' => trim((string)($event['source_plugin'] ?? '')),
            'source_type' => trim((string)($event['source_type'] ?? '')),
            'source_id' => (int)($event['source_id'] ?? 0),
            'receiver_uid' => $assigneeUid,
            'receiver_name' => trim((string)($event['assignee_name'] ?? '')),
            'wecom_userid' => $binding->isEmpty() ? '' : trim((string)$binding->wecom_userid),
            'title' => trim((string)($event['title'] ?? '新的待办任务')),
            'content' => $this->description($event),
            'target_url' => $target['web_url'],
            'payload_json' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'status' => $status,
            'retry_count' => 0,
            'next_retry_at' => $status === 'pending' ? time() : 0,
            'error_message' => $error,
            'create_at' => time(),
            'update_at' => time(),
        ]);
        if ($status === 'pending') $this->dispatchQueuedMessages([(int)$log->id]);
        return ['queued' => true, 'id' => (int)$log->id, 'status' => $status];
    }

    public function dispatch(int $id): bool
    {
        $log = WecomMessageLog::where('id', '=', $id)->findOrEmpty();
        if ($log->isEmpty() || !in_array((string)$log->status, ['pending', 'failed'], true)) return false;
        $config = (new WecomConfigService())->get((int)$log->site_id);
        try {
            $event = json_decode((string)$log->payload_json, true);
            if (!is_array($event)) $event = [];
            $isReport = (string)$log->scene === 'business_report';
            $invalidReason = $isReport ? '' : $this->taskInvalidReason($event);
            if ($invalidReason !== '') {
                $log->save([
                    'status' => 'skipped',
                    'next_retry_at' => 0,
                    'error_message' => $invalidReason,
                    'update_at' => time(),
                ]);
                return false;
            }
            $targetError = $this->targetContractError($event);
            if ($targetError !== '') throw new \RuntimeException($targetError);
            $target = $this->target($event, $config);
            $hasStructuredTarget = is_array($event['wecom_target'] ?? null) || is_array($event['target'] ?? null);
            if (!$hasStructuredTarget && trim((string)$log->target_url) !== '' && (string)($config['jump_mode'] ?? 'web') !== 'miniapp') {
                $target['web_url'] = trim((string)$log->target_url);
            }
            $response = (new WecomClient())->sendTaskCard($config, (string)$log->wecom_userid, [
                'title' => (string)$log->title,
                'description' => (string)$log->content,
                'plain_content' => $isReport ? $this->reportPlainDescription($event) : $this->plainDescription($event),
                'summary' => $isReport ? '经营数据已汇总，点击查看完整明细' : '任务已分配给你，请及时处理',
                'url' => $target['web_url'],
                'web_url' => $target['web_url'],
                'miniapp_appid' => $target['miniapp_appid'],
                'miniapp_path' => $target['miniapp_path'],
                'button_text' => $isReport ? '查看报告' : '查看任务',
                'source_desc' => $isReport ? '经营报告' : '业务待办',
            ]);
            $log->save([
                'status' => 'success',
                'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'sent_at' => time(),
                'next_retry_at' => 0,
                'error_message' => '',
                'update_at' => time(),
            ]);
            return true;
        } catch (\Throwable $e) {
            $retry = (int)$log->retry_count + 1;
            $log->save([
                'status' => 'failed',
                'retry_count' => $retry,
                'next_retry_at' => $retry >= 5 ? 0 : time() + min(1800, 60 * (2 ** max(0, $retry - 1))),
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'update_at' => time(),
            ]);
            Log::warning('企业微信任务消息发送失败', ['id' => $id, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function retryPending(int $limit = 50): int
    {
        $ids = WecomMessageLog::whereIn('status', ['pending', 'failed'])
            ->where('retry_count', '<', 5)
            ->where('next_retry_at', '>', 0)
            ->where('next_retry_at', '<=', time())
            ->order('id asc')->limit(max(1, min(200, $limit)))->column('id');
        $success = 0;
        foreach ($ids as $id) if ($this->dispatch((int)$id)) $success++;
        return $success;
    }

    /** 队列负责即时发送；计划任务继续作为失败重试和漏消费补偿。 */
    private function dispatchQueuedMessages(array $ids): void
    {
        if (!env('queue.state', false)) return;
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $id) {
            MessageSend::dispatch([$id]);
        }
    }

    private function description(array $event): string
    {
        $rows = [
            '<div class="gray">任务已分配给你，请及时处理</div>',
            '<div class="normal">任务：' . htmlspecialchars((string)($event['title'] ?? '待办任务')) . '</div>',
        ];
        if (!empty($event['business_no'])) $rows[] = '<div class="normal">业务单号：' . htmlspecialchars((string)$event['business_no']) . '</div>';
        if (!empty($event['imei'])) $rows[] = '<div class="normal">IMEI：' . htmlspecialchars((string)$event['imei']) . '</div>';
        if (!empty($event['assigner_name'])) $rows[] = '<div class="normal">分配人：' . htmlspecialchars((string)$event['assigner_name']) . '</div>';
        $rows[] = '<div class="highlight">今日待处理 ' . max(0, (int)($event['pending_count'] ?? 0)) . ' 项</div>';
        return implode('', $rows);
    }

    private function reportDescription(array $event): string
    {
        $summary = (array)($event['summary'] ?? []);
        $rows = ['<div class="gray">经营数据已自动汇总</div>'];
        $metrics = [
            'recycle_in_count' => '回收入库', 'sale_count' => '销售出库', 'recycle_return_count' => '回收退回',
            'stock_count' => '当前库存', 'turnover_rate' => '动销率',
        ];
        foreach ($metrics as $key => $label) {
            if (!array_key_exists($key, $summary)) continue;
            $suffix = $key === 'turnover_rate' ? '%' : ' 台';
            $rows[] = '<div class="normal">' . $label . '：' . htmlspecialchars((string)$summary[$key]) . $suffix . '</div>';
        }
        $staff = array_slice((array)($event['top_staff'] ?? []), 0, 5);
        if ($staff !== []) {
            $rows[] = '<div class="highlight">员工产出</div>';
            foreach ($staff as $item) {
                if (!is_array($item)) continue;
                $rows[] = '<div class="normal">' . htmlspecialchars((string)($item['name'] ?? '员工')) . ' · '
                    . htmlspecialchars((string)($item['role_name'] ?? '工作')) . ' ' . (int)($item['count'] ?? 0) . ' 项</div>';
            }
        }
        return implode('', $rows);
    }

    private function targetUrl(array $config, string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) return $path;
        $base = rtrim((string)($config['web_base_url'] ?? $config['admin_base_url'] ?? ''), '/');
        if ($base === '') return $path;
        return $base . '/' . ltrim($path, '/');
    }

    /** @return array{plugin:string,route_key:string,web_url:string,miniapp_appid:string,miniapp_path:string} */
    private function target(array $event, array $config): array
    {
        $businessTarget = is_array($event['target'] ?? null) ? $event['target'] : [];
        $businessTarget = $this->normalizeRecycleTaskTarget(
            $event,
            $businessTarget,
            (string)($config['recycle_task_target'] ?? 'detail')
        );
        $snapshot = is_array($event['wecom_target'] ?? null) ? $event['wecom_target'] : [];
        $dynamicRecycleTarget = (string)($businessTarget['plugin'] ?? '') === 'hsx_recycle';
        if (!$dynamicRecycleTarget && (array_key_exists('web_url', $snapshot) || array_key_exists('miniapp_path', $snapshot))) {
            return [
                'plugin' => trim((string)($snapshot['plugin'] ?? '')),
                'route_key' => trim((string)($snapshot['route_key'] ?? '')),
                'web_url' => trim((string)($snapshot['web_url'] ?? '')),
                'miniapp_appid' => trim((string)($snapshot['miniapp_appid'] ?? '')),
                'miniapp_path' => ltrim(trim((string)($snapshot['miniapp_path'] ?? '')), '/'),
            ];
        }
        $target = $businessTarget;
        $legacyPath = trim((string)($event['target_path'] ?? ''));
        $webPath = trim((string)($target['web_path'] ?? ''));
        $miniappPath = ltrim(trim((string)($target['miniapp_path'] ?? $legacyPath)), '/');
        $mode = (string)($config['jump_mode'] ?? 'web');
        if ($webPath === '' && $mode === 'web') $webPath = $legacyPath;
        $webUrl = $webPath !== '' ? $this->targetUrl($config, $webPath) : '';
        $miniappAppid = trim((string)($config['miniapp_appid'] ?? ''));
        if ($mode === 'web') {
            $miniappAppid = '';
            $miniappPath = '';
        } elseif ($mode === 'miniapp') {
            $webUrl = '';
        }
        return [
            'plugin' => trim((string)($target['plugin'] ?? '')),
            'route_key' => trim((string)($target['route_key'] ?? '')),
            'web_url' => $webUrl,
            'miniapp_appid' => $miniappAppid,
            'miniapp_path' => $miniappAppid !== '' ? $miniappPath : '',
        ];
    }

    /** 回收任务按站点配置在“待办列表”和“订单详情”之间切换。 */
    private function normalizeRecycleTaskTarget(array $event, array $target, string $targetMode): array
    {
        $routeKey = (string)($target['route_key'] ?? '');
        if ((string)($target['plugin'] ?? '') !== 'hsx_recycle'
            || (!str_starts_with($routeKey, 'hsx_recycle.task.') && !str_starts_with($routeKey, 'hsx_recycle.order.'))) {
            return $target;
        }
        $params = is_array($target['params'] ?? null) ? $target['params'] : [];
        $orderId = (int)($params['order_id'] ?? $params['id'] ?? 0);
        if ($orderId <= 0 && (string)($event['source_type'] ?? '') === 'recycle_order') {
            $orderId = (int)($event['source_id'] ?? 0);
        }
        if ($orderId <= 0) return $target;
        $deviceId = (int)($params['device_id'] ?? 0);
        if ($deviceId <= 0 && (string)($event['source_type'] ?? '') === 'recycle_device') {
            $deviceId = (int)($event['source_id'] ?? 0);
        }
        $stage = (string)($event['stage_key'] ?? $params['stage'] ?? '');
        if ($targetMode === 'list') {
            $keyword = $stage === 'sign'
                ? trim((string)($event['business_no'] ?? ''))
                : trim((string)($event['imei'] ?? ''));
            $listParams = array_filter([
                'stage' => $stage,
                'keyword' => $keyword,
            ], static fn($value): bool => $value !== '');
            $query = $listParams === [] ? '' : ('?' . http_build_query($listParams));
            return array_merge($target, [
                'route_key' => 'hsx_recycle.task.list',
                'params' => array_merge($listParams, ['order_id' => $orderId, 'device_id' => $deviceId]),
                'web_path' => 'site/stat/task' . $query,
                'miniapp_path' => 'addon/hsx_recycle/pages/task/index' . $query,
            ]);
        }
        $detailParams = array_filter([
            'id' => $orderId,
            'device_id' => $deviceId,
            'stage' => $stage,
        ], static fn($value): bool => $value !== '' && $value !== 0);
        return array_merge($target, [
            'route_key' => 'hsx_recycle.order.detail',
            'params' => $detailParams,
            'web_path' => 'site/recycle_order/list?' . http_build_query([
                'order_id' => $orderId,
                'device_id' => $deviceId,
                'stage' => $stage,
            ]),
            'miniapp_path' => 'addon/hsx_recycle/pages/order/detail?' . http_build_query($detailParams),
        ]);
    }

    /**
     * 新版结构化目标必须自洽。来源插件和目标插件允许不同，但目标插件、
     * route_key 与小程序 pagepath 不允许跨域，避免财务任务误开回收订单。
     */
    private function targetContractError(array $event): string
    {
        $target = is_array($event['target'] ?? null) ? $event['target'] : [];
        if ($target === []) return '';

        $routeKey = trim((string)($target['route_key'] ?? ''));
        $plugin = trim((string)($target['plugin'] ?? ''));
        // 兼容 1.0.0 已进入发件箱、尚未携带 plugin 的结构化消息。
        if ($plugin === '' && str_contains($routeKey, '.')) {
            $plugin = (string)strstr($routeKey, '.', true);
        }
        $webPath = trim((string)($target['web_path'] ?? ''));
        $miniappPath = ltrim(trim((string)($target['miniapp_path'] ?? '')), '/');
        if ($plugin === '') return '任务目标缺少所属插件，已停止发送';
        if ($routeKey === '') return '任务目标缺少路由标识，已停止发送';
        if (!str_starts_with($routeKey, $plugin . '.')) {
            return '任务目标插件与路由标识不一致，已停止发送';
        }
        if ($webPath === '' && $miniappPath === '') {
            return '任务目标缺少网页和小程序路径，已停止发送';
        }
        if ($miniappPath !== '' && !str_starts_with($miniappPath, 'addon/' . $plugin . '/')) {
            return '任务目标插件与小程序路径不一致，已停止发送';
        }
        if ($plugin === 'hsx_erp' && $webPath !== ''
            && !preg_match('#^(https?://[^/]+/)?site/hsx_erp(?:/|\?|$)#i', ltrim($webPath, '/'))) {
            return 'ERP任务的网页路径不属于ERP，已停止发送';
        }
        return '';
    }

    /** 业务插件在真正发件前拥有最终否决权，避免已完成任务仍产生企业微信提醒。 */
    private function taskInvalidReason(array $event): string
    {
        try {
            foreach ((array)event('HsxBusinessTaskValidate', $event) as $result) {
                if (is_array($result) && array_key_exists('valid', $result) && !$result['valid']) {
                    return trim((string)($result['reason'] ?? '任务已完成，无需继续通知'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('企业微信任务有效性校验失败', ['event_id' => (string)($event['event_id'] ?? ''), 'message' => $e->getMessage()]);
        }
        return '';
    }

    private function plainDescription(array $event): string
    {
        $rows = [];
        if (!empty($event['business_no'])) $rows[] = '业务单号：' . (string)$event['business_no'];
        if (!empty($event['imei'])) $rows[] = 'IMEI：' . (string)$event['imei'];
        if (!empty($event['assigner_name'])) $rows[] = '分配人：' . (string)$event['assigner_name'];
        $rows[] = '今日待处理 ' . max(0, (int)($event['pending_count'] ?? 0)) . ' 项';
        return implode("\n", $rows);
    }

    private function reportPlainDescription(array $event): string
    {
        $summary = (array)($event['summary'] ?? []);
        $rows = [];
        if (isset($summary['recycle_in_count'])) $rows[] = '回收入库：' . (int)$summary['recycle_in_count'] . ' 台';
        if (isset($summary['sale_count'])) $rows[] = '销售出库：' . (int)$summary['sale_count'] . ' 台';
        if (isset($summary['stock_count'])) $rows[] = '当前库存：' . (int)$summary['stock_count'] . ' 台';
        if (isset($summary['turnover_rate'])) $rows[] = '动销率：' . (float)$summary['turnover_rate'] . '%';
        foreach (array_slice((array)($event['top_staff'] ?? []), 0, 5) as $item) {
            if (!is_array($item)) continue;
            $rows[] = (string)($item['name'] ?? '员工') . ' · ' . (string)($item['role_name'] ?? '工作') . '：' . (int)($item['count'] ?? 0) . ' 项';
        }
        return implode("\n", $rows);
    }
}
