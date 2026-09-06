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
    public function sendTest(int $siteId, int $receiverUid): array
    {
        if ($siteId <= 0 || $receiverUid <= 0) throw new \InvalidArgumentException('站点或接收员工无效');
        $config = (new WecomDeliveryContextService())->resolve($siteId, true);
        $binding = WecomStaffBinding::where([
            ['site_id', '=', $siteId], ['uid', '=', $receiverUid], ['status', '=', 1],
        ])->findOrEmpty();
        if ($binding->isEmpty() || trim((string)$binding->wecom_userid) === '') {
            throw new \RuntimeException('请先让接收员工绑定企业微信身份');
        }
        if (($config['connection_mode'] ?? '') === 'provider'
            && (int)$binding->corp_authorization_id !== (int)($config['corp_authorization_id'] ?? 0)) {
            throw new \RuntimeException('接收员工的企业微信身份不属于当前授权企业，请重新绑定');
        }
        $user = SysUser::where('uid', '=', $receiverUid)->field('uid,username,real_name')->findOrEmpty();
        $receiverName = $user->isEmpty() ? ('员工' . $receiverUid)
            : (trim((string)$user->real_name) ?: trim((string)$user->username) ?: ('员工' . $receiverUid));
        $eventId = 'wecom:test:' . $siteId . ':' . $receiverUid . ':' . bin2hex(random_bytes(8));
        $event = [
            'site_id' => $siteId,
            'event_id' => $eventId,
            'title' => '企业微信通知已接通',
            'wecom_target' => [
                'plugin' => 'hsx_wecom',
                'route_key' => 'hsx_wecom.home',
                'web_url' => $this->targetUrl($config, 'site/hsx_wecom/config'),
                'miniapp_appid' => trim((string)($config['miniapp_appid'] ?? '')),
                'miniapp_path' => 'app/pages/index/index',
            ],
        ];
        $target = $this->target($event, $config);
        $now = time();
        $log = WecomMessageLog::create([
            'site_id' => $siteId,
            'corp_authorization_id' => (int)($config['corp_authorization_id'] ?? 0),
            'channel_code' => (string)($config['channel_code'] ?? ''),
            'auth_corpid' => (string)($config['auth_corpid'] ?? ''),
            'agent_id' => (int)($config['agent_id'] ?? 0),
            'event_id' => $eventId,
            'scene' => 'connection_test',
            'source_plugin' => 'hsx_wecom',
            'source_type' => 'connection_test',
            'source_id' => 0,
            'receiver_uid' => $receiverUid,
            'receiver_name' => $receiverName,
            'wecom_userid' => trim((string)$binding->wecom_userid),
            'title' => (string)$event['title'],
            'content' => '<div class="gray">当前站点与企业微信已连通</div><div class="normal">点击卡片即可进入后台管理端小程序</div>',
            'target_url' => $target['web_url'],
            'payload_json' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'status' => 'pending', 'retry_count' => 0, 'next_retry_at' => $now,
            'error_message' => '', 'create_at' => $now, 'update_at' => $now,
        ]);
        $sent = $this->dispatch((int)$log->id);
        $fresh = WecomMessageLog::where('id', '=', (int)$log->id)->findOrEmpty();
        return [
            'sent' => $sent ? 1 : 0,
            'message_id' => (int)$log->id,
            'status' => $fresh->isEmpty() ? ($sent ? 'success' : 'failed') : (string)$fresh->status,
            'receiver_name' => $receiverName,
            'error_message' => $fresh->isEmpty() ? '' : (string)$fresh->error_message,
        ];
    }

    public function enqueueBusinessReport(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $eventId = trim((string)($event['event_id'] ?? ''));
        $receiverUids = array_values(array_unique(array_filter(array_map('intval', (array)($event['receiver_uids'] ?? [])))));
        if ($siteId <= 0 || $eventId === '') return ['accepted' => false, 'reason' => 'invalid_event'];
        $config = (new WecomDeliveryContextService())->resolve($siteId);
        if (empty($config['enabled']) || empty($config['report_notice_enabled'])) {
            return ['accepted' => false, 'queued' => 0, 'reason' => 'report_notice_disabled'];
        }
        if (($config['connection_mode'] ?? '') === 'provider' && ($config['provider_status'] ?? '') !== 'authorized') {
            return ['accepted' => false, 'queued' => 0, 'reason' => 'provider_not_authorized'];
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
            } elseif (($config['connection_mode'] ?? '') === 'provider'
                && (int)$binding->corp_authorization_id !== (int)($config['corp_authorization_id'] ?? 0)) {
                $status = 'skipped';
                $error = '报告接收人的企业微信身份不属于当前授权企业，请重新绑定';
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
                'corp_authorization_id' => (int)($config['corp_authorization_id'] ?? 0),
                'channel_code' => (string)($config['channel_code'] ?? ''),
                'auth_corpid' => (string)($config['auth_corpid'] ?? ''),
                'agent_id' => (int)($config['agent_id'] ?? 0),
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
        $config = (new WecomDeliveryContextService())->resolve($siteId);
        $notifyAt = max(time(), (int)($event['notify_at'] ?? 0));
        $status = 'pending';
        $error = '';
        $targetError = $this->targetContractError($event);
        if (empty($config['enabled']) || empty($config['task_notice_enabled'])) {
            $status = 'skipped';
            $error = '企业微信任务通知未启用';
        } elseif (($config['connection_mode'] ?? '') === 'provider' && ($config['provider_status'] ?? '') !== 'authorized') {
            $status = 'skipped';
            $error = '客户企业微信尚未授权，请先完成一键授权';
        } elseif ($binding->isEmpty() || trim((string)$binding->wecom_userid) === '') {
            $status = 'skipped';
            $error = '责任人尚未绑定企业微信账号';
        } elseif (($config['connection_mode'] ?? '') === 'provider'
            && (int)$binding->corp_authorization_id !== (int)($config['corp_authorization_id'] ?? 0)) {
            $status = 'skipped';
            $error = '责任人的企业微信身份不属于当前授权企业，请重新绑定';
        } elseif ($targetError !== '') {
            $status = 'skipped';
            $error = $targetError;
        }

        $target = $this->target($event, $config);
        $event['wecom_target'] = $target;
        $log = WecomMessageLog::create([
            'site_id' => $siteId,
            'corp_authorization_id' => (int)($config['corp_authorization_id'] ?? 0),
            'channel_code' => (string)($config['channel_code'] ?? ''),
            'auth_corpid' => (string)($config['auth_corpid'] ?? ''),
            'agent_id' => (int)($config['agent_id'] ?? 0),
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
            'next_retry_at' => $status === 'pending' ? $notifyAt : 0,
            'error_message' => $error,
            'create_at' => time(),
            'update_at' => time(),
        ]);
        if ($status === 'pending' && $notifyAt <= time()) $this->dispatchQueuedMessages([(int)$log->id]);
        return ['queued' => true, 'id' => (int)$log->id, 'status' => $status];
    }

    public function dispatch(int $id): bool
    {
        $log = WecomMessageLog::where('id', '=', $id)->findOrEmpty();
        if ($log->isEmpty() || !in_array((string)$log->status, ['pending', 'failed'], true)) return false;
        try {
            $config = (new WecomDeliveryContextService())->resolve((int)$log->site_id, true);
            $binding = WecomStaffBinding::where([
                ['site_id', '=', (int)$log->site_id],
                ['uid', '=', (int)$log->receiver_uid],
                ['status', '=', 1],
            ])->findOrEmpty();
            if ($binding->isEmpty() || trim((string)$binding->wecom_userid) === '') {
                throw new \RuntimeException('接收员工尚未绑定企业微信账号');
            }
            if (($config['connection_mode'] ?? '') === 'provider'
                && (int)$binding->corp_authorization_id !== (int)($config['corp_authorization_id'] ?? 0)) {
                throw new \RuntimeException('接收员工绑定的企业微信身份与当前授权企业不一致');
            }
            $log->wecom_userid = trim((string)$binding->wecom_userid);
            $event = json_decode((string)$log->payload_json, true);
            if (!is_array($event)) $event = [];
            $isReport = (string)$log->scene === 'business_report';
            $isTest = (string)$log->scene === 'connection_test';
            $invalidReason = ($isReport || $isTest) ? '' : $this->taskInvalidReason($event);
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
            if (($config['credential_mode'] ?? $config['connection_mode'] ?? '') === 'provider'
                && trim((string)($target['miniapp_appid'] ?? '')) !== ''
                && trim((string)($target['miniapp_path'] ?? '')) !== '') {
                // 统一先进入带签名的站点入口，校验员工权限并切换 siteId 后再打开业务页。
                // 否则同一员工管理多个站点时，会沿用小程序本地缓存中的上一个站点。
                $target['miniapp_path'] = (new WecomEntryService())->issuePagePath(
                    (int)$log->site_id,
                    (string)$target['miniapp_path'],
                    (string)($target['route_key'] ?? '')
                );
            }
            $response = (new WecomClient())->sendTaskCard($config, (string)$log->wecom_userid, [
                'title' => (string)$log->title,
                'description' => (string)$log->content,
                'plain_content' => $isTest ? '测试成功：当前站点、企业微信身份和后台管理端入口已建立准确关联。'
                    : ($isReport ? $this->reportPlainDescription($event) : $this->plainDescription($event)),
                'summary' => $isTest ? '连通成功，点击进入后台管理端'
                    : ($isReport ? '经营数据已汇总，点击查看完整明细' : '任务已分配给你，请及时处理'),
                'url' => $target['web_url'],
                'web_url' => $target['web_url'],
                'miniapp_appid' => $target['miniapp_appid'],
                'miniapp_path' => $target['miniapp_path'],
                'button_text' => $isTest ? '进入管理端' : ($isReport ? '查看报告' : '查看任务'),
                'source_desc' => $isTest ? '连通测试' : ($isReport ? '经营报告' : '业务待办'),
            ]);
            $log->save([
                'status' => 'success',
                'corp_authorization_id' => (int)($config['corp_authorization_id'] ?? 0),
                'channel_code' => (string)($config['channel_code'] ?? ''),
                'auth_corpid' => (string)($config['auth_corpid'] ?? ''),
                'agent_id' => (int)($config['agent_id'] ?? 0),
                'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'provider_msgid' => trim((string)($response['msgid'] ?? $response['response_code'] ?? '')),
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
        if (!empty($event['customer_name'])) $rows[] = '<div class="normal">客户：' . htmlspecialchars((string)$event['customer_name']) . '</div>';
        if (!empty($event['logistics_pickup_address'])) $rows[] = '<div class="normal">取货地点：' . htmlspecialchars((string)$event['logistics_pickup_address']) . '</div>';
        if (!empty($event['logistics_vehicle_no'])) {
            $vehicle = trim((string)($event['logistics_name'] ?? '') . ' ' . (string)$event['logistics_vehicle_no']);
            $rows[] = '<div class="normal">物流车辆：' . htmlspecialchars($vehicle) . '</div>';
        }
        if (!empty($event['logistics_contact_mobile'])) {
            $contact = trim((string)($event['logistics_contact_name'] ?? '') . ' ' . (string)$event['logistics_contact_mobile']);
            $rows[] = '<div class="normal">现场联系：' . htmlspecialchars($contact) . '</div>';
        }
        if (!empty($event['imei'])) $rows[] = '<div class="normal">IMEI：' . htmlspecialchars((string)$event['imei']) . '</div>';
        if (!empty($event['assigner_name'])) $rows[] = '<div class="normal">分配人：' . htmlspecialchars((string)$event['assigner_name']) . '</div>';
        $rows[] = '<div class="highlight">今日待处理 ' . max(0, (int)($event['pending_count'] ?? 0)) . ' 项</div>';
        return implode('', $rows);
    }

    private function reportDescription(array $event): string
    {
        $summary = (array)($event['summary'] ?? []);
        $rows = ['<div class="gray">经营周期数据已自动汇总</div>'];
        $metrics = [
            'sale_amount' => ['销售额', 'money'], 'sale_profit' => ['销售毛利', 'money'],
            'sale_count' => ['销售件数', 'count'], 'recycle_in_count' => ['回收入库', 'count'],
            'gross_margin_rate' => ['毛利率', 'rate'], 'turnover_rate' => ['设备动销率', 'rate'],
            'todo_count' => ['当前待处理', 'item'], 'provider_error_count' => ['异常数据源', 'item'],
        ];
        foreach ($metrics as $key => [$label, $format]) {
            if (!array_key_exists($key, $summary)) continue;
            $value = (float)$summary[$key];
            $display = $format === 'money' ? ('¥' . number_format($value, 2, '.', '')) : (string)$summary[$key];
            $suffix = ['rate' => '%', 'count' => ' 件', 'item' => ' 项'][$format] ?? '';
            $rows[] = '<div class="normal">' . $label . '：' . htmlspecialchars($display) . $suffix . '</div>';
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
            $snapshotMiniappAppid = trim((string)($snapshot['miniapp_appid'] ?? ''));
            // 服务商模式下 AppID 属于当前 SaaS 通道，不能使用入队时的旧快照。
            if (($config['credential_mode'] ?? $config['connection_mode'] ?? '') === 'provider') {
                $snapshotMiniappAppid = trim((string)($config['miniapp_appid'] ?? ''));
            }
            return [
                'plugin' => trim((string)($snapshot['plugin'] ?? '')),
                'route_key' => trim((string)($snapshot['route_key'] ?? '')),
                'web_url' => trim((string)($snapshot['web_url'] ?? '')),
                'miniapp_appid' => $snapshotMiniappAppid,
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
            if ($miniappPath !== '') {
                $webUrl = '';
            } elseif ($miniappAppid !== '') {
                // 个别 PC 业务尚无移动管理端详情页。此时主入口仍进入管理端小程序，
                // 同时保留精确网页地址作为卡片的第二入口，不能发送一张无法点击的卡片。
                $miniappPath = 'app/pages/index/index';
            }
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
        if (!empty($event['customer_name'])) $rows[] = '客户：' . (string)$event['customer_name'];
        if (!empty($event['logistics_pickup_address'])) $rows[] = '取货地点：' . (string)$event['logistics_pickup_address'];
        if (!empty($event['logistics_vehicle_no'])) $rows[] = '物流车辆：' . trim((string)($event['logistics_name'] ?? '') . ' ' . (string)$event['logistics_vehicle_no']);
        if (!empty($event['logistics_contact_mobile'])) $rows[] = '现场联系：' . trim((string)($event['logistics_contact_name'] ?? '') . ' ' . (string)$event['logistics_contact_mobile']);
        if (!empty($event['imei'])) $rows[] = 'IMEI：' . (string)$event['imei'];
        if (!empty($event['assigner_name'])) $rows[] = '分配人：' . (string)$event['assigner_name'];
        $rows[] = '今日待处理 ' . max(0, (int)($event['pending_count'] ?? 0)) . ' 项';
        return implode("\n", $rows);
    }

    private function reportPlainDescription(array $event): string
    {
        $summary = (array)($event['summary'] ?? []);
        $rows = [];
        if (isset($summary['sale_amount'])) $rows[] = '销售额：¥' . number_format((float)$summary['sale_amount'], 2, '.', '');
        if (isset($summary['sale_profit'])) $rows[] = '销售毛利：¥' . number_format((float)$summary['sale_profit'], 2, '.', '');
        if (isset($summary['recycle_in_count'])) $rows[] = '回收入库：' . (int)$summary['recycle_in_count'] . ' 台';
        if (isset($summary['sale_count'])) $rows[] = '销售件数：' . (float)$summary['sale_count'] . ' 件';
        if (isset($summary['gross_margin_rate'])) $rows[] = '毛利率：' . (float)$summary['gross_margin_rate'] . '%';
        if (isset($summary['turnover_rate'])) $rows[] = '设备动销率：' . (float)$summary['turnover_rate'] . '%';
        if (isset($summary['todo_count'])) $rows[] = '当前待处理：' . (int)$summary['todo_count'] . ' 项';
        foreach (array_slice((array)($event['top_staff'] ?? []), 0, 5) as $item) {
            if (!is_array($item)) continue;
            $rows[] = (string)($item['name'] ?? '员工') . ' · ' . (string)($item['role_name'] ?? '工作') . '：' . (int)($item['count'] ?? 0) . ' 项';
        }
        return implode("\n", $rows);
    }
}
