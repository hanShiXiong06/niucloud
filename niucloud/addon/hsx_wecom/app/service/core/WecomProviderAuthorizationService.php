<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomAuthorizationIntent;
use addon\hsx_wecom\app\model\WecomCallbackEvent;
use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use addon\hsx_wecom\app\model\WecomMessageLog;
use addon\hsx_wecom\app\model\WecomProviderSuite;
use addon\hsx_wecom\app\model\WecomStaffBinding;
use addon\hsx_wecom\app\support\WecomProviderCipher;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

final class WecomProviderAuthorizationService
{
    public function startInstall(int $siteId, int $uid, string $returnUrl = ''): array
    {
        if ($siteId <= 0) throw new CommonException('当前站点信息无效');
        $suite = (new WecomProviderConfigService())->active();
        if ($suite->isEmpty()) throw new CommonException('平台尚未启用企业微信服务商应用');
        $credential = new WecomProviderCredentialService();
        $preAuth = $credential->preAuthCode($suite);
        $credential->setSessionInfo($suite, $preAuth['pre_auth_code']);

        $state = bin2hex(random_bytes(24));
        $returnUrl = $this->safeReturnUrl($suite, $returnUrl, '/site/hsx_wecom/config');
        WecomAuthorizationIntent::create([
            'state' => $state,
            'purpose' => 'install',
            'site_id' => $siteId,
            'uid' => $uid,
            'provider_suite_id' => (int)$suite->id,
            'pre_auth_code' => $preAuth['pre_auth_code'],
            'return_url' => $returnUrl,
            'meta_json' => [],
            'expires_at' => time() + min(1200, max(60, $preAuth['expires_in'])),
            'used_at' => 0,
            'create_at' => time(),
        ]);
        return [
            'url' => $credential->installUrl($suite, $preAuth['pre_auth_code'], $state),
            'expires_in' => min(1200, max(60, $preAuth['expires_in'])),
            'channel_code' => (string)$suite->channel_code,
        ];
    }

    public function completeInstall(string $channel, string $state, string $authCode): string
    {
        $suite = $this->suite($channel);
        $intent = $this->intent($suite, $state, 'install');
        try {
            $result = (new WecomProviderCredentialService())->permanentCode($suite, $authCode);
            $corp = is_array($result['auth_corp_info'] ?? null) ? $result['auth_corp_info'] : [];
            $authInfo = is_array($result['auth_info'] ?? null) ? $result['auth_info'] : [];
            $authCorpId = trim((string)($corp['corpid'] ?? $corp['corp_id'] ?? ''));
            $permanentCode = trim((string)($result['permanent_code'] ?? ''));
            $agentId = $this->agentId($authInfo);
            if ($authCorpId === '' || $permanentCode === '' || $agentId <= 0) {
                throw new CommonException('企业微信授权结果缺少 CorpID、永久授权码或 AgentId');
            }

            Db::transaction(function () use ($suite, $intent, $result, $corp, $authInfo, $authCorpId, $permanentCode, $agentId): void {
                $occupied = WecomCorpAuthorization::where([
                    ['provider_suite_id', '=', (int)$suite->id],
                    ['auth_corpid', '=', $authCorpId],
                ])->findOrEmpty();
                if (!$occupied->isEmpty() && (int)$occupied->site_id !== (int)$intent->site_id) {
                    throw new CommonException('该企业微信已经绑定到本系统的其他站点，请先核对客户站点');
                }
                $authorization = WecomCorpAuthorization::where([
                    ['site_id', '=', (int)$intent->site_id],
                    ['provider_suite_id', '=', (int)$suite->id],
                ])->findOrEmpty();
                $now = time();
                if (!$authorization->isEmpty()
                    && trim((string)$authorization->auth_corpid) !== ''
                    && trim((string)$authorization->auth_corpid) !== $authCorpId) {
                    $this->invalidateAuthorizationIdentity(
                        $authorization,
                        '站点已重新授权到另一家企业，请员工重新绑定企业微信身份'
                    );
                }
                $data = [
                    'auth_corpid' => $authCorpId,
                    'permanent_code_cipher' => WecomProviderCipher::encrypt($permanentCode),
                    'agent_id' => $agentId,
                    'corp_name' => trim((string)($corp['corp_name'] ?? $corp['corp_full_name'] ?? '')),
                    'auth_info_json' => [
                        'auth_corp_info' => $corp,
                        'auth_info' => $authInfo,
                        'edition_info' => $result['edition_info'] ?? [],
                    ],
                    'status' => 'authorized',
                    'last_error' => '',
                    'authorized_at' => $now,
                    'changed_at' => $now,
                    'cancelled_at' => 0,
                    'update_at' => $now,
                ];
                if ($authorization->isEmpty()) {
                    WecomCorpAuthorization::create(array_merge($data, [
                        'site_id' => (int)$intent->site_id,
                        'provider_suite_id' => (int)$suite->id,
                        'create_at' => $now,
                    ]));
                } else {
                    $authorization->save($data);
                }
                $intent->save(['used_at' => $now]);
            });

            $currentAuthorization = WecomCorpAuthorization::where([
                ['site_id', '=', (int)$intent->site_id],
                ['provider_suite_id', '=', (int)$suite->id],
            ])->findOrEmpty();
            if (!$currentAuthorization->isEmpty()) {
                (new WecomProviderCredentialService())->clearCorpToken($currentAuthorization);
            }

            $stored = (new WecomConfigService())->get((int)$intent->site_id);
            (new WecomConfigService())->save((int)$intent->site_id, array_replace($stored, [
                'connection_mode' => 'provider',
                'enabled' => 1,
                'jump_mode' => 'miniapp',
                'task_notice_enabled' => 1,
            ]));
            return $this->appendResult((string)$intent->return_url, 'success');
        } catch (\Throwable $e) {
            Log::error('企业微信服务商授权落库失败', ['channel' => $channel, 'site_id' => (int)$intent->site_id, 'message' => $e->getMessage()]);
            return $this->appendResult((string)$intent->return_url, 'failed', $e->getMessage());
        }
    }

    public function refreshSiteAuthorization(int $siteId): array
    {
        $suite = (new WecomProviderConfigService())->active();
        if ($suite->isEmpty()) throw new CommonException('平台尚未启用企业微信服务商应用');
        $authorization = WecomCorpAuthorization::where([
            ['site_id', '=', $siteId], ['provider_suite_id', '=', (int)$suite->id],
        ])->findOrEmpty();
        if ($authorization->isEmpty()) throw new CommonException('当前站点尚未授权企业微信');
        if ((string)$authorization->status === 'cancelled') throw new CommonException('企业已取消授权，请重新授权');

        try {
            $permanentCode = WecomProviderCipher::decrypt((string)$authorization->permanent_code_cipher);
            $result = (new WecomProviderCredentialService())->authInfo($suite, (string)$authorization->auth_corpid, $permanentCode);
            $corp = is_array($result['auth_corp_info'] ?? null) ? $result['auth_corp_info'] : [];
            $authInfo = is_array($result['auth_info'] ?? null) ? $result['auth_info'] : [];
            $agentId = $this->agentId($authInfo);
            $authorization->save([
                'agent_id' => $agentId > 0 ? $agentId : (int)$authorization->agent_id,
                'corp_name' => trim((string)($corp['corp_name'] ?? $authorization->corp_name)),
                'auth_info_json' => $result,
                'status' => 'authorized', 'last_error' => '', 'changed_at' => time(), 'update_at' => time(),
            ]);
        } catch (\Throwable $e) {
            $authorization->save(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500), 'update_at' => time()]);
            throw $e;
        }
        return (new WecomProviderConfigService())->siteStatus($siteId);
    }

    public function memberBindUrl(int $siteId, int $uid, string $returnUrl = ''): array
    {
        $suite = (new WecomProviderConfigService())->active();
        if ($suite->isEmpty()) throw new CommonException('平台尚未启用企业微信服务商应用');
        $authorization = WecomCorpAuthorization::where([
            ['site_id', '=', $siteId], ['provider_suite_id', '=', (int)$suite->id], ['status', '=', 'authorized'],
        ])->findOrEmpty();
        if ($authorization->isEmpty()) throw new CommonException('请先完成客户企业授权');
        $state = bin2hex(random_bytes(24));
        $returnUrl = $this->safeReturnUrl($suite, $returnUrl, '/site/hsx_wecom/config?tab=staff');
        WecomAuthorizationIntent::create([
            'state' => $state, 'purpose' => 'member_bind', 'site_id' => $siteId, 'uid' => $uid,
            'provider_suite_id' => (int)$suite->id, 'pre_auth_code' => '', 'return_url' => $returnUrl,
            'meta_json' => [], 'expires_at' => time() + 600, 'used_at' => 0, 'create_at' => time(),
        ]);
        return ['url' => (new WecomProviderCredentialService())->memberOauthUrl($suite, $authorization, $state)];
    }

    public function completeMemberBind(string $channel, string $state, string $code): string
    {
        $suite = $this->suite($channel);
        $intent = $this->intent($suite, $state, 'member_bind');
        try {
            $authorization = WecomCorpAuthorization::where([
                ['site_id', '=', (int)$intent->site_id],
                ['provider_suite_id', '=', (int)$suite->id],
                ['status', '=', 'authorized'],
            ])->findOrEmpty();
            if ($authorization->isEmpty()) throw new CommonException('当前站点企业微信授权已失效');
            $info = (new WecomProviderCredentialService())->userInfo3rd($suite, $code);
            $corpId = trim((string)($info['CorpId'] ?? $info['corpid'] ?? $info['corp_id'] ?? ''));
            $userId = trim((string)($info['UserId'] ?? $info['userid'] ?? ''));
            $openUserId = trim((string)($info['open_userid'] ?? $info['OpenUserId'] ?? ''));
            $recipientId = $openUserId !== '' ? $openUserId : $userId;
            if ($corpId === '' || $corpId !== (string)$authorization->auth_corpid) throw new CommonException('当前企业微信成员不属于已授权企业');
            if ($recipientId === '') throw new CommonException('未识别到企业微信成员身份，请在企业微信客户端中打开');

            $data = [
                'corp_authorization_id' => (int)$authorization->id,
                'wecom_userid' => $recipientId,
                'open_userid' => $openUserId,
                'id_scope' => $openUserId !== '' ? 'open_userid' : 'userid',
                'bind_source' => 'oauth',
                'verified_at' => time(),
                'status' => 1,
                'update_at' => time(),
            ];
            Db::transaction(function () use ($intent, $recipientId, $data): void {
                $duplicate = WecomStaffBinding::where([
                    ['site_id', '=', (int)$intent->site_id], ['wecom_userid', '=', $recipientId],
                ])->where('uid', '<>', (int)$intent->uid)->lock(true)->findOrEmpty();
                if (!$duplicate->isEmpty()) throw new CommonException('该企业微信成员已经绑定其他系统员工');
                $binding = WecomStaffBinding::where([
                    ['site_id', '=', (int)$intent->site_id], ['uid', '=', (int)$intent->uid],
                ])->lock(true)->findOrEmpty();
                if ($binding->isEmpty()) {
                    WecomStaffBinding::create(array_merge($data, [
                        'site_id' => (int)$intent->site_id, 'uid' => (int)$intent->uid, 'create_at' => time(),
                    ]));
                } else {
                    $binding->save($data);
                }
                $intent->save(['used_at' => time()]);
            });
            return $this->appendResult((string)$intent->return_url, 'bind_success');
        } catch (\Throwable $e) {
            return $this->appendResult((string)$intent->return_url, 'bind_failed', $e->getMessage());
        }
    }

    public function handleCallbackEvent(WecomProviderSuite $suite, array $payload): void
    {
        $payloadSuiteId = trim((string)($payload['SuiteId'] ?? $payload['suite_id'] ?? ''));
        if ($payloadSuiteId !== '' && $payloadSuiteId !== trim((string)$suite->suite_id)) {
            throw new CommonException('企业微信回调 SuiteID 与当前服务商通道不一致');
        }
        $infoType = trim((string)($payload['InfoType'] ?? $payload['info_type'] ?? 'unknown'));
        $authCorpId = trim((string)($payload['AuthCorpId'] ?? $payload['auth_corpid'] ?? ''));
        $eventTime = (int)($payload['TimeStamp'] ?? $payload['CreateTime'] ?? time());
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $hash = hash('sha256', $json);
        $eventKey = hash('sha256', (int)$suite->id . '|' . $infoType . '|' . $eventTime . '|' . $authCorpId . '|' . $hash);
        $event = WecomCallbackEvent::where('event_key', '=', $eventKey)->findOrEmpty();
        if ($event->isEmpty()) {
            try {
                $event = WecomCallbackEvent::create([
                    'event_key' => $eventKey, 'provider_suite_id' => (int)$suite->id,
                    'auth_corpid' => $authCorpId, 'info_type' => $infoType, 'event_time' => $eventTime,
                    'payload_json' => $payload, 'payload_hash' => $hash, 'status' => 'pending',
                    'error_message' => '', 'processed_at' => 0, 'create_at' => time(),
                ]);
            } catch (\Throwable $e) {
                // 企业微信可能并发重试同一回调。唯一键冲突时由已落库事件继续处理。
                $event = WecomCallbackEvent::where('event_key', '=', $eventKey)->findOrEmpty();
                if ($event->isEmpty()) throw $e;
            }
        }
        if ((string)$event->status === 'processed') return;
        if ((string)$event->status === 'processing' && (int)$event->processed_at < time() - 120) {
            WecomCallbackEvent::where('id', '=', (int)$event->id)->where('status', '=', 'processing')
                ->update(['status' => 'failed', 'error_message' => '上次处理超时，等待企业微信重试']);
        }
        $claimed = WecomCallbackEvent::where('id', '=', (int)$event->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'processing', 'processed_at' => time(), 'error_message' => '']);
        if ($claimed <= 0) return;
        $event = WecomCallbackEvent::where('id', '=', (int)$event->id)->findOrEmpty();
        try {
            if ($infoType === 'suite_ticket') {
                $ticket = trim((string)($payload['SuiteTicket'] ?? ''));
                if ($ticket === '') throw new CommonException('企业微信 SuiteTicket 回调内容为空');
                $suite->save(['suite_ticket' => $ticket, 'suite_ticket_at' => time(), 'last_error' => '', 'update_at' => time()]);
                (new WecomProviderCredentialService())->clearSuiteToken($suite);
            } elseif ($infoType === 'cancel_auth' && $authCorpId !== '') {
                $authorization = WecomCorpAuthorization::where([
                    ['provider_suite_id', '=', (int)$suite->id], ['auth_corpid', '=', $authCorpId],
                ])->findOrEmpty();
                if (!$authorization->isEmpty()) {
                    $this->invalidateAuthorizationIdentity($authorization, '企业已取消授权，请重新授权并绑定员工');
                    $authorization->save(['status' => 'cancelled', 'cancelled_at' => time(), 'last_error' => '企业已取消授权', 'update_at' => time()]);
                }
            } elseif ($infoType === 'change_auth' && $authCorpId !== '') {
                $authorization = WecomCorpAuthorization::where([
                    ['provider_suite_id', '=', (int)$suite->id], ['auth_corpid', '=', $authCorpId],
                ])->findOrEmpty();
                if (!$authorization->isEmpty()) {
                    $authorization->save(['status' => 'changed', 'changed_at' => time(), 'last_error' => '', 'update_at' => time()]);
                    $this->refreshSiteAuthorization((int)$authorization->site_id);
                }
            }
            $event->save(['status' => 'processed', 'processed_at' => time(), 'error_message' => '']);
        } catch (\Throwable $e) {
            $event->save(['status' => 'failed', 'processed_at' => time(), 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            $suite->save(['last_error' => mb_substr($e->getMessage(), 0, 500), 'update_at' => time()]);
            throw $e;
        }
    }

    private function suite(string $channel): WecomProviderSuite
    {
        $suite = (new WecomProviderConfigService())->byChannel($channel);
        if ($suite->isEmpty() || (string)$suite->status !== 'enabled') throw new CommonException('企业微信服务商通道不存在或未启用');
        return $suite;
    }

    private function intent(WecomProviderSuite $suite, string $state, string $purpose): WecomAuthorizationIntent
    {
        $state = trim($state);
        if ($state === '') throw new CommonException('企业微信授权状态参数缺失');
        $intent = WecomAuthorizationIntent::where([
            ['state', '=', $state], ['provider_suite_id', '=', (int)$suite->id], ['purpose', '=', $purpose],
        ])->findOrEmpty();
        if ($intent->isEmpty() || (int)$intent->used_at > 0 || (int)$intent->expires_at < time()) {
            throw new CommonException('授权链接已失效，请返回系统重新发起');
        }
        return $intent;
    }

    private function agentId(array $authInfo): int
    {
        $agents = is_array($authInfo['agent'] ?? null) ? $authInfo['agent'] : [];
        $agentIds = [];
        foreach ($agents as $agent) {
            if (is_array($agent) && (int)($agent['agentid'] ?? $agent['agent_id'] ?? 0) > 0) {
                $agentIds[] = (int)($agent['agentid'] ?? $agent['agent_id']);
            }
        }
        $agentIds = array_values(array_unique($agentIds));
        if (count($agentIds) > 1) {
            throw new CommonException('本次授权返回了多个企业微信应用，无法确定通知应用，请在服务商后台只保留当前应用后重新授权');
        }
        return $agentIds[0] ?? 0;
    }

    private function safeReturnUrl(WecomProviderSuite $suite, string $url, string $fallbackPath): string
    {
        $base = rtrim((string)$suite->web_base_url, '/');
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) throw new CommonException('服务商 SaaS 管理端域名未配置');
        $url = trim($url);
        if ($url === '') return $base . $fallbackPath;
        $baseOrigin = $this->origin($base);
        $urlOrigin = $this->origin($url);
        return $baseOrigin !== '' && hash_equals($baseOrigin, $urlOrigin) ? $url : $base . $fallbackPath;
    }

    private function origin(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') return '';
        if ($scheme !== 'https' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return '';
        $port = (int)(parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
        return $scheme . '://' . $host . ':' . $port;
    }

    private function invalidateAuthorizationIdentity(WecomCorpAuthorization $authorization, string $reason): void
    {
        WecomStaffBinding::where([
            ['site_id', '=', (int)$authorization->site_id],
            ['corp_authorization_id', '=', (int)$authorization->id],
        ])->update([
            'status' => 0,
            'verified_at' => 0,
            'update_at' => time(),
        ]);
        WecomMessageLog::where('site_id', '=', (int)$authorization->site_id)
            ->where('corp_authorization_id', '=', (int)$authorization->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'skipped',
                'next_retry_at' => 0,
                'error_message' => mb_substr($reason, 0, 500),
                'update_at' => time(),
            ]);
    }

    private function appendResult(string $url, string $result, string $message = ''): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $query = ['wecom_result' => $result];
        if ($message !== '') $query['wecom_message'] = mb_substr($message, 0, 160);
        return $url . $separator . http_build_query($query);
    }
}
